<?php
session_start();
require_once 'config.php';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Verificar se um técnico foi selecionado
$tecnico_id = isset($_GET['tecnico_id']) ? (int)$_GET['tecnico_id'] : null;
$mes_ano = isset($_GET['mes_ano']) ? $_GET['mes_ano'] : date('Y-m');

// Processar pagamento de diária
if (isset($_POST['pagar_diaria'])) {
    $diaria_id = (int)$_POST['diaria_id'];
    $valor_pago = floatval($_POST['valor_pago']);
    try {
        $stmt = $pdo->prepare("UPDATE diarias SET status_pagamento = 'pago', valor_pago = ? WHERE id = ?");
        $stmt->execute([$valor_pago, $diaria_id]);
        header("Location: historico_tecnico.php?tecnico_id=$tecnico_id&mes_ano=$mes_ano&success=Diária+paga+com+sucesso");
        exit;
    } catch (PDOException $e) {
        die("Erro ao registrar pagamento: " . $e->getMessage());
    }
}

// Carregar lista de técnicos
try {
    $stmt = $pdo->prepare("SELECT id, nome FROM usuarios WHERE cargo = 'tecnico' AND ativo = 1 ORDER BY nome");
    $stmt->execute();
    $tecnicos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao carregar técnicos: " . $e->getMessage());
}

