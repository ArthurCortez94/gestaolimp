<?php
// Incluir arquivo de configuração
require_once 'config.php';
require_once 'functions.php';

// Verificar se o usuário está logado (adicionar depois que criar o sistema de login)
// verificarAutenticacao();

// Instanciar classe de dados do dashboard
$dashboard = new DashboardData($pdo);

// Buscar todos os dados necessários
$receitaMensal = $dashboard->getReceitaMensal();
$receitaMesAnterior = $dashboard->getReceitaMesAnterior();
$percentualCrescimento = calcularPercentualCrescimento($receitaMensal, $receitaMesAnterior);

$agendamentos = $dashboard->getAgendamentosDia();
$funcionarios = $dashboard->getFuncionarios();
$lavanderia = $dashboard->getLavanderia();

$proximosServicos = $dashboard->getProximosServicos();
$entregasTapetes = $dashboard->getProximasEntregas();

$distribuicao = $dashboard->getDistribuicaoServicos();
$performance = $dashboard->getPerformance();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ultra Limp - Dashboard Executivo</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="styles.css" rel="stylesheet">
    <link href="dashboard-components.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            /* Paleta de cores profissional e clean */
            --primary: #2563eb;
            --primary-light: #3b82f6;
            --primary-dark: #1d4ed8;
            --secondary: #64748b;
            --accent: #0ea5e9;
            
            /* Tons de cinza refinados */
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
            
            /* Cores de status refinadas */
            --success: #059669;
            --warning: #d97706;
            --danger: #dc2626;
            --info: #0284c7;
            
            /* Backgrounds */
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --bg-card: #ffffff;
            
            /* Sombras profissionais */
            --shadow-xs: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
            
            /* Bordas e raios */
            --radius-sm: 0.375rem;
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;
            
            /* Espaçamentos consistentes */
            --space-xs: 0.25rem;
            --space-sm: 0.5rem;
            --space-md: 1rem;
            --space-lg: 1.5rem;
            --space-xl: 2rem;
            --space-2xl: 3rem;
            
            /* Transições suaves */
            --transition-fast: 0.15s ease-in-out;
            --transition-normal: 0.25s ease-in-out;
            --transition-slow: 0.35s ease-in-out;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-secondary);
            color: var(--gray-800);
            line-height: 1.6;
            font-size: 14px;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* Layout Principal */
        .main-content {
            min-height: 100vh;
            padding: var(--space-xl);
            max-width: 1440px;
            margin: 0 auto;
        }

        /* Header Executivo */
        .executive-header {
            background: var(--bg-card);
            border-radius: var(--radius-xl);
            padding: var(--space-2xl);
            margin-bottom: var(--space-2xl);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-100);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: var(--space-xl);
        }

        .brand-section {
            display: flex;
            align-items: center;
            gap: var(--space-lg);
        }

        .brand-logo {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: 700;
            box-shadow: var(--shadow-md);
        }

        .brand-info h1 {
            font-size: 28px;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: var(--space-xs);
        }

        .brand-subtitle {
            color: var(--gray-500);
            font-size: 15px;
            font-weight: 500;
        }

        .header-stats {
            display: flex;
            gap: var(--space-2xl);
            align-items: center;
        }

        .stat-item {
            text-align: center;
            padding: var(--space-md) 0;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: var(--space-xs);
        }

        .stat-label {
            font-size: 12px;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        /* KPI Cards - Design Minimalista */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: var(--space-lg);
            margin-bottom: var(--space-2xl);
        }

        .kpi-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: var(--space-xl);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-100);
            position: relative;
            overflow: hidden;
            transition: all var(--transition-normal);
        }

        .kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            border-color: var(--gray-200);
        }

        .kpi-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
        }

        .kpi-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: var(--space-lg);
        }

        .kpi-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            background: var(--gray-50);
            color: var(--primary);
        }

        .kpi-trend {
            display: flex;
            align-items: center;
            gap: var(--space-xs);
            font-size: 13px;
            font-weight: 600;
            padding: var(--space-xs) var(--space-sm);
            border-radius: var(--radius-sm);
            background: var(--gray-50);
            color: var(--gray-600);
        }

        .kpi-trend.positive {
            background: rgba(5, 150, 105, 0.1);
            color: var(--success);
        }

        .kpi-trend.negative {
            background: rgba(220, 38, 38, 0.1);
            color: var(--danger);
        }

        .kpi-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: var(--space-xs);
            line-height: 1.2;
        }

        .kpi-label {
            font-size: 14px;
            color: var(--gray-600);
            font-weight: 500;
            margin-bottom: var(--space-sm);
        }

        .kpi-subtitle {
            font-size: 12px;
            color: var(--gray-400);
            margin-bottom: var(--space-md);
        }

        .kpi-progress {
            height: 4px;
            background: var(--gray-100);
            border-radius: 2px;
            overflow: hidden;
        }

        .kpi-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
            border-radius: 2px;
            transition: width 1s ease-out;
        }

        /* Seções de Conteúdo */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: var(--space-xl);
            margin-bottom: var(--space-2xl);
        }

        .content-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-100);
            overflow: hidden;
        }

        .card-header {
            padding: var(--space-xl) var(--space-xl) var(--space-lg);
            border-bottom: 1px solid var(--gray-100);
            background: var(--gray-50);
        }

        .card-title {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            font-size: 16px;
            font-weight: 600;
            color: var(--gray-900);
        }

        .card-title i {
            color: var(--primary);
        }

        .card-content {
            padding: var(--space-xl);
        }

        /* Timeline de Serviços */
        .services-timeline {
            position: relative;
        }

        .timeline-item {
            display: flex;
            gap: var(--space-lg);
            padding: var(--space-lg) 0;
            border-bottom: 1px solid var(--gray-100);
            position: relative;
        }

        .timeline-item:last-child {
            border-bottom: none;
        }

        .timeline-time {
            min-width: 80px;
            text-align: center;
            position: relative;
        }

        .timeline-time::after {
            content: '';
            position: absolute;
            right: -8px;
            top: 50%;
            transform: translateY(-50%);
            width: 8px;
            height: 8px;
            background: var(--primary);
            border-radius: 50%;
            border: 2px solid white;
            box-shadow: 0 0 0 2px var(--primary);
        }

        .time-hour {
            font-size: 16px;
            font-weight: 700;
            color: var(--gray-900);
        }

        .time-date {
            font-size: 11px;
            color: var(--gray-400);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .timeline-content {
            flex: 1;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .service-info h4 {
            font-size: 14px;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: var(--space-xs);
        }

        .service-details {
            font-size: 12px;
            color: var(--gray-500);
            display: flex;
            align-items: center;
            gap: var(--space-xs);
        }

        .status-badge {
            padding: var(--space-xs) var(--space-sm);
            border-radius: var(--radius-sm);
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-badge.agendado {
            background: rgba(37, 99, 235, 0.1);
            color: var(--primary);
        }

        .status-badge.em-andamento {
            background: rgba(217, 119, 6, 0.1);
            color: var(--warning);
        }

        .status-badge.concluido {
            background: rgba(5, 150, 105, 0.1);
            color: var(--success);
        }

        /* Lista de Entregas */
        .deliveries-list {
            display: flex;
            flex-direction: column;
            gap: var(--space-md);
        }

        .delivery-item {
            display: flex;
            align-items: center;
            gap: var(--space-md);
            padding: var(--space-md);
            border-radius: var(--radius-md);
            background: var(--gray-50);
            transition: all var(--transition-fast);
        }

        .delivery-item:hover {
            background: var(--gray-100);
        }

        .delivery-icon {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-md);
            background: linear-gradient(135deg, var(--danger), #ef4444);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .delivery-info {
            flex: 1;
        }

        .delivery-client {
            font-size: 14px;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: var(--space-xs);
        }

        .delivery-date {
            font-size: 12px;
            color: var(--gray-500);
            display: flex;
            align-items: center;
            gap: var(--space-xs);
        }

        .delivery-value {
            font-size: 14px;
            font-weight: 700;
            color: var(--success);
        }

        /* Gráficos */
        .chart-container {
            position: relative;
            height: 300px;
            margin: var(--space-lg) 0;
        }

        .chart-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: var(--space-md);
            margin-top: var(--space-lg);
        }

        .stat-legend {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            padding: var(--space-sm);
            border-radius: var(--radius-sm);
            background: var(--gray-50);
        }

        .legend-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }

        .legend-text {
            font-size: 12px;
            color: var(--gray-600);
            font-weight: 500;
        }

        /* Performance Section */
        .performance-metrics {
            display: flex;
            flex-direction: column;
            gap: var(--space-lg);
        }

        .metric-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: var(--space-lg);
            background: var(--gray-50);
            border-radius: var(--radius-md);
        }

        .metric-info h4 {
            font-size: 14px;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: var(--space-xs);
        }

        .metric-description {
            font-size: 12px;
            color: var(--gray-500);
        }

        .metric-value-large {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
        }

        /* Alertas Elegantes */
        .alerts-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: var(--space-lg);
            margin-top: var(--space-2xl);
        }

        .alert-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: var(--space-xl);
            box-shadow: var(--shadow-sm);
            border-left: 4px solid;
            position: relative;
            transition: all var(--transition-normal);
        }

        .alert-card:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .alert-card.warning {
            border-left-color: var(--warning);
            background: linear-gradient(135deg, rgba(217, 119, 6, 0.02), rgba(217, 119, 6, 0.05));
        }

        .alert-card.success {
            border-left-color: var(--success);
            background: linear-gradient(135deg, rgba(5, 150, 105, 0.02), rgba(5, 150, 105, 0.05));
        }

        .alert-header {
            display: flex;
            align-items: center;
            gap: var(--space-md);
            margin-bottom: var(--space-md);
        }

        .alert-icon {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .alert-card.warning .alert-icon {
            background: rgba(217, 119, 6, 0.1);
            color: var(--warning);
        }

        .alert-card.success .alert-icon {
            background: rgba(5, 150, 105, 0.1);
            color: var(--success);
        }

        .alert-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--gray-900);
        }

        .alert-message {
            font-size: 14px;
            color: var(--gray-600);
            line-height: 1.5;
            margin-bottom: var(--space-lg);
        }

        .alert-actions {
            display: flex;
            gap: var(--space-sm);
        }

        .alert-btn {
            padding: var(--space-sm) var(--space-md);
            border: 1px solid var(--primary);
            background: white;
            color: var(--primary);
            border-radius: var(--radius-sm);
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition-fast);
        }

        .alert-btn:hover {
            background: var(--primary);
            color: white;
        }

        .alert-btn.secondary {
            border-color: var(--gray-300);
            color: var(--gray-600);
        }

        .alert-btn.secondary:hover {
            background: var(--gray-100);
            color: var(--gray-700);
        }

        /* Estados Vazios */
        .empty-state {
            text-align: center;
            padding: var(--space-2xl);
            color: var(--gray-400);
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: var(--space-lg);
            color: var(--gray-300);
        }

        .empty-state p {
            font-size: 14px;
            font-weight: 500;
        }

        /* Responsividade */
        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .header-content {
                flex-direction: column;
                gap: var(--space-lg);
            }
            
            .header-stats {
                justify-content: space-around;
                width: 100%;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: var(--space-lg);
            }
            
            .kpi-grid {
                grid-template-columns: 1fr;
            }
            
            .executive-header {
                padding: var(--space-lg);
            }
            
            .brand-section {
                flex-direction: column;
                text-align: center;
            }
            
            .alerts-section {
                grid-template-columns: 1fr;
            }
        }

        /* Animações Sutis */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .kpi-card,
        .content-card,
        .alert-card {
            animation: fadeInUp 0.6s ease-out;
        }

        .timeline-item {
            animation: fadeInUp 0.4s ease-out;
        }

        .timeline-item:nth-child(1) { animation-delay: 0.1s; }
        .timeline-item:nth-child(2) { animation-delay: 0.2s; }
        .timeline-item:nth-child(3) { animation-delay: 0.3s; }
        .timeline-item:nth-child(4) { animation-delay: 0.4s; }
        .timeline-item:nth-child(5) { animation-delay: 0.5s; }

        /* Hover Effects */
        .timeline-item:hover {
            background: rgba(37, 99, 235, 0.02);
            margin: 0 calc(-1 * var(--space-lg));
            padding: var(--space-lg);
            border-radius: var(--radius-md);
        }

        /* Focus States */
        .alert-btn:focus,
        button:focus {
            outline: 2px solid var(--primary);
            outline-offset: 2px;
        }

        /* Print Styles */
        @media print {
            .alert-actions,
            .card-actions {
                display: none;
            }
            
            .main-content {
                padding: 0;
            }
            
            .kpi-card,
            .content-card {
                box-shadow: none;
                border: 1px solid var(--gray-200);
            }
        }
    </style>
