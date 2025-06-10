<?php
session_start();
require_once 'config.php';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Carregar estatísticas financeiras
try {
    $stats = $pdo->query("
        SELECT 
            SUM(CASE WHEN tipo = 'receita' THEN valor ELSE 0 END) AS total_receita,
            SUM(CASE WHEN tipo = 'despesa' THEN valor ELSE 0 END) AS total_despesa,
            SUM(CASE WHEN status = 'pendente' AND tipo = 'receita' THEN valor ELSE 0 END) AS receber_pendente,
            SUM(CASE WHEN status = 'atrasado' AND tipo = 'receita' THEN valor ELSE 0 END) AS receber_atrasado,
            SUM(CASE WHEN status = 'pendente' AND tipo = 'despesa' THEN valor ELSE 0 END) AS pagar_pendente,
            SUM(CASE WHEN status = 'atrasado' AND tipo = 'despesa' THEN valor ELSE 0 END) AS pagar_atrasado
        FROM transacoes
    ")->fetch(PDO::FETCH_ASSOC);
    
    $fluxo_caixa = $pdo->query("
        SELECT DATE_FORMAT(data, '%Y-%m') AS mes,
               SUM(CASE WHEN tipo = 'receita' THEN valor ELSE 0 END) AS receita,
               SUM(CASE WHEN tipo = 'despesa' THEN valor ELSE 0 END) AS despesa
        FROM transacoes
        WHERE data >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY mes
        ORDER BY mes DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    $contas_receber = $pdo->query("
        SELECT * FROM transacoes 
        WHERE tipo = 'receita' 
        ORDER BY data_vencimento DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    $contas_pagar = $pdo->query("
        SELECT * FROM transacoes 
        WHERE tipo = 'despesa' 
        ORDER BY data_vencimento DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Erro ao carregar dados: " . $e->getMessage());
}

// Cálculos
$saldo_atual = $stats['total_receita'] - $stats['total_despesa'];
$fluxo_mensal = [];
foreach ($fluxo_caixa as $mes) {
    $fluxo_mensal[] = [
        'mes' => DateTime::createFromFormat('Y-m', $mes['mes'])->format('M/Y'),
        'receita' => $mes['receita'],
        'despesa' => $mes['despesa'],
        'saldo' => $mes['receita'] - $mes['despesa']
    ];
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão Financeira - UltraLimp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
    :root {
        --primary: #2563EB;
        --secondary: #1D4ED8;
        --success: #28A745;
        --danger: #DC3545;
        --warning: #FFC107;
        --info: #17A2B8;
        --light: #F9FAFB;
        --dark: #111827;
    }

    .finance-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        padding: 1.5rem;
        transition: transform 0.3s ease;
        border: none;
    }

    .finance-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 30px rgba(37, 99, 235, 0.15);
    }

    .status-badge {
        padding: 0.3rem 1rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 500;
    }

    .table-finance {
        --bs-table-bg: transparent;
        margin-bottom: 0;
        border-collapse: separate;
        border-spacing: 0 8px;
    }

    .table-finance thead th {
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        color: white;
        border: none;
        padding: 1rem;
        font-weight: 600;
    }

    .table-finance tbody td {
        padding: 1rem;
        background: white;
        vertical-align: middle;
        border: none;
    }

    .table-finance tbody tr {
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        border-radius: 8px;
    }

    .chart-container {
        height: 300px;
        position: relative;
    }

    .positive { color: var(--success); }
    .negative { color: var(--danger); }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php require __DIR__ . '/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <!-- Cabeçalho -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="text-dark fw-bold"><i class="fas fa-coins me-2"></i>Gestão Financeira</h2>
            <div class="d-flex gap-3">
                <a href="financeiro/contas_receber.php" class="btn btn-primary">
                    <i class="fas fa-hand-holding-usd me-2"></i>Contas a Receber
                </a>
                <a href="financeiro/contas_pagar.php" class="btn btn-danger">
                    <i class="fas fa-money-bill-wave me-2"></i>Contas a Pagar
                </a>
            </div>
        </div>

        <!-- Cards Principais -->
        <div class="row g-4 mb-4">
            <div class="col-12 col-md-6 col-xl-3">
                <div class="finance-card">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-success bg-opacity-10 p-3 rounded-circle">
                            <i class="fas fa-wallet fa-2x text-success"></i>
                        </div>
                        <div>
                            <div class="h4 mb-0 positive">R$ <?= number_format($saldo_atual, 2, ',', '.') ?></div>
                            <small class="text-muted">Saldo Atual</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-6 col-xl-3">
                <div class="finance-card">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 p-3 rounded-circle">
                            <i class="fas fa-hand-holding-usd fa-2x text-primary"></i>
                        </div>
                        <div>
                            <div class="h4 mb-0">R$ <?= number_format($stats['receber_pendente'], 2, ',', '.') ?></div>
                            <small class="text-muted">A Receber (Pendente)</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-6 col-xl-3">
                <div class="finance-card">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-danger bg-opacity-10 p-3 rounded-circle">
                            <i class="fas fa-money-bill-wave fa-2x text-danger"></i>
                        </div>
                        <div>
                            <div class="h4 mb-0">R$ <?= number_format($stats['pagar_pendente'], 2, ',', '.') ?></div>
                            <small class="text-muted">A Pagar (Pendente)</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-6 col-xl-3">
                <div class="finance-card">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-warning bg-opacity-10 p-3 rounded-circle">
                            <i class="fas fa-exclamation-triangle fa-2x text-warning"></i>
                        </div>
                        <div>
                            <div class="h4 mb-0">R$ <?= number_format($stats['receber_atrasado'] + $stats['pagar_atrasado'], 2, ',', '.') ?></div>
                            <small class="text-muted">Pagamentos Atrasados</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gráfico e Tabelas -->
        <div class="row g-4">
            <!-- Gráfico de Fluxo de Caixa -->
            <div class="col-12 col-xl-8">
                <div class="finance-card">
                    <h5 class="mb-4"><i class="fas fa-chart-line me-2"></i>Fluxo de Caixa (Últimos 6 meses)</h5>
                    <div class="chart-container">
                        <canvas id="fluxoCaixaChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Últimas Transações -->
            <div class="col-12 col-xl-4">
                <div class="finance-card">
                    <h5 class="mb-4"><i class="fas fa-list-alt me-2"></i>Últimas Transações</h5>
                    <div class="table-responsive">
                        <table class="table table-finance">
                            <tbody>
                                <?php 
                                $transacoes = array_merge($contas_receber, $contas_pagar);
                                usort($transacoes, function($a, $b) {
                                    return strtotime($b['data']) - strtotime($a['data']);
                                });
                                $transacoes = array_slice($transacoes, 0, 5);
                                
                                foreach ($transacoes as $t): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="fas fa-<?= $t['tipo'] === 'receita' ? 'arrow-down text-success' : 'arrow-up text-danger' ?>"></i>
                                            <?= htmlspecialchars($t['descricao']) ?>
                                        </div>
                                    </td>
                                    <td class="<?= $t['tipo'] === 'receita' ? 'positive' : 'negative' ?>">
                                        R$ <?= number_format($t['valor'], 2, ',', '.') ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tabelas Detalhadas -->
            <div class="col-12 col-lg-6">
                <div class="finance-card">
                    <h5 class="mb-4"><i class="fas fa-hand-holding-usd me-2"></i>Contas a Receber</h5>
                    <div class="table-responsive">
                        <table class="table table-finance">
                            <thead>
                                <tr>
                                    <th>Cliente</th>
                                    <th>Vencimento</th>
                                    <th>Valor</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($contas_receber as $cr): ?>
                                <tr>
                                    <td><?= htmlspecialchars($cr['cliente']) ?></td>
                                    <td><?= date('d/m/Y', strtotime($cr['data_vencimento'])) ?></td>
                                    <td class="positive">R$ <?= number_format($cr['valor'], 2, ',', '.') ?></td>
                                    <td>
                                        <span class="status-badge bg-<?= 
                                            $cr['status'] === 'pago' ? 'success' : 
                                            ($cr['status'] === 'atrasado' ? 'danger' : 'warning') ?>">
                                            <?= ucfirst($cr['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-6">
                <div class="finance-card">
                    <h5 class="mb-4"><i class="fas fa-money-bill-wave me-2"></i>Contas a Pagar</h5>
                    <div class="table-responsive">
                        <table class="table table-finance">
                            <thead>
                                <tr>
                                    <th>Fornecedor</th>
                                    <th>Vencimento</th>
                                    <th>Valor</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($contas_pagar as $cp): ?>
                                <tr>
                                    <td><?= htmlspecialchars($cp['fornecedor']) ?></td>
                                    <td><?= date('d/m/Y', strtotime($cp['data_vencimento'])) ?></td>
                                    <td class="negative">R$ <?= number_format($cp['valor'], 2, ',', '.') ?></td>
                                    <td>
                                        <span class="status-badge bg-<?= 
                                            $cp['status'] === 'pago' ? 'success' : 
                                            ($cp['status'] === 'atrasado' ? 'danger' : 'warning') ?>">
                                            <?= ucfirst($cp['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Gráfico de Fluxo de Caixa
    const ctx = document.getElementById('fluxoCaixaChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode(array_column($fluxo_mensal, 'mes')) ?>,
            datasets: [{
                label: 'Receita',
                data: <?= json_encode(array_column($fluxo_mensal, 'receita')) ?>,
                borderColor: '#28a745',
                tension: 0.3
            }, {
                label: 'Despesa',
                data: <?= json_encode(array_column($fluxo_mensal, 'despesa')) ?>,
                borderColor: '#dc3545',
                tension: 0.3
            }, {
                label: 'Saldo',
                data: <?= json_encode(array_column($fluxo_mensal, 'saldo')) ?>,
                borderColor: '#2563eb',
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#f3f4f6' }
                },
                x: {
                    grid: { display: false }
                }
            }
        }
    });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>