// Carregar dados do técnico selecionado
$historico = [];
$totais = [];
$semanas_data = [];
$tecnico_nome = '';
if ($tecnico_id) {
    try {
        // Nome do técnico
        $stmt = $pdo->prepare("SELECT nome FROM usuarios WHERE id = ? AND cargo = 'tecnico'");
        $stmt->execute([$tecnico_id]);
        $tecnico_nome = $stmt->fetchColumn();

        // Histórico de serviços e diárias com filtro por mês/ano
        $stmt = $pdo->prepare("
            SELECT 
                os.id,
                os.numero_ordem,
                os.data_servico,
                os.hora_servico,
                c.nome AS cliente_nome,
                os.total AS valor_servico,
                os.status,
                d.id AS diaria_id,
                d.valor_diaria,
                d.status_pagamento,
                d.valor_pago
            FROM ordens_servico os
            LEFT JOIN clientes c ON os.cliente_id = c.id
            LEFT JOIN diarias d ON d.ordem_id = os.id AND d.tecnico_id = os.tecnico_id
            WHERE os.tecnico_id = ? AND DATE_FORMAT(os.data_servico, '%Y-%m') = ?
            ORDER BY os.data_servico DESC
        ");
        $stmt->execute([$tecnico_id, $mes_ano]);
        $historico = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Criar diária se não existir
        foreach ($historico as &$item) {
            if (!$item['diaria_id']) {
                $stmt = $pdo->prepare("INSERT INTO diarias (ordem_id, tecnico_id, valor_diaria, status_pagamento) VALUES (?, ?, 0.00, 'pendente')");
                $stmt->execute([$item['id'], $tecnico_id]);
                $item['diaria_id'] = $pdo->lastInsertId();
                $item['valor_diaria'] = 0.00;
                $item['status_pagamento'] = 'pendente';
            }
        }
        unset($item);

        // Totais
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT DATE(os.data_servico)) AS dias_trabalhados,
                COUNT(os.id) AS total_servicos,
                SUM(os.total) AS total_valor_servicos,
                SUM(d.valor_pago) AS total_valor_pago,
                SUM(CASE WHEN d.status_pagamento = 'pendente' THEN d.valor_diaria ELSE 0 END) AS total_pendente
            FROM ordens_servico os
            LEFT JOIN diarias d ON d.ordem_id = os.id AND d.tecnico_id = os.tecnico_id
            WHERE os.tecnico_id = ? AND DATE_FORMAT(os.data_servico, '%Y-%m') = ?
        ");
        $stmt->execute([$tecnico_id, $mes_ano]);
        $totais = $stmt->fetch(PDO::FETCH_ASSOC);

        // Dados para gráfico de barras: Dias trabalhados por semana
        $stmt = $pdo->prepare("
            SELECT 
                WEEK(data_servico, 1) - WEEK(DATE_SUB(data_servico, INTERVAL DAYOFMONTH(data_servico)-1 DAY), 1) + 1 AS semana,
                COUNT(DISTINCT DATE(data_servico)) AS dias
            FROM ordens_servico
            WHERE tecnico_id = ? AND DATE_FORMAT(data_servico, '%Y-%m') = ?
            GROUP BY semana
            ORDER BY semana
        ");
        $stmt->execute([$tecnico_id, $mes_ano]);
        $semanas_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Erro ao carregar histórico: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Histórico do Técnico - UltraLimp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #2563EB;
            --secondary: #1D4ED8;
            --accent: #F59E0B;
            --light: #F9FAFB;
            --dark: #111827;
            --gray: #6B7280;
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            --header-gradient: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            --status-aberto: #17A2B8;
            --status-concluido: #28A745;
            --status-atrasado: #DC3545;
        }
        body { font-family: 'Inter', sans-serif; background: var(--light); }
        .dashboard-card { background: white; border-radius: 12px; box-shadow: var(--shadow); padding: 1.5rem; }
        .table-modern { --bs-table-bg: transparent; --bs-table-striped-bg: #f8fafc; border-collapse: separate; border-spacing: 0 8px; }
        .table-modern thead th { background: var(--header-gradient); color: white; border: none; padding: 1.2rem 1.5rem; }
        .table-modern tbody td { padding: 1.2rem 1.5rem; background: white; border: none; }
        .table-modern tbody tr { box-shadow: 0 2px 8px rgba(0,0,0,0.05); border-radius: 8px; }
        .status-badge { padding: 0.3rem 1rem; border-radius: 20px; font-size: 0.85rem; font-weight: 500; }
        .status-concluido { background-color: var(--status-concluido); color: white; }
        .status-pendente { background-color: var(--status-atrasado); color: white; }
        .status-pago { background-color: var(--status-aberto); color: white; }
        .chart-container { display: flex; justify-content: center; align-items: center; padding: 10px; }
        canvas { max-height: 200px; max-width: 100%; }
    </style>
</head>
<body>
    <?php require __DIR__ . '/navbar.php'; ?>

    <div class="container-fluid">
        <div class="header-section mb-4">
            <h2 class="text-dark fw-bold"><i class="fas fa-user-tie me-2"></i>Histórico do Técnico</h2>
        </div>

        <!-- Seleção de Técnico -->
        <div class="dashboard-card mb-4">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label for="tecnico_id" class="form-label">Selecione o Técnico</label>
                    <select name="tecnico_id" id="tecnico_id" class="form-select" onchange="this.form.submit()">
                        <option value="">-- Selecione um técnico --</option>
                        <?php foreach ($tecnicos as $tecnico): ?>
                            <option value="<?= $tecnico['id'] ?>" <?= $tecnico_id === $tecnico['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($tecnico['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="mes_ano" class="form-label">Mês/Ano</label>
                    <input type="month" name="mes_ano" id="mes_ano" class="form-control" value="<?= $mes_ano ?>" onchange="this.form.submit()">
                </div>
            </form>
        </div>

        <!-- Totais -->
        <?php if ($tecnico_id && !empty($totais)): ?>
            <div class="dashboard-card mb-4">
                <h4>Totais do Mês (<?= date('m/Y', strtotime($mes_ano)) ?>)</h4>
                <div class="row">
                    <div class="col-md-3">
                        <p><strong>Dias Trabalhados:</strong> <?= $totais['dias_trabalhados'] ?></p>
                    </div>
                    <div class="col-md-3">
                        <p><strong>Total de Serviços:</strong> <?= $totais['total_servicos'] ?></p>
                    </div>
                    <div class="col-md-3">
                        <p><strong>Valor Total Serviços:</strong> R$ <?= number_format($totais['total_valor_servicos'] ?? 0, 2, ',', '.') ?></p>
                    </div>
                    <div class="col-md-3">
                        <p><strong>Total Pago:</strong> R$ <?= number_format($totais['total_valor_pago'] ?? 0, 2, ',', '.') ?></p>
                    </div>
                    <div class="col-md-3">
                        <p><strong>Total Pendente:</strong> R$ <?= number_format($totais['total_pendente'] ?? 0, 2, ',', '.') ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Histórico do Técnico -->
        <?php if ($tecnico_id && !empty($historico)): ?>
            <div class="dashboard-card">
                <h3 class="mb-3">Histórico de <?= htmlspecialchars($tecnico_nome) ?></h3>
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars(urldecode($_GET['success'])) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <div class="table-responsive">
                    <table class="table table-modern table-hover">
                        <thead>
                            <tr>
                                <th><i class="fas fa-hashtag"></i> Ordem</th>
                                <th><i class="fas fa-user"></i> Cliente</th>
                                <th><i class="fas fa-calendar-day"></i> Data</th>
                                <th><i class="fas fa-clock"></i> Hora</th>
                                <th><i class="fas fa-dollar-sign"></i> Valor Serviço</th>
                                <th><i class="fas fa-tasks"></i> Status</th>
                                <th><i class="fas fa-money-bill"></i> Diária</th>
                                <th><i class="fas fa-wallet"></i> Pagamento</th>
                                <th><i class="fas fa-money-check-alt"></i> Valor Pago</th>
                                <th><i class="fas fa-cog"></i> Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historico as $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['numero_ordem']) ?></td>
                                    <td><?= htmlspecialchars($item['cliente_nome']) ?></td>
                                    <td><?= $item['data_servico'] ? date('d/m/Y', strtotime($item['data_servico'])) : 'N/A' ?></td>
                                    <td><?= htmlspecialchars($item['hora_servico'] ?? 'N/A') ?></td>
                                    <td>R$ <?= number_format($item['valor_servico'] ?? 0, 2, ',', '.') ?></td>
                                    <td>
                                        <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $item['status'])) ?>">
                                            <?= htmlspecialchars($item['status']) ?>
                                        </span>
                                    </td>
                                    <td>R$ <?= number_format($item['valor_diaria'] ?? 0, 2, ',', '.') ?></td>
                                    <td>
                                        <span class="status-badge <?= $item['status_pagamento'] === 'pago' ? 'status-pago' : 'status-pendente' ?>">
                                            <?= htmlspecialchars($item['status_pagamento'] ?? 'Pendente') ?>
                                        </span>
                                    </td>
                                    <td>R$ <?= number_format($item['valor_pago'] ?? 0, 2, ',', '.') ?></td>
                                    <td>
                                        <?php if ($item['diaria_id'] && $item['status_pagamento'] !== 'pago'): ?>
                                            <form method="POST" class="d-flex gap-2">
                                                <input type="hidden" name="diaria_id" value="<?= $item['diaria_id'] ?>">
                                                <input type="number" name="valor_pago" step="0.01" min="0" class="form-control form-control-sm" style="width: 100px;" placeholder="Valor" required>
                                                <button type="submit" name="pagar_diaria" class="btn btn-sm btn-success">Pagar</button>
                                            </form>
                                        <?php elseif (!$item['diaria_id']): ?>
                                            <span class="text-muted">Sem diária</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Gráficos -->
            <div class="dashboard-card mt-4">
                <h3 class="mb-4 text-center">Gráficos</h3>
                <div class="row justify-content-center g-4">
                    <div class="col-md-3 chart-container">
                        <canvas id="pieChart"></canvas>
                    </div>
                    <div class="col-md-3 chart-container">
                        <canvas id="barChart"></canvas>
                    </div>
                </div>
            </div>
        <?php elseif ($tecnico_id): ?>
            <div class="alert alert-info dashboard-card">
                <i class="fas fa-info-circle me-2"></i>Nenhum histórico encontrado para este técnico no período selecionado.
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    <?php if ($tecnico_id && !empty($historico)): ?>
        // Gráfico de Pizza: Status de Pagamento
        const pago = <?= json_encode($totais['total_valor_pago'] ?? 0) ?>;
        const pendente = <?= json_encode($totais['total_pendente'] ?? 0) ?>;
        new Chart(document.getElementById('pieChart'), {
            type: 'pie',
            data: {
                labels: ['Pago', 'Pendente'],
                datasets: [{
                    data: [pago, pendente],
                    backgroundColor: ['#28A745', '#DC3545']
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'top' }, title: { display: true, text: 'Status de Pagamento' } }
            }
        });

        // Gráfico de Barras: Dias Trabalhados por Semana
        const semanas = <?= json_encode(array_column($semanas_data, 'semana')) ?>;
        const diasPorSemana = <?= json_encode(array_column($semanas_data, 'dias')) ?>;
        new Chart(document.getElementById('barChart'), {
            type: 'bar',
            data: {
                labels: semanas.map(s => `Semana ${s}`),
                datasets: [{
                    label: 'Dias Trabalhados',
                    data: diasPorSemana,
                    backgroundColor: '#2563EB'
                }]
            },
            options: {
                responsive: true,
                plugins: { title: { display: true, text: 'Dias por Semana' } }
            }
        });
    <?php endif; ?>
    </script>
</body>
</html>