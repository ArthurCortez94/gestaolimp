<?php
declare(strict_types=1);
session_start();

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

require_once 'config.php';

// Processar ações
$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        try {
            $id = (int)$_POST['delete_id'];
            $stmt = $pdo->prepare("DELETE FROM clientes WHERE id = ?");
            $stmt->execute([$id]);
            $success = "Cliente excluído com sucesso!";
        } catch (PDOException $e) {
            $error = "Erro ao excluir cliente: " . $e->getMessage();
        }
    }
}

// Configuração de paginação
$registros_por_pagina = 15;
$pagina_atual = max(1, (int)($_GET['pagina'] ?? 1));
$offset = ($pagina_atual - 1) * $registros_por_pagina;

// Filtros
$filter_type = $_GET['type'] ?? 'all';
$search_term = $_GET['search'] ?? '';

// Construção da query
$query = "SELECT SQL_CALC_FOUND_ROWS * FROM clientes WHERE 1=1";
$params = [];
$types = [];

if ($filter_type !== 'all') {
    $query .= " AND tipo_cliente = ?";
    $params[] = $filter_type;
    $types[] = PDO::PARAM_STR;
}

if (!empty($search_term)) {
    $query .= " AND (nome LIKE ? OR cnpj_cpf LIKE ? OR email LIKE ?)";
    $search_param = "%$search_term%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    array_push($types, PDO::PARAM_STR, PDO::PARAM_STR, PDO::PARAM_STR);
}

$query .= " ORDER BY data_cadastro DESC LIMIT ? OFFSET ?";

