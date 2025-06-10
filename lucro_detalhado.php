<?php
declare(strict_types=1);
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_cargo'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$_SESSION['user_last_active'] = $_SESSION['user_last_active'] ?? time();
if ((time() - $_SESSION['user_last_active']) > 1800) {
    session_unset();
    session_destroy();
    header("Location: login.php?expired=1");
    exit();
}
$_SESSION['user_last_active'] = time();

require_once 'config.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function getFinancialMetric(PDO $pdo, string $table, string $column, string $condition = '', array $params = []): float {
    $query = "SELECT COALESCE(SUM($column), 0) FROM $table" . ($condition ? " WHERE $condition" : "");
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return floatval($stmt->fetchColumn());
}

function getDailyServices(PDO $pdo, string $date, ?int $tecnico_id = null): array {
    try {
        $query = "
            SELECT os.*, c.endereco, c.cep, u.nome AS tecnico_nome, u.id AS tecnico_id
            FROM ordens_servico os 
            JOIN clientes c ON os.cliente_id = c.id 
            JOIN usuarios u ON os.tecnico_id = u.id
            WHERE DATE(os.data_servico) = :date
            AND os.status IN ('Agendado', 'Em Andamento', 'Concluída')
        ";
        if ($tecnico_id) {
            $query .= " AND u.id = :tecnico_id";
        }
        $query .= " ORDER BY u.id";
        $stmt = $pdo->prepare($query);
        $params = ['date' => $date];
        if ($tecnico_id) $params['tecnico_id'] = $tecnico_id;
        $stmt->execute($params);
        $services = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
        $servicesByTechnician = [];
        foreach ($services as $service) {
            $servicesByTechnician[$service['tecnico_id']][] = $service;
        }
        return $servicesByTechnician;
    } catch (PDOException $e) {
        error_log("Erro em getDailyServices: " . $e->getMessage());
        return [];
    }
}

function getCoordenadasPorCep($cep): array {
    $url = "https://nominatim.openstreetmap.org/search?format=json&country=Brazil&postalcode=" . urlencode($cep);
    $response = @file_get_contents($url);
    if ($response === false) return ['lat' => 0, 'lon' => 0];
    $data = json_decode($response, true);
    return !empty($data) ? ['lat' => floatval($data[0]['lat']), 'lon' => floatval($data[0]['lon'])] : ['lat' => 0, 'lon' => 0];
}

function calcularRotaOSRM($cep_base, array $servicos, float $km_por_litro, float $preco_combustivel): array {
    $coords = [getCoordenadasPorCep($cep_base)];
    $ceps = [$cep_base];
    foreach ($servicos as $servico) {
        if (!empty($servico['cep'])) {
            $coords[] = getCoordenadasPorCep($servico['cep']);
            $ceps[] = $servico['cep'];
        }
    }
    $coords[] = getCoordenadasPorCep($cep_base);
    $ceps[] = $cep_base;

    if (count($coords) < 3) {
        return ['distancia' => 30.0, 'custo' => (30.0 / $km_por_litro) * $preco_combustivel, 'rota' => implode(' -> ', $ceps)];
    }

    $distancia_total = 0.0;
    for ($i = 0; $i < count($coords) - 1; $i++) {
        $origem = $coords[$i]['lon'] . ',' . $coords[$i]['lat'];
        $destino = $coords[$i + 1]['lon'] . ',' . $coords[$i + 1]['lat'];
        $url = "http://router.project-osm.org/route/v1/driving/$origem;$destino?overview=false&steps=false";
        $response = @file_get_contents($url);
        
        if ($response === false) {
            $distancia_total += 30.0;
            continue;
        }

        $data = json_decode($response, true);
        $distancia_segmento = isset($data['routes'][0]['distance']) ? floatval($data['routes'][0]['distance'] / 1000) : 30.0;
        $distancia_total += $distancia_segmento;
    }

    $custo = ($distancia_total / $km_por_litro) * $preco_combustivel;
    return ['distancia' => $distancia_total, 'custo' => $custo, 'rota' => implode(' -> ', $ceps)];
}

