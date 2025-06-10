<?php
session_start();
require_once 'config.php';

// Verificação de segurança
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Controle de inatividade (30 minutos)
if ((time() - $_SESSION['user_last_active']) > 1800) {
    session_unset();
    session_destroy();
    header("Location: login.php?expired=1");
    exit();
}
$_SESSION['user_last_active'] = time();

// Filtros
$mes_ano = isset($_GET['mes_ano']) ? $_GET['mes_ano'] : date('Y-m');
$tipo = isset($_GET['tipo']) ? $_GET['tipo'] : 'todos';
$status = isset($_GET['status']) ? $_GET['status'] : 'todos';

// Consulta para contas a pagar e receber
try {
    $where = ["DATE_FORMAT(data_vencimento, '%Y-%m') = :mes_ano"];
    $params = [':mes_ano' => $mes_ano];

    if ($tipo !== 'todos') {
        $where[] = "tipo = :tipo";
        $params[':tipo'] = $tipo;
    }
    if ($status !== 'todos') {
        $where[] = "status = :status";
        $params[':status'] = $status;
    }

    $whereClause = !empty($where) ? "WHERE " . implode(' AND ', $where) : "";

    $stmt = $pdo->prepare("
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
        $whereClause
        ORDER BY data_vencimento ASC
    ");
    $stmt->execute($params);
    $contas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Totais
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN tipo = 'pagar' AND status = 'pendente' THEN valor ELSE 0 END) AS total_pagar_pendente,
            SUM(CASE WHEN tipo = 'pagar' AND status = 'pago' THEN valor ELSE 0 END) AS total_pagar_pago,
            SUM(CASE WHEN tipo = 'receber' AND status = 'pendente' THEN valor ELSE 0 END) AS total_receber_pendente,
            SUM(CASE WHEN tipo = 'receber' AND status = 'pago' THEN valor ELSE 0 END) AS total_receber_pago
        FROM contas
        WHERE DATE_FORMAT(data_vencimento, '%Y-%m') = :mes_ano
    ");
    $stmt->execute([':mes_ano' => $mes_ano]);
    $totais = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao carregar dados: " . $e->getMessage());
}

// Processar pagamento de conta
if (isset($_POST['marcar_pago'])) {
    $conta_id = (int)$_POST['conta_id'];
    try {
        $stmt = $pdo->prepare("UPDATE contas SET status = 'pago', data_pagamento = CURDATE() WHERE id = ?");
        $stmt->execute([$conta_id]);
        header("Location: contas.php?mes_ano=$mes_ano&tipo=$tipo&status=$status&success=Conta+marcada+como+paga");
        exit;
    } catch (PDOException $e) {
        die("Erro ao marcar como pago: " . $e->getMessage());
    }
}

// Excluir conta
if (isset($_POST['excluir_conta'])) {
    $conta_id = (int)$_POST['conta_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM contas WHERE id = ?");
        $stmt->execute([$conta_id]);
        header("Location: contas.php?mes_ano=$mes_ano&tipo=$tipo&status=$status&success=Conta+excluída+com+sucesso");
        exit;
    } catch (PDOException $e) {
        die("Erro ao excluir conta: " . $e->getMessage());
    }
}

