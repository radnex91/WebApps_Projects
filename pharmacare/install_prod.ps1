<#
.SYNOPSIS
    Installation et configuration de PharmaCare pour la production (XAMPP LAN).
.DESCRIPTION
    Ce script :
      1. Detecte les chemins XAMPP (MySQL, PHP, Apache)
      2. Securise le compte root MySQL (mot de passe fort)
      3. Cree la base de donnees `pharmacare`
      4. Importe le schema (database.sql)
      5. Genere config/env.prod.php avec l'IP LAN du serveur
      6. Hash et change les mots de passe des 3 comptes demo
      7. Configure Apache (AllowOverride All + mod_rewrite + mod_headers)
      8. Ouvre le pare-feu Windows pour le port 80 (LAN)
      9. Affiche un resume + smoke test
.NOTES
    A executer sur le PC SERVEUR (celui qui heberge XAMPP).
    Droits administrateur requis.
#>

#Requires -RunAsAdministrator

# ── Parametres (mode non-interactif pilote par la GUI install_prod.exe) ──
# En mode console sans parametres : comportement interactif d'origine (Read-Host).
# En mode -NonInteractive (ou -ParamFile) : aucun prompt, utilise les valeurs
# fournies. Les secrets transitent via -ParamFile (JSON temporaire efface apres
# lecture) — JAMAIS sur la ligne de commande (process list / journaux).
param(
    [switch]$NonInteractive,
    [switch]$Fresh,
    [string]$Hostname,
    [string]$RootPass,
    [string]$AdminPass,
    [string]$PharmacienPass,
    [string]$CaissierPass,
    [string]$ParamFile
)

# Lecture du fichier de parametres (GUI) -> ecrase les params + force NonInteractive
if ($ParamFile -and (Test-Path -LiteralPath $ParamFile)) {
    $cfg = Get-Content -LiteralPath $ParamFile -Raw -Encoding UTF8 | ConvertFrom-Json
    $Hostname       = $cfg.hostname
    $RootPass       = $cfg.rootPass
    $AdminPass      = $cfg.adminPass
    $PharmacienPass = $cfg.pharmacienPass
    $CaissierPass   = $cfg.caissierPass
    # Reinstallation NEUVE explicite (efface la base existante apres dump) :
    # jamais active par defaut — l'assistant la propose seulement si l'utilisateur
    # a coche la case de confirmation.
    if ($cfg.PSObject.Properties['fresh'] -and $cfg.fresh) { $Fresh = [bool]$cfg.fresh }
    Remove-Item -LiteralPath $ParamFile -Force -ErrorAction SilentlyContinue
    $NonInteractive = $true
}

# ── Strict mode + erreurs fatales ─────────────────────────────
$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

# ── Variables XAMPP (adaptables) ──────────────────────────────
$XAMPP_DIR = 'C:\xampp'
$MYSQL     = "$XAMPP_DIR\mysql\bin\mysql.exe"
$MYSQLDUMP = "$XAMPP_DIR\mysql\bin\mysqldump.exe"
$PHP       = "$XAMPP_DIR\php\php.exe"
$APACHE    = "$XAMPP_DIR\apache"
$APP_DIR   = $PSScriptRoot                    # dossier ou se trouve ce script
$SQL_FILE  = Join-Path $APP_DIR '_archive\database.sql'
$ENV_FILE  = Join-Path $APP_DIR 'config\env.prod.php'

# ── Banniere ──────────────────────────────────────────────────
function Write-Banner {
    Write-Host ''
    Write-Host '  ============================================' -ForegroundColor Cyan
    Write-Host '   PharmaCare - Installation Production LAN' -ForegroundColor Cyan
    Write-Host '  ============================================' -ForegroundColor Cyan
    Write-Host ''
}

# ── Fichier d'identifiants MySQL temporaire ────────────────────
# On passe le mot de passe via --defaults-extra-file plutot que -p$pass sur la
# ligne de commande. Avantages :
#   1. Supprime le warning stderr "[Warning] Using a password on the command line"
#      (qui, sous $ErrorActionPreference='Stop', est converti en erreur terminante
#       et declenchait un throw fallacieux -> "Mot de passe root incorrect" MEME
#       avec le bon mot de passe).
#   2. Robuste aux caracteres speciaux (espaces, $, @, etc.) dans le mot de passe.
# Format ini [client] ; mot de passe entre guillemets pour tolerer les espaces.
function New-MysqlDefaultsFile([string]$user, [string]$pass) {
    $path = [System.IO.Path]::GetTempFileName()
    # Echapper \ et " pour le format ini (idem que includes/sauvegarde.php).
    $u = $user -replace '\\', '\\' -replace '"', '\"'
    $p = $pass -replace '\\', '\\' -replace '"', '\"'
    $content = "[client]`r`nuser=`"$u`"`r`npassword=`"$p`"`r`n"
    # UTF-8 SANS BOM : avec un BOM, mysql lit "[BOM][client]" et rejette le
    # fichier ("Found option without preceding group at line 1"). Encoding.UTF8
    # ajoute un BOM ; on utilise UTF8Encoding($false) pour l'eviter.
    $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText($path, $content, $utf8NoBom)
    return $path
}

# ── Lance mysql et renvoie $true si $LASTEXITCODE == 0 ─────────
# stderr redirigee vers un fichier (pas de creation d'ErrorRecord -> pas de
# throw sous ErrorActionPreference=Stop) ; EAP relacheche localement par
# securite. Verifier uniquement le code de sortie natif, jamais try/catch.
function Invoke-MysqlQuiet([string[]]$mysqlArgs) {
    $ErrorActionPreference = 'Continue'   # local : stderr natif -> pas de throw
    $errFile = [System.IO.Path]::GetTempFileName()
    try {
        & $MYSQL @mysqlArgs 2>$errFile | Out-Null
        return ($LASTEXITCODE -eq 0)
    } finally {
        Remove-Item $errFile -Force -ErrorAction SilentlyContinue
    }
}

