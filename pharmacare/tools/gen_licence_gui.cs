// PharmaCare — Gestionnaire de licences (interface WinForms, côté développeur).
//
// Pilote tools/gen_licence.php en mode --json via PHP CLI. Toute la logique
// crypto (signature RSA, HMAC, ledger, manifeste d'intégrité, openssl.cnf) reste
// dans PHP (licence_lib.php) : ce .exe n'est qu'une interface graphique.
//
// Aucune élévation UAC requise (manifeste asInvoker). Outil dev-only : ne
// JAMAIS livrer ce .exe chez le client (tools/ est exclu des bundles de déploiement).
using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.Drawing;
using System.IO;
using System.Reflection;
using System.Text;
using System.Web.Script.Serialization;
using System.Windows.Forms;

[assembly: AssemblyTitle("PharmaCare Licence Manager")]
[assembly: AssemblyProduct("PharmaCare Licence Manager")]
[assembly: AssemblyCompany("RADNEX")]
[assembly: AssemblyVersion("1.3.2.0")]
[assembly: AssemblyFileVersion("1.3.2.0")]
[assembly: AssemblyInformationalVersion("1.3.2.0 — portable (runtime PHP embarqué, statut auto-rafraîchi)")]

namespace PharmaCareLicence
{
    static class Program
    {
        [STAThread]
        static void Main()
        {
            Application.EnableVisualStyles();
            Application.SetCompatibleTextRenderingDefault(false);
            Application.Run(new MainForm());
        }
    }

    sealed class MainForm : Form
    {
        private static readonly Color Bg   = Color.FromArgb(248, 250, 252);
        private static readonly Color Ink  = Color.FromArgb(17, 24, 39);
        private static readonly Color Mute = Color.FromArgb(90, 100, 116);
        private static readonly Color Brand= Color.FromArgb(22, 101, 52);
        private static readonly Color Card = Color.White;
        private static readonly Color ErrC = Color.FromArgb(185, 28, 28);

        private FlowLayoutPanel _flow;
        private ComboBox _instance;
        private RadioButton _fmtShort, _fmtLong;
        private RadioButton _modePack, _modeSet;
        private NumericUpDown _value;
        private DateTimePicker _expire;
        private TextBox _code;
        private NumericUpDown _freeCap;
        private DataGridView _grid;
        private Label _status;
        private string _php;
        private Timer _refresh;
        private bool _loaded;
        private DateTime _lastRefresh = DateTime.MinValue;
        private bool? _lastPrivOk;

