<?php
// Chemin du dossier htdocs
$baseDir = __DIR__;

// Paramètres GET
$sort = $_GET['sort'] ?? 'az';
$theme = $_GET['theme'] ?? 'dark';
$font = $_GET['font'] ?? 'Arial';
$size = $_GET['size'] ?? '18';
$delete = $_GET['delete'] ?? '';
$message = '';

// Exclure certains dossiers système (optionnel)
$exclude = ['.', '..', 'dashboard', 'xampp', 'img', 'webalizer'];

// Supprimer un dossier
if ($delete && is_dir($baseDir . '/' . $delete)) {
    if (in_array($delete, $exclude)) {
        $message = '❌ Impossible de supprimer ce dossier système.';
    } else {
        function deleteFolder($path) {
            if (!is_dir($path)) return unlink($path);
            $items = array_diff(scandir($path), ['.', '..']);
            foreach ($items as $item) {
                deleteFolder($path . '/' . $item);
            }
            return rmdir($path);
        }
        if (deleteFolder($baseDir . '/' . $delete)) {
            $message = '✅ Dossier "' . htmlspecialchars($delete) . '" supprimé avec succès.';
        } else {
            $message = '❌ Erreur lors de la suppression du dossier.';
        }
    }
}

// Lire les dossiers
$folders = array_filter(glob($baseDir . '/*'), 'is_dir');

// Emoji selon le nom du dossier
function getFolderEmoji($name) {
    $name = strtolower($name);
    $emojis = [
        'admin' => '🔐', 'api' => '🔌', 'blog' => '📝', 'shop' => '🛒', 'store' => '🛍️',
        'portfolio' => '💼', 'test' => '🧪', 'dev' => '⚙️', 'app' => '📱', 'web' => '🌐',
        'dash' => '📊', 'cms' => '📰', 'erp' => '🏢', 'crm' => '🤝', 'login' => '🔑',
        'auth' => '🔒', 'panel' => '🎛️', 'chat' => '💬', 'social' => '👥', 'forum' => '🗣️',
        'music' => '🎵', 'video' => '🎬', 'photo' => '📷', 'game' => '🎮', 'learn' => '🎓',
        'edu' => '🎓', 'school' => '🏫', 'health' => '🏥', 'medical' => '🏥', 'food' => '🍕',
        'restaurant' => '🍽️', 'travel' => '✈️', 'hotel' => '🏨', 'booking' => '📅',
        'ecommerce' => '🛒', 'payment' => '💳', 'wallet' => '👛', 'crypto' => '🪙',
        'stock' => '📈', 'analytics' => '📊', 'seo' => '🔍', 'marketing' => '📢',
        'news' => '📰', 'magazine' => '📓', 'book' => '📖', 'reader' => '📖',
        'library' => '📚', 'download' => '⬇️', 'cloud' => '☁️', 'storage' => '💾',
        'file' => '📁', 'drive' => '📂', 'delivery' => '🚚',
        'mail' => '✉️', 'email' => '📧', 'sms' => '💬', 'notification' => '🔔',
        'push' => '📲', 'iot' => '📡', 'smart' => '🏠', 'home' => '🏠', 'office' => '🏢',
        'project' => '📁', 'task' => '✅', 'todo' => '✅', 'note' => '📝',
        'link' => '🔗', 'url' => '🔗', 'short' => '✂️',
        'clone' => '📋', 'copy' => '📄', 'backup' => '💿', 'restore' => '🔄',
        'tool' => '🔧', 'utility' => '🔧', 'helper' => '🤖', 'bot' => '🤖',
        'ai' => '🧠', 'ml' => '🧠', 'data' => '🗄️', 'db' => '🗄️',
        'database' => '🗄️', 'server' => '🖥️', 'client' => '👤', 'site' => '🌍',
        'mobile' => '📱', 'android' => '🤖', 'ios' => '🍎',
        'vue' => '💚', 'react' => '⚛️', 'angular' => '🔺', 'node' => '💚',
        'php' => '🐘', 'laravel' => '⚡', 'symfony' => '🔶', 'wordpress' => '🔵',
        'code' => '💻', 'github' => '🐙', 'gitlab' => '🦊', 'bitbucket' => '🔷',
        'docker' => '🐳', 'k8s' => '⚓', 'aws' => '☁️', 'azure' => '🔷',
        'gcp' => '🟢', 'firebase' => '🔥', 'vercel' => '▲', 'netlify' => '🌐',
        'theme' => '🎨', 'template' => '📄', 'starter' => '🚀', 'boiler' => '🔥',
        'demo' => '👁️', 'example' => '💡', 'sample' => '🧪', 'widget' => '🧩',
        'plugin' => '🧩', 'extension' => '➕', 'addon' => '🧩', 'module' => '📦',
        'lib' => '📖', 'package' => '📦', 'npm' => '📦',
        'yarn' => '🧶', 'pwa' => '📱', 'spa' => '🔄', 'ssr' => '🖥️',
    ];
    foreach ($emojis as $key => $emoji) {
        if (strpos($name, $key) !== false) return $emoji;
    }
    return '📂';
}
$folderData = [];
foreach ($folders as $folder) {
    $name = basename($folder);
    if (in_array($name, $exclude)) continue;
    $folderData[] = [
        'name' => $name,
        'time' => filemtime($folder)
    ];
}

