<?php
session_start();
require_once 'config.php';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (isset($_GET['ordem_id'])) {
    $ordem_id = (int)$_GET['ordem_id'];

    // Buscar o caminho do PDF no banco
    $stmt = $pdo->prepare("SELECT numero_ordem, caminho_pdf FROM ordens_servico WHERE id = ?");
    $stmt->execute([$ordem_id]);
    $ordem = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($ordem && file_exists($ordem['caminho_pdf'])) {
        $caminho_arquivo = $ordem['caminho_pdf'];
        $nome_arquivo = "Ordem_Servico_{$ordem['numero_ordem']}.pdf";

        // Enviar o arquivo para download
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $nome_arquivo . '"');
        header('Content-Length: ' . filesize($caminho_arquivo));
        readfile($caminho_arquivo);
        exit;
    } else {
        die("PDF não encontrado para a ordem de serviço ID: $ordem_id.");
    }
} else {
    die("Nenhum ID de ordem de serviço fornecido.");
}
?>