<?php
// includes/charts.php — Helpers de graphiques SVG

/**
 * Rend une courbe d'evolution SVG avec aire remplie et tooltip.
 *
 * @param array  $data   Tableau associatif [label => value]
 * @param string $color  Couleur de la courbe (hex ou var CSS)
 * @param string $title  Titre affiche au-dessus
 * @param string $suffix Suffixe des valeurs (€, ventes…)
 */
function renderLineChart(array $data, string $color = 'var(--teal)', string $title = '', string $suffix = ''): void {
    if (empty($data)) {
        echo '<div class="empty" style="padding:30px;"><div style="color:var(--text3);margin-bottom:8px;">' . icon('chart',32) . '</div><div>Aucune donnee</div></div>';
        return;
    }

    $values = array_values($data);
    $labels = array_keys($data);
    $count  = count($values);
    $maxV   = max($values) ?: 1;
    $minV   = min($values);
    $range  = $maxV - $minV;
    if ($range == 0) $range = 1;

    // Dimensions
    $width  = 900;
    $height = 280;
    $padL   = 56;
    $padR   = 24;
    $padT   = 32;
    $padB   = 44;
    $gw     = $width - $padL - $padR;
    $gh     = $height - $padT - $padB;

    // Points
    $pts = [];
    foreach ($values as $i => $v) {
        $x = $padL + ($i / max($count - 1, 1)) * $gw;
        $y = $padT + $gh - (($v - $minV) / $range) * $gh;
        $pts[] = ['x' => round($x,2), 'y' => round($y,2), 'v' => $v, 'l' => $labels[$i]];
    }

    // === Lissage Catmull-Rom -> Bezier ===
    function catmullRom2bezier($p0, $p1, $p2, $p3) {
        $cp1x = $p1['x'] + ($p2['x'] - $p0['x']) / 6;
        $cp1y = $p1['y'] + ($p2['y'] - $p0['y']) / 6;
        $cp2x = $p2['x'] - ($p3['x'] - $p1['x']) / 6;
        $cp2y = $p2['y'] - ($p3['y'] - $p1['y']) / 6;
        return "C {$cp1x} {$cp1y}, {$cp2x} {$cp2y}, {$p2['x']} {$p2['y']}";
    }

    $pathD = "M {$pts[0]['x']} {$pts[0]['y']}";
    for ($i = 0; $i < $count - 1; $i++) {
        $p0 = $pts[max(0, $i - 1)];
        $p1 = $pts[$i];
        $p2 = $pts[$i + 1];
        $p3 = $pts[min($count - 1, $i + 2)];
        $pathD .= ' ' . catmullRom2bezier($p0, $p1, $p2, $p3);
    }

    // Aire sous la courbe (fermee en bas)
    $areaD = $pathD . " L {$pts[$count-1]['x']} " . ($height - $padB) . " L {$pts[0]['x']} " . ($height - $padB) . " Z";

    $gid = 'grad_' . uniqid();
    $cid = 'chart_' . uniqid();

    // Resolve couleur
    $hexColor = str_starts_with($color, 'var(') ? '#22d3ee' : $color;
    if (str_starts_with($color, 'var(--gold')) $hexColor = '#f59e0b';
    if (str_starts_with($color, 'var(--red'))  $hexColor = '#ef4444';
    if (str_starts_with($color, 'var(--blue')) $hexColor = '#818cf8';
    if (str_starts_with($color, 'var(--teal')) $hexColor = '#22d3ee';

    // Couleurs derivees pour l'effet stacked
    $rgb = hex2rgb($hexColor);
    $lineLight = "rgba({$rgb},0.9)";
    $lineDim   = "rgba({$rgb},0.35)";
    $areaTop   = "rgba({$rgb},0.30)";
    $areaMid   = "rgba({$rgb},0.10)";
    $areaBot   = "rgba({$rgb},0.00)";
    $glowColor = "rgba({$rgb},0.45)";
    $glowSoft  = "rgba({$rgb},0.15)";

    // === SVG ===
    echo '<div class="line-chart-wrap" style="position:relative;">';
    echo '<svg viewBox="0 0 ' . $width . ' ' . $height . '" style="width:100%;height:auto;display:block;" class="line-chart-svg" id="' . $cid . '">';
    echo '<defs>';

    // Gradient aire empilee
    echo '<linearGradient id="' . $gid . '_area" x1="0" y1="0" x2="0" y2="1">';
    echo '<stop offset="0%"   stop-color="' . $hexColor . '" stop-opacity="0.35"/>';
    echo '<stop offset="30%"  stop-color="' . $hexColor . '" stop-opacity="0.18"/>';
    echo '<stop offset="70%"  stop-color="' . $hexColor . '" stop-opacity="0.04"/>';
    echo '<stop offset="100%" stop-color="' . $hexColor . '" stop-opacity="0"/>';
    echo '</linearGradient>';

    // Gradient ligne
    echo '<linearGradient id="' . $gid . '_line" x1="0" y1="0" x2="0" y2="1">';
    echo '<stop offset="0%"   stop-color="' . $lineLight . '"/>';
    echo '<stop offset="100%" stop-color="' . $lineDim . '"/>';
    echo '</linearGradient>';

    // Glow diffus large
    echo '<filter id="' . $gid . '_glow1" x="-60%" y="-60%" width="220%" height="220%">';
    echo '<feGaussianBlur stdDeviation="6" result="b1"/>';
    echo '<feFlood flood-color="' . $glowSoft . '" result="c1"/>';
    echo '<feComposite in="c1" in2="b1" operator="in" result="g1"/>';
    echo '<feMerge><feMergeNode in="g1"/><feMergeNode in="SourceGraphic"/></feMerge>';
    echo '</filter>';

    // Glow serre intense
    echo '<filter id="' . $gid . '_glow2" x="-40%" y="-40%" width="180%" height="180%">';
    echo '<feGaussianBlur stdDeviation="2.5" result="b2"/>';
    echo '<feFlood flood-color="' . $glowColor . '" result="c2"/>';
    echo '<feComposite in="c2" in2="b2" operator="in" result="g2"/>';
    echo '<feMerge><feMergeNode in="g2"/><feMergeNode in="SourceGraphic"/></feMerge>';
    echo '</filter>';

    // Halo point
    echo '<radialGradient id="' . $gid . '_halo">';
    echo '<stop offset="0%"   stop-color="' . $hexColor . '" stop-opacity="0.6"/>';
    echo '<stop offset="100%" stop-color="' . $hexColor . '" stop-opacity="0"/>';
    echo '</radialGradient>';

    // Clip pour animation
    $clipId = $gid . '_clip';
    echo '<clipPath id="' . $clipId . '">';
    echo '<rect x="0" y="0" width="0" height="' . $height . '" class="chart-clip-rect">';
    echo '<animate attributeName="width" from="0" to="' . $width . '" dur="1.2s" fill="freeze" calcMode="spline" keySplines="0.4 0 0.2 1"/>';
    echo '</rect>';
    echo '</clipPath>';

    echo '</defs>';

    // Grille horizontale
    $steps = 5;
    for ($s = 0; $s <= $steps; $s++) {
        $gy = $padT + ($s / $steps) * $gh;
        $val = round($minV + ($maxV - $minV) * (1 - $s / $steps), 0);
        echo '<line x1="' . $padL . '" y1="' . $gy . '" x2="' . ($width - $padR) . '" y2="' . $gy . '" stroke="var(--border)" stroke-width="0.5" stroke-dasharray="3 5" opacity="0.35"/>';
        echo '<text x="' . ($padL - 10) . '" y="' . ($gy + 4) . '" text-anchor="end" font-size="9" fill="var(--text3)" opacity="0.7">' . number_format($val,0,',',' ') . '</text>';
    }

    // Group anime par clipPath
    echo '<g clip-path="url(#' . $clipId . ')">';

    // Ombre projetee sous l'aire (effet empile)
    echo '<path d="' . $areaD . '" fill="url(#' . $gid . '_area)" filter="url(#' . $gid . '_glow1)" opacity="0.8"/>';

    // Aire principale
    echo '<path d="' . $areaD . '" fill="url(#' . $gid . '_area)"/>';

    // Ligne avec glow
    echo '<path d="' . $pathD . '" fill="none" stroke="url(#' . $gid . '_line)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" filter="url(#' . $gid . '_glow2)" class="chart-line"/>';

    // Points avec halo
    foreach ($pts as $i => $p) {
        $isEdge = ($i === 0 || $i === $count - 1);
        $opacity = $isEdge ? '1' : '0';
        // Halo
        echo '<circle cx="' . $p['x'] . '" cy="' . $p['y'] . '" r="14" fill="url(#' . $gid . '_halo)" class="chart-halo" data-i="' . $i . '" opacity="0" style="transition:opacity .25s ease;"/>';
        // Point centre
        echo '<circle cx="' . $p['x'] . '" cy="' . $p['y'] . '" r="5" fill="var(--bg)" stroke="' . $hexColor . '" stroke-width="2.5" class="chart-point" data-i="' . $i . '" opacity="' . $opacity . '"/>';
    }

    echo '</g>';

    // Labels X
    $skip = max(1, floor($count / 8));
    foreach ($pts as $i => $p) {
        if ($i % $skip !== 0 && $i !== $count - 1) continue;
        echo '<text x="' . $p['x'] . '" y="' . ($height - $padB + 22) . '" text-anchor="middle" font-size="10" fill="var(--text3)" opacity="0.8">' . e($p['l']) . '</text>';
    }

    // Barres de survol invisibles
    $barW = $gw / max($count, 1);
    foreach ($pts as $i => $p) {
        echo '<rect x="' . ($p['x'] - $barW/2) . '" y="' . $padT . '" width="' . $barW . '" height="' . $gh . '" fill="transparent" style="cursor:pointer;" onmouseover="chartShowTip(this,\'' . $cid . '\',' . $i . ',\'' . e($p['l']) . '\',\'' . number_format($p['v'],2,',',' ') . ' ' . $suffix . '\')" onmouseout="chartHideTip(\'' . $cid . '\',' . $i . ')"/>';
    }

    echo '</svg>';
    echo '</div>';
}