if ($sort === 'az') {
    usort($folderData, fn($a, $b) => strcmp($a['name'], $b['name']));
} else {
    usort($folderData, fn($a, $b) => $b['time'] - $a['time']);
}

// Themes ultra-modernes
$themes = [
    'neon-dark' => [
        'name' => 'Neon Dark',
        'bg' => 'linear-gradient(135deg, #0a0a0f 0%, #1a1a2e 50%, #16213e 100%)',
        'card' => 'rgba(255, 255, 255, 0.05)',
        'cardHover' => 'rgba(255, 255, 255, 0.1)',
        'text' => '#e2e8f0',
        'accent' => '#00ff88',
        'accent2' => '#00d4ff',
        'glass' => 'blur(20px)',
        'shadow' => '0 8px 32px rgba(0, 255, 136, 0.15)',
        'border' => '1px solid rgba(0, 255, 136, 0.2)'
    ],
    'glass-premium' => [
        'name' => 'Glass Premium',
        'bg' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
        'card' => 'rgba(255, 255, 255, 0.15)',
        'cardHover' => 'rgba(255, 255, 255, 0.25)',
        'text' => '#ffffff',
        'accent' => '#ffd700',
        'accent2' => '#ff6b6b',
        'glass' => 'blur(30px)',
        'shadow' => '0 8px 32px rgba(255, 255, 255, 0.2)',
        'border' => '1px solid rgba(255, 255, 255, 0.3)'
    ],
    'cyberpunk' => [
        'name' => 'Cyberpunk',
        'bg' => 'linear-gradient(135deg, #0f0c29 0%, #302b63 50%, #24243e 100%)',
        'card' => 'rgba(255, 0, 255, 0.08)',
        'cardHover' => 'rgba(255, 0, 255, 0.15)',
        'text' => '#00ff41',
        'accent' => '#ff00ff',
        'accent2' => '#00ffff',
        'glass' => 'blur(15px)',
        'shadow' => '0 0 30px rgba(255, 0, 255, 0.3), inset 0 0 30px rgba(0, 255, 65, 0.1)',
        'border' => '1px solid rgba(255, 0, 255, 0.4)'
    ],
    'aurora' => [
        'name' => 'Aurora',
        'bg' => 'linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%)',
        'card' => 'rgba(255, 255, 255, 0.08)',
        'cardHover' => 'rgba(255, 255, 255, 0.15)',
        'text' => '#ffffff',
        'accent' => '#64ffda',
        'accent2' => '#ff6b6b',
        'glass' => 'blur(25px)',
        'shadow' => '0 8px 32px rgba(100, 255, 218, 0.2)',
        'border' => '1px solid rgba(100, 255, 218, 0.3)'
    ],
    'sunset' => [
        'name' => 'Sunset',
        'bg' => 'linear-gradient(135deg, #fa709a 0%, #fee140 100%)',
        'card' => 'rgba(255, 255, 255, 0.2)',
        'cardHover' => 'rgba(255, 255, 255, 0.35)',
        'text' => '#1a1a2e',
        'accent' => '#ff6b6b',
        'accent2' => '#4ecdc4',
        'glass' => 'blur(20px)',
        'shadow' => '0 8px 32px rgba(250, 112, 154, 0.3)',
        'border' => '1px solid rgba(255, 255, 255, 0.4)'
    ],
    'midnight' => [
        'name' => 'Midnight',
        'bg' => 'linear-gradient(135deg, #0f0f23 0%, #1a1a3e 50%, #2d2d5f 100%)',
        'card' => 'rgba(255, 255, 255, 0.06)',
        'cardHover' => 'rgba(255, 255, 255, 0.12)',
        'text' => '#c4b5fd',
        'accent' => '#a78bfa',
        'accent2' => '#f472b6',
        'glass' => 'blur(20px)',
        'shadow' => '0 8px 32px rgba(167, 139, 250, 0.2)',
        'border' => '1px solid rgba(167, 139, 250, 0.3)'
    ]
];

