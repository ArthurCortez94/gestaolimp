<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/config.php';

// Verificação de segurança
if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit();
}

// Controle de inatividade (30 minutos)
$_SESSION['user_last_active'] = $_SESSION['user_last_active'] ?? time();
if ((time() - $_SESSION['user_last_active']) > 1800) {
    session_unset();
    session_destroy();
    header("Location: /login.php?expired=1");
    exit();
}
$_SESSION['user_last_active'] = time();

function getMetric(PDO $pdo, string $query, array $params = []): float {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $value = $stmt->fetchColumn();
    return is_numeric($value) ? floatval($value) : 0.0;
}

$error = '';
$success = '';
$atendente_id = $_SESSION['user_id'];

// Processar adição de lembrete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adicionar_lembrete'])) {
    $texto = trim($_POST['texto_lembrete'] ?? '');
    if (empty($texto)) {
        $error = "O lembrete não pode estar vazio.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO lembretes (usuario_id, texto) VALUES (?, ?)");
        $stmt->execute([$atendente_id, $texto]);
        $success = "Lembrete adicionado com sucesso!";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Processar conclusão de lembrete
if (isset($_GET['concluir_lembrete'])) {
    $lembrete_id = (int)$_GET['concluir_lembrete'];
    $stmt = $pdo->prepare("UPDATE lembretes SET concluido = 1 WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$lembrete_id, $atendente_id]);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Processar exclusão de lembrete
if (isset($_GET['excluir_lembrete'])) {
    $lembrete_id = (int)$_GET['excluir_lembrete'];
    $stmt = $pdo->prepare("DELETE FROM lembretes WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$lembrete_id, $atendente_id]);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

try {
    // Processar resposta ao ticket
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

    // Processar criação de ticket
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
            $stmt->execute([$atendente_id, $tecnico_id, $titulo, $descricao, $prioridade, $categoria]);
            $success = "Ticket enviado ao técnico com sucesso!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }

    // Métricas principais
    $orcamentos_dia = getMetric($pdo, "SELECT COUNT(*) FROM orcamentos WHERE DATE(validade) = CURDATE()");
    $valores_recebidos = getMetric($pdo, "SELECT COALESCE(SUM(total), 0) FROM ordens_servico WHERE status = 'Concluída'");
    $valores_a_receber = getMetric($pdo, "SELECT COALESCE(SUM(total), 0) FROM ordens_servico WHERE status = 'Agendado'");
    
    // Dados de status de orçamentos
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

    // Contas a Pagar e Receber do mês atual
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

    // Tickets
    $tickets_atendente = $pdo->query("
        SELECT tat.*, u.nome AS tecnico_nome 
        FROM tickets_atendente_tecnico tat 
        LEFT JOIN usuarios u ON tat.tecnico_id = u.id 
        WHERE tat.atendente_id = $atendente_id
        ORDER BY FIELD(tat.prioridade, 'alta', 'media', 'baixa'), tat.created_at DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
    $tickets_tecnico = $pdo->query("
        SELECT t.*, u.nome AS tecnico_nome 
        FROM tickets t 
        LEFT JOIN usuarios u ON t.atribuido_a = u.id 
        WHERE t.status = 'aberto'
        ORDER BY FIELD(t.prioridade, 'alta', 'media', 'baixa'), t.created_at DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Lavanderia
    $lavanderia = $pdo->query("
        SELECT l.*, COALESCE(c.nome, 'Cliente não encontrado') AS cliente 
        FROM lavanderia l 
        LEFT JOIN clientes c ON l.cliente_id = c.id 
        ORDER BY l.data_prevista_entrega ASC 
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Processar o campo 'itens' (JSON) para cada registro da lavanderia
    foreach ($lavanderia as &$item) {
        $item['itens'] = json_decode($item['itens'], true) ?? [];
    }
    unset($item);

    $tecnicos = $pdo->query("SELECT id, nome FROM usuarios WHERE cargo = 'tecnico' ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);

    // Próximos 5 serviços agendados
    $proximos_servicos = $pdo->query("
        SELECT 
            os.id, 
            os.numero_ordem, 
            os.data_servico, 
            os.hora_servico, 
            os.status, 
            c.nome AS cliente_nome,
            u.nome AS tecnico_nome
        FROM ordens_servico os
        LEFT JOIN clientes c ON os.cliente_id = c.id
        LEFT JOIN usuarios u ON os.tecnico_id = u.id
        WHERE os.status = 'Agendado' AND os.data_servico >= CURDATE()
        ORDER BY os.data_servico ASC, os.hora_servico ASC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Lembretes
    $lembretes = $pdo->query("
        SELECT id, texto, data_criacao, concluido
        FROM lembretes
        WHERE usuario_id = $atendente_id AND concluido = 0
        ORDER BY data_criacao DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro ao buscar dados: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Ultra Multiservice</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/animate.css@4.1.1/animate.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #2563EB;
            --primary-dark: #1D4ED8;
            --secondary: #10B981;
            --secondary-dark: #059669;
            --accent: #F59E0B;
            --accent-dark: #D97706;
            --success: #22C55E;
            --warning: #EAB308;
            --danger: #EF4444;
            --info: #3B82F6;
            --light: #F8FAFC;
            --light-gray: #F1F5F9;
            --gray: #64748B;
            --dark: #0F172A;
            --white: #FFFFFF;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --gradient-primary: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            --gradient-secondary: linear-gradient(135deg, var(--secondary) 0%, var(--secondary-dark) 100%);
            --gradient-accent: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%);
            --border-radius: 12px;
            --border-radius-lg: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, var(--light) 0%, #E2E8F0 100%);
            color: var(--dark);
            line-height: 1.6;
            padding-top: 80px;
            overflow-x: hidden;
        }

        /* Header Moderno */
        .header-section {
            background: var(--gradient-primary);
            color: white;
            padding: 2rem;
            border-radius: var(--border-radius-lg);
            margin-bottom: 2rem;
            box-shadow: var(--shadow-xl);
            position: relative;
            overflow: hidden;
        }

        .header-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="50" cy="50" r="1" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.3;
        }

        .header-section .content {
            position: relative;
            z-index: 1;
        }

        .header-section h2 {
            font-weight: 700;
            font-size: 2rem;
            margin: 0;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .header-section .subtitle {
            opacity: 0.9;
            font-size: 1.1rem;
            margin-top: 0.5rem;
        }

        /* Cards Modernos */
        .dashboard-card {
            background: var(--white);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow);
            padding: 2rem;
            transition: var(--transition);
            margin-bottom: 2rem;
            border: 1px solid rgba(226, 232, 240, 0.8);
            position: relative;
            overflow: hidden;
        }

        .dashboard-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-primary);
            opacity: 0;
            transition: var(--transition);
        }

        .dashboard-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-xl);
        }

        .dashboard-card:hover::before {
            opacity: 1;
        }

        /* Métricas Avançadas */
        .metric-card {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            text-align: center;
            transition: var(--transition);
            border: 1px solid rgba(226, 232, 240, 0.8);
            position: relative;
            overflow: hidden;
            height: 100%;
        }

        .metric-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.05) 0%, rgba(16, 185, 129, 0.05) 100%);
            opacity: 0;
            transition: var(--transition);
        }

        .metric-card:hover::before {
            opacity: 1;
        }

        .metric-card:hover {
            transform: translateY(-4px) scale(1.02);
            box-shadow: var(--shadow-lg);
        }

        .metric-icon {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.8rem;
            color: white;
            position: relative;
            z-index: 1;
            box-shadow: var(--shadow);
        }

        .metric-value {
            font-size: 2rem;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
        }

        .metric-label {
            font-size: 0.95rem;
            color: var(--gray);
            font-weight: 500;
            position: relative;
            z-index: 1;
        }

        .metric-trend {
            font-size: 0.85rem;
            margin-top: 0.5rem;
            font-weight: 600;
            position: relative;
            z-index: 1;
        }

        /* Status Cards Melhorados */
        .status-card {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 1.25rem;
            text-align: center;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            border: 1px solid rgba(226, 232, 240, 0.8);
            height: 100%;
        }

        .status-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .status-value {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .status-label {
            font-size: 0.85rem;
            color: var(--gray);
            font-weight: 500;
        }

        /* Dashboard Items Modernos */
        .dashboard-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 0.75rem;
            background: var(--light-gray);
            transition: var(--transition);
            cursor: pointer;
            border: 1px solid transparent;
        }

        .dashboard-item:hover {
            background: var(--white);
            box-shadow: var(--shadow);
            border-color: rgba(37, 99, 235, 0.2);
            transform: translateX(4px);
        }

        .dashboard-item i {
            margin-right: 1rem;
            font-size: 1.25rem;
            width: 24px;
            text-align: center;
        }

        .dashboard-item .info {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            overflow: hidden;
        }

        .dashboard-item .info span {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-size: 0.9rem;
        }

        .dashboard-item .info .fw-semibold {
            font-weight: 600;
            color: var(--dark);
        }

        .dashboard-item .actions {
            margin-left: 1rem;
            flex-shrink: 0;
            display: flex;
            gap: 0.5rem;
        }

        /* Botões Modernos */
        .btn {
            border-radius: var(--border-radius);
            font-weight: 600;
            transition: var(--transition);
            border: none;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn-primary {
            background: var(--gradient-primary);
            color: white;
            box-shadow: var(--shadow);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            color: white;
        }

        .btn-secondary {
            background: var(--gradient-secondary);
            color: white;
            box-shadow: var(--shadow);
        }

        .btn-secondary:hover {
            background: var(--secondary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            color: white;
        }

        .btn-outline-light {
            border: 2px solid rgba(255,255,255,0.3);
            color: white;
            background: transparent;
        }

        .btn-outline-light:hover {
            background: rgba(255,255,255,0.1);
            border-color: white;
            color: white;
            transform: translateY(-2px);
        }

        /* Badges Modernos */
        .badge {
            font-weight: 600;
            padding: 0.5rem 0.75rem;
            border-radius: 6px;
            font-size: 0.75rem;
        }

        /* Alertas Modernos */
        .alert {
            border-radius: var(--border-radius);
            border: none;
            box-shadow: var(--shadow);
            font-weight: 500;
        }

        /* Navegação por Tabs */
        .nav-tabs {
            border: none;
            margin-bottom: 1.5rem;
        }

        .nav-tabs .nav-link {
            color: var(--gray);
            font-weight: 600;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            border: none;
            padding: 1rem 1.5rem;
            transition: var(--transition);
        }

        .nav-tabs .nav-link.active {
            background: var(--gradient-primary);
            color: white;
            box-shadow: var(--shadow);
        }

        .nav-tabs .nav-link:hover:not(.active) {
            background: var(--light-gray);
            color: var(--dark);
        }

        /* Gráficos Container */
        .chart-container {
            position: relative;
            height: 300px;
            margin: 1rem 0;
        }

        /* AI Assistant Floating */
        .ai-assistant {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            z-index: 1000;
        }

        .ai-assistant-btn {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--gradient-accent);
            color: white;
            border: none;
            box-shadow: var(--shadow-lg);
            font-size: 1.5rem;
            transition: var(--transition);
            animation: pulse 2s infinite;
        }

        .ai-assistant-btn:hover {
            transform: scale(1.1);
            box-shadow: var(--shadow-xl);
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(245, 158, 11, 0); }
            100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0); }
        }

        /* Responsividade Avançada */
        @media (max-width: 1200px) {
            .dashboard-card {
                padding: 1.5rem;
            }
        }

        @media (max-width: 992px) {
            .header-section {
                padding: 1.5rem;
            }
            
            .header-section h2 {
                font-size: 1.75rem;
            }
            
            .metric-card {
                padding: 1.25rem;
            }
            
            .metric-icon {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }
            
            .metric-value {
                font-size: 1.75rem;
            }
        }

        @media (max-width: 768px) {
            body {
                padding-top: 70px;
            }
            
            .dashboard-card {
                padding: 1.25rem;
                margin-bottom: 1.5rem;
            }
            
            .metric-card {
                padding: 1rem;
            }
            
            .metric-icon {
                width: 50px;
                height: 50px;
                font-size: 1.25rem;
                margin-bottom: 0.75rem;
            }
            
            .metric-value {
                font-size: 1.5rem;
            }
            
            .dashboard-item {
                padding: 0.75rem;
                flex-wrap: wrap;
            }
            
            .dashboard-item .info {
                flex-direction: column;
                gap: 0.125rem;
            }
            
            .dashboard-item .info span {
                font-size: 0.85rem;
            }
            
            .ai-assistant {
                bottom: 1rem;
                right: 1rem;
            }
            
            .ai-assistant-btn {
                width: 50px;
                height: 50px;
                font-size: 1.25rem;
            }
        }

        @media (max-width: 576px) {
            .header-section {
                padding: 1.25rem;
            }
            
            .header-section h2 {
                font-size: 1.5rem;
            }
            
            .header-section .subtitle {
                font-size: 1rem;
            }
            
            .status-card {
                padding: 1rem;
            }
            
            .status-value {
                font-size: 1.25rem;
            }
            
            .status-label {
                font-size: 0.8rem;
            }
            
            .dashboard-item .actions {
                margin-left: 0.5rem;
                gap: 0.25rem;
            }
            
            .btn-sm {
                padding: 0.375rem 0.5rem;
                font-size: 0.75rem;
            }
        }

        /* Animações de Entrada */
        .animate-fade-in {
            animation: fadeIn 0.6s ease-out;
        }

        .animate-slide-up {
            animation: slideUp 0.6s ease-out;
        }

        .animate-slide-in-right {
            animation: slideInRight 0.6s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from { 
                opacity: 0;
                transform: translateY(30px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideInRight {
            from { 
                opacity: 0;
                transform: translateX(30px);
            }
            to { 
                opacity: 1;
                transform: translateX(0);
            }
        }

        /* Loading States */
        .loading {
            opacity: 0.7;
            pointer-events: none;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 2px solid var(--primary);
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Scrollbar Personalizada */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--light-gray);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--gray);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary);
        }
    </style>