        public MainForm()
        {
            Text = "PharmaCare — Gestionnaire de licences";
            StartPosition = FormStartPosition.CenterScreen;
            Width = 840; Height = 740;                 // tient sur un écran 1366×768
            MinimumSize = new Size(700, 550);          // fenêtre redimensionnable
            FormBorderStyle = FormBorderStyle.Sizable;
            MaximizeBox = true;
            BackColor = Bg;
            Font = new Font("Segoe UI", 9F);
            AutoScaleMode = AutoScaleMode.Dpi;         // net sur écrans 125/150 %

            // ── Bandeau ──
            var banner = new Panel { Dock = DockStyle.Top, Height = 68, BackColor = Color.White };
            var logo = new Panel { Location = new Point(22, 13), Size = new Size(42, 42), BackColor = Brand };
            logo.Controls.Add(new Label { Dock = DockStyle.Fill, Text = "Rx", ForeColor = Color.White,
                Font = new Font("Segoe UI", 15F, FontStyle.Bold), TextAlign = ContentAlignment.MiddleCenter });
            var lt = new Label { Text = "Gestionnaire de licences PharmaCare", Font = new Font("Segoe UI", 14F, FontStyle.Bold),
                ForeColor = Ink, Location = new Point(78, 12), AutoSize = true };
            var ls = new Label { Text = "Outil développeur — émission de codes d'activation (RSA / HMAC) — portable, PHP embarqué",
                Font = new Font("Segoe UI", 9F), ForeColor = Mute, Location = new Point(78, 37), AutoSize = true };
            banner.Controls.AddRange(new Control[] { logo, lt, ls });
            Controls.Add(banner);

            // ── Contenu défilant ──
            _flow = new FlowLayoutPanel
            {
                Dock = DockStyle.Fill, FlowDirection = FlowDirection.TopDown, WrapContents = false,
                AutoScroll = true, BackColor = Bg, Padding = new Padding(16)
            };
            Controls.Add(_flow);
            _flow.Resize += (s, e) => StretchCards();
            _flow.ClientSizeChanged += (s, e) => StretchCards();   // scrollbar qui apparaît/disparaît

            // ── Bas de page (statut) ──
            var foot = new Panel { Dock = DockStyle.Bottom, Height = 34, BackColor = Color.White };
            _status = new Label { Dock = DockStyle.Fill, TextAlign = ContentAlignment.MiddleLeft,
                AutoEllipsis = true, Padding = new Padding(16, 0, 12, 0),
                ForeColor = Ink, Font = new Font("Segoe UI", 9F, FontStyle.Bold) };
            foot.Controls.Add(_status);
            Controls.Add(foot);

            BuildEmitCard();
            BuildCodeCard();
            BuildFreeCapCard();
            BuildLedgerCard();

            _fmtShort.CheckedChanged += (s, e) => _expire.Enabled = !_fmtShort.Checked;

            // ── Rafraîchissement auto du statut ──
            // Le statut (clé privée, ledger, palier gratuit) était figé à
            // l'ouverture : un message d'erreur obsolète restait affiché même
            // après restauration de la clé. On relance --op=status quand la
            // fenêtre reprend le focus et périodiquement (10 s).
            _refresh = new Timer { Interval = 10000 };
            _refresh.Tick += (s, e) => RefreshStatus();
            // Activated se déclenche aussi au premier affichage (après Init) :
            // garde-fou temporel pour ne pas tout relancer deux fois.
            Activated += (s, e) =>
            {
                if (_loaded && (DateTime.Now - _lastRefresh).TotalSeconds >= 2) RefreshStatus();
            };
            Load += (s, e) => Init();
        }

        // ═══ Cartes ═════════════════════════════════════════════════════════
        private Panel MakeCard(string title, int height)
        {
            var p = new Panel
            {
                Width = 740, Height = height, BackColor = Card, BorderStyle = BorderStyle.FixedSingle,
                Margin = new Padding(0, 0, 0, 12)
            };
            p.Controls.Add(new Label
            {
                Text = title, Font = new Font("Segoe UI", 11F, FontStyle.Bold), ForeColor = Brand,
                Location = new Point(16, 10), AutoSize = true
            });
            return p;
        }

        private void BuildEmitCard()
        {
            var c = MakeCard("Émettre un code", 232);

            c.Controls.Add(new Label { Text = "Identifiant d'instance du client", Location = new Point(16, 40), AutoSize = true, ForeColor = Mute });
            _instance = new ComboBox
            {
                Location = new Point(16, 60), Width = 410, DropDownStyle = ComboBoxStyle.DropDown,
                AutoCompleteMode = AutoCompleteMode.SuggestAppend, AutoCompleteSource = AutoCompleteSource.ListItems
            };
            // Entrée = générer directement
            _instance.KeyDown += (s, e) => { if (e.KeyCode == Keys.Enter) { e.SuppressKeyPress = true; OnGenerate(s, e); } };
            c.Controls.Add(_instance);

            c.Controls.Add(new Label { Text = "Format", Location = new Point(16, 92), AutoSize = true, ForeColor = Mute });
            _fmtShort = new RadioButton { Text = "Court (SMS, LLLL-NNNNNN-CCCCCCCC)", Location = new Point(80, 90), AutoSize = true, Checked = true };
            _fmtLong  = new RadioButton { Text = "Long (signé RSA)", Location = new Point(360, 90), AutoSize = true };
            c.Controls.AddRange(new Control[] { _fmtShort, _fmtLong });

            c.Controls.Add(new Label { Text = "Mode", Location = new Point(16, 122), AutoSize = true, ForeColor = Mute });
            _modePack = new RadioButton { Text = "Pack (+N lignes)", Location = new Point(80, 120), AutoSize = true, Checked = true };
            _modeSet  = new RadioButton { Text = "Cap absolu (fixe le plafond)", Location = new Point(260, 120), AutoSize = true };
            c.Controls.AddRange(new Control[] { _modePack, _modeSet });

            c.Controls.Add(new Label { Text = "Lignes / plafond", Location = new Point(16, 152), AutoSize = true, ForeColor = Mute });
            _value = new NumericUpDown { Location = new Point(160, 150), Width = 120, Minimum = 1, Maximum = 1000000000, Value = 5000 };
            c.Controls.Add(_value);

            c.Controls.Add(new Label { Text = "Expiration (long uniquement)", Location = new Point(300, 152), AutoSize = true, ForeColor = Mute });
            _expire = new DateTimePicker { Location = new Point(470, 150), Width = 230, Format = DateTimePickerFormat.Custom, CustomFormat = "dd/MM/yyyy HH:mm", Enabled = false };
            c.Controls.Add(_expire);

            var btn = new Button
            {
                Text = "Générer le code", BackColor = Brand, ForeColor = Color.White, FlatStyle = FlatStyle.Flat,
                Size = new Size(170, 40), Location = new Point(552, 178), Font = new Font("Segoe UI", 9.5F, FontStyle.Bold)
            };
            btn.FlatAppearance.BorderSize = 0;
            btn.Click += OnGenerate;
            c.Controls.Add(btn);

            _flow.Controls.Add(c);
        }

