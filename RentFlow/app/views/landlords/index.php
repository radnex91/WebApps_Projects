<?php
$title = 'Bailleurs';
$content = '
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3"><i class="bi bi-people"></i> Bailleurs</h1>
        <a href="' . BASE_URL . '/landlords/create" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Ajouter</a>
    </div>
    
    <form method="GET" class="mb-3">
        <div class="input-group">
            <input type="text" name="search" class="form-control" placeholder="Rechercher..." value="' . htmlspecialchars($search) . '">
            <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
            ' . ($search ? '<a href="' . BASE_URL . '/landlords" class="btn btn-outline-danger"><i class="bi bi-x"></i></a>' : '') . '
        </div>
    </form>

    <div class="card">
        <div class="card-body">
            <table class="table table-hover datatable">
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Contact</th>
                        <th>Agence(s)</th>
                        <th>Lots</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>';
                    foreach ($landlords as $landlord) {
                        $content .= '<tr>
                            <td>' . htmlspecialchars($landlord['name']) . '</td>
                            <td>' . htmlspecialchars(substr($landlord['contact'] ?? '', 0, 40)) . '...</td>
                            <td><small>' . htmlspecialchars($landlord['agency_names'] ?? 'Aucune') . '</small></td>
                            <td><span class="badge bg-info">' . $landlord['batches_count'] . '</span></td>
                            <td>
                                <a href="' . BASE_URL . '/landlords/' . $landlord['id'] . '/edit" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i></a>
                                <form method="POST" action="' . BASE_URL . '/landlords/' . $landlord['id'] . '/delete" style="display:inline;">
                                    ' . Csrf::field() . '
                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm(\'Confirmer la suppression ?\')"><i class="bi bi-trash"></i></button>
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