# ── Verifications prealables ─────────────────────────────────
function Test-Prerequisites {
    Write-Host '[1/9] Verification des prerequis...' -ForegroundColor Yellow

    if (-not (Test-Path $MYSQL)) { throw "MySQL introuvable : $MYSQL" }
    if (-not (Test-Path $PHP))   { throw "PHP introuvable : $PHP" }
    if (-not (Test-Path $APACHE)) { throw "Apache introuvable : $APACHE" }
    if (-not (Test-Path $SQL_FILE)) { throw "Schema SQL introuvable : $SQL_FILE`nExecutez ce script depuis le dossier pharmacare/." }

    # MySQL doit tourner : sinon tous les appels suivants echoueraient avec un
    # message trompeur (« mot de passe root incorrect ») alors que le vrai
    # probleme est que mysqld est arrete. On teste la joignabilite TCP du port
    # 3306 plutôt que "mysql -u root" : root peut DEJA etre protege (le test
    # anonyme renverrait alors 1045, interprete a tort comme « arrete »).
    $mysqlUp = $false
    try {
        $client = New-Object System.Net.Sockets.TcpClient
        $ar = $client.BeginConnect('127.0.0.1', 3306, $null, $null)
        $mysqlUp = $ar.AsyncWaitHandle.WaitOne(3000) -and $client.Connected
        $client.Close()
    } catch { $mysqlUp = $false }
    if (-not $mysqlUp) {
        throw "MySQL ne repond pas sur 127.0.0.1:3306.`nDemarrez MySQL depuis le panneau de controle XAMPP puis relancez l'installation."
    }

    Write-Host '  OK : MySQL, PHP, Apache, schema SQL detectes.' -ForegroundColor Green
}

# ── Detection IP LAN + nom d'hote ────────────────────────────
function Get-LanIp {
    Write-Host '[2/9] Detection IP LAN + nom d''hote...' -ForegroundColor Yellow

    $ip = (Get-NetIPAddress -AddressFamily IPv4 |
           Where-Object { $_.InterfaceAlias -notmatch 'Loopback' -and $_.IPAddress -notmatch '^169\.254' -and $_.IPAddress -notmatch '^127\.' } |
           Sort-Object -Property InterfaceAlias |
           Select-Object -First 1).IPAddress

    if (-not $ip) { throw 'Aucune IP LAN detectee. Verifiez votre reseau.' }
    Write-Host "  IP LAN detectee : $ip" -ForegroundColor Green

    # Nom d'hote canonique pour l'acces (ex. pharmacare.lan). La resolution est
    # assuree cote client par votre routeur / DNS local (une seule config, tous
    # les postes en profitent). Le serveur lui-meme resout ce nom via son fichier
    # hosts (ajoute a l'etape 7) -> smoke test et auto-acces fonctionnels.
    if ($NonInteractive) {
        $hostname = if ([string]::IsNullOrWhiteSpace($Hostname)) { 'pharmacare.lan' } else { $Hostname }
        Write-Host "  Nom d'hote       : $hostname" -ForegroundColor Green
        Write-Host "  -> A configurer sur votre routeur/DNS local : $hostname = $ip" -ForegroundColor Gray
    } else {
        $hostname = Read-Host "  Nom d'hote souhaite pour l'acces (Entree = pharmacare.lan)"
        if ([string]::IsNullOrWhiteSpace($hostname)) { $hostname = 'pharmacare.lan' }
        Write-Host "  Nom d'hote       : $hostname" -ForegroundColor Green
        Write-Host "  -> A configurer sur votre routeur/DNS local : $hostname = $ip" -ForegroundColor Gray
    }
    $hostname = $hostname.Trim().ToLower()

    return [pscustomobject]@{ Ip = $ip; Hostname = $hostname }
}

# ── Tuning performance (my.ini + OPcache + dossier cache) ─────
# XAMPP livre my.ini avec innodb_buffer_pool_size=16M / log 5M : des que la
# base grossit (ventes, mouvements), MySQL lit le disque pour chaque requete
# et l'application ralentit. On calle le buffer pool sur la RAM du serveur
# (25 %, plafond 2G) et on active OPcache — sinon chaque requete PHP
# recompile tous les fichiers.
function Set-PerformanceTuning {
    Write-Host '[2.5/9] Optimisation des performances (my.ini, OPcache, cache)...' -ForegroundColor Yellow

    # ── (a) my.ini : buffer pool selon RAM + log 128M ──
    $myIni = "$XAMPP_DIR\mysql\bin\my.ini"
    if (Test-Path $myIni) {
        $ramMb = 2048
        try {
            $cs = Get-CimInstance -ClassName Win32_ComputerSystem -ErrorAction Stop
            if ($cs.TotalPhysicalMemory) { $ramMb = [int]($cs.TotalPhysicalMemory / 1MB) }
        } catch { }
        $poolMb = [Math]::Min([Math]::Max(256, [int]($ramMb * 0.25)), 2048)

        $ini = Get-Content $myIni -Raw -ErrorAction SilentlyContinue
        $backupDone = $false
        if ($ini -and ($ini -match '(?m)^\s*innodb_buffer_pool_size\s*=')) {
            if (-not (Test-Path "$myIni.bak-pharmacare")) {
                Copy-Item $myIni "$myIni.bak-pharmacare" -Force
                $backupDone = $true
            }
            $ini = $ini -replace '(?m)^(\s*innodb_buffer_pool_size\s*=)\s*\S+', ('${1}' + $poolMb + 'M')
            $ini = $ini -replace '(?m)^(\s*innodb_log_file_size\s*=)\s*\S+', '${1}128M'
            $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
            [System.IO.File]::WriteAllText($myIni, $ini, $utf8NoBom)
            Write-Host "  OK : innodb_buffer_pool_size=$poolMb`M (RAM ${ramMb} Mo), innodb_log_file_size=128M (backup : my.ini.bak-pharmacare)" -ForegroundColor Green
        } else {
            Write-Host '  ATTENTION : my.ini sans innodb_buffer_pool_size - tuning MySQL ignore.' -ForegroundColor Yellow
        }
    } else {
        Write-Host "  ATTENTION : my.ini introuvable ($myIni) - tuning MySQL ignore." -ForegroundColor Yellow
    }

    # ── (b) php.ini : activer OPcache s'il est commente/absent ──
    $phpIni = "$XAMPP_DIR\php\php.ini"
    if (Test-Path $phpIni) {
        $pi = Get-Content $phpIni -Raw -ErrorAction SilentlyContinue
        if ($pi) {
            $changed = $false
            if ($pi -match '(?m)^\s*;\s*zend_extension\s*=\s*opcache\s*$') {
                if (-not (Test-Path "$phpIni.bak-pharmacare")) { Copy-Item $phpIni "$phpIni.bak-pharmacare" -Force }
                $pi = $pi -replace '(?m)^\s*;\s*zend_extension(\s*)=\s*opcache\s*$', 'zend_extension${1}=opcache'
                $changed = $true
            }
            # Revalidate 2 s : frais de modification de fichiers quasi nuls, cache fiable.
            if ($changed -and ($pi -notmatch '(?m)^\s*opcache\.revalidate_freq\s*=')) {
                $pi = $pi -replace '(?m)^(\s*zend_extension\s*=\s*opcache\s*)$', ('$1' + "`r`nopcache.revalidate_freq=2`r`nopcache.memory_consumption=192`r`nopcache.max_accelerated_files=20000`r`nopcache.jit=tracing`r`nopcache.jit_buffer_size=64M")
            }
            if ($changed) {
                $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
                [System.IO.File]::WriteAllText($phpIni, $pi, $utf8NoBom)
                Write-Host '  OK : OPcache active dans php.ini (backup : php.ini.bak-pharmacare)' -ForegroundColor Green
            } else {
                Write-Host '  OK : OPcache deja actif ou php.ini non modifie.' -ForegroundColor Green
            }
        }
    } else {
        Write-Host "  ATTENTION : php.ini introuvable ($phpIni) - OPcache non configure." -ForegroundColor Yellow
    }

    # ── (c) Dossier cache/ de l'app : cree, inscriptible par Apache, protege du web ──
    $cacheDir = Join-Path $APP_DIR 'cache'
    if (-not (Test-Path $cacheDir)) { New-Item -ItemType Directory -Path $cacheDir | Out-Null }
    $htCache = Join-Path $cacheDir '.htaccess'
    [System.IO.File]::WriteAllText($htCache, "Require all denied`r`nDeny from all`r`nOptions -Indexes`r`n", (New-Object System.Text.UTF8Encoding($false)))
    try {
        $acl = Get-Acl $cacheDir
        $sid = New-Object System.Security.Principal.SecurityIdentifier('S-1-1-0')
        $rule = New-Object System.Security.AccessControl.FileSystemAccessRule($sid, 'Modify', 'ContainerInherit,ObjectInherit', 'None', 'Allow')
        $acl.AddAccessRule($rule)
        Set-Acl $cacheDir $acl
        Write-Host '  OK : cache/ cree (inscriptible, non liste en HTTP).' -ForegroundColor Green
    } catch {
        Write-Host '  OK : cache/ cree (ACL ignoree, .htaccess protege du web).' -ForegroundColor Yellow
    }

    # ── (d) Redemarrage de MySQL pour appliquer my.ini ──
    $ErrorActionPreference = 'Continue'
    & "$XAMPP_DIR\mysql\bin\mysqladmin.exe" -u root shutdown 2>$null
    Start-Sleep -Seconds 3
    $svc = Get-Service -Name mysql -ErrorAction SilentlyContinue
    if ($svc) {
        if ($svc.Status -ne 'Running') {
            Start-Service -Name mysql -ErrorAction SilentlyContinue
            Start-Sleep -Seconds 3
        }
        $svc.Refresh()
        if ($svc.Status -eq 'Running') {
            Write-Host '  OK : MySQL redemarre (nouvelles valeurs InnoDB actives).' -ForegroundColor Green
        } else {
            Write-Host '  ATTENTION : MySQL n''a pas redemarre automatiquement. Demarrez-le depuis le panneau XAMPP.' -ForegroundColor Yellow
        }
    }
    $ErrorActionPreference = 'Stop'
}

