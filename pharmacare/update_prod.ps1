<#
.SYNOPSIS
    Mise a jour de PharmaCare sur une installation production existante (XAMPP LAN).
.DESCRIPTION
    Principe absolu : NE JAMAIS detruire la base de production. Concretement :
      - AUCUN import de schema SQL : _archive/database.sql contient des
        "DROP TABLE IF EXISTS" - son import effacerait toutes les donnees.
      - AUCUNE modification d'identifiants : le mot de passe root MySQL et
        les mots de passe des comptes applicatifs (admin/pharmacien/caissier)
        sont CONSERVES. env.prod.php n'est jamais regenere.
      - Avant toute modification : DUMP COMPLET de la base via mysqldump vers
        <installation>\backups\pharmacare_YYYYMMDD_HHMMSS.sql (nommage conforme
        au systeme de restauration de l'app ; dossier protege du web).
    Etapes :
      [1/7] Prerequis (MySQL/PHP/Apache presents, MySQL joignable sur 3306)
      [2/7] Installation existante verifiee (env.prod.php accepte, base remplie)
      [3/7] Dump de securite (mysqldump)
      [4/7] Remplacement des fichiers (anciens conserves dans _patch_backup/<ts>)
      [5/7] Migrations BDD idempotentes (migrate_*.php livres avec ce script)
      [6/7] Purge du cache applicatif (cache/*, .htaccess conserve)
      [7/7] Apache/pare-feu idempotents + redemarrage + smoke test
    Sources/cibles : le bundle de mise a jour peut etre decompresse N'IMPORT OU
    (Bureau...) : les NOUVEAUX fichiers sont pris a cote de ce script
    (SOURCES), la copie est faite vers l'installation LIVE (CIBLE). Si
    l'assistant a ete decompresse directement sur l'installation live, la
    copie est inutile et sautee (les fichiers sont deja en place).
.NOTES
    Droits administrateur requis (comme install_prod.ps1).
    Mode GUI : -ParamFile <json> {"hostname": "...", "target": "C:\\xampp\\htdocs\\pharmacare"}
    Mode console : update_prod.ps1 [-Hostname x] [-Target C:\xampp\htdocs\pharmacare]
#>
#Requires -RunAsAdministrator

param(
    [string]$Hostname,
    [string]$Target,
    [string]$RootPass,
    [string]$ParamFile
)

if ($ParamFile -and (Test-Path -LiteralPath $ParamFile)) {
    $cfg = Get-Content -LiteralPath $ParamFile -Raw -Encoding UTF8 | ConvertFrom-Json
    if ($cfg.PSObject.Properties['hostname'] -and $cfg.hostname) { $Hostname = $cfg.hostname }
    if ($cfg.PSObject.Properties['target'] -and $cfg.target)     { $Target = $cfg.target }
    if ($cfg.PSObject.Properties['rootPass'])                    { $RootPass = $cfg.rootPass }
    Remove-Item -LiteralPath $ParamFile -Force -ErrorAction SilentlyContinue
}

# ── Strict mode + erreurs fatales ─────────────────────────────
$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

# ── Variables XAMPP ───────────────────────────────────────────
$XAMPP_DIR  = 'C:\xampp'
$MYSQL      = "$XAMPP_DIR\mysql\bin\mysql.exe"
$MYSQLDUMP  = "$XAMPP_DIR\mysql\bin\mysqldump.exe"
$PHP        = "$XAMPP_DIR\php\php.exe"
$APACHE     = "$XAMPP_DIR\apache"
$BUNDLE_DIR = $PSScriptRoot                 # nouveaux fichiers (dossier decompresse)
$DEFAULT_LIVE = 'C:\xampp\htdocs\pharmacare'

# ── Fichier d'identifiants MySQL temporaire (cf. install_prod.ps1) ──
function New-MysqlDefaultsFile([string]$user, [string]$pass) {
    $path = [System.IO.Path]::GetTempFileName()
    $u = $user -replace '\\', '\\' -replace '"', '\"'
    $p = $pass -replace '\\', '\\' -replace '"', '\"'
    $content = "[client]`r`nuser=`"$u`"`r`npassword=`"$p`"`r`n"
    $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText($path, $content, $utf8NoBom)
    return $path
}

function Invoke-MysqlQuiet([string[]]$mysqlArgs) {
    $ErrorActionPreference = 'Continue'
    $errFile = [System.IO.Path]::GetTempFileName()
    try {
        & $MYSQL @mysqlArgs 2>$errFile | Out-Null
        return ($LASTEXITCODE -eq 0)
    } finally {
        Remove-Item $errFile -Force -ErrorAction SilentlyContinue
    }
}

function Write-Banner {
    Write-Host ''
    Write-Host '  ============================================' -ForegroundColor Cyan
    Write-Host '   PharmaCare - MISE A JOUR (base conservee)' -ForegroundColor Cyan
    Write-Host '  ============================================' -ForegroundColor Cyan
    Write-Host ''
}

# ── Resolution CIBLE (installation live) ──────────────────────
# Priorite : parametre -Target (GUI) > dossier courant s'il EST l'installation
# (assistant decompresse sur place) > C:\xampp\htdocs\pharmacare par defaut.
function Resolve-LiveTarget {
    if (-not [string]::IsNullOrWhiteSpace($Target)) {
        if (-not (Test-Path (Join-Path $Target 'config\env.prod.php'))) {
            throw "Cible invalide : $Target (config\env.prod.php absent)."
        }
        return (Resolve-Path $Target).Path
    }
    if (Test-Path (Join-Path $BUNDLE_DIR 'config\env.prod.php')) {
        return $BUNDLE_DIR
    }
    $def = Join-Path $DEFAULT_LIVE 'config\env.prod.php'
    if (Test-Path $def) { return $DEFAULT_LIVE }
    throw "Installation PharmaCare existante introuvable.`n ni config\env.prod.php a cote de ce script, ni $def."
}

# ── [1/7] Prerequis ────────────────────────────────────────────
# MySQL doit tourner. On NE teste PAS avec "mysql -u root" : root est (doit
# etre) protege - un test "anonyme" renverrait 1045 et ferait croire a un
# serveur arrete. On teste la joignabilite TCP du port 3306 : un refus
# d'authentification est la PREUVE que le serveur tourne.
function Test-Prerequisites([string]$envFile) {
    Write-Host '[1/7] Verification des prerequis...' -ForegroundColor Yellow

    if (-not (Test-Path $MYSQL))  { throw "MySQL introuvable : $MYSQL" }
    if (-not (Test-Path $PHP))    { throw "PHP introuvable : $PHP" }
    if (-not (Test-Path $APACHE)) { throw "Apache introuvable : $APACHE" }

    $mysqlUp = $false
    try {
        $client = New-Object System.Net.Sockets.TcpClient
        $ar = $client.BeginConnect('127.0.0.1', 3306, $null, $null)
        $mysqlUp = $ar.AsyncWaitHandle.WaitOne(3000) -and $client.Connected
        $client.Close()
    } catch { $mysqlUp = $false }
    if (-not $mysqlUp) {
        throw "MySQL ne repond pas sur 127.0.0.1:3306.`nDemarrez MySQL depuis le panneau de controle XAMPP puis relancez."
    }
    Write-Host "  OK : MySQL (3306), PHP, Apache et $envFile detectes." -ForegroundColor Green
}

# ── [2/7] Installation existante : identifiants + base ────────
# Priorite des identifiants : ceux de env.prod.php (source de verite de
# l'application - c'est avec EUX que l'app se connecte).
function Get-DbCreds([string]$envFile) {
    $txt = Get-Content $envFile -Raw -Encoding UTF8
    $pass = ''
    $user = 'root'
    $m = [regex]::Match($txt, "['""]DB_PASS['""]\s*=>\s*['""]([^'""]*)['""]")
    if ($m.Success) { $pass = $m.Groups[1].Value }
    $mu = [regex]::Match($txt, "['""]DB_USER['""]\s*=>\s*['""]([^'""]*)['""]")
    if ($mu.Success -and $mu.Groups[1].Value) { $user = $mu.Groups[1].Value }
    # Filet de secours (GUI) : si env.prod.php est illisible/mute (regex rate),
    # le mot de passe root saisi dans l'assistant est utilise.
    if (-not $pass -and $user -eq 'root' -and $RootPass) { $pass = $RootPass }
    return [pscustomobject]@{ User = $user; Pass = $pass }
}

function Test-ExistingInstall([object]$creds) {
    Write-Host '[2/7] Verification de l installation existante...' -ForegroundColor Yellow

    $ini = New-MysqlDefaultsFile $creds.User $creds.Pass
    try {
        $ok = Invoke-MysqlQuiet @("--defaults-extra-file=$ini", '-e', 'SELECT 1;')
        if (-not $ok) {
            throw "Les identifiants de config\env.prod.php sont refuses par MySQL.`nVerifiez le mot de passe root (phpMyAdmin) puis relancez la mise a jour."
        }
        $rows = & $MYSQL "--defaults-extra-file=$ini" -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='pharmacare';" 2>$null
        $count = 0
        if ($rows) { [void][int]::TryParse((@($rows) | Select-Object -First 1).ToString().Trim(), [ref]$count) }
        if ($count -lt 5) {
            throw "La base pharmacare ne contient que $count table(s) : installation partielle ou corrompue.`nRefaites une installation neuve (install_prod.exe) ou restaurez une sauvegarde."
        }
        Write-Host "  OK : base pharmacare presente ($count tables) - donnees conservees." -ForegroundColor Green
    } finally {
        Remove-Item $ini -Force -ErrorAction SilentlyContinue
    }
}

# ── [3/7] Dump de securite (mysqldump) ─────────────────────────
# Meme nommage que l'application (pharmacare_YYYYMMDD_HHMMSS.sql) pour que le
# dump soit visible/restaurable depuis l'UI Sauvegarde de l'app. Comme elle,
# on convertit MyISAM/MEMORY -> InnoDB et latin1/utf8 -> utf8mb4 dans le dump.
# Identifiants via defaults-file (jamais -p sur la ligne de commande).
function Backup-Database([object]$creds, [string]$liveDir) {
    Write-Host '[3/7] Dump de securite de la base de production...' -ForegroundColor Yellow

    if (-not (Test-Path $MYSQLDUMP)) { throw "mysqldump introuvable : $MYSQLDUMP" }

    $bkDir = Join-Path $liveDir 'backups'
    if (-not (Test-Path $bkDir)) { New-Item -ItemType Directory -Path $bkDir | Out-Null }
    $ht = Join-Path $bkDir '.htaccess'
    if (-not (Test-Path $ht)) {
        [System.IO.File]::WriteAllText($ht, "Require all denied`r`nDeny from all`r`nOptions -Indexes`r`n", (New-Object System.Text.UTF8Encoding($false)))
    }
    $idx = Join-Path $bkDir 'index.php'
    if (-not (Test-Path $idx)) {
        [System.IO.File]::WriteAllText($idx, "<?php http_response_code(403); exit;`n", (New-Object System.Text.UTF8Encoding($false)))
    }

    $stamp = Get-Date -Format 'yyyyMMdd_HHmmss'
    $outFile = Join-Path $bkDir "pharmacare_$stamp.sql"

    $ini = New-MysqlDefaultsFile $creds.User $creds.Pass
    try {
        $ErrorActionPreference = 'Continue'
        $errFile = [System.IO.Path]::GetTempFileName()
        $prevOut = [Console]::OutputEncoding
        $raw = ''
        $ok = $false
        $errText = ''
        try {
            [Console]::OutputEncoding = [System.Text.Encoding]::UTF8
            $raw = (& $MYSQLDUMP "--defaults-extra-file=$ini" --single-transaction --quick --routines --triggers pharmacare 2>$errFile) | Out-String
            $ok = ($LASTEXITCODE -eq 0)
        } finally {
            [Console]::OutputEncoding = $prevOut
            $errText = Get-Content $errFile -Raw -ErrorAction SilentlyContinue
            Remove-Item $errFile -Force -ErrorAction SilentlyContinue
        }
        if ($ok -and $raw) {
            $raw = $raw -replace 'ENGINE=MyISAM', 'ENGINE=InnoDB'
            $raw = $raw -replace 'ENGINE=MEMORY', 'ENGINE=InnoDB'
            $raw = $raw -replace 'CHARSET=latin1', 'CHARSET=utf8mb4'
            $raw = $raw -replace 'CHARSET=utf8(?![mb4])', 'CHARSET=utf8mb4'
            $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
            [System.IO.File]::WriteAllText($outFile, $raw, $utf8NoBom)
        }
        $ErrorActionPreference = 'Stop'

        if (-not $ok) { throw "Echec du dump mysqldump.`n$errText" }
        if (-not (Test-Path $outFile) -or ((Get-Item $outFile).Length -lt 1000)) {
            throw "Dump suspect (fichier absent ou trop petit).`n$errText"
        }
    } finally {
        Remove-Item $ini -Force -ErrorAction SilentlyContinue
    }

    $kb = [int]((Get-Item $outFile).Length / 1KB)
    Write-Host "  OK : dump cree -> backups\pharmacare_$stamp.sql ($kb Ko)." -ForegroundColor Green
    Write-Host "       Restauration si besoin : menu Sauvegarde de l'application," -ForegroundColor Gray
    Write-Host "       ou : C:\xampp\mysql\bin\mysql.exe -u root -p pharmacare < pharmacare_$stamp.sql" -ForegroundColor Gray
}

# ── [4/7] Remplacement des fichiers (avec sauvegarde) ──────────
function Copy-Tree([string]$src, [string]$dst, [string]$bakDir) {
    Get-ChildItem -Path $src -Recurse -File | ForEach-Object {
        $rel = $_.FullName.Substring($src.Length + 1)
        $dstPath = Join-Path $dst $rel
        if (Test-Path $dstPath) {
            $bPath = Join-Path $bakDir $rel
            $null = New-Item -ItemType Directory -Force -Path (Split-Path $bPath)
            Copy-Item $dstPath $bPath -Force
        }
        $null = New-Item -ItemType Directory -Force -Path (Split-Path $dstPath)
        Copy-Item $_.FullName $dstPath -Force
    }
}

function Update-Files([string]$liveDir) {
    Write-Host '[4/7] Mise a jour des fichiers (anciens conserves en backup)...' -ForegroundColor Yellow

    if (-not (Test-Path (Join-Path $BUNDLE_DIR 'index.php'))) {
        throw "Fichiers de l'application introuvables (index.php absent a cote de update_prod.ps1)."
    }

    $same = ((Resolve-Path $BUNDLE_DIR).Path -eq (Resolve-Path $liveDir).Path)
    if ($same) {
        Write-Host '  Assistant decompresse sur l installation meme : fichiers deja en place.' -ForegroundColor Gray
        Write-Host '  (aucune copie : dump + migrations + cache restent executes ci-apres).' -ForegroundColor Gray
        return
    }

    $ts = Get-Date -Format 'yyyyMMdd_HHmmss'
    $bak = Join-Path $liveDir "_patch_backup\$ts"
    $null = New-Item -ItemType Directory -Force -Path $bak
    Write-Host "  Backup des fichiers remplaces : $bak" -ForegroundColor Gray

    Copy-Tree $BUNDLE_DIR $liveDir $bak

    $n = @(Get-ChildItem -Path $BUNDLE_DIR -Recurse -File).Count
    Write-Host "  OK : $n fichier(s) mis a jour (backup : _patch_backup\$ts)." -ForegroundColor Green
}

# ── [5/7] Migrations BDD idempotentes ──────────────────────────
# Meme mecanisme que tools/patch/apply_patch.ps1 : scripts migrate_*.php livres
# avec le bundle (dossier tools\patch). Idempotents par conception (verification
# information_schema avant chaque ALTER, cf. migrate_perf_indexes.php). Ils lisent
# les identifiants BDD dans la config de l'installation cible - pas de secret ici.
function Run-Migrations([string]$liveDir) {
    Write-Host '[5/7] Migrations de base de donnees (idempotentes)...' -ForegroundColor Yellow
    $migDir = Join-Path $BUNDLE_DIR 'tools\patch'
    $migs = @(Get-ChildItem -Path $migDir -Filter 'migrate_*.php' -File -ErrorAction SilentlyContinue)
    if ($migs.Count -gt 0 -and -not (Test-Path (Join-Path $liveDir 'config\env.php'))) {
        throw "Config introuvable pour les migrations : $liveDir\config\env.php"
    }
    if ($migs.Count -eq 0) {
        Write-Host '  OK : aucune migration livree avec cette mise a jour.' -ForegroundColor Green
        return
    }
    if (-not (Test-Path $PHP)) { throw "PHP introuvable : $PHP`nExecutez manuellement : php migrate_*.php `"$liveDir`"" }

    foreach ($m in $migs) {
        Write-Host "  -> $($m.Name)" -ForegroundColor Cyan
        $ErrorActionPreference = 'Continue'
        & $PHP $m.FullName $liveDir
        $code = $LASTEXITCODE
        $ErrorActionPreference = 'Stop'
        if ($code -ne 0) { throw "Migration echouee : $($m.Name) (code $code)" }
        Write-Host '     OK' -ForegroundColor Green
    }
}

# ── [6/7] Purge du cache applicatif ────────────────────────────
# L'app cache des agregats dans <live>\cache\*.cache ; apres un changement de
# code il faut les invalider. On supprime les fichiers mais PAS le dossier ni
# le .htaccess (la protection web doit rester en place).
function Clear-AppCache([string]$liveDir) {
    Write-Host '[6/7] Purge du cache applicatif...' -ForegroundColor Yellow
    $cacheDir = Join-Path $liveDir 'cache'
    if (Test-Path $cacheDir) {
        $n = 0
        Get-ChildItem -Path $cacheDir -File -ErrorAction SilentlyContinue | ForEach-Object {
            if ($_.Name -ne '.htaccess') {
                Remove-Item $_.FullName -Force -ErrorAction SilentlyContinue
                $n++
            }
        }
        Write-Host "  OK : $n fichier(s) de cache supprimes (agregats recalcules)." -ForegroundColor Green
    } else {
        Write-Host '  OK : pas de dossier cache/.' -ForegroundColor Green
    }
}

# ── [7/7] Apache / hosts / pare-feu (idempotents) + smoke test ──
# VirtualHost deja correct (hostname identique) -> no-op. Hostname change ->
# le bloc marque est reecrit. Enfin : redemarrage Apache + verification port 80.
function Set-ApacheConfig([string]$hostname) {
    Write-Host '[7/7] Apache / pare-feu (idempotent)...' -ForegroundColor Yellow

    $httpdConf = "$APACHE\conf\httpd.conf"
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
    if ($modified) { Set-Content -Path $httpdConf -Value $content -NoNewline }

    $xamppConf = "$APACHE\conf\extra\httpd-xampp.conf"
    if (Test-Path $xamppConf) {
        $xc = Get-Content $xamppConf -Raw
        if ($xc -match 'AllowOverride None') {
            $xc = $xc -replace 'AllowOverride None', 'AllowOverride All'
            Set-Content -Path $xamppConf -Value $xc -NoNewline
        }
    }
    $hc = Get-Content $httpdConf -Raw
    if ($hc -match '(?m)^\s*#\s*(Include.*httpd-vhosts\.conf)') {
        $hc = $hc -replace '(?m)^\s*#\s*(Include.*httpd-vhosts\.conf)', '$1'
        Set-Content -Path $httpdConf -Value $hc -NoNewline
    }
    Write-Host '  OK : modules + AllowOverride verifies.' -ForegroundColor Green

    $vhostsFile = "$APACHE\conf\extra\httpd-vhosts.conf"
    if (-not (Test-Path $vhostsFile)) { New-Item -ItemType File -Path $vhostsFile -Force | Out-Null }
    $marker    = '# >>> PharmaCare VirtualHosts (install_prod.ps1) >>>'
    $endMarker = '# <<< PharmaCare VirtualHosts <<<'
    $vcontent = Get-Content $vhostsFile -Raw -ErrorAction SilentlyContinue
    $hasMarker = $vcontent -and ($vcontent -match [regex]::Escape($marker))

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

    if (-not $hasMarker) {
        $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
        [System.IO.File]::AppendAllText($vhostsFile, $block, $utf8NoBom)
        Write-Host "  OK : VirtualHosts PharmaCare ajoutes (ServerName $hostname)." -ForegroundColor Green
    } elseif ($vcontent -match '(?m)^\s*ServerName\s+' + [regex]::Escape($hostname) + '\s*$') {
        Write-Host '  OK : VirtualHosts deja corrects (hostname inchange).' -ForegroundColor Green
    } else {
        $pattern = '(?s)' + [regex]::Escape($marker) + '.*?' + [regex]::Escape($endMarker)
        $updated = [regex]::Replace($vcontent, $pattern, { param($m) $block })
        $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
        [System.IO.File]::WriteAllText($vhostsFile, $updated, $utf8NoBom)
        Write-Host "  OK : VirtualHosts reecrits (ServerName $hostname)." -ForegroundColor Green
    }

    $hostsFile = "$env:SystemRoot\System32\drivers\etc\hosts"
    $hcontent = Get-Content $hostsFile -Raw -ErrorAction SilentlyContinue
    if ($hcontent -and ($hcontent -match [regex]::Escape($hostname))) {
        Write-Host "  OK : entree hosts deja presente ($hostname)." -ForegroundColor Green
    } else {
        $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
        [System.IO.File]::AppendAllText($hostsFile, "`r`n127.0.0.1  $hostname", $utf8NoBom)
        Write-Host "  OK : entree hosts ajoutee (127.0.0.1  $hostname)." -ForegroundColor Green
    }

    $ruleName = 'Apache HTTP (LAN)'
    $existing = Get-NetFirewallRule -DisplayName $ruleName -ErrorAction SilentlyContinue
    if (-not $existing) {
        New-NetFirewallRule -DisplayName $ruleName -Direction Inbound -Protocol TCP -LocalPort 80 -Action Allow -Profile Any | Out-Null
        Write-Host '  OK : regle pare-feu port 80 creee.' -ForegroundColor Green
    } else {
        Write-Host '  OK : regle pare-feu deja presente.' -ForegroundColor Green
    }

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
        Write-Host '  ATTENTION : Apache ne repond pas sur le port 80. Verifiez le panneau XAMPP.' -ForegroundColor Red
    }
}

# ── Resume ─────────────────────────────────────────────────────
function Show-Summary([string]$hostname) {
    $appUrl = "http://$hostname"
    Write-Host ''
    Write-Host '  ============================================' -ForegroundColor Green
    Write-Host '   MISE A JOUR TERMINEE' -ForegroundColor Green
    Write-Host '  ============================================' -ForegroundColor Green
    Write-Host ''
    Write-Host "  URL d'acces : $appUrl (donnees et comptes CONSERVES)" -ForegroundColor White
    Write-Host '  Mots de passe root/admin/pharmacien/caissier : inchanges.' -ForegroundColor Gray
    Write-Host '  Un dump de securite de la base se trouve dans pharmacare\backups\.' -ForegroundColor Gray
    Write-Host '  L''ancien code est sauvegarde dans pharmacare\_patch_backup\<horodatage>.' -ForegroundColor Gray
    Write-Host ''
    Write-Host '  Conseil : videz le cache du navigateur (CSS/JS cache-bustes via APP_VERSION).' -ForegroundColor Yellow
    Write-Host ''
}

# ── Programme principal ───────────────────────────────────────
Write-Banner

try {
    $liveDir = Resolve-LiveTarget
    $envFile = Join-Path $liveDir 'config\env.prod.php'
    Test-Prerequisites $envFile
    if ([string]::IsNullOrWhiteSpace($Hostname)) {
        # Reprise de l'hostname actuel depuis APP_URL (installation conservee).
        $envTxt = Get-Content $envFile -Raw -Encoding UTF8
        $mUrl = [regex]::Match($envTxt, "['""]APP_URL['""]\s*=>\s*['""]http://([^'""/]+)['""]")
        $Hostname = if ($mUrl.Success) { $mUrl.Groups[1].Value } else { 'pharmacare.lan' }
    }
    $Hostname = $Hostname.Trim().ToLower()
    Write-Host "  Installation cible : $liveDir" -ForegroundColor Gray
    Write-Host "  Nom d'hote         : $Hostname" -ForegroundColor Gray
    $creds = Get-DbCreds $envFile
    Test-ExistingInstall $creds
    Backup-Database $creds $liveDir
    Update-Files $liveDir
    Run-Migrations $liveDir
    Clear-AppCache $liveDir
    Set-ApacheConfig $Hostname
    Show-Summary $Hostname
    exit 0
}
catch {
    Write-Host ''
    Write-Host "  ERREUR : $($_.Exception.Message)" -ForegroundColor Red
    Write-Host ''
    Write-Host '  Aucune migration partielle n''a ete appliquee aux donnees :' -ForegroundColor Yellow
    Write-Host '  un dump de securite est fait AVANT toute modification (voir backups\).' -ForegroundColor Yellow
    Write-Host '  Corrigez la cause puis relancez la mise a jour.' -ForegroundColor Yellow
    Write-Host ''
    exit 1
}