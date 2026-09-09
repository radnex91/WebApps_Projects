// PharmaCare — Lanceur d'installation production (interface WinForms)
// Remplace install_prod.bat. Pilote install_prod.ps1 en mode NON-INTERACTIF :
// les parametres (nom d'hote + 4 mots de passe) sont saisis dans la GUI et
// transmis au script via un fichier JSON temporaire (-ParamFile) — JAMAIS sur
// la ligne de commande (securite : process list / journaux). Le script lit et
// efface ce fichier immediatement. UAC via manifeste requireAdministrator.
//
// Mise en page : TableLayoutPanel proportionnels (aucune coordonnee absolue),
// donc stable quel que soit le DPI / le facteur d'echelle de texte de Windows.
using System;
using System.ComponentModel;
using System.Diagnostics;
using System.Drawing;
using System.Drawing.Drawing2D;
using System.IO;
using System.Reflection;
using System.Security.Cryptography;
using System.Text;
using System.Text.RegularExpressions;
using System.Windows.Forms;

[assembly: System.Reflection.AssemblyTitle("PharmaCare Installer")]
[assembly: System.Reflection.AssemblyProduct("PharmaCare Installer")]
[assembly: System.Reflection.AssemblyCompany("PharmaCare")]
[assembly: System.Reflection.AssemblyVersion("1.4.0.0")]
[assembly: System.Reflection.AssemblyFileVersion("1.4.0.0")]
[assembly: System.Reflection.AssemblyInformationalVersion("1.4.0.0")]

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
        private RichTextBox _log;
        private Button _btnRun;
        private Button _btnOpen;
        private Button _btnQuit;
        private ProgressBar _prog;
        private Label _status;
        private TextBox _host, _rootPass, _adminPass, _pharmaPass, _caissPass;
        private CheckBox _samePass;
        private Label _rootHint;                            // aide vivante du champ root
        private System.Windows.Forms.Timer _verifyTimer;    // anti-rebond vérification
        private int _rootVerifySeq;                         // token anti-course des vérifs async
        private string _rootVerifyTag;                      // "good" | "bad" | "new" | null
        private string _rootMode = "down";                  // new | verified | protected | down | unknown

        private string _paramFile;
        private bool _isUpdateMode;                        // MAJ d'une installation existante
        private string _existingTarget;                    // chemin de l'installation détectée (mode MAJ)
        private Label _lblTitle, _lblSubtitle;             // bandeau (texte dynamique selon le mode)
        private System.Windows.Forms.RadioButton _rbUpdate, _rbFresh;  // choix du mode
        private Label _modeHint;                            // retour de détection sous les radios
        private SplitContainer _split;                      // séparateur formulaire / journal
        private System.Windows.Forms.CheckBox _freshCheck; // réinstallation neuve explicite (efface la base)
        private bool _freshConfirmed;                      // confirmation saisie après la case
        private bool _pendingUpdate;                       // MAJ détectée avant création du handle

        private static readonly Color Bg   = Color.FromArgb(243, 246, 250);
        private GdiCard _cardLog;                  // carte arrondie du journal (GDI+)

        // Couleurs des cartes (déclarées ici, utilisées par GdiCard plus bas).
        private static readonly Color Ink  = Color.FromArgb(17, 24, 39);
        private static readonly Color Mute = Color.FromArgb(90, 100, 116);
        private static readonly Color Brand= Color.FromArgb(22, 101, 52);
        private static readonly Color LogBg= Color.FromArgb(30, 32, 38);
        private static readonly Color LogFg= Color.FromArgb(222, 226, 232);
        private static readonly Color Line = Color.FromArgb(229, 233, 240);
        private static readonly Color BrandHover   = Color.FromArgb(31, 125, 66);   // survol bouton vert
        private static readonly Color DarkBtn      = Color.FromArgb(37, 44, 55);    // bouton secondaire
        private static readonly Color DarkBtnHover = Color.FromArgb(53, 62, 76);    // survol secondaire

        public MainForm()
        {
            Text = "PharmaCare — Installation production";
            Font = new Font("Segoe UI", 9F);
            // L'echelle suit la police systeme : libelles et champs restent alignes
            // a 100 %, 125 % ou 150 % de DPI (interface « equilibree » partout).
            AutoScaleMode = AutoScaleMode.Font;
            StartPosition = FormStartPosition.CenterScreen;
            // Fenetre agrandie + redimensionnable (MaximizeBox) : le journal doit
            // pouvoir etre lu en entier, surtout pendant une mise a jour.
            ClientSize = new Size(980, 780);
            MinimumSize = new Size(900, 700);
            FormBorderStyle = FormBorderStyle.Sizable;
            MaximizeBox = true;
            BackColor = Bg;

            // Ordre d'ajout = ordre de docking ( dernier ajoute = docke en premier ) :
            // footer (bas) -> zone centrale (remplissage) -> bandeau (tout en haut).
            Controls.Add(BuildFooter());
            // SplitContainer : le formulaire et le journal partagent l'espace centre.
            // La poignée centrale permet d'agrandir la zone qu'on préfère ; le
            // formulaire garde la priorité (Panel1MinSize élevé), le journal garde
            // un mini de 120 px. _split est retenu pour caler le séparateur à la
            // hauteur réelle du formulaire une fois le layout affiché (OnShown).
            _split = new SplitContainer {
                Dock = DockStyle.Fill, BackColor = Bg,
                Orientation = Orientation.Horizontal,
                Panel1MinSize = 220, Panel2MinSize = 120, SplitterWidth = 6
            };
            _split.Panel1.BackColor = Bg;
            _split.Panel2.BackColor = Bg;
            // Si l'utilisateur réduit fortement la fenêtre, le formulaire doit
            // rester entièrement accessible : scroll au lieu de rognage.
            _split.Panel1.AutoScroll = true;

            // Panneau supérieur : carte des parametres (hauteur = contenu).
            var paramsWrap = new Panel { Dock = DockStyle.Top, AutoSize = true,
                AutoSizeMode = AutoSizeMode.GrowAndShrink, BackColor = Bg, Padding = new Padding(12, 12, 12, 0) };
            paramsWrap.Controls.Add(BuildParamsPanel());
            _split.Panel1.Controls.Add(paramsWrap);

            // Panneau inferieur : carte du journal, agrandissable via la poignée.
            var logWrap = new Panel { Dock = DockStyle.Fill, BackColor = Bg, Padding = new Padding(12, 0, 12, 12) };
            logWrap.Controls.Add(BuildLogPanel());
            _split.Panel2.Controls.Add(logWrap);

            Controls.Add(_split);
            Controls.Add(BuildBanner());

            _btnRun.Click += RunInstall;
            _btnOpen.Click += (s, e) => { try { Process.Start(ResolveAppUrl()); } catch {} };
            _btnQuit.Click += (s, e) => Close();

            // Vérification en direct du mot de passe root (anti-rebond 700 ms ;
            // test serveur asynchrone, cf. QueueRootVerify / VerifyRootPass).
            _verifyTimer = new System.Windows.Forms.Timer { Interval = 700 };
            _verifyTimer.Tick += (s, e) => { _verifyTimer.Stop(); VerifyRootPass(); };
            _rootPass.TextChanged += (s, e) => QueueRootVerify();
            _host.TextChanged       += (s, e) => UpdateRunButtonState();
            _adminPass.TextChanged  += (s, e) => UpdateRunButtonState();
            _pharmaPass.TextChanged += (s, e) => UpdateRunButtonState();
            _caissPass.TextChanged  += (s, e) => UpdateRunButtonState();
            _samePass.CheckedChanged += (s, e) => UpdateRunButtonState();

            Welcome();

            // Détection automatique du mot de passe root MySQL (aucune saisie demandée) :
            // 1) config\env.prod.php d'une installation précédente (source autoritaire),
            // 2) sinon root sans mot de passe (XAMPP par défaut) -> on en génère un fort
            //    qui sera appliqué par le script [3/9] ; l'utilisateur peut le changer.
            BeginAutoDetectRootPass();

            // Détection d'une installation PharmaCare existante (env.prod.php + base
            // remplie) : si détectée, le mode « Mise à jour » est proposé (base et
            // comptes conservés). L'utilisateur peut repasser en installation neuve.
            BeginDetectExistingInstall();
        }

        // ── Détection d'une installation PharmaCare existante ─────
        // env.prod.php fait foi (l'app s'exécute avec EUX). On vérifie ensuite que
        // la base pharmacare contient bien des tables (table_count >= 5) pour ne
        // pas proposer « Mise à jour » sur une installation inutilisable. En mode
        // MAJ : comptes/hôte inchangés, champ root = mot de passe root EXISTANT.
        private void BeginDetectExistingInstall()
        {
            var bg = new BackgroundWorker();
            bg.DoWork += (_, ev) =>
            {
                try
                {
                    string envFile = Path.Combine(ScriptDir(), "config", "env.prod.php");
                    bool hasEnv = File.Exists(envFile);

                    // Bundles livraison : l'app vit dans pharmacare\ à côté de l'assistant.
                    if (!hasEnv)
                    {
                        string sub = Path.Combine(ScriptDir(), "pharmacare", "config", "env.prod.php");
                        if (File.Exists(sub)) { envFile = sub; hasEnv = true; }
                    }

                    if (!hasEnv) {
                        string def = "C:\\xampp\\htdocs\\pharmacare\\config\\env.prod.php";
                        if (File.Exists(def)) { envFile = def; hasEnv = true; }
                    }
                    if (!hasEnv) { ev.Result = null; return; }

                    string txt = File.ReadAllText(envFile, Encoding.UTF8);
                    var mU = Regex.Match(txt, @"['""]DB_USER['""]\s*=>\s*['""]([^'""]*)['""]");
                    var mP = Regex.Match(txt, @"['""]DB_PASS['""]\s*=>\s*[""']([^""']*)[""']");
                    string dbUser = mU.Success ? mU.Groups[1].Value : "";
                    string dbPass = mP.Success ? mP.Groups[1].Value : "";
                    string target = Path.GetDirectoryName(Path.GetDirectoryName(envFile));

                    // La base est-elle remplie ? (>= 5 tables = installation utilisée)
                    int tableCount = 0;
                    string mysql = Path.Combine(XamppDir(), "mysql", "bin", "mysql.exe");
                    if (File.Exists(mysql))
                    {
                        string ini = Path.Combine(Path.GetTempPath(), "pc_det_" + Guid.NewGuid().ToString("N") + ".ini");
                        string escU = dbUser.Replace("\\", "\\\\").Replace("\"", "\\\"");
                        string escP = dbPass.Replace("\\", "\\\\").Replace("\"", "\\\"");
                        File.WriteAllText(ini, "[client]\r\nuser=\"" + escU + "\"\r\npassword=\"" + escP + "\"\r\n", new UTF8Encoding(false));
                        try
                        {
                            var psi = new ProcessStartInfo
                            {
                                FileName = mysql,
                                UseShellExecute = false,
                                CreateNoWindow = true,
                                Arguments = "--defaults-extra-file=\"" + ini + "\" -N -e \"SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='pharmacare';\"",
                                RedirectStandardOutput = true,
                                RedirectStandardError = true,
                                StandardOutputEncoding = Encoding.UTF8
                            };
                            using (var p = Process.Start(psi))
                            {
                                string outp = p.StandardOutput.ReadToEnd() ?? "";
                                p.WaitForExit(4000);
                                int.TryParse((outp ?? "").Trim(), out tableCount);
                            }
                        }
                        finally { try { File.Delete(ini); } catch {} }
                    }
                    ev.Result = new object[] { hasEnv, tableCount, target };
                }
                catch { ev.Result = null; }
            };
            bg.RunWorkerCompleted += (_, ev) =>
            {
                var r = ev.Result as object[];
                if (r == null) return;
                bool hasEnv = (bool)r[0];
                int tc = (int)r[1];
                string target = (string)r[2];

                if (hasEnv && tc >= 5)
                {
                    // Installation existante utilisée : mode MAJ disponible.
                    _existingTarget = target;
                    Log("", false);
                    Log("✓ Installation PharmaCare existante détectée : " + target, false, true);
                    Log("  → Mode « Mise à jour » sélectionné : vos données, comptes et mots de", false, true);
                    Log("  passe seront CONSERVÉS. Un dump de sécurité de la base sera fait avant", false);
                    Log("  toute modification (il restera dans pharmacare\\backups\\).", false);
                    Log("  Saisissez le mot de passe root ACTUEL dans le champ dédié (il sera vérifié).", false);
                    Log("", false);
                    EnableUpdateMode();
                }
                else if (hasEnv && tc < 5)
                {
                    // env.prod.php présent mais base vide/partielle : pas de mode MAJ.
                    Log("", false);
                    Log("! env.prod.php trouvé mais la base « pharmacare » est vide/partielle (" + tc + " table(s)).", true);
                    Log("  Mode « Mise à jour » indisponible : faite une installation neuve ou restaurez", true);
                    Log("  une sauvegarde depuis phpMyAdmin avant de lancer l'assistant.", true);
                    Log("", false);
                    _rbFresh.Checked = true;
                }
                else
                {
                    // Aucune installation : mode Installation neuve par défaut.
                    if (_modeHint != null)
                    {
                        _modeHint.Text = "Aucune installation détectée — mode Installation neuve.";
                        _modeHint.ForeColor = Mute;
                    }
                }

                UpdateRunButtonState();
            };
            bg.RunWorkerAsync();
        }

        // ── Mode MAJ : adapter l'interface ────────────────────────
        // Champ root = mot de passe root EXISTANT (sémantique inverse de l'instal-
        // lation neuve). Comptes + hôte : conservés -> champs désactivés (l'app et
        // les utilisateurs gardent leurs identifiants). Radios pilotes le mode.
        private void EnableUpdateMode()
        {
            // BeginInvoke exige un handle cree : si la detection est terminee
            // avant l'affichage de la fenetre, différer à OnShown.
            if (!IsHandleCreated) { _pendingUpdate = true; return; }
            if (_rbUpdate.InvokeRequired) { BeginInvoke((Action)(() => EnableUpdateMode())); return; }
            _rbUpdate.Enabled = true;
            _rbUpdate.Checked = true;   // déclenche ApplyMode(true)
        }

        protected override void OnShown(EventArgs e)
        {
            base.OnShown(e);
            if (_pendingUpdate) { _pendingUpdate = false; EnableUpdateMode(); }

            // Cale le séparateur juste sous la carte des paramètres : le formulaire
            // est affiché en entier (aucune lecture coupée) et le journal récupère
            // tout l'espace restant.
            int contentHeight = paramsPanelHeight();
            try
            {
                _split.SplitterDistance = Math.Max(_split.Panel1MinSize,
                    Math.Min(_split.Height - _split.Panel2MinSize - _split.SplitterWidth, contentHeight));
            }
            catch { /* distance hors bornes selon DPI : on garde la valeur par défaut */ }
        }

        // Hauteur réelle de la carte des paramètres (paramsWrap AutoSize) mesurée
        // après layout ; +12 px de marge basse de sécurité.
        private int paramsPanelHeight()
        {
            if (_split == null || _split.Panel1.Controls.Count == 0) return 300;
            var p = _split.Panel1.Controls[0];
            return Math.Min(Math.Max(p.Height + 12, _split.Panel1MinSize),
                            Math.Max(_split.Panel1MinSize, 900));
        }

        private void ApplyMode(bool update)
        {
            _isUpdateMode = update;
            bool freshMode = !update;

            // Bandeau
            _lblTitle.Text    = update ? "Mise à jour de PharmaCare" : "Installation de PharmaCare";
            _lblSubtitle.Text = update
                ? "Vos données, comptes et mots de passe sont conservés (dump de sécurité avant migration)"
                : "Assistant de déploiement production (XAMPP LAN)";

            // Retour de détection sous les radios (remplace « Recherche… »).
            if (_modeHint != null)
            {
                _modeHint.Text = update
                    ? "Installation existante détectée — vos données seront conservées."
                    : "Crée une base de données et des comptes neufs.";
                _modeHint.ForeColor = update ? Brand : Mute;
            }

            // Retour visuel des radios : la radio du mode actif passe en ink.
            if (_rbUpdate != null) _rbUpdate.ForeColor = update ? Ink : Mute;
            if (_rbFresh  != null) _rbFresh.ForeColor  = update ? Mute : Ink;

            // Ligne 6 : case « réinstallation neuve » visible seulement en MAJ
            // (c'est la sortie de secours pour écraser une base existante).
            _freshCheck.Visible = update;

            // Hôte : figé en MAJ (l'URL de production ne doit pas changer de façon
            // involontaire ; le script reprend l'hostname d'env.prod.php).
            _host.ReadOnly = update;
            if (update)
            {
                try
                {
                    string f = Path.Combine(ExistingTargetDir(), "config", "env.prod.php");
                    if (File.Exists(f))
                    {
                        string txt = File.ReadAllText(f, Encoding.UTF8);
                        var m = Regex.Match(txt, @"['""]APP_URL['""]\s*=>\s*['""]http://([^'""/]+)['""]");
                        if (m.Success) _host.Text = m.Groups[1].Value;
                    }
                } catch {}
            }
            else
            {
                if (_host.Text.Length == 0) _host.Text = "pharmacare.lan";
            }

            // Comptes applicatifs : requis en installation neuve ET en
            // réinstallation neuve (case cochée — les comptes seront réinitialisés) ;
            // inutiles en MAJ simple (comptes conservés). _samePass est remis à
            // false AVANT de régler les Enabled (son handler réactive sinon).
            bool pwNeeded = freshMode || (update && _freshCheck.Checked);
            _samePass.Checked = false;
            _adminPass.Enabled  = pwNeeded;
            _pharmaPass.Enabled = pwNeeded;
            _caissPass.Enabled  = pwNeeded;
            _samePass.Enabled   = pwNeeded;
            if (!pwNeeded) { _adminPass.Text = ""; _pharmaPass.Text = ""; _caissPass.Text = ""; }

            // Bouton principal
            _btnRun.Text = update ? "Lancer la mise à jour" : "Lancer l’installation";

            // Sécurité root : en MAJ le champ doit contenir le mot de passe root EXISTANT.
            if (update)
                _rootHint.Text = "Mot de passe root ACTUEL (vérifié contre le serveur)";
            else if (_rootVerifyTag == "new") SetRootHint(null, null);

            UpdateRunButtonState();
        }

        private string ExistingTargetDir()
        {
            return _existingTarget ?? Path.Combine(ScriptDir(), "pharmacare");
        }

        // Confirmation EFFACER : double garde-fou avant d'effacer la base de
        // production (case cochée + confirmation tapee au clic sur « Lancer »).
        private bool AskConfirmFresh()
        {
            var prompt = new Form
            {
                Text = "Confirmer la réinstallation neuve",
                ClientSize = new Size(620, 286),
                FormBorderStyle = FormBorderStyle.FixedDialog,
                StartPosition = FormStartPosition.CenterParent,
                MaximizeBox = false, MinimizeBox = false, ShowInTaskbar = false,
                BackColor = Color.White
            };

            // Bandeau rouge : la gravité de l'action doit sauter aux yeux.
            var top = new Panel { Dock = DockStyle.Top, Height = 86, BackColor = Color.FromArgb(185, 28, 28) };
            top.Controls.Add(new Label {
                Text = "⚠  Réinstallation neuve — action irréversible",
                Dock = DockStyle.Fill, ForeColor = Color.White,
                Font = new Font("Segoe UI", 12F, FontStyle.Bold),
                TextAlign = ContentAlignment.MiddleLeft, Padding = new Padding(20, 0, 0, 0) });

            // Corps : texte explicatif + champ de confirmation.
            var body = new Panel { Dock = DockStyle.Fill, Padding = new Padding(20, 18, 20, 8), BackColor = Color.White };
            var lbl = new Label
            {
                Text = "La base « pharmacare » va être EFFACÉE et recréée vide.\n" +
                       "Un dump de secours sera d'abord effectué (backups\\pharmacare_<date>.sql).\n\n" +
                       "Tapez EFFACER dans le champ ci-dessous pour confirmer :",
                Dock = DockStyle.Top, AutoSize = true, ForeColor = Ink,
                Font = new Font("Segoe UI", 9.5F)
            };
            var gap = new Panel { Dock = DockStyle.Top, Height = 12, BackColor = Color.White };
            var txt = new TextBox { Dock = DockStyle.Top, Font = new Font("Segoe UI", 10.5F) };
            body.Controls.Add(txt);
            body.Controls.Add(gap);
            body.Controls.Add(lbl);

            // Barre de boutons : Confirmer (rouge, inactif tant que EFFACER
            // n'est pas tapé) à droite, Annuler juste à sa gauche. FlowLayoutPanel
            // RightToLeft = même mécanique que le pied de page principal.
            var bottom = new Panel { Dock = DockStyle.Bottom, Height = 56,
                BackColor = Color.FromArgb(248, 249, 251), Padding = new Padding(20, 11, 20, 11) };
            var ok = new Button { Text = "Confirmer l'effacement", DialogResult = DialogResult.OK,
                BackColor = Color.FromArgb(185, 28, 28), ForeColor = Color.White,
                FlatStyle = FlatStyle.Flat, Size = new Size(170, 34), Enabled = false,
                Cursor = Cursors.Hand };
            ok.FlatAppearance.BorderSize = 0;
            var no = new Button { Text = "Annuler", DialogResult = DialogResult.Cancel,
                BackColor = Color.White, ForeColor = DarkBtn,
                FlatStyle = FlatStyle.Flat, Size = new Size(96, 34), Margin = new Padding(8, 0, 0, 0),
                Cursor = Cursors.Hand };
            no.FlatAppearance.BorderColor = Line;
            txt.TextChanged += (s, e) => ok.Enabled = txt.Text == "EFFACER";
            var btns = new FlowLayoutPanel { FlowDirection = FlowDirection.RightToLeft,
                Dock = DockStyle.Fill, WrapContents = false, Margin = new Padding(0),
                AutoSize = false };
            btns.Controls.Add(ok);      // RightToLeft : premier ajouté = plus à droite
            btns.Controls.Add(no);
            bottom.Controls.Add(btns);
            prompt.Controls.Add(body);
            prompt.Controls.Add(top);
            prompt.Controls.Add(bottom);
            prompt.AcceptButton = ok; prompt.CancelButton = no;
            return prompt.ShowDialog(this) == DialogResult.OK;
        }

        // ── Auto-détection du mot de passe root MySQL ─────────────
        // Principe : ne JAMAIS présenter un mot de passe sans l'avoir vérifié
        // contre le serveur, ni laisser croire qu'un mot de passe généré est
        // le mot de passe actuel. Quatre vérités possibles :
        //   new       : root n'a PAS de mot de passe -> on en propose un fort
        //               (le script l'appliquera à l'étape [3/9]) ; annoncé comme tel.
        //   verified  : root a un mot de passe ; celui d'env.prod.php est accepté
        //               par le serveur -> collé, tout est déjà en place.
        //   protected : root a un mot de passe inconnu -> champ laissé vide, la GUI
        //               demande de le saisir (aucune invention).
        //   down      : MySQL ne répond pas / mysql.exe absent -> pas de détection.
        private void BeginAutoDetectRootPass()
        {
            var bg = new BackgroundWorker();
            bg.DoWork += (_, ev) =>
            {
                string envFile = Path.Combine(ScriptDir(), "config", "env.prod.php");

                // 1) env.prod.php (installation précédente) : seul candidat à "verified".
                string stored = null;
                try
                {
                    if (File.Exists(envFile))
                    {
                        string txt = File.ReadAllText(envFile, Encoding.UTF8);
                        var m = Regex.Match(txt, @"['""]DB_PASS['""]\s*=>\s*['""]([^'""]*)['""]");
                        if (m.Success) stored = m.Groups[1].Value;
                    }
                } catch {}

                // 2) Interroger le serveur : pourquoi pas mysql.exe ?
                string mysql = Path.Combine(XamppDir(), "mysql", "bin", "mysql.exe");
                if (!File.Exists(mysql))
                {
                    ev.Result = new string[] { "unknown", stored ?? "" };
                    return;
                }

                string errOpen;
                bool rootOpen = RunMysqlProbe(null, out errOpen) == 0;

                if (rootOpen)
                {
                    // Vérité n°1 : root est ouvert (XAMPP par défaut). Le champ root
                    // reste VIDE : aucun mot de passe n'est inventé pour l'utilisateur.
                    // C'est à lui de choisir le nouveau mot de passe qui sera appliqué
                    // par l'étape [3/9] (minimum 8 caractères, validé avant lancement).
                    ev.Result = new string[] { "new", "" };
                    return;
                }

                if (AuthDenied(errOpen) && !string.IsNullOrEmpty(stored))
                {
                    string errStored;
                    if (RunMysqlProbe(stored, out errStored) == 0)
                    {
                        // Vérité n°2 : root protégé, mais le mot de passe stocké dans
                        // env.prod.php est accepté par le serveur.
                        ev.Result = new string[] { "verified", stored };
                        return;
                    }
                }

                // Refus d'authentification sans mot de passe connu de l'assistant
                // (root protégé), OU serveur ne répondant pas : on distingue via
                // la stderr du refus (1045) vs l'absence de refus (2003 = arrêté).
                if (AuthDenied(errOpen))
                {
                    // Vérité n°3 : root protégé, mot de passe inconnu de l'assistant.
                    ev.Result = new string[] { "protected", "" };
                    return;
                }

                // Pas de refus ni de succès : le serveur ne répond pas (arrêté ?).
                ev.Result = new string[] { "down", "" };
            };
            bg.RunWorkerCompleted += (_, ev) =>
            {
                var r = ev.Result as string[];
                if (r == null || r.Length < 2) return;
                _rootMode = r[0];
                string pass = r[1];

                if (_rootMode == "new")
                {
                    // Champ root laissé VIDE : l'utilisateur choisit lui-même le
                    // mot de passe qui sera appliqué à l'étape [3/9].
                    if (_samePass.Checked == false)
                    {
                        if (string.IsNullOrEmpty(_adminPass.Text))  _adminPass.Text  = GeneratePassword(16);
                        if (string.IsNullOrEmpty(_pharmaPass.Text)) _pharmaPass.Text = GeneratePassword(16);
                        if (string.IsNullOrEmpty(_caissPass.Text))  _caissPass.Text  = GeneratePassword(16);
                    }
                    Log("", false);
                    Log("✓ MySQL accessible — root SANS mot de passe (configuration XAMPP par défaut).", false, true);
                    Log("  → À FAIRE : saisissez dans le champ « Mot de passe root MySQL » le mot de", false);
                    Log("  passe que vous voulez définir (8 caractères min.). Il sera APPLIQUÉ au", false);
                    Log("  serveur et enregistré dans env.prod.php + phpMyAdmin à l'étape 3/9.", false);
                    Log("  Admin/pharmacien/caissier : mots de passe générés automatiquement.", false);
                    Log("", false);
                    SetRootHint("Root actuellement SANS mot de passe — définissez-en un ici (obligatoire, 8 min.)", "new");
                }
                else if (_rootMode == "verified")
                {
                    if (_rootPass.Text.Length == 0) _rootPass.Text = pass;
                    Log("", false);
                    Log("✓ MySQL accessible — mot de passe root existant détecté et VÉRIFIÉ.", false, true);
                    Log("", false);
                    SetRootHint("Mot de passe existant — vérifié ✓", "good");
                }
                else if (_rootMode == "protected")
                {
                    _rootPass.Text = "";
                    Log("", false);
                    Log("! MySQL accessible — root est PROTÉGÉ par un mot de passe inconnu de", true);
                    Log("  l'assistant. Saisissez le mot de passe root actuel (il sera vérifié).", true);
                    Log("", false);
                    SetRootHint("Root protégé — saisissez le mot de passe actuel", "bad");
                }
                else if (_rootMode == "unknown")
                {
                    Log("", false);
                    Log("! MySQL introuvable (C:\\xampp\\mysql absent ?) : détection impossible.", true);
                    Log("  Saisissez le mot de passe root manuellement dans le champ dédié.", true);
                    Log("", false);
                    SetRootHint(null, null);
                }
                else // down : MySQL ne répond pas (service arrêté) — déjà signalé côté probe
                {
                    Log("", false);
                    Log("! MySQL ne répond pas : démarrez-le depuis le panneau de contrôle", true);
                    Log("  XAMPP puis relancez cet assistant. Détection impossible pour l'instant.", true);
                    Log("", false);
                    SetRootHint("MySQL ne répond pas — démarrez-le dans le panneau XAMPP", "bad");
                }

                UpdateRunButtonState();
                if (_rootPass.Text.Length > 0) QueueRootVerify();   // statut immédiat
            };
            bg.RunWorkerAsync();
        }

        // Sonde mysql.exe : renvoie le code de sortie natif et la stderr.
        //   0  = connexion acceptée (le mot de passe convient)
        //   1  = refusée (stderr contient ERROR 1045 Access denied)
        //   1+ = autre erreur (2003 Can't connect -> serveur arrêté)
        //   -1 = exception/timeout (processus tué)
        // Le mot de passe passe par un fichier d'options temporaire (jamais
        // -p<pass> sur la ligne de commande : visible dans la liste des process).
        private static int RunMysqlProbe(string pass, out string stderr)
        {
            stderr = null;
            try
            {
                string mysql = Path.Combine(XamppDir(), "mysql", "bin", "mysql.exe");
                var psi = new ProcessStartInfo
                {
                    FileName = mysql,
                    UseShellExecute = false,
                    CreateNoWindow = true,
                    RedirectStandardOutput = true,
                    RedirectStandardError = true,
                    StandardErrorEncoding = Encoding.UTF8
                };
                if (string.IsNullOrEmpty(pass))
                {
                    psi.Arguments = "-u root -e \"SELECT 1;\"";
                }
                else
                {
                    string ini = Path.Combine(Path.GetTempPath(), "pc_probe_" + Guid.NewGuid().ToString("N") + ".ini");
                    string esc = pass.Replace("\\", "\\\\").Replace("\"", "\\\"");
                    File.WriteAllText(ini, "[client]\r\nuser=\"root\"\r\npassword=\"" + esc + "\"\r\n", new UTF8Encoding(false));
                    psi.Arguments = "--defaults-extra-file=\"" + ini + "\" -e \"SELECT 1;\"";
                }
                using (var p = Process.Start(psi))
                {
                    stderr = p.StandardError.ReadToEnd() ?? "";
                    p.WaitForExit(4000);
                    if (!p.HasExited) { p.Kill(); return -1; }
                    return p.ExitCode;
                }
            }
            catch { stderr = null; return -1; }
        }

        private static bool AuthDenied(string stderr)
        {
            return stderr != null && stderr.IndexOf("1045", StringComparison.Ordinal) >= 0;
        }

        // Statut vivant sous le champ root : texte + couleur selon l'état vérifié.
        private void SetRootHint(string text, string tag)
        {
            if (_rootHint == null || _rootHint.IsDisposed) return;
            if (InvokeRequired) { BeginInvoke((Action)(() => SetRootHint(text, tag))); return; }
            if (text == null) { _rootHint.Text = "auto-rempli (détection) — modifiable"; _rootHint.ForeColor = Mute; _rootVerifyTag = null; return; }
            _rootHint.Text = text;
            _rootHint.ForeColor = tag == "good" ? Brand : (tag == "bad" ? Color.FromArgb(185, 28, 28) : Mute);
            _rootVerifyTag = tag;
        }

        // Vérification en direct de l'état root : lance un test de connexion en
        // arrière-plan et affiche le statut sous le champ. En mode "new" (root
        // ouvert), le test vérifie la joignabilité du serveur, jamais le champ.
        private void QueueRootVerify()
        {
            if (_rootHint == null) return;
            if (_rootMode == "down" || _rootMode == "unknown") { SetRootHint("MySQL non détecté — vérification impossible", "bad"); return; }
            _verifyTimer.Stop();
            _verifyTimer.Start();
        }

        private void VerifyRootPass()
        {
            string candidate = _rootPass.Text;
            string probedMode = _rootMode;
            int seq = ++_rootVerifySeq;
            var bg = new BackgroundWorker();
            bg.DoWork += (_, ev) =>
            {
                string err;
                int code;
                if (probedMode == "new" && !_isUpdateMode)
                {
                    // Installation neuve, root ouvert : le champ contient le mot de
                    // passe FUTUR (à appliquer en [3/9]) — l'authentifier serait
                    // absurde, le serveur le refuserait forcément. On ne vérifie
                    // QUE la joignabilité du serveur (sonde sans mot de passe) ;
                    // le refus éventuel est attendu et non une erreur de saisie.
                    code = RunMysqlProbe(null, out err);
                }
                else
                {
                    // Root protégé OU mode mise à jour : le champ est censé
                    // contenir le mot de passe root EXISTANT -> on l'authentifie
                    // réellement (champ vide = test sans mot de passe).
                    code = RunMysqlProbe(candidate.Length > 0 ? candidate : null, out err);
                }
                ev.Result = new object[] { code, err, candidate.Length > 0 ? candidate : null, probedMode, seq };
            };
            bg.RunWorkerCompleted += (_, ev) =>
            {
                var r = ev.Result as object[];
                if (r == null || (int)r[4] != _rootVerifySeq) return;   // une vérif plus récente existe
                int code = (int)r[0];
                string err = (string)r[1];
                string cand = (string)r[2];
                string mode = (string)r[3];

                if (mode == "new" && !_isUpdateMode)
                {
                    // On n'a PAS testé le contenu du champ (root est ouvert, le
                    // mot de passe saisi est FUTUR) : seul l'état du serveur compte.
                    if (code == 0)
                    {
                        if (string.IsNullOrEmpty(cand))
                            SetRootHint("Root actuellement SANS mot de passe — définissez-en un ici (obligatoire, 8 min.)", "new");
                        else
                            SetRootHint("Nouveau mot de passe retenu — il sera appliqué par l'installation ✓", "new");
                    }
                    else if (code == -1 && err == null)
                        SetRootHint("Vérification impossible (mysql.exe ?) — passons en saisie manuelle", "bad");
                    else
                        SetRootHint("MySQL ne répond pas — démarrez-le dans le panneau XAMPP", "bad");
                }
                else if (mode == "new" && _isUpdateMode)
                {
                    // MAJ + root ouvert : le champ contient le mot de passe ACTUEL
                    // (vide = root réellement sans mot de passe). Test réel du
                    // candidat fait dans DoWork — reprendre sa sémantique.
                    if (code == 0)
                        SetRootHint("Root SANS mot de passe actuel — vérifié ✓ (la MAJ l'utilisera tel quel)", "good");
                    else if (AuthDenied(err) && !string.IsNullOrEmpty(cand))
                        SetRootHint("Mot de passe root refusé par le serveur ✗ — corrigez", "bad");
                    else if (code == -1 && err == null)
                        SetRootHint("Vérification impossible (mysql.exe ?)", "bad");
                    else
                        SetRootHint("MySQL ne répond pas — démarrez-le dans le panneau XAMPP", "bad");
                }
                else if (code == 0)
                {
                    SetRootHint("Mot de passe root vérifié ✓ (accepté par le serveur)", "good");
                }
                else if (AuthDenied(err) && !string.IsNullOrEmpty(cand))
                {
                    SetRootHint("Mot de passe root refusé par le serveur ✗", "bad");
                    Log("⚠ Le mot de passe root saisi est REFUSÉ (ERROR 1045) : root est protégé par", true);
                    Log("  un autre mot de passe. L'installation échouera à l'étape [3/9] si le champ ne", true);
                    Log("  contient pas le BON mot de passe existant.", true);
                    Log("", false);
                }
                else if (code == -1 && err == null)
                {
                    SetRootHint("Vérification impossible (mysql.exe ?) — passons en saisie manuelle", "bad");
                }
                else
                {
                    // 2003 Can't connect, etc. : serveur non joignable, pas un mauvais mdp.
                    SetRootHint("MySQL ne répond pas — démarrez-le dans le panneau XAMPP", "bad");
                }
                UpdateRunButtonState();
            };
            bg.RunWorkerAsync();
        }

        // Mot de passe fort : lettres + chiffres + symboles, sans caractères ambigus
        // (0/O, 1/l/I) ni apostrophe/antislash (littéraux SQL/JSON dans le .ps1).
        private static string GeneratePassword(int len)
        {
            const string chars = "ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!#$%*+-.=?@_";
            var sb = new StringBuilder();
            using (var rng = new RNGCryptoServiceProvider())
            {
                byte[] buf = new byte[len];
                rng.GetBytes(buf);
                foreach (byte b in buf) sb.Append(chars[b % chars.Length]);
            }
            return sb.ToString();
        }

        private static string XamppDir()
        {
            string c = Path.Combine(Path.GetPathRoot(Assembly.GetExecutingAssembly().Location) ?? "C:\\", "xampp");
            return Directory.Exists(c) ? c : "C:\\xampp";
        }

        // ── Bandeau titre (dégradé vert, dessiné en GDI+) ────────
        private Control BuildBanner()
        {
            var banner = new GdiBanner { Dock = DockStyle.Top, Height = 92 };
            banner.Padding = new Padding(24, 14, 24, 12);

            var grid = new TableLayoutPanel { Dock = DockStyle.Fill, ColumnCount = 2, RowCount = 1, BackColor = Color.Transparent };
            grid.ColumnStyles.Add(new ColumnStyle(SizeType.AutoSize));
            grid.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 100F));
            grid.RowStyles.Add(new RowStyle(SizeType.Percent, 100F));

            var logo = new GdiCircle { Size = new Size(52, 52) };

            var stack = new FlowLayoutPanel { FlowDirection = FlowDirection.TopDown, AutoSize = true,
                AutoSizeMode = AutoSizeMode.GrowAndShrink, WrapContents = false, Margin = new Padding(16, 2, 0, 0),
                Anchor = AnchorStyles.Left, BackColor = Color.Transparent };
            var lblTitle = new Label { Text = "Installation de PharmaCare",
                Font = new Font("Segoe UI", 15.5F, FontStyle.Bold), ForeColor = Color.White, AutoSize = true,
                Margin = new Padding(0), BackColor = Color.Transparent };
            var lblSubtitle = new Label { Text = "Assistant de déploiement production (XAMPP LAN)",
                ForeColor = Color.FromArgb(200, 236, 221), AutoSize = true,
                Margin = new Padding(0, 3, 0, 0), BackColor = Color.Transparent };
            stack.Controls.Add(lblTitle);
            stack.Controls.Add(lblSubtitle);
            _lblTitle = lblTitle; _lblSubtitle = lblSubtitle;

            grid.Controls.Add(logo, 0, 0);
            grid.Controls.Add(stack, 1, 0);
            banner.Controls.Add(grid);
            return banner;
        }

        // ── Panneau de parametres : sections titrees + grille 3 colonnes ──
        // Structure (chaque section = en-tete + lignes libelle|champ|aide) :
        //   Type d'installation (radios MAJ/neuve + retour de detection)
        //   Deploiement       (hote)
        //   Serveur MySQL     (mot de passe root)
        //   Comptes PharmaCare(admin / pharmacien / caissier + options)
        private Control BuildParamsPanel()
        {
            var card = new GdiCard { Dock = DockStyle.Top, AutoSize = true, AutoSizeMode = AutoSizeMode.GrowAndShrink };
            card.Padding = new Padding(24, 16, 24, 16);

            var grid = new TableLayoutPanel { Dock = DockStyle.Fill, AutoSize = true,
                ColumnCount = 3, RowCount = 12 };
            grid.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 26F));
            grid.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 37F));
            grid.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 37F));
            for (int i = 0; i < 12; i++) grid.RowStyles.Add(new RowStyle(SizeType.AutoSize));

            _host       = new TextBox { Text = "pharmacare.lan", TabIndex = 1 };
            _rootPass   = PwBox(2);
            _adminPass  = PwBox(3);
            _pharmaPass = PwBox(4);
            _caissPass  = PwBox(5);

            AddSection(grid, 0, "Type d’installation");
            _modeHint = new Label { Text = "Recherche d’une installation existante en cours…",
                AutoSize = true, ForeColor = Mute, Font = new Font("Segoe UI", 8.25F, FontStyle.Italic),
                Anchor = AnchorStyles.Left, Margin = new Padding(0, 2, 0, 4) };

            AddSection(grid, 2, "Déploiement");
            AddField(grid, 3, "Adresse d’accès (hôte)", _host, "http://<hôte> — enregistrement DNS local requis");

            AddSection(grid, 4, "Serveur MySQL");
            _rootHint = AddField(grid, 5, "Mot de passe root MySQL *", _rootPass, "détection automatique en cours…");

            AddSection(grid, 6, "Comptes PharmaCare");
            AddField(grid, 7, "Mot de passe admin *",      _adminPass,  "compte administrateur");
            AddField(grid, 8, "Mot de passe pharmacien *", _pharmaPass, "compte pharmacien");
            AddField(grid, 9, "Mot de passe caissier *",   _caissPass,  "compte caissier");

            // Ligne 10 : option de saisie (utilise le mot de passe admin pour les 3 comptes).
            var cbHolder = new Panel { Dock = DockStyle.Fill, Margin = new Padding(0, 2, 0, 0) };
            _samePass = new CheckBox { Text = "Même mot de passe pour les 3 comptes",
                AutoSize = true, ForeColor = Mute, Location = new Point(0, 2), TabIndex = 6 };
            cbHolder.Controls.Add(_samePass);
            grid.Controls.Add(MakeCellLabel(""), 0, 10);
            grid.Controls.Add(cbHolder, 1, 10);
            grid.Controls.Add(MakeCellHint("utilise le mot de passe admin"), 2, 10);

            // Ligne 11 : réinstallation neuve explicite (mode MAJ uniquement, cachée par défaut).
            var freshHolder = new Panel { Dock = DockStyle.Fill, Margin = new Padding(0, 2, 0, 0) };
            _freshCheck = new CheckBox { Text = "⚠ Réinstallation neuve : EFFACER la base et la recréer (dump de secours automatique)",
                AutoSize = true, ForeColor = Color.FromArgb(185, 28, 28), Location = new Point(0, 2), Visible = false, TabIndex = 7 };
            freshHolder.Controls.Add(_freshCheck);
            grid.Controls.Add(MakeCellLabel(""), 0, 11);
            grid.Controls.Add(freshHolder, 1, 11);
            grid.SetColumnSpan(freshHolder, 2);
            _freshCheck.CheckedChanged += (s, e) => { ApplyMode(true); };

            // Radios de mode : la MAJ n'est activée que si une installation
            // existante est détectée (la mention au-dessus des radios l'explique).
            var modeFlow = new FlowLayoutPanel { FlowDirection = FlowDirection.LeftToRight,
                AutoSize = true, AutoSizeMode = AutoSizeMode.GrowAndShrink, WrapContents = false,
                Margin = new Padding(0, 0, 0, 4), Anchor = AnchorStyles.Left };
            var rbUpdate = new RadioButton { Text = "Mise à jour (base et comptes conservés)",
                AutoSize = true, ForeColor = Mute, Margin = new Padding(0, 4, 16, 0), Checked = false,
                Enabled = false, TabIndex = 8, Cursor = Cursors.Hand };
            var rbFresh  = new RadioButton { Text = "Installation neuve",
                AutoSize = true, ForeColor = Mute, Margin = new Padding(0, 4, 0, 0), Checked = true,
                TabIndex = 9, Cursor = Cursors.Hand };
            modeFlow.Controls.Add(rbUpdate);
            modeFlow.Controls.Add(rbFresh);
            _rbUpdate = rbUpdate; _rbFresh = rbFresh;
            rbUpdate.CheckedChanged += (s, e) => { if (rbUpdate.Checked) ApplyMode(true); };
            rbFresh.CheckedChanged  += (s, e) => { if (rbFresh.Checked)  ApplyMode(false); };
            grid.Controls.Add(MakeCellLabel(""), 0, 1);
            grid.SetColumnSpan(modeFlow, 2);
            grid.Controls.Add(modeFlow, 0, 1);
            grid.Controls.Add(_modeHint, 2, 1);

            _samePass.CheckedChanged += (s, e) => {
                bool en = !_samePass.Checked;
                _pharmaPass.Enabled = en; _caissPass.Enabled = en;
                if (!en) { _pharmaPass.Text = ""; _caissPass.Text = ""; }
            };

            card.Controls.Add(grid);
            return card;
        }

        // Mode d'affichage : 'default' (champ), sinon nb de caractères min.
        private static TextBox PwBox(int tabIndex = 0) { return new TextBox { UseSystemPasswordChar = true, TabIndex = tabIndex }; }

        // Libellé de champ : ink foncé, aligné à gauche de sa ligne.
        private static Label MakeCellLabel(string text)
        {
            return new Label { Text = text, AutoSize = true, ForeColor = Ink,
                               Anchor = AnchorStyles.Left, Margin = new Padding(0, 10, 12, 2) };
        }

        // Aide contextuelle d'option (gris, italique, ton uniforme).
        private static Label MakeCellHint(string text)
        {
            return new Label { Text = text, AutoSize = true, ForeColor = Mute,
                Font = new Font("Segoe UI", 8F), Anchor = AnchorStyles.Left, Margin = new Padding(0, 10, 0, 2) };
        }

        // En-tête de section : libellé semibold couvrant les 3 colonnes.
        private static void AddSection(TableLayoutPanel grid, int row, string title)
        {
            var t = new Label { Text = title, AutoSize = true, ForeColor = Ink,
                Font = new Font("Segoe UI", 9.5F, FontStyle.Bold),
                Anchor = AnchorStyles.Left, Margin = new Padding(0, 8, 0, 4) };
            grid.Controls.Add(t, 0, row);
            grid.SetColumnSpan(t, 3);
        }

        private static Label AddField(TableLayoutPanel grid, int row, string label, TextBox box, string hint)
        {
            grid.Controls.Add(MakeCellLabel(label), 0, row);

            // Champ + bouton « Voir » pour un mot de passe (sinon champ seul) :
            // empilés dans une sous-grille 2 colonnes (champ | œil), ce qui garde
            // tous les champs alignés quel que soit le DPI.
            Control fieldCell = box;
            if (box.UseSystemPasswordChar)
            {
                var holder = new TableLayoutPanel { Dock = DockStyle.Fill, AutoSize = true,
                    ColumnCount = 2, Margin = new Padding(0) };
                holder.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 100F));
                holder.ColumnStyles.Add(new ColumnStyle(SizeType.AutoSize));
                holder.RowStyles.Add(new RowStyle(SizeType.Percent, 100F));

                box.Dock = DockStyle.Fill;
                box.Margin = new Padding(0, 6, 6, 2);
                holder.Controls.Add(box, 0, 0);

                var eye = new Button {
                    Text = "Voir", Size = new Size(56, 25), FlatStyle = FlatStyle.Flat,
                    BackColor = Color.White, ForeColor = Mute,
                    Font = new Font("Segoe UI", 8.5F), Margin = new Padding(0, 6, 0, 2),
                    Anchor = AnchorStyles.Left
                };
                eye.FlatAppearance.BorderColor = Line;
                var tb = box;                       // capture pour le lambda
                eye.Click += (s, e) =>
                {
                    bool vis = tb.UseSystemPasswordChar;
                    tb.UseSystemPasswordChar = !vis;
                    eye.Text = vis ? "Cacher" : "Voir";
                };
                holder.Controls.Add(eye, 1, 0);
                fieldCell = holder;
            }
            else
            {
                box.Dock = DockStyle.Fill;
                box.Margin = new Padding(0, 6, 12, 2);
            }
            grid.Controls.Add(fieldCell, 1, row);

            var h = new Label { Text = hint, AutoSize = true, ForeColor = Mute,
                Font = new Font("Segoe UI", 8F), Anchor = AnchorStyles.Left | AnchorStyles.Top,
                Margin = new Padding(0, 10, 0, 2) };
            grid.Controls.Add(h, 2, row);
            return h;
        }

        // ── Journal (carte sombre type terminal, remplissage) ────
        private Control BuildLogPanel()
        {
            var card = new GdiCard {
                Dock = DockStyle.Fill, MinimumSize = new Size(240, 200),
                FillColor = LogBg, Radius = 12
            };
            // Marge interne : le RichTextBox (rectangle) reste en retrait de 10 px
            // pour que les coins arrondis de la carte restent visibles.
            card.Padding = new Padding(10);
            _cardLog = card;

            // Police 10 pt (au lieu de 9) : le journal est le point de lecture
            // principal pendant l'installation — la lisibilité prime.
            _log = new RichTextBox {
                Dock = DockStyle.Fill, ReadOnly = true, BorderStyle = BorderStyle.None,
                BackColor = LogBg, ForeColor = LogFg,
                Font = new Font("Consolas", 10F),
                TabStop = false,
                // WordWrap actif : aucune ligne du journal n'est coupée, même les
                // longues (chemins de dump, messages d'erreur multiline).
                WordWrap = true,
                ScrollBars = RichTextBoxScrollBars.Vertical
            };
            // Clic droit : « Copier tout » (l'utilisateur doit pouvoir extraire
            // le journal pour un rapport d'erreur sans sélection à la souris).
            var logMenu = new ContextMenuStrip();
            logMenu.Items.Add("Copier tout", null, (s, e) =>
            {
                try { if (_log.TextLength > 0) Clipboard.SetText(_log.Text); } catch {}
            });
            logMenu.Items.Add("Effacer", null, (s, e) => _log.Clear());
            _log.ContextMenuStrip = logMenu;
            card.Controls.Add(_log);
            return card;
        }

        // ── Pied de page : statut + progression a gauche, boutons a droite ──
        private Control BuildFooter()
        {
            var foot = new Panel { Dock = DockStyle.Bottom, Height = 86, BackColor = Color.White,
                                   Padding = new Padding(24, 12, 24, 12) };

            var grid = new TableLayoutPanel { Dock = DockStyle.Fill, ColumnCount = 2, RowCount = 1 };
            grid.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 100F));
            grid.ColumnStyles.Add(new ColumnStyle(SizeType.AutoSize));
            grid.RowStyles.Add(new RowStyle(SizeType.Percent, 100F));

            // Colonne gauche : statut au-dessus de la barre de progression.
            var left = new TableLayoutPanel { Dock = DockStyle.Fill, ColumnCount = 1, RowCount = 2, Margin = new Padding(0) };
            left.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 100F));
            left.RowStyles.Add(new RowStyle(SizeType.AutoSize));
            left.RowStyles.Add(new RowStyle(SizeType.Percent, 100F));

            _status = new Label { Text = "Prêt — renseignez les champs ci-dessus.",
                Font = new Font("Segoe UI", 9F, FontStyle.Bold), ForeColor = Ink, AutoSize = true,
                Anchor = AnchorStyles.Left, Margin = new Padding(0, 2, 8, 2) };
            _prog = new ProgressBar { Style = ProgressBarStyle.Marquee, Visible = false, Height = 16,
                Dock = DockStyle.Fill, Margin = new Padding(0, 8, 12, 4) };
            left.Controls.Add(_status, 0, 0);
            left.Controls.Add(_prog, 0, 1);
            grid.Controls.Add(left, 0, 0);

            // Colonne droite : les 3 boutons, alignes a droite sur une seule ligne.
            // Tailles uniformes (40 px de haut) ; action principale (verte) la plus
            // large et la plus a droite — dernier point d'attention avant le clic.
            // TabIndex 20-22 : les actions viennent APRES les champs dans l'ordre
            // de tabulation (les champs sont 1-9).
            _btnRun = new Button { Text = "Lancer l’installation", BackColor = Brand, ForeColor = Color.White,
                FlatStyle = FlatStyle.Flat, Size = new Size(176, 40),
                Font = new Font("Segoe UI", 9.5F, FontStyle.Bold), Margin = new Padding(8, 0, 0, 0),
                TabIndex = 20, Cursor = Cursors.Hand };
            _btnRun.FlatAppearance.BorderSize = 0;
            _btnRun.FlatAppearance.MouseOverBackColor = BrandHover;
            _btnOpen = new Button { Text = "Ouvrir PharmaCare", Enabled = false,
                BackColor = DarkBtn, ForeColor = Color.White,
                FlatStyle = FlatStyle.Flat, Size = new Size(150, 40), Margin = new Padding(8, 0, 0, 0),
                TabIndex = 21, Cursor = Cursors.Hand };
            _btnOpen.FlatAppearance.BorderSize = 0;
            _btnOpen.FlatAppearance.MouseOverBackColor = DarkBtnHover;
            _btnQuit = new Button { Text = "Quitter", BackColor = Color.White, ForeColor = DarkBtn,
                FlatStyle = FlatStyle.Flat, Size = new Size(96, 40),
                Margin = new Padding(8, 0, 0, 0), TabIndex = 22, Cursor = Cursors.Hand };
            _btnQuit.FlatAppearance.BorderColor = Line;
            _btnQuit.FlatAppearance.MouseOverBackColor = Color.FromArgb(240, 243, 247);

            // Flow RightToLeft : le premier ajoute est le plus a droite.
            var btns = new FlowLayoutPanel { FlowDirection = FlowDirection.RightToLeft,
                AutoSize = true, AutoSizeMode = AutoSizeMode.GrowAndShrink, WrapContents = false,
                Margin = new Padding(0), Anchor = AnchorStyles.Right };
            btns.Controls.Add(_btnRun);
            btns.Controls.Add(_btnOpen);
            btns.Controls.Add(_btnQuit);
            grid.Controls.Add(btns, 1, 0);

            foot.Controls.Add(grid);
            return foot;
        }

        private string ScriptDir() { return Path.GetDirectoryName(Assembly.GetExecutingAssembly().Location); }

        private void Welcome()
        {
            Log("Bienvenue dans l’assistant d’installation de PharmaCare.", false);
            Log("", false);
            Log("1. Renseignez le formulaire ci-dessus (root est pré-rempli si detecté).", false);
            Log("2. Cliquez sur « Lancer » — le détail de chaque étape s’affiche ici.", false);
            Log("", false);
            Log("L’assistant va : sécuriser root MySQL, créer la base pharmacare et", false);
            Log("importer le schéma, générer config/env.prod.php, définir les mots de", false);
            Log("passe des comptes, optimiser (InnoDB, OPcache) et configurer Apache.", false);
            Log("", false);
            Log("Sécurité : les mots de passe transitent via un fichier temporaire effacé", true);
            Log("immédiatement après lecture (jamais en ligne de commande).", true);
            Log("", false);
        }

        // Réactivité du bouton principal : désactivé tant que la validation échoue.
        private void UpdateRunButtonState()
        {
            if (_btnRun == null || _btnRun.IsDisposed) return;
            _btnRun.Enabled = ValidateForm() == null;
        }

        private string ValidateForm()
        {
            if (string.IsNullOrWhiteSpace(_host.Text)) return "Nom d’hôte requis.";
            if (_isUpdateMode)
            {
                if (_freshCheck.Visible && _freshCheck.Checked)
                {
                    // Réinstallation neuve depuis le mode MAJ : sémantique « install
                    // neuve » (install_prod.ps1 -Fresh) — les 3 comptes seront
                    // réinitialisés, leurs mots de passe sont donc requis. La
                    // confirmation EFFACER est demandée au clic (dialogue), pas ici :
                    // le bouton doit rester accessible.
                    if (_rootMode == "new")
                    {
                        if (_rootPass.Text.Length < 8) return "Réinstallation neuve : mot de passe root à définir (8 caractères minimum).";
                    }
                    else if ((_rootPass.Text ?? "").Length == 0)
                    {
                        return "Réinstallation neuve : mot de passe root ACTUEL requis.";
                    }
                    if ((_adminPass.Text ?? "").Length < 8) return "Réinstallation neuve : mot de passe admin à redéfinir (8 caractères minimum).";
                    if (!_samePass.Checked)
                    {
                        if ((_pharmaPass.Text ?? "").Length < 8) return "Réinstallation neuve : mot de passe pharmacien à redéfinir (8 caractères minimum).";
                        if ((_caissPass.Text ?? "").Length < 8)  return "Réinstallation neuve : mot de passe caissier à redéfinir (8 caractères minimum).";
                    }
                    return null;
                }
                // MAJ : le champ root doit contenir le mot de passe root EXISTANT
                // (celui que update_prod.ps1 utilisera pour le dump de sécurité).
                // Exception : root réellement SANS mot de passe (mode "new", vérifié
                // par la sonde) — le champ vide est alors une valeur valide.
                if (_rootPass.Text.Length == 0 && _rootMode != "new")
                    return "Mot de passe root ACTUEL requis — laisser VIDE seulement si root est sans mot de passe (statut ✓ vérifié).";
                return null;
            }
            if ((_rootPass.Text ?? "").Length < 8)  return "Mot de passe root : à définir (8 caractères minimum) — il sera appliqué au serveur.";
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
            bool update = _isUpdateMode;
            bool freshFromUpdate = update && _freshCheck.Visible && _freshCheck.Checked;
            string ps1;
            if (freshFromUpdate)      ps1 = Path.Combine(dir, "install_prod.ps1");
            else if (update)          ps1 = Path.Combine(dir, "update_prod.ps1");
            else                      ps1 = Path.Combine(dir, "install_prod.ps1");
            if (freshFromUpdate && !File.Exists(ps1)) ps1 = Path.Combine(dir, "pharmacare", "install_prod.ps1");
            if (update && !File.Exists(ps1)) ps1 = Path.Combine(dir, "pharmacare", "update_prod.ps1");
            if (!File.Exists(ps1))
            {
                Log("✗ Fichier introuvable : " + ps1, true);
                Log(update
                    ? "  Placez install_prod.exe à côté de update_prod.ps1 (bundle de mise à jour)."
                    : "  Placez install_prod.exe à côté de install_prod.ps1.", true);
                return;
            }

            // Réinstallation neuve : confirmation EFFACER tapee au clavier au clic.
            if (update && _freshCheck.Visible && _freshCheck.Checked && !_freshConfirmed)
            {
                if (!AskConfirmFresh()) return;
                _freshConfirmed = true;
            }

            // Fichier de parametres temporaire (JSON) — secrets hors ligne de commande.
            try
            {
                _paramFile = Path.GetTempFileName();
                string json;
                if (update && !freshFromUpdate)
                {
                    // MAJ : AUCUN mot de passe applicatif transmis (comptes conserves).
                    // rootPass = filet de secours uniquement si env.prod.php est illisible.
                    json = "{" +
                        "\"hostname\":"   + J(_host.Text.Trim()) +
                        ",\"rootPass\":"  + J(_rootPass.Text) +
                        ",\"target\":"    + J(ExistingTargetDir()) +
                        "}";
                }
                else
                {
                    // Installation neuve OU reinstallation neuve (-Fresh) : les 3
                    // comptes sont (re)initialises -> leurs mots de passe transitent.
                    string pharma = _samePass.Checked ? _adminPass.Text : _pharmaPass.Text;
                    string caiss  = _samePass.Checked ? _adminPass.Text : _caissPass.Text;
                    json = "{" +
                        "\"hostname\":"       + J(_host.Text.Trim()) +
                        ",\"rootPass\":"      + J(_rootPass.Text) +
                        ",\"adminPass\":"     + J(_adminPass.Text) +
                        ",\"pharmacienPass\":"+ J(pharma) +
                        ",\"caissierPass\":"  + J(caiss) +
                        ",\"fresh\":"         + (freshFromUpdate ? "true" : "false") +
                        "}";
                }
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
            _status.Text = update ? "Mise à jour en cours…" : "Installation en cours…";
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
                        _status.Text = update ? "Mise à jour terminée avec succès." : "Installation terminée avec succès.";
                        _status.ForeColor = Brand;
                        Log("", false);
                        Log(update
                            ? "=== Mise à jour terminée (code 0) — données, comptes et mots de passe conservés ==="
                            : "=== Installation terminée (code 0) ===", false, true);
                        Log("URL : " + ResolveAppUrl(), false, true);
                        _btnOpen.Enabled = true;
                    }
                    else
                    {
                        _status.Text = update
                            ? "Échec de la mise à jour (code " + code + ")."
                            : "Échec de l’installation (code " + code + ").";
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

    // ── Rendu GDI+ « cartes » ─────────────────────────────────
    // Carte arrondie avec ombre douce, dessinée à la main. Le fond derrière les
    // coins (BehindColor) reste celui de la fenêtre ; FillColor est la carte.
    internal class GdiCard : Panel
    {
        public int Radius = 14;
        public Color FillColor   = Color.White;                        // couleur de la carte
        public Color BehindColor = Color.FromArgb(243, 246, 250);      // fond autour des coins

        public GdiCard()
        {
            DoubleBuffered = true;
            SetStyle(ControlStyles.ResizeRedraw | ControlStyles.OptimizedDoubleBuffer, true);
        }

        protected override void OnPaintBackground(PaintEventArgs e)
        {
            using (var b = new SolidBrush(BehindColor))
                e.Graphics.FillRectangle(b, ClientRectangle);
        }

        protected override void OnPaint(PaintEventArgs e)
        {
            var g = e.Graphics;
            g.SmoothingMode = SmoothingMode.AntiAlias;
            var rect = new Rectangle(0, 0, Width - 1, Height - 1);
            using (var path = Rounded(rect, Radius))
            {
                // Ombre douce : rectangle décalé, tracé d'abord (sous la carte).
                var sha = new Rectangle(2, 3, Width - 5, Height - 5);
                using (var shadowPath = Rounded(sha, Radius))
                using (var sb = new SolidBrush(Color.FromArgb(20, 15, 23, 42)))
                    g.FillPath(sb, shadowPath);

                using (var b = new SolidBrush(FillColor)) g.FillPath(b, path);
                using (var p = new Pen(Color.FromArgb(226, 232, 240)))
                    g.DrawPath(p, path);
            }
        }

        internal static GraphicsPath Rounded(Rectangle r, int radius)
        {
            int d = Math.Min(radius * 2, Math.Min(r.Width, r.Height));
            var p = new GraphicsPath();
            p.AddArc(r.X, r.Y, d, d, 180, 90);
            p.AddArc(r.Right - d, r.Y, d, d, 270, 90);
            p.AddArc(r.Right - d, r.Bottom - d, d, d, 0, 90);
            p.AddArc(r.X, r.Bottom - d, d, d, 90, 90);
            p.CloseFigure();
            return p;
        }
    }

    // Bandeau : dégradé vertical vert (haut clair -> bas foncé).
    internal class GdiBanner : Panel
    {
        public GdiBanner()
        {
            DoubleBuffered = true;
        }

        protected override void OnPaintBackground(PaintEventArgs e)
        {
            var g = e.Graphics;
            var rect = new Rectangle(0, 0, Width, Height);
            using (var b = new LinearGradientBrush(rect,
                Color.FromArgb(24, 130, 63),
                Color.FromArgb(16, 82, 44),
                LinearGradientMode.Vertical))
            {
                g.FillRectangle(b, rect);
            }
            // Liseré inférieur légèrement plus sombre pour détacher du contenu.
            using (var p = new Pen(Color.FromArgb(12, 62, 33)))
                g.DrawLine(p, 0, Height - 1, Width, Height - 1);
        }
    }

    // Logo circulaire (Rx) dessiné entièrement dans OnPaint (pas de contrôle
    // enfant : pas de souci de transparence sur le fond dégradé).
    internal class GdiCircle : Panel
    {
        public string Glyph = "Rx";

        public GdiCircle()
        {
            DoubleBuffered = true;
            SetStyle(ControlStyles.ResizeRedraw | ControlStyles.OptimizedDoubleBuffer |
                     ControlStyles.SupportsTransparentBackColor, true);
        }

        protected override void OnPaintBackground(PaintEventArgs e) { /* rien : le bandeau reste visible autour du disque */ }

        protected override void OnPaint(PaintEventArgs e)
        {
            var g = e.Graphics;
            g.SmoothingMode = SmoothingMode.AntiAlias;
            int w = Math.Min(Width, Height) - 2;
            var rect = new Rectangle(1, 1, w, w);
            using (var b = new SolidBrush(Color.White))
                g.FillEllipse(b, rect);
            using (var p = new Pen(Color.FromArgb(160, 255, 255, 255), 2F))
                g.DrawEllipse(p, rect);
            TextRenderer.DrawText(g, Glyph, new Font("Segoe UI", 17F, FontStyle.Bold),
                rect, Color.FromArgb(22, 101, 52), Color.Transparent,
                TextFormatFlags.HorizontalCenter | TextFormatFlags.VerticalCenter);
        }
    }
}