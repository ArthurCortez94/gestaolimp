<?php
declare(strict_types=1);
session_start();

// Verificação de segurança: apenas admin pode acessar
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_cargo'], ['atendente', 'admin'])) {
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

require_once 'config.php';

$error = '';
$success = '';
$cadastro_info = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = filter_input(INPUT_POST, 'nome', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $telefone = filter_input(INPUT_POST, 'telefone', FILTER_SANITIZE_STRING);
    $senha = $_POST['senha'];
    $cargo = filter_input(INPUT_POST, 'cargo', FILTER_SANITIZE_STRING);
    $especialidade = filter_input(INPUT_POST, 'especialidade', FILTER_SANITIZE_STRING) ?: null;
    $data_admissao = filter_input(INPUT_POST, 'data_admissao', FILTER_SANITIZE_STRING) ?: null;

    // Validações
    if (empty($nome) || empty($email) || empty($senha) || empty($cargo)) {
        $error = "Os campos Nome, Email, Senha e Cargo são obrigatórios.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Email inválido.";
    } elseif (strlen($senha) < 6) {
        $error = "A senha deve ter pelo menos 6 caracteres.";
    } elseif (!in_array($cargo, ['tecnico', 'atendente', 'admin'])) {
        $error = "Cargo inválido.";
    } elseif ($telefone && !preg_match('/^\(\d{2}\)\d{8,9}$/', $telefone)) {
        $error = "Telefone inválido. Use o formato (XX)XXXXXXXX ou (XX)XXXXXXXXX.";
    } else {
        try {
            // Verifica se o email já existe
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                $error = "Este email já está cadastrado.";
            } else {
                // Criptografa a senha
                $hash = password_hash($senha, PASSWORD_DEFAULT);

                // Insere o novo usuário
                $stmt = $pdo->prepare("
                    INSERT INTO usuarios (nome, email, telefone, senha, cargo, especialidade, data_admissao)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$nome, $email, $telefone, $hash, $cargo, $especialidade, $data_admissao]);

                $success = "Usuário '$nome' cadastrado com sucesso como $cargo!";

                // Preparar informações para o pop-up
                $cadastro_info = "Informações do Novo Usuário:\n" .
                                 "Nome: $nome\n" .
                                 "Email: $email\n" .
                                 "Telefone: " . ($telefone ?: 'Não informado') . "\n" .
                                 "Senha: $senha\n" .
                                 "Cargo: $cargo\n" .
                                 "Especialidade: " . ($especialidade ?: 'Não especificada') . "\n" .
                                 "Data de Admissão: " . ($data_admissao ? date('d/m/Y', strtotime($data_admissao)) : 'Não informada');
            }
        } catch (PDOException $e) {
            $error = "Erro ao cadastrar: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastrar Usuário - UltraLimp</title>
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
    .form-container {
        max-width: 600px;
        margin: 0 auto;
        padding: 2rem;
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    }
    .btn-primary {
        background: var(--primary);
        border: none;
    }
    .btn-primary:hover {
        background: #1a365f;
    }
    textarea.info-text {
        width: 100%;
        height: 200px;
        resize: none;
    }
    </style>
</head>
<body>

<?php require __DIR__ . '/navbar.php'; ?>

<div class="container-fluid px-4 py-4">
    <h2 class="text-primary fw-bold mb-4">Cadastrar Novo Usuário</h2>

    <div class="form-container">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success && !$cadastro_info): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Nome</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                    <input type="text" name="nome" class="form-control" required>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Email</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                    <input type="email" name="email" class="form-control" required>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Telefone</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-phone"></i></span>
                    <input type="text" name="telefone" class="form-control" placeholder="(XX)XXXXXXXXX">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Senha</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input type="password" name="senha" class="form-control" required>
                </div>
                <small class="text-muted">Mínimo de 6 caracteres</small>
            </div>

            <div class="mb-3">
                <label class="form-label">Cargo</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-briefcase"></i></span>
                    <select name="cargo" class="form-select" required>
                        <option value="">Selecione o cargo</option>
                        <option value="tecnico">Técnico</option>
                        <option value="atendente">Atendente</option>
                        <option value="admin">Administrador</option>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Especialidade</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-tools"></i></span>
                    <input type="text" name="especialidade" class="form-control" placeholder="Ex.: Limpeza, Manutenção">
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label">Data de Admissão</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                    <input type="date" name="data_admissao" class="form-control">
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100">
                <i class="fas fa-save me-2"></i>Cadastrar
            </button>

            <a href="dashboard.php" class="btn btn-outline-secondary w-100 mt-2">
                <i class="fas fa-arrow-left me-2"></i>Voltar ao Dashboard
            </a>
        </form>
    </div>
</div>

<!-- Modal para exibir informações do cadastro -->
<div class="modal fade" id="cadastroInfoModal" tabindex="-1" aria-labelledby="cadastroInfoModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cadastroInfoModalLabel">Informações do Cadastro</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Copie as informações abaixo para enviar ao técnico:</p>
                <textarea class="form-control info-text" readonly><?= htmlspecialchars($cadastro_info) ?></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/cleave.js@1.6.0/dist/cleave.min.js"></script>
<script>
// Máscara para o telefone
new Cleave('input[name="telefone"]', {
    blocks: [0, 2, 9],
    delimiters: ['(', ')', '']
});

// Exibir o modal automaticamente após o cadastro
<?php if ($cadastro_info): ?>
    document.addEventListener('DOMContentLoaded', function() {
        const modal = new bootstrap.Modal(document.getElementById('cadastroInfoModal'));
        modal.show();
    });
<?php endif; ?>
</script>
</body>
</html>