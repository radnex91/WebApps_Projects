<?php
/**
 * Export CSV / Excel (HTML-based XLS)
 * Usage: Call exportData($rows, $headers, $filename, $format) before any HTML output.
 *        $format = 'csv' | 'excel'
 *        $rows is an array of associative arrays (DB results).
 */

function exportData(array $rows, array $headers, string $filename, string $format = 'csv'): void {
    $filename = preg_replace('/[^A-Za-z0-9_\-]/', '_', $filename);

    if ($format === 'csv') {
        exportCSV($rows, $headers, $filename);
    } else {
        exportExcel($rows, $headers, $filename);
    }
    exit;
}

function exportCSV(array $rows, array $headers, string $filename): void {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');

    $out = fopen('php://output', 'w');
    // BOM for Excel UTF-8 detection
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, array_values($headers), ';');

    foreach ($rows as $row) {
        $line = [];
        foreach ($headers as $key => $label) {
            $line[] = $row[$key] ?? '';
        }
        fputcsv($out, $line, ';');
    }
    fclose($out);
}

function exportExcel(array $rows, array $headers, string $filename): void {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');

    echo '<!DOCTYPE html><html><head><meta charset="UTF-8">';
    echo '<style>';
    echo 'table { border-collapse: collapse; }';
    echo 'th { background: #1a365d; color: #fff; font-weight: 700; padding: 8px 12px; border: 1px solid #0f3060; }';
    echo 'td { padding: 6px 12px; border: 1px solid #cbd5e1; }';
    echo 'tr:nth-child(even) td { background: #f1f5f9; }';
    echo '.amount { text-align: right; font-family: monospace; }';
    echo '</style></head><body>';
    echo '<table>';

    echo '<thead><tr>';
    foreach ($headers as $label) {
        echo '<th>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</th>';
    }
    echo '</tr></thead><tbody>';

    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($headers as $key => $label) {
            $val = $row[$key] ?? '';
            if (is_numeric($val) && !is_string($row[$key] ?? null)) {
                echo '<td class="amount">' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '</td>';
            } else {
                echo '<td>' . htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8') . '</td>';
            }
        }
        echo '</tr>';
    }

    echo '</tbody></table></body></html>';
}

/**
 * Render a dropdown + export button for use in module pages.
 * Pass current GET params to preserve filters.
 */
function exportButtons(array $currentParams = []): string {
    $baseParams = array_merge($currentParams, ['export' => '__FMT__']);
    $csvUrl = '?' . http_build_query(array_merge($currentParams, ['export' => 'csv']));
    $xlsUrl = '?' . http_build_query(array_merge($currentParams, ['export' => 'excel']));

    return '
    <div class="export-buttons" style="display:inline-flex;gap:6px;align-items:center">
        <select class="form-control form-control-sm" id="export-fmt" style="width:100px">
            <option value="csv">CSV</option>
            <option value="excel">Excel</option>
        </select>
        <button class="btn btn-outline btn-sm" onclick="doExport()" title="Exporter">
            <i class="fa-solid fa-file-export"></i> Exporter
        </button>
    </div>
    <script>
    function doExport() {
        var fmt = document.getElementById("export-fmt").value;
        var sep = window.location.search ? "&" : "?";
        window.location = window.location.pathname + window.location.search + sep + "export=" + fmt;
    }
    </script>';
}
