<?php
declare(strict_types=1);
session_start();

// Verificação de segurança
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config.php';

// Garantir que a conexão com o banco de dados use UTF-8
$pdo->exec("SET NAMES 'utf8mb4'");

// Verifica se o ID do cliente foi passado na URL
if (!isset($_GET['id'])) {
    header("Location: lista.php");
    exit();
}

$cliente_id = (int)$_GET['id'];

// Consulta os dados do cliente
$cliente = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            id,
            tipo_cliente,
            nome,
            razao_social,
            nome_fantasia,
            cnpj_cpf,
            endereco,
            numero,
            complemento,
            bairro,
            cidade,
            uf,
            cep,
            telefone,
            email
        FROM clientes
        WHERE id = :id
    ");
    $stmt->execute(['id' => $cliente_id]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cliente) {
        header("Location: lista.php");
        exit();
    }
} catch (PDOException $e) {
    die("Erro ao carregar cliente: " . $e->getMessage());
}

// Processamento do formulário de edição
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo_cliente = $_POST['tipo_cliente'] ?? null;
    $nome = $_POST['nome'] ?? null;
    $razao_social = $tipo_cliente === 'Pessoa Jurídica' ? ($_POST['razao_social'] ?? null) : null;
    $nome_fantasia = $tipo_cliente === 'Pessoa Jurídica' ? ($_POST['nome_fantasia'] ?? null) : null;
    $cnpj_cpf = !empty($_POST['cnpj_cpf']) ? $_POST['cnpj_cpf'] : null; // CPF/CNPJ não obrigatório
    $endereco = $_POST['endereco'] ?? null;
    $numero = $_POST['numero'] ?? null;
    $complemento = $_POST['complemento'] ?? null;
    $bairro = $_POST['bairro'] ?? null;
    $cidade = $_POST['cidade'] ?? null;
    $uf = $_POST['uf'] ?? null;
    $cep = $_POST['cep'] ?? null;
    $telefone = $_POST['telefone'] ?? null;
    $email = $_POST['email'] ?? null;

    // Validações básicas
    if (empty($tipo_cliente) || empty($nome) || empty($telefone)) {
        $error = "Os campos Tipo de Cliente, Nome e Telefone são obrigatórios.";
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE clientes
                SET 
                    tipo_cliente = :tipo_cliente,
                    nome = :nome,
                    razao_social = :razao_social,
                    nome_fantasia = :nome_fantasia,
                    cnpj_cpf = :cnpj_cpf,
                    endereco = :endereco,
                    numero = :numero,
                    complemento = :complemento,
                    bairro = :bairro,
                    cidade = :cidade,
                    uf = :uf,
                    cep = :cep,
                    telefone = :telefone,
                    email = :email
                WHERE id = :id
            ");
            $stmt->execute([
                'tipo_cliente' => $tipo_cliente,
                'nome' => $nome,
                'razao_social' => $razao_social,
                'nome_fantasia' => $nome_fantasia,
                'cnpj_cpf' => $cnpj_cpf, // Pode ser null
                'endereco' => $endereco,
                'numero' => $numero,
                'complemento' => $complemento,
                'bairro' => $bairro,
                'cidade' => $cidade,
                'uf' => $uf,
                'cep' => $cep,
                'telefone' => $telefone,
                'email' => $email,
                'id' => $cliente_id
            ]);

            header("Location: lista.php?updated=1");
            exit();
        } catch (PDOException $e) {
            $error = "Erro ao atualizar cliente: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Cliente - UltraLimp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
    :root {
        --primary: #2A5C82;
        --secondary: #4CAF50;
        --accent: #FFC107;
        --light: #f8fafc;
    }

    body {
        font-family: 'Inter', sans-serif;
        background: var(--light);
    }

    .dashboard-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        transition: transform 0.2s;
    }

    .dashboard-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 12px rgba(0,0,0,0.1);
    }

    .form-section h4 {
        color: var(--primary);
        font-weight: bold;
        margin-bottom: 1rem;
    }

    .btn-primary {
        background-color: var(--primary);
        border-color: var(--primary);
    }

    .btn-primary:hover {
        background-color: #224a6b;
        border-color: #224a6b;
    }

    .btn-success {
        background-color: var(--secondary);
        border-color: var(--secondary);
    }

    .btn-success:hover {
        background-color: #3d8b40;
        border-color: #3d8b40;
    }

    .form-label {
        font-weight: 500;
    }

    .pj-only {
        display: none;
    }
    </style>
