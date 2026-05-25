<?php
$title = 'Lots';
$content = '
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3"><i class="bi bi-grid"></i> Lots</h1>
        <a href="' . BASE_URL . '/batches/create" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Ajouter</a>
    </div>
    <div class="card">
        <div class="card-body">
            <table class="table table-hover datatable">
                <thead>
                    <tr>
                        <th>Code lot</th>
                        <th>Bailleur</th>
                        <th>Agence</th>
                        <th>Mensualité</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>';
                    foreach ($batches as $batch) {
                        $content .= '<tr>
                            <td>' . htmlspecialchars($batch['name']) . '</td>
                            <td>' . htmlspecialchars($batch['landlord_name']) . '</td>
                            <td>' . htmlspecialchars($batch['agency_name']) . '</td>
                            <td><strong>' . formatCurrency($batch['monthly_price'] ?? 0) . '</strong></td>
                            <td>
                                <a href="' . BASE_URL . '/batches/' . $batch['id'] . '/edit" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i></a>
                                <form method="POST" action="' . BASE_URL . '/batches/' . $batch['id'] . '/delete" style="display:inline;">
                                    ' . Csrf::field() . '
                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm(\'Confirmer ?\')"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>';
                    }
                $content .= '</tbody>
            </table>
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center">';
                    for ($i = 1; $i <= $totalPages; $i++) {
                        $active = $i == $page ? 'active' : '';
                        $content .= '<li class="page-item ' . $active . '"><a class="page-link" href="?page=' . $i . '">' . $i . '</a></li>';
                    }
                $content .= '</ul>
            </nav>
        </div>
    </div>
</div>';
require __DIR__ . '/../layouts/main.php';