        private void BuildCodeCard()
        {
            var c = MakeCard("Code d'activation", 132);

            _code = new TextBox
            {
                Location = new Point(16, 38), Width = 708, Height = 56, ReadOnly = true, Multiline = true,
                Anchor = AnchorStyles.Left | AnchorStyles.Top | AnchorStyles.Right,
                Font = new Font("Consolas", 10F), BackColor = Color.FromArgb(13, 22, 34), ForeColor = Color.FromArgb(245, 158, 11),
                BorderStyle = BorderStyle.FixedSingle
            };
            c.Controls.Add(_code);

            var copy = new Button { Text = "Copier le code", FlatStyle = FlatStyle.Flat, Size = new Size(150, 30), Location = new Point(16, 98) };
            copy.FlatAppearance.BorderColor = Mute;
            copy.Click += OnCopy;
            c.Controls.Add(copy);

            _flow.Controls.Add(c);
        }

        private void BuildFreeCapCard()
        {
            var c = MakeCard("Palier gratuit", 80);

            c.Controls.Add(new Label { Text = "Lignes offertes à tout nouveau client (const LICENCE_FREE_CAP)",
                Location = new Point(16, 40), AutoSize = true, ForeColor = Mute, Font = new Font("Segoe UI", 8.5F) });
            _freeCap = new NumericUpDown { Location = new Point(480, 38), Width = 140, Minimum = 1, Maximum = 1000000000, Value = 150 };
            c.Controls.Add(_freeCap);
            var b = new Button { Text = "Enregistrer", FlatStyle = FlatStyle.Flat, Size = new Size(110, 30), Location = new Point(628, 37) };
            b.FlatAppearance.BorderColor = Mute;
            b.Click += OnSetFree;
            c.Controls.Add(b);

            _flow.Controls.Add(c);
        }

