<?php
declare(strict_types=1);
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => true, // Ajuste para false se não usar HTTPS
    'use_strict_mode' => true
]);

// Verificação de autenticação
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Controle de inatividade (30 minutos)
const SESSION_TIMEOUT = 1800;
$_SESSION['user_last_active'] = $_SESSION['user_last_active'] ?? time();
if ((time() - $_SESSION['user_last_active']) > SESSION_TIMEOUT) {
    session_unset();
    session_destroy();
    header("Location: login.php?expired=1");
    exit();
}
$_SESSION['user_last_active'] = time();

// Gerar CSRF Token
$_SESSION['csrf_token'] = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));

require_once 'config.php';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$error = '';
$success = '';
$empreitada = null;

// Verificar se o ID foi fornecido
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    header("Location: empreitadas_list.php?error=ID+inválido");
    exit();
}

$empreitada_id = (int)$_GET['id'];

// Carregar os dados da empreitada
try {
    $stmt = $pdo->prepare("SELECT * FROM empreitadas WHERE id = ?");
    $stmt->execute([$empreitada_id]);
    $empreitada = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$empreitada) {
        header("Location: empreitadas_list.php?error=Empreitada+não+encontrada");
        exit();
    }
} catch (PDOException $e) {
    die("Erro ao carregar empreitada: " . $e->getMessage());
}

// Processar exclusão de atualização
if (isset($_GET['delete_atualizacao_id'])) {
    $atualizacao_id = (int)$_GET['delete_atualizacao_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM atualizacoes_empreitada WHERE id = ? AND empreitada_id = ?");
        $stmt->execute([$atualizacao_id, $empreitada_id]);
        $success = "Atualização excluída com sucesso!";
        header("Location: empreitada_detalhes.php?id=$empreitada_id&success=" . urlencode($success));
        exit();
    } catch (PDOException $e) {
        $error = "Erro ao excluir atualização: " . $e->getMessage();
    }
}

// Carregar as atualizações associadas
try {
    $stmt = $pdo->prepare("SELECT * FROM atualizacoes_empreitada WHERE empreitada_id = ? ORDER BY data_gasto DESC");
    $stmt->execute([$empreitada_id]);
    $atualizacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calcular o total de gastos (somente atualizações com valor)
    $total_gastos = 0;
    foreach ($atualizacoes as $atualizacao) {
        if (!is_null($atualizacao['valor'])) {
            $total_gastos += (float)$atualizacao['valor'];
        }
    }
    $saldo_restante = $empreitada['valor_total'] - $total_gastos;
} catch (PDOException $e) {
    die("Erro ao carregar atualizações: " . $e->getMessage());
}