$currentTheme = $themes[$theme] ?? $themes['neon-dark'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Projet Application Web</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;700&family=Roboto:wght@400;700&family=Open+Sans:wght@400;700&family=Lato:wght@400;700&family=Montserrat:wght@400;700&family=Raleway:wght@400;700&family=Playfair+Display:wght@400;700&family=Source+Sans+Pro:wght@400;700&family=Nunito:wght@400;700&family=Oswald:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap');

        :root {
            --bg-gradient: <?= $currentTheme['bg'] ?>;
            --card-bg: <?= $currentTheme['card'] ?>;
            --card-hover: <?= $currentTheme['cardHover'] ?>;
            --text: <?= $currentTheme['text'] ?>;
            --accent: <?= $currentTheme['accent'] ?>;
            --accent2: <?= $currentTheme['accent2'] ?>;
            --glass: <?= $currentTheme['glass'] ?>;
            --shadow: <?= $currentTheme['shadow'] ?>;
            --border: <?= $currentTheme['border'] ?>;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', <?= $font ?>, sans-serif;
            font-size: <?= $size ?>px;
            background: var(--bg-gradient);
            color: var(--text);
            min-height: 100vh;
            padding: 40px 20px;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle at 20% 50%, rgba(120, 119, 198, 0.3) 0%, transparent 50%),
                        radial-gradient(circle at 80% 80%, rgba(255, 119, 198, 0.3) 0%, transparent 50%),
                        radial-gradient(circle at 40% 20%, rgba(255, 215, 0, 0.2) 0%, transparent 50%);
            animation: gradientShift 20s ease infinite;
            z-index: -1;
        }

        @keyframes gradientShift {
            0%, 100% { transform: rotate(0deg); }
            50% { transform: rotate(180deg); }
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }

        @keyframes glow {
            0%, 100% { box-shadow: var(--shadow); }
            50% { box-shadow: var(--shadow), 0 0 40px var(--accent); }
        }

        h1 {
            text-align: center;
            margin-bottom: 40px;
            font-size: 3em;
            font-weight: 900;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: float 3s ease-in-out infinite;
            text-shadow: 0 0 40px rgba(255, 255, 255, 0.1);
        }

        .controls {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 40px;
            padding: 25px 30px;
            background: var(--card-bg);
            backdrop-filter: var(--glass);
            -webkit-backdrop-filter: var(--glass);
            border-radius: 20px;
            border: var(--border);
            box-shadow: var(--shadow);
            animation: glow 3s ease-in-out infinite;
        }

        .controls label {
            font-weight: 600;
            display: flex;
            flex-direction: column;
            gap: 8px;
            font-size: 0.85em;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.9;
        }

        .controls select {
            padding: 12px 20px;
            border-radius: 12px;
            border: var(--border);
            cursor: pointer;
            background: rgba(255, 255, 255, 0.1);
            color: var(--text);
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            font-weight: 500;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            min-width: 160px;
        }

        .controls select:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }

        .controls select:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 20px rgba(0, 255, 136, 0.3);
        }

        .controls select option {
            background: #1a1a2e;
            color: #fff;
        }

        .container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .card {
            background: var(--card-bg);
            backdrop-filter: var(--glass);
            -webkit-backdrop-filter: var(--glass);
            padding: 30px 25px;
            border-radius: 20px;
            border: var(--border);
            box-shadow: var(--shadow);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            text-align: center;
            position: relative;
            overflow: hidden;
            cursor: pointer;
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transition: left 0.6s ease;
        }

        .card:hover::before {
            left: 100%;
        }

        .card:hover {
            background: var(--card-hover);
            transform: translateY(-8px) scale(1.02);
            box-shadow: var(--shadow), 0 20px 40px rgba(0, 0, 0, 0.3);
            border-color: var(--accent);
        }

        .card a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 700;
            font-size: 1.2em;
            display: flex;
            flex-direction: column;
            gap: 15px;
            align-items: center;
            transition: all 0.3s ease;
        }

        .card a:hover {
            color: var(--accent2);
        }

        .card .emoji {
            font-size: 3em;
            display: block;
            animation: float 2s ease-in-out infinite;
        }

        ::-webkit-scrollbar {
            width: 10px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.2);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--accent);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--accent2);
        }

        @media (max-width: 768px) {
            h1 { font-size: 2em; }
            .container { grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; }
            .controls { padding: 15px; gap: 10px; }
        }
    </style>
</head>
<body>

<h1>🚀 Mes Projets Application Web</h1>