        private void BuildLedgerCard()
        {
            var c = MakeCard("Clients enregistrés", 230);

            _grid = new DataGridView
            {
                Location = new Point(16, 40), Width = 708, Height = 170,
                Anchor = AnchorStyles.Left | AnchorStyles.Top | AnchorStyles.Right,
                AllowUserToAddRows = false, AllowUserToDeleteRows = false, AllowUserToResizeRows = false,
                ReadOnly = true, RowHeadersVisible = false, SelectionMode = DataGridViewSelectionMode.FullRowSelect,
                BackgroundColor = Card, BorderStyle = BorderStyle.FixedSingle, AutoSizeColumnsMode = DataGridViewAutoSizeColumnsMode.Fill,
                Font = new Font("Segoe UI", 9F)
            };
            _grid.Columns.Add(new DataGridViewTextBoxColumn { Name = "inst", HeaderText = "Instance" });
            _grid.Columns.Add(new DataGridViewTextBoxColumn { Name = "cap", HeaderText = "Plafond" });
            _grid.Columns.Add(new DataGridViewTextBoxColumn { Name = "cnt", HeaderText = "Counter" });
            _grid.Columns.Add(new DataGridViewTextBoxColumn { Name = "exp", HeaderText = "Expire" });
            var cReset = new DataGridViewButtonColumn { Name = "reset", Text = "↺", HeaderText = "", UseColumnTextForButtonValue = true, Width = 40, FillWeight = 40 };
            var cRem = new DataGridViewButtonColumn { Name = "remove", Text = "✕", HeaderText = "", UseColumnTextForButtonValue = true, Width = 40, FillWeight = 40 };
            _grid.Columns.Add(cReset);
            _grid.Columns.Add(cRem);
            _grid.CellContentClick += OnGridCellClick;
            // Double-clic sur une ligne → pré-remplit l'identifiant d'instance
            _grid.CellDoubleClick += (s, e) =>
            {
                if (e.RowIndex >= 0)
                {
                    _instance.Text = Convert.ToString(_grid.Rows[e.RowIndex].Cells[0].Value);
                    _instance.Focus();
                }
            };
            c.Controls.Add(_grid);

            _flow.Controls.Add(c);
        }

        // ═══ Résolution PHP ══════════════════════════════════════════════════
        private static string AppDir()
        {
            return Path.GetDirectoryName(Assembly.GetExecutingAssembly().Location);
        }
        private static string GenScript()
        {
            return Path.Combine(AppDir(), "gen_licence.php");
        }
        private static string ResolvePhp()
        {
            // 1) Runtime PHP portable embarqué : dossier php\ à côté de l'exe
            //    (bundle PharmaCare-Licence-Manager — fonctionne sur toute machine
            //    Windows sans XAMPP ni PHP installé).
            string portable = Path.Combine(AppDir(), "php", "php.exe");
            if (File.Exists(portable)) return portable;
            // 2) XAMPP local (repo complet sur une machine de dev), puis PATH.
            string[] cands = { @"C:\xampp\php\php.exe", @"C:\xampp64\php\php.exe" };
            foreach (var c in cands) if (File.Exists(c)) return c;
            try
            {
                var p = Process.Start(new ProcessStartInfo("where", "php")
                {
                    UseShellExecute = false, CreateNoWindow = true, RedirectStandardOutput = true
                });
                if (p != null)
                {
                    string o = p.StandardOutput.ReadToEnd();
                    p.WaitForExit();
                    foreach (var line in o.Split('\n'))
                    {
                        var t = line.Trim();
                        if (File.Exists(t)) return t;
                    }
                }
            }
            catch { }
            return null;
        }

        // ═══ Appel PHP ═══════════════════════════════════════════════════════
        private Dictionary<string, object> RunPhp(string args)
        {
            if (_php == null) { Status("PHP introuvable — dossier php\\ manquant à côté de l'exe et aucun PHP sur cette machine.", true); return null; }
            string script = GenScript();
            if (!File.Exists(script)) { Status("gen_licence.php introuvable : " + script, true); return null; }

            var psi = new ProcessStartInfo
            {
                FileName = _php,
                Arguments = "\"" + script + "\" " + args,
                UseShellExecute = false, CreateNoWindow = true,
                RedirectStandardOutput = true, RedirectStandardError = true,
                StandardOutputEncoding = Encoding.UTF8, StandardErrorEncoding = Encoding.UTF8
            };
            try
            {
                var p = Process.Start(psi);
                if (p == null) { Status("Impossible de lancer PHP.", true); return null; }
                string stdout = p.StandardOutput.ReadToEnd();
                string stderr = p.StandardError.ReadToEnd();
                p.WaitForExit();
                if (p.ExitCode != 0)
                {
                    Status("PHP exit " + p.ExitCode + " : " + Trim(stderr), true);
                    return null;
                }
                var ser = new JavaScriptSerializer();
                var res = ser.Deserialize<Dictionary<string, object>>(stdout.Trim());
                return res;
            }
            catch (Exception ex)
            {
                Status("Erreur d'exécution PHP : " + ex.Message, true);
                return null;
            }
        }

