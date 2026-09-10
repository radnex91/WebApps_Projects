<?php
declare(strict_types=1);
/**
 * Helper de pagination côté serveur.
 *
 * renderPagination(int $page, int $perPage, int $total, array $keep)
 *   Génère les liens de pagination en préservant les paramètres de filtre
 *   passés dans $keep (ex: ['dateDebut'=>'2026-01-01','mode'=>'espèces']).
 *   Retourne le HTML (à echo) ; chaîne vide si une seule page.
 */

function paginateOffset(int $page, int $perPage): int {
    return max(0, ($page - 1) * $perPage);
}

function renderPagination(int $page, int $perPage, int $total, array $keep = [], string $pageParam = 'page'): string {
    if ($total <= 0 || $perPage <= 0) return '';
    $pages = (int)ceil($total / $perPage);
    if ($pages <= 1) return '';

    $page = max(1, min($page, $pages));

    // Construit la query string de base (filtres conservés, sans le param de page)
    $base = $keep;
    unset($base[$pageParam]);
    $qs = http_build_query($base);

    $link = function(int $p) use ($qs, $pageParam): string {
        return '?' . ($qs !== '' ? $qs . '&' : '') . $pageParam . '=' . $p;
    };

    $html = '<div class="pagination" style="display:flex;gap:6px;align-items:center;justify-content:center;padding:14px 0;flex-wrap:wrap;">';

    // Précédent
    if ($page > 1) {
        $html .= '<a href="' . $link($page - 1) . '" class="btn btn-ghost btn-xs">‹ Précédent</a>';
    } else {
        $html .= '<span class="btn btn-ghost btn-xs" style="opacity:.4;pointer-events:none;">‹ Précédent</span>';
    }

    // Numéros de pages (fenêtre de 5 autour de la page courante)
    $start = max(1, $page - 2);
    $end = min($pages, $page + 2);
    if ($start > 1) {
        $html .= '<a href="' . $link(1) . '" class="btn btn-ghost btn-xs">1</a>';
        if ($start > 2) $html .= '<span style="padding:0 4px;color:var(--text3);">…</span>';
    }
    for ($p = $start; $p <= $end; $p++) {
        if ($p === $page) {
            $html .= '<span class="btn btn-primary btn-xs" style="pointer-events:none;">' . $p . '</span>';
        } else {
            $html .= '<a href="' . $link($p) . '" class="btn btn-ghost btn-xs">' . $p . '</a>';
        }
    }
    if ($end < $pages) {
        if ($end < $pages - 1) $html .= '<span style="padding:0 4px;color:var(--text3);">…</span>';
        $html .= '<a href="' . $link($pages) . '" class="btn btn-ghost btn-xs">' . $pages . '</a>';
    }

    // Suivant
    if ($page < $pages) {
        $html .= '<a href="' . $link($page + 1) . '" class="btn btn-ghost btn-xs">Suivant ›</a>';
    } else {
        $html .= '<span class="btn btn-ghost btn-xs" style="opacity:.4;pointer-events:none;">Suivant ›</span>';
    }

    $html .= '</div>';
    return $html;
}