# ── Securiser MySQL (mot de passe root) ──────────────────────
function Set-MySQLPassword {
    Write-Host '[3/9] Securisation du compte root MySQL...' -ForegroundColor Yellow

    # Tester si root a deja un mot de passe (connexion sans -p).
    #   $LASTEXITCODE == 0 -> root sans mot de passe ; sinon -> root protege.
    $hasPassword = -not (Invoke-MysqlQuiet @('-u','root','-e','SELECT 1;'))

    # ── Mode non-interactif (GUI) : un seul champ "Mot de passe root".
    #    root sans mdp  -> on le definit a $RootPass.
    #    root avec mdp  -> on s'authentifie avec $RootPass (existant conserve).
    if ($NonInteractive) {
        if ([string]::IsNullOrWhiteSpace($RootPass)) {
            throw 'Mode non-interactif : mot de passe root requis (parametre -RootPass / GUI).'
        }
        if ($RootPass.Length -lt 8) { throw 'Mot de passe root trop court (minimum 8 caracteres).' }
        if ($hasPassword) {
            $ini = New-MysqlDefaultsFile 'root' $RootPass
            try { $ok = Invoke-MysqlQuiet @("--defaults-extra-file=$ini", '-e', 'SELECT 1;') }
            finally { Remove-Item $ini -Force -ErrorAction SilentlyContinue }
            if (-not $ok) { throw 'Mot de passe root fourni incorrect (root deja protege par un autre mot de passe).' }
            Write-Host '  OK : mot de passe root verifie (existant conserve).' -ForegroundColor Green
            return $RootPass
        }
        $plain = $RootPass
        $safe = $plain -replace "'", "''"
        $altered = Invoke-MysqlQuiet @('-u','root','-e',"ALTER USER 'root'@'localhost' IDENTIFIED BY '$safe'; FLUSH PRIVILEGES;")
        if (-not $altered) {
            $altered = Invoke-MysqlQuiet @('-u','root','-e',"SET PASSWORD FOR 'root'@'localhost' = PASSWORD('$safe'); FLUSH PRIVILEGES;")
        }
        if (-not $altered) { throw 'Echec definition mot de passe root.' }
        $ini = New-MysqlDefaultsFile 'root' $plain
        try { $ok = Invoke-MysqlQuiet @("--defaults-extra-file=$ini", '-e', 'SELECT 1;') }
        finally { Remove-Item $ini -Force -ErrorAction SilentlyContinue }
        if (-not $ok) { throw 'Echec definition mot de passe root. Definissez-le manuellement dans phpMyAdmin.' }
        Write-Host '  OK : mot de passe root defini et verifie.' -ForegroundColor Green
        $pmaConfig = "$XAMPP_DIR\phpMyAdmin\config.inc.php"
        if (Test-Path $pmaConfig) {
            $content = Get-Content $pmaConfig -Raw
            # (?m) obligatoire : sur une chaîne Raw multiline, ^ ne matche sinon
            # que le tout début du fichier et la ligne password n'est jamais
            # modifiée (le « OK » ci-dessous était alors mensonger).
            $newContent = $content -replace "(?m)^\s*(\$cfg\['Servers'\]\[\$i\]\['password'\])\s*=\s*'[^']*';", "`$1 = '$plain';"
            if ($newContent -ne $content) {
                Set-Content -Path $pmaConfig -Value $newContent -NoNewline
                Write-Host '  OK : phpMyAdmin mis a jour avec le mot de passe root.' -ForegroundColor Green
            } else {
                Write-Host '  ATTENTION : ligne password phpMyAdmin non trouvee — configurez-la manuellement.' -ForegroundColor Yellow
            }
        }
        return $plain
    }

    if ($hasPassword) {
        $rootPass = Read-Host '  Le compte root a deja un mot de passe. Entrez-le (masque)' -AsSecureString
        $plain = [Runtime.InteropServices.Marshal]::PtrToStringAuto(
                    [Runtime.InteropServices.Marshal]::SecureStringToBSTR($rootPass))
        # Verifier via defaults-file (evite le warning stderr fallacieux + caracteres speciaux).
        $ini = New-MysqlDefaultsFile 'root' $plain
        try {
            $ok = Invoke-MysqlQuiet @("--defaults-extra-file=$ini", '-e', 'SELECT 1;')
        } finally {
            Remove-Item $ini -Force -ErrorAction SilentlyContinue
        }
        if (-not $ok) { throw 'Mot de passe root incorrect.' }
        Write-Host '  OK : mot de passe root verifie.' -ForegroundColor Green
        return $plain
    }

    # root sans mot de passe -> en creer un
    Write-Host '  ATTENTION : root n''a pas de mot de passe (faille de securite sur LAN !)' -ForegroundColor Red
    $newPass = Read-Host '  Nouveau mot de passe root (fort, 12+ caracteres)' -AsSecureString
    $plain = [Runtime.InteropServices.Marshal]::PtrToStringAuto(
                [Runtime.InteropServices.Marshal]::SecureStringToBSTR($newPass))

    if ($plain.Length -lt 8) {
        throw 'Mot de passe trop court (minimum 8 caracteres).'
    }

    # Echapper les quotes pour le litteral SQL (tolerer un mot de passe avec ').
    $safe = $plain -replace "'", "''"
    $altered = Invoke-MysqlQuiet @('-u','root','-e',"ALTER USER 'root'@'localhost' IDENTIFIED BY '$safe'; FLUSH PRIVILEGES;")
    if (-not $altered) {
        # Fallback : SET PASSWORD (syntaxe plus ancienne / MariaDB)
        $altered = Invoke-MysqlQuiet @('-u','root','-e',"SET PASSWORD FOR 'root'@'localhost' = PASSWORD('$safe'); FLUSH PRIVILEGES;")
    }
    if (-not $altered) { throw 'Echec definition mot de passe root.' }

    # Verifier le nouveau mot de passe via defaults-file.
    $ini = New-MysqlDefaultsFile 'root' $plain
    try {
        $ok = Invoke-MysqlQuiet @("--defaults-extra-file=$ini", '-e', 'SELECT 1;')
    } finally {
        Remove-Item $ini -Force -ErrorAction SilentlyContinue
    }
    if (-not $ok) { throw 'Echec definition mot de passe root. Definissez-le manuellement dans phpMyAdmin.' }
    Write-Host '  OK : mot de passe root defini et verifie.' -ForegroundColor Green

    # Mettre a jour config.inc.php de phpMyAdmin (si present)
    $pmaConfig = "$XAMPP_DIR\phpMyAdmin\config.inc.php"
    if (Test-Path $pmaConfig) {
        $content = Get-Content $pmaConfig -Raw
        # (?m) : cf. commentaire dans Set-MySQLPassword (mode non-interactif).
        $newContent = $content -replace "(?m)^\s*(\$cfg\['Servers'\]\[\$i\]\['password'\])\s*=\s*'[^']*';", "`$1 = '$plain';"
        if ($newContent -ne $content) {
            Set-Content -Path $pmaConfig -Value $newContent -NoNewline
            Write-Host '  OK : phpMyAdmin mis a jour avec le mot de passe root.' -ForegroundColor Green
        } else {
            Write-Host '  ATTENTION : ligne password phpMyAdmin non trouvee — configurez-la manuellement.' -ForegroundColor Yellow
        }
    }

    return $plain
}

