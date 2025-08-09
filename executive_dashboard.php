<?php
declare(strict_types=1);

// Incluir arquivos de configuração
require_once 'config.php';
require_once 'functions.php';

// Verificar se o usuário está logado
verificarAutenticacao();

// Instanciar classe de dados do dashboard
$dashboard = new DashboardData($pdo);

// Buscar todos os dados necessários
try {
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
    error_log("Erro no dashboard executivo: " . $e->getMessage());
    // Valores padrão em caso de erro
    $receitaMensal = 0;
    $receitaMesAnterior = 0;
    $percentualCrescimento = 0;
    $agendamentos = ['total_agendamentos' => 0, 'em_andamento' => 0, 'concluidos' => 0];
    $funcionarios = ['funcionarios_ativos' => 0, 'total_funcionarios' => 0, 'em_trabalho' => 0];
    $lavanderia = ['total_itens' => 0, 'prontos' => 0, 'lavando' => 0];
    $proximosServicos = [];
    $entregasTapetes = [];
    $distribuicao = ['labels' => [], 'valores' => []];
    $performance = ['meta_mensal' => 50000, 'satisfacao_cliente' => 95];
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ultra Limp - Dashboard Executivo</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/executive-dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <!-- Incluir Navbar -->
    <?php include 'navbar.php'; ?>

    <div class="main-content">
        <!-- Executive Header -->
        <div class="executive-header">
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
        <div class="kpi-grid">
            <div class="kpi-card">
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

            <div class="kpi-card">
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

            <div class="kpi-card">
                <div class="kpi-header">
                    <div class="kpi-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="kpi-trend">Ativos</div>
                </div>
                <div class="kpi-value"><?php echo $funcionarios['funcionarios_ativos']; ?>/<?php echo $funcionarios['total_funcionarios']; ?></div>
                <div class="kpi-label">Equipe Presente</div>
                <div class="kpi-subtitle"><?php echo $funcionarios['em_trabalho']; ?> em campo</div>
            </div>

            <div class="kpi-card">
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
                                        <span class="status-badge <?php echo strtolower(str_replace([' ', '_'], '-', $servico['status'])); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $servico['status'])); ?>
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
                                    <div class="metric-description">Baseado em avaliações</div>
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
                        <?php 
                        $colors = ['#3b82f6', '#8b5cf6', '#f97316', '#10b981'];
                        for ($i = 0; $i < count($distribuicao['labels']); $i++): 
                            if (isset($distribuicao['labels'][$i]) && isset($distribuicao['valores'][$i])): ?>
                                <div class="stat-legend">
                                    <div class="legend-dot" style="background: <?php echo $colors[$i] ?? '#6b7280'; ?>;"></div>
                                    <div class="legend-text"><?php echo $distribuicao['labels'][$i]; ?> (<?php echo $distribuicao['valores'][$i]; ?>%)</div>
                                </div>
                            <?php endif; 
                        endfor; ?>
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
                            <div class="metric-value-large" style="color: var(--success);">
                                <?php echo $percentualCrescimento >= 0 ? '+' : ''; ?><?php echo number_format($percentualCrescimento, 1); ?>%
                            </div>
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
        <div class="alerts-section">
            <?php if (count($entregasTapetes) > 0): ?>
                <div class="alert-card warning">
                    <div class="alert-header">
                        <div class="alert-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="alert-title"><?php echo count($entregasTapetes); ?> tapetes com prazo próximo</div>
                    </div>
                    <div class="alert-message">
                        Verifique os prazos de entrega para esta semana. Alguns clientes podem precisar de comunicação proativa sobre o status.
                    </div>
                    <div class="alert-actions">
                        <button class="alert-btn" onclick="window.location.href='lavanderia_list.php'">Ver Detalhes</button>
                        <button class="alert-btn secondary">Notificar Equipe</button>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($performance['satisfacao_cliente'] >= 90): ?>
                <div class="alert-card success">
                    <div class="alert-header">
                        <div class="alert-icon">
                            <i class="fas fa-trophy"></i>
                        </div>
                        <div class="alert-title">Meta de satisfação atingida!</div>
                    </div>
                    <div class="alert-message">
                        Parabéns! Você alcançou <?php echo $performance['satisfacao_cliente']; ?>% de satisfação este mês, superando a meta de 90%. Excelente trabalho da equipe.
                    </div>
                    <div class="alert-actions">
                        <button class="alert-btn">Compartilhar</button>
                        <button class="alert-btn secondary">Ver Relatório</button>
                    </div>
                </div>
            <?php endif; ?>
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

            // Hover effects nos KPI cards
            const kpiCards = document.querySelectorAll('.kpi-card');
            kpiCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-4px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
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
        });

        // Função para atualizar dados (pode ser chamada via AJAX)
        function refreshDashboard() {
            console.log('Dashboard atualizado:', new Date().toLocaleTimeString());
            // Implementar atualização via AJAX se necessário
        }

        // Auto-refresh opcional (descomentado se necessário)
        // setInterval(refreshDashboard, 300000); // 5 minutos
    </script>
</body>
</html>