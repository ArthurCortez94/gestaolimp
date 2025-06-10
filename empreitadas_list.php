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

require_once 'config.php';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Processar exclusão de empreitada
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM empreitadas WHERE id = ?");
        $stmt->execute([$delete_id]);
        header("Location: empreitadas_list.php?success=Empreitada+excluída+com+sucesso");
        exit;
    } catch (PDOException $e) {
        die("Erro ao excluir empreitada: " . $e->getMessage());
    }
}

// Carregar empreitadas
try {
    $stmt = $pdo->query("
        SELECT e.*, 
               COALESCE(SUM(a.valor), 0) AS total_gastos
        FROM empreitadas e
        LEFT JOIN atualizacoes_empreitada a ON e.id = a.empreitada_id AND a.valor IS NOT NULL
        GROUP BY e.id
        ORDER BY e.data_inicio DESC
    ");
    $empreitadas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao carregar empreitadas: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Empreitadas - UltraLimp</title>
    <!-- Carregar o CSS do Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
    <!-- Carregar o Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="sha512-9usAa10IRO0HhonpyAIVpjrylPvoDwiPUiKdWk5t3PyolY1cOd4DSE0Ga+ri4AuTroPR5aQvXU9xC6qOPnzF==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body>
    <?php require __DIR__ . '/navbar.php'; ?>

    <div class="container-fluid">
        <!-- Cabeçalho -->
        <div class="header-section mb-4 d-flex justify-content-between align-items-center">
            <h2 class="text-dark fw-bold"><i class="fas fa-briefcase me-2"></i>Gestão de Empreitadas</h2>
            <a href="empreitada_form.php" class="btn btn-primary px-4 py-2">
                <i class="fas fa-plus me-2"></i>Nova Empreitada
            </a>
        </div>

        <!-- Alertas -->
        <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show dashboard-card" role="alert">
            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars(urldecode($_GET['success'])) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <!-- Tabela -->
        <div class="dashboard-card">
            <div class="table-responsive">
                <table class="table table-modern table-hover">
                    <thead>
                        <tr>
                            <th><i class="fas fa-hashtag column-icon"></i>ID</th>
                            <th><i class="fas fa-briefcase column-icon"></i>Título</th>
                            <th><i class="fas fa-file-invoice column-icon"></i>Ordem de Serviço</th>
                            <th><i class="fas fa-map-marker-alt column-icon"></i>Local</th>
                            <th><i class="fas fa-user column-icon"></i>Cliente</th>
                            <th><i class="fas fa-dollar-sign column-icon"></i>Valor Total</th>
                            <th><i class="fas fa-money-bill-wave column-icon"></i>Gastos Acumulados</th>
                            <th><i class="fas fa-wallet column-icon"></i>Saldo Restante</th>
                            <th><i class="fas fa-calendar-day column-icon"></i>Início</th>
                            <th><i class="fas fa-calendar-check column-icon"></i>Término</th>
                            <th><i class="fas fa-tasks column-icon"></i>Status</th>
                            <th><i class="fas fa-cog column-icon"></i>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($empreitadas)): ?>
                            <tr>
                                <td colspan="12" class="text-center py-4">Nenhuma empreitada cadastrada</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($empreitadas as $empreitada): ?>
                                <?php
                                $saldo_restante = $empreitada['valor_total'] - $empreitada['total_gastos'];
                                $status = (strtotime($empreitada['data_termino']) < time()) ? 'Concluído' : 'Em Andamento';
                                ?>
                                <tr>
                                    <td data-label="ID"><?= $empreitada['id'] ?></td>
                                    <td data-label="Título"><?= htmlspecialchars($empreitada['titulo']) ?></td>
                                    <td data-label="Ordem de Serviço"><?= htmlspecialchars($empreitada['ordem_servico'] ?: 'N/A') ?></td>
                                    <td data-label="Local"><?= htmlspecialchars($empreitada['local_servico'] ?: 'N/A') ?></td>
                                    <td data-label="Cliente"><?= htmlspecialchars($empreitada['cliente_responsavel'] ?: 'N/A') ?></td>
                                    <td data-label="Valor Total">R$ <?= number_format((float)$empreitada['valor_total'], 2, ',', '.') ?></td>
                                    <td data-label="Gastos Acumulados">R$ <?= number_format((float)$empreitada['total_gastos'], 2, ',', '.') ?></td>
                                    <td data-label="Saldo Restante">R$ <?= number_format((float)$saldo_restante, 2, ',', '.') ?></td>
                                    <td data-label="Início"><?= date('d/m/Y', strtotime($empreitada['data_inicio'])) ?></td>
                                    <td data-label="Término"><?= date('d/m/Y', strtotime($empreitada['data_termino'])) ?></td>
                                    <td data-label="Status">
                                        <span class="status-badge bg-<?= $status === 'Em Andamento' ? 'warning' : 'success' ?> bg-opacity-10 text-<?= $status === 'Em Andamento' ? 'warning' : 'success' ?>">
                                            <?= $status ?>
                                        </span>
                                    </td>
                                    <td data-label="Ações">
                                        <div class="d-flex flex-wrap gap-2">
                                            <a href="empreitada_detalhes.php?id=<?= $empreitada['id'] ?>" 
                                               class="btn btn-sm btn-primary px-3 py-2 rounded-pill">
                                                <i class="fas fa-eye me-1"></i><span class="d-none d-md-inline">Detalhes</span>
                                            </a>
                                            <a href="empreitada_form.php?id=<?= $empreitada['id'] ?>" 
                                               class="btn btn-sm btn-primary px-3 py-2 rounded-pill">
                                                <i class="fas fa-edit me-1"></i><span class="d-none d-md-inline">Editar</span>
                                            </a>
                                            <a href="?delete_id=<?= $empreitada['id'] ?>" 
                                               class="btn btn-sm btn-danger px-3 py-2 rounded-pill"
                                               onclick="return confirm('Tem certeza que deseja excluir esta empreitada?')">
                                                <i class="fas fa-trash me-1"></i><span class="d-none d-md-inline">Excluir</span>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

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
    }

    .dashboard-card {
        background: white;
        border-radius: 12px;
        box-shadow: var(--shadow);
        padding: 1.5rem;
        transition: transform 0.3s ease;
        border: 1px solid rgba(203, 213, 225, 0.3);
        backdrop-filter: blur(4px);
        background: rgba(255, 255, 255, 0.98);
    }

    .dashboard-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 30px rgba(37, 99, 235, 0.15);
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

    /* Responsividade */
    @media (max-width: 992px) {
        .table-modern thead th {
            padding: 1rem;
            font-size: 0.95rem;
        }
        
        .table-modern tbody td {
            padding: 1rem;
        }
        
        .status-badge {
            font-size: 0.8rem;
            padding: 0.25rem 0.8rem;
        }
    }

    @media (max-width: 768px) {
        .dashboard-card {
            padding: 1rem;
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
        
        .btn {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
        }
    }

    @media (max-width: 576px) {
        .header-section h2 {
            font-size: 1.5rem;
        }
        
        .table-modern thead th {
            font-size: 0.85rem;
            padding: 0.6rem;
        }
        
        .table-modern tbody td {
            padding: 0.6rem;
            font-size: 0.85rem;
        }
        
        .status-badge {
            font-size: 0.75rem;
        }
        
        .action-buttons .btn {
            width: 100%;
            justify-content: center;
        }
    }

    @media (max-width: 480px) {
        .table-modern tbody tr:hover td {
            transform: none;
        }
        
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
</body>
</html>