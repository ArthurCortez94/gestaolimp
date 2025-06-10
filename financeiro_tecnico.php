<?php
ob_start();
session_start();
require_once 'config.php';

// Verificação de segurança
if (!isset($_SESSION['user_id']) || $_SESSION['user_cargo'] !== 'tecnico') {
    header("Location: login.php");
    exit();
}

// Controle de inatividade (30 minutos)
$_SESSION['user_last_active'] = $_SESSION['user_last_active'] ?? time();
if ((time() - $_SESSION['user_last_active']) > 1800) {
    session_unset();
    session_destroy();
    header("Location: login.php?expired=1");
    exit();
}
$_SESSION['user_last_active'] = time();

$tecnico_id = $_SESSION['user_id'];
$error = '';
$success = '';
$mes_ano = isset($_GET['mes_ano']) ? $_GET['mes_ano'] : date('Y-m');

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    file_put_contents('debug.log', "POST Recebido: " . print_r($_POST, true) . "\n", FILE_APPEND);
    try {
        if (isset($_POST['informar_valor'])) {
            $valor_informado = floatval($_POST['valor_informado']);
            $motivo = trim($_POST['motivo']);
            if (empty($motivo) || $valor_informado <= 0) {
                $error = "Por favor, informe o valor e o motivo.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO valores_informados (tecnico_id, valor_informado, motivo) VALUES (?, ?, ?)");
                $stmt->execute([$tecnico_id, $valor_informado, $motivo]);
                $success = "Valor informado com sucesso!";
            }
        }

        if (!$error) {
            header("Location: " . $_SERVER['PHP_SELF'] . "?mes_ano=$mes_ano&success=" . urlencode($success));
            exit();
        }
    } catch (PDOException $e) {
        $error = "Erro ao processar a ação: " . $e->getMessage();
        file_put_contents('debug.log', "Erro: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}

// Carregar dados financeiros
try {
    // Histórico de diárias
    $stmt = $pdo->prepare("
        SELECT 
            d.id AS diaria_id,
            os.numero_ordem,
            os.data_servico,
            d.valor_diaria,
            d.horas_trabalhadas,
            d.status_pagamento,
            d.valor_pago
        FROM diarias d
        LEFT JOIN ordens_servico os ON d.ordem_id = os.id
        WHERE d.tecnico_id = ? AND DATE_FORMAT(os.data_servico, '%Y-%m') = ?
        ORDER BY os.data_servico DESC
    ");
    $stmt->execute([$tecnico_id, $mes_ano]);
    $diarias = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Pagamentos extras
    $stmt = $pdo->prepare("
        SELECT valor_extra, descricao, data_pagamento 
        FROM pagamentos_extras 
        WHERE tecnico_id = ? AND DATE_FORMAT(data_pagamento, '%Y-%m') = ?
        ORDER BY data_pagamento DESC
    ");
    $stmt->execute([$tecnico_id, $mes_ano]);
    $pagamentos_extras = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Valores informados
    $stmt = $pdo->prepare("
        SELECT valor_informado, motivo, data_informada 
        FROM valores_informados 
        WHERE tecnico_id = ? AND DATE_FORMAT(data_informada, '%Y-%m') = ?
        ORDER BY data_informada DESC
    ");
    $stmt->execute([$tecnico_id, $mes_ano]);
    $valores_informados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Totais
    $stmt = $pdo->prepare("
        SELECT 
            (IFNULL(SUM(d.valor_pago), 0) + IFNULL((SELECT SUM(valor_extra) FROM pagamentos_extras pe WHERE pe.tecnico_id = ? AND DATE_FORMAT(pe.data_pagamento, '%Y-%m') = ?), 0)) AS total_valor_pago,
            SUM(CASE WHEN d.status_pagamento = 'pendente' AND d.valor_diaria > 0 THEN d.valor_diaria ELSE 0 END) AS total_pendente
        FROM diarias d
        LEFT JOIN ordens_servico os ON d.ordem_id = os.id
        WHERE d.tecnico_id = ? AND DATE_FORMAT(os.data_servico, '%Y-%m') = ?
    ");
    $stmt->execute([$tecnico_id, $mes_ano, $tecnico_id, $mes_ano]);
    $totais = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao carregar dados financeiros: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financeiro - UltraLimp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #2A5C82;
            --secondary: #4CAF50;
            --accent: #FFC107;
            --light: #f8fafc;
            --dark: #1A3C5A;
            --danger: #dc3545;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--light);
            padding-top: 80px;
        }
        .dashboard-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            padding: 1.5rem;
        }
        .modal-open .dashboard-card {
            transform: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        .finance-item {
            border-left: 4px solid;
            padding-left: 10px;
            margin-bottom: 1rem;
            transition: background-color 0.2s;
        }
        .finance-item.pago { border-color: var(--secondary); background-color: rgba(76, 175, 80, 0.05); }
        .finance-item.pendente { border-color: var(--danger); background-color: rgba(220, 53, 69, 0.05); }
        .finance-item.informado { border-color: var(--accent); background-color: rgba(255, 193, 7, 0.05); }
        .finance-item:hover { background-color: rgba(0, 0, 0, 0.05); }
        .btn-primary { background-color: var(--primary); border-color: var(--primary); }
        .btn-primary:hover { background-color: var(--dark); border-color: var(--dark); }
        .btn-success { background-color: var(--secondary); border-color: var(--secondary); }
        .btn-success:hover { background-color: #3d8b40; border-color: #3d8b40; }
        .modal-custom {
            background: #fff;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            border: none;
            overflow: hidden;
        }
        .modal-custom .modal-header {
            background: var(--primary);
            color: white;
            border-bottom: none;
            padding: 1.5rem;
        }
        .modal-custom .modal-title {
            font-weight: 600;
            display: flex;
            align-items: center;
        }
        .modal-custom .modal-body {
            padding: 2rem;
            background: #fff;
        }
        .modal-custom .modal-footer {
            border-top: none;
            padding: 1rem 2rem;
            background: #f8fafc;
        }
        .modal-custom .form-label {
            color: var(--primary);
            font-weight: 500;
        }
        .modal-custom .form-control {
            border-radius: 8px;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
        }
        .modal-custom .btn-primary {
            background: var(--secondary);
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 25px;
        }
        .modal-custom .btn-primary:hover {
            background: #3d8b40;
        }
        .modal-custom .btn-secondary {
            background: #6c757d;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 25px;
        }
        .modal-custom .btn-secondary:hover {
            background: #5a6268;
        }
        .info-section {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        .info-label {
            font-weight: bold;
            color: var(--primary);
            margin-bottom: 0.25rem;
        }
        .info-text { margin-bottom: 0.5rem; color: #333; }
    </style>
</head>
<body>
    <?php require __DIR__ . '/navbar_tecnico.php'; ?>

    <div class="container-fluid px-4 py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="text-primary fw-bold mb-0">Meu Financeiro</h2>
            <a href="dashboard.php" class="btn btn-primary"><i class="fas fa-arrow-left me-1"></i>Voltar ao Dashboard</a>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_GET['success']) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Filtro por Mês/Ano -->
        <div class="dashboard-card mb-4">
            <form method="GET" class="row g-3 align-items-end">
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
                    <div class="col-md-6">
                        <p><strong>Total Pago:</strong> R$ <?= number_format($totais['total_valor_pago'] ?? 0, 2, ',', '.') ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Total Pendente:</strong> R$ <?= number_format($totais['total_pendente'] ?? 0, 2, ',', '.') ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Histórico Financeiro -->
        <div class="dashboard-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="fw-bold"><i class="fas fa-wallet me-2"></i>Histórico Financeiro</h4>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#informarValorModal">
                    <i class="fas fa-plus me-2"></i>Informar Valor Extra
                </button>
            </div>
            <?php if (!empty($diarias) || !empty($pagamentos_extras) || !empty($valores_informados)): ?>
                <?php foreach ($diarias as $diaria): ?>
                    <div class="finance-item <?= $diaria['status_pagamento'] === 'pago' ? 'pago' : 'pendente' ?>" data-bs-toggle="modal" data-bs-target="#diariaModal_<?= $diaria['diaria_id'] ?>">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-semibold"><i class="fas fa-money-bill me-2"></i>Ordem #<?= htmlspecialchars($diaria['numero_ordem']) ?></div>
                                <small class="text-muted">Data: <?= date('d/m/Y', strtotime($diaria['data_servico'])) ?> | Status: <?= ucfirst($diaria['status_pagamento']) ?></small>
                            </div>
                            <span>R$ <?= number_format($diaria['valor_pago'] ?? $diaria['valor_diaria'], 2, ',', '.') ?></span>
                        </div>
                    </div>

                    <!-- Modal para Detalhes da Diária -->
                    <div class="modal" id="diariaModal_<?= $diaria['diaria_id'] ?>" tabindex="-1" aria-labelledby="diariaModalLabel_<?= $diaria['diaria_id'] ?>" aria-hidden="true">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-custom">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="diariaModalLabel_<?= $diaria['diaria_id'] ?>">
                                        <i class="fas fa-money-bill me-2"></i>Diária - Ordem #<?= htmlspecialchars($diaria['numero_ordem']) ?>
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="info-section">
                                        <div class="info-label">Data do Serviço</div>
                                        <div class="info-text"><?= date('d/m/Y', strtotime($diaria['data_servico'])) ?></div>
                                        <div class="info-label">Horas Trabalhadas</div>
                                        <div class="info-text"><?= htmlspecialchars($diaria['horas_trabalhadas']) ?></div>
                                        <div class="info-label">Valor da Diária</div>
                                        <div class="info-text">R$ <?= number_format($diaria['valor_diaria'], 2, ',', '.') ?></div>
                                        <div class="info-label">Status</div>
                                        <div class="info-text"><?= ucfirst($diaria['status_pagamento']) ?></div>
                                        <?php if ($diaria['valor_pago']): ?>
                                            <div class="info-label">Valor Pago</div>
                                            <div class="info-text">R$ <?= number_format($diaria['valor_pago'], 2, ',', '.') ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php foreach ($pagamentos_extras as $extra): ?>
                    <div class="finance-item pago">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-semibold"><i class="fas fa-gift me-2"></i>Pagamento Extra</div>
                                <small class="text-muted">Data: <?= date('d/m/Y', strtotime($extra['data_pagamento'])) ?> | <?= htmlspecialchars($extra['descricao']) ?></small>
                            </div>
                            <span>R$ <?= number_format($extra['valor_extra'], 2, ',', '.') ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php foreach ($valores_informados as $informado): ?>
                    <div class="finance-item informado">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-semibold"><i class="fas fa-hand-holding-usd me-2"></i>Valor Informado</div>
                                <small class="text-muted">Data: <?= date('d/m/Y', strtotime($informado['data_informada'])) ?> | <?= htmlspecialchars($informado['motivo']) ?></small>
                            </div>
                            <span>R$ <?= number_format($informado['valor_informado'], 2, ',', '.') ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-info">Nenhum registro financeiro encontrado para este período.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal para Informar Valor Extra -->
    <div class="modal" id="informarValorModal" tabindex="-1" aria-labelledby="informarValorModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-custom">
                <div class="modal-header">
                    <h5 class="modal-title" id="informarValorModalLabel"><i class="fas fa-hand-holding-usd me-2"></i>Informar Valor Extra</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="valor_informado" class="form-label">Valor Pago (R$)</label>
                            <input type="number" name="valor_informado" id="valor_informado" class="form-control" step="0.01" min="0" required placeholder="Ex.: 50,00">
                        </div>
                        <div class="mb-3">
                            <label for="motivo" class="form-label">Motivo</label>
                            <textarea name="motivo" id="motivo" class="form-control" rows="3" required placeholder="Ex.: Compra de material para o serviço"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="informar_valor" class="btn btn-primary">Registrar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>