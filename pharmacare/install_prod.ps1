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
            $content = $content -replace "^\$cfg\['Servers'\]\[\$i\]\['password'\] = '';", "`$cfg['Servers'][`$i]['password'] = '$plain';"
            Set-Content -Path $pmaConfig -Value $content -NoNewline
            Write-Host '  OK : phpMyAdmin mis a jour avec le nouveau mot de passe.' -ForegroundColor Green
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
        $content = $content -replace "^\$cfg\['Servers'\]\[\$i\]\['password'\] = '';", "`$cfg['Servers'][`$i]['password'] = '$plain';"
        Set-Content -Path $pmaConfig -Value $content -NoNewline
        Write-Host '  OK : phpMyAdmin mis a jour avec le nouveau mot de passe.' -ForegroundColor Green
    }

    return $plain
}

# ── Creer la base + importer le schema ───────────────────────
function Import-Database([string]$rootIni) {
    Write-Host '[4/9] Creation de la base `pharmacare` + import du schema...' -ForegroundColor Yellow

    # Creer la base (via defaults-file root).
    $ok = Invoke-MysqlQuiet @("--defaults-extra-file=$rootIni", '-e', 'CREATE DATABASE IF NOT EXISTS pharmacare CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;')
    if (-not $ok) { throw 'Echec creation base pharmacare.' }

    # Importer le schema.
    # IMPORTANT : on force l'encodage UTF-8 sur les deux bouts du tuyau.
    #   - Get-Content -Encoding UTF8 : lit le fichier (BOM UTF-8) en UTF-8 -> string .NET
    #   - [Console]::OutputEncoding = UTF8 : le pipe vers mysql.exe encode en UTF-8
    #   - --default-character-set=utf8mb4 : mysql interpretant le stdin comme utf8mb4
    #   Sans cela, les accents des seed (roles « Pharmacien », permissions, menus
    #   « Medicaments », « Roles ») sont corrompus (bug constate en dev).
    Write-Host "  Import de $SQL_FILE ..." -ForegroundColor Gray
    $prevEncoding = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'   # local : stderr natif -> pas de throw
    $errFile = [System.IO.Path]::GetTempFileName()
    $ok2 = $false
    $errText = ''
    try {
        $prevOut = [Console]::OutputEncoding
        [Console]::OutputEncoding = [System.Text.Encoding]::UTF8
        Get-Content $SQL_FILE -Raw -Encoding UTF8 | & $MYSQL "--defaults-extra-file=$rootIni" --default-character-set=utf8mb4 pharmacare 2>$errFile
        $ok2 = ($LASTEXITCODE -eq 0)
        $errText = Get-Content $errFile -Raw -ErrorAction SilentlyContinue
        [Console]::OutputEncoding = $prevOut
    } finally {
        $ErrorActionPreference = $prevEncoding
        Remove-Item $errFile -Force -ErrorAction SilentlyContinue
    }
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

    Set-Content -Path $ENV_FILE -Value $content -NoNewline -Encoding UTF8
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

    # Redemarrer Apache
    Write-Host '  Redemarrage d''Apache...' -ForegroundColor Gray
    & "$APACHE\bin\httpd.exe" -k restart 2>$null
    Start-Sleep -Seconds 2
    Write-Host '  OK : Apache redemarre.' -ForegroundColor Green
}

# ── Pare-feu Windows ──────────────────────────────────────────
function Set-Firewall {
    Write-Host '[8/9] Configuration du pare-feu Windows (port 80 LAN)...' -ForegroundColor Yellow

    $ruleName = 'Apache HTTP (LAN)'
    $existing = Get-NetFirewallRule -DisplayName $ruleName -ErrorAction SilentlyContinue
    if ($existing) {
        Write-Host '  OK : regle pare-feu deja presente.' -ForegroundColor Green
    } else {
        New-NetFirewallRule -DisplayName $ruleName -Direction Inbound -Protocol TCP -LocalPort 80 -Action Allow -Profile Private | Out-Null
        Write-Host '  OK : regle pare-feu creee (port 80, profil Prive).' -ForegroundColor Green
    }
}

# ── Resume + smoke test ───────────────────────────────────────
function Show-Summary([string]$lanIp, [string]$hostname) {
    Write-Host '[9/9] Resume et smoke test...' -ForegroundColor Yellow

    $appUrl = "http://$hostname"

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
    Set-ApacheConfig $lan.Hostname
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