        // ═══ Actions ══════════════════════════════════════════════════════════
        private void Init()
        {
            _php = ResolvePhp();
            if (_php == null) { Status("PHP introuvable : ni php\\php.exe (portable), ni C:\\xampp\\php\\php.exe, ni PATH.", true); return; }
            RefreshStatus();
            _loaded = true;
            _refresh.Start();
            StretchCards();
        }

        // Relit le statut (clé privée, ledger, palier gratuit) et met à jour la
        // fenêtre. Appelé à l'ouverture, quand la fenêtre reprend le focus et
        // toutes les 10 s : un message obsolète (« Clé privée introuvable »)
        // disparaît dès que la clé est restaurée, sans rouvrir le GUI.
        private void RefreshStatus()
        {
            if (_instance.DroppedDown) return;   // ne pas refermer la liste déroulante ouverte
            var res = RunPhp("--json --op=status");
            if (res == null) return;
            ApplyLedger(res);

            object pok;
            bool privOk = res.TryGetValue("priv_ok", out pok) && pok is bool && (bool)pok;
            if (!privOk)
            {
                // Message contextuel fourni par gen_licence.php : restauration
                // si une clé publique existe déjà (régénérer invaliderait tous
                // les codes émis), création normale sinon.
                object hint;
                string privHint = res.TryGetValue("priv_hint", out hint) && hint is string ? (string)hint : null;
                Status(privHint ?? "⚠ Clé privée introuvable — restaurez licence_privatekey.php depuis votre sauvegarde (NE PAS régénérer la paire si des codes ont déjà été émis).", true);
            }
            else if (_lastPrivOk != true)
            {
                // Première lecture, ou retour à la normale après une clé absente :
                // rafraîchit le bandeau. Sinon on ne touche pas au bandeau pour
                // préserver les messages transitoires (« code copié », etc.).
                Status("Prêt — " + _grid.Rows.Count + " client(s) dans le ledger.");
            }
            _lastPrivOk = privOk;
            _lastRefresh = DateTime.Now;
        }

        // Adapte la largeur des cartes à la largeur disponible (fenêtre redimensionnable).
        private void StretchCards()
        {
            if (_flow == null) return;
            int w = _flow.ClientSize.Width - _flow.Padding.Left - _flow.Padding.Right;
            if (_flow.VerticalScroll.Visible) w -= SystemInformation.VerticalScrollBarWidth;
            if (w < 420) w = 420;
            foreach (Control c in _flow.Controls)
                if (Math.Abs(c.Width - w) > 1) c.Width = w;
        }

        private void OnGenerate(object s, EventArgs e)
        {
            string inst = (_instance.Text ?? "").Trim();
            if (inst == "") { Status("Renseignez l'identifiant d'instance du client.", true); _instance.Focus(); return; }
            if (inst.IndexOf(' ') >= 0) { Status("L'instance ne doit pas contenir d'espace.", true); return; }
            if (_value.Value <= 0) { Status("La valeur doit être > 0.", true); return; }

            string format = _fmtShort.Checked ? "short" : "long";
            string mode = _modePack.Checked ? "pack" : "set";
            var sb = new StringBuilder("--json --op=generate");
            sb.Append(" --instance=").Append(inst);
            sb.Append(" --format=").Append(format);
            sb.Append(" --mode=").Append(mode);
            sb.Append(" --value=").Append((int)_value.Value);
            if (format == "long" && _expire.Enabled)
            {
                long ts = ToEpoch(_expire.Value);
                if (ts > 0) sb.Append(" --expire=").Append(ts);
            }

            Status("Génération…");
            var res = RunPhp(sb.ToString());
            if (res == null) return;
            object ok; res.TryGetValue("ok", out ok);
            if (ok is bool && (bool)ok)
            {
                _code.Text = (string)res["code"];
                Status((string)res["message"]);
                ApplyLedger(res);
                _instance.Text = inst;
            }
            else
            {
                Status((string)res["message"] ?? "Erreur de génération.", true);
            }
        }

