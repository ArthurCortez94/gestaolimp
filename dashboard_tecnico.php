<?php
declare(strict_types=1);
session_start();

// Verificação de segurança
if (!isset($_SESSION['user_id']) || $_SESSION['user_cargo'] !== 'tecnico') {
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
    return (float) $stmt->fetchColumn();
}

$error = '';
$success = '';
$tecnico_id = $_SESSION['user_id'];

try {
    $tecnico_nome = $_SESSION['user_name']; // Nome do técnico logado, ex.: "lucio"

    // Processar criação de ticket pelo técnico
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['criar_ticket'])) {
        $titulo = trim($_POST['titulo'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $prioridade = $_POST['prioridade'] ?? 'baixa';
        $categoria = $_POST['categoria'] ?? '';

        if (empty($titulo) || empty($descricao) || empty($categoria)) {
            $error = "Preencha todos os campos obrigatórios.";
        } elseif (!in_array($prioridade, ['baixa', 'media', 'alta'])) {
            $error = "Prioridade inválida.";
        } elseif (!in_array($categoria, ['falta', 'reembolso', 'problema_servico', 'manutencao_equipamento', 'atraso'])) {
            $error = "Categoria inválida.";
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO tickets (titulo, descricao, prioridade, status, atribuido_a, created_at, categoria)
                VALUES (?, ?, ?, 'aberto', NULL, NOW(), ?)
            ");
            $stmt->execute([$titulo, $descricao, $prioridade, $categoria]);
            $success = "Ticket criado com sucesso!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }

    // Processar resposta ao atendente
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['responder_ticket'])) {
        $ticket_id = (int)$_POST['ticket_id'];
        $resposta = trim($_POST['resposta'] ?? '');

        if (empty($resposta)) {
            $error = "A resposta não pode estar vazia.";
        } else {
            $stmt = $pdo->prepare("
                UPDATE tickets_atendente_tecnico 
                SET data_resposta = NOW(), resposta = ?, status = 'resolvido'
                WHERE id = ? AND tecnico_id = ?
            ");
            $stmt->execute([$resposta, $ticket_id, $tecnico_id]);
            $success = "Resposta enviada ao atendente com sucesso!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }

    // Métricas principais para o técnico
    $servicos_hoje = getMetric($pdo, "
        SELECT COUNT(*) 
        FROM ordens_servico 
        WHERE tecnico_id = ? AND DATE(data_servico) = CURDATE()", 
        [$tecnico_id]
    );
    
    $gastos_total = getMetric($pdo, "
        SELECT COALESCE(SUM(valor), 0) 
        FROM gastos_tecnicos 
        WHERE tecnico_id = ?", 
        [$tecnico_id]
    );
    
    $tickets_pendentes = getMetric($pdo, "
        SELECT COUNT(*) 
        FROM tickets_atendente_tecnico 
        WHERE tecnico_id = ? AND status = 'aberto'", 
        [$tecnico_id]
    );

    // Status dos serviços do técnico
    $status_servicos = $pdo->prepare("
        SELECT 
            SUM(status = 'Aberta') AS abertos,
            SUM(status = 'Concluída') AS concluidos,
            SUM(status = 'Cancelada') AS cancelados
        FROM ordens_servico
        WHERE tecnico_id = ?
    ");
    $status_servicos->execute([$tecnico_id]);
    $status_servicos = $status_servicos->fetch();

    // Desempenho semanal do técnico
    $desempenho_semanal = $pdo->prepare("
        SELECT 
            ANY_VALUE(DAYNAME(data_servico)) AS dia,
            COUNT(*) AS total_servicos
        FROM ordens_servico 
        WHERE tecnico_id = ?
        AND data_servico >= CURDATE() - INTERVAL 7 DAY
        GROUP BY DAYOFWEEK(data_servico)
        ORDER BY DAYOFWEEK(data_servico)
    ");
    $desempenho_semanal->execute([$tecnico_id]);
    $desempenho_semanal = $desempenho_semanal->fetchAll();

    // Dados adicionais
    $servicos = $pdo->prepare("
        SELECT os.*, c.nome AS cliente 
        FROM ordens_servico os 
        JOIN clientes c ON os.cliente_id = c.id 
        WHERE os.tecnico_id = ?
        AND os.status = 'Aberta'
        ORDER BY os.data_servico ASC 
        LIMIT 5
    ");
    $servicos->execute([$tecnico_id]);
    $servicos = $servicos->fetchAll();

    $gastos_tecnicos = $pdo->prepare("
        SELECT * 
        FROM gastos_tecnicos 
        WHERE tecnico_id = ?
        ORDER BY data_registro DESC 
        LIMIT 5
    ");
    $gastos_tecnicos->execute([$tecnico_id]);
    $gastos_tecnicos = $gastos_tecnicos->fetchAll();

    // Tickets recebidos do atendente
    $tickets_atendente = $pdo->prepare("
        SELECT tat.*, u.nome AS atendente_nome 
        FROM tickets_atendente_tecnico tat 
        LEFT JOIN usuarios u ON tat.atendente_id = u.id 
        WHERE tat.tecnico_id = ? AND tat.status = 'aberto' 
        ORDER BY FIELD(tat.prioridade, 'alta', 'media', 'baixa'), tat.created_at DESC
    ");
    $tickets_atendente->execute([$tecnico_id]);
    $tickets_atendente = $tickets_atendente->fetchAll();

    // Tickets enviados pelo técnico
    $tickets_tecnico = $pdo->query("
        SELECT t.*, u.nome AS atendente_nome 
        FROM tickets t 
        LEFT JOIN usuarios u ON t.atribuido_a = u.id 
        WHERE t.status = 'aberto'
        ORDER BY FIELD(t.prioridade, 'alta', 'media', 'baixa'), t.created_at DESC
    ")->fetchAll();

} catch (PDOException $e) {
    die("Erro ao buscar dados: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Técnico - UltraLimp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
    :root {
        --primary: #2A5C82;
        --secondary: #4CAF50;
        --accent: #FFC107;
        --light: #f8fafc;
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
        transition: transform 0.2s;
    }
    .dashboard-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 12px rgba(0,0,0,0.1);
    }
    .metric-icon {
        width: 50px;
        height: 50px;
        border-radius: 8px;
        display: grid;
        place-items: center;
    }
    .metric-value {
        font-size: 1.8rem;
        font-weight: 600;
        line-height: 1;
    }
    .metric-label {
        color: #6c757d;
        font-size: 0.9rem;
    }
    .grafico-container {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }
    .ticket-resumo {
        cursor: pointer;
        border-left: 4px solid;
        padding-left: 10px;
        transition: background-color 0.2s;
    }
    .ticket-resumo.alta { border-color: #dc3545; background-color: rgba(220, 53, 69, 0.05); }
    .ticket-resumo.media { border-color: #ffc107; background-color: rgba(255, 193, 7, 0.05); }
    .ticket-resumo.baixa { border-color: #6c757d; background-color: rgba(108, 117, 125, 0.05); }
    .ticket-resumo:hover {
        background-color: rgba(0, 0, 0, 0.05);
    }
    .modal-open .dashboard-card:hover {
        transform: none;
        box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    }
    .dashboard-card .ticket-resumo:hover {
        transform: none;
        box-shadow: none;
    }
    </style>
</head>
<body>

<?php require __DIR__ . '/navbar_tecnico.php'; ?>

<div class="container-fluid px-4 py-4">
    <!-- Barra Superior -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="text-primary fw-bold mb-0">Dashboard Técnico</h2>
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-primary">
                <i class="fas fa-sync"></i>
            </button>
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#criarTicketModal" onclick="preSelecionarReembolso()">
                <i class="fas fa-plus me-2"></i>Abrir Ticket
            </button>
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
                        <div class="metric-value"><?= $servicos_hoje ?></div>
                        <div class="metric-label">Serviços Hoje</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="dashboard-card p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="metric-icon bg-danger">
                        <i class="fas fa-dollar-sign text-white"></i>
                    </div>
                    <div>
                        <div class="metric-value">R$ <?= number_format($gastos_total, 2, ',', '.') ?></div>
                        <div class="metric-label">Gastos Totais</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="dashboard-card p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="metric-icon bg-warning">
                        <i class="fas fa-ticket-alt text-white"></i>
                    </div>
                    <div>
                        <div class="metric-value"><?= $tickets_pendentes ?></div>
                        <div class="metric-label">Tickets Pendentes</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Status dos Serviços -->
    <div class="dashboard-card p-3 mb-4">
        <h5 class="fw-bold mb-3"><i class="fas fa-tasks me-2"></i>Status dos Serviços</h5>
        <div class="row g-2">
            <?php 
            $statusMapping = [
                'abertos'    => ['label' => 'Abertos', 'color' => 'primary'],
                'concluidos' => ['label' => 'Concluídos', 'color' => 'success'],
                'cancelados' => ['label' => 'Cancelados', 'color' => 'danger']
            ];
            foreach ($statusMapping as $key => $info): 
            ?>
                <div class="col-6 col-md-3">
                    <div class="dashboard-card p-2 text-center">
                        <div class="metric-value text-<?= $info['color'] ?>">
                            <?= $status_servicos[$key] ?? 0 ?>
                        </div>
                        <div class="metric-label"><?= $info['label'] ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Gráficos -->
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="grafico-container" style="height:200px;">
                <h5 class="fw-bold mb-3"><i class="fas fa-chart-line me-2"></i>Desempenho Semanal</h5>
                <canvas id="desempenhoChart"></canvas>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="grafico-container" style="height:200px;">
                <h5 class="fw-bold mb-3"><i class="fas fa-chart-pie me-2"></i>Distribuição de Status</h5>
                <canvas id="distribuicaoChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Abas: Serviços, Tickets e Gastos -->
    <div class="dashboard-card p-3 mb-4">
        <ul class="nav nav-tabs mb-3">
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#servicos">Serviços</a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#tickets">Tickets</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#gastos">Gastos</a>
            </li>
        </ul>
        <div class="tab-content">
            <!-- Serviços -->
            <div class="tab-pane fade" id="servicos">
                <?php if (count($servicos) > 0): ?>
                    <?php foreach ($servicos as $servico): ?>
                        <?php
                        $desc_json = json_decode($servico['descricao_servicos'] ?? '', true);
                        $desc_formatada = 'Sem descrição';
                        if ($desc_json && is_array($desc_json)) {
                            $itens = array_map(function($item) {
                                return sprintf(
                                    '%s - Quantidade: %s - Valor: R$ %s',
                                    htmlspecialchars($item['nome'] ?? 'Desconhecido'),
                                    htmlspecialchars($item['quantidade'] ?? '0'),
                                    number_format((float)($item['valor_unitario'] ?? 0), 2, ',', '.')
                                );
                            }, $desc_json);
                            $desc_formatada = implode(', ', $itens);
                        }
                        ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <div class="fw-semibold"><?= htmlspecialchars($servico['cliente']) ?></div>
                                <small class="text-muted">
                                    Ordem #<?= htmlspecialchars($servico['numero_ordem']) ?> - 
                                    <?= $servico['data_servico'] ? date('d/m/Y', strtotime($servico['data_servico'])) : 'Sem data' ?>
                                    <?= $servico['hora_servico'] ? ' às ' . $servico['hora_servico'] : '' ?> - 
                                    <?= $desc_formatada ?>
                                </small>
                            </div>
                            <span class="badge bg-<?= match(strtolower($servico['status'])) {
                                'aberta' => 'primary',
                                'concluída' => 'success',
                                'cancelada' => 'danger',
                                default => 'secondary'
                            } ?>"><?= ucfirst($servico['status']) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="alert alert-info">Nenhum serviço pendente.</div>
                <?php endif; ?>
            </div>
            <!-- Tickets -->
            <div class="tab-pane fade show active" id="tickets">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <!-- Tickets recebidos do atendente -->
                <h6 class="fw-bold mb-2">Tickets Recebidos do Atendente</h6>
                <?php if (count($tickets_atendente) > 0): ?>
                    <?php foreach ($tickets_atendente as $ticket): ?>
                        <div class="alert alert-light mb-2 ticket-resumo <?= strtolower($ticket['prioridade']) ?>" data-bs-toggle="modal" data-bs-target="#responderTicketModal_atendente_<?= $ticket['id'] ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-semibold">
                                        <i class="fas me-2 <?= match($ticket['categoria']) {
                                            'falta' => 'fa-user-times',
                                            'reembolso' => 'fa-dollar-sign',
                                            'problema_servico' => 'fa-exclamation-triangle',
                                            'manutencao_equipamento' => 'fa-tools',
                                            'atraso' => 'fa-clock',
                                            'outro' => 'fa-question',
                                            default => 'fa-ticket-alt'
                                        } ?>"></i>
                                        <?= htmlspecialchars($ticket['titulo']) ?>
                                    </div>
                                    <small class="text-muted">
                                        Atendente: <?= htmlspecialchars($ticket['atendente_nome'] ?? 'Não identificado') ?> - 
                                        Criado: <?= date('d/m/Y H:i', strtotime($ticket['created_at'])) ?>
                                    </small>
                                </div>
                                <span class="badge bg-<?= match($ticket['prioridade']) {
                                    'alta' => 'danger',
                                    'media' => 'warning',
                                    'baixa' => 'secondary'
                                } ?>"><?= ucfirst($ticket['prioridade']) ?></span>
                            </div>
                        </div>

                        <!-- Modal para Responder ao Ticket do Atendente -->
                        <div class="modal fade" id="responderTicketModal_atendente_<?= $ticket['id'] ?>" tabindex="-1" aria-labelledby="responderTicketModalLabel_atendente_<?= $ticket['id'] ?>" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="responderTicketModalLabel_atendente_<?= $ticket['id'] ?>">
                                            <i class="fas me-2 <?= match($ticket['categoria']) {
                                                'falta' => 'fa-user-times',
                                                'reembolso' => 'fa-dollar-sign',
                                                'problema_servico' => 'fa-exclamation-triangle',
                                                'manutencao_equipamento' => 'fa-tools',
                                                'atraso' => 'fa-clock',
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
                                                <p><strong>Atendente:</strong> <?= htmlspecialchars($ticket['atendente_nome'] ?? 'Não identificado') ?></p>
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
                                        <?php else: ?>
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
                    <div class="alert alert-info">Nenhum ticket pendente do atendente.</div>
                <?php endif; ?>

                <!-- Tickets enviados pelo técnico -->
                <h6 class="fw-bold mt-4 mb-2">Tickets Enviados ao Atendente</h6>
                <?php if (count($tickets_tecnico) > 0): ?>
                    <?php foreach ($tickets_tecnico as $ticket): ?>
                        <div class="alert alert-light mb-2 ticket-resumo <?= strtolower($ticket['prioridade']) ?>" data-bs-toggle="modal" data-bs-target="#ticketModal_tecnico_<?= $ticket['id'] ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-semibold">
                                        <i class="fas me-2 <?= match($ticket['categoria']) {
                                            'falta' => 'fa-user-times',
                                            'reembolso' => 'fa-dollar-sign',
                                            'problema_servico' => 'fa-exclamation-triangle',
                                            'manutencao_equipamento' => 'fa-tools',
                                            'atraso' => 'fa-clock',
                                            default => 'fa-ticket-alt'
                                        } ?>"></i>
                                        <?= htmlspecialchars($ticket['titulo']) ?>
                                    </div>
                                    <small class="text-muted">
                                        Criado: <?= date('d/m/Y H:i', strtotime($ticket['created_at'])) ?>
                                        <?php if ($ticket['data_resposta']): ?>
                                            - Respondido: <?= date('d/m/Y H:i', strtotime($ticket['data_resposta'])) ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <span class="badge bg-<?= match($ticket['prioridade']) {
                                    'alta' => 'danger',
                                    'media' => 'warning',
                                    'baixa' => 'secondary'
                                } ?>"><?= ucfirst($ticket['prioridade']) ?></span>
                            </div>
                        </div>

                        <!-- Modal para Visualizar Tickets Enviados -->
                        <div class="modal fade" id="ticketModal_tecnico_<?= $ticket['id'] ?>" tabindex="-1" aria-labelledby="ticketModalLabel_tecnico_<?= $ticket['id'] ?>" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="ticketModalLabel_tecnico_<?= $ticket['id'] ?>">
                                            <i class="fas me-2 <?= match($ticket['categoria']) {
                                                'falta' => 'fa-user-times',
                                                'reembolso' => 'fa-dollar-sign',
                                                'problema_servico' => 'fa-exclamation-triangle',
                                                'manutencao_equipamento' => 'fa-tools',
                                                'atraso' => 'fa-clock',
                                                default => 'fa-ticket-alt'
                                            } ?>"></i>
                                            <?= htmlspecialchars($ticket['titulo']) ?>
                                        </h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row">
                                            <div class="col-md-6">
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
                                        <?php else: ?>
                                            <p class="text-warning">Aguardando resposta do atendente</p>
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
                    <div class="alert alert-info">Nenhum ticket enviado ao atendente.</div>
                <?php endif; ?>
            </div>
            <!-- Gastos -->
            <div class="tab-pane fade" id="gastos">
                <?php if (count($gastos_tecnicos) > 0): ?>
                    <?php foreach ($gastos_tecnicos as $gasto): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <div class="fw-semibold"><?= htmlspecialchars($gasto['descricao']) ?></div>
                                <small class="text-muted"><?= date('d/m/Y H:i', strtotime($gasto['data_registro'])) ?></small>
                            </div>
                            <span class="badge bg-danger">R$ <?= number_format($gasto['valor'], 2, ',', '.') ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="alert alert-info">Nenhum gasto registrado.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

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
                        <label for="titulo" class="form-label">Título <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="titulo" name="titulo" required>
                    </div>
                    <div class="mb-3">
                        <label for="categoria" class="form-label">Categoria <span class="text-danger">*</span></label>
                        <select class="form-select" id="categoria" name="categoria" required>
                            <option value="">Selecione uma categoria</option>
                            <option value="falta">Informar Falta</option>
                            <option value="reembolso" selected>Solicitar Reembolso</option>
                            <option value="problema_servico">Problema no Serviço</option>
                            <option value="manutencao_equipamento">Manutenção de Equipamento</option>
                            <option value="atraso">Comunicar Atraso</option>
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
                    <button type="submit" class="btn btn-primary">Criar Ticket</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bootstrap e Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gráfico de Desempenho Semanal (Line Chart)
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

    // Gráfico de Distribuição de Status (Pie Chart)
    new Chart(document.getElementById('distribuicaoChart'), {
        type: 'pie',
        data: {
            labels: ['Aberta', 'Concluída', 'Cancelada'],
            datasets: [{
                data: [
                    <?= $status_servicos['abertos'] ?? 0 ?>,
                    <?= $status_servicos['concluidos'] ?? 0 ?>,
                    <?= $status_servicos['cancelados'] ?? 0 ?>
                ],
                backgroundColor: [
                    'rgba(42, 92, 130, 0.4)',  // Aberta
                    'rgba(76, 175, 80, 0.4)',  // Concluída
                    'rgba(220, 53, 69, 0.4)'   // Cancelada
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } }
        }
    });

    // Função para pré-selecionar "Solicitar Reembolso" no modal
    function preSelecionarReembolso() {
        document.getElementById('categoria').value = 'reembolso';
        document.getElementById('titulo').value = 'Solicitação de Reembolso';
        document.getElementById('prioridade').value = 'media';
    }
});
</script>
</body>
</html>