// Processar o formulário de adição de atualização
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar CSRF Token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Token CSRF inválido.";
    } else {
        $data_gasto = $_POST['data_gasto'] ?: null;
        $categoria = trim(filter_input(INPUT_POST, 'categoria', FILTER_SANITIZE_STRING) ?? '');
        $valor = filter_input(INPUT_POST, 'valor', FILTER_VALIDATE_FLOAT);
        $observacao = trim(filter_input(INPUT_POST, 'observacao', FILTER_SANITIZE_STRING) ?? '');

        // Validações
        if (!$data_gasto) {
            $error = "A data da atualização é obrigatória.";
        } elseif (empty($categoria)) {
            $error = "A categoria é obrigatória.";
        } elseif ($valor !== false && $valor < 0) {
            $error = "O valor não pode ser negativo.";
        } elseif (empty($observacao)) {
            $error = "A observação é obrigatória.";
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO atualizacoes_empreitada (
                        empreitada_id,
                        data_gasto,
                        categoria,
                        valor,
                        observacao,
                        created_at
                    ) VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $empreitada_id,
                    $data_gasto,
                    $categoria,
                    $valor !== false ? $valor : null,
                    $observacao
                ]);
                $success = "Atualização registrada com sucesso!";
                header("Location: empreitada_detalhes.php?id=$empreitada_id&success=" . urlencode($success));
                exit();
            } catch (PDOException $e) {
                $error = "Erro ao registrar atualização: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes da Empreitada - UltraLimp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
        --status-em-andamento: #FFC107;
        --status-concluido: #28A745;
    }

    body {
        font-family: 'Inter', sans-serif;
        background: var(--light);
        padding-top: 80px;
    }

    .container-fluid {
        max-width: 1200px;
    }

    .dashboard-card {
        background: white;
        border-radius: 12px;
        box-shadow: var(--shadow);
        padding: 2rem;
        margin-bottom: 2rem;
        transition: transform 0.3s ease;
        border: 1px solid rgba(203, 213, 225, 0.3);
        backdrop-filter: blur(4px);
        background: rgba(255, 255, 255, 0.98);
    }

    .dashboard-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 30px rgba(37, 99, 235, 0.15);
    }

    .section-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--primary);
        margin-bottom: 1rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid rgba(203, 213, 225, 0.3);
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
    }

    .form-group-full {
        grid-column: 1 / -1;
    }

    .form-label {
        font-weight: 600;
        color: var(--gray);
        margin-bottom: 0.25rem;
    }

    .form-control, .form-select {
        border-radius: 8px;
        border: 1px solid rgba(203, 213, 225, 0.3);
    }

    .form-control:focus, .form-select:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.25);
    }

    .btn-primary {
        background-color: var(--primary);
        border-color: var(--primary);
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
    }

    .btn-primary:hover {
        background-color: var(--secondary);
        border-color: var(--secondary);
    }

    .btn-secondary {
        background-color: var(--gray);
        border-color: var(--gray);
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
    }

    .btn-secondary:hover {
        background-color: #5c636a;
        border-color: #5c636a;
    }

    .btn-danger {
        padding: 0.5rem 1rem;
        border-radius: 8px;
    }

    .is-invalid {
        border-color: #dc3545;
    }

    .invalid-feedback {
        font-size: 0.875rem;
    }

    .table-modern {
        --bs-table-bg: transparent;
        --bs-table-striped-bg: #f8fafc;
        margin-bottom: 0;
        border-collapse: separate;
        border-spacing: 0 8px;
    }

    .table-modern thead th {
        background: var(--header-gradient);
        color: white;
        border: none;
        padding: 1.2rem 1.5rem;
        font-weight: 600;
        letter-spacing: 0.5px;
        position: relative;
        transition: all 0.3s ease;
    }

    .table-modern thead th:first-child {
        border-radius: 12px 0 0 12px;
    }

    .table-modern thead th:last-child {
        border-radius: 0 12px 12px 0;
    }

    .table-modern thead th:not(:last-child)::after {
        content: '';
        position: absolute;
        right: 0;
        top: 25%;
        height: 50%;
        width: 1px;
        background: rgba(255,255,255,0.2);
    }

    .table-modern tbody td {
        padding: 1.2rem 1.5rem;
        background: white;
        border: none;
        vertical-align: middle;
        transition: all 0.2s ease;
    }

    .table-modern tbody tr {
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        border-radius: 8px;
    }

    .table-modern tbody tr:hover td {
        background: #F8FAFC;
        transform: translateX(8px);
    }

    .status-badge {
        padding: 0.3rem 1rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 500;
    }

    .status-em-andamento { background-color: var(--status-em-andamento); color: black; }
    .status-concluido { background-color: var(--status-concluido); color: white; }

    .column-icon {
        margin-right: 10px;
        font-size: 0.95em;
        opacity: 0.9;
    }

    @media (max-width: 768px) {
        .form-grid {
            grid-template-columns: 1fr;
        }
        .table-modern thead th {
            padding: 0.8rem;
            font-size: 0.9rem;
        }
        .table-modern tbody td {
            padding: 0.8rem;
            font-size: 0.9rem;
        }
        .column-icon {
            display: none;
        }
    }

    @media (max-width: 480px) {
        .table-modern tbody td {
            display: flex;
            flex-direction: column;
            align-items: start;
            gap: 0.5rem;
        }
        .table-modern tbody td::before {
            content: attr(data-label);
            font-weight: 600;
            color: var(--primary);
            font-size: 0.8rem;
        }
        .table-modern thead {
            display: none;
        }
        .table-modern tbody tr {
            display: block;
            margin-bottom: 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .table-modern tbody td {
            border-bottom: 1px solid #f1f5f9;
        }
        .table-modern tbody td:last-child {
            border-bottom: none;
        }
    }
    </style>
</head>
<body>
    <?php require __DIR__ . '/navbar.php'; ?>

    <div class="container-fluid px-4 py-4">
        <!-- Cabeçalho -->
        <div class="header-section mb-4 d-flex justify-content-between align-items-center">
            <h2 class="text-dark fw-bold"><i class="fas fa-briefcase me-2"></i>Detalhes da Empreitada: <?= htmlspecialchars($empreitada['titulo']) ?></h2>
            <a href="empreitadas_list.php" class="btn btn-primary px-4 py-2">
                <i class="fas fa-arrow-left me-2"></i>Voltar
            </a>
        </div>

        <!-- Alertas -->
        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success" role="alert"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <!-- Resumo Financeiro -->
        <div class="dashboard-card p-3 mb-4">
            <h5 class="fw-bold mb-3"><i class="fas fa-wallet me-2"></i>Resumo Financeiro</h5>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="dashboard-card p-3 text-center">
                        <div class="metric-value text-primary">R$ <?= number_format((float)$empreitada['valor_total'], 2, ',', '.') ?></div>
                        <div class="metric-label">Valor Total</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="dashboard-card p-3 text-center">
                        <div class="metric-value text-danger">R$ <?= number_format((float)$total_gastos, 2, ',', '.') ?></div>
                        <div class="metric-label">Gastos Acumulados</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="dashboard-card p-3 text-center">
                        <div class="metric-value text-success">R$ <?= number_format((float)$saldo_restante, 2, ',', '.') ?></div>
                        <div class="metric-label">Saldo Restante</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detalhes da Empreitada -->
        <div class="dashboard-card p-3 mb-4">
            <h5 class="fw-bold mb-3"><i class="fas fa-info-circle me-2"></i>Detalhes da Empreitada</h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <p><strong>Título:</strong> <?= htmlspecialchars($empreitada['titulo']) ?></p>
                    <p><strong>Ordem de Serviço:</strong> <?= htmlspecialchars($empreitada['ordem_servico'] ?: 'Não informado') ?></p>
                    <p><strong>Local do Serviço:</strong> <?= htmlspecialchars($empreitada['local_servico'] ?: 'Não informado') ?></p>
                    <p><strong>Cliente Responsável:</strong> <?= htmlspecialchars($empreitada['cliente_responsavel'] ?: 'Não informado') ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Data de Início:</strong> <?= date('d/m/Y', strtotime($empreitada['data_inicio'])) ?></p>
                    <p><strong>Data de Término:</strong> <?= date('d/m/Y', strtotime($empreitada['data_termino'])) ?></p>
                    <p><strong>Status:</strong> 
                        <span class="status-badge bg-<?= (strtotime($empreitada['data_termino']) < time()) ? 'success' : 'warning' ?> bg-opacity-10 text-<?= (strtotime($empreitada['data_termino']) < time()) ? 'success' : 'warning' ?>">
                            <?= (strtotime($empreitada['data_termino']) < time()) ? 'Concluído' : 'Em Andamento' ?>
                        </span>
                    </p>
                    <p><strong>Observações:</strong> <?= htmlspecialchars($empreitada['observacoes'] ?: 'Nenhuma') ?></p>
                </div>
            </div>
        </div>

        <!-- Formulário para Adicionar Atualização -->
        <div class="dashboard-card p-3 mb-4">
            <h5 class="fw-bold mb-3"><i class="fas fa-plus-circle me-2"></i>Adicionar Atualização Diária</h5>
            <form method="POST" id="atualizacaoForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <div class="form-grid">
                    <div>
                        <label for="data_gasto" class="form-label">Data <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="data_gasto" name="data_gasto" required aria-required="true">
                        <div class="invalid-feedback">Selecione a data da atualização.</div>
                    </div>
                    <div>
                        <label for="categoria" class="form-label">Categoria <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="categoria" name="categoria" required 
                               placeholder="Ex.: Diária, Gasolina, Compra, Observação" aria-required="true">
                        <div class="invalid-feedback">Digite a categoria da atualização.</div>
                    </div>
                    <div>
                        <label for="valor" class="form-label">Valor (R$)</label>
                        <input type="number" class="form-control" id="valor" name="valor" step="0.01" min="0" 
                               placeholder="Deixe em branco se não for um gasto">
                    </div>
                </div>
                <div class="form-group-full mt-3">
                    <label for="observacao" class="form-label">Observação <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="observacao" name="observacao" rows="2" required 
                              placeholder="Ex.: Diária de João - 8 horas, Compra de cimento, Observação do dia"></textarea>
                    <div class="invalid-feedback">Digite uma observação.</div>
                </div>
                <div class="text-end mt-3">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Adicionar Atualização</button>
                </div>
            </form>
        </div>

        <!-- Lista de Atualizações -->
        <div class="dashboard-card p-3">
            <h5 class="fw-bold mb-3"><i class="fas fa-list me-2"></i>Histórico de Atualizações Diárias</h5>
            <div class="table-responsive">
                <table class="table table-modern table-hover">
                    <thead>
                        <tr>
                            <th><i class="fas fa-calendar-day column-icon"></i>Data</th>
                            <th><i class="fas fa-tag column-icon"></i>Categoria</th>
                            <th><i class="fas fa-dollar-sign column-icon"></i>Valor</th>
                            <th><i class="fas fa-sticky-note column-icon"></i>Observação</th>
                            <th><i class="fas fa-cog column-icon"></i>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($atualizacoes)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4">Nenhuma atualização registrada</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($atualizacoes as $atualizacao): ?>
                                <tr>
                                    <td data-label="Data"><?= date('d/m/Y', strtotime($atualizacao['data_gasto'])) ?></td>
                                    <td data-label="Categoria"><?= htmlspecialchars($atualizacao['categoria']) ?></td>
                                    <td data-label="Valor"><?= is_null($atualizacao['valor']) ? 'N/A' : 'R$ ' . number_format((float)$atualizacao['valor'], 2, ',', '.') ?></td>
                                    <td data-label="Observação"><?= htmlspecialchars($atualizacao['observacao']) ?></td>
                                    <td data-label="Ações">
                                        <a href="?id=<?= $empreitada_id ?>&delete_atualizacao_id=<?= $atualizacao['id'] ?>" 
                                           class="btn btn-sm btn-danger px-3 py-2 rounded-pill"
                                           onclick="return confirm('Tem certeza que deseja excluir esta atualização?')">
                                            <i class="fas fa-trash me-1"></i><span class="d-none d-md-inline">Excluir</span>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('atualizacaoForm');

        // Validação no lado do cliente
        form.addEventListener('submit', (e) => {
            let isValid = true;
            form.querySelectorAll('[required]').forEach(input => {
                if (!input.value.trim()) {
                    input.classList.add('is-invalid');
                    isValid = false;
                } else {
                    input.classList.remove('is-invalid');
                }
            });

            const valor = parseFloat(document.getElementById('valor').value);
            if (!isNaN(valor) && valor < 0) {
                document.getElementById('valor').classList.add('is-invalid');
                isValid = false;
            }

            if (!isValid) {
                e.preventDefault();
                form.classList.add('was-validated');
            }
        });

        form.querySelectorAll('input, select, textarea').forEach(input => {
            input.addEventListener('input', () => {
                if (input.value.trim()) input.classList.remove('is-invalid');
            });
        });
    });
    </script>
</body>
</html>