</head>
<body>
    <?php require __DIR__ . '/navbar.php'; ?>

    <div class="container-fluid px-4 py-4">
        <!-- Cabeçalho -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="text-primary fw-bold mb-0"><i class="fas fa-edit me-2"></i>Editar Cliente</h2>
            <a href="lista.php" class="btn btn-primary"><i class="fas fa-arrow-left me-1"></i>Voltar</a>
        </div>

        <!-- Mensagem de Erro -->
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Formulário -->
        <div class="dashboard-card form-section">
            <h4><i class="fas fa-users me-2"></i>Dados do Cliente</h4>
            <form method="POST">
                <div class="row g-3">
                    <!-- Dados Principais -->
                    <div class="col-md-6">
                        <label for="tipo_cliente" class="form-label">Tipo de Cliente</label>
                        <select class="form-select" id="tipo_cliente" name="tipo_cliente" required>
                            <option value="Pessoa Física" <?= $cliente['tipo_cliente'] === 'Pessoa Física' ? 'selected' : '' ?>>Pessoa Física</option>
                            <option value="Pessoa Jurídica" <?= $cliente['tipo_cliente'] === 'Pessoa Jurídica' ? 'selected' : '' ?>>Pessoa Jurídica</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="nome" class="form-label">Nome Completo/Razão Social</label>
                        <input type="text" class="form-control" id="nome" name="nome" value="<?= htmlspecialchars($cliente['nome'], ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    <div class="col-md-6 pj-only">
                        <label for="razao_social" class="form-label">Razão Social</label>
                        <input type="text" class="form-control" id="razao_social" name="razao_social" value="<?= htmlspecialchars($cliente['razao_social'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-6 pj-only">
                        <label for="nome_fantasia" class="form-label">Nome Fantasia</label>
                        <input type="text" class="form-control" id="nome_fantasia" name="nome_fantasia" value="<?= htmlspecialchars($cliente['nome_fantasia'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="cnpj_cpf" class="form-label">CPF/CNPJ</label>
                        <input type="text" class="form-control" id="cnpj_cpf" name="cnpj_cpf" value="<?= htmlspecialchars($cliente['cnpj_cpf'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="telefone" class="form-label">Telefone</label>
                        <input type="text" class="form-control" id="telefone" name="telefone" value="<?= htmlspecialchars($cliente['telefone'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="email" class="form-label">E-mail</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($cliente['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <!-- Endereço -->
                    <div class="col-md-6">
                        <label for="endereco" class="form-label">Endereço</label>
                        <input type="text" class="form-control" id="endereco" name="endereco" value="<?= htmlspecialchars($cliente['endereco'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="numero" class="form-label">Número</label>
                        <input type="text" class="form-control" id="numero" name="numero" value="<?= htmlspecialchars($cliente['numero'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="complemento" class="form-label">Complemento</label>
                        <input type="text" class="form-control" id="complemento" name="complemento" value="<?= htmlspecialchars($cliente['complemento'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="bairro" class="form-label">Bairro</label>
                        <input type="text" class="form-control" id="bairro" name="bairro" value="<?= htmlspecialchars($cliente['bairro'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="cep" class="form-label">CEP</label>
                        <input type="text" class="form-control" id="cep" name="cep" value="<?= htmlspecialchars($cliente['cep'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="cidade" class="form-label">Cidade</label>
                        <input type="text" class="form-control" id="cidade" name="cidade" value="<?= htmlspecialchars($cliente['cidade'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="uf" class="form-label">UF</label>
                        <input type="text" class="form-control" id="uf" name="uf" value="<?= htmlspecialchars($cliente['uf'] ?? '', ENT_QUOTES, 'UTF-8') ?>" maxlength="2">
                    </div>
                </div>

                <div class="text-end mt-4">
                    <button type="submit" class="btn btn-success btn-lg"><i class="fas fa-save me-1"></i>Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    <script>
    $(document).ready(function() {
        // Máscaras de entrada
        $('#cnpj_cpf').mask(function() {
            return $('#tipo_cliente').val() === 'Pessoa Jurídica' ? '00.000.000/0000-00' : '000.000.000-00';
        });
        $('#telefone').mask('(00) 00000-0000');
        $('#cep').mask('00000-000');

        // Exibir/esconder campos de Pessoa Jurídica
        function togglePJFields() {
            if ($('#tipo_cliente').val() === 'Pessoa Jurídica') {
                $('.pj-only').show();
                $('#cnpj_cpf').mask('00.000.000/0000-00'); // CNPJ
            } else {
                $('.pj-only').hide();
                $('#cnpj_cpf').mask('000.000.000-00'); // CPF
            }
        }

        // Inicializar visibilidade
        togglePJFields();

        // Atualizar visibilidade ao mudar o tipo de cliente
        $('#tipo_cliente').on('change', function() {
            togglePJFields();
        });
    });
    </script>
</body>
</html>