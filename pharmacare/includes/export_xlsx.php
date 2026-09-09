<?php
declare(strict_types=1);
/**
 * Export Excel (.xlsx) — générateur minimaliste 100 % PHP (aucune extension).
 *
 * Pourquoi pas PhpSpreadsheet ni ZipArchive ? L'hébergement cible (XAMPP on-premise)
 * n'a pas toujours composer ni l'extension zip activée. On écrit donc le conteneur
 * ZIP nous-même en mode "stored" (sans compression) : XLSX l'accepte tel quel,
 * Excel/LibreOffice/Google Sheets ouvrent le fichier normalement. Le CRC32 est
 * fourni par hash('crc32b', …), présent dans le cœur de PHP.
 *
 * Usage :
 *   require_once __DIR__ . '/export_xlsx.php';
 *   export_xlsx_send('medicaments', 'Médicaments',
 *       ['Référence', 'Nom', 'Stock'],
 *       [['MED-001', 'Paracétamol', 120], ...]);
 *   // → envoie le fichier et termine la requête (exit).
 *
 * La première ligne (en-têtes) est figée, en gras sur fond de marque ;
 * les colonnes sont dimensionnées automatiquement.
 */

/**
 * Envoie une feuille XLSX au navigateur puis termine la requête.
 *
 * @param string $filename  Nom de fichier sans extension (assaini).
 * @param string $sheetName Nom de l'onglet (31 car. max, sans \ / ? * [ ] :).
 * @param array  $headers   En-têtes de colonnes (chaînes).
 * @param array  $rows      Lignes : tableaux de scalaires (null => cellule vide).
 *                           Nombres int/float → cellules numériques Excel.
 * @param bool   $audit     true (défaut) : trace l'export dans le journal d'audit.
 */
