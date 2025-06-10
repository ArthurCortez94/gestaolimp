<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

try {
    $pdo = new PDO(
        "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8",
        DB_USER, 
        DB_PASS
    );

    $estoque = $pdo->query("
        SELECT * FROM estoque 
        ORDER BY quantidade ASC
    ")->fetchAll();

} catch(PDOException $e) {
    die("Erro: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Gestão de Estoque</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <div class="d-flex justify-content-between mb-4">
            <h2>Controle de Estoque</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAdicionar">
                <i class="fas fa-plus"></i> Novo Item
            </button>
        </div>

        <div class="card">
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Quantidade</th>
                            <th>Estoque Mínimo</th>
                            <th>Última Reposição</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($estoque as $e): ?>
                        <tr class="<?= ($e['quantidade'] < $e['estoque_minimo']) ? 'table-warning' : '' ?>">
                            <td><?= htmlspecialchars($e['item']) ?></td>
                            <td><?= $e['quantidade'] ?> <?= $e['unidade_medida'] ?></td>
                            <td><?= $e['estoque_minimo'] ?></td>
                            <td><?= $e['ultima_reposicao'] ? date('d/m/Y', strtotime($e['ultima_reposicao'])) : 'N/A' ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Adicionar -->
    <div class="modal fade" id="modalAdicionar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Novo Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="inventory_action.php">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Nome do Item</label>
                            <input type="text" name="item" class="form-control" required>
                        </div>
                        <div class="row">
                            <div class="col-6">
                                <label>Quantidade</label>
                                <input type="number" name="quantidade" class="form-control" required>
                            </div>
                            <div class="col-6">
                                <label>Estoque Mínimo</label>
                                <input type="number" name="estoque_minimo" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