# ── Dump de secours avant reinstallation NEUVE ────────────────
# L'utilisateur a explicitement demande d'effacer la base (case de confirmation
# GUI / -Fresh) : on refuse de le faire a l'aveugle et on deverse d'abord un
# dump complet dans pharmacare/backups/ (nommage compatible avec le systeme de
# restauration de l'application). Echec du dump = effacement ANNULE.
function Backup-DatabaseFresh([string]$rootIni) {
    $MYSQLDUMP = "$XAMPP_DIR\mysql\bin\mysqldump.exe"
    if (-not (Test-Path $MYSQLDUMP)) {
        throw "mysqldump introuvable ($MYSQLDUMP) : impossible de securiser la base avant effacement. Reinstallation ANNULEE."
    }
    $bkDir = Join-Path $APP_DIR 'backups'
    if (-not (Test-Path $bkDir)) { New-Item -ItemType Directory -Path $bkDir | Out-Null }
    $stamp = Get-Date -Format 'yyyyMMdd_HHmmss'
    $outFile = Join-Path $bkDir "pharmacare_$stamp.sql"

    $ErrorActionPreference = 'Continue'
    $errFile = [System.IO.Path]::GetTempFileName()
    $prevOut = [Console]::OutputEncoding
    $raw = ''
    $ok = $false
    $errText = ''
    try {
        [Console]::OutputEncoding = [System.Text.Encoding]::UTF8
        $raw = (& $MYSQLDUMP "--defaults-extra-file=$rootIni" --single-transaction --quick --routines --triggers pharmacare 2>$errFile) | Out-String
        $ok = ($LASTEXITCODE -eq 0)
    } finally {
        [Console]::OutputEncoding = $prevOut
        $errText = Get-Content $errFile -Raw -ErrorAction SilentlyContinue
        Remove-Item $errFile -Force -ErrorAction SilentlyContinue
    }
    $ErrorActionPreference = 'Stop'
    if ($ok -and $raw) {
        $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
        [System.IO.File]::WriteAllText($outFile, $raw, $utf8NoBom)
    }
    if (-not $ok -or -not (Test-Path $outFile) -or ((Get-Item $outFile).Length -lt 1000)) {
        throw "Dump de secours echoue - reinstallation ANNULEE pour proteger les donnees.`n$errText"
    }
    $kb = [int]((Get-Item $outFile).Length / 1KB)
    Write-Host "  OK : dump de secours -> backups\pharmacare_$stamp.sql ($kb Ko)." -ForegroundColor Green
}