function export_xlsx_send(string $filename, string $sheet, array $headers, array $rows, bool $audit = true): void
{
    // ── Assainissement du nom de fichier et de l'onglet ─────────────────────
    $base = preg_replace('/[^A-Za-z0-9._-]+/', '_', $filename);
    $base = trim($base, '._-');
    if ($base === '') $base = 'export';
    $sheet = preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', mb_substr(trim($sheet), 0, 31));
    if ($sheet === null || $sheet === '') $sheet = 'Export';

    // ── Échappement XML ─────────────────────────────────────────────────────
    $xml = static function ($v): string {
        return htmlspecialchars((string)$v, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    };

    // ── Colonnes : largeur auto (échantillon 300 lignes) ────────────────────
    $nCols = max(1, count($headers));
    $widths = [];
    foreach ($headers as $i => $h) {
        $widths[$i] = mb_strlen((string)$h) + 4;
    }
    $sample = array_slice($rows, 0, 300);
    foreach ($sample as $row) {
        foreach ($row as $i => $v) {
            if ($i >= $nCols || !is_scalar($v)) continue;
            $len = mb_strlen((string)$v) + 2;
            if ($len > $widths[$i]) $widths[$i] = $len;
        }
    }
    $colsXml = '';
    foreach ($widths as $i => $w) {
        $w = min(60.0, max(10.0, (float)$w));
        $c = $i + 1;
        $colsXml .= '<col min="' . $c . '" max="' . $c . '" width="' . $w . '" customWidth="1"/>';
    }

    // ── Lignes de la feuille ────────────────────────────────────────────────
    $body = '';
    $n = 1;
    // Ligne d'en-têtes (style s=1 : gras, fond de marque)
    $body .= '<row r="' . $n . '">';
    foreach ($headers as $i => $h) {
        $col = export_xlsx_col($i);
        $body .= '<c r="' . $col . $n . '" t="inlineStr" s="1"><is><t xml:space="preserve">' . $xml($h) . '</t></is></c>';
    }
    $body .= '</row>';
    foreach ($rows as $row) {
        $n++;
        $body .= '<row r="' . $n . '">';
        for ($i = 0; $i < $nCols; $i++) {
            $v = $row[$i] ?? null;
            $col = export_xlsx_col($i);
            if ($v === null || $v === '') { $body .= '<c r="' . $col . $n . '"/>'; continue; }
            if (is_int($v) || is_float($v)) {
                $body .= '<c r="' . $col . $n . '"><v>' . (string)$v . '</v></c>';
            } else {
                $body .= '<c r="' . $col . $n . '" t="inlineStr"><is><t xml:space="preserve">' . $xml($v) . '</t></is></c>';
            }
        }
        $body .= '</row>';
    }

    $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<dimension ref="A1:' . export_xlsx_col($nCols - 1) . max(1, $n) . '"/>'
        . '<sheetViews><sheetView workbookViewId="0" tabSelected="1">'
        . '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
        . '<selection pane="bottomLeft" activeCell="A2" sqref="A2"/>'
        . '</sheetView></sheetViews>'
        . '<sheetFormatPr defaultRowHeight="15"/>'
        . '<cols>' . $colsXml . '</cols>'
        . '<sheetData>' . $body . '</sheetData>'
        . '<pageMargins left="0.5" right="0.5" top="0.6" bottom="0.6" header="0.3" footer="0.3"/>'
        . '</worksheet>';

    // ── Pièces du paquet OPC ────────────────────────────────────────────────
    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '</Types>';

    $rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';

    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<bookViews><workbookView/></bookViews>'
        . '<sheets><sheet name="' . $xml($sheet) . '" sheetId="1" r:id="rId1"/></sheets>'
        . '</workbook>';

    $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>';

    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="2">'
        . '<font><sz val="11"/><color rgb="FF1F2937"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
        . '</fonts>'
        . '<fills count="3">'
        . '<fill><patternFill patternType="none"/></fill>'
        . '<fill><patternFill patternType="gray125"/></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FF0F766E"/><bgColor indexed="64"/></patternFill></fill>'
        . '</fills>'
        . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="2">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
        . '</cellXfs>'
        . '</styleSheet>';

    // ── Conteneur ZIP (stored, CRC32 via hash — aucune extension requise) ────
    $entries = [
        ['[Content_Types].xml',        $contentTypes],
        ['_rels/.rels',                $rootRels],
        ['xl/workbook.xml',            $workbook],
        ['xl/_rels/workbook.xml.rels', $wbRels],
        ['xl/styles.xml',              $styles],
        ['xl/worksheets/sheet1.xml',   $sheetXml],
    ];

    // Date DOS fixe valide (2024-01-01 00:00) — inutile d'aller plus fin.
    $dosDate = ((2024 - 1980) << 9) | (1 << 5) | 1;

    $local = ''; $central = ''; $offset = 0;
    foreach ($entries as [$name, $data]) {
        $crc  = unpack('N', hash('crc32b', $data, true))[1];
        $size = strlen($data);
        $hdr  = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, 0, $dosDate, $crc, $size, $size, strlen($name), 0)
              . $name;
        $local   .= $hdr . $data;
        $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 0, 0, $dosDate, $crc, $size, $size,
                            strlen($name), 0, 0, 0, 0, 0, $offset)
                  . $name;
        $offset += strlen($hdr) + $size;
    }
    $zip = $local . $central
         . pack('VvvvvVVv', 0x06054b50, 0, 0, count($entries), count($entries), strlen($central), $offset, 0);

    // ── Audit + en-têtes HTTP + flux ─────────────────────────────────────────
    if ($audit) {
        try { auditLog('export', 'Liste « ' . $sheet . ' » (' . count($rows) . ' ligne(s)) — ' . $base . '.xlsx'); } catch (Throwable $e) { /* non bloquant */ }
    }
    while (ob_get_level() > 0) { @ob_end_clean(); }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $base . '.xlsx"; filename*=UTF-8\'\'' . rawurlencode($base . '.xlsx'));
    header('Content-Length: ' . strlen($zip));
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    echo $zip;
    exit;
}

/** Index 0-based → lettre(s) de colonne Excel (0=A, 25=Z, 26=AA…). */
function export_xlsx_col(int $i): string
{
    $s = '';
    $i++;
    while ($i > 0) {
        $i--;
        $s = chr(65 + ($i % 26)) . $s;
        $i = intdiv($i, 26);
    }
    return $s;
}