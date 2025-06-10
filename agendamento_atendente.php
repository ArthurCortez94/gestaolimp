<?php
ob_start();
session_start();
require_once 'config.php';

// Verificação de segurança
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_cargo'], ['atendente', 'admin'])) {
    file_put_contents('debug.log', "Acesso negado - user_id: " . ($_SESSION['user_id'] ?? 'não definido') . ", cargo: " . ($_SESSION['user_cargo'] ?? 'não definido') . "\n", FILE_APPEND);
    header("Location: login.php?error=Acesso+negado.+Verifique+seu+cargo.");
    exit();
}

// Controle de inatividade (30 minutos)
$_SESSION['user_last_active'] = $_SESSION['user_last_active'] ?? time();
if ((time() - $_SESSION['user_last_active']) > 1800) {
    session_unset();
    session_destroy();
    header("Location: login.php?expired=1");
    exit();
}
$_SESSION['user_last_active'] = time();

$error = '';
$success = '';

// Processar ações do atendente
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    file_put_contents('debug.log', "POST Recebido: " . print_r($_POST, true) . "\n", FILE_APPEND);
    try {
        $ordem_id = (int)$_POST['ordem_id'];
        $usuario_id = $_SESSION['user_id'];

        if (isset($_POST['confirmar_conclusao'])) {
            $stmt = $pdo->prepare("UPDATE ordens_servico SET status = 'Concluída' WHERE id = ?");
            $stmt->execute([$ordem_id]);
            $stmt = $pdo->prepare("INSERT INTO historico_ordens (ordem_id, usuario_id, acao) VALUES (?, ?, 'Confirmou conclusão')");
            $stmt->execute([$ordem_id, $usuario_id]);
            $success = "Ordem marcada como concluída com sucesso!";
        } elseif (isset($_POST['reatribuir_tecnico'])) {
            $novo_tecnico_id = (int)$_POST['novo_tecnico_id'];
            $stmt = $pdo->prepare("UPDATE ordens_servico SET tecnico_id = ? WHERE id = ?");
            $stmt->execute([$novo_tecnico_id, $ordem_id]);
            $stmt = $pdo->prepare("INSERT INTO historico_ordens (ordem_id, usuario_id, acao) VALUES (?, ?, 'Reatribuiu técnico')");
            $stmt->execute([$ordem_id, $usuario_id]);
            $success = "Técnico reatribuído com sucesso!";
        } elseif (isset($_POST['reabrir_retorno'])) {
            $stmt = $pdo->prepare("UPDATE ordens_servico SET status = 'Agendado' WHERE id = ?");
            $stmt->execute([$ordem_id]);
            $stmt = $pdo->prepare("INSERT INTO historico_ordens (ordem_id, usuario_id, acao) VALUES (?, ?, 'Reaberto para retorno')");
            $stmt->execute([$ordem_id, $usuario_id]);
            $success = "Ordem reaberta para retorno com sucesso!";
        }

        if (!$error) {
            header("Location: " . $_SERVER['PHP_SELF'] . "?success=" . urlencode($success));
            exit();
        }
    } catch (PDOException $e) {
        $error = "Erro ao processar a ação: " . $e->getMessage();
        file_put_contents('debug.log', "Erro: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}

// Buscar todas as ordens de serviço com filtros
try {
    $status_filter = $_GET['status'] ?? '';
    $search_filter = $_GET['search'] ?? '';
    
    $query = "
        SELECT os.*, 
               c.nome AS cliente_nome, 
               c.telefone AS cliente_telefone, 
               c.endereco AS cliente_endereco,
               c.numero AS cliente_numero,
               c.complemento AS cliente_complemento,
               c.bairro AS cliente_bairro,
               c.cidade AS cliente_cidade,
               c.uf AS cliente_uf,
               c.cep AS cliente_cep,
               t.nome AS tecnico_nome
        FROM ordens_servico os
        JOIN clientes c ON os.cliente_id = c.id
        LEFT JOIN usuarios t ON os.tecnico_id = t.id
        WHERE 1=1
    ";
    $params = [];
    
    if ($status_filter) {
        $query .= " AND os.status = :status";
        $params[':status'] = $status_filter;
    }
    if ($search_filter) {
        $query .= " AND (c.nome LIKE :search OR os.numero_ordem LIKE :search)";
        $params[':search'] = "%$search_filter%";
    }
    
    $query .= " ORDER BY os.data_servico ASC, os.hora_servico ASC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $ordens_servico = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Buscar técnicos disponíveis para reatribuição
    $stmt = $pdo->prepare("SELECT id, nome FROM usuarios WHERE cargo = 'tecnico' AND ativo = 1 ORDER BY nome ASC");
    $stmt->execute();
    $tecnicos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($ordens_servico as &$ordem) {
        $stmt = $pdo->prepare("SELECT acao, motivo_recusa, observacao_aceite, data_confirmacao FROM ordens_servico_confirmacoes WHERE ordem_id = ? ORDER BY data_confirmacao DESC LIMIT 1");
        $stmt->execute([$ordem['id']]);
        $ordem['confirmacao'] = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT caminho_foto, data_upload FROM fotos_servico WHERE ordem_id = ?");
        $stmt->execute([$ordem['id']]);
        $ordem['fotos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT h.acao, h.data_alteracao, u.nome AS usuario_nome FROM historico_ordens h JOIN usuarios u ON h.usuario_id = u.id WHERE h.ordem_id = ? ORDER BY h.data_alteracao DESC");
        $stmt->execute([$ordem['id']]);
        $ordem['historico'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($ordem);

} catch (PDOException $e) {
    die("Erro ao buscar ordens de serviço: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agendamentos - Atendente - UltraLimp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #2A5C82;
            --secondary: #4CAF50;
            --accent: #FFC107;
            --light: #f8fafc;
            --dark: #1A3C5A;
            --danger: #dc3545;
            --gray: #6c757d;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--light);
            padding-top: 80px;
        }
        .dashboard-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            padding: 1.5rem;
        }
        .ordem-item {
            background: white;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
        }
        .ordem-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }
        .ordem-item.agendado { border-left: 4px solid var(--primary); }
        .ordem-item.aceito { border-left: 4px solid var(--secondary); }
        .ordem-item.recusado { border-left: 4px solid var(--danger); }
        .ordem-item.concluida { border-left: 4px solid var(--secondary); }
        .ordem-item.cancelada { border-left: 4px solid var(--danger); }
        .ordem-item .status-badge {
            font-size: 0.9rem;
            padding: 0.4em 0.8em;
            border-radius: 20px;
        }
        .ordem-item .text-muted {
            font-size: 0.85rem;
        }
        .btn-primary { background-color: var(--primary); border-color: var(--primary); }
        .btn-primary:hover { background-color: var(--dark); border-color: var(--dark); }
        .btn-success { background-color: var(--secondary); border-color: var(--secondary); }
        .btn-success:hover { background-color: #3d8b40; border-color: #3d8b40; }
        .btn-danger { background-color: var(--danger); border-color: var(--danger); }
        .btn-danger:hover { background-color: #c82333; border-color: #c82333; }
        .btn-warning { background-color: var(--accent); border-color: var(--accent); }
        .btn-warning:hover { background-color: #e0a800; border-color: #e0a800; }
        .btn-info { background-color: var(--gray); border-color: var(--gray); }
        .btn-info:hover { background-color: #5a6268; border-color: #5a6268; }

        .modal-content {
            border-radius: 15px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
            border: none;
        }
        .modal-header {
            background: linear-gradient(135deg, var(--primary), var(--dark));
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 1.5rem;
        }
        .modal-title {
            font-weight: 600;
            display: flex;
            align-items: center;
        }
        .modal-body {
            padding: 2rem;
            background: #fff;
            border-radius: 0 0 15px 15px;
        }
        .modal-footer {
            border-top: none;
            padding: 1rem 2rem;
        }
        .info-section {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        .info-label {
            font-weight: bold;
            color: var(--primary);
            margin-bottom: 0.25rem;
        }
        .info-text {
            margin-bottom: 0.5rem;
            color: #333;
        }
        .servicos-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 0.5rem;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .servicos-table th, .servicos-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        .servicos-table th {
            background: linear-gradient(135deg, var(--primary), var(--dark));
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9rem;
        }
        .servicos-table td {
            background: white;
            color: #333;
        }
        .servicos-table tr:nth-child(even) td {
            background: #f9f9f9;
        }
        .servicos-table tr:hover td {
            background: #f1f1f1;
        }
        .servicos-table th:first-child {
            border-top-left-radius: 10px;
        }
        .servicos-table th:last-child {
            border-top-right-radius: 10px;
        }
        .servicos-table tr:last-child td:first-child {
            border-bottom-left-radius: 10px;
        }
        .servicos-table tr:last-child td:last-child {
            border-bottom-right-radius: 10px;
        }
        .servicos-table td.text-center {
            text-align: center;
        }
        .servicos-table td.text-end {
            text-align: right;
        }
        .btn-data-hora {
            background-color: var(--accent);
            border: none;
            color: #fff;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-data-hora:hover {
            background-color: #e0a800;
            transform: scale(1.05);
        }
        .foto-link {
            display: block;
            margin: 5px 0;
            color: var(--primary);
            text-decoration: none;
            font-size: 0.9rem;
        }
        .foto-link:hover {
            text-decoration: underline;
            color: var(--dark);
        }
        .conclusao-section {
            background: #e8f5e9;
            border: 2px solid var(--secondary);
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1rem;
        }
        .historico-list {
            list-style: none;
            padding: 0;
        }
        .historico-list li {
            padding: 0.5rem 0;
            border-bottom: 1px solid #e0e0e0;
        }
        .historico-list li:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>

<?php require __DIR__ . '/navbar.php'; ?>

<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="text-primary fw-bold mb-0">Gerenciamento de Agendamentos</h2>
        <div>
            <a href="criar_ordem_servico.php" class="btn btn-success me-2"><i class="fas fa-plus me-1"></i>Nova Ordem</a>
            <a href="dashboard.php" class="btn btn-primary"><i class="fas fa-arrow-left me-1"></i>Voltar ao Dashboard</a>
        </div>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_GET['success']) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Filtros -->
    <div class="dashboard-card mb-4">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Filtrar por Status:</label>
                <select class="form-select" onchange="window.location.href='<?php echo $_SERVER['PHP_SELF']; ?>?status='+this.value+'&search=<?php echo urlencode($search_filter); ?>'">
                    <option value="" <?php echo $status_filter === '' ? 'selected' : ''; ?>>Todos</option>
                    <option value="Agendado" <?php echo $status_filter === 'Agendado' ? 'selected' : ''; ?>>Agendado</option>
                    <option value="Aceito" <?php echo $status_filter === 'Aceito' ? 'selected' : ''; ?>>Aceito</option>
                    <option value="Recusado" <?php echo $status_filter === 'Recusado' ? 'selected' : ''; ?>>Recusado</option>
                    <option value="Concluída" <?php echo $status_filter === 'Concluída' ? 'selected' : ''; ?>>Concluído</option>
                    <option value="Cancelada" <?php echo $status_filter === 'Cancelada' ? 'selected' : ''; ?>>Cancelado</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Buscar por Cliente ou Número:</label>
                <form method="GET" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="d-flex">
                    <input type="text" name="search" class="form-control me-2" value="<?php echo htmlspecialchars($search_filter); ?>" placeholder="Digite o nome ou número da ordem">
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                </form>
            </div>
        </div>
    </div>

    <div class="dashboard-card">
        <h4 class="fw-bold mb-3"><i class="fas fa-calendar-check me-2"></i>Todas as Ordens de Serviço</h4>
        <?php if (count($ordens_servico) > 0): ?>
            <?php foreach ($ordens_servico as $ordem): ?>
                <?php
                $status_class = match(strtolower($ordem['status'])) {
                    'agendado' => 'agendado',
                    'aceito' => 'aceito',
                    'recusado' => 'recusado',
                    'concluída' => 'concluida',
                    'cancelada' => 'cancelada',
                    default => 'agendado'
                };
                $servicos = json_decode($ordem['descricao_servicos'] ?? '', true) ?: [];
                $servicos_lista = array_map(function($s) {
                    return htmlspecialchars($s['nome'] ?? '') . ' (Qtd: ' . ($s['quantidade'] ?? 0) . ', R$ ' . number_format($s['valor_unitario'] ?? 0, 2, ',', '.') . ')';
                }, $servicos);
                $servicos_formatados = implode(', ', $servicos_lista);

                $endereco_completo = htmlspecialchars($ordem['cliente_endereco']);
                if (!empty($ordem['cliente_numero'])) {
                    $endereco_completo .= ", " . htmlspecialchars($ordem['cliente_numero']);
                }
                if (!empty($ordem['cliente_complemento'])) {
                    $endereco_completo .= " - " . htmlspecialchars($ordem['cliente_complemento']);
                }
                if (!empty($ordem['cliente_bairro'])) {
                    $endereco_completo .= " - " . htmlspecialchars($ordem['cliente_bairro']);
                }
                if (!empty($ordem['cliente_cidade']) && !empty($ordem['cliente_uf'])) {
                    $endereco_completo .= ", " . htmlspecialchars($ordem['cliente_cidade']) . "/" . htmlspecialchars($ordem['cliente_uf']);
                }
                if (!empty($ordem['cliente_cep'])) {
                    $endereco_completo .= " - CEP: " . htmlspecialchars($ordem['cliente_cep']);
                }
                ?>
                <div class="ordem-item <?= $status_class ?>" data-bs-toggle="modal" data-bs-target="#ordemModal_<?= $ordem['id'] ?>">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-semibold">
                                <i class="fas fa-tools me-2"></i>
                                Ordem #<?= htmlspecialchars($ordem['numero_ordem']) ?> - <?= htmlspecialchars($ordem['cliente_nome']) ?>
                            </div>
                            <small class="text-muted">
                                <i class="fas fa-calendar-alt me-1"></i>Data: <?= date('d/m/Y', strtotime($ordem['data_servico'])) ?> às <?= $ordem['hora_servico'] ?> 
                                | <i class="fas fa-user me-1"></i>Técnico: <?= htmlspecialchars($ordem['tecnico_nome'] ?? 'Não atribuído') ?>
                                | <i class="fas fa-info-circle me-1"></i>Status: <?= ucfirst($ordem['status']) ?>
                                <?php if ($ordem['confirmacao']): ?>
                                    | <i class="fas fa-user-check me-1"></i>Confirmação: <?= ucfirst($ordem['confirmacao']['acao']) ?>
                                <?php endif; ?>
                            </small>
                        </div>
                        <span class="status-badge bg-<?= match(strtolower($ordem['status'])) {
                            'agendado' => 'primary',
                            'aceito' => 'success',
                            'recusado' => 'danger',
                            'concluída' => 'success',
                            'cancelada' => 'danger',
                            default => 'secondary'
                        } ?>"><?= ucfirst($ordem['status']) ?></span>
                    </div>
                </div>

                <!-- Modal para Detalhes da Ordem -->
                <div class="modal fade" id="ordemModal_<?= $ordem['id'] ?>" tabindex="-1" aria-labelledby="ordemModalLabel_<?= $ordem['id'] ?>" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="ordemModalLabel_<?= $ordem['id'] ?>">
                                    <i class="fas fa-tools me-2"></i> Ordem de Serviço #<?= htmlspecialchars($ordem['numero_ordem']) ?>
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="info-section">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="info-label">Cliente</div>
                                            <div class="info-text"><?= htmlspecialchars($ordem['cliente_nome']) ?></div>
                                            <div class="info-label">Telefone</div>
                                            <div class="info-text"><?= htmlspecialchars($ordem['cliente_telefone']) ?></div>
                                            <div class="info-label">Endereço Completo</div>
                                            <div class="info-text"><?= $endereco_completo ?></div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="info-label">Data e Hora do Serviço</div>
                                            <button class="btn-data-hora">
                                                <i class="fas fa-calendar-alt me-2"></i>
                                                <?= date('d/m/Y', strtotime($ordem['data_servico'])) ?> às <?= $ordem['hora_servico'] ?>
                                            </button>
                                            <div class="info-label mt-3">Previsão de Conclusão</div>
                                            <div class="info-text"><?= date('d/m/Y', strtotime($ordem['previsao_conclusao'])) ?></div>
                                            <div class="info-label">Status</div>
                                            <div class="info-text"><?= ucfirst($ordem['status']) ?></div>
                                            <div class="info-label">Técnico Atribuído</div>
                                            <div class="info-text"><?= htmlspecialchars($ordem['tecnico_nome'] ?? 'Não atribuído') ?></div>
                                            <div class="info-label">Valor Total</div>
                                            <div class="info-text">R$ <?= number_format($ordem['total'], 2, ',', '.') ?></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="info-section">
                                    <h6 class="info-label mb-2">Serviços a Executar</h6>
                                    <?php if (count($servicos) > 0): ?>
                                        <table class="servicos-table">
                                            <thead>
                                                <tr>
                                                    <th>Serviço</th>
                                                    <th>Quantidade</th>
                                                    <th>Valor Unitário</th>
                                                    <th>Subtotal</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($servicos as $servico): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($servico['nome'] ?? '') ?></td>
                                                        <td class="text-center"><?= $servico['quantidade'] ?? 0 ?></td>
                                                        <td class="text-end">R$ <?= number_format($servico['valor_unitario'] ?? 0, 2, ',', '.') ?></td>
                                                        <td class="text-end">R$ <?= number_format(($servico['quantidade'] ?? 0) * ($servico['valor_unitario'] ?? 0), 2, ',', '.') ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php else: ?>
                                        <p class="info-text">Nenhum serviço listado.</p>
                                    <?php endif; ?>
                                </div>

                                <?php if ($ordem['observacoes']): ?>
                                    <div class="info-section">
                                        <h6 class="info-label mb-2">Observações</h6>
                                        <div class="info-text"><?= htmlspecialchars($ordem['observacoes']) ?></div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($ordem['informacoes_extras']): ?>
                                    <div class="info-section">
                                        <h6 class="info-label mb-2">Informações Extras</h6>
                                        <div class="info-text"><?= htmlspecialchars($ordem['informacoes_extras']) ?></div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($ordem['confirmacao']): ?>
                                    <div class="info-section">
                                        <h6 class="info-label mb-2">Confirmação do Técnico</h6>
                                        <div class="info-text">
                                            <strong>Ação:</strong> <?= ucfirst($ordem['confirmacao']['acao']) ?><br>
                                            <?php if ($ordem['confirmacao']['motivo_recusa']): ?>
                                                <strong>Motivo da Recusa:</strong> <?= htmlspecialchars($ordem['confirmacao']['motivo_recusa']) ?><br>
                                            <?php endif; ?>
                                            <?php if ($ordem['confirmacao']['observacao_aceite']): ?>
                                                <strong>Observação ao Aceitar:</strong> <?= htmlspecialchars($ordem['confirmacao']['observacao_aceite']) ?><br>
                                            <?php endif; ?>
                                            <strong>Data:</strong> <?= date('d/m/Y H:i', strtotime($ordem['confirmacao']['data_confirmacao'])) ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($ordem['observacoes_tecnico']): ?>
                                    <div class="info-section">
                                        <h6 class="info-label mb-2">Observações do Técnico</h6>
                                        <div class="info-text"><?= htmlspecialchars($ordem['observacoes_tecnico']) ?></div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($ordem['observacoes_finalizacao'] || $ordem['hora_inicio'] || $ordem['hora_fim'] || $ordem['produtos_usados'] || $ordem['servicos_adicionais']): ?>
                                    <div class="conclusao-section">
                                        <h6 class="info-label mb-2">Dados de Conclusão do Técnico</h6>
                                        <div class="info-text">
                                            <?php if ($ordem['hora_inicio']): ?>
                                                <strong>Hora de Início:</strong> <?= htmlspecialchars($ordem['hora_inicio']) ?><br>
                                            <?php endif; ?>
                                            <?php if ($ordem['hora_fim']): ?>
                                                <strong>Hora de Fim:</strong> <?= htmlspecialchars($ordem['hora_fim']) ?><br>
                                            <?php endif; ?>
                                            <?php if ($ordem['produtos_usados']): ?>
                                                <strong>Produtos Usados:</strong> <?= htmlspecialchars($ordem['produtos_usados']) ?><br>
                                            <?php endif; ?>
                                            <?php if ($ordem['servicos_adicionais']): ?>
                                                <strong>Serviços Adicionais:</strong> <?= htmlspecialchars($ordem['servicos_adicionais']) ?><br>
                                            <?php endif; ?>
                                            <?php if ($ordem['observacoes_finalizacao']): ?>
                                                <strong>Observações de Finalização:</strong> <?= htmlspecialchars($ordem['observacoes_finalizacao']) ?><br>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($ordem['caminho_pdf']): ?>
                                    <div class="info-section">
                                        <h6 class="info-label mb-2">PDF da Ordem</h6>
                                        <a href="<?= htmlspecialchars($ordem['caminho_pdf']) ?>" target="_blank" class="btn btn-primary">
                                            <i class="fas fa-file-pdf me-2"></i>Visualizar PDF
                                        </a>
                                    </div>
                                <?php endif; ?>

                                <?php if (count($ordem['fotos']) > 0): ?>
                                    <div class="info-section">
                                        <h6 class="info-label mb-2">Fotos do Serviço</h6>
                                        <div>
                                            <?php foreach ($ordem['fotos'] as $index => $foto): ?>
                                                <a href="<?= htmlspecialchars($foto['caminho_foto']) ?>" target="_blank" class="foto-link">
                                                    <i class="fas fa-image me-1"></i>Foto <?= $index + 1 ?> (<?= date('d/m/Y H:i', strtotime($foto['data_upload'])) ?>)
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($ordem['historico'])): ?>
                                    <div class="info-section">
                                        <h6 class="info-label mb-2">Histórico de Alterações</h6>
                                        <ul class="historico-list">
                                            <?php foreach ($ordem['historico'] as $entry): ?>
                                                <li>
                                                    <?= htmlspecialchars($entry['acao']) ?> por <?= htmlspecialchars($entry['usuario_nome']) ?> em <?= date('d/m/Y H:i', strtotime($entry['data_alteracao'])) ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>

                                <!-- Ações do Atendente -->
                                <div class="info-section">
                                    <h6 class="info-label mb-2">Ações do Atendente</h6>
                                    <form method="POST" action="" class="d-inline-block">
                                        <input type="hidden" name="ordem_id" value="<?= $ordem['id'] ?>">
                                        <?php if (strtolower($ordem['status']) !== 'concluída'): ?>
                                            <button type="submit" name="confirmar_conclusao" class="btn btn-success me-2">
                                                <i class="fas fa-check-circle me-2"></i>Confirmar Conclusão
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                    <form method="POST" action="" class="d-inline-block">
                                        <input type="hidden" name="ordem_id" value="<?= $ordem['id'] ?>">
                                        <select name="novo_tecnico_id" class="form-select d-inline-block w-auto me-2" required>
                                            <option value="">Selecione um técnico</option>
                                            <?php foreach ($tecnicos as $tecnico): ?>
                                                <option value="<?= $tecnico['id'] ?>" <?= $tecnico['id'] == $ordem['tecnico_id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($tecnico['nome']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" name="reatribuir_tecnico" class="btn btn-warning me-2">
                                            <i class="fas fa-user-cog me-2"></i>Reatribuir Técnico
                                        </button>
                                    </form>
                                    <form method="POST" action="" class="d-inline-block">
                                        <input type="hidden" name="ordem_id" value="<?= $ordem['id'] ?>">
                                        <?php if (strtolower($ordem['status']) === 'concluída'): ?>
                                            <button type="submit" name="reabrir_retorno" class="btn btn-danger">
                                                <i class="fas fa-undo me-2"></i>Reabrir para Retorno
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alert alert-info">Nenhuma ordem de serviço encontrada.</div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleMotivoRecusa(id) {
    const container = document.getElementById(id);
    container.style.display = container.style.display === 'none' ? 'block' : 'none';
}

function toggleObservacaoAceite(id) {
    const container = document.getElementById(id);
    container.style.display = container.style.display === 'none' ? 'block' : 'none';
}

function toggleFinalizacao(id) {
    const container = document.getElementById(id);
    container.style.display = container.style.display === 'none' ? 'block' : 'none';
}
</script>
</body>
</html>