<?php
require_once 'config.php';

try {
    // Consultar agendamentos existentes para determinar horários ocupados
    $sql_agendamentos = "
        SELECT a.data_agendamento, TIME_FORMAT(a.hora_agendamento, '%H:%i') as hora_agendamento, COUNT(*) as total
        FROM agenda_servicos a
        JOIN ordens_servico os ON a.ordem_id = os.id
        WHERE a.data_agendamento IS NOT NULL
        AND a.data_agendamento >= CURDATE()
        AND os.status NOT IN ('Cancelada', 'Concluída')
        GROUP BY a.data_agendamento, TIME_FORMAT(a.hora_agendamento, '%H:%i')
        ORDER BY a.data_agendamento ASC
    ";
    $stmt = $pdo->query($sql_agendamentos);
    $agendamentos_existentes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_pre_agendamento'])) {
        $nome_cliente = trim($_POST['nome_cliente'] ?? '');
        $telefone = trim($_POST['telefone'] ?? '');
        $endereco = trim($_POST['endereco'] ?? '');
        $data = $_POST['data_agendamento'];
        $hora = $_POST['hora_agendamento'];

        // Validações básicas
        if (empty($nome_cliente) || empty($telefone) || empty($endereco) || empty($data) || empty($hora)) {
            $error = "Todos os campos são obrigatórios.";
        } else {
            // Verificar se o dia é de segunda a sexta
            $dia_semana = date('N', strtotime($data)); // 1 (segunda) a 7 (domingo)
            if ($dia_semana > 5) {
                $error = "Agendamentos só podem ser feitos de segunda a sexta-feira.";
            }
            // Verificar se o horário é válido
            $horarios_validos = ['08:30', '10:30', '13:30'];
            if (!in_array($hora, $horarios_validos)) {
                $error = "Horário inválido. Escolha entre 08:30, 10:30 ou 13:30.";
            }
            // Verificar limite de 2 vagas por horário
            $vagas_ocupadas = 0;
            foreach ($agendamentos_existentes as $agendamento) {
                if ($agendamento['data_agendamento'] === $data && $agendamento['hora_agendamento'] === $hora) {
                    $vagas_ocupadas = $agendamento['total'];
                    break;
                }
            }
            if ($vagas_ocupadas >= 2) {
                $error = "Este horário já atingiu o limite de 2 agendamentos. Escolha outro horário.";
            }

            if (!isset($error)) {
                // Inserir cliente temporariamente
                $stmt = $pdo->prepare("INSERT INTO clientes (nome, telefone, endereco) VALUES (?, ?, ?)");
                $stmt->execute([$nome_cliente, $telefone, $endereco]);
                $cliente_id = $pdo->lastInsertId();

                // Criar ordem de serviço com status 'Aberta' e tecnico_id como NULL
                $stmt = $pdo->prepare("
                    INSERT INTO ordens_servico 
                    (cliente_id, numero_ordem, data_emissao, previsao_conclusao, tecnico_id, status) 
                    VALUES (?, ?, ?, ?, NULL, 'Aberta')
                ");
                $numero_ordem = "PRE-" . time();
                $data_emissao = date('Y-m-d');
                $previsao_conclusao = $data;
                $stmt->execute([$cliente_id, $numero_ordem, $data_emissao, $previsao_conclusao]);

                $ordem_id = $pdo->lastInsertId();

                // Inserir na agenda de serviços
                $stmt = $pdo->prepare("
                    INSERT INTO agenda_servicos 
                    (ordem_id, data_agendamento, hora_agendamento) 
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$ordem_id, $data, $hora]);

                $success = "Pré-agendamento enviado com sucesso! Aguarde a aprovação do atendente.";
            }
        }
    }

} catch (PDOException $e) {
    die("Erro ao processar pré-agendamento: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pré-Agendamento Rápido - UltraLimp</title>
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
            max-width: 1200px;
            margin: 0 auto;
        }
        .header-section {
            background: linear-gradient(120deg, var(--primary) 0%, #2563EB 100%);
            color: white;
            padding: 1rem;
            border-radius: 16px 16px 0 0;
            box-shadow: var(--shadow);
            text-align: center;
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
        }
        .btn-primary {
            background: var(--primary);
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background: #2563EB;
            transform: translateY(-2px);
        }
        .fc .fc-daygrid-day {
            background: white;
            border-radius: 6px;
            cursor: pointer;
        }
        .fc .fc-daygrid-day:hover {
            background: #F3F4F6;
        }
        .fc .fc-daygrid-day.fc-day-disabled {
            background: #E5E7EB;
            cursor: not-allowed;
        }
        .fc .fc-daygrid-day.fc-day-sat, .fc .fc-daygrid-day.fc-day-sun {
            background: #F3F4F6;
            cursor: not-allowed;
        }
        .fc .fc-toolbar {
            background: transparent;
            padding: 0.5rem 0;
            border-bottom: 1px solid #E5E7EB;
        }
        .fc-button {
            background: var(--primary) !important;
            border: none !important;
            border-radius: 6px !important;
            padding: 0.4rem 0.8rem !important;
            font-size: 0.8rem !important;
            text-transform: uppercase;
            transition: all 0.3s ease;
        }
        .fc-button:hover {
            background: #2563EB !important;
        }
        .modal-content {
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            border: none;
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
        .modal-footer {
            padding: 1rem;
            border-top: none;
            background: #F9FAFB;
        }
        .alert-info {
            background: #DBEAFE;
            color: #1E3A8A;
            border: none;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        @media (max-width: 768px) {
            .container-fluid { padding: 0.5rem; }
            .header-section h2 { font-size: 1.25rem; }
            .dashboard-card { padding: 1rem; }
            .fc .fc-toolbar-title { font-size: 1rem; }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="header-section">
            <h2><i class="fas fa-calendar-alt me-2"></i>Pré-Agendamento Rápido</h2>
        </div>

        <div class="dashboard-card">
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php elseif (isset($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>Escolha um dia (segunda a sexta) e um horário disponível (08:30, 10:30 ou 13:30). Cada horário tem limite de 2 vagas. Seu agendamento será enviado para aprovação e você será notificado assim que for confirmado.
            </div>
            <div id="calendario"></div>
        </div>
    </div>

    <div class="modal fade" id="agendamentoModal" tabindex="-1" aria-labelledby="agendamentoModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="agendamentoModalLabel"><i class="fas fa-plus-circle me-2"></i>Confirmar Pré-Agendamento</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="add_pre_agendamento" value="1">
                        <input type="hidden" name="data_agendamento" id="data_agendamento">
                        <input type="hidden" name="hora_agendamento" id="hora_agendamento">
                        <div class="mb-3">
                            <label class="form-label"><i class="fas fa-user me-2"></i><strong>Nome:</strong></label>
                            <input type="text" name="nome_cliente" class="form-control" required placeholder="Digite seu nome">
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><i class="fas fa-phone me-2"></i><strong>Telefone:</strong></label>
                            <input type="text" name="telefone" class="form-control" required placeholder="Digite seu telefone">
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><i class="fas fa-map-marker-alt me-2"></i><strong>Endereço:</strong></label>
                            <textarea name="endereco" class="form-control" required placeholder="Digite seu endereço"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><i class="fas fa-calendar me-2"></i><strong>Data Selecionada:</strong></label>
                            <input type="text" class="form-control" id="data_display" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><i class="fas fa-clock me-2"></i><strong>Hora Selecionada:</strong></label>
                            <select name="hora_agendamento_select" class="form-select" required onchange="document.getElementById('hora_agendamento').value = this.value;">
                                <option value="">Selecione um horário</option>
                                <?php
                                $horarios_disponiveis = ['08:30', '10:30', '13:30'];
                                $data_selecionada = $_POST['data_agendamento'] ?? '';
                                foreach ($horarios_disponiveis as $hora) {
                                    $vagas_ocupadas = 0;
                                    foreach ($agendamentos_existentes as $agendamento) {
                                        if ($agendamento['data_agendamento'] === $data_selecionada && $agendamento['hora_agendamento'] === $hora) {
                                            $vagas_ocupadas = $agendamento['total'];
                                            break;
                                        }
                                    }
                                    $disponivel = $vagas_ocupadas < 2;
                                    echo "<option value='$hora'" . (!$disponivel && $data_selecionada ? ' disabled' : '') . ">$hora" . (!$disponivel && $data_selecionada ? ' (Lotado)' : '') . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Enviar Solicitação</button>
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
        var agendamentosExistentes = <?php echo json_encode($agendamentos_existentes); ?>;
        
        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            locale: 'pt-br',
            selectable: true,
            validRange: {
                start: new Date() // Apenas datas futuras
            },
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: ''
            },
            dayCellClassNames: function(arg) {
                var dateStr = arg.date.toISOString().split('T')[0];
                var diaSemana = arg.date.getDay(); // 0 (domingo) a 6 (sábado)
                if (diaSemana === 0 || diaSemana === 6) {
                    return ['fc-day-disabled']; // Desabilitar sábado e domingo
                }
                return [];
            },
            dateClick: function(info) {
                var selectedDate = info.dateStr;
                var today = new Date().toISOString().split('T')[0];
                var diaSemana = info.date.getDay(); // 0 (domingo) a 6 (sábado)

                if (selectedDate < today || diaSemana === 0 || diaSemana === 6) {
                    alert('Agendamentos só podem ser feitos de segunda a sexta-feira a partir de hoje.');
                    return;
                }

                document.getElementById('data_agendamento').value = selectedDate;
                document.getElementById('data_display').value = selectedDate.split('-').reverse().join('/');
                document.getElementById('hora_agendamento').value = '';
                document.querySelector('select[name="hora_agendamento_select"]').value = '';

                // Atualizar disponibilidade de horários dinamicamente
                var selectHora = document.querySelector('select[name="hora_agendamento_select"]');
                var options = selectHora.querySelectorAll('option:not(:first-child)');
                options.forEach(option => {
                    option.disabled = false;
                    option.textContent = option.value;
                    agendamentosExistentes.forEach(agendamento => {
                        if (agendamento.data_agendamento === selectedDate && agendamento.hora_agendamento === option.value && agendamento.total >= 2) {
                            option.disabled = true;
                            option.textContent = option.value + ' (Lotado)';
                        }
                    });
                });

                var modal = new bootstrap.Modal(document.getElementById('agendamentoModal'));
                modal.show();
            },
            height: 'auto'
        });
        calendar.render();

        // Log para depuração
        console.log('Agendamentos existentes:', agendamentosExistentes);
    });
    </script>
</body>
</html>