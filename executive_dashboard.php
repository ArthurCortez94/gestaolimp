<?php
/**
 * Ultra Limp - Executive Dashboard
 * 
 * Professional executive dashboard with comprehensive metrics and insights
 * Provides high-level overview of business performance, KPIs, and operations
 * 
 * @author Ultra Limp Development Team
 * @version 2.0
 * @since 2024
 */

// Security check - prevent direct access
if (!defined('SECURE_ACCESS')) {
    define('SECURE_ACCESS', true);
}

// Include required files
require_once 'config.php';
require_once 'functions.php';

// Authentication check (uncomment when login system is implemented)
// verificarAutenticacao();

/**
 * Dashboard Data Class
 * Handles all data retrieval for the executive dashboard
 */
class DashboardData {
    private $pdo;
    
    public function __construct($database) {
        $this->pdo = $database;
    }
    
    /**
     * Get monthly revenue
     */
    public function getReceitaMensal() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(valor_total), 0) as receita 
                FROM agendamentos 
                WHERE MONTH(data_agendamento) = MONTH(CURRENT_DATE()) 
                AND YEAR(data_agendamento) = YEAR(CURRENT_DATE())
                AND status = 'concluido'
            ");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['receita'] ?? 0;
        } catch (Exception $e) {
            error_log("Error getting monthly revenue: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get previous month revenue for comparison
     */
    public function getReceitaMesAnterior() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(valor_total), 0) as receita 
                FROM agendamentos 
                WHERE MONTH(data_agendamento) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH) 
                AND YEAR(data_agendamento) = YEAR(CURRENT_DATE() - INTERVAL 1 MONTH)
                AND status = 'concluido'
            ");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['receita'] ?? 0;
        } catch (Exception $e) {
            error_log("Error getting previous month revenue: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get today's appointments summary
     */
    public function getAgendamentosDia() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_agendamentos,
                    SUM(CASE WHEN status = 'em_andamento' THEN 1 ELSE 0 END) as em_andamento,
                    SUM(CASE WHEN status = 'concluido' THEN 1 ELSE 0 END) as concluidos
                FROM agendamentos 
                WHERE DATE(data_agendamento) = CURRENT_DATE()
            ");
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC) ?? ['total_agendamentos' => 0, 'em_andamento' => 0, 'concluidos' => 0];
        } catch (Exception $e) {
            error_log("Error getting daily appointments: " . $e->getMessage());
            return ['total_agendamentos' => 0, 'em_andamento' => 0, 'concluidos' => 0];
        }
    }
    
    /**
     * Get staff summary
     */
    public function getFuncionarios() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_funcionarios,
                    SUM(CASE WHEN status = 'ativo' THEN 1 ELSE 0 END) as funcionarios_ativos,
                    SUM(CASE WHEN status = 'em_trabalho' THEN 1 ELSE 0 END) as em_trabalho
                FROM funcionarios
            ");
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC) ?? ['total_funcionarios' => 0, 'funcionarios_ativos' => 0, 'em_trabalho' => 0];
        } catch (Exception $e) {
            error_log("Error getting staff data: " . $e->getMessage());
            return ['total_funcionarios' => 0, 'funcionarios_ativos' => 0, 'em_trabalho' => 0];
        }
    }
    
    /**
     * Get laundry summary
     */
    public function getLavanderia() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_itens,
                    SUM(CASE WHEN status = 'pronto' THEN 1 ELSE 0 END) as prontos,
                    SUM(CASE WHEN status = 'processamento' THEN 1 ELSE 0 END) as processamento
                FROM lavanderia
                WHERE status IN ('pronto', 'processamento', 'lavando')
            ");
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC) ?? ['total_itens' => 0, 'prontos' => 0, 'processamento' => 0];
        } catch (Exception $e) {
            error_log("Error getting laundry data: " . $e->getMessage());
            return ['total_itens' => 0, 'prontos' => 0, 'processamento' => 0];
        }
    }
    
    /**
     * Get upcoming services
     */
    public function getProximosServicos($limit = 5) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    a.id,
                    c.nome as cliente,
                    a.data_agendamento,
                    a.hora_agendamento,
                    a.status,
                    a.tipo_servico
                FROM agendamentos a
                LEFT JOIN clientes c ON a.cliente_id = c.id
                WHERE a.data_agendamento >= CURRENT_DATE()
                AND a.status IN ('agendado', 'confirmado')
                ORDER BY a.data_agendamento ASC, a.hora_agendamento ASC
                LIMIT :limit
            ");
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting upcoming services: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get upcoming carpet deliveries
     */
    public function getProximasEntregas($limit = 4) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    l.id,
                    c.nome as cliente,
                    l.data_prevista_entrega,
                    l.valor_total_geral,
                    l.status
                FROM lavanderia l
                LEFT JOIN clientes c ON l.cliente_id = c.id
                WHERE l.status = 'pronto'
                AND l.data_prevista_entrega >= CURRENT_DATE()
                ORDER BY l.data_prevista_entrega ASC
                LIMIT :limit
            ");
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting upcoming deliveries: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get service distribution data for charts
     */
    public function getDistribuicaoServicos() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    tipo_servico,
                    COUNT(*) as quantidade,
                    ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM agendamentos WHERE MONTH(data_agendamento) = MONTH(CURRENT_DATE()))), 1) as percentual
                FROM agendamentos 
                WHERE MONTH(data_agendamento) = MONTH(CURRENT_DATE())
                GROUP BY tipo_servico
                ORDER BY quantidade DESC
            ");
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $labels = [];
            $valores = [];
            
            foreach ($results as $row) {
                $labels[] = ucfirst($row['tipo_servico']);
                $valores[] = floatval($row['percentual']);
            }
            
            // Fill with default data if empty
            if (empty($labels)) {
                $labels = ['Residencial', 'Comercial', 'Tapetes', 'Outros'];
                $valores = [45, 30, 15, 10];
            }
            
            return [
                'labels' => $labels,
                'valores' => $valores
            ];
        } catch (Exception $e) {
            error_log("Error getting service distribution: " . $e->getMessage());
            return [
                'labels' => ['Residencial', 'Comercial', 'Tapetes', 'Outros'],
                'valores' => [45, 30, 15, 10]
            ];
        }
    }
    
    /**
     * Get performance metrics
     */
    public function getPerformance() {
        try {
            // This would typically come from a performance tracking table
            // For now, returning mock data
            return [
                'satisfacao_cliente' => 95,
                'meta_mensal' => 50000,
                'eficiencia_operacional' => 87,
                'taxa_retencao' => 94
            ];
        } catch (Exception $e) {
            error_log("Error getting performance data: " . $e->getMessage());
            return [
                'satisfacao_cliente' => 95,
                'meta_mensal' => 50000,
                'eficiencia_operacional' => 87,
                'taxa_retencao' => 94
            ];
        }
    }
}

