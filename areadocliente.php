<?php
session_start();
require_once 'config.php';

// Função para exibir PDF no navegador (MANTIDA INALTERADA)
function exibirPDFOrdem(string $caminho_pdf): void {
    if (file_exists(__DIR__ . $caminho_pdf)) {
        header('Content-Type: application/pdf');
        readfile(__DIR__ . $caminho_pdf);
        exit;
    } else {
        die("PDF não encontrado no servidor.");
    }
}

$error = '';
$ordens = [];
$lavanderia = [];
$historico = [];
$comentarios = [];
$por_pagina = 5;
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina - 1) * $por_pagina;

// Verifica login do cliente
if (isset($_POST['telefone'])) {
    $telefone = preg_replace('/[^0-9]/', '', $_POST['telefone']);
    try {
        $stmt = $pdo->prepare("SELECT * FROM clientes WHERE telefone = ?");
        $stmt->execute([$telefone]);
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($cliente) {
            $_SESSION['cliente_id'] = $cliente['id'];
            $_SESSION['cliente_nome'] = $cliente['nome'];
        } else {
            $error = "Telefone não encontrado. Verifique o número digitado.";
        }
    } catch (PDOException $e) {
        $error = "Erro ao verificar telefone: " . $e->getMessage();
    }
}

// Processar novo comentário
if (isset($_SESSION['cliente_id']) && isset($_POST['adicionar_comentario'])) {
    $comentario = trim($_POST['comentario']);
    if (!empty($comentario)) {
        $stmt = $pdo->prepare("INSERT INTO comentarios_clientes (cliente_id, comentario) VALUES (?, ?)");
        $stmt->execute([$_SESSION['cliente_id'], $comentario]);
        header("Location: " . $_SERVER['PHP_SELF'] . "?pagina=$pagina");
        exit;
    } else {
        $error = "O comentário não pode estar vazio.";
    }
}

