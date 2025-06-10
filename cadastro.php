<?php
declare(strict_types=1);
session_start();

// Verificação de segurança
if (!isset($_SESSION['user_id'])) {
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
$dados = [];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validação CSRF
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            throw new Exception("Token de segurança inválido!");
        }

        // Sanitização dos dados
        $dados = [
            'tipo_cliente' => $_POST['tipo_cliente'] === 'Pessoa Jurídica' ? 'Pessoa Jurídica' : 'Pessoa Física',
            'nome' => filter_input(INPUT_POST, 'nome', FILTER_SANITIZE_FULL_SPECIAL_CHARS),
            'razao_social' => filter_input(INPUT_POST, 'razao_social', FILTER_SANITIZE_FULL_SPECIAL_CHARS),
            'nome_fantasia' => filter_input(INPUT_POST, 'nome_fantasia', FILTER_SANITIZE_FULL_SPECIAL_CHARS),
            'cnpj_cpf' => preg_replace('/[^0-9]/', '', $_POST['cnpj_cpf'] ?? ''),
            'endereco' => filter_input(INPUT_POST, 'endereco', FILTER_SANITIZE_FULL_SPECIAL_CHARS),
            'numero' => filter_input(INPUT_POST, 'numero', FILTER_SANITIZE_FULL_SPECIAL_CHARS),
            'complemento' => filter_input(INPUT_POST, 'complemento', FILTER_SANITIZE_FULL_SPECIAL_CHARS),
            'bairro' => filter_input(INPUT_POST, 'bairro', FILTER_SANITIZE_FULL_SPECIAL_CHARS),
            'cidade' => filter_input(INPUT_POST, 'cidade', FILTER_SANITIZE_FULL_SPECIAL_CHARS),
            'uf' => strtoupper(substr(filter_input(INPUT_POST, 'uf', FILTER_SANITIZE_FULL_SPECIAL_CHARS), 0, 2)),
            'cep' => preg_replace('/[^0-9]/', '', $_POST['cep'] ?? ''),
            'telefone' => filter_input(INPUT_POST, 'telefone', FILTER_SANITIZE_FULL_SPECIAL_CHARS),
            'email' => filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL)
        ];

        // Validações
        if (empty($dados['nome'])) {
            throw new Exception("Nome é obrigatório!");
        }

        if ($dados['tipo_cliente'] === 'Pessoa Jurídica') {
            if (empty($dados['razao_social'])) {
                throw new Exception("Razão Social é obrigatória para PJ!");
            }
            if (empty($dados['cnpj_cpf'])) {
                throw new Exception("CNPJ é obrigatório para PJ!");
            }
            if (!validarCNPJ($dados['cnpj_cpf'])) {
                throw new Exception("CNPJ inválido!");
            }
        } else {
            if (!empty($dados['cnpj_cpf']) && !validarCPF($dados['cnpj_cpf'])) {
                throw new Exception("CPF inválido!");
            }
        }

        // Inserir no banco
        $stmt = $pdo->prepare("INSERT INTO clientes (
            tipo_cliente, nome, razao_social, nome_fantasia, cnpj_cpf,
            endereco, numero, complemento, bairro, cidade, uf, cep, telefone, email
        ) VALUES (
            :tipo_cliente, :nome, :razao_social, :nome_fantasia, :cnpj_cpf,
            :endereco, :numero, :complemento, :bairro, :cidade, :uf, :cep, :telefone, :email
        )");

        $stmt->execute($dados);
        $success = "Cliente cadastrado com sucesso!";
        $dados = [];
    }
} catch (PDOException $e) {
    $error = $e->getCode() === '23000' ? "Documento já cadastrado!" : "Erro no banco: " . $e->getMessage();
} catch (Exception $e) {
    $error = $e->getMessage();
}

// Gerar novo token CSRF
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

function validarCPF($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    if (strlen($cpf) != 11) return false;
    
    for ($t = 9; $t < 11; $t++) {
        for ($d = 0, $c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) return false;
    }
    return true;
}