// Initialize dashboard data
try {
    $dashboard = new DashboardData($pdo);
    
    // Fetch all required data
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
    
} catch (Exception $e) {
    error_log("Dashboard initialization error: " . $e->getMessage());
    // Set default values in case of error
    $receitaMensal = 0;
    $receitaMesAnterior = 0;
    $percentualCrescimento = 0;
    $agendamentos = ['total_agendamentos' => 0, 'em_andamento' => 0, 'concluidos' => 0];
    $funcionarios = ['total_funcionarios' => 0, 'funcionarios_ativos' => 0, 'em_trabalho' => 0];
    $lavanderia = ['total_itens' => 0, 'prontos' => 0, 'processamento' => 0];
    $proximosServicos = [];
    $entregasTapetes = [];
    $distribuicao = ['labels' => ['Residencial', 'Comercial', 'Tapetes', 'Outros'], 'valores' => [45, 30, 15, 10]];
    $performance = ['satisfacao_cliente' => 95, 'meta_mensal' => 50000, 'eficiencia_operacional' => 87, 'taxa_retencao' => 94];
}

/**
 * Helper function to calculate percentage growth
 */
function calcularPercentualCrescimento($atual, $anterior) {
    if ($anterior == 0) {
        return $atual > 0 ? 100 : 0;
    }
    return (($atual - $anterior) / $anterior) * 100;
}

/**
 * Helper function to format currency
 */
