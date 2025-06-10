<?php
session_start();
require 'config.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: clientes.php");
    exit();
}

try {
    $pdo = new PDO(
        "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8",
        DB_USER, 
        DB_PASS
    );

    $cliente = $pdo->query("
        SELECT * FROM clientes 
        WHERE id = " . $pdo->quote($_GET['id'])
    )->fetch();

    $historico = $pdo->query("
        SELECT s.*, t.nome AS tecnico 
        FROM servicos s
        LEFT JOIN tecnicos t ON s.tecnico_id = t.id
        WHERE s.cliente_id = " . $pdo->quote($_GET['id']) . "
        ORDER BY s.data_servico DESC
    ")->fetchAll();

} catch(PDOException $e) {
    die("Erro: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Histórico - <?= $cliente['nome'] ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <h2 class="mb-4">Histórico de Serviços</h2>
        <div class="card">
            <div class="card-header">
                <h4><?= htmlspecialchars($cliente['nome']) ?></h4>
                <small><?= $cliente['email'] ?></small>
            </div>
            
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Serviço</th>
                            <th>Valor</th>
                            <th>Técnico</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($historico as $h): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($h['data_servico'])) ?></td>
                            <td><?= htmlspecialchars($h['descricao']) ?></td>
                            <td>R$ <?= number_format($h['valor'], 2, ',', '.') ?></td>
                            <td><?= htmlspecialchars($h['tecnico']) ?? 'N/A' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