// Carregar dados do cliente logado
if (isset($_SESSION['cliente_id'])) {
    $cliente_id = $_SESSION['cliente_id'];

    // Contar total de ordens para paginação
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ordens_servico WHERE cliente_id = ?");
    $stmt->execute([$cliente_id]);
    $total_ordens = $stmt->fetchColumn();
    $total_paginas = ceil($total_ordens / $por_pagina);

    // Carregar ordens de serviço
    $stmt = $pdo->prepare("
        SELECT 
            os.id,
            os.numero_ordem,
            os.data_emissao,
            os.data_servico,
            os.hora_servico,
            os.previsao_conclusao,
            os.observacoes,
            os.status,
            os.descricao_servicos,
            os.total,
            u.nome AS tecnico_nome,
            IFNULL(o.total, 0) AS orcamento_total,
            os.caminho_pdf
        FROM ordens_servico os
        LEFT JOIN orcamentos o ON os.orcamento_id = o.id
        LEFT JOIN usuarios u ON os.tecnico_id = u.id
        WHERE os.cliente_id = ?
        ORDER BY os.data_emissao DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$cliente_id, $por_pagina, $offset]);
    $ordens = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Carregar itens na lavanderia
    $stmt = $pdo->prepare("
        SELECT id, nome_item, quantidade, status, data_prevista_entrega, data_entrega
        FROM lavanderia
        WHERE cliente_id = ?
        ORDER BY data_prevista_entrega ASC
    ");
    $stmt->execute([$cliente_id]);
    $lavanderia = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Carregar histórico de serviços concluídos
    $stmt = $pdo->prepare("
        SELECT 
            os.id,
            os.numero_ordem,
            os.data_emissao,
            os.data_servico,
            os.hora_servico,
            os.previsao_conclusao,
            os.observacoes,
            os.status,
            os.descricao_servicos,
            os.total,
            u.nome AS tecnico_nome,
            IFNULL(o.total, 0) AS orcamento_total,
            os.caminho_pdf
        FROM ordens_servico os
        LEFT JOIN orcamentos o ON os.orcamento_id = o.id
        LEFT JOIN usuarios u ON os.tecnico_id = u.id
        WHERE os.cliente_id = ? AND os.status = 'Concluída'
        ORDER BY os.data_emissao DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$cliente_id, $por_pagina, $offset]);
    $historico = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Carregar comentários
    $stmt = $pdo->prepare("
        SELECT comentario, data_comentario
        FROM comentarios_clientes
        WHERE cliente_id = ?
        ORDER BY data_comentario DESC
    ");
    $stmt->execute([$cliente_id]);
    $comentarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Exibir PDF se solicitado (MANTIDO INALTERADO)
    if (isset($_GET['exibir_pdf']) && isset($_GET['ordem_id'])) {
        $ordem_id = (int)$_GET['ordem_id'];
        $stmt = $pdo->prepare("SELECT caminho_pdf FROM ordens_servico WHERE id = ? AND cliente_id = ?");
        $stmt->execute([$ordem_id, $cliente_id]);
        $caminho_pdf = $stmt->fetchColumn();
        if ($caminho_pdf) {
            exibirPDFOrdem($caminho_pdf);
        } else {
            header("Location: gerar_pdf_os.php?id=$ordem_id");
            exit;
        }
    }
}

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel do Cliente - UltraLimp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #2563EB;
            --secondary: #1D4ED8;
            --accent: #F59E0B;
            --light: #F9FAFB;
            --dark: #111827;
            --gray: #6B7280;
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            --header-gradient: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            --status-aberto: #17A2B8;
            --status-agendado: #007BFF;
            --status-andamento: #FFC107;
            --status-concluido: #28A745;
            --status-atrasado: #DC3545;
            --status-cancelado: #6C757D;
        }
        body { font-family: 'Inter', sans-serif; background: var(--light); padding: 20px; }
        .dashboard-card { background: white; border-radius: 12px; box-shadow: var(--shadow); padding: 1.5rem; margin-bottom: 1.5rem; border: 1px solid rgba(203, 213, 225, 0.3); }
        .header-section { background: var(--header-gradient); color: white; padding: 1.5rem; border-radius: 12px; margin-bottom: 2rem; text-align: center; }
        .btn-primary { background-color: var(--primary); border-color: var(--primary); padding: 0.8rem 1.5rem; border-radius: 8px; transition: all 0.3s ease; }
        .btn-primary:hover { background-color: var(--secondary); transform: translateY(-2px); }
        .btn-success { background-color: #28A745; border-color: #28A745; padding: 0.5rem 1rem; border-radius: 8px; }
        .btn-success:hover { background-color: #218838; }
        .btn-secondary { background-color: var(--gray); border-color: var(--gray); padding: 0.5rem 1rem; border-radius: 8px; }
        .table-custom { width: 100%; border-collapse: collapse; margin: 15px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .table-custom th, .table-custom td { padding: 8px; border: 1px solid #e0e0e0; text-align: left; }
        .table-custom th { background-color: #ecf0f1; font-weight: bold; color: #2c3e50; font-size: 13px; }
        .table-custom tbody tr:hover { background: #f8fafc; cursor: pointer; }
        .status-badge { padding: 0.3rem 1rem; border-radius: 20px; font-size: 0.85rem; font-weight: 500; }
        .status-aberto { background-color: var(--status-aberto); color: white; }
        .status-agendado { background-color: var(--status-agendado); color: white; }
        .status-andamento { background-color: var(--status-andamento); color: black; }
        .status-concluido { background-color: var(--status-concluido); color: white; }
        .status-atrasado { background-color: var(--status-atrasado); color: white; }
        .status-cancelado { background-color: var(--status-cancelado); color: white; }
        .pagination { justify-content: center; margin-top: 1.5rem; }
        .page-link { color: var(--primary); }
        .page-item.active .page-link { background-color: var(--primary); border-color: var(--primary); color: white; }
        .modal-content { border-radius: 12px; }
        .modal-header { background: var(--primary); color: white; }
        @media (max-width: 768px) {
            .dashboard-card { padding: 1rem; }
            .table-custom { display: block; overflow-x: auto; white-space: nowrap; }
            .table-custom th, .table-custom td { padding: 6px; font-size: 0.9rem; }
            .modal-dialog { max-width: 90%; margin: 1rem auto; }
            .btn { padding: 0.4rem 0.8rem; font-size: 0.9rem; }
        }
        @media (max-width: 480px) {
            .header-section { padding: 1rem; }
            .table-custom th, .table-custom td { font-size: 0.8rem; }
            .modal-dialog { margin: 0.5rem; }
        }
    </style>
</head>
<body>

<div class="container-fluid">
    <!-- Cabeçalho -->
    <div class="header-section">
        <h2 class="mb-0"><i class="fas fa-user me-2"></i>Painel do Cliente</h2>
    </div>

    <!-- Boas-vindas -->
    <div class="dashboard-card">
        <h4 class="section-title">Bem-vindo ao Painel do Cliente</h4>
        <p>Acompanhe seus serviços e itens em tempo real. Faça login com seu telefone para acessar suas informações personalizadas.</p>
        <?php if (isset($_SESSION['cliente_id'])): ?>
            <p>Bem-vindo, <strong><?= htmlspecialchars($_SESSION['cliente_nome']) ?></strong>! <a href="logout_cliente.php" class="btn btn-secondary btn-sm">Sair</a></p>
        <?php endif; ?>
    </div>

    <!-- Formulário de Login -->
    <?php if (!isset($_SESSION['cliente_id'])): ?>
        <div class="dashboard-card">
            <h4><i class="fas fa-phone me-2"></i>Acesse com seu Telefone</h4>
            <form method="POST">
                <div class="mb-3">
                    <label for="telefone" class="form-label">Número de Telefone</label>
                    <input type="text" class="form-control" id="telefone" name="telefone" placeholder="(XX) XXXXX-XXXX" required>
                </div>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <button type="submit" class="btn btn-primary">Acessar</button>
            </form>
        </div>
    <?php endif; ?>

    <!-- Área do Cliente Logado -->
    <?php if (isset($_SESSION['cliente_id'])): ?>
        <!-- Ordens de Serviço -->
        <div class="dashboard-card">
            <h4><i class="fas fa-tools me-2"></i>Ordens de Serviço</h4>
            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Nº Ordem</th>
                            <th>Emissão</th>
                            <th>Serviço</th>
                            <th>Hora</th>
                            <th>Técnico</th>
                            <th>Status</th>
                            <th>Valor</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ordens)): ?>
                            <tr><td colspan="8" class="text-center py-4">Nenhuma ordem cadastrada</td></tr>
                        <?php else: ?>
                            <?php foreach ($ordens as $ordem): ?>
                                <tr data-bs-toggle="modal" data-bs-target="#ordemModal<?= $ordem['id'] ?>">
                                    <td><?= htmlspecialchars($ordem['numero_ordem']) ?></td>
                                    <td><?= date('d/m/Y', strtotime($ordem['data_emissao'])) ?></td>
                                    <td><?= $ordem['data_servico'] ? date('d/m/Y', strtotime($ordem['data_servico'])) : 'N/A' ?></td>
                                    <td><?= $ordem['hora_servico'] ?? 'N/A' ?></td>
                                    <td><?= htmlspecialchars($ordem['tecnico_nome'] ?? 'Não atribuído') ?></td>
                                    <td><span class="status-badge status-<?= strtolower(str_replace(' ', '-', $ordem['status'])) ?>"><?= htmlspecialchars($ordem['status']) ?></span></td>
                                    <td>R$ <?= number_format($ordem['orcamento_total'], 2, ',', '.') ?></td>
                                    <td>
                                        <?php if ($ordem['caminho_pdf']): ?>
                                            <a href="<?= htmlspecialchars($ordem['caminho_pdf']) ?>" class="btn btn-success btn-sm" target="_blank">Ver PDF</a>
                                        <?php else: ?>
                                            <a href="?exibir_pdf=1&ordem_id=<?= $ordem['id'] ?>" class="btn btn-success btn-sm" target="_blank">Ver PDF</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <!-- Modal com Detalhes -->
                                <div class="modal fade" id="ordemModal<?= $ordem['id'] ?>" tabindex="-1" aria-labelledby="ordemModalLabel<?= $ordem['id'] ?>" aria-hidden="true">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="ordemModalLabel<?= $ordem['id'] ?>">Detalhes da Ordem <?= htmlspecialchars($ordem['numero_ordem']) ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p><strong>Data de Emissão:</strong> <?= date('d/m/Y', strtotime($ordem['data_emissao'])) ?></p>
                                                <p><strong>Data do Serviço:</strong> <?= $ordem['data_servico'] ? date('d/m/Y', strtotime($ordem['data_servico'])) : 'N/A' ?></p>
                                                <p><strong>Hora do Serviço:</strong> <?= $ordem['hora_servico'] ?? 'N/A' ?></p>
                                                <p><strong>Técnico:</strong> <?= htmlspecialchars($ordem['tecnico_nome'] ?? 'Não atribuído') ?></p>
                                                <p><strong>Status:</strong> <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $ordem['status'])) ?>"><?= htmlspecialchars($ordem['status']) ?></span></p>
                                                <p><strong>Valor Total:</strong> R$ <?= number_format($ordem['orcamento_total'], 2, ',', '.') ?></p>
                                                <p><strong>Observações:</strong> <?= htmlspecialchars($ordem['observacoes'] ?? 'Nenhuma observação') ?></p>
                                                <h6>Serviços:</h6>
                                                <ul>
                                                    <?php foreach (json_decode($ordem['descricao_servicos'], true) ?: [] as $servico): ?>
                                                        <li><?= htmlspecialchars($servico['nome'] ?? '') ?> - Qtd: <?= $servico['quantidade'] ?? 0 ?> - R$ <?= number_format($servico['valor_unitario'] ?? 0, 2, ',', '.') ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($total_paginas > 1): ?>
                <nav aria-label="Paginação de Ordens">
                    <ul class="pagination">
                        <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?pagina=<?= $pagina - 1 ?>" aria-label="Anterior">
                                <span aria-hidden="true">«</span>
                            </a>
                        </li>
                        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                            <li class="page-item <?= $i == $pagina ? 'active' : '' ?>">
                                <a class="page-link" href="?pagina=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $pagina >= $total_paginas ? 'disabled' : '' ?>">
                            <a class="page-link" href="?pagina=<?= $pagina + 1 ?>" aria-label="Próximo">
                                <span aria-hidden="true">»</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>

        <!-- Itens na Lavanderia -->
        <div class="dashboard-card">
            <h4><i class="fas fa-tshirt me-2"></i>Itens na Lavanderia</h4>
            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Quantidade</th>
                            <th>Status</th>
                            <th>Previsão</th>
                            <th>Entrega</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lavanderia)): ?>
                            <tr><td colspan="5" class="text-center py-4">Nenhum item na lavanderia</td></tr>
                        <?php else: ?>
                            <?php foreach ($lavanderia as $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['nome_item']) ?></td>
                                    <td><?= $item['quantidade'] ?></td>
                                    <td><span class="status-badge status-<?= strtolower(str_replace(' ', '-', $item['status'])) ?>"><?= ucfirst($item['status']) ?></span></td>
                                    <td><?= $item['data_prevista_entrega'] ? date('d/m/Y', strtotime($item['data_prevista_entrega'])) : 'N/A' ?></td>
                                    <td><?= $item['data_entrega'] ? date('d/m/Y', strtotime($item['data_entrega'])) : 'N/A' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Adicionar Comentário -->
        <div class="dashboard-card">
            <h4><i class="fas fa-comment me-2"></i>Adicionar Observação</h4>
            <form method="POST">
                <div class="mb-3">
                    <textarea class="form-control" name="comentario" rows="3" placeholder="Digite sua observação aqui" required></textarea>
                </div>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <button type="submit" name="adicionar_comentario" class="btn btn-primary">
                    <i class="fas fa-paper-plane me-2"></i>Enviar
                </button>
            </form>
            <h5 class="mt-4">Observações Enviadas</h5>
            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Observação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($comentarios)): ?>
                            <tr><td colspan="2" class="text-center py-4">Nenhuma observação enviada</td></tr>
                        <?php else: ?>
                            <?php foreach ($comentarios as $comentario): ?>
                                <tr>
                                    <td><?= date('d/m/Y H:i', strtotime($comentario['data_comentario'])) ?></td>
                                    <td><?= htmlspecialchars($comentario['comentario']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Histórico de Serviços -->
        <div class="dashboard-card">
            <h4><i class="fas fa-history me-2"></i>Histórico de Serviços</h4>
            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Nº Ordem</th>
                            <th>Emissão</th>
                            <th>Serviço</th>
                            <th>Hora</th>
                            <th>Técnico</th>
                            <th>Status</th>
                            <th>Valor</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($historico)): ?>
                            <tr><td colspan="8" class="text-center py-4">Nenhum serviço concluído</td></tr>
                        <?php else: ?>
                            <?php foreach ($historico as $servico): ?>
                                <tr data-bs-toggle="modal" data-bs-target="#historicoModal<?= $servico['id'] ?>">
                                    <td><?= htmlspecialchars($servico['numero_ordem']) ?></td>
                                    <td><?= date('d/m/Y', strtotime($servico['data_emissao'])) ?></td>
                                    <td><?= $servico['data_servico'] ? date('d/m/Y', strtotime($servico['data_servico'])) : 'N/A' ?></td>
                                    <td><?= $servico['hora_servico'] ?? 'N/A' ?></td>
                                    <td><?= htmlspecialchars($servico['tecnico_nome'] ?? 'Não atribuído') ?></td>
                                    <td><span class="status-badge status-<?= strtolower(str_replace(' ', '-', $servico['status'])) ?>"><?= htmlspecialchars($servico['status']) ?></span></td>
                                    <td>R$ <?= number_format($servico['orcamento_total'], 2, ',', '.') ?></td>
                                    <td>
                                        <?php if ($servico['caminho_pdf']): ?>
                                            <a href="<?= htmlspecialchars($servico['caminho_pdf']) ?>" class="btn btn-success btn-sm" target="_blank">Ver PDF</a>
                                        <?php else: ?>
                                            <a href="?exibir_pdf=1&ordem_id=<?= $servico['id'] ?>" class="btn btn-success btn-sm" target="_blank">Ver PDF</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <!-- Modal com Detalhes do Histórico -->
                                <div class="modal fade" id="historicoModal<?= $servico['id'] ?>" tabindex="-1" aria-labelledby="historicoModalLabel<?= $servico['id'] ?>" aria-hidden="true">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="historicoModalLabel<?= $servico['id'] ?>">Detalhes do Serviço <?= htmlspecialchars($servico['numero_ordem']) ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p><strong>Data de Emissão:</strong> <?= date('d/m/Y', strtotime($servico['data_emissao'])) ?></p>
                                                <p><strong>Data do Serviço:</strong> <?= $servico['data_servico'] ? date('d/m/Y', strtotime($servico['data_servico'])) : 'N/A' ?></p>
                                                <p><strong>Hora do Serviço:</strong> <?= $servico['hora_servico'] ?? 'N/A' ?></p>
                                                <p><strong>Técnico:</strong> <?= htmlspecialchars($servico['tecnico_nome'] ?? 'Não atribuído') ?></p>
                                                <p><strong>Status:</strong> <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $servico['status'])) ?>"><?= htmlspecialchars($servico['status']) ?></span></p>
                                                <p><strong>Valor Total:</strong> R$ <?= number_format($servico['orcamento_total'], 2, ',', '.') ?></p>
                                                <p><strong>Observações:</strong> <?= htmlspecialchars($servico['observacoes'] ?? 'Nenhuma observação') ?></p>
                                                <h6>Serviços:</h6>
                                                <ul>
                                                    <?php foreach (json_decode($servico['descricao_servicos'], true) ?: [] as $servico_item): ?>
                                                        <li><?= htmlspecialchars($servico_item['nome'] ?? '') ?> - Qtd: <?= $servico_item['quantidade'] ?? 0 ?> - R$ <?= number_format($servico_item['valor_unitario'] ?? 0, 2, ',', '.') ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($total_paginas > 1): ?>
                <nav aria-label="Paginação de Histórico">
                    <ul class="pagination">
                        <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?pagina=<?= $pagina - 1 ?>" aria-label="Anterior">
                                <span aria-hidden="true">«</span>
                            </a>
                        </li>
                        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                            <li class="page-item <?= $i == $pagina ? 'active' : '' ?>">
                                <a class="page-link" href="?pagina=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $pagina >= $total_paginas ? 'disabled' : '' ?>">
                            <a class="page-link" href="?pagina=<?= $pagina + 1 ?>" aria-label="Próximo">
                                <span aria-hidden="true">»</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Corrige o travamento do modal no mobile
    document.addEventListener('DOMContentLoaded', function () {
        var modals = document.querySelectorAll('.modal');
        modals.forEach(function(modal) {
            modal.addEventListener('show.bs.modal', function () {
                document.body.style.overflow = 'hidden'; // Bloqueia scroll ao abrir
                document.body.style.paddingRight = '0'; // Remove padding extra
            });
            modal.addEventListener('hidden.bs.modal', function () {
                document.body.style.overflow = 'auto'; // Restaura scroll ao fechar
                document.body.style.paddingRight = '0'; // Garante padding zerado
            });
        });
    });
</script>
</body>
</html>