<?php if ($message): ?>
    <div style="text-align:center; padding:15px; margin-bottom:20px; background:var(--card-bg); border-radius:12px; border:var(--border); backdrop-filter:var(--glass); max-width:600px; margin-left:auto; margin-right:auto;">
        <?= $message ?>
    </div>
<?php endif; ?>

<form class="controls" method="get">
    <label>
        Trier :
        <select name="sort" onchange="this.form.submit()">
            <option value="az" <?= $sort === 'az' ? 'selected' : '' ?>>A-Z</option>
            <option value="recent" <?= $sort === 'recent' ? 'selected' : '' ?>>Plus récent → Ancien</option>
        </select>
    </label>
    <label>
        Thème :
        <select name="theme" onchange="this.form.submit()">
            <?php foreach ($themes as $key => $t): ?>
                <option value="<?= $key ?>" <?= $theme === $key ? 'selected' : '' ?>><?= $t['name'] ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>
        Police :
        <select name="font" onchange="this.form.submit()">
            <option value="Arial" <?= $font === 'Arial' ? 'selected' : '' ?>>Arial</option>
            <option value="Poppins" <?= $font === 'Poppins' ? 'selected' : '' ?>>Poppins</option>
            <option value="Roboto" <?= $font === 'Roboto' ? 'selected' : '' ?>>Roboto</option>
            <option value="Open Sans" <?= $font === 'Open Sans' ? 'selected' : '' ?>>Open Sans</option>
            <option value="Lato" <?= $font === 'Lato' ? 'selected' : '' ?>>Lato</option>
            <option value="Montserrat" <?= $font === 'Montserrat' ? 'selected' : '' ?>>Montserrat</option>
            <option value="Raleway" <?= $font === 'Raleway' ? 'selected' : '' ?>>Raleway</option>
            <option value="Playfair Display" <?= $font === 'Playfair Display' ? 'selected' : '' ?>>Playfair Display</option>
            <option value="Source Sans Pro" <?= $font === 'Source Sans Pro' ? 'selected' : '' ?>>Source Sans Pro</option>
            <option value="Nunito" <?= $font === 'Nunito' ? 'selected' : '' ?>>Nunito</option>
            <option value="Oswald" <?= $font === 'Oswald' ? 'selected' : '' ?>>Oswald</option>
            <option value="Georgia" <?= $font === 'Georgia' ? 'selected' : '' ?>>Georgia</option>
            <option value="Courier New" <?= $font === 'Courier New' ? 'selected' : '' ?>>Courier New</option>
            <option value="Verdana" <?= $font === 'Verdana' ? 'selected' : '' ?>>Verdana</option>
            <option value="Times New Roman" <?= $font === 'Times New Roman' ? 'selected' : '' ?>>Times New Roman</option>
        </select>
    </label>
    <label>
        Taille :
        <select name="size" onchange="this.form.submit()">
            <option value="14" <?= $size === '14' ? 'selected' : '' ?>>14px</option>
            <option value="16" <?= $size === '16' ? 'selected' : '' ?>>16px</option>
            <option value="18" <?= $size === '18' ? 'selected' : '' ?>>18px</option>
            <option value="20" <?= $size === '20' ? 'selected' : '' ?>>20px</option>
            <option value="22" <?= $size === '22' ? 'selected' : '' ?>>22px</option>
        </select>
    </label>
</form>

<div class="container">
    <?php foreach ($folderData as $folder):
        $url = "http://".$_SERVER['HTTP_HOST']."/".$folder['name'];
    ?>
    <div class="card">
            <a href="<?= $url ?>" target="_blank">
                <span style="font-size:3em; display:block; margin-bottom:15px;"><?= getFolderEmoji($folder['name']) ?></span>
                <?= htmlspecialchars($folder['name']) ?>
            </a>
            <a href="?delete=<?= urlencode($folder['name']) ?>&sort=<?= $sort ?>&theme=<?= $theme ?>&font=<?= $font ?>&size=<?= $size ?>"
               onclick="return confirm('⚠️ Supprimer le dossier \"<?= htmlspecialchars($folder['name']) ?>\" et tout son contenu ?')"
               style="position:absolute; top:10px; right:10px; background:rgba(255,0,0,0.7); color:#fff; border:none; border-radius:50%; width:30px; height:30px; display:flex; align-items:center; justify-content:center; cursor:pointer; text-decoration:none; font-size:16px; backdrop-filter:blur(10px); transition:all 0.3s ease;"
               onmouseover="this.style.background='rgba(255,0,0,0.9)'; this.style.transform='scale(1.1)'"
               onmouseout="this.style.background='rgba(255,0,0,0.7)'; this.style.transform='scale(1)'">🗑️</a>
        </div>
    <?php endforeach; ?>
</div>

</body>
</html>
