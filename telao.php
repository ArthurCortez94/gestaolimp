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

try {
    // Métricas principais
    $orcamentos_dia = getMetric($pdo, "SELECT COUNT(*) FROM orcamentos WHERE DATE(validade) = CURDATE()");
    $valores_recebidos = getMetric($pdo, "SELECT COALESCE(SUM(total), 0) FROM ordens_servico WHERE status = 'Concluída'");
    $valores_a_receber = getMetric($pdo, "SELECT COALESCE(SUM(total), 0) FROM ordens_servico WHERE status = 'Agendado'");
    $orcamentos_abertos = getMetric($pdo, "SELECT COUNT(*) FROM orcamentos WHERE status = 'Aberto'");

    // Próximos 10 serviços agendados
    $proximos_servicos = $pdo->query("
        SELECT 
            os.id, 
            os.numero_ordem, 
            os.data_servico, 
            os.hora_servico, 
            os.status, 
            COALESCE(c.nome, 'Cliente não encontrado') AS cliente_nome,
            COALESCE(u.nome, 'Não atribuído') AS tecnico_nome
        FROM ordens_servico os
        LEFT JOIN clientes c ON os.cliente_id = c.id
        LEFT JOIN usuarios u ON os.tecnico_id = u.id
        WHERE os.status = 'Agendado' AND os.data_servico >= CURDATE()
        ORDER BY os.data_servico ASC, os.hora_servico ASC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Itens da lavanderia
    $lavanderia = $pdo->query("
        SELECT l.*, COALESCE(c.nome, 'Cliente não encontrado') AS cliente 
        FROM lavanderia l 
        LEFT JOIN clientes c ON l.cliente_id = c.id 
        ORDER BY l.data_prevista_entrega ASC 
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Processar o campo 'itens' (JSON) para cada registro da lavanderia
    foreach ($lavanderia as &$item) {
        $item['itens'] = json_decode($item['itens'], true) ?? [];
    }
    unset($item);

} catch (PDOException $e) {
    die("Erro ao buscar dados: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Atualização automática a cada 5 minutos (300 segundos) -->
    <meta http-equiv="refresh" content="300">
    <title>Telão UltraLimp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
            font-family: 'Inter', sans-serif;
            background: var(--light);
            padding: 2rem;
            color: var(--dark);
        }

        .header-section {
            background: linear-gradient(120deg, var(--primary) 0%, #2563EB 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
            text-align: center;
        }

        .header-section h1 {
            font-size: 2.5rem;
            margin: 0;
        }

        .metric-card {
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow);
            padding: 1.5rem;
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .metric-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 2rem;
            color: white;
        }

        .metric-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
        }

        .metric-label {
            font-size: 1.2rem;
            color: var(--gray);
        }

        .section-card {
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .section-card h2 {
            font-size: 1.8rem;
            margin-bottom: 1rem;
            color: var(--primary);
        }

        .table {
            font-size: 1.2rem;
        }

        .table th, .table td {
            padding: 1rem;
            vertical-align: middle;
        }

        .table th {
            background: var(--primary);
            color: white;
            font-weight: 600;
        }

        .table tbody tr:nth-child(odd) {
            background: #f8f9fa;
        }

        .badge {
            font-size: 1rem;
            padding: 0.5rem 1rem;
        }

        @media (max-width: 1200px) {
            .header-section h1 {
                font-size: 2rem;
            }

            .metric-value {
                font-size: 1.8rem;
            }

            .metric-label {
                font-size: 1rem;
            }

            .section-card h2 {
                font-size: 1.5rem;
            }

            .table {
                font-size: 1rem;
            }
        }

        @media (max-width: 768px) {
            .header-section h1 {
                font-size: 1.5rem;
            }

            .metric-value {
                font-size: 1.5rem;
            }

            .metric-label {
                font-size: 0.9rem;
            }

            .metric-icon {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }

            .section-card h2 {
                font-size: 1.2rem;
            }

            .table {
                font-size: 0.9rem;
            }

            .table th, .table td {
                padding: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Header -->
        <div class="header-section">
            <h1><i class="fas fa-tachometer-alt me-2"></i>Telão UltraLimp - Controle Operacional</h1>
        </div>

        <!-- Métricas Principais -->
        <div class="row g-4 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="metric-card">
                    <div class="metric-icon bg-primary">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div class="metric-value"><?= (int)$orcamentos_dia ?></div>
                    <div class="metric-label">Orçamentos Hoje</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="metric-card">
                    <div class="metric-icon bg-success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="metric-value">R$ <?= number_format((float)$valores_recebidos, 2, ',', '.') ?></div>
                    <div class="metric-label">Recebidos</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="metric-card">
                    <div class="metric-icon bg-warning">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="metric-value">R$ <?= number_format((float)$valores_a_receber, 2, ',', '.') ?></div>
                    <div class="metric-label">A Receber</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="metric-card">
                    <div class="metric-icon bg-info">
                        <i class="fas fa-folder-open"></i>
                    </div>
                    <div class="metric-value"><?= (int)$orcamentos_abertos ?></div>
                    <div class="metric-label">Orçamentos Abertos</div>
                </div>
            </div>
        </div>

        <!-- Serviços Agendados -->
        <div class="section-card">
            <h2><i class="fas fa-calendar-check me-2"></i>Próximos Serviços Agendados</h2>
            <?php if (count($proximos_servicos) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Ordem</th>
                                <th>Cliente</th>
                                <th>Técnico</th>
                                <th>Data/Hora</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($proximos_servicos as $servico): ?>
                                <tr>
                                    <td><?= htmlspecialchars($servico['numero_ordem']) ?></td>
                                    <td><?= htmlspecialchars($servico['cliente_nome']) ?></td>
                                    <td><?= htmlspecialchars($servico['tecnico_nome']) ?></td>
                                    <td>
                                        <?php
                                        $data_servico = $servico['data_servico'] ? date('d/m/Y', strtotime($servico['data_servico'])) : 'N/A';
                                        $hora_servico = $servico['hora_servico'] ? date('H:i', strtotime($servico['hora_servico'])) : 'N/A';
                                        echo $data_servico . ' ' . $hora_servico;
                                        ?>
                                    </td>
                                    <td><span class="badge bg-primary"><?= htmlspecialchars($servico['status']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted text-center">Nenhum serviço agendado no momento.</p>
            <?php endif; ?>
        </div>

        <!-- Itens da Lavanderia -->
        <div class="section-card">
            <h2><i class="fas fa-tshirt me-2"></i>Itens da Lavanderia</h2>
            <?php if (count($lavanderia) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>Itens</th>
                                <th>Status</th>
                                <th>Previsão de Entrega</th>
                                <th>Valor Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lavanderia as $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['cliente']) ?></td>
                                    <td>
                                        <?php
                                        $first_item = !empty($item['itens']) ? $item['itens'][0] : null;
                                        $item_name = $first_item ? htmlspecialchars($first_item['nome_item']) : 'N/A';
                                        $item_count = $first_item ? count($item['itens']) : 0;
                                        echo $item_name;
                                        if ($item_count > 1) echo " (+".($item_count-1).")";
                                        ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= match(strtolower($item['status'])) {
                                            'em processamento' => 'warning',
                                            'lavado' => 'info',
                                            'pronto para retirada' => 'success',
                                            default => 'secondary'
                                        } ?>">
                                            <?= htmlspecialchars($item['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= $item['data_prevista_entrega'] ? date('d/m/Y', strtotime($item['data_prevista_entrega'])) : 'N/A' ?>
                                    </td>
                                    <td>R$ <?= number_format((float)$item['valor_total_geral'], 2, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted text-center">Nenhum item na lavanderia no momento.</p>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>