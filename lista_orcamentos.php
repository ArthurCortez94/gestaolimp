<?php
session_start();
require_once 'config.php';

// Verificação de segurança
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Processar atualização de status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'], $_POST['status'])) {
    try {
        $id = (int)$_POST['id'];
        $status = $_POST['status'];
        
        $valid_statuses = ['Aberto', 'Agendado', 'Em Andamento', 'Concluído', 'Atrasado', 'Cancelado'];
        if (!in_array($status, $valid_statuses)) {
            throw new Exception("Status inválido.");
        }
        
        $stmt = $pdo->prepare("UPDATE orcamentos SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        
        $_SESSION['success'] = "Status atualizado com sucesso!";
        header("Location: lista_orcamentos.php");
        exit();
    } catch (PDOException $e) {
        die("Erro ao atualizar status: " . $e->getMessage());
    }
}

// Processar exclusão
if (isset($_GET['delete_id'])) {
    try {
        $id = (int)$_GET['delete_id'];
        
        // Verificar se existe uma ordem de serviço associada
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM ordens_servico WHERE orcamento_id = ?");
        $stmt_check->execute([$id]);
        $ordem_count = $stmt_check->fetchColumn();

        if ($ordem_count > 0) {
            $_SESSION['error'] = "Não é possível excluir este orçamento, pois existe uma Ordem de Serviço aberta associada a ele.";
            header("Location: lista_orcamentos.php");
            exit();
        }

        // Se não houver ordens de serviço, prosseguir com a exclusão
        $stmt = $pdo->prepare("DELETE FROM orcamentos WHERE id = ?");
        $stmt->execute([$id]);
        
        $_SESSION['success'] = "Orçamento excluído com sucesso!";
        header("Location: lista_orcamentos.php");
        exit();
    } catch (PDOException $e) {
        // Capturar erro de violação de chave estrangeira (opcional, como fallback)
        if ($e->getCode() === '23000') {
            $_SESSION['error'] = "Não é possível excluir este orçamento, pois existe uma Ordem de Serviço aberta associada a ele.";
        } else {
            $_SESSION['error'] = "Erro ao excluir orçamento: " . $e->getMessage();
        }
        header("Location: lista_orcamentos.php");
        exit();
    }
}

// Configuração de Paginação
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10; // Itens por página
$offset = ($page - 1) * $limit;

