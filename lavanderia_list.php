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

// Processar exclusão de item
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM lavanderia WHERE id = ?");
        $stmt->execute([$delete_id]);
        header("Location: lavanderia_list.php?success=Item+excluído+com+sucesso");
        exit;
    } catch (PDOException $e) {
        die("Erro ao excluir item: " . $e->getMessage());
    }
}

// Carregar estatísticas de status
try {
    $status_lavanderia = $pdo->query("
        SELECT 
            COUNT(CASE WHEN status = 'Em Processamento' THEN 1 END) AS em_processamento,
            COUNT(CASE WHEN status = 'Lavado' THEN 1 END) AS lavado,
            COUNT(CASE WHEN status = 'Pronto para Retirada' THEN 1 END) AS pronto
        FROM lavanderia
    ")->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao carregar estatísticas: " . $e->getMessage());
}

// Carregar itens da lavanderia com LEFT JOIN
try {
    $query = "
        SELECT l.*, COALESCE(c.nome, 'Cliente não encontrado') AS cliente 
        FROM lavanderia l 
        LEFT JOIN clientes c ON l.cliente_id = c.id 
        ORDER BY l.created_at DESC
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $itens_lavanderia = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Processar o campo 'itens' (JSON) para cada registro
    foreach ($itens_lavanderia as &$item) {
        $item['itens'] = json_decode($item['itens'], true) ?? [];
    }
    unset($item); // Limpar a referência

} catch (PDOException $e) {
    die("Erro ao carregar itens: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Lavanderia - UltraLimp</title>
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
        --status-em-processamento: #FFC107;
        --status-lavado: #17A2B8;
        --status-pronto: #28A745;
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

    .status-em-processamento { background-color: var(--status-em-processamento); color: black; }
    .status-lavado { background-color: var(--status-lavado); color: white; }
    .status-pronto { background-color: var(--status-pronto); color: white; }

    .column-icon {
        margin-right: 10px;
        font-size: 0.95em;
        opacity: 0.9;
    }

    .items-list {
        margin-top: 0.5rem;
        padding-left: 1.5rem;
    }

    .items-list li {
        padding: 0.3rem 0;
        font-size: 0.9rem;
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
</head>
<body>
    <?php require __DIR__ . '/navbar.php'; ?>

    <div class="container-fluid">
        <!-- Cabeçalho -->
        <div class="header-section mb-4 d-flex justify-content-between align-items-center">
            <h2 class="text-dark fw-bold"><i class="fas fa-tshirt me-2"></i>Gestão de Lavanderia</h2>
            <a href="lavanderia_form.php" class="btn btn-primary px-4 py-2">
                <i class="fas fa-plus me-2"></i>Novo Item
            </a>
        </div>

        <!-- Alertas -->
        <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show dashboard-card" role="alert">
            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars(urldecode($_GET['success'])) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <!-- Cards de Status -->
        <div class="row g-4 mb-4">
            <?php 
            $statusConfig = [
                'em_processamento' => ['label' => 'Em Processamento', 'color' => 'warning', 'icon' => 'cogs'],
                'lavado' => ['label' => 'Lavado', 'color' => 'info', 'icon' => 'tint'],
                'pronto' => ['label' => 'Pronto para Retirada', 'color' => 'success', 'icon' => 'check-circle']
            ];
            
            foreach ($statusConfig as $key => $config): ?>
                <div class="col-12 col-sm-6 col-xl-4">
                    <div class="dashboard-card p-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-<?= $config['color'] ?> bg-opacity-10 p-3 rounded-circle">
                                <i class="fas fa-<?= $config['icon'] ?> text-<?= $config['color'] ?> fa-lg"></i>
                            </div>
                            <div>
                                <div class="h2 mb-0"><?= $status_lavanderia[$key] ?? 0 ?></div>
                                <small class="text-muted"><?= $config['label'] ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Tabela -->
        <div class="dashboard-card">
            <div class="table-responsive">
                <table class="table table-modern table-hover">
                    <thead>
                        <tr>
                            <th><i class="fas fa-hashtag column-icon"></i>ID</th>
                            <th><i class="fas fa-user column-icon"></i>Cliente</th>
                            <th><i class="fas fa-tshirt column-icon"></i>Itens</th>
                            <th><i class="fas fa-dollar-sign column-icon"></i>Val. Total Geral</th>
                            <th><i class="fas fa-tasks column-icon"></i>Status</th>
                            <th><i class="fas fa-calendar-day column-icon"></i>Coleta</th>
                            <th><i class="fas fa-calendar-check column-icon"></i>Prevista</th>
                            <th><i class="fas fa-calendar-alt column-icon"></i>Entrega</th>
                            <th><i class="fas fa-clock column-icon"></i>Criado</th>
                            <th><i class="fas fa-sticky-note column-icon"></i>Obs.</th>
                            <th><i class="fas fa-cog column-icon"></i>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($itens_lavanderia)): ?>
                            <tr>
                                <td colspan="11" class="text-center py-4">Nenhum item cadastrado</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($itens_lavanderia as $item): ?>
                                <tr>
                                    <td data-label="ID"><?= $item['id'] ?></td>
                                    <td data-label="Cliente"><?= htmlspecialchars($item['cliente']) ?></td>
                                    <td data-label="Itens">
                                        <?php if (!empty($item['itens'])): ?>
                                            <ul class="items-list">
                                                <?php foreach ($item['itens'] as $subitem): ?>
                                                    <li>
                                                        <strong><?= htmlspecialchars($subitem['nome_item']) ?></strong> 
                                                        (Qtd: <?= $subitem['quantidade'] ?>, 
                                                        Val. Unit.: R$ <?= number_format((float)$subitem['valor_unitario'], 2, ',', '.') ?>, 
                                                        Val. Total: R$ <?= number_format((float)$subitem['valor_total'], 2, ',', '.') ?>)
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php else: ?>
                                            <span class="text-muted">Nenhum item</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Val. Total Geral">
                                        R$ <?= number_format((float)$item['valor_total_geral'], 2, ',', '.') ?>
                                    </td>
                                    <td data-label="Status">
                                        <?php
                                        $statusInfo = match($item['status']) {
                                            'Em Processamento' => ['color' => 'warning', 'label' => 'Em Processamento'],
                                            'Lavado' => ['color' => 'info', 'label' => 'Lavado'],
                                            'Pronto para Retirada' => ['color' => 'success', 'label' => 'Pronto'],
                                            default => ['color' => 'secondary', 'label' => 'Desconhecido']
                                        };
                                        ?>
                                        <span class="status-badge bg-<?= $statusInfo['color'] ?> bg-opacity-10 text-<?= $statusInfo['color'] ?>">
                                            <?= $statusInfo['label'] ?>
                                        </span>
                                    </td>
                                    <td data-label="Coleta">
                                        <?= $item['data_coleta'] 
                                            ? date('d/m/Y', strtotime($item['data_coleta'])) 
                                            : '<span class="text-muted">N/A</span>' ?>
                                    </td>
                                    <td data-label="Prevista">
                                        <?= $item['data_prevista_entrega'] 
                                            ? date('d/m/Y', strtotime($item['data_prevista_entrega'])) 
                                            : '<span class="text-muted">N/A</span>' ?>
                                    </td>
                                    <td data-label="Entrega">
                                        <?= $item['data_entrega'] 
                                            ? date('d/m/Y', strtotime($item['data_entrega'])) 
                                            : '<span class="text-muted">N/A</span>' ?>
                                    </td>
                                    <td data-label="Criado"><?= date('d/m/Y H:i', strtotime($item['created_at'])) ?></td>
                                    <td data-label="Obs.">
                                        <?= $item['observacao'] 
                                            ? htmlspecialchars(substr($item['observacao'], 0, 20) . (strlen($item['observacao']) > 20 ? '...' : '')) 
                                            : '<span class="text-muted">N/A</span>' ?>
                                    </td>
                                    <td data-label="Ações">
                                        <div class="d-flex flex-wrap gap-2">
                                            <a href="lavanderia_edit.php?id=<?= $item['id'] ?>" 
                                               class="btn btn-sm btn-primary px-3 py-2 rounded-pill">
                                                <i class="fas fa-edit me-1"></i><span class="d-none d-md-inline">Editar</span>
                                            </a>
                                            <a href="lavanderia_pdf.php?id=<?= $item['id'] ?>" 
                                               class="btn btn-sm btn-success px-3 py-2 rounded-pill">
                                                <i class="fas fa-file-pdf me-1"></i><span class="d-none d-md-inline">PDF</span>
                                            </a>
                                            <a href="?delete_id=<?= $item['id'] ?>" 
                                               class="btn btn-sm btn-danger px-3 py-2 rounded-pill"
                                               onclick="return confirm('Tem certeza que deseja excluir este item?')">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>