try {
    $default_km_por_litro = 10.0;
    $default_preco_combustivel = 6.00;
    $default_cep_base = '59108-500';
    $current_date = date('Y-m-d');

    // Carregar valores da sessão ou usar padrões
    $km_por_litro = floatval($_SESSION['km_por_litro'] ?? $default_km_por_litro);
    $preco_combustivel = floatval($_SESSION['preco_combustivel'] ?? $default_preco_combustivel);

    $stmt = $pdo->query("SELECT id, nome FROM usuarios WHERE cargo = 'tecnico' ORDER BY nome");
    $tecnicos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $filtro_tecnico_id = isset($_GET['tecnico_id']) && $_GET['tecnico_id'] !== '' ? (int)$_GET['tecnico_id'] : null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cep_base'])) {
        $tecnico_id = (int)$_POST['tecnico_id'];
        $cep_base = trim($_POST['cep_base'] ?? $default_cep_base);
        $_SESSION['cep_base'][$tecnico_id] = $cep_base;
        header("Location: lucro_detalhado.php" . ($filtro_tecnico_id ? "?tecnico_id=$filtro_tecnico_id" : ""));
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_gastos'])) {
        $diaria = floatval($_POST['diaria'] ?? 0);
        $combustivel = floatval($_POST['combustivel'] ?? 0);
        $outros = floatval($_POST['outros'] ?? 0);
        $km_por_litro = floatval($_POST['km_por_litro'] ?? $default_km_por_litro);
        $preco_combustivel = floatval($_POST['preco_combustivel'] ?? $default_preco_combustivel);

        // Salvar na sessão
        $_SESSION['km_por_litro'] = $km_por_litro;
        $_SESSION['preco_combustivel'] = $preco_combustivel;

        foreach ($servicos_por_tecnico as $tecnico_id => $servicos) {
            $stmt = $pdo->prepare("INSERT INTO gastos_tecnicos (tecnico_id, descricao, valor, data_registro) VALUES (?, ?, ?, NOW())");
            if ($diaria > 0) $stmt->execute([$tecnico_id, 'Diária', $diaria]);
            if ($combustivel > 0) $stmt->execute([$tecnico_id, 'Combustível', $combustivel]);
            if ($outros > 0) $stmt->execute([$tecnico_id, 'Outros', $outros]);
        }
        header("Location: lucro_detalhado.php" . ($filtro_tecnico_id ? "?tecnico_id=$filtro_tecnico_id" : ""));
        exit();
    }

    $receita_mes = getFinancialMetric($pdo, 'ordens_servico', 'total', "status = 'Concluída' AND YEAR(data_servico) = YEAR(CURDATE()) AND MONTH(data_servico) = MONTH(CURDATE())");
    $gastos_mes = getFinancialMetric($pdo, 'gastos_tecnicos', 'valor', "YEAR(data_registro) = YEAR(CURDATE()) AND MONTH(data_registro) = MONTH(CURDATE())");
    $lucro_liquido = $receita_mes - $gastos_mes;
    $margem_lucro = $receita_mes > 0 ? ($lucro_liquido / $receita_mes) * 100 : 0.0;

    $servicos_por_tecnico = getDailyServices($pdo, $current_date, $filtro_tecnico_id);

    $receita_dia = getFinancialMetric($pdo, 'ordens_servico', 'total', "status = 'Concluída' AND DATE(data_servico) = CURDATE()");
    $gastos_dia = getFinancialMetric($pdo, 'gastos_tecnicos', 'valor', "DATE(data_registro) = CURDATE()");
    $lucro_dia = $receita_dia - $gastos_dia;

    $sugestoes = [];
    if ($margem_lucro < 80) {
        $sugestoes[] = "Margem de lucro mensal em " . number_format($margem_lucro, 1) . "%. Aumente preços em R$ " . number_format(($receita_mes * 0.8 - $lucro_liquido) / (count($servicos_por_tecnico) ?: 1), 2, ',', '.') . " por serviço para atingir 80%.";
    }

} catch (PDOException $e) {
    die("Erro ao buscar dados: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lucro Detalhado - UltraLimp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #2A5C82;
            --secondary: #4CAF50;
            --accent: #FFC107;
            --light: #F8FAFC;
            --dark: #1A2A44;
            --shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--light);
            padding-top: 70px;
            color: var(--dark);
        }
        .container-fluid {
            max-width: 1400px;
        }
        .dashboard-card {
            background: white;
            border-radius: 15px;
            box-shadow: var(--shadow);
            padding: 1.5rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .modal-open .dashboard-card:hover {
            transform: none;
            box-shadow: var(--shadow);
        }
        .metric-icon {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: grid;
            place-items: center;
            font-size: 1.5rem;
            color: white;
        }
        .metric-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
        }
        .metric-label {
            font-size: 0.95rem;
            color: #6c757d;
        }
        .table {
            border-radius: 10px;
            overflow: hidden;
        }
        .table thead {
            background: var(--primary);
            color: white;
        }
        .table tbody tr {
            transition: background 0.2s ease;
        }
        .table tbody tr:hover {
            background: #f1f5f9;
        }
        .btn-expand {
            background: none;
            border: none;
            color: var(--primary);
        }
        .btn-expand:hover {
            color: var(--accent);
        }
        .btn-cep {
            background: none;
            border: none;
            color: var(--primary);
            padding: 0.2rem 0.5rem;
        }
        .btn-cep:hover {
            color: var(--accent);
        }
        .form-control, .form-select {
            border-radius: 8px;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.05);
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), #1b4965);
            border: none;
            border-radius: 8px;
            transition: background 0.3s ease;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #1b4965, var(--primary));
        }
        .suggestion-list {
            background: #fff3e6;
            border-left: 4px solid var(--accent);
        }
        .details-row {
            display: none;
            background: #f8f9fa;
        }
    </style>