# ── Creer la base + importer le schema ───────────────────────
function Import-Database([string]$rootIni) {
    Write-Host '[4/9] Creation de la base `pharmacare` + import du schema...' -ForegroundColor Yellow

    # GARDE-FOU : ne jamais ecraser une base de production. database.sql
    # contient des "DROP TABLE IF EXISTS" pour TOUTES les tables : son import
    # sur une base existante detruit toutes les donnees. Si la base pharmacare
    # existe deja avec ses tables, on refuse — sauf reinstallation NEUVE
    # explicite (-Fresh), qui d'abord deverse un dump de secours.
    $rows = & $MYSQL "--defaults-extra-file=$rootIni" -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='pharmacare';" 2>$null
    $existing = 0
    if ($rows) { [void][int]::TryParse((@($rows) | Select-Object -First 1).ToString().Trim(), [ref]$existing) }
    if ($existing -gt 0) {
        if (-not $Fresh) {
            throw "La base pharmacare existe deja ($existing tables - donnees de production).`nL'import du schema effacerait TOUTES les donnees (DROP TABLE dans database.sql).`nUtilisez le mode « Mise a jour » de l'assistant (base et comptes conserves).`nPour repartir de zero, cochez « Reinstallation neuve » dans l'assistant (dump de secours automatique)."
        }
        Write-Host '  ATTENTION : reinstallation NEUVE confirmee — dump de secours avant effacement...' -ForegroundColor Yellow
        Backup-DatabaseFresh $rootIni
    }

    # Creer la base (via defaults-file root).
    $ok = Invoke-MysqlQuiet @("--defaults-extra-file=$rootIni", '-e', 'CREATE DATABASE IF NOT EXISTS pharmacare CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;')
    if (-not $ok) { throw 'Echec creation base pharmacare.' }

    # Importer le schema.
    # IMPORTANT : PAS de pipe PowerShell vers mysql.exe. En PS 5.1, le stdin
    # d'un exe natif est encode via $OutputEncoding (US-ASCII par defaut) ;
    # [Console]::OutputEncoding ne gouverne que le DECODAGE de la sortie.
    # Piped, chaque accent du SQL ('entrée', 'espèces', 'Gérer'…) devenait '?'
    # dans la base : ENUM ('entrée','sortie') casses -> mouvements de caisse a
    # '' -> solde fige au fond initial + sessions non cloturables (bug client
    # 2026-09-09). mysql.exe lit directement le FICHIER (UTF-8) via SOURCE :
    # le contenu traverse le client sans reencodage Windows.
    Write-Host "  Import de $SQL_FILE ..." -ForegroundColor Gray
    $prevEap = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'   # local : stderr natif -> pas de throw
    $errFile = [System.IO.Path]::GetTempFileName()
    $ok2 = $false
    try {
        # Slashes forward obligatoires : dans SOURCE, le \ est un escape SQL
        # ('C:\xampp' serait relu 'C:xampp'). mysql.exe les accepte sous Windows.
        $sqlPath = (Resolve-Path $SQL_FILE).Path -replace '\\', '/'
        & $MYSQL "--defaults-extra-file=$rootIni" --default-character-set=utf8mb4 pharmacare -e "SOURCE $sqlPath" 2>$errFile | Out-Null
        $ok2 = ($LASTEXITCODE -eq 0)
    } finally {
        $ErrorActionPreference = $prevEap
    }
    $errText = Get-Content $errFile -Raw -ErrorAction SilentlyContinue
    Remove-Item $errFile -Force -ErrorAction SilentlyContinue
    if (-not $ok2) { throw "Echec import schema SQL.`n$errText" }

    # Verifier le nombre de tables
    $tables = & $MYSQL "--defaults-extra-file=$rootIni" pharmacare -e 'SHOW TABLES;' 2>$null
    $count = ($tables | Measure-Object).Count - 1   # moins l'en-tete
    Write-Host "  OK : $count tables importees." -ForegroundColor Green
}

# ── Generer config/env.prod.php ───────────────────────────────
function New-EnvProdFile([string]$rootPass, [string]$hostname) {
    Write-Host '[5/9] Generation de config/env.prod.php...' -ForegroundColor Yellow

    # APP_URL pointe vers le nom d'hote (servi a la racine via VirtualHost) :
    # pas de /pharmacare. Le fallback http://<IP>/pharmacare reste disponible.
    $appUrl = "http://$hostname"

    $content = @"
<?php
return [
    'DB_HOST' => '127.0.0.1',
    'DB_NAME' => 'pharmacare',
    'DB_USER' => 'root',
    'DB_PASS' => '$rootPass',
    'APP_URL' => '$appUrl',
];
"@

    # UTF-8 SANS BOM : Set-Content -Encoding UTF8 (PS 5.1) ajoute un BOM qui
    # serait emis en sortie par PHP sur chaque requete (env.prod.php est
    # require par config/env.php) — risque de « headers already sent ».
    $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText($ENV_FILE, $content, $utf8NoBom)
    Write-Host "  OK : $ENV_FILE cree (APP_URL=$appUrl)" -ForegroundColor Green
}

