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

// Buscar itens do banco de dados com contagem de serviços concluídos
$itens = [];
$stmt = $pdo->query("
    SELECT i.id, i.nome, i.valor_unitario_padrao,
           COUNT(CASE WHEN o.status = 'Concluído' THEN JSON_UNQUOTE(JSON_EXTRACT(o.descricao_servico, '$[*].id_item')) END) as serviços_concluídos
    FROM itens i
    LEFT JOIN orcamentos o ON JSON_CONTAINS(JSON_EXTRACT(o.descricao_servico, '$[*].id_item'), JSON_QUOTE(CAST(i.id AS CHAR)))
    GROUP BY i.id, i.nome, i.valor_unitario_padrao
    ORDER BY i.nome
");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $itens[] = $row;
}

// Processar cadastro de novo item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'cadastrar') {
    try {
        $nome = $_POST['nome'];
        $valor_unitario = floatval($_POST['valor_unitario']);
        
        if (empty($nome) || $valor_unitario < 0) {
            throw new Exception("Nome e valor unitário são obrigatórios e o valor deve ser positivo.");
        }

        $stmt = $pdo->prepare("INSERT INTO itens (nome, valor_unitario_padrao) VALUES (?, ?)");
        $stmt->execute([$nome, $valor_unitario]);
        
        header("Location: segmentos.php?success=cadastrado");
        exit();
    } catch (Exception $e) {
        $erro = $e->getMessage();
    }
}

// Processar edição de item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'editar') {
    try {
        $id = $_POST['id'];
        $nome = $_POST['nome'];
        $valor_unitario = floatval($_POST['valor_unitario']);
        
        if (empty($nome) || $valor_unitario < 0) {
            throw new Exception("Nome e valor unitário são obrigatórios e o valor deve ser positivo.");
        }

        $stmt = $pdo->prepare("UPDATE itens SET nome = ?, valor_unitario_padrao = ? WHERE id = ?");
        $stmt->execute([$nome, $valor_unitario, $id]);
        
        header("Location: segmentos.php?success=editado");
        exit();
    } catch (Exception $e) {
        $erro = $e->getMessage();
    }
}

