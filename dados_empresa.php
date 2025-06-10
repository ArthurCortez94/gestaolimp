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

// Processar o formulário de criação/edição
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    try {
        $nome_empresa = $_POST['nome_empresa'];
        $cnpj = $_POST['cnpj'];
        $endereco = $_POST['endereco'];
        $cidade = $_POST['cidade'];
        $cep = $_POST['cep'];
        $email = $_POST['email'];

        // Validação básica
        if (empty($nome_empresa) || empty($cnpj) || empty($endereco) || empty($cidade) || empty($cep) || empty($email)) {
            throw new Exception("Todos os campos obrigatórios devem ser preenchidos.");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("E-mail inválido.");
        }

        // Inserir ou atualizar os dados da empresa
        if (isset($_POST['id']) && !empty($_POST['id'])) {
            // Atualizar
            $id = (int)$_POST['id'];
            $stmt = $pdo->prepare("UPDATE dados_empresa SET nome_empresa = ?, cnpj = ?, endereco = ?, cidade = ?, cep = ?, email = ? WHERE id = ?");
            $stmt->execute([$nome_empresa, $cnpj, $endereco, $cidade, $cep, $email, $id]);
        } else {
            // Inserir
            $stmt = $pdo->prepare("INSERT INTO dados_empresa (nome_empresa, cnpj, endereco, cidade, cep, email) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nome_empresa, $cnpj, $endereco, $cidade, $cep, $email]);
        }

        header("Location: dados_empresa.php?saved=1");
        exit();
    } catch (Exception $e) {
        $error = "Erro: " . $e->getMessage();
    }
}

// Processar a seleção de empresa para uso
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'select_empresa') {
    $_SESSION['selected_empresa_id'] = (int)$_POST['empresa_id'];
    header("Location: dados_empresa.php?selected=1");
    exit();
}

// Buscar todos os registros de dados da empresa
$dados_empresa = [];
$stmt = $pdo->query("SELECT * FROM dados_empresa ORDER BY id DESC");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $dados_empresa[] = $row;
}

// Buscar dados para edição, se aplicável
$edit_dados = null;
if (isset($_GET['edit_id'])) {
    $edit_id = (int)$_GET['edit_id'];
    $stmt = $pdo->prepare("SELECT * FROM dados_empresa WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_dados = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Empresa selecionada (se houver)
$selected_empresa_id = $_SESSION['selected_empresa_id'] ?? null;
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dados da Empresa - UltraLimp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #1E3A8A;
            --secondary: #10B981;
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

        .form-section h4 {
            color: var(--primary);
            font-weight: 600;
            border-bottom: 2px solid var(--primary);
            padding-bottom: 0.5rem;
            margin-bottom: 1.5rem;
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
        }

        .form-label {
            font-weight: 500;
            color: var(--dark);
        }

        .selected-row {
            background-color: #e0f7fa;
        }
    </style>
</head>
<body>
    <?php require __DIR__ . '/navbar.php'; ?>

    <div class="container-fluid">
        <!-- Cabeçalho -->
        <div class="header-section">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="mb-0"><i class="fas fa-building me-2"></i>Dados da Empresa</h2>
                <div class="d-flex gap-2">
                    <a href="index.php" class="btn btn-outline-light">
                        <i class="fas fa-arrow-left me-1"></i>Voltar
                    </a>
                </div>
            </div>
        </div>

        <!-- Mensagem de erro ou sucesso -->
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php elseif (isset($_GET['saved'])): ?>
            <div class="alert alert-success">Dados salvos com sucesso!</div>
        <?php elseif (isset($_GET['selected'])): ?>
            <div class="alert alert-success">Empresa selecionada com sucesso!</div>
        <?php endif; ?>

        <!-- Formulário para adicionar/editar dados da empresa -->
        <div class="dashboard-card">
            <h4><i class="fas fa-building me-2"></i><?php echo $edit_dados ? 'Editar Dados da Empresa' : 'Adicionar Dados da Empresa'; ?></h4>
            <form method="POST">
                <input type="hidden" name="action" value="save">
                <?php if ($edit_dados): ?>
                    <input type="hidden" name="id" value="<?php echo $edit_dados['id']; ?>">
                <?php endif; ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Nome da Empresa</label>
                        <input type="text" name="nome_empresa" class="form-control" value="<?php echo $edit_dados['nome_empresa'] ?? ''; ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">CNPJ</label>
                        <input type="text" name="cnpj" class="form-control" value="<?php echo $edit_dados['cnpj'] ?? ''; ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Endereço</label>
                        <input type="text" name="endereco" class="form-control" value="<?php echo $edit_dados['endereco'] ?? ''; ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Cidade</label>
                        <input type="text" name="cidade" class="form-control" value="<?php echo $edit_dados['cidade'] ?? ''; ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">CEP</label>
                        <input type="text" name="cep" class="form-control" value="<?php echo $edit_dados['cep'] ?? ''; ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">E-mail</label>
                        <input type="email" name="email" class="form-control" value="<?php echo $edit_dados['email'] ?? ''; ?>" required>
                    </div>
                </div>
                <div class="text-end mt-3">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save me-2"></i>Salvar
                    </button>
                </div>
            </form>
        </div>

        <!-- Lista de dados da empresa -->
        <div class="dashboard-card">
            <h4><i class="fas fa-list me-2"></i>Empresas Cadastradas</h4>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Nome da Empresa</th>
                        <th>CNPJ</th>
                        <th>Endereço</th>
                        <th>Cidade</th>
                        <th>CEP</th>
                        <th>E-mail</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dados_empresa)): ?>
                        <tr>
                            <td colspan="7" class="text-center">Nenhum dado de empresa cadastrado.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($dados_empresa as $dado): ?>
                            <tr <?php echo $dado['id'] == $selected_empresa_id ? 'class="selected-row"' : ''; ?>>
                                <td><?php echo htmlspecialchars($dado['nome_empresa']); ?></td>
                                <td><?php echo htmlspecialchars($dado['cnpj']); ?></td>
                                <td><?php echo htmlspecialchars($dado['endereco']); ?></td>
                                <td><?php echo htmlspecialchars($dado['cidade']); ?></td>
                                <td><?php echo htmlspecialchars($dado['cep']); ?></td>
                                <td><?php echo htmlspecialchars($dado['email']); ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="select_empresa">
                                        <input type="hidden" name="empresa_id" value="<?php echo $dado['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-success">
                                            <i class="fas fa-check"></i> Selecionar
                                        </button>
                                    </form>
                                    <a href="dados_empresa.php?edit_id=<?php echo $dado['id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i> Editar
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>