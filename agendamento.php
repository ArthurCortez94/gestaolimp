<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config.php';

try {
    // Consultar agendamentos existentes
    $sql_agendamentos = "
        SELECT 
            a.id AS agenda_id, 
            a.data_agendamento, 
            a.hora_agendamento,
            os.id AS ordem_id, 
            os.numero_ordem, 
            os.status AS ordem_status,
            c.nome AS cliente_nome, 
            c.telefone, 
            c.endereco,
            o.status AS orcamento_status,
            u.nome AS tecnico_nome
        FROM agenda_servicos a
        JOIN ordens_servico os ON a.ordem_id = os.id
        JOIN clientes c ON os.cliente_id = c.id
        JOIN orcamentos o ON os.cliente_id = o.cliente_id
        LEFT JOIN usuarios u ON os.tecnico_id = u.id
        WHERE a.data_agendamento IS NOT NULL
        ORDER BY a.data_agendamento ASC
    ";
    $stmt = $pdo->query($sql_agendamentos);
    $agendamentos = [];
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $status = $row['ordem_status'];
        $agendamentos[] = [
            'agenda_id' => $row['agenda_id'],
            'ordem_id' => $row['ordem_id'],
            'title' => "OS #{$row['numero_ordem']} - {$row['tecnico_nome']}",
            'start' => $row['data_agendamento'] ? "{$row['data_agendamento']}T{$row['hora_agendamento']}" : null,
            'status' => $status,
            'cliente' => $row['cliente_nome'],
            'telefone' => $row['telefone'],
            'endereco' => $row['endereco'],
            'data' => $row['data_agendamento'],
            'hora' => $row['hora_agendamento'],
            'numero_ordem' => $row['numero_ordem'],
            'responsavel' => $row['tecnico_nome'],
            'orcamento_status' => $row['orcamento_status']
        ];
    }

    // Consultar pré-agendamentos pendentes (inclui aceitos mas não agendados)
    $sql_pre_agendamentos = "
        SELECT 
            os.id AS ordem_id,
            os.numero_ordem,
            c.nome AS cliente_nome,
            c.telefone,
            c.endereco,
            a.data_agendamento,
            a.hora_agendamento
        FROM ordens_servico os
        JOIN clientes c ON os.cliente_id = c.id
        LEFT JOIN agenda_servicos a ON os.id = a.ordem_id
        WHERE os.status = 'Aberta'
        AND os.tecnico_id IS NULL
        ORDER BY a.data_agendamento ASC
    ";
    $stmt_pre = $pdo->query($sql_pre_agendamentos);
    $pre_agendamentos = $stmt_pre->fetchAll(PDO::FETCH_ASSOC);

    $stmt_tecnicos = $pdo->query("SELECT id, nome FROM usuarios ORDER BY nome");
    $tecnicos = $stmt_tecnicos->fetchAll(PDO::FETCH_ASSOC);

    $mensagem = ''; // Variável para armazenar a mensagem de sucesso ou erro

    // Processar novo agendamento
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_agendamento'])) {
        $cliente_id = $_POST['cliente_id'];
        $data = $_POST['data_agendamento'];
        $hora = $_POST['hora_agendamento'];
        $status = $_POST['status'];
        $tecnico_id = $_POST['tecnico_id'];

        $valid_statuses = ['Aberta', 'Agendado', 'Em Andamento', 'Concluída', 'Cancelada'];
        $ordem_status = in_array($status, $valid_statuses) ? $status : 'Agendado';

        $stmt = $pdo->prepare("
            INSERT INTO ordens_servico 
            (cliente_id, numero_ordem, data_emissao, previsao_conclusao, tecnico_id, status) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $numero_ordem = "OS-" . time();
        $data_emissao = date('Y-m-d');
        $previsao_conclusao = $data;
        $stmt->execute([$cliente_id, $numero_ordem, $data_emissao, $previsao_conclusao, $tecnico_id, $ordem_status]);
        $ordem_id = $pdo->lastInsertId();

        $stmt = $pdo->prepare("
            INSERT INTO agenda_servicos 
            (ordem_id, data_agendamento, hora_agendamento) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$ordem_id, $data, $hora]);

        $mensagem = "Agendamento criado com sucesso!";
    }

    // Processar aceitação de pré-agendamento
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aceitar_pre_agendamento'])) {
        $ordem_id = $_POST['ordem_id'];
        $stmt = $pdo->prepare("
            UPDATE ordens_servico 
            SET numero_ordem = ?, status = 'Aberta'
            WHERE id = ?
        ");
        $novo_numero_ordem = "OS-" . time();
        $stmt->execute([$novo_numero_ordem, $ordem_id]);
        $mensagem = "Pré-agendamento aceito com sucesso! Número da OS: $novo_numero_ordem";

        // Atualizar a lista de pré-agendamentos
        $stmt_pre = $pdo->query($sql_pre_agendamentos);
        $pre_agendamentos = $stmt_pre->fetchAll(PDO::FETCH_ASSOC);
    }

    // Processar recusa de pré-agendamento
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recusar_pre_agendamento'])) {
        $ordem_id = $_POST['ordem_id'];
        $stmt = $pdo->prepare("
            UPDATE ordens_servico 
            SET status = 'Cancelada'
            WHERE id = ?
        ");
        $stmt->execute([$ordem_id]);
        $mensagem = "Pré-agendamento recusado com sucesso!";

        // Atualizar a lista de pré-agendamentos
        $stmt_pre = $pdo->query($sql_pre_agendamentos);
        $pre_agendamentos = $stmt_pre->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    die("Erro ao processar agendamento: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agendamento de Serviços - UltraLimp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">
    <style>
        :root {
            --primary: #1E3A8A;
            --secondary: #10B981;
            --accent: #F59E0B;
            --light: #F9FAFB;
            --dark: #111827;
            --gray: #6B7280;
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, var(--light) 0%, #E5E7EB 100%);
            color: var(--dark);
            margin: 0;
            padding: 0;
        }
        .container-fluid {
            padding: 1rem;
            max-width: 1400px;
            margin: 0 auto;
        }
        .header-section {
            background: linear-gradient(120deg, var(--primary) 0%, #2563EB 100%);
            color: white;
            padding: 1rem;
            border-radius: 16px 16px 0 0;
            box-shadow: var(--shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .header-section h2 {
            font-weight: 600;
            margin: 0;
            font-size: 1.5rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        .dashboard-card {
            background: white;
            border-radius: 0 0 16px 16px;
            box-shadow: var(--shadow);
            padding: 1.5rem;
            transition: transform 0.3s ease;
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
        }
        .btn-primary, .btn-secondary {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            box-shadow: 0 2px 10px rgba(30, 58, 138, 0.3);
        }
        .btn-primary {
            background: var(--primary);
            border: none;
        }
        .btn-primary:hover {
            background: #2563EB;
            transform: translateY(-2px);
        }
        .btn-secondary {
            background: var(--secondary);
            border: none;
        }
        .btn-secondary:hover {
            background: #059669;
            transform: translateY(-2px);
        }
        .btn-danger {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }
        .btn-warning {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.9rem;
            cursor: default;
        }
        .filter-buttons {
            margin-bottom: 1.5rem;
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .filter-btn {
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        .filter-btn.active {
            background: var(--primary);
            color: white;
            box-shadow: 0 4px 10px rgba(30, 58, 138, 0.3);
        }
        .fc .fc-toolbar {
            background: transparent;
            padding: 0.5rem 0;
            border-bottom: 1px solid #E5E7EB;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .fc-button {
            background: var(--primary) !important;
            border: none !important;
            border-radius: 6px !important;
            padding: 0.4rem 0.8rem !important;
            font-size: 0.8rem !important;
            text-transform: uppercase;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        .fc-button:hover {
            background: #2563EB !important;
            transform: translateY(-2px);
        }
        .fc-daygrid-day {
            background: white;
            border-radius: 6px;
            transition: all 0.3s ease;
        }
        .fc-daygrid-day:hover {
            background: #F3F4F6;
        }
        .fc-event {
            background: linear-gradient(135deg, var(--primary) 0%, #3B82F6 100%);
            color: white;
            border: none;
            border-radius: 6px;
            padding: 0.4rem;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            display: flex;
            flex-direction: column;
            gap: 0.2rem;
        }
        .fc-event:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        .fc-event.status-agendado { background: linear-gradient(135deg, #1E3A8A 0%, #3B82F6 100%); }
        .fc-event.status-em-andamento { background: linear-gradient(135deg, #D97706 0%, #F59E0B 100%); }
        .fc-event.status-concluída { background: linear-gradient(135deg, #059669 0%, #10B981 100%); }
        .fc-event.status-cancelada { background: linear-gradient(135deg, #4B5563 0%, #6B7280 100%); }
        .fc-event.status-aberta { background: linear-gradient(135deg, #17A2B8 0%, #22D3EE 100%); }
        .fc-event.status-atrasado { background: linear-gradient(135deg, #DC3545 0%, #EF4444 100%); }
        .fc-event-time { font-weight: 600; }
        .fc-event-title { font-weight: 500; }
        .modal-content {
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            border: none;
            overflow: hidden;
            max-width: 90%;
            margin: 1rem auto;
        }
        .modal-header {
            background: linear-gradient(120deg, var(--primary) 0%, #2563EB 100%);
            color: white;
            padding: 1rem;
            border-bottom: none;
        }
        .modal-body {
            padding: 1.5rem;
            background: white;
        }
        .modal-body p {
            margin: 0.5rem 0;
            display: flex;
            align-items: center;
            font-size: 0.9rem;
        }
        .modal-body p strong {
            color: var(--primary);
            min-width: 100px;
            font-weight: 600;
        }
        .modal-body p i {
            margin-right: 8px;
            color: var(--gray);
            font-size: 1rem;
        }
        .modal-footer {
            padding: 1rem;
            border-top: none;
            background: #F9FAFB;
        }
        .badge {
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
            border-radius: 20px;
        }
        .pre-agendamento-table {
            margin-top: 2rem;
        }
        .pre-agendamento-table th, .pre-agendamento-table td {
            vertical-align: middle;
        }
        .pre-agendamento-table .btn {
            margin: 0 0.25rem;
        }

        @media (max-width: 768px) {
            .container-fluid { padding: 0.5rem; }
            .header-section { padding: 0.75rem; flex-direction: column; align-items: flex-start; }
            .header-section h2 { font-size: 1.25rem; }
            .header-section .btn-primary { width: 100%; margin: 0.25rem 0; }
            .dashboard-card { padding: 1rem; }
            .filter-buttons { justify-content: center; }
            .filter-btn { padding: 0.3rem 0.8rem; font-size: 0.75rem; }
            .fc .fc-toolbar-title { font-size: 1rem; }
            .fc-event { font-size: 0.7rem; padding: 0.3rem; }
            .fc-event-time, .fc-event-title { display: inline; }
            .fc-event div:last-child { display: none; }
            .modal-body p { font-size: 0.85rem; }
            .modal-body p strong { min-width: 80px; }
            .pre-agendamento-table { font-size: 0.85rem; }
        }

        @media (max-width: 576px) {
            .fc .fc-toolbar { flex-direction: column; align-items: center; }
            .fc-button { padding: 0.3rem 0.6rem !important; font-size: 0.7rem !important; }
            .pre-agendamento-table .btn { width: 100%; margin: 0.25rem 0; }
        }
    </style>
</head>
<body>
    <?php require __DIR__ . '/navbar.php'; ?>

    <div class="container-fluid">
        <div class="header-section">
            <h2><i class="fas fa-calendar-alt me-2"></i>Agendamento de Serviços</h2>
            <div>
                <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="fas fa-plus me-1"></i>Novo Agendamento
                </button>
                <a href="dashboard.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left me-1"></i>Voltar ao Dashboard
                </a>
            </div>
        </div>

        <div class="dashboard-card">
            <?php if ($mensagem): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($mensagem) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="filter-buttons">
                <button class="btn filter-btn btn-outline-primary active" data-filter="all">Todos</button>
                <button class="btn filter-btn btn-outline-primary" data-filter="Aberta">Abertas</button>
                <button class="btn filter-btn btn-outline-primary" data-filter="Agendado">Agendados</button>
                <button class="btn filter-btn btn-outline-warning" data-filter="Em Andamento">Em Andamento</button>
                <button class="btn filter-btn btn-outline-success" data-filter="Concluída">Concluídos</button>
                <button class="btn filter-btn btn-outline-danger" data-filter="Atrasado">Atrasados</button>
                <button class="btn filter-btn btn-outline-secondary" data-filter="Cancelada">Cancelados</button>
            </div>
            <div id="calendario"></div>

            <!-- Seção de Pré-Agendamentos -->
            <div class="pre-agendamento-table">
                <h5 class="mt-4 mb-3"><i class="fas fa-list me-2"></i>Solicitações de Pré-Agendamento</h5>
                <?php if (count($pre_agendamentos) > 0): ?>
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Cliente</th>
                                <th>Telefone</th>
                                <th>Endereço</th>
                                <th>Data</th>
                                <th>Hora</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pre_agendamentos as $pre): ?>
                                <tr>
                                    <td><?= htmlspecialchars($pre['numero_ordem']) ?></td>
                                    <td><?= htmlspecialchars($pre['cliente_nome']) ?></td>
                                    <td><?= htmlspecialchars($pre['telefone']) ?></td>
                                    <td><?= htmlspecialchars($pre['endereco']) ?></td>
                                    <td><?= $pre['data_agendamento'] ? date('d/m/Y', strtotime($pre['data_agendamento'])) : 'N/A' ?></td>
                                    <td><?= htmlspecialchars($pre['hora_agendamento']) ?></td>
                                    <td>
                                        <?php if (strpos($pre['numero_ordem'], 'PRE-') === 0): ?>
                                            <form method="POST" action="" style="display:inline;">
                                                <input type="hidden" name="ordem_id" value="<?= $pre['ordem_id'] ?>">
                                                <input type="hidden" name="aceitar_pre_agendamento" value="1">
                                                <button type="submit" class="btn btn-secondary btn-sm">
                                                    <i class="fas fa-check me-1"></i>Aceitar
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-warning btn-sm" disabled>
                                                <i class="fas fa-hourglass-half me-1"></i>Aguardando Emissão de OS
                                            </button>
                                        <?php endif; ?>
                                        <form method="POST" action="" style="display:inline;">
                                            <input type="hidden" name="ordem_id" value="<?= $pre['ordem_id'] ?>">
                                            <input type="hidden" name="recusar_pre_agendamento" value="1">
                                            <button type="submit" class="btn btn-danger btn-sm">
                                                <i class="fas fa-times me-1"></i>Recusar
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-muted">Nenhuma solicitação de pré-agendamento pendente.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="modal fade" id="infoModal" tabindex="-1" aria-labelledby="infoModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="infoModalLabel"><i class="fas fa-info-circle me-2"></i>Detalhes do Agendamento</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <p><i class="fas fa-user-tie me-2"></i><strong>Técnico:</strong> <span id="modal_tecnico"></span></p>
                    <p><i class="fas fa-user me-2"></i><strong>Cliente:</strong> <span id="modal_cliente"></span></p>
                    <p><i class="fas fa-map-marker-alt me-2"></i><strong>Endereço:</strong> <span id="modal_endereco"></span></p>
                    <p><i class="fas fa-calendar me-2"></i><strong>Data:</strong> <span id="modal_data"></span></p>
                    <p><i class="fas fa-clock me-2"></i><strong>Hora:</strong> <span id="modal_hora"></span></p>
                    <p><i class="fas fa-tasks me-2"></i><strong>Status:</strong> <span id="modal_status" class="badge bg-primary"></span></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addModalLabel"><i class="fas fa-plus-circle me-2"></i>Novo Agendamento</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="add_agendamento" value="1">
                        <div class="mb-3">
                            <label><i class="fas fa-user me-2"></i><strong>ID do Cliente:</strong></label>
                            <input type="number" name="cliente_id" class="form-control" required placeholder="Digite o ID do cliente">
                        </div>
                        <div class="mb-3">
                            <label><i class="fas fa-user-tie me-2"></i><strong>Técnico:</strong></label>
                            <select name="tecnico_id" class="form-select" required>
                                <option value="">Selecione um técnico</option>
                                <?php foreach ($tecnicos as $tecnico): ?>
                                    <option value="<?php echo $tecnico['id']; ?>">
                                        <?php echo htmlspecialchars($tecnico['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label><i class="fas fa-calendar me-2"></i><strong>Data:</strong></label>
                            <input type="date" name="data_agendamento" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label><i class="fas fa-clock me-2"></i><strong>Hora:</strong></label>
                            <input type="time" name="hora_agendamento" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label><i class="fas fa-tasks me-2"></i><strong>Status:</strong></label>
                            <select name="status" class="form-select" required>
                                <option value="Aberta">Aberta</option>
                                <option value="Agendado" selected>Agendado</option>
                                <option value="Em Andamento">Em Andamento</option>
                                <option value="Concluída">Concluída</option>
                                <option value="Cancelada">Cancelada</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">Adicionar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales/pt-br.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var calendarEl = document.getElementById('calendario');
        var allEvents = <?php echo json_encode($agendamentos); ?>.filter(event => event.start);
        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: window.innerWidth < 768 ? 'timeGridDay' : 'dayGridMonth',
            locale: 'pt-br',
            events: allEvents,
            eventClassNames: function(arg) {
                return ['status-' + arg.event.extendedProps.status.toLowerCase().replace(' ', '-')];
            },
            eventContent: function(arg) {
                return {
                    html: `
                        <div class="fc-event-time">${arg.timeText}</div>
                        <div class="fc-event-title">${arg.event.title}</div>
                        <div style="font-size: 0.8rem; opacity: 0.9;">${arg.event.extendedProps.cliente}</div>
                    `
                };
            },
            eventClick: function(info) {
                var event = info.event;
                document.getElementById('modal_tecnico').textContent = event.extendedProps.responsavel || 'Não atribuído';
                document.getElementById('modal_cliente').textContent = event.extendedProps.cliente || 'N/A';
                document.getElementById('modal_endereco').textContent = event.extendedProps.endereco || 'N/A';
                
                var startDate = event.start;
                var dataFormatada = startDate ? 
                    ('0' + startDate.getDate()).slice(-2) + '/' + 
                    ('0' + (startDate.getMonth() + 1)).slice(-2) + '/' + 
                    startDate.getFullYear() : 'N/A';
                document.getElementById('modal_data').textContent = dataFormatada;

                document.getElementById('modal_hora').textContent = event.extendedProps.hora || 'N/A';
                document.getElementById('modal_status').textContent = event.extendedProps.status;

                var statusBadge = document.getElementById('modal_status');
                statusBadge.className = 'badge';
                switch (event.extendedProps.status) {
                    case 'Aberta': statusBadge.classList.add('bg-info'); break;
                    case 'Agendado': statusBadge.classList.add('bg-primary'); break;
                    case 'Em Andamento': statusBadge.classList.add('bg-warning'); break;
                    case 'Concluída': statusBadge.classList.add('bg-success'); break;
                    case 'Cancelada': statusBadge.classList.add('bg-dark'); break;
                    case 'Atrasado': statusBadge.classList.add('bg-danger'); break;
                    default: statusBadge.classList.add('bg-secondary');
                }

                var modal = new bootstrap.Modal(document.getElementById('infoModal'));
                modal.show();
            },
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            slotMinTime: '08:00:00',
            slotMaxTime: '20:00:00',
            height: 'auto'
        });
        calendar.render();

        document.querySelectorAll('.filter-btn').forEach(button => {
            button.addEventListener('click', function() {
                document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');
                var filter = this.getAttribute('data-filter');
                var filteredEvents = filter === 'all' ? allEvents : allEvents.filter(event => event.status === filter);
                calendar.removeAllEvents();
                calendar.addEventSource(filteredEvents);
            });
        });

        window.addEventListener('resize', function() {
            if (window.innerWidth < 768 && calendar.view.type !== 'timeGridDay') {
                calendar.changeView('timeGridDay');
            } else if (window.innerWidth >= 768 && calendar.view.type === 'timeGridDay') {
                calendar.changeView('dayGridMonth');
            }
        });
    });
    </script>
</body>
</html>