function validarCNPJ($cnpj) {
    $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
    if (strlen($cnpj) != 14) return false;

    $b = [6,5,4,3,2,9,8,7,6,5,4,3,2];
    for ($i = 0; $i < 2; $i++) {
        $soma = 0;
        for ($j = 0; $j < 12 + $i; $soma += $cnpj[$j] * $b[1 + $i + $j], $j++);
        $resto = $soma % 11;
        $digito = $resto < 2 ? 0 : 11 - $resto;
        if ($cnpj[12 + $i] != $digito) return false;
    }
    return true;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Clientes - UltraLimp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
    :root {
        --primary: #2A5C82;
        --secondary: #4CAF50;
        --accent: #FFC107;
        --light: #f8fafc;
    }

    .main-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }

    .section-card {
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        margin-bottom: 2rem;
        padding: 2rem;
        transition: transform 0.2s;
    }

    .section-title {
        color: var(--primary);
        font-size: 1.25rem;
        font-weight: 600;
        margin-bottom: 1.5rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid var(--primary);
    }

    .form-label {
        font-weight: 500;
        color: #495057;
        margin-bottom: 0.5rem;
        display: block;
    }

    .form-control-custom {
        border: 2px solid #dee2e6;
        border-radius: 8px;
        padding: 0.75rem 1rem;
        transition: border-color 0.3s ease;
        width: 100%;
    }

    .form-control-custom:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(42,92,130,0.1);
    }

    .required-star::after {
        content: "*";
        color: #dc3545;
        margin-left: 4px;
    }

    .dynamic-field {
        display: none;
        animation: fadeIn 0.3s ease;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-5px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .loading-cep::after {
        content: "";
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
        width: 20px;
        height: 20px;
        border: 3px solid #f3f3f3;
        border-top: 3px solid var(--primary);
        border-radius: 50%;
        animation: spin 1s linear infinite;
        display: none;
    }

    @keyframes spin {
        0% { transform: translateY(-50%) rotate(0deg); }
        100% { transform: translateY(-50%) rotate(360deg); }
    }
    </style>
</head>
<body>
    <?php require __DIR__ . '/navbar.php'; ?>

    <div class="main-container">
        <div class="d-flex justify-content-between align-items-center mb-5">
            <h1 class="h4 mb-0 text-primary">
                <i class="fas fa-user-plus me-2"></i>Cadastro de Clientes
            </h1>
            <a href="lista_clientes.php" class="btn btn-primary">
                <i class="fas fa-list me-2"></i>Ver Lista
            </a>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger d-flex align-items-center mb-4">
            <i class="fas fa-exclamation-circle me-3"></i>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php elseif ($success): ?>
        <div class="alert alert-success d-flex align-items-center mb-4">
            <i class="fas fa-check-circle me-3"></i>
            <?= $success ?>
        </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <!-- Seção Tipo de Cliente -->
            <div class="section-card">
                <h4 class="section-title">
                    <i class="fas fa-user-tag me-2"></i>Tipo de Cliente
                </h4>
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label required-star">Tipo</label>
                        <select name="tipo_cliente" class="form-select form-control-custom" id="tipoCliente" required>
                            <option value="Pessoa Física" <?= ($dados['tipo_cliente'] ?? '') === 'Pessoa Física' ? 'selected' : '' ?>>Pessoa Física</option>
                            <option value="Pessoa Jurídica" <?= ($dados['tipo_cliente'] ?? '') === 'Pessoa Jurídica' ? 'selected' : '' ?>>Pessoa Jurídica</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Seção Dados Principais -->
            <div class="section-card">
                <h4 class="section-title">
                    <i class="fas fa-id-card me-2"></i>Dados Principais
                </h4>
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label required-star" id="nomeLabel">Nome Completo</label>
                        <input type="text" name="nome" class="form-control form-control-custom"
                            value="<?= htmlspecialchars($dados['nome'] ?? '') ?>" 
                            required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" id="docLabel">CPF</label>
                        <input type="text" name="cnpj_cpf" class="form-control form-control-custom"
                            value="<?= htmlspecialchars($dados['cnpj_cpf'] ?? '') ?>">
                    </div>

                    <div class="col-md-6 dynamic-field">
                        <label class="form-label required-star">Razão Social</label>
                        <input type="text" name="razao_social" class="form-control form-control-custom"
                            value="<?= htmlspecialchars($dados['razao_social'] ?? '') ?>">
                    </div>

                    <div class="col-md-6 dynamic-field">
                        <label class="form-label">Nome Fantasia</label>
                        <input type="text" name="nome_fantasia" class="form-control form-control-custom"
                            value="<?= htmlspecialchars($dados['nome_fantasia'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <!-- Seção Endereço -->
            <div class="section-card">
                <h4 class="section-title">
                    <i class="fas fa-map-marker-alt me-2"></i>Endereço
                </h4>
                <div class="row g-4">
                    <div class="col-md-3">
                        <label class="form-label required-star">CEP</label>
                        <input type="text" name="cep" id="cep" class="form-control form-control-custom loading-cep"
                            value="<?= htmlspecialchars($dados['cep'] ?? '') ?>"
                            data-mask="00000-000">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label required-star">Endereço</label>
                        <input type="text" name="endereco" id="endereco" class="form-control form-control-custom"
                            value="<?= htmlspecialchars($dados['endereco'] ?? '') ?>">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label required-star">Número</label>
                        <input type="text" name="numero" class="form-control form-control-custom"
                            value="<?= htmlspecialchars($dados['numero'] ?? '') ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Complemento</label>
                        <input type="text" name="complemento" class="form-control form-control-custom"
                            value="<?= htmlspecialchars($dados['complemento'] ?? '') ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label required-star">Bairro</label>
                        <input type="text" name="bairro" id="bairro" class="form-control form-control-custom"
                            value="<?= htmlspecialchars($dados['bairro'] ?? '') ?>">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label required-star">Cidade</label>
                        <input type="text" name="cidade" id="cidade" class="form-control form-control-custom"
                            value="<?= htmlspecialchars($dados['cidade'] ?? '') ?>">
                    </div>

                    <div class="col-md-1">
                        <label class="form-label required-star">UF</label>
                        <select name="uf" id="uf" class="form-select form-control-custom">
                            <option value="">--</option>
                            <?php include __DIR__ . '/includes/uf_options.php'; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Seção Contato -->
            <div class="section-card">
                <h4 class="section-title">
                    <i class="fas fa-phone-alt me-2"></i>Contato
                </h4>
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label">Telefone</label>
                        <input type="text" name="telefone" class="form-control form-control-custom"
                            value="<?= htmlspecialchars($dados['telefone'] ?? '') ?>"
                            data-mask="(00) 00000-0000">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">E-mail</label>
                        <input type="email" name="email" class="form-control form-control-custom"
                            value="<?= htmlspecialchars($dados['email'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <div class="text-end mt-5">
                <button type="submit" class="btn btn-primary btn-lg px-5">
                    <i class="fas fa-save me-2"></i>Salvar Cliente
                </button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    <script>
    $(document).ready(function() {
        const togglePJFields = () => {
            const isPJ = $('#tipoCliente').val() === 'Pessoa Jurídica';
            $('.dynamic-field').toggle(isPJ);
            $('#docLabel').text(isPJ ? 'CNPJ' : 'CPF');
            $('#nomeLabel').text(isPJ ? 'Nome Fantasia' : 'Nome Completo');
            
            $('[name="cnpj_cpf"]').mask(
                isPJ ? '00.000.000/0000-00' : '000.000.000-00', 
                {
                    reverse: false,
                    placeholder: isPJ ? "__.___.___/____-__" : "___.___.___-__"
                }
            );
        };

        $('#tipoCliente').change(togglePJFields).trigger('change');

        $('#cep').on('blur', function() {
            const cep = $(this).cleanVal();
            if (cep.length === 8) {
                $(this).addClass('loading');
                
                fetch(`https://viacep.com.br/ws/${cep}/json/`)
                    .then(response => response.json())
                    .then(data => {
                        if (!data.erro) {
                            $('#endereco').val(data.logradouro);
                            $('#bairro').val(data.bairro);
                            $('#cidade').val(data.localidade);
                            $('#uf').val(data.uf).trigger('change');
                        }
                    })
                    .catch(() => alert('CEP não encontrado'))
                    .finally(() => $(this).removeClass('loading'));
            }
        });

        $('[name="telefone"]').mask('(00) 00000-0000');
        $('#cep').mask('00000-000');
    });
    </script>
</body>
</html>