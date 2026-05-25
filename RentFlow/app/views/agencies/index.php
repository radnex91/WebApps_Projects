<?php
$title = 'Agences';
$content = '
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3"><i class="bi bi-building"></i> Agences</h1>
        <a href="' . BASE_URL . '/agencies/create" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Ajouter</a>
    </div>
    <div class="card">
        <div class="card-body">
            <table class="table table-hover datatable">
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Contact</th>
                        <th>Lots</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>';
                    foreach ($agencies as $agency) {
                        $content .= '<tr>
                            <td>' . htmlspecialchars($agency['name']) . '</td>
                            <td>' . htmlspecialchars(substr($agency['contact'] ?? '', 0, 40)) . '...</td>
                            <td><span class="badge bg-info">' . $agency['batches_count'] . '</span></td>
                            <td>
                                <a href="' . BASE_URL . '/agencies/' . $agency['id'] . '/edit" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i></a>
                                <form method="POST" action="' . BASE_URL . '/agencies/' . $agency['id'] . '/delete" style="display:inline;">
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