</head>
<body>

<?php require __DIR__ . '/navbar.php'; ?>

<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="text-primary fw-bold">Lucro Detalhado</h2>
        <button class="btn btn-primary"><i class="fas fa-download me-2"></i>Exportar</button>
    </div>

    <!-- Métricas Mensais -->
    <div class="row g-4 mb-4">
        <?php
        $metrics = [
            ['label' => 'Receita Total (Mês)', 'value' => $receita_mes, 'icon' => 'fa-money-bill-wave', 'color' => 'bg-success'],
            ['label' => 'Gastos Totais (Mês)', 'value' => $gastos_mes, 'icon' => 'fa-shopping-cart', 'color' => 'bg-danger'],
            ['label' => 'Lucro Líquido (' . number_format($margem_lucro, 1) . '%)', 'value' => $lucro_liquido, 'icon' => 'fa-wallet', 'color' => 'bg-primary']
        ];
        foreach ($metrics as $metric): ?>
            <div class="col-md-4">
                <div class="dashboard-card">
                    <div class="d-flex align-items-center gap-3">
                        <div class="metric-icon <?= $metric['color'] ?>"><i class="fas <?= $metric['icon'] ?>"></i></div>
                        <div>
                            <div class="metric-value">R$ <?= number_format($metric['value'], 2, ',', '.') ?></div>
                            <div class="metric-label"><?= $metric['label'] ?></div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Serviços do Dia por Técnico -->
    <div class="dashboard-card mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold"><i class="fas fa-road me-2"></i>Serviços de Hoje por Técnico</h5>
            <form method="GET" class="d-flex gap-2">
                <select name="tecnico_id" class="form-select" onchange="this.form.submit()">
                    <option value="">Todos os Técnicos</option>
                    <?php foreach ($tecnicos as $tecnico): ?>
                        <option value="<?= $tecnico['id'] ?>" <?= $filtro_tecnico_id === $tecnico['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($tecnico['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <?php if (is_array($servicos_por_tecnico) && count($servicos_por_tecnico) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Técnico</th>
                            <th>Serviços</th>
                            <th>Total (R$)</th>
                            <th>CEP Base</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($servicos_por_tecnico as $tecnico_id => $servicos): ?>
                            <tr>
                                <td><?= htmlspecialchars($servicos[0]['tecnico_nome']) ?></td>
                                <td><?= count($servicos) ?></td>
                                <td>R$ <?= number_format(array_sum(array_column($servicos, 'total')), 2, ',', '.') ?></td>
                                <td>
                                    <?php $cep_base_atual = $_SESSION['cep_base'][$tecnico_id] ?? $default_cep_base; ?>
                                    <span class="cep-display"><?= htmlspecialchars($cep_base_atual) ?></span>
                                    <button type="button" class="btn-cep" data-bs-toggle="modal" data-bs-target="#cepModal_<?= $tecnico_id ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </td>
                                <td>
                                    <button class="btn-expand" onclick="toggleDetails('details_<?= $tecnico_id ?>')">
                                        <i class="fas fa-chevron-down"></i>
                                    </button>
                                </td>
                            </tr>
                            <tr id="details_<?= $tecnico_id ?>" class="details-row">
                                <td colspan="5">
                                    <ul class="list-group mb-3">
                                        <?php foreach ($servicos as $servico): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <span>
                                                    Ordem #<?= htmlspecialchars($servico['numero_ordem']) ?> - 
                                                    <?= htmlspecialchars($servico['endereco']) ?> - 
                                                    CEP: <?= htmlspecialchars($servico['cep'] ?? 'Não informado') ?>
                                                </span>
                                                <span class="badge bg-<?= match($servico['status']) {
                                                    'Agendado' => 'warning',
                                                    'Em Andamento' => 'info',
                                                    'Concluída' => 'success',
                                                    default => 'secondary'
                                                } ?>">
                                                    R$ <?= number_format(floatval($servico['total']), 2, ',', '.') ?> - 
                                                    <?= htmlspecialchars($servico['status']) ?>
                                                </span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <?php $rota_info = calcularRotaOSRM($cep_base_atual, $servicos, $km_por_litro, $preco_combustivel); ?>
                                    <p><strong>Rota Completa:</strong> <?= htmlspecialchars($rota_info['rota']) ?></p>
                                    <p><strong>Distância Total (com retorno):</strong> <?= number_format($rota_info['distancia'], 2, ',', '.') ?> km</p>
                                    <p><strong>Custo Combustível:</strong> R$ <?= number_format($rota_info['custo'], 2, ',', '.') ?> (R$ <?= number_format($preco_combustivel, 2, ',', '.') ?>/L, <?= number_format($km_por_litro, 1) ?> km/L)</p>
                                </td>
                            </tr>

                            <!-- Modal para Editar CEP Base -->
                            <div class="modal fade" id="cepModal_<?= $tecnico_id ?>" tabindex="-1" aria-labelledby="cepModalLabel_<?= $tecnico_id ?>" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="cepModalLabel_<?= $tecnico_id ?>">Editar CEP Base - <?= htmlspecialchars($servicos[0]['tecnico_nome']) ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <form method="POST">
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label for="cep_base_<?= $tecnico_id ?>" class="form-label">CEP Base</label>
                                                    <input type="text" name="cep_base" id="cep_base_<?= $tecnico_id ?>" class="form-control" value="<?= htmlspecialchars($cep_base_atual) ?>" required>
                                                </div>
                                                <input type="hidden" name="tecnico_id" value="<?= $tecnico_id ?>">
                                                <input type="hidden" name="update_cep_base" value="1">
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                <button type="submit" class="btn btn-primary">Salvar</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">Nenhum serviço agendado para hoje.</div>
        <?php endif; ?>
    </div>

    <!-- Resumo do Dia -->
    <div class="dashboard-card mb-4">
        <h5 class="fw-bold mb-3"><i class="fas fa-calendar-day me-2"></i>Resumo do Dia</h5>
        <div class="row g-4 mb-3">
            <?php
            $daily_metrics = [
                ['label' => 'Receita do Dia', 'value' => $receita_dia, 'icon' => 'fa-money-bill-wave', 'color' => 'bg-success'],
                ['label' => 'Gastos do Dia', 'value' => $gastos_dia, 'icon' => 'fa-shopping-cart', 'color' => 'bg-danger'],
                ['label' => 'Lucro do Dia', 'value' => $lucro_dia, 'icon' => 'fa-wallet', 'color' => 'bg-primary']
            ];
            foreach ($daily_metrics as $metric): ?>
                <div class="col-md-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="metric-icon <?= $metric['color'] ?>"><i class="fas <?= $metric['icon'] ?>"></i></div>
                        <div>
                            <div class="metric-value">R$ <?= number_format($metric['value'], 2, ',', '.') ?></div>
                            <div class="metric-label"><?= $metric['label'] ?></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <form method="POST" class="mt-3">
            <h6 class="fw-bold mb-2">Registrar Gastos do Dia</h6>
            <div class="row g-3">
                <div class="col-md-3"><input type="number" name="diaria" class="form-control" step="0.01" min="0" placeholder="Diária (R$)" required></div>
                <div class="col-md-3"><input type="number" name="combustivel" class="form-control" step="0.01" min="0" placeholder="Combustível (R$)" required></div>
                <div class="col-md-3"><input type="number" name="outros" class="form-control" step="0.01" min="0" placeholder="Outros (R$)"></div>
                <div class="col-md-3">
                    <label class="form-label">Preço Combustível (R$/L)</label>
                    <input type="number" name="preco_combustivel" class="form-control" step="0.01" min="0" value="<?= number_format($preco_combustivel, 2, '.', '') ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Km por Litro</label>
                    <input type="number" name="km_por_litro" class="form-control" step="0.1" min="0" value="<?= number_format($km_por_litro, 1, '.', '') ?>" required>
                </div>
            </div>
            <button type="submit" name="registrar_gastos" class="btn btn-primary mt-3"><i class="fas fa-save me-2"></i>Salvar</button>
        </form>
    </div>

    <!-- Sugestões -->
    <div class="dashboard-card">
        <h5 class="fw-bold mb-3"><i class="fas fa-lightbulb me-2"></i>Sugestões</h5>
        <?php if (is_array($sugestoes) && count($sugestoes) > 0): ?>
            <ul class="list-group suggestion-list">
                <?php foreach ($sugestoes as $sugestao): ?>
                    <li class="list-group-item"><?= htmlspecialchars($sugestao) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div class="alert alert-success">Tudo parece estar otimizado!</div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleDetails(rowId) {
    const row = document.getElementById(rowId);
    row.style.display = row.style.display === 'table-row' ? 'none' : 'table-row';
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

document.querySelectorAll('.btn-cep').forEach(button => {
    const debouncedOpenModal = debounce(() => {
        const modalId = button.getAttribute('data-bs-target');
        const modal = new bootstrap.Modal(document.querySelector(modalId), {
            backdrop: 'static',
            keyboard: true
        });
        modal.show();
    }, 300);
    button.addEventListener('click', debouncedOpenModal);
});
</script>
</body>
</html>