</head>
<body>
    <!-- Incluir Navbar -->
    <?php include 'navbar.php'; ?>

    <!-- Partículas de Fundo Sutis -->
    <div class="particles-bg">
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
    </div>

    <div class="main-content">
        <!-- Executive Header -->
        <div class="executive-header gpu-optimized">
            <div class="header-content">
                <div class="brand-section">
                    <div class="brand-logo">UL</div>
                    <div class="brand-info">
                        <h1>Ultra Limp</h1>
                        <p class="brand-subtitle">Dashboard Executivo · Sistema de Gestão Profissional</p>
                    </div>
                </div>
                <div class="header-stats">
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $funcionarios['funcionarios_ativos']; ?></div>
                        <div class="stat-label">Equipe Online</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $agendamentos['total_agendamentos']; ?></div>
                        <div class="stat-label">Hoje</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value">98%</div>
                        <div class="stat-label">Uptime</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="kpi-grid staggered-entry">
            <div class="kpi-card hover-glow-primary gpu-optimized" data-tooltip="Clique para ver detalhes da receita">
                <div class="kpi-header">
                    <div class="kpi-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="kpi-trend <?php echo $percentualCrescimento >= 0 ? 'positive' : 'negative'; ?>">
                        <i class="fas fa-arrow-<?php echo $percentualCrescimento >= 0 ? 'up' : 'down'; ?>"></i>
                        <?php echo number_format(abs($percentualCrescimento), 1, ',', '.'); ?>%
                    </div>
                </div>
                <div class="kpi-value"><?php echo formatarMoeda($receitaMensal); ?></div>
                <div class="kpi-label">Receita Mensal</div>
                <div class="kpi-subtitle">Meta: <?php echo formatarMoeda($performance['meta_mensal']); ?></div>
                <div class="kpi-progress">
                    <div class="kpi-progress-fill" style="width: <?php echo min(($receitaMensal / $performance['meta_mensal']) * 100, 100); ?>%"></div>
                </div>
            </div>

            <div class="kpi-card hover-lift gpu-optimized tooltip-premium" data-tooltip="Visualizar agenda completa">
                <div class="kpi-header">
                    <div class="kpi-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="kpi-trend">Hoje</div>
                </div>
                <div class="kpi-value"><?php echo $agendamentos['total_agendamentos']; ?></div>
                <div class="kpi-label">Agendamentos</div>
                <div class="kpi-subtitle"><?php echo $agendamentos['em_andamento']; ?> em andamento</div>
            </div>

            <div class="kpi-card hover-scale gpu-optimized tooltip-premium" data-tooltip="Gerenciar equipe">
                <div class="kpi-header">
                    <div class="kpi-icon">
                        <i class="fas fa-users"></i>
                        <span class="status-indicator-premium online"></span>
                    </div>
                    <div class="kpi-trend">Ativos</div>
                </div>
                <div class="kpi-value"><?php echo $funcionarios['funcionarios_ativos']; ?>/<?php echo $funcionarios['total_funcionarios']; ?></div>
                <div class="kpi-label">Equipe Presente</div>
                <div class="kpi-subtitle"><?php echo $funcionarios['em_trabalho']; ?> em campo</div>
            </div>

            <div class="kpi-card hover-glow-warning gpu-optimized tooltip-premium" data-tooltip="Acompanhar lavanderia">
                <div class="kpi-header">
                    <div class="kpi-icon">
                        <i class="fas fa-tshirt"></i>
                    </div>
                    <div class="kpi-trend">Processando</div>
                </div>
                <div class="kpi-value"><?php echo $lavanderia['total_itens']; ?></div>
                <div class="kpi-label">Itens Lavanderia</div>
                <div class="kpi-subtitle"><?php echo $lavanderia['prontos']; ?> prontos para entrega</div>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Próximos Serviços -->
            <div class="content-card">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fas fa-calendar-day"></i>
                        Próximos Serviços Agendados
                    </div>
                </div>
                <div class="card-content">
                    <?php if (empty($proximosServicos)): ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-times"></i>
                            <p>Nenhum serviço agendado</p>
                        </div>
                    <?php else: ?>
                        <div class="services-timeline">
                            <?php foreach ($proximosServicos as $servico): ?>
                                <div class="timeline-item">
                                    <div class="timeline-time">
                                        <div class="time-hour"><?php echo formatarHora($servico['hora_agendamento']); ?></div>
                                        <div class="time-date"><?php echo formatarData($servico['data_agendamento']); ?></div>
                                    </div>
                                    <div class="timeline-content">
                                        <div class="service-info">
                                            <h4><?php echo htmlspecialchars($servico['cliente']); ?></h4>
                                            <div class="service-details">
                                                <i class="fas fa-map-marker-alt"></i>
                                                Limpeza Residencial
                                            </div>
                                        </div>
                                        <span class="status-badge <?php echo strtolower(str_replace(' ', '-', $servico['status'])); ?>">
                                            <?php echo $servico['status']; ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Entregas e Performance -->
            <div style="display: flex; flex-direction: column; gap: var(--space-xl);">
                <!-- Próximas Entregas -->
                <div class="content-card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fas fa-truck"></i>
                            Entregas de Tapetes
                        </div>
                    </div>
                    <div class="card-content">
                        <?php if (empty($entregasTapetes)): ?>
                            <div class="empty-state">
                                <i class="fas fa-rug"></i>
                                <p>Nenhuma entrega pendente</p>
                            </div>
                        <?php else: ?>
                            <div class="deliveries-list">
                                <?php foreach (array_slice($entregasTapetes, 0, 4) as $tapete): ?>
                                    <div class="delivery-item">
                                        <div class="delivery-icon">
                                            <i class="fas fa-rug"></i>
                                        </div>
                                        <div class="delivery-info">
                                            <div class="delivery-client"><?php echo htmlspecialchars($tapete['cliente']); ?></div>
                                            <div class="delivery-date">
                                                <i class="fas fa-calendar"></i>
                                                <?php echo formatarData($tapete['data_prevista_entrega']); ?>
                                            </div>
                                        </div>
                                        <div class="delivery-value"><?php echo formatarMoeda($tapete['valor_total_geral']); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Performance -->
                <div class="content-card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fas fa-chart-bar"></i>
                            Performance
                        </div>
                    </div>
                    <div class="card-content">
                        <div class="performance-metrics">
                            <div class="metric-item">
                                <div class="metric-info">
                                    <h4>Satisfação Cliente</h4>
                                    <div class="metric-description">Baseado em 247 avaliações</div>
                                </div>
                                <div class="metric-value-large"><?php echo number_format($performance['satisfacao_cliente'], 0); ?>%</div>
                            </div>
                            <div class="metric-item">
                                <div class="metric-info">
                                    <h4>Eficiência Operacional</h4>
                                    <div class="metric-description">Tempo médio por serviço</div>
                                </div>
                                <div class="metric-value-large">2.3h</div>
                            </div>
                            <div class="metric-item">
                                <div class="metric-info">
                                    <h4>Taxa de Retenção</h4>
                                    <div class="metric-description">Clientes recorrentes</div>
                                </div>
                                <div class="metric-value-large">94%</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="content-grid">
            <!-- Distribuição de Serviços -->
            <div class="content-card">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fas fa-chart-pie"></i>
                        Distribuição de Serviços
                    </div>
                </div>
                <div class="card-content">
                    <div class="chart-container">
                        <canvas id="serviceChart"></canvas>
                    </div>
                    <div class="chart-stats">
                        <div class="stat-legend">
                            <div class="legend-dot" style="background: #3b82f6;"></div>
                            <div class="legend-text">Residencial (45%)</div>
                        </div>
                        <div class="stat-legend">
                            <div class="legend-dot" style="background: #8b5cf6;"></div>
                            <div class="legend-text">Comercial (30%)</div>
                        </div>
                        <div class="stat-legend">
                            <div class="legend-dot" style="background: #f97316;"></div>
                            <div class="legend-text">Tapetes (15%)</div>
                        </div>
                        <div class="stat-legend">
                            <div class="legend-dot" style="background: #10b981;"></div>
                            <div class="legend-text">Outros (10%)</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Insights -->
            <div class="content-card">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fas fa-lightbulb"></i>
                        Insights Executivos
                    </div>
                </div>
                <div class="card-content">
                    <div class="performance-metrics">
                        <div class="metric-item">
                            <div class="metric-info">
                                <h4>Crescimento Mensal</h4>
                                <div class="metric-description">Comparado ao mês anterior</div>
                            </div>
                            <div class="metric-value-large" style="color: var(--success);">+<?php echo number_format($percentualCrescimento, 1); ?>%</div>
                        </div>
                        <div class="metric-item">
                            <div class="metric-info">
                                <h4>Ticket Médio</h4>
                                <div class="metric-description">Por serviço realizado</div>
                            </div>
                            <div class="metric-value-large">R$ 245</div>
                        </div>
                        <div class="metric-item">
                            <div class="metric-info">
                                <h4>ROI Marketing</h4>
                                <div class="metric-description">Retorno sobre investimento</div>
                            </div>
                            <div class="metric-value-large" style="color: var(--success);">3.2x</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alertas Importantes -->
        <div class="alerts-section fade-in-sequence">
            <div class="alert-card warning animated-border gpu-optimized">
                <div class="alert-header">
                    <div class="alert-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="alert-title">5 tapetes com prazo próximo</div>
                </div>
                <div class="alert-message">
                    Verifique os prazos de entrega para esta semana. Alguns clientes podem precisar de comunicação proativa sobre o status.
                </div>
                <div class="alert-actions">
                    <button class="alert-btn ripple-effect">Ver Detalhes</button>
                    <button class="alert-btn secondary">Notificar Equipe</button>
                </div>
            </div>

            <div class="alert-card success hover-glow-success gpu-optimized">
                <div class="alert-header">
                    <div class="alert-icon">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <div class="alert-title">Meta de satisfação atingida!</div>
                </div>
                <div class="alert-message">
                    Parabéns! Você alcançou 95% de satisfação este mês, superando a meta de 90%. Excelente trabalho da equipe.
                </div>
                <div class="alert-actions">
                    <button class="alert-btn ripple-effect">Compartilhar</button>
                    <button class="alert-btn secondary">Ver Relatório</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Configuração dos gráficos
        Chart.defaults.font.family = "'Inter', sans-serif";
        Chart.defaults.font.size = 12;
        Chart.defaults.color = '#64748b';

        // Gráfico de distribuição de serviços
        const ctx = document.getElementById('serviceChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($distribuicao['labels']); ?>,
                datasets: [{
                    data: <?php echo json_encode($distribuicao['valores']); ?>,
                    backgroundColor: ['#3b82f6', '#8b5cf6', '#f97316', '#10b981'],
                    borderWidth: 0,
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.9)',
                        titleColor: 'white',
                        bodyColor: 'white',
                        borderColor: 'rgba(148, 163, 184, 0.2)',
                        borderWidth: 1,
                        cornerRadius: 8,
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + context.parsed + '%';
                            }
                        }
                    }
                },
                cutout: '70%',
                animation: {
                    animateScale: true,
                    duration: 1000
                }
            }
        });

        // Animações e interações
        document.addEventListener('DOMContentLoaded', function() {
            // Animação das barras de progresso
            const progressBars = document.querySelectorAll('.kpi-progress-fill');
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const bar = entry.target;
                        const width = bar.style.width;
                        bar.style.width = '0%';
                        setTimeout(() => {
                            bar.style.width = width;
                        }, 300);
                    }
                });
            });

            progressBars.forEach(bar => observer.observe(bar));

            // Hover effects nos KPI cards com ripple
            const kpiCards = document.querySelectorAll('.kpi-card');
            kpiCards.forEach(card => {
                card.classList.add('ripple-effect');
                
                card.addEventListener('click', function(e) {
                    const ripple = document.createElement('span');
                    ripple.classList.add('ripple');
                    this.appendChild(ripple);

                    const rect = this.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height);
                    const x = e.clientX - rect.left - size / 2;
                    const y = e.clientY - rect.top - size / 2;

                    ripple.style.width = ripple.style.height = size + 'px';
                    ripple.style.left = x + 'px';
                    ripple.style.top = y + 'px';

                    setTimeout(() => ripple.remove(), 600);
                });
                
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-6px) scale(1.01)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
            });

            // Fechar alertas
            const alertCloses = document.querySelectorAll('.alert-btn.secondary');
            alertCloses.forEach(btn => {
                if (btn.textContent.includes('Notificar') || btn.textContent.includes('Ver Relatório')) {
                    btn.addEventListener('click', function() {
                        const alertCard = this.closest('.alert-card');
                        alertCard.style.transform = 'translateX(100%)';
                        alertCard.style.opacity = '0';
                        setTimeout(() => alertCard.remove(), 300);
                    });
                }
            });

            // Atualização automática do tempo
            function updateTime() {
                const now = new Date();
                const timeString = now.toLocaleTimeString('pt-BR', {
                    hour: '2-digit',
                    minute: '2-digit'
                });
                
                // Atualizar indicador de tempo se existir
                const timeIndicators = document.querySelectorAll('.time-indicator');
                timeIndicators.forEach(indicator => {
                    indicator.textContent = timeString;
                });
            }

            setInterval(updateTime, 1000);
            updateTime();

            // Animação dos valores dos KPIs
            function animateKPIValues() {
                const kpiValues = document.querySelectorAll('.kpi-value');
                kpiValues.forEach(value => {
                    const finalValue = value.textContent;
                    const isMonetary = finalValue.includes('R$');
                    const isPercentage = finalValue.includes('%');
                    const isFraction = finalValue.includes('/');
                    
                    if (isMonetary) {
                        const numericValue = parseFloat(finalValue.replace(/[^\d,]/g, '').replace(',', '.'));
                        animateNumber(value, 0, numericValue, 1500, (val) => {
                            return 'R$ ' + val.toLocaleString('pt-BR', {
                                minimumFractionDigits: 0,
                                maximumFractionDigits: 0
                            });
                        });
                    } else if (isPercentage) {
                        const numericValue = parseFloat(finalValue.replace('%', ''));
                        animateNumber(value, 0, numericValue, 1200, (val) => val.toFixed(0) + '%');
                    } else if (!isFraction) {
                        const numericValue = parseFloat(finalValue);
                        if (!isNaN(numericValue)) {
                            animateNumber(value, 0, numericValue, 1000, (val) => Math.round(val).toString());
                        }
                    }
                });
            }

            function animateNumber(element, start, end, duration, formatter) {
                const startTime = performance.now();
                element.classList.add('metric-value-animated');
                
                function update(currentTime) {
                    const elapsed = currentTime - startTime;
                    const progress = Math.min(elapsed / duration, 1);
                    const easeOutCubic = 1 - Math.pow(1 - progress, 3);
                    const current = start + (end - start) * easeOutCubic;
                    
                    element.textContent = formatter(current);
                    
                    if (progress < 1) {
                        requestAnimationFrame(update);
                    }
                }
                
                requestAnimationFrame(update);
            }

            // Iniciar animação após carregamento
            setTimeout(animateKPIValues, 500);

            // Smooth scrolling
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });
        });

        // Função para atualizar dados (pode ser chamada via AJAX)
        function refreshDashboard() {
            // Implementar atualização via AJAX se necessário
            console.log('Dashboard atualizado:', new Date().toLocaleTimeString());
        }

        // Auto-refresh opcional (descomentado se necessário)
        // setInterval(refreshDashboard, 300000); // 5 minutos
    </script>
</body>
</html>

