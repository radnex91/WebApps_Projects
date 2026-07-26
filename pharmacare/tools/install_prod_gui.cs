// PharmaCare — Lanceur d'installation production (interface WinForms)
// Remplace install_prod.bat. Pilote install_prod.ps1 en mode NON-INTERACTIF :
// les parametres (nom d'hote + 4 mots de passe) sont saisis dans la GUI et
// transmis au script via un fichier JSON temporaire (-ParamFile) — JAMAIS sur
// la ligne de commande (securite : process list / journaux). Le script lit et
// efface ce fichier immediatement. UAC via manifeste requireAdministrator.
using System;
using System.Diagnostics;
using System.Drawing;
using System.IO;
using System.Reflection;
using System.Text;
using System.Text.RegularExpressions;
using System.Windows.Forms;

[assembly: System.Reflection.AssemblyTitle("PharmaCare Installer")]
[assembly: System.Reflection.AssemblyProduct("PharmaCare Installer")]
[assembly: System.Reflection.AssemblyCompany("PharmaCare")]
[assembly: System.Reflection.AssemblyVersion("1.2.0.0")]
[assembly: System.Reflection.AssemblyFileVersion("1.2.0.0")]
[assembly: System.Reflection.AssemblyInformationalVersion("1.2.0.0")]