# ── Changer les mots de passe des comptes demo ───────────────
function Set-DemoPasswords([string]$rootIni) {
    Write-Host '[6/9] Changement des mots de passe des comptes demo...' -ForegroundColor Yellow

    $accounts = @('admin', 'pharmacien', 'caissier')

    # Mode non-interactif : pre-valider les 3 mots de passe fournis (GUI).
    if ($NonInteractive) {
        $supplied = @{ admin = $AdminPass; pharmacien = $PharmacienPass; caissier = $CaissierPass }
        foreach ($login in $accounts) {
            $v = $supplied[$login]
            if ([string]::IsNullOrWhiteSpace($v)) { throw "Mode non-interactif : mot de passe manquant pour '$login'." }
            if ($v.Length -lt 8) { throw "Mot de passe '$login' trop court (minimum 8 caracteres)." }
        }
    }

    foreach ($login in $accounts) {
        Write-Host ''
        Write-Host "  Compte : $login" -ForegroundColor Cyan

        if ($NonInteractive) {
            $plain = $supplied[$login]
            Write-Host '    (mot de passe fourni via interface, masque)' -ForegroundColor Gray
        } else {
            do {
                $pass = Read-Host "    Nouveau mot de passe pour '$login' (8+ car.)" -AsSecureString
                $plain = [Runtime.InteropServices.Marshal]::PtrToStringAuto(
                            [Runtime.InteropServices.Marshal]::SecureStringToBSTR($pass))
                $confirm = Read-Host "    Confirmer" -AsSecureString
                $plainConfirm = [Runtime.InteropServices.Marshal]::PtrToStringAuto(
                            [Runtime.InteropServices.Marshal]::SecureStringToBSTR($confirm))
                if ($plain -ne $plainConfirm) {
                    Write-Host '    ERREUR : les mots de passe ne correspondent pas.' -ForegroundColor Red
                    continue
                }
                if ($plain.Length -lt 8) {
                    Write-Host '    ERREUR : minimum 8 caracteres.' -ForegroundColor Red
                    continue
                }
                break
            } while ($true)
        }

        # Generer le hash bcrypt via PHP. Le mot de passe est transmis sur STDIN
        # (jamais interpole dans le code PHP) -> robuste aux ', \, $, etc.
        $hash = $plain | & $PHP -r 'echo password_hash(trim(fgets(STDIN)), PASSWORD_DEFAULT);' 2>$null
        if (-not $hash) { throw "Echec generation hash pour $login" }

        # Mettre a jour en base : echapper les quotes pour le litteral SQL.
        $escaped = $hash -replace "'", "''"
        $ok = Invoke-MysqlQuiet @("--defaults-extra-file=$rootIni", 'pharmacare', '-e', "UPDATE utilisateurs SET mot_de_passe = '$escaped' WHERE login = '$login';")
        if (-not $ok) { throw "Echec mise a jour mot de passe $login en base." }

        Write-Host "    OK : mot de passe de '$login' change." -ForegroundColor Green
    }
}

# ── Migrations BDD idempotentes (apres creation du schema + env.prod.php) ────
# Les migrations (migrate_*.php) lisent les identifiants via config/env.php,
# qui requiert env.prod.php : on les execute donc apres l'etape 5/9. Idempotentes
# : verification information_schema avant chaque ALTER (cf. migrate_perf_indexes.php).
function Run-ProdMigrations {
    $migDir = Join-Path $APP_DIR 'tools\patch'
    $migs = @(Get-ChildItem -Path $migDir -Filter 'migrate_*.php' -File -ErrorAction SilentlyContinue)
    if ($migs.Count -eq 0) {
        Write-Host '  OK : aucune migration a appliquer.' -ForegroundColor Green
        return
    }
    Write-Host '[5.5/9] Migrations de base de donnees (idempotentes)...' -ForegroundColor Yellow
    foreach ($m in $migs) {
        Write-Host "  -> $($m.Name)" -ForegroundColor Cyan
        $ErrorActionPreference = 'Continue'
        & $PHP $m.FullName $APP_DIR
        $code = $LASTEXITCODE
        $ErrorActionPreference = 'Stop'
        if ($code -ne 0) { throw "Migration echouee : $($m.Name) (code $code)" }
        Write-Host '     OK' -ForegroundColor Green
    }
}

# ── Configurer Apache ─────────────────────────────────────────
function Set-ApacheConfig([string]$hostname) {
    Write-Host '[7/9] Configuration Apache (modules + VirtualHost racine)...' -ForegroundColor Yellow

    $httpdConf = "$APACHE\conf\httpd.conf"

    # Activer mod_rewrite + mod_headers si commentes
    $content = Get-Content $httpdConf -Raw

    $modified = $false
    if ($content -match '#\s*LoadModule rewrite_module') {
        $content = $content -replace '#\s*LoadModule rewrite_module', 'LoadModule rewrite_module'
        $modified = $true
    }
    if ($content -match '#\s*LoadModule headers_module') {
        $content = $content -replace '#\s*LoadModule headers_module', 'LoadModule headers_module'
        $modified = $true
    }

    if ($modified) {
        Set-Content -Path $httpdConf -Value $content -NoNewline
        Write-Host '  OK : modules mod_rewrite + mod_headers actives.' -ForegroundColor Green
    } else {
        Write-Host '  OK : modules deja actives.' -ForegroundColor Green
    }

    # AllowOverride All sur htdocs (fallback http://<IP>/pharmacare)
    $xamppConf = "$APACHE\conf\extra\httpd-xampp.conf"
    if (Test-Path $xamppConf) {
        $xc = Get-Content $xamppConf -Raw
        if ($xc -match 'AllowOverride None') {
            $xc = $xc -replace 'AllowOverride None', 'AllowOverride All'
            Set-Content -Path $xamppConf -Value $xc -NoNewline
            Write-Host '  OK : AllowOverride All active dans httpd-xampp.conf' -ForegroundColor Green
        } else {
            Write-Host '  OK : AllowOverride deja configure.' -ForegroundColor Green
        }
    }

    # S'assurer que httpd-vhosts.conf est inclus (decommenter la ligne Include)
    $hc = Get-Content $httpdConf -Raw
    if ($hc -match '(?m)^\s*#\s*(Include.*httpd-vhosts\.conf)') {
        $hc = $hc -replace '(?m)^\s*#\s*(Include.*httpd-vhosts\.conf)', '$1'
        Set-Content -Path $httpdConf -Value $hc -NoNewline
        Write-Host '  OK : inclusion httpd-vhosts.conf activee.' -ForegroundColor Green
    }

    # VirtualHosts : un VH par defaut (preserve localhost/dashboard + fallback
    # /pharmacare) + un VH nomme servant l'app a la racine de http://<hostname>.
    # Idempotent : on n'ajoute le bloc qu'une fois (marqueur).
    $vhostsFile = "$APACHE\conf\extra\httpd-vhosts.conf"
    if (-not (Test-Path $vhostsFile)) { New-Item -ItemType File -Path $vhostsFile -Force | Out-Null }
    $marker = '# >>> PharmaCare VirtualHosts (install_prod.ps1) >>>'
    $vcontent = Get-Content $vhostsFile -Raw -ErrorAction SilentlyContinue
    if ($vcontent -and ($vcontent -match [regex]::Escape($marker))) {
        Write-Host '  OK : VirtualHosts pharmacare deja presents.' -ForegroundColor Green
    } else {
        $computer = $env:COMPUTERNAME
        $block = @"

$marker
# VH par defaut : preserve localhost (dashboard XAMPP) + fallback /pharmacare
<VirtualHost *:80>
    DocumentRoot "C:/xampp/htdocs"
    ServerName localhost
    <Directory "C:/xampp/htdocs">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
# VH PharmaCare : sert l'app a la racine de http://$hostname
<VirtualHost *:80>
    DocumentRoot "C:/xampp/htdocs/pharmacare"
    ServerName $hostname
    ServerAlias $computer pharmacare.local pharmacare
    <Directory "C:/xampp/htdocs/pharmacare">
        AllowOverride All
        Require all granted
        DirectoryIndex index.php
    </Directory>
</VirtualHost>
# <<< PharmaCare VirtualHosts <<<
"@
        $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
        [System.IO.File]::AppendAllText($vhostsFile, $block, $utf8NoBom)
        Write-Host "  OK : VirtualHosts ajoutes -> http://$hostname (racine)." -ForegroundColor Green
    }

    # Entree hosts du serveur : le serveur resout lui-meme $hostname (auto-acces
    # + smoke test). Les clients, eux, resolvent via le routeur/DNS local.
    $hostsFile = "$env:SystemRoot\System32\drivers\etc\hosts"
    $hcontent = Get-Content $hostsFile -Raw -ErrorAction SilentlyContinue
    if ($hcontent -and ($hcontent -match [regex]::Escape($hostname))) {
        Write-Host "  OK : entree hosts '$hostname' deja presente." -ForegroundColor Green
    } else {
        $line = "127.0.0.1  $hostname"
        $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
        [System.IO.File]::AppendAllText($hostsFile, "`r`n$line", $utf8NoBom)
        Write-Host "  OK : entree hosts ajoutee (127.0.0.1  $hostname)." -ForegroundColor Green
    }

    # Redemarrer Apache — httpd -k restart n'agit que si Apache tourne en
    # service/daemon XAMPP ; en cas d'echec on tente un demarrage simple puis
    # on verifie reellement que le port 80 repond (sinon avertissement clair :
    # la GUI annonce « succes » des que le script sort a 0).
    Write-Host '  Redemarrage d''Apache...' -ForegroundColor Gray
    $ErrorActionPreference = 'Continue'
    & "$APACHE\bin\httpd.exe" -k restart 2>$null
    if ($LASTEXITCODE -ne 0) {
        & "$APACHE\bin\httpd.exe" -k start 2>$null
    }
    Start-Sleep -Seconds 2
    $httpdUp = $false
    try {
        $r = Invoke-WebRequest -Uri 'http://127.0.0.1/' -UseBasicParsing -TimeoutSec 8 -ErrorAction SilentlyContinue
        $httpdUp = ($null -ne $r)
    } catch { $httpdUp = $false }
    $ErrorActionPreference = 'Stop'
    if ($httpdUp) {
        Write-Host '  OK : Apache redemarre et repond.' -ForegroundColor Green
    } else {
        Write-Host '  ATTENTION : Apache ne repond pas sur le port 80.' -ForegroundColor Red
        Write-Host '  Verifiez le panneau de controle XAMPP (erreurs Apache possibles).' -ForegroundColor Yellow
    }
}