/**
 * Rend un graphique en chandeliers (candlestick) SVG.
 *
 * @param array  $data   [label => ['open'=>x,'high'=>x,'low'=>x,'close'=>x]]
 * @param string $suffix Suffixe des valeurs
 */
function renderCandleChart(array $data, string $suffix = ''): void {
    if (empty($data)) {
        echo '<div class="empty" style="padding:30px;"><div style="color:var(--text3);margin-bottom:8px;">' . icon('chart',32) . '</div><div>Aucune donnée</div></div>';
        return;
    }

    $labels = array_keys($data);
    $count  = count($data);

    // Trouver min/max pour l'échelle Y
    $allHigh = array_column($data, 'high');
    $allLow  = array_column($data, 'low');
    $maxV = max($allHigh) ?: 1;
    $minV = min($allLow);
    $range = $maxV - $minV;
    if ($range == 0) $range = 1;
    // Marge de 8%
    $minV = max(0, $minV - $range * 0.08);
    $maxV = $maxV + $range * 0.08;
    $range = $maxV - $minV;

    $width  = 900;
    $height = 300;
    $padL   = 56;
    $padR   = 16;
    $padT   = 28;
    $padB   = 44;
    $gw     = $width - $padL - $padR;
    $gh     = $height - $padT - $padB;

    $slotW = $gw / $count;
    $candleW = min(14, $slotW * 0.55);
    $wickW   = 1.5;

    // Couleurs haussier/baissier
    $bullColor = '#22d3ee'; // teal — hausse
    $bearColor = '#ef4444'; // red  — baisse
    $bullDim   = 'rgba(34,211,238,0.18)';
    $bearDim   = 'rgba(239,68,68,0.15)';

    $gid = 'cnd_' . uniqid();
    $cid = 'chart_' . uniqid();

    echo '<div class="candle-chart-wrap" style="position:relative;" id="' . $cid . '_wrap">';
    echo '<svg viewBox="0 0 ' . $width . ' ' . $height . '" style="width:100%;height:auto;display:block;" class="candle-chart-svg">';
    echo '<defs>';

    // Glow haussier
    echo '<filter id="' . $gid . '_glowBull" x="-50%" y="-50%" width="200%" height="200%">';
    echo '<feGaussianBlur stdDeviation="2.5" result="b"/>';
    echo '<feFlood flood-color="rgba(34,211,238,0.4)" result="c"/>';
    echo '<feComposite in="c" in2="b" operator="in" result="g"/>';
    echo '<feMerge><feMergeNode in="g"/><feMergeNode in="SourceGraphic"/></feMerge>';
    echo '</filter>';

    // Glow baissier
    echo '<filter id="' . $gid . '_glowBear" x="-50%" y="-50%" width="200%" height="200%">';
    echo '<feGaussianBlur stdDeviation="2.5" result="b"/>';
    echo '<feFlood flood-color="rgba(239,68,68,0.35)" result="c"/>';
    echo '<feComposite in="c" in2="b" operator="in" result="g"/>';
    echo '<feMerge><feMergeNode in="g"/><feMergeNode in="SourceGraphic"/></feMerge>';
    echo '</filter>';

    // Clip animation
    $clipId = $gid . '_clip';
    echo '<clipPath id="' . $clipId . '">';
    echo '<rect x="0" y="0" width="0" height="' . $height . '" class="chart-clip-rect">';
    echo '<animate attributeName="width" from="0" to="' . $width . '" dur="1.2s" fill="freeze" calcMode="spline" keySplines="0.4 0 0.2 1"/>';
    echo '</rect>';
    echo '</clipPath>';

    echo '</defs>';

    // Grille horizontale
    $steps = 5;
    for ($s = 0; $s <= $steps; $s++) {
        $gy = $padT + ($s / $steps) * $gh;
        $val = round($minV + ($maxV - $minV) * (1 - $s / $steps), 0);
        echo '<line x1="' . $padL . '" y1="' . $gy . '" x2="' . ($width - $padR) . '" y2="' . $gy . '" stroke="var(--border)" stroke-width="0.5" stroke-dasharray="3 5" opacity="0.25"/>';
        echo '<text x="' . ($padL - 10) . '" y="' . ($gy + 4) . '" text-anchor="end" font-size="9" fill="var(--text3)" opacity="0.6">' . number_format($val,0,',',' ') . '</text>';
    }

    echo '<g clip-path="url(#' . $clipId . ')">';

    $candles = [];
    $idx = 0;
    foreach ($data as $labelKey => $c) {
        $cx = $padL + ($idx + 0.5) * $slotW;
        $yHigh  = $padT + $gh - (($c['high'] - $minV) / $range) * $gh;
        $yLow   = $padT + $gh - (($c['low']  - $minV) / $range) * $gh;
        $yOpen  = $padT + $gh - (($c['open'] - $minV) / $range) * $gh;
        $yClose = $padT + $gh - (($c['close'] - $minV) / $range) * $gh;

        $isBull = $c['close'] >= $c['open'];
        $color  = $isBull ? $bullColor : $bearColor;
        $fill   = $isBull ? $bullDim   : $bearDim;
        $filter = $isBull ? 'url(#' . $gid . '_glowBull)' : 'url(#' . $gid . '_glowBear)';

        $bodyTop    = min($yOpen, $yClose);
        $bodyBottom = max($yOpen, $yClose);
        $bodyH      = max(2, $bodyBottom - $bodyTop);

        $candles[] = [
            'cx' => $cx, 'yHigh' => $yHigh, 'yLow' => $yLow,
            'bodyTop' => $bodyTop, 'bodyBottom' => $bodyBottom,
            'bodyH' => $bodyH, 'color' => $color, 'fill' => $fill,
            'filter' => $filter, 'isBull' => $isBull,
            'label' => $labelKey, 'data' => $c,
        ];
        $idx++;
    }

    // Wick (derriere)
    foreach ($candles as $c) {
        echo '<line x1="' . round($c['cx'],2) . '" y1="' . round($c['yHigh'],2) . '" x2="' . round($c['cx'],2) . '" y2="' . round($c['yLow'],2) . '" stroke="' . $c['color'] . '" stroke-width="' . $wickW . '" opacity="0.7"/>';
    }

    // Body
    foreach ($candles as $i => $c) {
        $rx = 2;
        echo '<rect x="' . round($c['cx'] - $candleW/2,2) . '" y="' . round($c['bodyTop'],2) . '" width="' . round($candleW,2) . '" height="' . round($c['bodyH'],2) . '" rx="' . $rx . '" fill="' . $c['fill'] . '" stroke="' . $c['color'] . '" stroke-width="1.5" filter="' . $c['filter'] . '" class="candle-body" data-i="' . $i . '"/>';
    }

    echo '</g>';

    // Labels X (espaces)
    $skip = max(1, floor($count / 8));
    foreach ($candles as $i => $c) {
        if ($i % $skip !== 0 && $i !== $count - 1) continue;
        echo '<text x="' . round($c['cx'],2) . '" y="' . ($height - $padB + 22) . '" text-anchor="middle" font-size="10" fill="var(--text3)" opacity="0.7">' . e($c['label']) . '</text>';
    }

    // Barres de survol invisibles
    foreach ($candles as $i => $c) {
        $tipOpen  = number_format($c['data']['open'],2,',',' ') . ' ' . $suffix;
        $tipHigh  = number_format($c['data']['high'],2,',',' ') . ' ' . $suffix;
        $tipLow   = number_format($c['data']['low'],2,',',' ') . ' ' . $suffix;
        $tipClose = number_format($c['data']['close'],2,',',' ') . ' ' . $suffix;
        $tipLabel = e($c['label']);
        echo '<rect x="' . ($c['cx'] - $slotW/2) . '" y="' . $padT . '" width="' . $slotW . '" height="' . $gh . '" fill="transparent" style="cursor:pointer;" onmouseover="candleShowTip(this,\'' . $cid . '_wrap\',' . $i . ',\'' . $tipLabel . '\',\'' . $tipOpen . '\',\'' . $tipHigh . '\',\'' . $tipLow . '\',\'' . $tipClose . '\',' . ($c['isBull'] ? '1' : '0') . ')" onmouseout="candleHideTip(\'' . $cid . '_wrap\',' . $i . ')"/>';
    }

    echo '</svg>';
    echo '</div>';
}