namespace PharmaCareInstaller
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
        private readonly RichTextBox _log;
        private readonly Button _btnRun;
        private readonly Button _btnOpen;
        private readonly Button _btnQuit;
        private readonly ProgressBar _prog;
        private readonly Label _status;
        private readonly TextBox _host, _rootPass, _adminPass, _pharmaPass, _caissPass;
        private readonly CheckBox _samePass;

        private string _paramFile;

        private static readonly Color Bg   = Color.FromArgb(248, 250, 252);
        private static readonly Color Ink  = Color.FromArgb(17, 24, 39);
        private static readonly Color Mute = Color.FromArgb(90, 100, 116);
        private static readonly Color Brand= Color.FromArgb(22, 101, 52);
        private static readonly Color LogBg= Color.FromArgb(30, 32, 38);
        private static readonly Color LogFg= Color.FromArgb(222, 226, 232);

        public MainForm()
        {
            Text = "PharmaCare — Installation production";
            StartPosition = FormStartPosition.CenterScreen;
            Width = 820; Height = 700;
            MinimumSize = new Size(720, 620);
            FormBorderStyle = FormBorderStyle.FixedSingle;
            MaximizeBox = false;
            BackColor = Bg;
            Font = new Font("Segoe UI", 9F);

            // ── Bandeau ──
            var banner = new Panel { Dock = DockStyle.Top, Height = 78, BackColor = Color.White };
            var logo = new Panel { Location = new Point(24, 16), Size = new Size(46, 46), BackColor = Brand };
            logo.Controls.Add(new Label { Dock = DockStyle.Fill, Text = "Rx", ForeColor = Color.White,
                Font = new Font("Segoe UI", 17F, FontStyle.Bold), TextAlign = ContentAlignment.MiddleCenter });
            var lblTitle = new Label { Text = "Installation de PharmaCare", Font = new Font("Segoe UI", 15F, FontStyle.Bold),
                ForeColor = Ink, Location = new Point(84, 14), AutoSize = true };
            var lblSub = new Label { Text = "Assistant de déploiement production (XAMPP LAN) — renseignez les champs puis lancez",
                Font = new Font("Segoe UI", 9F), ForeColor = Mute, Location = new Point(84, 42), AutoSize = true };
            banner.Controls.AddRange(new Control[]{ logo, lblTitle, lblSub });
            Controls.Add(banner);

            // ── Panneau de parametres ──
            var param = new Panel { Dock = DockStyle.Top, Height = 210, BackColor = Color.White };
            AddField(param, 14,  "Nom d’hôte d’accès",        _host      = new TextBox { Text = "pharmacare.lan", Width = 300 }, "→ http://<hôte>  (DNS local à configurer)");
            AddField(param, 50,  "Mot de passe root MySQL *", _rootPass  = PwBox(),     "sera créé s’il n’existe pas, sinon utilisé pour se connecter");
            AddField(param, 86,  "Mot de passe admin *",      _adminPass = PwBox(),     "compte administrateur PharmaCare");
            AddField(param, 122, "Mot de passe pharmacien *", _pharmaPass= PwBox(),     "compte pharmacien");
            AddField(param, 158, "Mot de passe caissier *",   _caissPass = PwBox(),     "compte caissier");
            _samePass = new CheckBox { Text = "Même mot de passe pour les 3 comptes (utilise « admin »)",
                Location = new Point(290, 162), AutoSize = true, ForeColor = Mute };
            _samePass.CheckedChanged += (s, e) => {
                bool en = !_samePass.Checked;
                _pharmaPass.Enabled = en; _caissPass.Enabled = en;
                if (!en) { _pharmaPass.Text = ""; _caissPass.Text = ""; }
            };
            param.Controls.Add(_samePass);
            Controls.Add(param);

            // ── Journal ──
            _log = new RichTextBox {
                Dock = DockStyle.Fill, ReadOnly = true, BorderStyle = BorderStyle.None,
                BackColor = LogBg, ForeColor = LogFg,
                Font = new Font("Consolas", 9F), WordWrap = false,
                ScrollBars = RichTextBoxScrollBars.Vertical
            };
            var logPad = new Panel { Dock = DockStyle.Fill, Padding = new Padding(16, 12, 16, 12), BackColor = Bg };
            logPad.Controls.Add(_log);
            Controls.Add(logPad);

            // ── Bas de page ──
            var foot = new Panel { Dock = DockStyle.Bottom, Height = 76, BackColor = Color.White };
            _status = new Label { Text = "Prêt — renseignez les champs ci-dessus.", Font = new Font("Segoe UI", 9F, FontStyle.Bold),
                ForeColor = Ink, Location = new Point(24, 14), AutoSize = true };
            _prog = new ProgressBar { Location = new Point(24, 38), Size = new Size(340, 14),
                Style = ProgressBarStyle.Marquee, Visible = false };
            _btnRun = new Button { Text = "Lancer l’installation", BackColor = Brand, ForeColor = Color.White,
                FlatStyle = FlatStyle.Flat, Size = new Size(160, 38), Location = new Point(408, 20), Font = new Font("Segoe UI", 9.5F, FontStyle.Bold) };
            _btnRun.FlatAppearance.BorderSize = 0;
            _btnOpen = new Button { Text = "Ouvrir PharmaCare", Enabled = false,
                FlatStyle = FlatStyle.Flat, Size = new Size(150, 38), Location = new Point(578, 20) };
            _btnOpen.FlatAppearance.BorderSize = 0;
            _btnQuit = new Button { Text = "Quitter", FlatStyle = FlatStyle.Flat, Size = new Size(110, 38),
                Location = new Point(690, 20) };
            _btnQuit.FlatAppearance.BorderColor = Mute;
            foot.Controls.AddRange(new Control[]{ _status, _prog, _btnRun, _btnOpen, _btnQuit });
            Controls.Add(foot);

            _btnRun.Click += RunInstall;
            _btnOpen.Click += (s, e) => { try { Process.Start(ResolveAppUrl()); } catch {} };
            _btnQuit.Click += (s, e) => Close();

            Welcome();
        }

        private static TextBox PwBox() { return new TextBox { UseSystemPasswordChar = true, Width = 300 }; }

        private static void AddField(Panel p, int y, string label, TextBox box, string hint)
        {
            var lbl = new Label { Text = label, Location = new Point(24, y + 3), AutoSize = true, ForeColor = Color.FromArgb(60,70,86) };
            box.Location = new Point(290, y);
            var h = new Label { Text = hint, Location = new Point(600, y + 3), AutoSize = true,
                ForeColor = Color.FromArgb(150,160,175), Font = new Font("Segoe UI", 8F) };
            p.Controls.AddRange(new Control[]{ lbl, box, h });
        }

        private string ScriptDir() { return Path.GetDirectoryName(Assembly.GetExecutingAssembly().Location); }

        private void Welcome()
        {
            Log("Bienvenue dans l’assistant d’installation de PharmaCare.", false);
            Log("", false);
            Log("1. Renseignez les champs ci-dessus (nom d’hôte + 4 mots de passe, 8 caractères min).", false);
            Log("2. Cliquez sur « Lancer l’installation ».", false);
            Log("", false);
            Log("L’assistant va :", false);
            Log("  • vérifier XAMPP (Apache + MySQL) et détecter l’IP LAN", false);
            Log("  • sécuriser root MySQL + créer la base pharmacare + importer le schéma", false);
            Log("  • générer config/env.prod.php (URL = http://<hôte>)", false);
            Log("  • définir les mots de passe des comptes admin / pharmacien / caissier", false);
            Log("  • configurer Apache + le pare-feu (port 80 LAN)", false);
            Log("", false);
            Log("Les mots de passe sont transmis au script via un fichier temporaire effacé", false);
            Log("immédiatement après lecture (jamais sur la ligne de commande).", true);
            Log("", false);
        }

        private string ValidateForm()
        {
            if (string.IsNullOrWhiteSpace(_host.Text)) return "Nom d’hôte requis.";
            if ((_rootPass.Text ?? "").Length < 8)  return "Mot de passe root : 8 caractères minimum.";
            if ((_adminPass.Text ?? "").Length < 8) return "Mot de passe admin : 8 caractères minimum.";
            if (!_samePass.Checked)
            {
                if ((_pharmaPass.Text ?? "").Length < 8) return "Mot de passe pharmacien : 8 caractères minimum.";
                if ((_caissPass.Text ?? "").Length < 8) return "Mot de passe caissier : 8 caractères minimum.";
            }
            return null;
        }

        private void RunInstall(object s, EventArgs e)
        {
            string err = ValidateForm();
            if (err != null) { _status.Text = err; _status.ForeColor = Color.FromArgb(185, 28, 28); return; }

            string dir = ScriptDir();
            string ps1 = Path.Combine(dir, "install_prod.ps1");
            if (!File.Exists(ps1))
            {
                Log("✗ Fichier introuvable : " + ps1, true);
                Log("  Placez install_prod.exe à côté de install_prod.ps1.", true);
                return;
            }

            // Fichier de parametres temporaire (JSON) — secrets hors ligne de commande.
            try
            {
                _paramFile = Path.GetTempFileName();
                string pharma = _samePass.Checked ? _adminPass.Text : _pharmaPass.Text;
                string caiss  = _samePass.Checked ? _adminPass.Text : _caissPass.Text;
                string json = "{" +
                    "\"hostname\":"       + J(_host.Text.Trim()) +
                    ",\"rootPass\":"      + J(_rootPass.Text) +
                    ",\"adminPass\":"     + J(_adminPass.Text) +
                    ",\"pharmacienPass\":"+ J(pharma) +
                    ",\"caissierPass\":"  + J(caiss) +
                    "}";
                File.WriteAllText(_paramFile, json, new UTF8Encoding(false));
            }
            catch (Exception ex)
            {
                Log("✗ Impossible de créer le fichier de paramètres : " + ex.Message, true);
                return;
            }

            _btnRun.Enabled = false;
            _btnOpen.Enabled = false;
            _prog.Visible = true;
            _status.Text = "Installation en cours…";
            _status.ForeColor = Ink;
            _log.Clear();

            var psi = new ProcessStartInfo
            {
                FileName = "powershell.exe",
                // powershell.exe -NonInteractive = filet de secu : un Read-Host residuel
                // lèverait une erreur au lieu de bloquer (aucun prompt attendu en mode ParamFile).
                Arguments = "-NoProfile -NonInteractive -ExecutionPolicy Bypass -File \"" + ps1 + "\" -ParamFile \"" + _paramFile + "\"",
                WorkingDirectory = dir,
                UseShellExecute = false,
                CreateNoWindow = true,
                RedirectStandardOutput = true,
                RedirectStandardError = true,
                StandardOutputEncoding = Encoding.UTF8,
                StandardErrorEncoding = Encoding.UTF8
            };

            var p = new Process { StartInfo = psi, EnableRaisingEvents = true };
            p.OutputDataReceived += (_, ev) => { if (ev.Data != null) Log(ev.Data, false); };
            p.ErrorDataReceived  += (_, ev) => { if (ev.Data != null) Log(ev.Data, true);  };
            p.Exited += (_, __) =>
            {
                int code = p.ExitCode;
                p.Dispose();
                DeleteParamFile();
                BeginInvoke((Action)(() =>
                {
                    _prog.Visible = false;
                    _btnRun.Enabled = true;
                    if (code == 0)
                    {
                        _status.Text = "Installation terminée avec succès.";
                        _status.ForeColor = Brand;
                        Log("", false);
                        Log("=== Installation terminée (code 0) ===", false, true);
                        Log("URL : " + ResolveAppUrl(), false, true);
                        _btnOpen.Enabled = true;
                    }
                    else
                    {
                        _status.Text = "Échec de l’installation (code " + code + ").";
                        _status.ForeColor = Color.FromArgb(185, 28, 28);
                        Log("", true);
                        Log("=== ÉCHEC (code " + code + ") — corrigez l’erreur ci-dessus ===", true);
                    }
                }));
            };

            try
            {
                p.Start();
                p.BeginOutputReadLine();
                p.BeginErrorReadLine();
            }
            catch (Exception ex)
            {
                Log("✗ Impossible de lancer PowerShell : " + ex.Message, true);
                _prog.Visible = false;
                _btnRun.Enabled = true;
                DeleteParamFile();
            }
        }

        private void DeleteParamFile()
        {
            try { if (_paramFile != null && File.Exists(_paramFile)) File.Delete(_paramFile); } catch {}
            _paramFile = null;
        }

        protected override void OnFormClosing(FormClosingEventArgs e)
        {
            DeleteParamFile();
            base.OnFormClosing(e);
        }

        private string ResolveAppUrl()
        {
            try
            {
                string f = Path.Combine(ScriptDir(), "config", "env.prod.php");
                if (File.Exists(f))
                {
                    string txt = File.ReadAllText(f, Encoding.UTF8);
                    var m = Regex.Match(txt, @"['""]APP_URL['""]\s*=>\s*['""]([^'""]+)['""]");
                    if (m.Success) return m.Groups[1].Value;
                }
            } catch {}
            return "http://localhost/pharmacare";
        }

        // Echappement JSON d'une chaine.
        private static string J(string s)
        {
            var sb = new StringBuilder("\"");
            if (s != null) foreach (char c in s)
            {
                switch (c)
                {
                    case '"':  sb.Append("\\\""); break;
                    case '\\': sb.Append("\\\\"); break;
                    case '\n': sb.Append("\\n"); break;
                    case '\r': sb.Append("\\r"); break;
                    case '\t': sb.Append("\\t"); break;
                    default:
                        if (c < 0x20) sb.Append("\\u").Append(((int)c).ToString("x4"));
                        else sb.Append(c);
                        break;
                }
            }
            sb.Append('"');
            return sb.ToString();
        }

        private void Log(string line, bool isError, bool emphasis = false)
        {
            if (InvokeRequired)
            {
                BeginInvoke((Action)(() => Log(line, isError, emphasis)));
                return;
            }
            _log.SelectionStart = _log.TextLength;
            _log.SelectionLength = 0;
            if (emphasis) _log.SelectionColor = Color.FromArgb(86, 197, 122);
            else if (isError) _log.SelectionColor = Color.FromArgb(248, 113, 113);
            else _log.SelectionColor = LogFg;
            _log.AppendText(line + "\n");
            _log.SelectionColor = LogFg;
            _log.ScrollToCaret();
        }
    }
}