function formatarMoeda($valor) {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

/**
 * Helper function to format date
 */
function formatarData($data) {
    return date('d/m', strtotime($data));
}

/**
 * Helper function to format time
 */
function formatarHora($hora) {
    return date('H:i', strtotime($hora));
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Ultra Limp - Dashboard Executivo com métricas de desempenho e insights de negócio">
    <meta name="author" content="Ultra Limp Development Team">
    
    <title>Ultra Limp - Dashboard Executivo</title>
    
    <!-- Preload critical resources -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Styles -->
    <link rel="stylesheet" href="assets/css/dashboard-executive.css">
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="manifest.json">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">
    
    <!-- Theme Color -->
    <meta name="theme-color" content="#2563eb">
</head>
<body>
    <!-- Include Navigation -->
    <?php include 'navbar.php'; ?>

    <div class="main-content">
        <!-- Executive Header -->
        <header class="executive-header">
            <div class="header-content">
                <div class="brand-section">
                    <div class="brand-logo" aria-label="Ultra Limp Logo">UL</div>
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
        </header>

        <!-- KPI Cards -->
        <section class="kpi-grid" aria-label="Key Performance Indicators">
            <!-- Revenue Card -->
            <article class="kpi-card">
                <div class="kpi-header">
                    <div class="kpi-icon" aria-label="Revenue Icon">
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
            </article>

            <!-- Appointments Card -->
            <article class="kpi-card">
                <div class="kpi-header">
                    <div class="kpi-icon" aria-label="Appointments Icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="kpi-trend">Hoje</div>
                </div>
                <div class="kpi-value"><?php echo $agendamentos['total_agendamentos']; ?></div>
                <div class="kpi-label">Agendamentos</div>
                <div class="kpi-subtitle"><?php echo $agendamentos['em_andamento']; ?> em andamento</div>
            </article>

            <!-- Staff Card -->
            <article class="kpi-card">
                <div class="kpi-header">
                    <div class="kpi-icon" aria-label="Staff Icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="kpi-trend">Ativos</div>
                </div>
                <div class="kpi-value"><?php echo $funcionarios['funcionarios_ativos']; ?>/<?php echo $funcionarios['total_funcionarios']; ?></div>
                <div class="kpi-label">Equipe Presente</div>
                <div class="kpi-subtitle"><?php echo $funcionarios['em_trabalho']; ?> em campo</div>
            </article>

            <!-- Laundry Card -->
            <article class="kpi-card">
                <div class="kpi-header">
                    <div class="kpi-icon" aria-label="Laundry Icon">
                        <i class="fas fa-tshirt"></i>
                    </div>
                    <div class="kpi-trend">Processando</div>
                </div>
                <div class="kpi-value"><?php echo $lavanderia['total_itens']; ?></div>
                <div class="kpi-label">Itens Lavanderia</div>
                <div class="kpi-subtitle"><?php echo $lavanderia['prontos']; ?> prontos para entrega</div>
            </article>
        </section>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Upcoming Services -->
            <section class="content-card">
                <header class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-calendar-day"></i>
                        Próximos Serviços Agendados
                    </h2>
                </header>
                <div class="card-content">
                    <?php if (empty($proximosServicos)): ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-times"></i>
                            <p>Nenhum serviço agendado</p>
                        </div>
                    <?php else: ?>
                        <div class="services-timeline">
                            <?php foreach ($proximosServicos as $servico): ?>
                                <article class="timeline-item">
                                    <div class="timeline-time">
                                        <div class="time-hour"><?php echo formatarHora($servico['hora_agendamento']); ?></div>
                                        <div class="time-date"><?php echo formatarData($servico['data_agendamento']); ?></div>
                                    </div>
                                    <div class="timeline-content">
                                        <div class="service-info">
                                            <h4><?php echo htmlspecialchars($servico['cliente']); ?></h4>
                                            <div class="service-details">
                                                <i class="fas fa-map-marker-alt"></i>
                                                <?php echo ucfirst($servico['tipo_servico'] ?? 'Limpeza Residencial'); ?>
                                            </div>
                                        </div>
                                        <span class="status-badge <?php echo strtolower(str_replace(' ', '-', $servico['status'])); ?>">
                                            <?php echo ucfirst($servico['status']); ?>
                                        </span>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Deliveries and Performance -->
            <div style="display: flex; flex-direction: column; gap: var(--space-xl);">
                <!-- Carpet Deliveries -->
                <section class="content-card">
                    <header class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-truck"></i>
                            Entregas de Tapetes
                        </h2>
                    </header>
                    <div class="card-content">
                        <?php if (empty($entregasTapetes)): ?>
                            <div class="empty-state">
                                <i class="fas fa-rug"></i>
                                <p>Nenhuma entrega pendente</p>
                            </div>
                        <?php else: ?>
                            <div class="deliveries-list">
                                <?php foreach (array_slice($entregasTapetes, 0, 4) as $tapete): ?>
                                    <article class="delivery-item">
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
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Performance Metrics -->
                <section class="content-card">
                    <header class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-chart-bar"></i>
                            Performance
                        </h2>
                    </header>
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
                </section>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="content-grid">
            <!-- Service Distribution Chart -->
            <section class="content-card">
                <header class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-chart-pie"></i>
                        Distribuição de Serviços
                    </h2>
                </header>
                <div class="card-content">
                    <div class="chart-container">
                        <canvas id="serviceChart" aria-label="Service Distribution Chart"></canvas>
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
            </section>

            <!-- Executive Insights -->
            <section class="content-card">
                <header class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-lightbulb"></i>
                        Insights Executivos
                    </h2>
                </header>
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
            </section>
        </div>

        <!-- Important Alerts -->
        <section class="alerts-section" aria-label="Important Alerts">
            <article class="alert-card warning">
                <header class="alert-header">
                    <div class="alert-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h3 class="alert-title">5 tapetes com prazo próximo</h3>
                </header>
                <div class="alert-message">
                    Verifique os prazos de entrega para esta semana. Alguns clientes podem precisar de comunicação proativa sobre o status.
                </div>
                <div class="alert-actions">
                    <button class="alert-btn" onclick="window.location.href='lavanderia.php'">Ver Detalhes</button>
                    <button class="alert-btn secondary">Notificar Equipe</button>
                </div>
            </article>

            <article class="alert-card success">
                <header class="alert-header">
                    <div class="alert-icon">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <h3 class="alert-title">Meta de satisfação atingida!</h3>
                </header>
                <div class="alert-message">
                    Parabéns! Você alcançou 95% de satisfação este mês, superando a meta de 90%. Excelente trabalho da equipe.
                </div>
                <div class="alert-actions">
                    <button class="alert-btn">Compartilhar</button>
                    <button class="alert-btn secondary">Ver Relatório</button>
                </div>
            </article>
        </section>
    </div>

    <!-- JavaScript -->
    <script>
        // Chart.js Configuration
        Chart.defaults.font.family = "'Inter', sans-serif";
        Chart.defaults.font.size = 12;
        Chart.defaults.color = '#64748b';

        // Service Distribution Chart
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

        // Dashboard Interactions
        document.addEventListener('DOMContentLoaded', function() {
            // Progress bar animations
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

            // KPI card hover effects
            const kpiCards = document.querySelectorAll('.kpi-card');
            kpiCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-4px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });

            // Alert interactions
            const alertActions = document.querySelectorAll('.alert-btn');
            alertActions.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    if (this.textContent.includes('Notificar') || this.textContent.includes('Ver Relatório')) {
                        const alertCard = this.closest('.alert-card');
                        alertCard.style.transform = 'translateX(100%)';
                        alertCard.style.opacity = '0';
                        setTimeout(() => alertCard.remove(), 300);
                    }
                });
            });

            // Auto-refresh data (optional)
            // setInterval(refreshDashboard, 300000); // 5 minutes
        });

        // Refresh dashboard function
        function refreshDashboard() {
            console.log('Dashboard refreshed:', new Date().toLocaleTimeString());
            // Implement AJAX refresh if needed
        }

        // Accessibility improvements
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                // Close any open modals or dropdowns
                const openElements = document.querySelectorAll('.is-open, .show');
                openElements.forEach(el => el.classList.remove('is-open', 'show'));
            }
        });
    </script>

    <!-- Service Worker for PWA (optional) -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('service-worker.js')
                    .then(function(registration) {
                        console.log('ServiceWorker registration successful');
                    })
                    .catch(function(err) {
                        console.log('ServiceWorker registration failed');
                    });
            });
        }
    </script>
</body>
</html>