try {
    $stmt = $pdo->prepare($query);
    
    // Vincular parâmetros
    $param_index = 1;
    foreach($params as $key => $value) {
        $stmt->bindValue($param_index, $value, $types[$key] ?? PDO::PARAM_STR);
        $param_index++;
    }
    
    // Vincular limit e offset
    $stmt->bindValue($param_index++, $registros_por_pagina, PDO::PARAM_INT);
    $stmt->bindValue($param_index, $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_registros = $pdo->query("SELECT FOUND_ROWS()")->fetchColumn();
    $total_paginas = ceil($total_registros / $registros_por_pagina);

} catch (PDOException $e) {
    die("Erro ao carregar clientes: " . $e->getMessage());
}

function formatarDocumento($doc, $tipo) {
    if (empty($doc)) return '';
    
    $doc = preg_replace('/[^0-9]/', '', $doc);
    
    if ($tipo === 'Pessoa Jurídica') {
        return substr($doc, 0, 2) . '.' . 
               substr($doc, 2, 3) . '.' . 
               substr($doc, 5, 3) . '/' . 
               substr($doc, 8, 4) . '-' . 
               substr($doc, 12, 2);
    }
    
    return substr($doc, 0, 3) . '.' . 
           substr($doc, 3, 3) . '.' . 
           substr($doc, 6, 3) . '-' . 
           substr($doc, 9, 2);
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes - UltraLimp</title>
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

    .badge-pf {
        background: #DBEAFE;
        color: #1E40AF;
    }

    .badge-pj {
        background: #FCE7F3;
        color: #BE185D;
    }

    .filter-container {
        background: rgba(255,255,255,0.95);
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: var(--shadow);
        backdrop-filter: blur(6px);
    }

    .column-icon {
        margin-right: 10px;
        font-size: 0.95em;
        opacity: 0.9;
    }

    .pagination .page-link {
        border-radius: 8px;
        margin: 0 4px;
        border: none;
        color: var(--primary);
    }

    .pagination .page-item.active .page-link {
        background: var(--primary);
        color: white;
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
        
        .filter-container {
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
        
        .pagination .page-link {
            padding: 0.5rem 0.8rem;
        }
    }

    @media (max-width: 576px) {
        .header-section h2 {
            font-size: 1.5rem;
        }
        
        .filter-container .row {
            flex-direction: column;
            gap: 1rem;
        }
        
        .filter-container .col-md-3,
        .filter-container .col-md-9 {
            width: 100%;
            max-width: 100%;
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
        
        .pagination {
            flex-wrap: wrap;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .pagination .page-item {
            margin: 2px;
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
        <div class="header-section mb-4">
            <h2 class="text-dark fw-bold"><i class="fas fa-users me-2"></i>Gestão de Clientes</h2>
        </div>

        <!-- Alertas -->
        <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show dashboard-card" role="alert">
            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show dashboard-card" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <!-- Seção de Ações -->
        <div class="dashboard-card mb-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                <a href="relatorio_clientes.php" class="btn btn-outline-primary px-4 py-2 w-100 w-md-auto">
                    <i class="fas fa-file-pdf me-2"></i>Exportar Relatório
                </a>
                <a href="cadastro.php" class="btn btn-primary px-4 py-2 w-100 w-md-auto">
                    <i class="fas fa-plus me-2"></i>Novo Cliente
                </a>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filter-container mb-4">
            <form method="GET" class="row g-3 align-items-center">
                <div class="col-12 col-md-3">
                    <select class="form-select" name="type" onchange="this.form.submit()">
                        <option value="all" <?= $filter_type === 'all' ? 'selected' : '' ?>>Todos os Tipos</option>
                        <option value="Pessoa Física" <?= $filter_type === 'Pessoa Física' ? 'selected' : '' ?>>Pessoa Física</option>
                        <option value="Pessoa Jurídica" <?= $filter_type === 'Pessoa Jurídica' ? 'selected' : '' ?>>Pessoa Jurídica</option>
                    </select>
                </div>
                <div class="col-12 col-md-9">
                    <div class="input-group">
                        <input type="text" class="form-control" name="search" 
                               placeholder="Pesquisar clientes..." 
                               value="<?= htmlspecialchars($search_term) ?>">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="fas fa-search me-2"></i>Pesquisar
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Tabela -->
        <div class="dashboard-card">
            <div class="table-responsive">
                <table class="table table-modern table-hover">
                    <thead>
                        <tr>
                            <th><i class="fas fa-tag column-icon"></i>Tipo</th>
                            <th><i class="fas fa-building column-icon"></i>Nome/Razão</th>
                            <th><i class="fas fa-id-card column-icon"></i>Documento</th>
                            <th><i class="fas fa-phone column-icon"></i>Contato</th>
                            <th><i class="fas fa-map-marker-alt column-icon"></i>Localização</th>
                            <th><i class="fas fa-calendar-alt column-icon"></i>Cadastro</th>
                            <th><i class="fas fa-cog column-icon"></i>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clientes as $cliente): ?>
                        <tr>
                            <td data-label="Tipo">
                                <span class="status-badge <?= $cliente['tipo_cliente'] === 'Pessoa Física' ? 'badge-pf' : 'badge-pj' ?>">
                                    <?= htmlspecialchars($cliente['tipo_cliente'] ?? '') ?>
                                </span>
                            </td>
                            <td data-label="Nome/Razão">
                                <div class="fw-semibold"><?= htmlspecialchars($cliente['nome'] ?? '') ?></div>
                                <small class="text-muted"><?= htmlspecialchars($cliente['email'] ?? '') ?></small>
                            </td>
                            <td data-label="Documento">
                                <?= formatarDocumento($cliente['cnpj_cpf'] ?? '', $cliente['tipo_cliente'] ?? '') ?>
                            </td>
                            <td data-label="Contato">
                                <i class="fas fa-phone me-2 d-none d-md-inline"></i>
                                <?= htmlspecialchars($cliente['telefone'] ?? '') ?>
                            </td>
                            <td data-label="Localização">
                                <i class="fas fa-map-pin me-2 d-none d-md-inline"></i>
                                <?= htmlspecialchars($cliente['cidade'] ?? '') ?>/<?= htmlspecialchars($cliente['uf'] ?? '') ?>
                            </td>
                            <td data-label="Cadastro">
                                <?= $cliente['data_cadastro'] ? date('d/m/Y', strtotime($cliente['data_cadastro'])) : '' ?>
                            </td>
                            <td data-label="Ações">
                                <div class="d-flex flex-wrap gap-2">
                                    <a href="editar_cliente.php?id=<?= $cliente['id'] ?>" 
                                       class="btn btn-sm btn-primary px-3 py-2 rounded-pill">
                                        <i class="fas fa-edit me-1"></i><span class="d-none d-md-inline">Editar</span>
                                    </a>
                                    <form method="POST">
                                        <input type="hidden" name="delete_id" value="<?= $cliente['id'] ?>">
                                        <button type="submit" 
                                                class="btn btn-sm btn-danger px-3 py-2 rounded-pill"
                                                onclick="return confirm('Tem certeza que deseja excluir este cliente permanentemente?');">
                                            <i class="fas fa-trash me-1"></i><span class="d-none d-md-inline">Excluir</span>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Paginação -->
            <?php if($total_paginas > 1): ?>
            <div class="d-flex justify-content-center mt-4">
                <nav>
                    <ul class="pagination">
                        <?php if($pagina_atual > 1): ?>
                            <li class="page-item">
                                <a class="page-link" 
                                   href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina_atual - 1])) ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php for($i = 1; $i <= $total_paginas; $i++): ?>
                            <li class="page-item <?= $i === $pagina_atual ? 'active' : '' ?>">
                                <a class="page-link" 
                                   href="?<?= http_build_query(array_merge($_GET, ['pagina' => $i])) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <?php if($pagina_atual < $total_paginas): ?>
                            <li class="page-item">
                                <a class="page-link" 
                                   href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina_atual + 1])) ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>