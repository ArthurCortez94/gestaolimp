<?php
session_start();
require_once 'config.php';

// Verificação de segurança: apenas admin pode acessar
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_cargo'], ['atendente', 'admin'])) {
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

// Buscar técnicos ativos do banco de dados
$tecnicos = [];
$stmt = $pdo->query("
    SELECT id, nome, email, telefone, especialidade, data_admissao, cargo, ativo
    FROM usuarios 
    WHERE cargo = 'tecnico' AND ativo = 1
    ORDER BY nome
");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $tecnicos[] = $row;
}

// Processar edição de usuário
$error = '';
$success = '';
$edit_info = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'editar') {
    try {
        $id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);
        $nome = filter_input(INPUT_POST, 'nome', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $telefone = filter_input(INPUT_POST, 'telefone', FILTER_SANITIZE_STRING);
        $cargo = filter_input(INPUT_POST, 'cargo', FILTER_SANITIZE_STRING);
        $especialidade = filter_input(INPUT_POST, 'especialidade', FILTER_SANITIZE_STRING) ?: null;
        $data_admissao = filter_input(INPUT_POST, 'data_admissao', FILTER_SANITIZE_STRING) ?: null;
        $alterar_senha = filter_input(INPUT_POST, 'alterar_senha', FILTER_SANITIZE_STRING);
        $nova_senha = $_POST['nova_senha'] ?? null;

        if (empty($nome) || empty($email) || empty($cargo)) {
            throw new Exception("Os campos Nome, Email e Cargo são obrigatórios.");
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Email inválido.");
        }
        if ($telefone && !preg_match('/^\(\d{2}\)\d{8,9}$/', $telefone)) {
            throw new Exception("Telefone inválido. Use o formato (XX)XXXXXXXX ou (XX)XXXXXXXXX.");
        }
        if (!in_array($cargo, ['tecnico', 'atendente', 'admin'])) {
            throw new Exception("Cargo inválido.");
        }
        if ($alterar_senha === 'sim' && (empty($nova_senha) || strlen($nova_senha) < 6)) {
            throw new Exception("A nova senha deve ter pelo menos 6 caracteres.");
        }

        // Preparar a query de atualização
        $query = "UPDATE usuarios SET nome = ?, email = ?, telefone = ?, cargo = ?, especialidade = ?, data_admissao = ?";
        $params = [$nome, $email, $telefone, $cargo, $especialidade, $data_admissao];

        if ($alterar_senha === 'sim') {
            $hash = password_hash($nova_senha, PASSWORD_DEFAULT);
            $query .= ", senha = ?";
            $params[] = $hash;
        }

        $query .= " WHERE id = ?";
        $params[] = $id;

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);

        $success = "Técnico '$nome' editado com sucesso!";

        // Preparar informações para o pop-up
        $edit_info = "Informações do Técnico Atualizado:\n" .
                     "Nome: $nome\n" .
                     "Email: $email\n" .
                     "Telefone: " . ($telefone ?: 'Não informado') . "\n" .
                     "Cargo: $cargo\n" .
                     "Especialidade: " . ($especialidade ?: 'Não especificada') . "\n" .
                     "Data de Admissão: " . ($data_admissao ? date('d/m/Y', strtotime($data_admissao)) : 'Não informada');
        if ($alterar_senha === 'sim') {
            $edit_info .= "\nNova Senha: $nova_senha";
        }

    } catch (Exception $e) {
        $error = "Erro ao editar: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Técnicos - UltraLimp</title>
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

    .status-ativo {
        color: var(--secondary);
        font-weight: bold;
    }

    .status-inativo {
        color: var(--gray);
        font-weight: bold;
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

    <div class="container-fluid">
        <!-- Cabeçalho -->
        <div class="header-section">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="mb-0"><i class="fas fa-users me-2"></i>Lista de Técnicos</h2>
                <a href="criar_orcamento.php" class="btn btn-outline-light">
                    <i class="fas fa-arrow-left me-1"></i>Voltar
                </a>
            </div>
        </div>

        <!-- Mensagem de sucesso ou erro -->
        <?php if ($success && !$edit_info): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Lista de Técnicos -->
        <div class="dashboard-card">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>E-mail</th>
                        <th>Telefone</th>
                        <th>Cargo</th>
                        <th>Especialidade</th>
                        <th>Data de Admissão</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tecnicos)): ?>
                        <tr>
                            <td colspan="9" class="text-center">Nenhum técnico ativo cadastrado.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($tecnicos as $tecnico): ?>
                            <tr>
                                <td><?= $tecnico['id'] ?></td>
                                <td><?= htmlspecialchars($tecnico['nome']) ?></td>
                                <td><?= htmlspecialchars($tecnico['email']) ?></td>
                                <td><?= htmlspecialchars($tecnico['telefone'] ?? 'Não informado') ?></td>
                                <td><?= htmlspecialchars($tecnico['cargo']) ?></td>
                                <td><?= htmlspecialchars($tecnico['especialidade'] ?? 'Não especificada') ?></td>
                                <td><?= $tecnico['data_admissao'] ? date('d/m/Y', strtotime($tecnico['data_admissao'])) : 'Não informada' ?></td>
                                <td class="<?= $tecnico['ativo'] ? 'status-ativo' : 'status-inativo' ?>">
                                    <?= $tecnico['ativo'] ? 'Ativo' : 'Inativo' ?>
                                </td>
                                <td>
                                    <button class="btn btn-warning btn-sm" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editarTecnicoModal"
                                            data-id="<?= $tecnico['id'] ?>"
                                            data-nome="<?= htmlspecialchars($tecnico['nome']) ?>"
                                            data-email="<?= htmlspecialchars($tecnico['email']) ?>"
                                            data-telefone="<?= htmlspecialchars($tecnico['telefone'] ?? '') ?>"
                                            data-cargo="<?= htmlspecialchars($tecnico['cargo']) ?>"
                                            data-especialidade="<?= htmlspecialchars($tecnico['especialidade'] ?? '') ?>"
                                            data-data_admissao="<?= htmlspecialchars($tecnico['data_admissao'] ?? '') ?>">
                                        <i class="fas fa-edit"></i> Editar
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal para editar técnico -->
    <div class="modal fade" id="editarTecnicoModal" tabindex="-1" aria-labelledby="editarTecnicoModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editarTecnicoModalLabel">Editar Técnico</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="acao" value="editar">
                        <input type="hidden" name="id" id="editarId">
                        <div class="mb-3">
                            <label class="form-label">Nome</label>
                            <input type="text" name="nome" id="editarNome" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" id="editarEmail" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Telefone</label>
                            <input type="text" name="telefone" id="editarTelefone" class="form-control" placeholder="(XX)XXXXXXXXX">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Cargo</label>
                            <select name="cargo" id="editarCargo" class="form-select" required>
                                <option value="tecnico">Técnico</option>
                                <option value="atendente">Atendente</option>
                                <option value="admin">Administrador</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Especialidade</label>
                            <input type="text" name="especialidade" id="editarEspecialidade" class="form-control" placeholder="Ex.: Limpeza, Manutenção">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Data de Admissão</label>
                            <input type="date" name="data_admissao" id="editarDataAdmissao" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Alterar Senha?</label>
                            <select name="alterar_senha" id="alterarSenha" class="form-select" onchange="toggleSenhaField()">
                                <option value="nao">Não</option>
                                <option value="sim">Sim</option>
                            </select>
                        </div>
                        <div class="mb-3" id="novaSenhaField" style="display: none;">
                            <label class="form-label">Nova Senha</label>
                            <input type="password" name="nova_senha" id="novaSenha" class="form-control" placeholder="Mínimo 6 caracteres">
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

    <!-- Modal para exibir informações da edição -->
    <div class="modal fade" id="editInfoModal" tabindex="-1" aria-labelledby="editInfoModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editInfoModalLabel">Informações do Técnico Atualizado</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Copie as informações abaixo para enviar ao técnico:</p>
                    <textarea class="form-control info-text" readonly><?= htmlspecialchars($edit_info) ?></textarea>
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
    const editarModal = document.getElementById('editarTecnicoModal');
    editarModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const id = button.getAttribute('data-id');
        const nome = button.getAttribute('data-nome');
        const email = button.getAttribute('data-email');
        const telefone = button.getAttribute('data-telefone');
        const cargo = button.getAttribute('data-cargo');
        const especialidade = button.getAttribute('data-especialidade');
        const dataAdmissao = button.getAttribute('data-data_admissao');

        const modalId = editarModal.querySelector('#editarId');
        const modalNome = editarModal.querySelector('#editarNome');
        const modalEmail = editarModal.querySelector('#editarEmail');
        const modalTelefone = editarModal.querySelector('#editarTelefone');
        const modalCargo = editarModal.querySelector('#editarCargo');
        const modalEspecialidade = editarModal.querySelector('#editarEspecialidade');
        const modalDataAdmissao = editarModal.querySelector('#editarDataAdmissao');

        modalId.value = id;
        modalNome.value = nome;
        modalEmail.value = email;
        modalTelefone.value = telefone;
        modalCargo.value = cargo;
        modalEspecialidade.value = especialidade;
        modalDataAdmissao.value = dataAdmissao;

        new Cleave('#editarTelefone', {
            blocks: [0, 2, 9],
            delimiters: ['(', ')', '']
        });

        // Resetar o campo de senha
        document.getElementById('alterarSenha').value = 'nao';
        document.getElementById('novaSenhaField').style.display = 'none';
        document.getElementById('novaSenha').value = '';
    });

    function toggleSenhaField() {
        const alterarSenha = document.getElementById('alterarSenha').value;
        const novaSenhaField = document.getElementById('novaSenhaField');
        novaSenhaField.style.display = alterarSenha === 'sim' ? 'block' : 'none';
    }

    // Exibir o modal automaticamente após a edição
    <?php if ($edit_info): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const modal = new bootstrap.Modal(document.getElementById('editInfoModal'));
            modal.show();
        });
    <?php endif; ?>
    </script>
</body>
</html>