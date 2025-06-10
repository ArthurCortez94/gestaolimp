<?php
require_once 'config.php';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

if (!isset($_POST['orcamento_id'])) {
    echo json_encode(['error' => 'ID do orçamento não fornecido']);
    exit;
}

$orcamento_id = (int)$_POST['orcamento_id'];

try {
    $stmt = $pdo->prepare("
        SELECT o.*, c.* 
        FROM orcamentos o 
        JOIN clientes c ON o.cliente_id = c.id 
        WHERE o.id = ?
    ");
    $stmt->execute([$orcamento_id]);
    $orcamento = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$orcamento) {
        echo json_encode(['error' => 'Orçamento não encontrado']);
        exit;
    }

    $servicos = json_decode($orcamento['descricao_servico'], true) ?: [];

    $response = [
        'tipo_cliente' => $orcamento['tipo_cliente'],
        'nome' => $orcamento['nome'],
        'razao_social' => $orcamento['razao_social'] ?? null,
        'nome_fantasia' => $orcamento['nome_fantasia'] ?? null,
        'cnpj_cpf' => $orcamento['cnpj_cpf'],
        'endereco' => $orcamento['endereco'],
        'numero' => $orcamento['numero'],
        'complemento' => $orcamento['complemento'] ?? null,
        'bairro' => $orcamento['bairro'],
        'cidade' => $orcamento['cidade'],
        'uf' => $orcamento['uf'],
        'cep' => $orcamento['cep'],
        'telefone' => $orcamento['telefone'],
        'email' => $orcamento['email'] ?? null,
        'servicos' => $servicos,
        'total' => $orcamento['total'],
        'forma_pagamento' => $orcamento['forma_pagamento']
    ];

    echo json_encode($response);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Erro ao buscar dados: ' . $e->getMessage()]);
}
exit;