# ── Pare-feu Windows ──────────────────────────────────────────
# ── Services Windows en démarrage automatique ────────────────
# Après une coupure de courant + reboot, Apache et MySQL doivent se relancer
# seuls : c'est le pilier serveur du comportement offline-first (les postes
# caisse basculent en file d'attente puis resynchronisent dès le retour du
# serveur). Idempotent : installe le service s'il manque, force Automatique.
function Set-AutoStartServices {
    Write-Host '[+] Services Windows : demarrage automatique (reprise apres coupure de courant)...' -ForegroundColor Yellow

    # MySQL — service 'mysql' (XAMPP)
    $svc = Get-Service -Name 'mysql' -ErrorAction SilentlyContinue
    if (-not $svc) {
        $myIni = "$XAMPP_DIR\mysql\bin\my.ini"
        & "$XAMPP_DIR\mysql\bin\mysqld.exe" --install mysql "--defaults-file=$myIni" 2>$null
        Start-Sleep -Seconds 2
        $svc = Get-Service -Name 'mysql' -ErrorAction SilentlyContinue
        if ($svc) { Write-Host '  OK : service MySQL installe.' -ForegroundColor Green }
    }
    if ($svc) {
        try {
            Set-Service -Name 'mysql' -StartupType Automatic -ErrorAction Stop
            Write-Host '  OK : MySQL → demarrage automatique.' -ForegroundColor Green
        } catch {
            Write-Host "  ATTENTION : impossible de mettre mysql en automatique : $($_.Exception.Message)" -ForegroundColor Yellow
        }
        if ($svc.Status -ne 'Running') { Start-Service -Name mysql -ErrorAction SilentlyContinue }
    } else {
        Write-Host '  ATTENTION : service MySQL absent — demarrez MySQL (panneau XAMPP) puis relancez l''installateur.' -ForegroundColor Yellow
    }

    # Apache — service 'Apache2.4' (httpd -k install)
    $asvc = Get-Service -Name 'Apache2.4' -ErrorAction SilentlyContinue
    if (-not $asvc) {
        & "$APACHE\bin\httpd.exe" -k install 2>$null
        Start-Sleep -Seconds 2
        $asvc = Get-Service -Name 'Apache2.4' -ErrorAction SilentlyContinue
        if ($asvc) { Write-Host '  OK : service Apache2.4 installe.' -ForegroundColor Green }
    }
    if ($asvc) {
        try {
            Set-Service -Name 'Apache2.4' -StartupType Automatic -ErrorAction Stop
            Write-Host '  OK : Apache2.4 → demarrage automatique.' -ForegroundColor Green
        } catch {
            Write-Host "  ATTENTION : impossible de mettre Apache2.4 en automatique : $($_.Exception.Message)" -ForegroundColor Yellow
        }
        if ($asvc.Status -ne 'Running') { Start-Service -Name 'Apache2.4' -ErrorAction SilentlyContinue }
    } else {
        Write-Host '  ATTENTION : service Apache2.4 non cree — verifiez httpd.exe -k install (droits admin).' -ForegroundColor Yellow
    }
}

function Set-Firewall {
    Write-Host '[8/9] Configuration du pare-feu Windows (port 80 LAN)...' -ForegroundColor Yellow

    $ruleName = 'Apache HTTP (LAN)'
    $existing = Get-NetFirewallRule -DisplayName $ruleName -ErrorAction SilentlyContinue
    if ($existing) {
        Write-Host '  OK : regle pare-feu deja presente.' -ForegroundColor Green
    } else {
        # -Profile Any : couvre Prive ET Public — un LAN classé Public
        # (fréquent) sinon resterait bloqué malgré la règle.
        New-NetFirewallRule -DisplayName $ruleName -Direction Inbound -Protocol TCP -LocalPort 80 -Action Allow -Profile Any | Out-Null
        Write-Host '  OK : regle pare-feu creee (port 80, tous profils).' -ForegroundColor Green
    }
}