</head>
<body>
    <?php require __DIR__ . '/navbar.php'; ?>

    <div class="container-fluid px-4 py-4">
        <!-- Header Moderno -->
        <div class="header-section animate-fade-in">
            <div class="content">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <h2><i class="fas fa-chart-line me-3"></i>Dashboard Ultra Multiservice</h2>
                        <div class="subtitle">Bem-vindo de volta, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Atendente') ?>! Aqui está o resumo do seu dia.</div>
                    </div>
                    <div class="d-flex gap-2 mt-3 mt-md-0">
                        <button class="btn btn-outline-light btn-sm" title="Atualizar Dados" onclick="location.reload()">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                        <a href="/criar_orcamento.php" class="btn btn-light btn-sm" title="Criar Novo Orçamento">
                            <i class="fas fa-plus me-2"></i>Novo Orçamento
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mensagens -->
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show animate-slide-up" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show animate-slide-up" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Métricas Principais -->
        <div class="row g-4 mb-4">
            <div class="col-6 col-lg-3">
                <div class="metric-card animate-slide-up" style="animation-delay: 0.1s">
                    <div class="metric-icon" style="background: var(--gradient-primary);">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div class="metric-value"><?= (int)$orcamentos_dia ?></div>
                    <div class="metric-label">Orçamentos Hoje</div>
                    <div class="metric-trend text-success">
                        <i class="fas fa-arrow-up"></i> +12% vs ontem
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="metric-card animate-slide-up" style="animation-delay: 0.2s">
                    <div class="metric-icon" style="background: var(--gradient-secondary);">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="metric-value">R$ <?= number_format((float)$valores_recebidos, 0, ',', '.') ?></div>
                    <div class="metric-label">Valores Recebidos</div>
                    <div class="metric-trend text-success">
                        <i class="fas fa-arrow-up"></i> +8% vs mês passado
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="metric-card animate-slide-up" style="animation-delay: 0.3s">
                    <div class="metric-icon" style="background: var(--gradient-accent);">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <div class="metric-value">R$ <?= number_format((float)$valores_a_receber, 0, ',', '.') ?></div>
                    <div class="metric-label">A Receber</div>
                    <div class="metric-trend text-warning">
                        <i class="fas fa-clock"></i> Pendente
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="metric-card animate-slide-up" style="animation-delay: 0.4s">
                    <div class="metric-icon" style="background: linear-gradient(135deg, var(--info) 0%, #1E40AF 100%);">
                        <i class="fas fa-folder-open"></i>
                    </div>
                    <div class="metric-value"><?= (int)($status_orcamentos['abertos'] ?? 0) ?></div>
                    <div class="metric-label">Orçamentos Abertos</div>
                    <div class="metric-trend text-info">
                        <i class="fas fa-eye"></i> Aguardando
                    </div>
                </div>
            </div>
        </div>

        <!-- Status dos Orçamentos com Gráfico -->
        <div class="dashboard-card animate-slide-up" style="animation-delay: 0.5s">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold mb-0"><i class="fas fa-chart-pie me-2 text-primary"></i>Status dos Orçamentos</h5>
                <button class="btn btn-outline-primary btn-sm" onclick="toggleChartView()">
                    <i class="fas fa-chart-bar me-1"></i>Alternar Visualização
                </button>
            </div>
            
            <div id="statusCards" class="row g-3">
                <?php 
                $statusMapping = [
                    'abertos'      => ['label' => 'Abertos', 'color' => 'info', 'icon' => 'folder-open'],
                    'agendados'    => ['label' => 'Agendados', 'color' => 'primary', 'icon' => 'calendar-check'],
                    'em_andamento' => ['label' => 'Em Andamento', 'color' => 'warning', 'icon' => 'cog'],
                    'concluidos'   => ['label' => 'Concluídos', 'color' => 'success', 'icon' => 'check-circle'],
                    'atrasados'    => ['label' => 'Atrasados', 'color' => 'danger', 'icon' => 'exclamation-triangle'],
                    'cancelados'   => ['label' => 'Cancelados', 'color' => 'dark', 'icon' => 'times-circle']
                ];
                foreach ($statusMapping as $key => $info): 
                ?>
                    <div class="col-6 col-md-4 col-lg-2">
                        <div class="status-card">
                            <div class="mb-2">
                                <i class="fas fa-<?= $info['icon'] ?> text-<?= $info['color'] ?> fa-lg"></i>
                            </div>
                            <div class="status-value text-<?= $info['color'] ?>">
                                <?= (int)($status_orcamentos[$key] ?? 0) ?>
                            </div>
                            <div class="status-label"><?= $info['label'] ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div id="statusChart" class="chart-container" style="display: none;">
                <canvas id="statusPieChart"></canvas>
            </div>
        </div>

        <!-- Conteúdo Principal em Tabs -->
        <div class="dashboard-card animate-slide-up" style="animation-delay: 0.6s">
            <ul class="nav nav-tabs" id="dashboardTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="servicos-tab" data-bs-toggle="tab" data-bs-target="#servicos" type="button" role="tab">
                        <i class="fas fa-tools me-2"></i>Serviços & Lavanderia
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tickets-tab" data-bs-toggle="tab" data-bs-target="#tickets" type="button" role="tab">
                        <i class="fas fa-ticket-alt me-2"></i>Tickets & Suporte
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="financeiro-tab" data-bs-toggle="tab" data-bs-target="#financeiro" type="button" role="tab">
                        <i class="fas fa-chart-line me-2"></i>Financeiro
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="lembretes-tab" data-bs-toggle="tab" data-bs-target="#lembretes" type="button" role="tab">
                        <i class="fas fa-bell me-2"></i>Lembretes
                    </button>
                </li>
            </ul>
            
            <div class="tab-content" id="dashboardTabsContent">
                <!-- Tab Serviços & Lavanderia -->
                <div class="tab-pane fade show active" id="servicos" role="tabpanel">
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold mb-0"><i class="fas fa-tshirt me-2 text-info"></i>Lavanderia</h6>
                                <a href="lavanderia_list.php" class="btn btn-sm btn-primary">
                                    <i class="fas fa-eye"></i> Ver Todos
                                </a>
                            </div>
                            <div class="lavanderia-list">
                                <?php if(count($lavanderia) > 0): ?>
                                    <?php foreach($lavanderia as $item): ?>
                                        <div class="dashboard-item" data-bs-toggle="modal" data-bs-target="#lavanderiaModal-<?= $item['id'] ?>">
                                            <i class="fas fa-tshirt text-info"></i>
                                            <div class="info">
                                                <?php
                                                $first_item = !empty($item['itens']) ? $item['itens'][0] : null;
                                                $item_name = $first_item ? htmlspecialchars($first_item['nome_item']) : 'N/A';
                                                $item_count = $first_item ? count($item['itens']) : 0;
                                                ?>
                                                <span class="fw-semibold"><?= $item_name ?><?php if ($item_count > 1) echo " (+".($item_count-1).")"; ?></span>
                                                <span class="text-muted"><?= htmlspecialchars($item['cliente']) ?></span>
                                                <span class="text-muted">Previsão: <?= $item['data_prevista_entrega'] ? date('d/m/Y', strtotime($item['data_prevista_entrega'])) : 'N/A' ?></span>
                                            </div>
                                            <span class="badge bg-<?= match(strtolower($item['status'])) {
                                                'em processamento' => 'warning',
                                                'lavado' => 'info',
                                                'pronto para retirada' => 'success',
                                                default => 'secondary'
                                            } ?>"><?= htmlspecialchars($item['status']) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-muted text-center py-4">
                                        <i class="fas fa-tshirt fa-3x mb-3 opacity-25"></i>
                                        <p>Nenhum item na lavanderia</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="col-lg-6">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold mb-0"><i class="fas fa-calendar-alt me-2 text-primary"></i>Próximos Serviços</h6>
                                <a href="agenda.php" class="btn btn-sm btn-primary">
                                    <i class="fas fa-calendar"></i> Ver Agenda
                                </a>
                            </div>
                            <div class="servicos-list">
                                <?php if(count($proximos_servicos) > 0): ?>
                                    <?php foreach($proximos_servicos as $servico): ?>
                                        <div class="dashboard-item">
                                            <i class="fas fa-wrench text-primary"></i>
                                            <div class="info">
                                                <span class="fw-semibold">OS #<?= htmlspecialchars($servico['numero_ordem']) ?></span>
                                                <span class="text-muted"><?= htmlspecialchars($servico['cliente_nome']) ?></span>
                                                <span class="text-muted"><?= date('d/m/Y', strtotime($servico['data_servico'])) ?> às <?= date('H:i', strtotime($servico['hora_servico'])) ?></span>
                                                <span class="text-muted">Técnico: <?= htmlspecialchars($servico['tecnico_nome'] ?? 'Não atribuído') ?></span>
                                            </div>
                                            <span class="badge bg-primary"><?= htmlspecialchars($servico['status']) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-muted text-center py-4">
                                        <i class="fas fa-calendar-alt fa-3x mb-3 opacity-25"></i>
                                        <p>Nenhum serviço agendado</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tab Tickets & Suporte -->
                <div class="tab-pane fade" id="tickets" role="tabpanel">
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold mb-0"><i class="fas fa-ticket-alt me-2 text-warning"></i>Meus Tickets</h6>
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#criarTicketModal">
                                    <i class="fas fa-plus"></i> Novo Ticket
                                </button>
                            </div>
                            <div class="tickets-list">
                                <?php if(count($tickets_atendente) > 0): ?>
                                    <?php foreach($tickets_atendente as $ticket): ?>
                                        <div class="dashboard-item">
                                            <i class="fas fa-ticket-alt text-<?= match($ticket['prioridade']) {
                                                'alta' => 'danger',
                                                'media' => 'warning',
                                                'baixa' => 'info',
                                                default => 'secondary'
                                            } ?>"></i>
                                            <div class="info">
                                                <span class="fw-semibold"><?= htmlspecialchars($ticket['titulo']) ?></span>
                                                <span class="text-muted">Para: <?= htmlspecialchars($ticket['tecnico_nome'] ?? 'Não atribuído') ?></span>
                                                <span class="text-muted"><?= date('d/m/Y H:i', strtotime($ticket['created_at'])) ?></span>
                                            </div>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="badge bg-<?= match($ticket['prioridade']) {
                                                    'alta' => 'danger',
                                                    'media' => 'warning',
                                                    'baixa' => 'info',
                                                    default => 'secondary'
                                                } ?>"><?= ucfirst($ticket['prioridade']) ?></span>
                                                <span class="badge bg-<?= match($ticket['status']) {
                                                    'aberto' => 'primary',
                                                    'em_andamento' => 'warning',
                                                    'resolvido' => 'success',
                                                    'fechado' => 'dark',
                                                    default => 'secondary'
                                                } ?>"><?= ucfirst(str_replace('_', ' ', $ticket['status'])) ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-muted text-center py-4">
                                        <i class="fas fa-ticket-alt fa-3x mb-3 opacity-25"></i>
                                        <p>Nenhum ticket criado</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="col-lg-6">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold mb-0"><i class="fas fa-headset me-2 text-success"></i>Tickets Técnicos</h6>
                                <a href="tickets.php" class="btn btn-sm btn-primary">
                                    <i class="fas fa-list"></i> Ver Todos
                                </a>
                            </div>
                            <div class="tickets-tecnico-list">
                                <?php if(count($tickets_tecnico) > 0): ?>
                                    <?php foreach($tickets_tecnico as $ticket): ?>
                                        <div class="dashboard-item" data-bs-toggle="modal" data-bs-target="#responderTicketModal-<?= $ticket['id'] ?>">
                                            <i class="fas fa-headset text-<?= match($ticket['prioridade']) {
                                                'alta' => 'danger',
                                                'media' => 'warning',
                                                'baixa' => 'info',
                                                default => 'secondary'
                                            } ?>"></i>
                                            <div class="info">
                                                <span class="fw-semibold"><?= htmlspecialchars($ticket['titulo']) ?></span>
                                                <span class="text-muted">De: <?= htmlspecialchars($ticket['tecnico_nome'] ?? 'Técnico') ?></span>
                                                <span class="text-muted"><?= date('d/m/Y H:i', strtotime($ticket['created_at'])) ?></span>
                                            </div>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="badge bg-<?= match($ticket['prioridade']) {
                                                    'alta' => 'danger',
                                                    'media' => 'warning',
                                                    'baixa' => 'info',
                                                    default => 'secondary'
                                                } ?>"><?= ucfirst($ticket['prioridade']) ?></span>
                                                <button class="btn btn-sm btn-success" title="Responder">
                                                    <i class="fas fa-reply"></i>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-muted text-center py-4">
                                        <i class="fas fa-headset fa-3x mb-3 opacity-25"></i>
                                        <p>Nenhum ticket técnico pendente</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tab Financeiro -->
                <div class="tab-pane fade" id="financeiro" role="tabpanel">
                    <div class="row g-4">
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold mb-0"><i class="fas fa-chart-line me-2 text-success"></i>Contas do Mês</h6>
                                <a href="financeiro.php" class="btn btn-sm btn-primary">
                                    <i class="fas fa-calculator"></i> Ver Relatório
                                </a>
                            </div>
                            
                            <div class="chart-container mb-4">
                                <canvas id="financeiroChart"></canvas>
                            </div>
                            
                            <div class="contas-list">
                                <?php if(count($contas) > 0): ?>
                                    <?php foreach($contas as $conta): ?>
                                        <div class="dashboard-item">
                                            <i class="fas fa-<?= $conta['tipo'] === 'pagar' ? 'arrow-down' : 'arrow-up' ?> text-<?= $conta['tipo'] === 'pagar' ? 'danger' : 'success' ?>"></i>
                                            <div class="info">
                                                <span class="fw-semibold"><?= htmlspecialchars($conta['descricao']) ?></span>
                                                <span class="text-muted">R$ <?= number_format((float)$conta['valor'], 2, ',', '.') ?></span>
                                                <span class="text-muted">Vencimento: <?= date('d/m/Y', strtotime($conta['data_vencimento'])) ?></span>
                                            </div>
                                            <span class="badge bg-<?= match($conta['status_atualizado']) {
                                                'pago' => 'success',
                                                'pendente' => 'warning',
                                                'atrasado' => 'danger',
                                                default => 'secondary'
                                            } ?>"><?= ucfirst($conta['status_atualizado']) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-muted text-center py-4">
                                        <i class="fas fa-chart-line fa-3x mb-3 opacity-25"></i>
                                        <p>Nenhuma conta registrada este mês</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tab Lembretes -->
                <div class="tab-pane fade" id="lembretes" role="tabpanel">
                    <div class="row g-4">
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold mb-0"><i class="fas fa-bell me-2 text-warning"></i>Meus Lembretes</h6>
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#adicionarLembreteModal">
                                    <i class="fas fa-plus"></i> Novo Lembrete
                                </button>
                            </div>
                            <div class="lembretes-list">
                                <?php if(count($lembretes) > 0): ?>
                                    <?php foreach($lembretes as $lembrete): ?>
                                        <div class="dashboard-item" data-bs-toggle="modal" data-bs-target="#verLembreteModal_<?= $lembrete['id'] ?>">
                                            <i class="fas fa-bell text-warning"></i>
                                            <div class="info">
                                                <span class="fw-semibold"><?= htmlspecialchars(substr($lembrete['texto'], 0, 50) . (strlen($lembrete['texto']) > 50 ? '...' : '')) ?></span>
                                                <span class="text-muted"><?= date('d/m/Y H:i', strtotime($lembrete['data_criacao'])) ?></span>
                                            </div>
                                            <div class="actions">
                                                <a href="?concluir_lembrete=<?= $lembrete['id'] ?>" class="btn btn-sm btn-success" title="Concluir" onclick="event.stopPropagation();">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                                <a href="?excluir_lembrete=<?= $lembrete['id'] ?>" class="btn btn-sm btn-danger" title="Excluir" onclick="event.stopPropagation(); return confirm('Tem certeza que deseja excluir este lembrete?');">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-muted text-center py-4">
                                        <i class="fas fa-bell fa-3x mb-3 opacity-25"></i>
                                        <p>Nenhum lembrete ativo</p>
                                        <button class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#adicionarLembreteModal">
                                            <i class="fas fa-plus me-2"></i>Criar Primeiro Lembrete
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- AI Assistant (Elemento Surpreendente) -->
    <div class="ai-assistant">
        <button class="ai-assistant-btn" data-bs-toggle="modal" data-bs-target="#aiAssistantModal" title="Assistente IA">
            <i class="fas fa-robot"></i>
        </button>
    </div>

    <!-- Modal AI Assistant -->
    <div class="modal fade" id="aiAssistantModal" tabindex="-1" aria-labelledby="aiAssistantModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: var(--gradient-accent); color: white;">
                    <h5 class="modal-title" id="aiAssistantModalLabel">
                        <i class="fas fa-robot me-2"></i>Assistente IA Ultra
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="ai-insights mb-4">
                        <h6 class="fw-bold mb-3"><i class="fas fa-lightbulb me-2 text-warning"></i>Insights do Dia</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="alert alert-info">
                                    <i class="fas fa-chart-line me-2"></i>
                                    <strong>Performance:</strong> Seus orçamentos aumentaram 12% hoje!
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="alert alert-warning">
                                    <i class="fas fa-clock me-2"></i>
                                    <strong>Atenção:</strong> 3 serviços agendados para amanhã.
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="alert alert-success">
                                    <i class="fas fa-money-bill-wave me-2"></i>
                                    <strong>Financeiro:</strong> Meta mensal 78% atingida.
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="alert alert-primary">
                                    <i class="fas fa-users me-2"></i>
                                    <strong>Equipe:</strong> Todos os técnicos disponíveis.
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="ai-suggestions">
                        <h6 class="fw-bold mb-3"><i class="fas fa-magic me-2 text-primary"></i>Sugestões Inteligentes</h6>
                        <div class="list-group">
                            <div class="list-group-item">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1">Otimizar Agenda</h6>
                                    <small>Agora</small>
                                </div>
                                <p class="mb-1">Reorganizar serviços de amanhã para reduzir tempo de deslocamento em 25%.</p>
                                <button class="btn btn-sm btn-outline-primary">Aplicar Sugestão</button>
                            </div>
                            <div class="list-group-item">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1">Contatar Clientes</h6>
                                    <small>Urgente</small>
                                </div>
                                <p class="mb-1">2 clientes com pagamentos em atraso. Enviar lembrete automático?</p>
                                <button class="btn btn-sm btn-outline-warning">Enviar Lembretes</button>
                            </div>
                            <div class="list-group-item">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1">Análise de Tendências</h6>
                                    <small>Semanal</small>
                                </div>
                                <p class="mb-1">Serviços de lavanderia têm maior margem de lucro. Considere promoção.</p>
                                <button class="btn btn-sm btn-outline-success">Ver Análise</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="button" class="btn btn-primary">
                        <i class="fas fa-comments me-2"></i>Chat com IA
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Adicionar Lembrete -->
    <div class="modal fade" id="adicionarLembreteModal" tabindex="-1" aria-labelledby="adicionarLembreteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="adicionarLembreteModalLabel">
                        <i class="fas fa-bell me-2"></i>Novo Lembrete
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="texto_lembrete" class="form-label">Texto do Lembrete</label>
                            <textarea class="form-control" id="texto_lembrete" name="texto_lembrete" rows="3" required placeholder="Digite seu lembrete aqui..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="adicionar_lembrete" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Salvar Lembrete
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Dados para os gráficos
        const statusData = {
            labels: ['Abertos', 'Agendados', 'Em Andamento', 'Concluídos', 'Atrasados', 'Cancelados'],
            data: [
                <?= (int)($status_orcamentos['abertos'] ?? 0) ?>,
                <?= (int)($status_orcamentos['agendados'] ?? 0) ?>,
                <?= (int)($status_orcamentos['em_andamento'] ?? 0) ?>,
                <?= (int)($status_orcamentos['concluidos'] ?? 0) ?>,
                <?= (int)($status_orcamentos['atrasados'] ?? 0) ?>,
                <?= (int)($status_orcamentos['cancelados'] ?? 0) ?>
            ],
            colors: ['#3B82F6', '#2563EB', '#F59E0B', '#10B981', '#EF4444', '#374151']
        };

        // Gráfico de Pizza para Status
        let statusChart = null;
        
        function initStatusChart() {
            const ctx = document.getElementById('statusPieChart').getContext('2d');
            statusChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: statusData.labels,
                    datasets: [{
                        data: statusData.data,
                        backgroundColor: statusData.colors,
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                usePointStyle: true
                            }
                        }
                    }
                }
            });
        }

        // Alternar visualização do gráfico
        function toggleChartView() {
            const cardsDiv = document.getElementById('statusCards');
            const chartDiv = document.getElementById('statusChart');
            
            if (chartDiv.style.display === 'none') {
                cardsDiv.style.display = 'none';
                chartDiv.style.display = 'block';
                if (!statusChart) {
                    initStatusChart();
                }
            } else {
                cardsDiv.style.display = 'flex';
                chartDiv.style.display = 'none';
            }
        }

        // Gráfico Financeiro
        function initFinanceiroChart() {
            const ctx = document.getElementById('financeiroChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Sem 1', 'Sem 2', 'Sem 3', 'Sem 4'],
                    datasets: [{
                        label: 'Recebidos',
                        data: [<?= (float)$valores_recebidos * 0.2 ?>, <?= (float)$valores_recebidos * 0.3 ?>, <?= (float)$valores_recebidos * 0.25 ?>, <?= (float)$valores_recebidos * 0.25 ?>],
                        borderColor: '#10B981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        tension: 0.4,
                        fill: true
                    }, {
                        label: 'A Receber',
                        data: [<?= (float)$valores_a_receber * 0.3 ?>, <?= (float)$valores_a_receber * 0.2 ?>, <?= (float)$valores_a_receber * 0.3 ?>, <?= (float)$valores_a_receber * 0.2 ?>],
                        borderColor: '#F59E0B',
                        backgroundColor: 'rgba(245, 158, 11, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'R$ ' + value.toLocaleString('pt-BR');
                                }
                            }
                        }
                    }
                }
            });
        }

        // Inicializar quando o DOM estiver pronto
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar gráfico financeiro
            initFinanceiroChart();
            
            // Adicionar animações aos elementos
            const cards = document.querySelectorAll('.metric-card, .dashboard-card');
            cards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
            });
            
            // Auto-refresh a cada 5 minutos
            setInterval(() => {
                const refreshBtn = document.querySelector('[title="Atualizar Dados"]');
                if (refreshBtn) {
                    refreshBtn.innerHTML = '<i class="fas fa-sync-alt fa-spin"></i>';
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                }
            }, 300000); // 5 minutos
        });

        // Notificações em tempo real (simulação)
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            notification.style.cssText = 'top: 100px; right: 20px; z-index: 9999; min-width: 300px;';
            notification.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 5000);
        }

        // Simular notificações
        setTimeout(() => {
            showNotification('<i class="fas fa-bell me-2"></i>Novo orçamento recebido!', 'success');
        }, 10000);

        setTimeout(() => {
            showNotification('<i class="fas fa-clock me-2"></i>Lembrete: Reunião em 30 minutos', 'warning');
        }, 20000);
    </script>
</body>
</html>

