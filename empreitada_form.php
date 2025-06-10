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

// Verificar se é uma edição
if (isset($_GET['id']) && filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $empreitada_id = (int)$_GET['id'];
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
}

// Processar o formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar CSRF Token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Token CSRF inválido.";
    } else {
        $titulo = trim(filter_input(INPUT_POST, 'titulo', FILTER_SANITIZE_STRING) ?? '');
        $valor_total = filter_input(INPUT_POST, 'valor_total', FILTER_VALIDATE_FLOAT);
        $data_inicio = $_POST['data_inicio'] ?: null;
        $data_termino = $_POST['data_termino'] ?: null;
        $ordem_servico = trim(filter_input(INPUT_POST, 'ordem_servico', FILTER_SANITIZE_STRING) ?? '');
        $local_servico = trim(filter_input(INPUT_POST, 'local_servico', FILTER_SANITIZE_STRING) ?? '');
        $cliente_responsavel = trim(filter_input(INPUT_POST, 'cliente_responsavel', FILTER_SANITIZE_STRING) ?? '');
        $observacoes = trim(filter_input(INPUT_POST, 'observacoes', FILTER_SANITIZE_STRING) ?? '');

        // Validações
        if (empty($titulo)) {
            $error = "O título é obrigatório.";
        } elseif ($valor_total <= 0) {
            $error = "O valor total deve ser maior que zero.";
        } elseif (!$data_inicio || !$data_termino) {
            $error = "As datas de início e término são obrigatórias.";
        } elseif (strtotime($data_termino) < strtotime($data_inicio)) {
            $error = "A data de término não pode ser anterior à data de início.";
        } else {
            try {
                if ($empreitada) {
                    // Atualizar empreitada existente
                    $stmt = $pdo->prepare("
                        UPDATE empreitadas SET
                            titulo = ?,
                            valor_total = ?,
                            data_inicio = ?,
                            data_termino = ?,
                            ordem_servico = ?,
                            local_servico = ?,
                            cliente_responsavel = ?,
                            observacoes = ?,
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $titulo,
                        $valor_total,
                        $data_inicio,
                        $data_termino,
                        $ordem_servico,
                        $local_servico,
                        $cliente_responsavel,
                        $observacoes,
                        $empreitada['id']
                    ]);
                    $success = "Empreitada atualizada com sucesso!";
                } else {
                    // Criar nova empreitada
                    $stmt = $pdo->prepare("
                        INSERT INTO empreitadas (
                            titulo,
                            valor_total,
                            data_inicio,
                            data_termino,
                            ordem_servico,
                            local_servico,
                            cliente_responsavel,
                            observacoes,
                            created_at,
                            updated_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ");
                    $stmt->execute([
                        $titulo,
                        $valor_total,
                        $data_inicio,
                        $data_termino,
                        $ordem_servico,
                        $local_servico,
                        $cliente_responsavel,
                        $observacoes
                    ]);
                    $success = "Empreitada criada com sucesso!";
                }
                header("Location: empreitadas_list.php?success=" . urlencode($success));
                exit();
            } catch (PDOException $e) {
                $error = "Erro ao salvar empreitada: " . $e->getMessage();
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
    <title><?= $empreitada ? 'Editar' : 'Criar' ?> Empreitada - UltraLimp</title>
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

    .is-invalid {
        border-color: #dc3545;
    }

    .invalid-feedback {
        font-size: 0.875rem;
    }

    @media (max-width: 768px) {
        .form-grid {
            grid-template-columns: 1fr;
        }
    }
    </style>
</head>
<body>
    <?php require __DIR__ . '/navbar.php'; ?>

    <div class="container-fluid px-4 py-4">
        <div class="dashboard-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="text-dark fw-bold"><i class="fas fa-briefcase me-2"></i><?= $empreitada ? 'Editar' : 'Criar' ?> Empreitada</h2>
                <a href="empreitadas_list.php" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i>Voltar
                </a>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success" role="alert"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form method="POST" id="empreitadaForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                <!-- Seção: Informações Básicas -->
                <div class="section-title">Informações Básicas</div>
                <div class="form-grid">
                    <div>
                        <label for="titulo" class="form-label">Título <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="titulo" name="titulo" required 
                               value="<?= htmlspecialchars($empreitada['titulo'] ?? '') ?>" placeholder="Ex.: Reforma de Fachada" aria-required="true">
                        <div class="invalid-feedback">Digite o título da empreitada.</div>
                    </div>
                    <div>
                        <label for="valor_total" class="form-label">Valor Total (R$) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="valor_total" name="valor_total" step="0.01" min="0" 
                               value="<?= number_format((float)($empreitada['valor_total'] ?? 0), 2, '.', '') ?>" required aria-required="true">
                        <div class="invalid-feedback">Digite um valor maior que zero.</div>
                    </div>
                    <div>
                        <label for="ordem_servico" class="form-label">Número da Ordem de Serviço</label>
                        <input type="text" class="form-control" id="ordem_servico" name="ordem_servico" 
                               value="<?= htmlspecialchars($empreitada['ordem_servico'] ?? '') ?>" placeholder="Ex.: OS-123">
                    </div>
                    <div>
                        <label for="local_servico" class="form-label">Local do Serviço</label>
                        <input type="text" class="form-control" id="local_servico" name="local_servico" 
                               value="<?= htmlspecialchars($empreitada['local_servico'] ?? '') ?>" placeholder="Ex.: Rua das Flores, 123">
                    </div>
                    <div>
                        <label for="cliente_responsavel" class="form-label">Cliente Responsável</label>
                        <input type="text" class="form-control" id="cliente_responsavel" name="cliente_responsavel" 
                               value="<?= htmlspecialchars($empreitada['cliente_responsavel'] ?? '') ?>" placeholder="Ex.: João Silva">
                    </div>
                    <div>
                        <label for="data_inicio" class="form-label">Data de Início <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="data_inicio" name="data_inicio" 
                               value="<?= isset($empreitada['data_inicio']) ? date('Y-m-d', strtotime($empreitada['data_inicio'])) : '' ?>" required aria-required="true">
                        <div class="invalid-feedback">Selecione a data de início.</div>
                    </div>
                    <div>
                        <label for="data_termino" class="form-label">Data de Término <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="data_termino" name="data_termino" 
                               value="<?= isset($empreitada['data_termino']) ? date('Y-m-d', strtotime($empreitada['data_termino'])) : '' ?>" required aria-required="true">
                        <div class="invalid-feedback">Selecione a data de término.</div>
                    </div>
                </div>

                <!-- Seção: Observações -->
                <div class="section-title mt-4">Observações</div>
                <div class="form-grid">
                    <div class="form-group-full">
                        <label for="observacoes" class="form-label">Observações</label>
                        <textarea class="form-control" id="observacoes" name="observacoes" rows="3" 
                                  placeholder="Notas adicionais (opcional)"><?= htmlspecialchars($empreitada['observacoes'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- Botões -->
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="empreitadas_list.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Salvar</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('empreitadaForm');

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

            const dataInicio = new Date(document.getElementById('data_inicio').value);
            const dataTermino = new Date(document.getElementById('data_termino').value);
            if (dataInicio && dataTermino && dataTermino < dataInicio) {
                document.getElementById('data_termino').classList.add('is-invalid');
                isValid = false;
            }

            if (!isValid) {
                e.preventDefault();
                form.classList.add('was-validated');
            }
        });

        form.querySelectorAll('input, select').forEach(input => {
            input.addEventListener('input', () => {
                if (input.value.trim()) input.classList.remove('is-invalid');
            });
        });
    });
    </script>
</body>
</html>