// Buscar dados
try {
    // Status consolidado
    $status_counts = $pdo->query("
        SELECT 
            SUM(status = 'Aberto') AS abertos,
            SUM(status = 'Agendado') AS agendados,
            SUM(status = 'Em Andamento') AS em_andamento,
            SUM(status = 'Concluído') AS concluidos,
            SUM(status = 'Atrasado') AS atrasados,
            SUM(status = 'Cancelado') AS cancelados
        FROM orcamentos
    ")->fetch(PDO::FETCH_ASSOC);

    // Total para paginação
    $totalStmt = $pdo->query("SELECT COUNT(*) FROM orcamentos");
    $total = $totalStmt->fetchColumn();
    $totalPages = ceil($total / $limit);

    // Lista completa com paginação
    $stmt = $pdo->query("
        SELECT o.id, o.cliente_id, o.numero_orcamento, o.validade, o.total, o.status, COALESCE(c.nome, 'Cliente não encontrado') AS cliente_nome 
        FROM orcamentos o
        LEFT JOIN clientes c ON o.cliente_id = c.id
        ORDER BY o.created_at DESC
        LIMIT $limit OFFSET $offset
    ");
    $orcamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro ao buscar dados: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Orçamentos - UltraLimp</title>
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
        --status-aberto: #17A2B8;
        --status-agendado: #007BFF;
        --status-andamento: #FFC107;
        --status-concluido: #28A745;
        --status-atrasado: #DC3545;
        --status-cancelado: #6C757D;
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

    .status-aberto { background-color: var(--status-aberto); color: white; }
    .status-agendado { background-color: var(--status-agendado); color: white; }
    .status-andamento { background-color: var(--status-andamento); color: black; }
    .status-concluido { background-color: var(--status-concluido); color: white; }
    .status-atrasado { background-color: var(--status-atrasado); color: white; }
    .status-cancelado { background-color: var(--status-cancelado); color: white; }

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
            <h2 class="text-dark fw-bold"><i class="fas fa-file-invoice me-2"></i>Gestão de Orçamentos</h2>
            <a href="criar_orcamento.php" class="btn btn-primary px-4 py-2">
                <i class="fas fa-plus me-2"></i>Novo Orçamento
            </a>
        </div>

        <!-- Alertas -->
        <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show dashboard-card" role="alert">
            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($_SESSION['success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show dashboard-card" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($_SESSION['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Filtro -->
        <div class="filter-container mb-4">
            <div class="row align-items-center">
                <div class="col-md-3">
                    <h5 class="fw-bold mb-0"><i class="fas fa-filter me-2"></i>Filtros</h5>
                </div>
                <div class="col-md-9">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" id="searchInput" class="form-control" placeholder="Buscar por cliente ou número do orçamento">
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabela -->
        <div class="dashboard-card">
            <div class="table-responsive">
                <table class="table table-modern table-hover">
                    <thead>
                        <tr>
                            <th><i class="fas fa-hashtag column-icon"></i>Número</th>
                            <th><i class="fas fa-user column-icon"></i>Cliente</th>
                            <th><i class="fas fa-calendar-day column-icon"></i>Validade</th>
                            <th><i class="fas fa-dollar-sign column-icon"></i>Valor</th>
                            <th><i class="fas fa-tasks column-icon"></i>Status</th>
                            <th><i class="fas fa-cog column-icon"></i>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orcamentos)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4">Nenhum orçamento encontrado</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orcamentos as $orcamento): ?>
                                <tr>
                                    <td data-label="Número"><?= htmlspecialchars($orcamento['numero_orcamento']) ?></td>
                                    <td data-label="Cliente"><?= htmlspecialchars($orcamento['cliente_nome']) ?></td>
                                    <td data-label="Validade"><?= date('d/m/Y', strtotime($orcamento['validade'])) ?></td>
                                    <td data-label="Valor">R$ <?= number_format($orcamento['total'], 2, ',', '.') ?></td>
                                    <td data-label="Status">
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="id" value="<?= $orcamento['id'] ?>">
                                            <select 
                                                name="status" 
                                                class="status-badge status-<?= strtolower(str_replace(' ', '-', $orcamento['status'])) ?>"
                                                onchange="if(confirm('Deseja alterar o status para ' + this.value + '?')) this.form.submit()"
                                            >
                                                <?php foreach (['Aberto', 'Agendado', 'Em Andamento', 'Concluído', 'Atrasado', 'Cancelado'] as $option): ?>
                                                    <option 
                                                        value="<?= $option ?>" 
                                                        <?= $option === $orcamento['status'] ? 'selected' : '' ?>
                                                    >
                                                        <?= $option ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </form>
                                    </td>
                                    <td data-label="Ações">
                                        <div class="d-flex flex-wrap gap-2">
                                            <a href="editar_orcamento.php?id=<?= $orcamento['id'] ?>" 
                                               class="btn btn-sm btn-primary px-3 py-2 rounded-pill">
                                                <i class="fas fa-edit me-1"></i><span class="d-none d-md-inline">Editar</span>
                                            </a>
                                            <a href="gerar_pdf_orcamento.php?id=<?= $orcamento['id'] ?>" 
                                               class="btn btn-sm btn-success px-3 py-2 rounded-pill">
                                                <i class="fas fa-file-pdf me-1"></i><span class="d-none d-md-inline">PDF</span>
                                            </a>
                                            <a href="criar_ordem_servico.php?orcamento_id=<?= $orcamento['id'] ?>" 
                                               class="btn btn-sm btn-warning px-3 py-2 rounded-pill">
                                                <i class="fas fa-file-signature me-1"></i><span class="d-none d-md-inline">Ordem</span>
                                            </a>
                                            <a 
                                                href="lista_orcamentos.php?delete_id=<?= $orcamento['id'] ?>" 
                                                class="btn btn-sm btn-danger px-3 py-2 rounded-pill"
                                                onclick="return confirm('Tem certeza que deseja excluir este orçamento?')"
                                            >
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

            <!-- Paginação -->
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center mt-4">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="lista_orcamentos.php?page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Filtro de busca
    document.getElementById('searchInput').addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        document.querySelectorAll('.table-modern tbody tr').forEach(row => {
            const cliente = row.cells[1].textContent.toLowerCase();
            const numero = row.cells[0].textContent.toLowerCase();
            row.style.display = (cliente.includes(searchTerm) || numero.includes(searchTerm)) ? '' : 'none';
        });
    });
    </script>
</body>
</html>