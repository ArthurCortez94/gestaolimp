<?php
declare(strict_types=1);
session_start();

// Verificação de segurança com checagem de nível de acesso
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
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

require_once 'config.php';

function getMetric(PDO $pdo, string $query, array $params = []): float {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $value = $stmt->fetchColumn();
    return is_numeric($value) ? floatval($value) : 0.0;
}

$error = '';
$success = '';
$admin_id = $_SESSION['user_id'];

try {
    // Processar resposta ao ticket (igual ao atendente)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['responder_ticket'])) {
        $ticket_id = (int)$_POST['ticket_id'];
        $resposta = trim($_POST['resposta'] ?? '');

        if (empty($resposta)) {
            $error = "A resposta não pode estar vazia.";
        } else {
            $stmt = $pdo->prepare("
                UPDATE tickets 
                SET data_resposta = NOW(), resposta = ?, status = 'resolvido'
                WHERE id = ?
            ");
            $stmt->execute([$resposta, $ticket_id]);
            $success = "Ticket respondido com sucesso!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }

    // Processar criação de ticket (igual ao atendente)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['criar_ticket'])) {
        $titulo = trim($_POST['titulo'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $prioridade = $_POST['prioridade'] ?? 'baixa';
        $categoria = $_POST['categoria'] ?? '';
        $tecnico_id = (int)$_POST['tecnico_id'];

        if (empty($titulo) || empty($descricao) || empty($categoria) || $tecnico_id <= 0) {
            $error = "Preencha todos os campos obrigatórios.";
        } elseif (!in_array($prioridade, ['baixa', 'media', 'alta'])) {
            $error = "Prioridade inválida.";
        } elseif (!in_array($categoria, ['falta', 'reembolso', 'problema_servico', 'manutencao_equipamento', 'atraso', 'outro'])) {
            $error = "Categoria inválida.";
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO tickets_atendente_tecnico (atendente_id, tecnico_id, titulo, descricao, prioridade, status, created_at, categoria)
                VALUES (?, ?, ?, ?, ?, 'aberto', NOW(), ?)
            ");
            $stmt->execute([$admin_id, $tecnico_id, $titulo, $descricao, $prioridade, $categoria]);
            $success = "Ticket enviado ao técnico com sucesso!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }

    // Métricas principais (igual ao atendente)
    $orcamentos_dia = getMetric($pdo, "SELECT COUNT(*) FROM orcamentos WHERE DATE(validade) = CURDATE()");
    $valores_recebidos = getMetric($pdo, "SELECT COALESCE(SUM(total), 0) FROM ordens_servico WHERE status = 'Concluída'");
    $valores_a_receber = getMetric($pdo, "SELECT COALESCE(SUM(total), 0) FROM ordens_servico WHERE status = 'Agendado'");
    
    $status_orcamentos = [];
    $stmt_abertos = $pdo->query("SELECT COUNT(*) AS abertos FROM orcamentos WHERE status = 'Aberto'");
    $status_orcamentos['abertos'] = (int) $stmt_abertos->fetchColumn();

    $stmt_ordens = $pdo->query("
        SELECT 
            SUM(status = 'Agendado') AS agendados,
            SUM(status = 'Em Andamento') AS em_andamento,
            SUM(status = 'Concluída') AS concluidos,
            SUM(status = 'Atrasado') AS atrasados,
            SUM(status = 'Cancelada') AS cancelados
        FROM ordens_servico
    ");
    $ordens_result = $stmt_ordens->fetch(PDO::FETCH_ASSOC);
    $status_orcamentos['agendados'] = (int) ($ordens_result['agendados'] ?? 0);
    $status_orcamentos['em_andamento'] = (int) ($ordens_result['em_andamento'] ?? 0);
    $status_orcamentos['concluidos'] = (int) ($ordens_result['concluidos'] ?? 0);
    $status_orcamentos['atrasados'] = (int) ($ordens_result['atrasados'] ?? 0);
    $status_orcamentos['cancelados'] = (int) ($ordens_result['cancelados'] ?? 0);

    $desempenho_semanal = $pdo->query("
        SELECT 
            ANY_VALUE(DAYNAME(data_servico)) AS dia,
            COUNT(*) AS total_servicos
        FROM servicos 
        WHERE data_servico >= CURDATE() - INTERVAL 7 DAY
        GROUP BY DAYOFWEEK(data_servico)
        ORDER BY DAYOFWEEK(data_servico)
    ")->fetchAll(PDO::FETCH_ASSOC);

    $fluxo_caixa = $pdo->query("
        SELECT 
            DATE_FORMAT(data_pagamento, '%Y-%m') AS mes,
            COALESCE(SUM(CASE WHEN tipo = 'entrada' THEN valor ELSE 0 END), 0) AS entradas,
            COALESCE(SUM(CASE WHEN tipo = 'saida' THEN valor ELSE 0 END), 0) AS saidas
        FROM fluxo_caixa
        WHERE data_pagamento >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(data_pagamento, '%Y-%m')
        ORDER BY mes ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $contas = $pdo->query("
        SELECT 
            id,
            tipo,
            descricao,
            valor,
            data_vencimento,
            data_pagamento,
            status,
            CASE 
                WHEN status = 'pendente' AND data_vencimento < CURDATE() THEN 'atrasado'
                ELSE status
            END AS status_atualizado
        FROM contas
        WHERE DATE_FORMAT(data_vencimento, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
        ORDER BY data_vencimento DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $tickets_atendente = $pdo->query("
        SELECT tat.*, u.nome AS tecnico_nome 
        FROM tickets_atendente_tecnico tat 
        LEFT JOIN usuarios u ON tat.tecnico_id = u.id 
        WHERE tat.atendente_id = $admin_id
        ORDER BY FIELD(tat.prioridade, 'alta', 'media', 'baixa'), tat.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    $tickets_tecnico = $pdo->query("
        SELECT t.*, u.nome AS tecnico_nome 
        FROM tickets t 
        LEFT JOIN usuarios u ON t.atribuido_a = u.id 
        WHERE t.status = 'aberto'
        ORDER BY FIELD(t.prioridade, 'alta', 'media', 'baixa'), t.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    $lavanderia = $pdo->query("
        SELECT l.*, COALESCE(c.nome, 'Cliente não encontrado') AS cliente 
        FROM lavanderia l 
        LEFT JOIN clientes c ON l.cliente_id = c.id 
        ORDER BY l.data_prevista_entrega ASC LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
    $tecnicos = $pdo->query("SELECT id, nome FROM usuarios WHERE cargo = 'tecnico' ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);

    // [FUTURO] Adicionar métricas específicas para o administrador (ex.: desempenho de equipe)
    // Exemplo: $team_performance = $pdo->query("...")->fetchAll();

} catch (PDOException $e) {
    die("Erro ao buscar dados: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Administrador - UltraLimp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/ultralimp/css/dashboard.css">
</head>
<body>

<?php require __DIR__ . '/admin_navbar.php'; ?>

<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="text-primary fw-bold mb-0">Painel Administrativo</h2>
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-primary">
                <i class="fas fa-sync"></i>
            </button>
            <a href="criar_orcamento.php" class="btn btn-sm btn-primary">
                <i class="fas fa-plus"></i> Novo Orçamento
            </a>
        </div>
    </div>

    <!-- Métricas Principais -->
    <div class="row g-4 mb-4">
        <div class="col-6 col-lg-3">
            <div class="dashboard-card p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="metric-icon bg-primary">
                        <i class="fas fa-calendar-day text-white"></i>
                    </div>
                    <div>
                        <div class="metric-value"><?= (int)$orcamentos_dia ?></div>
                        <div class="metric-label">Orçamentos Hoje</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="dashboard-card p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="metric-icon bg-success">
                        <i class="fas fa-check-circle text-white"></i>
                    </div>
                    <div>
                        <div class="metric-value">R$ <?= number_format((float)$valores_recebidos, 2, ',', '.') ?></div>
                        <div class="metric-label">Recebidos</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="dashboard-card p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="metric-icon bg-warning">
                        <i class="fas fa-clock text-white"></i>
                    </div>
                    <div>
                        <div class="metric-value">R$ <?= number_format((float)$valores_a_receber, 2, ',', '.') ?></div>
                        <div class="metric-label">A Receber</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="dashboard-card p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="metric-icon bg-info">
                        <i class="fas fa-folder-open text-white"></i>
                    </div>
                    <div>
                        <div class="metric-value"><?= (int)($status_orcamentos['abertos'] ?? 0) ?></div>
                        <div class="metric-label">Orçamentos Abertos</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- [FUTURO] Seção para métricas adicionais do administrador -->
    <!-- Exemplo: Desempenho da equipe, lucros, etc. -->

    <!-- Status dos Orçamentos -->
    <div class="dashboard-card p-3 mb-4">
        <h5 class="fw-bold mb-3"><i class="fas fa-tasks me-2"></i>Status dos Orçamentos</h5>
        <div class="row g-2">
            <?php 
            $statusMapping = [
                'abertos'      => ['label' => 'Abertos', 'color' => 'info'],
                'agendados'    => ['label' => 'Agendados', 'color' => 'primary'],
                'em_andamento' => ['label' => 'Em Andamento', 'color' => 'warning'],
                'concluidos'   => ['label' => 'Concluídos', 'color' => 'success'],
                'atrasados'    => ['label' => 'Atrasados', 'color' => 'danger'],
                'cancelados'   => ['label' => 'Cancelados', 'color' => 'dark']
            ];
            foreach ($statusMapping as $key => $info): 
            ?>
                <div class="col-6 col-md-2">
                    <div class="dashboard-card p-2 text-center">
                        <div class="metric-value text-<?= $info['color'] ?>">
                            <?= (int)($status_orcamentos[$key] ?? 0) ?>
                        </div>
                        <div class="metric-label"><?= $info['label'] ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Gráficos em Cards Separados -->
    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <div class="dashboard-card grafico-container">
                <h5 class="fw-bold mb-3"><i class="fas fa-chart-line me-2"></i>Desempenho Semanal</h5>
                <div style="height:200px;">
                    <canvas id="desempenhoChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="dashboard-card grafico-container">
                <h5 class="fw-bold mb-3"><i class="fas fa-chart-bar me-2"></i>Fluxo de Caixa Mensal</h5>
                <div style="height:200px;">
                    <canvas id="fluxoCaixaChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="dashboard-card grafico-container">
                <h5 class="fw-bold mb-3"><i class="fas fa-chart-pie me-2"></i>Distribuição de Status</h5>
                <div style="height:200px;">
                    <canvas id="distribuicaoChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Cards Separados: Tickets, Contas e Lavanderia -->
    <div class="row g-4">
        <!-- Tickets -->
        <div class="col-lg-4">
            <div class="dashboard-card p-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0"><i class="fas fa-ticket-alt me-2 text-primary"></i>Tickets</h5>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#criarTicketModal">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
                <?php if ($error): ?>
                    <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success py-2"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                <div class="ticket-list">
                    <?php if(count($tickets_atendente) > 0 || count($tickets_tecnico) > 0): ?>
                        <?php foreach(array_merge($tickets_atendente, $tickets_tecnico) as $ticket): ?>
                            <div class="ticket-item lavanderia-item d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#responderTicketModal_<?= isset($ticket['atendente_id']) ? 'atendente' : 'tecnico' ?>_<?= $ticket['id'] ?>">
                                <i class="fas me-2 text-muted <?= match($ticket['categoria']) {
                                    'falta' => 'fa-user-times',
                                    'reembolso' => 'fa-dollar-sign',
                                    'problema_servico' => 'fa-exclamation-triangle',
                                    'manutencao_equipamento' => 'fa-tools',
                                    'atraso' => 'fa-clock',
                                    'resposta' => 'fa-reply',
                                    'outro' => 'fa-question',
                                    default => 'fa-ticket-alt'
                                } ?>"></i>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold text-truncate" style="max-width: 200px;"><?= htmlspecialchars($ticket['titulo']) ?></div>
                                    <small class="text-muted"><?= date('d/m H:i', strtotime($ticket['created_at'])) ?></small>
                                </div>
                                <span class="badge bg-<?= match($ticket['prioridade']) {
                                    'alta' => 'danger',
                                    'media' => 'warning',
                                    'baixa' => 'secondary'
                                } ?> ms-2"><?= ucfirst($ticket['prioridade']) ?></span>
                            </div>

                            <div class="modal fade" id="responderTicketModal_<?= isset($ticket['atendente_id']) ? 'atendente' : 'tecnico' ?>_<?= $ticket['id'] ?>" tabindex="-1" aria-labelledby="responderTicketModalLabel_<?= isset($ticket['atendente_id']) ? 'atendente' : 'tecnico' ?>_<?= $ticket['id'] ?>" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="responderTicketModalLabel_<?= isset($ticket['atendente_id']) ? 'atendente' : 'tecnico' ?>_<?= $ticket['id'] ?>">
                                                <i class="fas me-2 <?= match($ticket['categoria']) {
                                                    'falta' => 'fa-user-times',
                                                    'reembolso' => 'fa-dollar-sign',
                                                    'problema_servico' => 'fa-exclamation-triangle',
                                                    'manutencao_equipamento' => 'fa-tools',
                                                    'atraso' => 'fa-clock',
                                                    'resposta' => 'fa-reply',
                                                    'outro' => 'fa-question',
                                                    default => 'fa-ticket-alt'
                                                } ?>"></i>
                                                <?= htmlspecialchars($ticket['titulo']) ?>
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <p><strong>Técnico:</strong> <?= htmlspecialchars($ticket['tecnico_nome'] ?? 'Não atribuído') ?></p>
                                                    <p><strong>Categoria:</strong> <?= ucfirst(str_replace('_', ' ', $ticket['categoria'])) ?></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <p><strong>Data de Criação:</strong> <?= date('d/m/Y H:i', strtotime($ticket['created_at'])) ?></p>
                                                    <p><strong>Prioridade:</strong> <?= ucfirst($ticket['prioridade']) ?></p>
                                                </div>
                                            </div>
                                            <hr>
                                            <p><strong>Descrição:</strong> <?= htmlspecialchars($ticket['descricao']) ?></p>
                                            <?php if ($ticket['data_resposta']): ?>
                                                <p><strong>Respondido em:</strong> <?= date('d/m/Y H:i', strtotime($ticket['data_resposta'])) ?></p>
                                                <p><strong>Resposta:</strong> <?= htmlspecialchars($ticket['resposta'] ?? 'Sem texto') ?></p>
                                            <?php elseif (!isset($ticket['atendente_id'])): ?>
                                                <form method="POST">
                                                    <div class="mb-3">
                                                        <label for="resposta_<?= $ticket['id'] ?>" class="form-label">Resposta</label>
                                                        <textarea class="form-control" id="resposta_<?= $ticket['id'] ?>" name="resposta" rows="3" required placeholder="Digite sua resposta aqui"></textarea>
                                                    </div>
                                                    <input type="hidden" name="ticket_id" value="<?= $ticket['id'] ?>">
                                                    <input type="hidden" name="responder_ticket" value="1">
                                                    <button type="submit" class="btn btn-primary">
                                                        <i class="fas fa-reply me-2"></i>Enviar Resposta
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <p class="text-warning">Aguardando resposta do técnico</p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-muted text-center py-3">Nenhum ticket encontrado</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Contas a Pagar e Receber do Mês Atual -->
        <div class="col-lg-4">
            <div class="dashboard-card p-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0"><i class="fas fa-wallet me-2 text-info"></i>Contas do Mês</h5>
                    <a href="contas.php" class="btn btn-sm btn-primary">
                        <i class="fas fa-eye"></i> Ver Todas
                    </a>
                </div>
                <div class="contas-list">
                    <?php if(count($contas) > 0): ?>
                        <?php foreach($contas as $conta): ?>
                            <div class="conta-item lavanderia-item d-flex align-items-center">
                                <i class="fas fa-<?= $conta['tipo'] === 'pagar' ? 'arrow-up' : 'arrow-down' ?> me-2 text-<?= $conta['tipo'] === 'pagar' ? 'danger' : 'success' ?>"></i>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold text-truncate" style="max-width: 200px;"><?= htmlspecialchars($conta['descricao']) ?></div>
                                    <small class="text-muted"><?= date('d/m/Y', strtotime($conta['data_vencimento'])) ?></small>
                                </div>
                                <span class="text-<?= $conta['tipo'] === 'pagar' ? 'danger' : 'success' ?> fw-bold ms-2">R$ <?= number_format((float)$conta['valor'], 2, ',', '.') ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-muted text-center py-3">Nenhuma conta neste mês</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Lavanderia -->
        <div class="col-lg-4">
            <div class="dashboard-card p-3">
                <h5 class="fw-bold mb-3"><i class="fas fa-tshirt me-2"></i>Lavanderia</h5>
                <?php if(count($lavanderia) > 0): ?>
                    <?php foreach($lavanderia as $item): ?>
                        <div class="lavanderia-item" data-bs-toggle="modal" data-bs-target="#lavanderiaModal-<?= $item['id'] ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-semibold"><?= htmlspecialchars($item['cliente']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($item['nome_item']) ?></small>
                                </div>
                                <div class="text-end">
                                    <span class="lavanderia-date-label">Previsão:</span>
                                    <span class="data-prevista">
                                        <?= $item['data_prevista_entrega'] ? date('d/m/Y', strtotime($item['data_prevista_entrega'])) : 'Sem data' ?>
                                    </span>
                                    <span class="badge bg-<?= match(strtolower($item['status'])) {
                                        'em processamento' => 'warning',
                                        'lavado' => 'info',
                                        'pronto para retirada' => 'success',
                                        default => 'secondary'
                                    } ?> ms-2"><?= htmlspecialchars($item['status']) ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="modal fade" id="lavanderiaModal-<?= $item['id'] ?>" tabindex="-1" aria-labelledby="lavanderiaModalLabel-<?= $item['id'] ?>" aria-hidden="true">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="lavanderiaModalLabel-<?= $item['id'] ?>">
                                            <i class="fas fa-tshirt me-2 text-white"></i>
                                            <?= htmlspecialchars($item['nome_item']) ?>
                                        </h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <p><strong>ID:</strong> <?= $item['id'] ?></p>
                                                <p><strong>Cliente:</strong> <?= htmlspecialchars($item['cliente']) ?></p>
                                                <p><strong>Quantidade:</strong> <?= $item['quantidade'] ?></p>
                                                <p><strong>Valor Unitário:</strong> R$ <?= number_format((float)$item['valor_unitario'], 2, ',', '.') ?></p>
                                                <p><strong>Valor Total:</strong> R$ <?= number_format((float)$item['valor_total'], 2, ',', '.') ?></p>
                                            </div>
                                            <div class="col-md-6">
                                                <p><strong>Status:</strong> <?= htmlspecialchars($item['status']) ?></p>
                                                <p><strong>Data de Coleta:</strong> <?= $item['data_coleta'] ? date('d/m/Y', strtotime($item['data_coleta'])) : 'Não informada' ?></p>
                                                <p><strong>Data Prevista de Entrega:</strong> <?= $item['data_prevista_entrega'] ? date('d/m/Y', strtotime($item['data_prevista_entrega'])) : 'Não informada' ?></p>
                                                <p><strong>Data de Entrega:</strong> <?= $item['data_entrega'] ? date('d/m/Y', strtotime($item['data_entrega'])) : 'Não informada' ?></p>
                                                <p><strong>Criado em:</strong> <?= date('d/m/Y H:i', strtotime($item['created_at'])) ?></p>
                                            </div>
                                        </div>
                                        <hr>
                                        <p><strong>Observação:</strong> <?= htmlspecialchars($item['observacao'] ?: 'Nenhuma') ?></p>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="alert alert-info">Nenhum item na lavanderia.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- [FUTURO] Seção para funcionalidades adicionais do administrador -->
    <!-- Exemplo: Gerenciamento de usuários, relatórios avançados, etc. -->
    <!-- <div class="row g-4 mt-4">
        <div class="col-lg-12">
            <div class="dashboard-card p-3">
                <h5 class="fw-bold mb-3"><i class="fas fa-users me-2"></i>Gerenciamento de Equipe</h5>
                <p>Aqui será implementado o gerenciamento de usuários no futuro.</p>
            </div>
        </div>
    </div> -->

    <!-- Modal para Criar Ticket -->
    <div class="modal fade" id="criarTicketModal" tabindex="-1" aria-labelledby="criarTicketModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="criarTicketModalLabel">Criar Novo Ticket</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="tecnico_id" class="form-label">Técnico <span class="text-danger">*</span></label>
                            <select class="form-select" id="tecnico_id" name="tecnico_id" required>
                                <option value="">Selecione um técnico</option>
                                <?php foreach ($tecnicos as $tecnico): ?>
                                    <option value="<?= $tecnico['id'] ?>"><?= htmlspecialchars($tecnico['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="titulo" class="form-label">Título <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="titulo" name="titulo" required>
                        </div>
                        <div class="mb-3">
                            <label for="categoria" class="form-label">Categoria <span class="text-danger">*</span></label>
                            <select class="form-select" id="categoria" name="categoria" required>
                                <option value="">Selecione uma categoria</option>
                                <option value="falta">Informar Falta</option>
                                <option value="reembolso">Solicitar Reembolso</option>
                                <option value="problema_servico">Problema no Serviço</option>
                                <option value="manutencao_equipamento">Manutenção de Equipamento</option>
                                <option value="atraso">Comunicar Atraso</option>
                                <option value="outro">Outro</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="descricao" class="form-label">Descrição <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="descricao" name="descricao" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="prioridade" class="form-label">Prioridade</label>
                            <select class="form-select" id="prioridade" name="prioridade">
                                <option value="baixa">Baixa</option>
                                <option value="media" selected>Média</option>
                                <option value="alta">Alta</option>
                            </select>
                        </div>
                        <input type="hidden" name="criar_ticket" value="1">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Enviar Ticket</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    new Chart(document.getElementById('desempenhoChart'), {
        type: 'line',
        data: {
            labels: <?= json_encode(array_column($desempenho_semanal, 'dia')) ?>,
            datasets: [{
                label: 'Serviços Realizados',
                data: <?= json_encode(array_column($desempenho_semanal, 'total_servicos')) ?>,
                borderColor: 'rgba(42, 92, 130, 0.6)',
                backgroundColor: 'rgba(42, 92, 130, 0.2)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });

    new Chart(document.getElementById('fluxoCaixaChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($fluxo_caixa, 'mes')) ?>,
            datasets: [
                {
                    label: 'Entradas',
                    data: <?= json_encode(array_column($fluxo_caixa, 'entradas')) ?>,
                    backgroundColor: 'rgba(76, 175, 80, 0.4)',
                    borderColor: 'rgba(76, 175, 80, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Saídas',
                    data: <?= json_encode(array_column($fluxo_caixa, 'saidas')) ?>,
                    backgroundColor: 'rgba(220, 53, 69, 0.4)',
                    borderColor: 'rgba(220, 53, 69, 1)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'top' } },
            scales: {
                y: { beginAtZero: true },
                x: { stacked: false }
            }
        }
    });

    new Chart(document.getElementById('distribuicaoChart'), {
        type: 'pie',
        data: {
            labels: ['Aberto', 'Agendado', 'Em Andamento', 'Concluída', 'Atrasado', 'Cancelada'],
            datasets: [{
                data: [
                    <?= (int)($status_orcamentos['abertos'] ?? 0) ?>,
                    <?= (int)($status_orcamentos['agendados'] ?? 0) ?>,
                    <?= (int)($status_orcamentos['em_andamento'] ?? 0) ?>,
                    <?= (int)($status_orcamentos['concluidos'] ?? 0) ?>,
                    <?= (int)($status_orcamentos['atrasados'] ?? 0) ?>,
                    <?= (int)($status_orcamentos['cancelados'] ?? 0) ?>
                ],
                backgroundColor: [
                    'rgba(42, 92, 130, 0.4)',
                    'rgba(42, 92, 130, 0.4)',
                    'rgba(255, 193, 7, 0.4)',
                    'rgba(76, 175, 80, 0.4)',
                    'rgba(220, 53, 69, 0.4)',
                    'rgba(108, 117, 125, 0.4)'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } }
        }
    });
});
</script>
</body>
</html>