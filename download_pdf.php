<?php
require_once 'config.php';

$numero_ordem = $_GET['numero_ordem'] ?? '';
if ($numero_ordem) {
    $stmt = $pdo->prepare("SELECT caminho_pdf FROM ordens_servico WHERE numero_ordem = ?");
    $stmt->execute([$numero_ordem]);
    $caminho_pdf = $stmt->fetchColumn();

    if ($caminho_pdf && file_exists($caminho_pdf)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($caminho_pdf) . '"');
        readfile($caminho_pdf);
        exit;
    } else {
        die("PDF não encontrado.");
    }
}
die("Número da ordem inválido.");
?>