        private void OnSetFree(object s, EventArgs e)
        {
            int cap = (int)_freeCap.Value;
            if (cap <= 0) { Status("Le palier doit être > 0.", true); return; }
            var res = RunPhp("--json --op=setfree --freecap=" + cap);
            if (res == null) return;
            object ok; res.TryGetValue("ok", out ok);
            bool good = ok is bool && (bool)ok;
            Status((string)res["message"], !good);
        }

        private void OnGridCellClick(object s, DataGridViewCellEventArgs e)
        {
            if (e.RowIndex < 0) return;
            string inst = Convert.ToString(_grid.Rows[e.RowIndex].Cells[0].Value);

            if (e.ColumnIndex == 4) // reset
            {
                if (Confirm("Réinitialiser le ledger de « " + inst + " » ?\n(cap = palier gratuit, counter = 0)") != DialogResult.OK) return;
                var res = RunPhp("--json --op=reset --instance=" + inst);
                if (res == null) return;
                Status((string)res["message"]); ApplyLedger(res);
            }
            else if (e.ColumnIndex == 5) // remove
            {
                if (Confirm("Retirer « " + inst + " » du ledger ?") != DialogResult.OK) return;
                var res = RunPhp("--json --op=remove --instance=" + inst);
                if (res == null) return;
                Status((string)res["message"]); ApplyLedger(res);
            }
        }

        private void OnCopy(object s, EventArgs e)
        {
            try
            {
                if (!string.IsNullOrEmpty(_code.Text))
                {
                    Clipboard.SetText(_code.Text);
                    Status("Code copié dans le presse-papiers.");
                }
            }
            catch { }
        }

        // ═══ Rendu du ledger ══════════════════════════════════════════════════
        private void ApplyLedger(Dictionary<string, object> res)
        {
            _grid.Rows.Clear();
            _instance.Items.Clear();
            if (res == null) return;

            object led;
            Dictionary<string, object> ledger = null;
            if (res.TryGetValue("ledger", out led)) ledger = led as Dictionary<string, object>;
            if (ledger != null)
            {
                foreach (var kv in ledger)
                {
                    var entry = kv.Value as Dictionary<string, object>;
                    if (entry == null) continue;
                    int cap = ToInt(entry, "cap");
                    int cnt = ToInt(entry, "counter");
                    int exp = ToInt(entry, "expiry");
                    _grid.Rows.Add(kv.Key, cap.ToString("N0"), cnt.ToString(), exp > 0 ? EpochToString(exp) : "—");
                    _instance.Items.Add(kv.Key);
                }
            }

            object fc;
            // Ne pas écraser une saisie en cours (le timer rafraîchit toutes les 10 s)
            if (res.TryGetValue("free_cap", out fc) && !_freeCap.Focused) _freeCap.Value = Math.Max(1, ToIntObj(fc));
        }

        // ═══ Helpers ══════════════════════════════════════════════════════════
        private void Status(string msg, bool error = false)
        {
            _status.Text = msg;
            _status.ForeColor = error ? ErrC : Ink;
        }
        private static DialogResult Confirm(string msg)
        {
            return MessageBox.Show(msg, "Confirmer", MessageBoxButtons.OKCancel, MessageBoxIcon.Question);
        }
        private static string Trim(string s) { return s == null ? "" : s.Trim(); }
        private static int ToInt(Dictionary<string, object> d, string k)
        {
            object v;
            if (!d.TryGetValue(k, out v) || v == null) return 0;
            try { return Convert.ToInt32(v); } catch { return 0; }
        }
        private static int ToIntObj(object v)
        {
            if (v == null) return 0;
            try { return Convert.ToInt32(v); } catch { return 0; }
        }
        private static long ToEpoch(DateTime dt)
        {
            try { return new DateTimeOffset(DateTime.SpecifyKind(dt, DateTimeKind.Local)).ToUnixTimeSeconds(); } catch { return 0; }
        }
        private static string EpochToString(long e)
        {
            try { return DateTimeOffset.FromUnixTimeSeconds(e).LocalDateTime.ToString("dd/MM/yyyy HH:mm"); } catch { return e.ToString(); }
        }
    }
}