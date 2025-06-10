<?php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    try {
        // Remover arquivo físico
        if (file_exists($data['foto_path'])) {
            unlink($data['foto_path']);
        }

        // Atualizar banco de dados
        $stmt = $pdo->prepare("UPDATE clientes SET fotos = JSON_REMOVE(fotos, JSON_UNQUOTE(JSON_SEARCH(fotos, 'one', ?))) WHERE id = (SELECT cliente_id FROM orcamentos WHERE id = ?)");
        $stmt->execute([$data['foto_path'], $data['orcamento_id']]);

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}