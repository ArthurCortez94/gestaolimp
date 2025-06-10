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
$item = null;

// Verificar se o ID foi fornecido
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    header("Location: lavanderia_list.php?error=ID+inválido");
    exit();
}

$item_id = (int)$_GET['id'];

// Carregar os dados do item
try {
    $stmt = $pdo->prepare("
        SELECT l.*, c.nome AS cliente_nome 
        FROM lavanderia l 
        LEFT JOIN clientes c ON l.cliente_id = c.id 
        WHERE l.id = ?
    ");
    $stmt->execute([$item_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        header("Location: lavanderia_list.php?error=Item+não+encontrado");
        exit();
    }

    // Decodificar o campo 'itens' (JSON)
    $item['itens'] = json_decode($item['itens'], true) ?: [];
} catch (PDOException $e) {
    die("Erro ao carregar item: " . $e->getMessage());
}

// Carregar lista de clientes para o dropdown
try {
    $stmt = $pdo->prepare("SELECT id, nome FROM clientes ORDER BY nome ASC");
    $stmt->execute();
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao carregar clientes: " . $e->getMessage());
}

// Processar o formulário de edição
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar CSRF Token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Token CSRF inválido.";
    } else {
        // Sanitizar e validar entradas
        $cliente_id = filter_input(INPUT_POST, 'cliente_id', FILTER_VALIDATE_INT);
        $itens = $_POST['itens'] ?? [];
        $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING) ?? 'Em Processamento';
        $data_prevista_entrega = $_POST['data_prevista_entrega'] ?: null;
        $data_coleta = $_POST['data_coleta'] ?: null;
        $data_entrega = $_POST['data_entrega'] ?: null;
        $observacao = trim(filter_input(INPUT_POST, 'observacao', FILTER_SANITIZE_STRING) ?? '');

        // Validações
        if (!$cliente_id) {
            $error = "Cliente é obrigatório.";
        } elseif (empty($itens)) {
            $error = "Adicione pelo menos um item.";
        } elseif (!in_array($status, ['Em Processamento', 'Lavado', 'Pronto para Retirada'])) {
            $error = "Status inválido.";
        } else {
            // Validar e processar os itens
            $itens_validados = [];
            $valor_total_geral = 0;

            foreach ($itens as $index => $subitem) {
                // Usar filter_var para sanitizar e garantir que o valor não seja null
                $nome_item = isset($subitem['nome_item']) ? trim(filter_var($subitem['nome_item'], FILTER_SANITIZE_STRING)) : '';
                $quantidade = isset($subitem['quantidade']) ? filter_var($subitem['quantidade'], FILTER_VALIDATE_INT) : 0;
                $valor_unitario = isset($subitem['valor_unitario']) ? filter_var($subitem['valor_unitario'], FILTER_VALIDATE_FLOAT) : 0;
                $valor_total = isset($subitem['valor_total']) ? filter_var($subitem['valor_total'], FILTER_VALIDATE_FLOAT) : 0;

                // Validações
                if (empty($nome_item)) {
                    $error = "Nome do item é obrigatório para o item " . ($index + 1) . ".";
                    break;
                }
                if ($quantidade <= 0) {
                    $error = "Quantidade deve ser maior que zero para o item " . ($index + 1) . ".";
                    break;
                }
                if ($valor_unitario < 0) {
                    $error = "Valor unitário não pode ser negativo para o item " . ($index + 1) . ".";
                    break;
                }
                if (abs($valor_total - ($quantidade * $valor_unitario)) > 0.01) {
                    $error = "Valor total inconsistente para o item " . ($index + 1) . ".";
                    break;
                }

                $itens_validados[] = [
                    'nome_item' => $nome_item,
                    'quantidade' => $quantidade,
                    'valor_unitario' => $valor_unitario,
                    'valor_total' => $valor_total
                ];
                $valor_total_geral += $valor_total;
            }

            if (!$error) {
                try {
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare("
                        UPDATE lavanderia SET
                            cliente_id = ?,
                            itens = ?,
                            valor_total_geral = ?,
                            status = ?,
                            data_prevista_entrega = ?,
                            data_coleta = ?,
                            data_entrega = ?,
                            observacao = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $cliente_id,
                        json_encode($itens_validados, JSON_UNESCAPED_UNICODE),
                        $valor_total_geral,
                        $status,
                        $data_prevista_entrega,
                        $data_coleta,
                        $data_entrega,
                        $observacao,
                        $item_id
                    ]);
                    $pdo->commit();
                    $success = "Item atualizado com sucesso!";
                    header("Location: lavanderia_list.php?success=" . urlencode($success));
                    exit();
                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $error = "Erro ao atualizar item: " . $e->getMessage();
                }
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
    <title>Editar Item - Lavanderia UltraLimp</title>
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

        .btn-danger {
            padding: 0.5rem 1rem;
            border-radius: 8px;
        }

        .is-invalid {
            border-color: #dc3545;
        }

        .invalid-feedback {
            font-size: 0.875rem;
        }

        .item-container {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            position: relative;
        }

        .item-container .form-grid {
            gap: 1rem;
        }

        .total-geral {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary);
            margin-top: 1rem;
            text-align: right;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            .item-container .form-grid {
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
                <h2 class="text-dark fw-bold"><i class="fas fa-tshirt me-2"></i>Editar Itens (ID: <?= $item['id'] ?>)</h2>
                <a href="lavanderia_list.php" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i>Voltar
                </a>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success" role="alert"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form method="POST" id="lavanderiaForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                <!-- Seção: Informações Básicas -->
                <div class="section-title">Informações Básicas</div>
                <div class="form-grid">
                    <div>
                        <label for="cliente_id" class="form-label">Cliente <span class="text-danger">*</span></label>
                        <select class="form-select" id="cliente_id" name="cliente_id" required aria-required="true">
                            <option value="">Selecione um cliente</option>
                            <?php foreach ($clientes as $cliente): ?>
                                <option value="<?= $cliente['id'] ?>" <?= $item['cliente_id'] == $cliente['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cliente['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Selecione um cliente.</div>
                    </div>
                </div>

                <!-- Seção: Itens -->
                <div class="section-title mt-4">Itens</div>
                <div id="itens-container">
                    <?php foreach ($item['itens'] as $index => $subitem): ?>
                        <div class="item-container">
                            <div class="form-grid">
                                <div>
                                    <label for="itens_<?= $index ?>_nome_item" class="form-label">Nome do Item <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="itens_<?= $index ?>_nome_item" name="itens[<?= $index ?>][nome_item]" 
                                           value="<?= htmlspecialchars($subitem['nome_item']) ?>" required placeholder="Ex.: Tapete Persa" aria-required="true">
                                    <div class="invalid-feedback">Digite o nome do item.</div>
                                </div>
                                <div>
                                    <label for="itens_<?= $index ?>_quantidade" class="form-label">Quantidade <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control quantidade" id="itens_<?= $index ?>_quantidade" name="itens[<?= $index ?>][quantidade]" 
                                           min="1" value="<?= $subitem['quantidade'] ?>" required aria-required="true">
                                    <div class="invalid-feedback">Quantidade deve ser maior que 0.</div>
                                </div>
                                <div>
                                    <label for="itens_<?= $index ?>_valor_unitario" class="form-label">Valor Unitário (R$) <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control valor-unitario" id="itens_<?= $index ?>_valor_unitario" name="itens[<?= $index ?>][valor_unitario]" 
                                           step="0.01" min="0" value="<?= number_format((float)$subitem['valor_unitario'], 2, '.', '') ?>" required aria-required="true">
                                    <div class="invalid-feedback">Valor não pode ser negativo.</div>
                                </div>
                                <div>
                                    <label for="itens_<?= $index ?>_valor_total" class="form-label">Valor Total (R$)</label>
                                    <input type="number" class="form-control valor-total" id="itens_<?= $index ?>_valor_total" name="itens[<?= $index ?>][valor_total]" 
                                           step="0.01" value="<?= number_format((float)$subitem['valor_total'], 2, '.', '') ?>" readonly aria-readonly="true">
                                </div>
                            </div>
                            <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0 mt-2 me-2 remove-item">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn btn-primary mt-2" id="add-item"><i class="fas fa-plus me-2"></i>Adicionar Item</button>
                <div class="total-geral">Valor Total Geral: R$ <span id="valor-total-geral"><?= number_format((float)$item['valor_total_geral'], 2, '.', '') ?></span></div>

                <!-- Seção: Status e Datas -->
                <div class="section-title mt-4">Status e Datas</div>
                <div class="form-grid">
                    <div>
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status" aria-describedby="statusHelp">
                            <option value="Em Processamento" <?= $item['status'] === 'Em Processamento' ? 'selected' : '' ?>>Em Processamento</option>
                            <option value="Lavado" <?= $item['status'] === 'Lavado' ? 'selected' : '' ?>>Lavado</option>
                            <option value="Pronto para Retirada" <?= $item['status'] === 'Pronto para Retirada' ? 'selected' : '' ?>>Pronto para Retirada</option>
                        </select>
                        <small id="statusHelp" class="form-text text-muted">Estado atual dos itens.</small>
                    </div>
                    <div>
                        <label for="data_coleta" class="form-label">Data de Coleta</label>
                        <input type="date" class="form-control" id="data_coleta" name="data_coleta" 
                               value="<?= $item['data_coleta'] ? date('Y-m-d', strtotime($item['data_coleta'])) : '' ?>">
                    </div>
                    <div>
                        <label for="data_prevista_entrega" class="form-label">Data Prevista de Entrega</label>
                        <input type="date" class="form-control" id="data_prevista_entrega" name="data_prevista_entrega" 
                               value="<?= $item['data_prevista_entrega'] ? date('Y-m-d', strtotime($item['data_prevista_entrega'])) : '' ?>">
                    </div>
                    <div>
                        <label for="data_entrega" class="form-label">Data de Entrega</label>
                        <input type="date" class="form-control" id="data_entrega" name="data_entrega" 
                               value="<?= $item['data_entrega'] ? date('Y-m-d', strtotime($item['data_entrega'])) : '' ?>">
                    </div>
                </div>

                <!-- Seção: Observações -->
                <div class="section-title mt-4">Observações</div>
                <div class="form-grid">
                    <div class="form-group-full">
                        <label for="observacao" class="form-label">Observação</label>
                        <textarea class="form-control" id="observacao" name="observacao" rows="3" 
                                  placeholder="Notas adicionais (opcional)"><?= htmlspecialchars($item['observacao'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- Botões -->
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="lavanderia_list.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('lavanderiaForm');
        const itensContainer = document.getElementById('itens-container');
        let itemCount = <?= count($item['itens']) ?>;

        // Função para calcular o valor total de um item
        const calcularTotalItem = (container) => {
            const quantidade = parseInt(container.querySelector('.quantidade').value) || 0;
            const valorUnitario = parseFloat(container.querySelector('.valor-unitario').value) || 0;
            const valorTotal = container.querySelector('.valor-total');
            valorTotal.value = (quantidade * valorUnitario).toFixed(2);
            calcularTotalGeral();
        };

        // Função para calcular o valor total geral
        const calcularTotalGeral = () => {
            let totalGeral = 0;
            document.querySelectorAll('.valor-total').forEach(input => {
                totalGeral += parseFloat(input.value) || 0;
            });
            document.getElementById('valor-total-geral').textContent = totalGeral.toFixed(2);
        };

        // Adicionar novo item
        document.getElementById('add-item').addEventListener('click', () => {
            const newItem = document.createElement('div');
            newItem.classList.add('item-container');
            newItem.innerHTML = `
                <div class="form-grid">
                    <div>
                        <label for="itens_${itemCount}_nome_item" class="form-label">Nome do Item <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="itens_${itemCount}_nome_item" name="itens[${itemCount}][nome_item]" 
                               required placeholder="Ex.: Tapete Persa" aria-required="true">
                        <div class="invalid-feedback">Digite o nome do item.</div>
                    </div>
                    <div>
                        <label for="itens_${itemCount}_quantidade" class="form-label">Quantidade <span class="text-danger">*</span></label>
                        <input type="number" class="form-control quantidade" id="itens_${itemCount}_quantidade" name="itens[${itemCount}][quantidade]" 
                               min="1" value="1" required aria-required="true">
                        <div class="invalid-feedback">Quantidade deve ser maior que 0.</div>
                    </div>
                    <div>
                        <label for="itens_${itemCount}_valor_unitario" class="form-label">Valor Unitário (R$) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control valor-unitario" id="itens_${itemCount}_valor_unitario" name="itens[${itemCount}][valor_unitario]" 
                               step="0.01" min="0" value="0.00" required aria-required="true">
                        <div class="invalid-feedback">Valor não pode ser negativo.</div>
                    </div>
                    <div>
                        <label for="itens_${itemCount}_valor_total" class="form-label">Valor Total (R$)</label>
                        <input type="number" class="form-control valor-total" id="itens_${itemCount}_valor_total" name="itens[${itemCount}][valor_total]" 
                               step="0.01" readonly aria-readonly="true">
                    </div>
                </div>
                <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0 mt-2 me-2 remove-item">
                    <i class="fas fa-trash"></i>
                </button>
            `;
            itensContainer.appendChild(newItem);

            // Adicionar eventos aos novos campos
            const container = itensContainer.lastElementChild;
            container.querySelector('.quantidade').addEventListener('input', () => calcularTotalItem(container));
            container.querySelector('.valor-unitario').addEventListener('input', () => calcularTotalItem(container));
            container.querySelector('.remove-item').addEventListener('click', () => {
                container.remove();
                calcularTotalGeral();
                updateRemoveButtons();
            });

            itemCount++;
            updateRemoveButtons();
            calcularTotalItem(container);
        });

        // Atualizar visibilidade dos botões de remoção
        const updateRemoveButtons = () => {
            const items = document.querySelectorAll('.item-container');
            items.forEach((item, index) => {
                const removeButton = item.querySelector('.remove-item');
                removeButton.style.display = items.length > 1 ? 'block' : 'none';
            });
        };

        // Inicializar eventos para os itens existentes
        document.querySelectorAll('.item-container').forEach(container => {
            container.querySelector('.quantidade').addEventListener('input', () => calcularTotalItem(container));
            container.querySelector('.valor-unitario').addEventListener('input', () => calcularTotalItem(container));
            container.querySelector('.remove-item').addEventListener('click', () => {
                container.remove();
                calcularTotalGeral();
                updateRemoveButtons();
            });
            calcularTotalItem(container);
        });

        updateRemoveButtons();
        calcularTotalGeral();

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

            if (!isValid) {
                e.preventDefault();
                form.classList.add('was-validated');
            }
        });

        form.querySelectorAll('input, select').forEach(input => {
            input.addEventListener('input', () => {
                if (input.value.trim()) {
                    input.classList.remove('is-invalid');
                }
            });
        });
    });
    </script>
</body>
</html>