# ── Resume + smoke test ───────────────────────────────────────
function Show-Summary([string]$lanIp, [string]$hostname) {
    Write-Host '[9/9] Resume et smoke test...' -ForegroundColor Yellow

    $appUrl = "http://$hostname"

    # Smoke test HTTP (equivalent install_prod.sh) : l'app doit repondre et
    # config/ doit etre bloque. Teste depuis le serveur lui-meme (entree hosts
    # ajoutee plus tot) ; un echec ici signale une config Apache a revoir.
    $ErrorActionPreference = 'Continue'
    $homeCode = 0
    try {
        $r = Invoke-WebRequest -Uri $appUrl -UseBasicParsing -TimeoutSec 10
        $homeCode = [int]$r.StatusCode
    } catch {
        if ($_.Exception.Response) { $homeCode = [int]$_.Exception.Response.StatusCode }
    }
    $envCode = 0
    try {
        Invoke-WebRequest -Uri "$appUrl/config/env.php" -UseBasicParsing -TimeoutSec 10 | Out-Null
    } catch {
        if ($_.Exception.Response) { $envCode = [int]$_.Exception.Response.StatusCode }
    }
    $ErrorActionPreference = 'Stop'

    Write-Host ''
    Write-Host '  Smoke test :'
    if ($homeCode -ge 200 -and $homeCode -le 399) {
        Write-Host "   OK     $appUrl -> HTTP $homeCode" -ForegroundColor Green
    } else {
        Write-Host "   ECHEC  $appUrl -> HTTP $homeCode (200/302 attendu)" -ForegroundColor Red
        Write-Host '          Verifiez Apache (panneau XAMPP) et la config du vhost.' -ForegroundColor Yellow
    }
    if ($envCode -eq 403) {
        Write-Host "   OK     $appUrl/config/env.php -> 403 (protege)" -ForegroundColor Green
    } else {
        Write-Host "   ECHEC  $appUrl/config/env.php -> HTTP $envCode (403 attendu)" -ForegroundColor Red
        Write-Host '          Verifiez le .htaccess et AllowOverride dans httpd.conf.' -ForegroundColor Yellow
    }

    Write-Host ''
    Write-Host '  ============================================' -ForegroundColor Green
    Write-Host '   INSTALLATION TERMINEE' -ForegroundColor Green
    Write-Host '  ============================================' -ForegroundColor Green
    Write-Host ''
    Write-Host "  URL d'acces (recommandee) : $appUrl" -ForegroundColor White
    Write-Host "  Fallback (avant DNS)       : http://$lanIp/pharmacare" -ForegroundColor Gray
    Write-Host ''
    Write-Host "  >>> ACTION REQUISE sur votre routeur / DNS local <<<" -ForegroundColor Cyan
    Write-Host "   Creer l'hote :  $hostname  ->  $lanIp" -ForegroundColor White
    Write-Host "   (une seule config ; tous les postes clients en profitent)" -ForegroundColor Gray
    Write-Host "   Recommande : IP fixe ou reservation DHCP pour ce serveur ($lanIp)." -ForegroundColor White
    Write-Host "   Le serveur resout deja $hostname (entree hosts locale ajoutee)." -ForegroundColor Gray
    Write-Host ''
    Write-Host '  Prochaines etapes :' -ForegroundColor Cyan
    Write-Host "   1. Configurez le DNS local ($hostname -> $lanIp) sur le routeur" -ForegroundColor White
    Write-Host "   2. Ouvrez $appUrl dans un navigateur (depuis un poste client)" -ForegroundColor White
    Write-Host '   3. Connectez-vous avec admin / <votre mot de passe>' -ForegroundColor White
    Write-Host '   4. Verifiez que le bloc "Comptes de demonstration" a disparu' -ForegroundColor White
    Write-Host '   5. Testez une vente, une caisse, un transfert de stock' -ForegroundColor White
    Write-Host ''
    Write-Host '  Verification securite (depuis un client) :' -ForegroundColor Cyan
    Write-Host "   - $appUrl/config/env.php      -> doit donner 403" -ForegroundColor White
    Write-Host "   - $appUrl/_archive/database.sql -> doit donner 403" -ForegroundColor White
    Write-Host "   - $appUrl/_archive/alter_db.php -> doit donner 403" -ForegroundColor White
    Write-Host ''
    Write-Host '  Pour sauvegarde automatique, creez une tache planifiee :' -ForegroundColor Cyan
    Write-Host "    C:\xampp\mysql\bin\mysqldump.exe -u root -p<MOTDEPASSE> pharmacare > backup.sql" -ForegroundColor White
    Write-Host ''
}

# ── Programme principal ───────────────────────────────────────
Write-Banner

try {
    Test-Prerequisites
    $lan      = Get-LanIp
    Set-PerformanceTuning
    $rootPass = Set-MySQLPassword
    # Fichier d'identifiants root reutilise pour les appels mysql suivants
    # (import + mots de passe demo). On l'efface en finally (contient le mdp root).
    $rootIni = New-MysqlDefaultsFile 'root' $rootPass
    try {
        Import-Database $rootIni
        Set-DemoPasswords $rootIni
    } finally {
        Remove-Item $rootIni -Force -ErrorAction SilentlyContinue
    }
    New-EnvProdFile $rootPass $lan.Hostname
    Run-ProdMigrations
    Set-ApacheConfig $lan.Hostname
    Set-AutoStartServices
    Set-Firewall
    Show-Summary $lan.Ip $lan.Hostname
}
catch {
    Write-Host ''
    Write-Host "  ERREUR : $($_.Exception.Message)" -ForegroundColor Red
    Write-Host ''
    Write-Host '  Conseils :' -ForegroundColor Yellow
    Write-Host '   - Verifiez que XAMPP est installe dans C:\xampp' -ForegroundColor White
    Write-Host '   - Verifiez que MySQL et Apache sont demarres (panneau XAMPP)' -ForegroundColor White
    Write-Host '   - Executez ce script en tant qu''administrateur' -ForegroundColor White
    Write-Host '   - Executez ce script depuis le dossier pharmacare/' -ForegroundColor White
    Write-Host ''
    exit 1
}