// Processar exclusão de item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'excluir') {
    try {
        $id = $_POST['id'];
        
        // Verificar se o item está vinculado a algum orçamento
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM orcamentos 
            WHERE JSON_CONTAINS(JSON_EXTRACT(descricao_servico, '$[*].id_item'), JSON_QUOTE(CAST(? AS CHAR)))
        ");
        $stmt->execute([$id]);
        $uso = $stmt->fetchColumn();

        if ($uso > 0) {
            throw new Exception("Este item está vinculado a orçamentos e não pode ser excluído.");
        }

        $stmt = $pdo->prepare("DELETE FROM itens WHERE id = ?");
        $stmt->execute([$id]);
        
        header("Location: segmentos.php?success=excluido");
        exit();
    } catch (Exception $e) {
        $erro = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Itens - UltraLimp</title>
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
        margin-bottom: 1.5rem;
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

    .btn-warning {
        background: var(--accent);
        border: none;
        padding: 0.5rem 1rem;
        border-radius: 8px;
    }

    .btn-warning:hover {
        background: #D97706;
    }

    .btn-danger {
        background: #DC3545;
        border: none;
        padding: 0.5rem 1rem;
        border-radius: 8px;
    }

    .btn-danger:hover {
        background: #C82333;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    th, td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #ddd;
    }

    th {
        background: var(--primary);
        color: white;
    }

    tr:nth-child(even) {
        background: #f8fafc;
    }

    .form-label {
        font-weight: 500;
        color: var(--dark);
    }
    </style>
</head>
<body>
    <?php require __DIR__ . '/navbar.php'; ?>

    <div class="container-fluid">
        <!-- Cabeçalho -->
        <div class="header-section">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="mb-0"><i class="fas fa-list me-2"></i>Gerenciar Itens</h2>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#novoItemModal">
                    <i class="fas fa-plus me-2"></i>Novo Item
                </button>
            </div>
        </div>

        <!-- Mensagem de sucesso ou erro -->
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                Item <?= $_GET['success'] === 'cadastrado' ? 'cadastrado' : ($_GET['success'] === 'editado' ? 'editado' : 'excluído') ?> com sucesso!
            </div>
        <?php endif; ?>
        <?php if (isset($erro)): ?>
            <div class="alert alert-danger"><?= $erro ?></div>
        <?php endif; ?>

        <!-- Lista de Itens -->
        <div class="dashboard-card">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Valor Unitário</th>
                        <th>Serviços Concluídos</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($itens)): ?>
                        <tr>
                            <td colspan="5" class="text-center">Nenhum item cadastrado.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($itens as $item): ?>
                            <tr>
                                <td><?= $item['id'] ?></td>
                                <td><?= htmlspecialchars($item['nome']) ?></td>
                                <td>R$ <?= number_format($item['valor_unitario_padrao'], 2, ',', '.') ?></td>
                                <td><?= $item['serviços_concluídos'] ?: 0 ?></td>
                                <td>
                                    <button class="btn btn-warning btn-sm me-2" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editarItemModal"
                                            data-id="<?= $item['id'] ?>"
                                            data-nome="<?= htmlspecialchars($item['nome']) ?>"
                                            data-valor="<?= $item['valor_unitario_padrao'] ?>">
                                        <i class="fas fa-edit"></i> Editar
                                    </button>
                                    <button class="btn btn-danger btn-sm" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#excluirItemModal"
                                            data-id="<?= $item['id'] ?>"
                                            data-nome="<?= htmlspecialchars($item['nome']) ?>">
                                        <i class="fas fa-trash"></i> Excluir
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal para cadastrar novo item -->
    <div class="modal fade" id="novoItemModal" tabindex="-1" aria-labelledby="novoItemModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="novoItemModalLabel">Cadastrar Novo Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="acao" value="cadastrar">
                        <div class="mb-3">
                            <label for="nomeNovo" class="form-label">Nome do Item</label>
                            <input type="text" class="form-control" id="nomeNovo" name="nome" required>
                        </div>
                        <div class="mb-3">
                            <label for="valorNovo" class="form-label">Valor Unitário Padrão</label>
                            <input type="number" class="form-control" id="valorNovo" name="valor_unitario" step="0.01" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para editar item -->
    <div class="modal fade" id="editarItemModal" tabindex="-1" aria-labelledby="editarItemModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editarItemModalLabel">Editar Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="acao" value="editar">
                        <input type="hidden" name="id" id="editarId">
                        <div class="mb-3">
                            <label for="nomeEditar" class="form-label">Nome do Item</label>
                            <input type="text" class="form-control" id="nomeEditar" name="nome" required>
                        </div>
                        <div class="mb-3">
                            <label for="valorEditar" class="form-label">Valor Unitário Padrão</label>
                            <input type="number" class="form-control" id="valorEditar" name="valor_unitario" step="0.01" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para excluir item -->
    <div class="modal fade" id="excluirItemModal" tabindex="-1" aria-labelledby="excluirItemModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="excluirItemModalLabel">Excluir Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="acao" value="excluir">
                        <input type="hidden" name="id" id="excluirId">
                        <p>Tem certeza que deseja excluir o item "<span id="excluirNome"></span>"?</p>
                        <p class="text-danger">Esta ação não pode ser desfeita.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">Excluir</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Preencher o modal de edição com os dados do item
    const editarModal = document.getElementById('editarItemModal');
    editarModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const id = button.getAttribute('data-id');
        const nome = button.getAttribute('data-nome');
        const valor = button.getAttribute('data-valor');

        const modalId = editarModal.querySelector('#editarId');
        const modalNome = editarModal.querySelector('#nomeEditar');
        const modalValor = editarModal.querySelector('#valorEditar');

        modalId.value = id;
        modalNome.value = nome;
        modalValor.value = valor;
    });

    // Preencher o modal de exclusão com os dados do item
    const excluirModal = document.getElementById('excluirItemModal');
    excluirModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const id = button.getAttribute('data-id');
        const nome = button.getAttribute('data-nome');

        const modalId = excluirModal.querySelector('#excluirId');
        const modalNome = excluirModal.querySelector('#excluirNome');

        modalId.value = id;
        modalNome.textContent = nome;
    });
    </script>
</body>
</html>