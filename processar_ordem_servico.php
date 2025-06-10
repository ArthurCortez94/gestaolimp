<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        $orcamento_id = (int)$_POST['orcamento_id'];
        $descricao = trim($_POST['descricao']);
        $data_servico = $_POST['data_servico'];
        $hora_servico = $_POST['hora_servico'];
        $tecnico_responsavel = trim($_POST['tecnico_responsavel']);

        // Buscar status e valor do orçamento
        $stmt = $pdo->prepare("SELECT status, total FROM orcamentos WHERE id = ?");
        $stmt->execute([$orcamento_id]);
        $orcamento = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$orcamento) {
            die("Erro: Orçamento não encontrado.");
        }

        $status_orcamento = $orcamento['status'];
        $valor_total = $orcamento['total'];

        // Inserir a nova ordem de serviço com status e valor do orçamento
        $stmt = $pdo->prepare("
            INSERT INTO ordens_servico (orcamento_id, descricao, data_servico, hora_servico, tecnico_responsavel, status, valor)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$orcamento_id, $descricao, $data_servico, $hora_servico, $tecnico_responsavel, $status_orcamento, $valor_total]);

        $_SESSION['success'] = "Ordem de serviço criada com sucesso!";
        header("Location: lista_ordens_servico.php");
        exit();
    } catch (PDOException $e) {
        die("Erro ao criar ordem de serviço: " . $e->getMessage());
    }
} else {
    header("Location: lista_orcamentos.php");
    exit();
}