// Adicionar nova conta
if (isset($_POST['adicionar_conta'])) {
    try {
        $recorrente = isset($_POST['recorrente']) ? true : false;
        $periodicidade = $recorrente ? $_POST['periodicidade'] : 'unica';

        $stmt = $pdo->prepare("
            INSERT INTO contas (tipo, descricao, valor, data_vencimento, status)
            VALUES (:tipo, :descricao, :valor, :data_vencimento, 'pendente')
        ");
        $stmt->execute([
            ':tipo' => $_POST['tipo'],
            ':descricao' => $_POST['descricao'],
            ':valor' => floatval($_POST['valor']),
            ':data_vencimento' => $_POST['data_vencimento']
        ]);

        // Se for recorrente, criar próximas instâncias
        if ($recorrente && $periodicidade === 'mensal') {
            $data_vencimento = new DateTime($_POST['data_vencimento']);
            for ($i = 1; $i <= 11; $i++) { // 11 próximas parcelas
                $data_vencimento->modify('+1 month');
                $stmt->execute([
                    ':tipo' => $_POST['tipo'],
                    ':descricao' => $_POST['descricao'] . " (Parcela " . ($i + 1) . ")",
                    ':valor' => floatval($_POST['valor']),
                    ':data_vencimento' => $data_vencimento->format('Y-m-d')
                ]);
            }
        }

        header("Location: contas.php?mes_ano=$mes_ano&tipo=$tipo&status=$status&success=Conta+adicionada+com+sucesso");
        exit;
    } catch (PDOException $e) {
        die("Erro ao adicionar conta: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contas a Pagar e Receber - UltraLimp</title>
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
        font-family: 'Poppins', sans-serif;
        background: var(--light);
    }

    .dashboard-card {
        background: white;
        border-radius: 12px;
        box-shadow: var(--shadow);
        padding: 1.5rem;
        transition: transform 0.3s ease;
        margin-bottom: 1.5rem;
    }

    .dashboard-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 30px rgba(37, 99, 235, 0.15);
    }

    .header-section {
        background: linear-gradient(120deg, var(--primary) 0%, #2563EB 100%);
        color: white;
        padding: 1.5rem;
        border-radius: 12px;
        margin-bottom: 2rem;
    }

    .btn-primary {
        background: var(--primary);
        border: none;
        padding: 0.8rem 1.5rem;
        border-radius: 8px;
        transition: all 0.3s ease;
    }

    .btn-primary:hover {
        background: #1D4ED8;
        transform: translateY(-2px);
    }

    .btn-success {
        background: var(--secondary);
        border: none;
        padding: 0.8rem 1.5rem;
        border-radius: 8px;
        transition: all 0.3s ease;
    }

    .btn-success:hover {
        background: #0D9488;
        transform: translateY(-2px);
    }

    .btn-danger {
        background: #DC3545;
        border: none;
        padding: 0.8rem 1.5rem;
        border-radius: 8px;
        transition: all 0.3s ease;
    }

    .btn-danger:hover {
        background: #C82333;
        transform: translateY(-2px);
    }

    .table-contas {
        background: #f8fafc;
        border-radius: 8px;
        overflow: hidden;
    }

    .table-contas th {
        background: var(--primary);
        color: white;
        border: none;
    }

    .table-contas td {
        vertical-align: middle;
        border-bottom: 1px solid #ddd;
    }

    .status-badge {
        padding: 0.3rem 1rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 500;
    }

    .status-pendente { background-color: #F59E0B; color: white; }
    .status-pago { background-color: #10B981; color: white; }
    .status-atrasado { background-color: #DC3545; color: white; }

    .form-section h4 {
        color: var(--primary);
        font-weight: 600;
        border-bottom: 2px solid var(--primary);
        padding-bottom: 0.5rem;
        margin-bottom: 1.5rem;
    }

    @media (max-width: 768px) {
        .dashboard-card {
            padding: 1rem;
        }
        
        .btn {
            width: 100%;
            margin-top: 0.5rem;
        }
    }

    @media (max-width: 576px) {
        .header-section h2 {
            font-size: 1.5rem;
        }
        
        .table-contas {
            font-size: 0.9rem;
        }
    }
    </style>
</head>
<body>
    <?php require __DIR__ . '/navbar.php'; ?>

    <div class="container-fluid">
        <!-- Cabeçalho -->
        <div class="header-section">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="mb-0"><i class="fas fa-wallet me-2"></i>Contas a Pagar e Receber</h2>
                <div class="d-flex gap-2">
                    <a href="dashboard.php" class="btn btn-outline-light">
                        <i class="fas fa-arrow-left me-1"></i>Voltar ao Dashboard
                    </a>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="dashboard-card mb-4">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="mes_ano" class="form-label">Mês/Ano</label>
                    <input type="month" name="mes_ano" id="mes_ano" class="form-control" value="<?= $mes_ano ?>" onchange="this.form.submit()">
                </div>
                <div class="col-md-4">
                    <label for="tipo" class="form-label">Tipo</label>
                    <select name="tipo" id="tipo" class="form-select" onchange="this.form.submit()">
                        <option value="todos" <?= $tipo === 'todos' ? 'selected' : '' ?>>Todos</option>
                        <option value="pagar" <?= $tipo === 'pagar' ? 'selected' : '' ?>>A Pagar</option>
                        <option value="receber" <?= $tipo === 'receber' ? 'selected' : '' ?>>A Receber</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="status" class="form-label">Status</label>
                    <select name="status" id="status" class="form-select" onchange="this.form.submit()">
                        <option value="todos" <?= $status === 'todos' ? 'selected' : '' ?>>Todos</option>
                        <option value="pendente" <?= $status === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                        <option value="pago" <?= $status === 'pago' ? 'selected' : '' ?>>Pago</option>
                        <option value="atrasado" <?= $status === 'atrasado' ? 'selected' : '' ?>>Atrasado</option>
                    </select>
                </div>
            </form>
        </div>

        <!-- Totais -->
        <div class="dashboard-card mb-4">
            <h4><i class="fas fa-chart-pie me-2"></i>Totais do Mês (<?= date('m/Y', strtotime($mes_ano)) ?>)</h4>
            <div class="row">
                <div class="col-md-3">
                    <p><strong>A Pagar Pendente:</strong> R$ <?= number_format($totais['total_pagar_pendente'] ?? 0, 2, ',', '.') ?></p>
                </div>
                <div class="col-md-3">
                    <p><strong>A Pagar Pago:</strong> R$ <?= number_format($totais['total_pagar_pago'] ?? 0, 2, ',', '.') ?></p>
                </div>
                <div class="col-md-3">
                    <p><strong>A Receber Pendente:</strong> R$ <?= number_format($totais['total_receber_pendente'] ?? 0, 2, ',', '.') ?></p>
                </div>
                <div class="col-md-3">
                    <p><strong>A Receber Pago:</strong> R$ <?= number_format($totais['total_receber_pago'] ?? 0, 2, ',', '.') ?></p>
                </div>
            </div>
        </div>

        <!-- Formulário para Adicionar Conta -->
        <div class="dashboard-card mb-4">
            <h4><i class="fas fa-plus me-2"></i>Adicionar Nova Conta</h4>
            <form method="POST" class="row g-3">
                <div class="col-md-3">
                    <label for="tipo" class="form-label">Tipo</label>
                    <select name="tipo" id="tipo" class="form-select" required>
                        <option value="pagar">A Pagar</option>
                        <option value="receber">A Receber</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="descricao" class="form-label">Descrição</label>
                    <input type="text" name="descricao" id="descricao" class="form-control" required placeholder="Ex.: Pagamento Fornecedor">
                </div>
                <div class="col-md-2">
                    <label for="valor" class="form-label">Valor (R$)</label>
                    <input type="number" name="valor" id="valor" class="form-control" step="0.01" required placeholder="0,00">
                </div>
                <div class="col-md-2">
                    <label for="data_vencimento" class="form-label">Vencimento</label>
                    <input type="date" name="data_vencimento" id="data_vencimento" class="form-control" required>
                </div>
                <div class="col-md-2">
                    <div class="form-check mt-4">
                        <input class="form-check-input" type="checkbox" name="recorrente" id="recorrente" onchange="togglePeriodicidade()">
                        <label class="form-check-label" for="recorrente">Recorrente</label>
                    </div>
                    <select name="periodicidade" id="periodicidade" class="form-select mt-2" style="display: none;">
                        <option value="mensal">Mensal</option>
                        <option value="anual">Anual</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" name="adicionar_conta" class="btn btn-primary w-100 mt-4">
                        <i class="fas fa-save me-2"></i>Adicionar
                    </button>
                </div>
            </form>
        </div>

        <!-- Tabela de Contas -->
        <div class="dashboard-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4><i class="fas fa-list me-2"></i>Lista de Contas</h4>
                <button class="btn btn-primary" onclick="scrollToForm()">
                    <i class="fas fa-plus me-1"></i>Inserir Conta
                </button>
            </div>
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars(urldecode($_GET['success'])) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <div class="table-responsive">
                <table class="table table-contas table-hover">
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th>Descrição</th>
                            <th>Valor</th>
                            <th>Vencimento</th>
                            <th>Pagamento</th>
                            <th>Status</th>
                            <th>Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contas as $conta): ?>
                        <tr>
                            <td><?= $conta['tipo'] === 'pagar' ? 'A Pagar' : 'A Receber' ?></td>
                            <td><?= htmlspecialchars($conta['descricao']) ?></td>
                            <td>R$ <?= number_format($conta['valor'], 2, ',', '.') ?></td>
                            <td><?= date('d/m/Y', strtotime($conta['data_vencimento'])) ?></td>
                            <td><?= $conta['data_pagamento'] ? date('d/m/Y', strtotime($conta['data_pagamento'])) : 'N/A' ?></td>
                            <td>
                                <span class="status-badge status-<?= $conta['status_atualizado'] ?>">
                                    <?= ucfirst($conta['status_atualizado']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="d-flex gap-2">
                                    <?php if ($conta['status'] === 'pendente'): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="conta_id" value="<?= $conta['id'] ?>">
                                            <button type="submit" name="marcar_pago" class="btn btn-sm btn-success">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="conta_id" value="<?= $conta['id'] ?>">
                                        <button type="submit" name="excluir_conta" class="btn btn-sm btn-danger" onclick="return confirm('Tem certeza que deseja excluir esta conta?');">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function togglePeriodicidade() {
        const recorrente = document.getElementById('recorrente');
        const periodicidade = document.getElementById('periodicidade');
        periodicidade.style.display = recorrente.checked ? 'block' : 'none';
    }

    function scrollToForm() {
        document.querySelector('.dashboard-card:nth-child(3)').scrollIntoView({ behavior: 'smooth' });
    }
    </script>
</body>
</html>