/**
 * Rend un histogramme (barres) SVG détaillé.
 *
 * @param array  $data   [label => value] ou [label => ['value'=>x,'nb'=>y,'avg'=>z]]
 * @param string $suffix Suffixe des valeurs (FCFA, ventes…)
 */
function renderBarChart(array $data, string $suffix = ''): void {
    if (empty($data)) {
        echo '<div class="empty" style="padding:30px;"><div style="color:var(--text3);margin-bottom:8px;">' . icon('chart',32) . '</div><div>Aucune donnée</div></div>';
        return;
    }

    // Normalisation : chaque item => ['value','nb','avg']
    $labels = array_keys($data);
    $items  = [];
    foreach (array_values($data) as $v) {
        if (is_array($v)) {
            $items[] = ['value' => (float)($v['value'] ?? 0), 'nb' => $v['nb'] ?? null, 'avg' => $v['avg'] ?? null];
        } else {
            $items[] = ['value' => (float)$v, 'nb' => null, 'avg' => null];
        }
    }
    $count = count($items);
    $values = array_column($items, 'value');
    $maxV   = max($values) ?: 1;
    $mean   = array_sum($values) / $count;
    $maxIdx = array_search($maxV, $values, true);

    $compact = function ($v) {
        if ($v >= 1e6) return round($v / 1e6, 1) . 'M';
        if ($v >= 1e3) return round($v / 1e3) . 'k';
        return (string)(int)$v;
    };

    $width  = 900;
    $height = 300;
    $padL = 56; $padR = 16; $padT = 34; $padB = 44;
    $gw = $width - $padL - $padR;
    $gh = $height - $padT - $padB;
    $slotW = $gw / $count;
    $barW  = min($slotW * 0.62, 44);

    $hexTeal = '#22d3ee';
    $hexGold = '#f59e0b';
    $rgbTeal = hex2rgb($hexTeal);
    $gid = 'bar_' . uniqid();
    $cid = 'chart_' . uniqid();

    echo '<div class="bar-chart-wrap" style="position:relative;" id="' . $cid . '_wrap">';
    echo '<svg viewBox="0 0 ' . $width . ' ' . $height . '" style="width:100%;height:auto;display:block;" class="bar-chart-svg" id="' . $cid . '">';
    echo '<defs>';

    // Dégradés verticalaux (normal + pic)
    foreach ([['bar', $hexTeal, $rgbTeal], ['peak', $hexGold, hex2rgb($hexGold)]] as $g) {
        [$k, $col, $rgb] = $g;
        echo '<linearGradient id="' . $gid . '_' . $k . '" x1="0" y1="0" x2="0" y2="1">';
        echo '<stop offset="0%" stop-color="' . $col . '" stop-opacity="0.95"/>';
        echo '<stop offset="100%" stop-color="rgba(' . $rgb . ',0.22)"/>';
        echo '</linearGradient>';
    }

    // Glow
    echo '<filter id="' . $gid . '_glow" x="-50%" y="-50%" width="200%" height="200%">';
    echo '<feGaussianBlur stdDeviation="3" result="b"/>';
    echo '<feFlood flood-color="rgba(' . $rgbTeal . ',0.4)" result="c"/>';
    echo '<feComposite in="c" in2="b" operator="in" result="g"/>';
    echo '<feMerge><feMergeNode in="g"/><feMergeNode in="SourceGraphic"/></feMerge>';
    echo '</filter>';

    // Clip animation
    $clipId = $gid . '_clip';
    echo '<clipPath id="' . $clipId . '">';
    echo '<rect x="0" y="0" width="0" height="' . $height . '" class="chart-clip-rect">';
    echo '<animate attributeName="width" from="0" to="' . $width . '" dur="1.1s" fill="freeze" calcMode="spline" keySplines="0.4 0 0.2 1"/>';
    echo '</rect>';
    echo '</clipPath>';

    echo '</defs>';

    // Grille horizontale + échelle
    $steps = 5;
    for ($s = 0; $s <= $steps; $s++) {
        $gy = $padT + ($s / $steps) * $gh;
        $val = round($maxV * (1 - $s / $steps), 0);
        echo '<line x1="' . $padL . '" y1="' . $gy . '" x2="' . ($width - $padR) . '" y2="' . $gy . '" stroke="var(--border)" stroke-width="0.5" stroke-dasharray="3 5" opacity="0.3"/>';
        echo '<text x="' . ($padL - 10) . '" y="' . ($gy + 4) . '" text-anchor="end" font-size="9" fill="var(--text3)" opacity="0.7">' . $compact($val) . '</text>';
    }

    // Ligne de moyenne
    $meanY = $padT + $gh - ($mean / $maxV) * $gh;
    echo '<line x1="' . $padL . '" y1="' . round($meanY,2) . '" x2="' . ($width - $padR) . '" y2="' . round($meanY,2) . '" stroke="var(--gold)" stroke-width="1" stroke-dasharray="5 4" opacity="0.7"/>';
    echo '<text x="' . ($width - $padR) . '" y="' . round($meanY - 5,2) . '" text-anchor="end" font-size="9" fill="var(--gold)" opacity="0.9">moy. ' . $compact($mean) . '</text>';

    // Calcul des barres
    $baseY = $padT + $gh;
    $bars = [];
    foreach ($items as $idx => $it) {
        $v = $it['value'];
        $cx = $padL + ($idx + 0.5) * $slotW;
        $h = $maxV > 0 ? ($v / $maxV) * $gh : 0;
        $h = $v > 0 ? max(2, $h) : 0;
        $isPeak = ($idx === $maxIdx && $v > 0);
        $bars[] = [
            'cx' => $cx, 'x' => $cx - $barW/2, 'y' => $baseY - $h, 'w' => $barW, 'h' => $h,
            'v' => $v, 'l' => $labels[$idx], 'peak' => $isPeak, 'nb' => $it['nb'], 'avg' => $it['avg'],
        ];
    }

    echo '<g clip-path="url(#' . $clipId . ')">';
    foreach ($bars as $i => $b) {
        if ($b['h'] <= 0) continue;
        $grad = $b['peak'] ? $gid . '_peak' : $gid . '_bar';
        $stroke = $b['peak'] ? $hexGold : $hexTeal;
        echo '<rect x="' . round($b['x'],2) . '" y="' . round($b['y'],2) . '" width="' . round($b['w'],2) . '" height="' . round($b['h'],2) . '" rx="3" fill="url(#' . $grad . ')" stroke="' . $stroke . '" stroke-width="1" filter="url(#' . $gid . '_glow)" class="bar-body" data-i="' . $i . '"/>';
    }
    echo '</g>';

    // Étiquettes de valeur au sommet (compactes, espacées si dense)
    $lblSkip = $count <= 16 ? 1 : max(1, floor($count / 10));
    foreach ($bars as $i => $b) {
        if ($b['v'] <= 0) continue;
        if ($i % $lblSkip !== 0 && $i !== $maxIdx) continue;
        echo '<text x="' . round($b['cx'],2) . '" y="' . round($b['y'] - 5,2) . '" text-anchor="middle" font-size="9" font-weight="600" fill="' . ($b['peak'] ? 'var(--gold)' : 'var(--text2)') . '" opacity="0.95">' . $compact($b['v']) . '</text>';
    }

    // Labels X (espacés)
    $skip = max(1, floor($count / 9));
    foreach ($bars as $i => $b) {
        if ($i % $skip !== 0 && $i !== $count - 1) continue;
        echo '<text x="' . round($b['cx'],2) . '" y="' . ($height - $padB + 22) . '" text-anchor="middle" font-size="10" fill="var(--text3)" opacity="0.8">' . e($b['l']) . '</text>';
    }

    // Zones de survol + tooltip riche
    foreach ($bars as $i => $b) {
        $rows = [['CA', number_format($b['v'], 0, ',', ' ') . ' ' . $suffix]];
        if ($b['nb'] !== null) $rows[] = ['Ventes', (string)(int)$b['nb']];
        if ($b['avg'] !== null && $b['avg'] > 0) $rows[] = ['Panier moyen', number_format($b['avg'], 0, ',', ' ') . ' ' . $suffix];
        $rowsJson = json_encode($rows, JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP);
        echo '<rect x="' . ($b['cx'] - $slotW/2) . '" y="' . $padT . '" width="' . $slotW . '" height="' . $gh . '" fill="transparent" style="cursor:pointer;" onmouseover="barShowTip(this,\'' . $cid . '_wrap\',\'' . e($b['l']) . '\',\'' . $rowsJson . '\')" onmouseout="barHideTip(\'' . $cid . '_wrap\')"/>';
    }

    echo '</svg>';
    echo '</div>';
}

/** Convertit hex -> "r,g,b" */
function hex2rgb(string $hex): string {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    return "{$r},{$g},{$b}";
}
