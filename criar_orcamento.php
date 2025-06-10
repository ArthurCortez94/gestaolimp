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

// Buscar clientes existentes do banco de dados
$clientes_existentes = [];
$stmt = $pdo->query("SELECT id, nome, tipo_cliente, cnpj_cpf, endereco, numero, complemento, bairro, cidade, uf, cep, telefone, email FROM clientes ORDER BY nome");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $clientes_existentes[] = $row;
}

// Buscar itens existentes do banco de dados
$itens_existentes = [];
$stmt = $pdo->query("SELECT id, nome, valor_unitario_padrao FROM itens ORDER BY nome");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $itens_existentes[] = $row;
}

function gerarNumeroOrcamento(PDO $pdo): string {
    $stmt = $pdo->query("SELECT MAX(id) FROM orcamentos");
    $ultimo_id = $stmt->fetchColumn();
    return str_pad($ultimo_id + 1, 3, '0', STR_PAD_LEFT) . '/' . date('Y');
}

function validarCliente(array $dados): void {
    if (empty($dados['nome'])) {
        throw new Exception("O campo 'Nome Completo/Razão Social' é obrigatório.");
    }
    if (empty($dados['telefone'])) {
        throw new Exception("O campo 'Telefone' é obrigatório.");
    }
    if (!empty($dados['email']) && !filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception("E-mail inválido.");
    }
    if (!preg_match('/^\d{5}-?\d{3}$/', str_replace('-', '', $dados['cep']))) {
        throw new Exception("CEP inválido.");
    }
    if (!preg_match('/^\(\d{2}\)\d{8,9}$/', $dados['telefone'])) {
        throw new Exception("Telefone inválido. Use o formato (XX)XXXXXXXX ou (XX)XXXXXXXXX.");
    }
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction(); // Iniciar transação

        // Verificar se é um cliente existente ou novo
        $cliente_id = null;
        $isNewClient = (!isset($_POST['cliente_id']) || $_POST['cliente_id'] === 'novo' || empty($_POST['cliente_id']));
        
        if (!$isNewClient) {
            // Cliente existente selecionado
            $cliente_id = (int)$_POST['cliente_id'];
            $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
            $stmt->execute([$cliente_id]);
            $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$cliente) {
                throw new Exception("Cliente existente não encontrado no banco de dados.");
            }
        } else {
            // Novo cliente
            $dados_cliente = [
                'tipo_cliente' => $_POST['tipo_cliente'] ?? 'Pessoa Física',
                'nome' => trim($_POST['nome'] ?? ''),
                'razao_social' => $_POST['razao_social'] ?? null,
                'nome_fantasia' => $_POST['nome_fantasia'] ?? null,
                'cnpj_cpf' => $_POST['cnpj_cpf'] ?? null,
                'endereco' => $_POST['endereco'] ?? '',
                'numero' => $_POST['numero'] ?? '',
                'complemento' => $_POST['complemento'] ?? null,
                'bairro' => $_POST['bairro'] ?? '',
                'cidade' => $_POST['cidade'] ?? '',
                'uf' => $_POST['uf'] ?? '',
                'cep' => $_POST['cep'] ?? '',
                'telefone' => $_POST['telefone'] ?? '',
                'email' => $_POST['email'] ?? null,
            ];

            // Validar dados do cliente (apenas para novos clientes)
            validarCliente($dados_cliente);

            // Inserir novo cliente
            $stmt = $pdo->prepare("INSERT INTO clientes (
                tipo_cliente, nome, razao_social, nome_fantasia, cnpj_cpf,
                endereco, numero, complemento, bairro, cidade, uf, cep, telefone, email
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            
            $stmt->execute([
                $dados_cliente['tipo_cliente'],
                $dados_cliente['nome'],
                $dados_cliente['razao_social'],
                $dados_cliente['nome_fantasia'],
                $dados_cliente['cnpj_cpf'],
                $dados_cliente['endereco'],
                $dados_cliente['numero'],
                $dados_cliente['complemento'],
                $dados_cliente['bairro'],
                $dados_cliente['cidade'],
                $dados_cliente['uf'],
                $dados_cliente['cep'],
                $dados_cliente['telefone'],
                $dados_cliente['email']
            ]);

            $cliente_id = $pdo->lastInsertId();
            $cliente = $dados_cliente; // Dados do novo cliente
        }

        // Preparar os dados do orçamento
        $dados = [
            'tipo_cliente' => $cliente['tipo_cliente'],
            'nome' => $cliente['nome'],
            'razao_social' => $cliente['razao_social'],
            'nome_fantasia' => $cliente['nome_fantasia'],
            'cnpj_cpf' => $cliente['cnpj_cpf'],
            'endereco' => $cliente['endereco'],
            'numero' => $cliente['numero'],
            'complemento' => $cliente['complemento'],
            'bairro' => $cliente['bairro'],
            'cidade' => $cliente['cidade'],
            'uf' => $cliente['uf'],
            'cep' => $cliente['cep'],
            'telefone' => $cliente['telefone'],
            'email' => $cliente['email'],
            'forma_pagamento' => $_POST['forma_pagamento'] ?? 'A Combinar',
            'observacoes' => htmlspecialchars($_POST['observacoes'] ?? '', ENT_QUOTES, 'UTF-8'),
            'data_orcamento' => date('Y-m-d'),
            'data_vencimento' => date('Y-m-d', strtotime('+30 days')),
            'aos_cuidados' => htmlspecialchars($_POST['aos_cuidados'] ?? 'Não especificado', ENT_QUOTES, 'UTF-8'),
            'descricao_servicos' => htmlspecialchars($_POST['descricao_servicos'] ?? '', ENT_QUOTES, 'UTF-8'),
            'informacoes_extras' => htmlspecialchars($_POST['informacoes_extras'] ?? '', ENT_QUOTES, 'UTF-8'),
            'fotos' => []
        ];

        // Processar upload de fotos
        $fotos = [];
        if (!empty($_FILES['fotos']['name'][0])) {
            $uploadDir = 'Uploads/orcamentos/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $fileCount = count($_FILES['fotos']['name']);
            if ($fileCount > 5) {
                throw new Exception('Máximo de 5 fotos permitidas');
            }

            for ($i = 0; $i < $fileCount; $i++) {
                $fileName = $_FILES['fotos']['name'][$i];
                $fileTmp = $_FILES['fotos']['tmp_name'][$i];
                $fileSize = $_FILES['fotos']['size'][$i];
                $fileError = $_FILES['fotos']['error'][$i];
                
                $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'gif'];

                if (in_array($fileExt, $allowed)) {
                    if ($fileError === 0) {
                        if ($fileSize <= 5242880) { // 5MB
                            $newFileName = uniqid('', true) . '.' . $fileExt;
                            $fileDest = $uploadDir . $newFileName;
                            
                            if (move_uploaded_file($fileTmp, $fileDest)) {
                                $fotos[] = $fileDest;
                            }
                        } else {
                            throw new Exception("Tamanho do arquivo {$fileName} excede 5MB.");
                        }
                    } else {
                        throw new Exception("Erro ao carregar o arquivo {$fileName}.");
                    }
                } else {
                    throw new Exception("Formato inválido para o arquivo {$fileName}.");
                }
            }
            $dados['fotos'] = $fotos;
        }

        // Processar serviços e criar novos itens se necessário
        $servicos = [];
        $valor_total = 0;
        if (!isset($_POST['servicos']) || empty($_POST['servicos'])) {
            throw new Exception("Nenhum serviço foi adicionado ao orçamento.");
        }
        foreach ($_POST['servicos'] as $index => $servico) {
            if (strpos($servico['id_item'], 'temp-') === 0 && !empty($servico['nome'])) {
                $stmt = $pdo->prepare("INSERT INTO itens (nome, valor_unitario_padrao) VALUES (?, ?)");
                $stmt->execute([$servico['nome'], $servico['valor_unitario']]);
                $id_item = $pdo->lastInsertId();
            } else {
                $id_item = $servico['id_item'];
            }
            
            $quantidade = floatval($servico['quantidade']);
            $valor_unitario = floatval($servico['valor_unitario']);
            $subtotal = $quantidade * $valor_unitario;
            $valor_total += $subtotal;

            $servicos[] = [
                'id_item' => $id_item,
                'nome' => $servico['nome'],
                'quantidade' => $quantidade,
                'valor_unitario' => $valor_unitario
            ];
        }
        $dados['servicos'] = $servicos;

        // Gerar número do orçamento
        $numero_orcamento = gerarNumeroOrcamento($pdo);

        // Inserir orçamento
        $stmt = $pdo->prepare("INSERT INTO orcamentos (
            cliente_id, numero_orcamento, validade, forma_pagamento, 
            descricao_servico, observacao, descricao_servicos, informacoes_extras, total, status, fotos
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        
        $stmt->execute([
            $cliente_id,
            $numero_orcamento,
            $dados['data_vencimento'],
            $dados['forma_pagamento'],
            json_encode($dados['servicos'], JSON_UNESCAPED_UNICODE),
            $dados['observacoes'],
            $dados['descricao_servicos'],
            $dados['informacoes_extras'],
            $valor_total,
            'Agendado',
            json_encode($dados['fotos'])
        ]);

        $orcamento_id = $pdo->lastInsertId();

        $pdo->commit(); // Confirmar transação

        if (isset($_POST['gerar_pdf'])) {
            require_once 'vendor/autoload.php';
            $dompdf = new Dompdf\Dompdf();
            $dompdf->setOptions(['isHtml5ParserEnabled' => true, 'isRemoteEnabled' => true]);
            $html = gerarTemplatePDF($dados, $cliente_id, $numero_orcamento, $valor_total);
            
            // Extrair número do orçamento (ex.: "048" de "048/2025")
            $numero_orcamento_file = $numero_orcamento;
            if (preg_match('/^(\d+)/', $numero_orcamento, $matches)) {
                $numero_orcamento_file = $matches[1];
            }
            // Sanitizar nome do cliente
            $cliente_nome = preg_replace('/[^A-Za-z0-9\-_]/', '', str_replace(' ', '_', $cliente['nome']));
            $cliente_nome = substr($cliente_nome, 0, 50);
            $pdf_filename = "Orcamento_{$numero_orcamento_file}_{$cliente_nome}.pdf";

            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $dompdf->stream($pdf_filename, ['Attachment' => false]);
            exit();
        } else {
            $success = "Orçamento salvo com sucesso!";
            header("Location: lista_orcamentos.php?saved=1");
            exit();
        }
        
    } catch (Exception $e) {
        $pdo->rollBack(); // Reverter transação
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Orçamento - Ultra Multiservice</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ===== ULTRA BUDGET CREATOR - MODERN DESIGN SYSTEM ===== */
        :root {
            /* Paleta de Cores Principal */
            --ultra-primary: #1E40AF;
            --ultra-primary-light: #3B82F6;
            --ultra-primary-dark: #1E3A8A;
            --ultra-secondary: #059669;
            --ultra-secondary-light: #10B981;
            --ultra-accent: #EA580C;
            --ultra-accent-light: #F97316;
            --ultra-neutral: #6B7280;
            --ultra-neutral-light: #9CA3AF;
            --ultra-neutral-dark: #374151;
            --ultra-background: #F8FAFC;
            --ultra-surface: #FFFFFF;
            --ultra-border: #E5E7EB;
            --ultra-text-primary: #111827;
            --ultra-text-secondary: #6B7280;
            --ultra-success: #059669;
            --ultra-warning: #D97706;
            --ultra-error: #DC2626;
            
            /* Sombras */
            --ultra-shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --ultra-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --ultra-shadow-md: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --ultra-shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --ultra-shadow-xl: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            
            /* Transições */
            --ultra-transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --ultra-transition-fast: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
            
            /* Bordas */
            --ultra-radius: 8px;
            --ultra-radius-lg: 12px;
            --ultra-radius-xl: 16px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--ultra-background);
            color: var(--ultra-text-primary);
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }

        /* ===== LAYOUT PRINCIPAL ===== */
        .ultra-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .ultra-main-layout {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 2rem;
            min-height: 100vh;
            padding: 2rem 0;
        }

        /* ===== HEADER INTELIGENTE ===== */
        .ultra-header {
            background: linear-gradient(135deg, var(--ultra-primary) 0%, var(--ultra-primary-light) 100%);
            color: white;
            padding: 1.5rem 2rem;
            border-radius: var(--ultra-radius-lg);
            margin-bottom: 2rem;
            box-shadow: var(--ultra-shadow-lg);
            position: relative;
            overflow: hidden;
        }

        .ultra-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: translate(50%, -50%);
        }

        .ultra-header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            z-index: 1;
        }

        .ultra-header h1 {
            font-family: 'Poppins', sans-serif;
            font-size: 1.75rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .ultra-header-actions {
            display: flex;
            gap: 0.75rem;
        }

        .ultra-btn-header {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: var(--ultra-radius);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: var(--ultra-transition);
            backdrop-filter: blur(10px);
        }

        .ultra-btn-header:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
            transform: translateY(-1px);
        }

        /* ===== WIZARD DE PROGRESSO ===== */
        .ultra-wizard {
            background: var(--ultra-surface);
            border-radius: var(--ultra-radius-lg);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--ultra-shadow);
        }

        .ultra-progress-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            position: relative;
        }

        .ultra-progress-bar::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--ultra-border);
            z-index: 1;
        }

        .ultra-progress-line {
            position: absolute;
            top: 50%;
            left: 0;
            height: 2px;
            background: linear-gradient(90deg, var(--ultra-primary), var(--ultra-secondary));
            z-index: 2;
            transition: width 0.5s ease;
            transform: translateY(-50%);
        }

        .ultra-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            position: relative;
            z-index: 3;
            cursor: pointer;
            transition: var(--ultra-transition);
        }

        .ultra-step-circle {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.875rem;
            transition: var(--ultra-transition);
            border: 2px solid var(--ultra-border);
            background: var(--ultra-surface);
        }

        .ultra-step.completed .ultra-step-circle {
            background: var(--ultra-secondary);
            border-color: var(--ultra-secondary);
            color: white;
        }

        .ultra-step.active .ultra-step-circle {
            background: var(--ultra-primary);
            border-color: var(--ultra-primary);
            color: white;
            box-shadow: 0 0 0 4px rgba(30, 64, 175, 0.2);
        }

        .ultra-step-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--ultra-text-secondary);
            text-align: center;
            transition: var(--ultra-transition);
        }

        .ultra-step.active .ultra-step-label,
        .ultra-step.completed .ultra-step-label {
            color: var(--ultra-text-primary);
        }

        /* ===== CONTEÚDO PRINCIPAL ===== */
        .ultra-content {
            background: var(--ultra-surface);
            border-radius: var(--ultra-radius-lg);
            padding: 2rem;
            box-shadow: var(--ultra-shadow);
            min-height: 600px;
        }

        .ultra-step-content {
            display: none;
            animation: fadeInUp 0.5s ease;
        }

        .ultra-step-content.active {
            display: block;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .ultra-section-title {
            font-family: 'Poppins', sans-serif;
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--ultra-text-primary);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .ultra-section-title i {
            color: var(--ultra-primary);
        }

        /* ===== FORMULÁRIOS ===== */
        .ultra-form-group {
            margin-bottom: 1.5rem;
        }

        .ultra-form-label {
            display: block;
            font-weight: 500;
            color: var(--ultra-text-primary);
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }

        .ultra-form-label.required::after {
            content: '*';
            color: var(--ultra-error);
            margin-left: 0.25rem;
        }

        .ultra-form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--ultra-border);
            border-radius: var(--ultra-radius);
            font-size: 0.875rem;
            transition: var(--ultra-transition);
            background: var(--ultra-surface);
        }

        .ultra-form-control:focus {
            outline: none;
            border-color: var(--ultra-primary);
            box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
        }

        .ultra-form-control.is-valid {
            border-color: var(--ultra-success);
            background-color: rgba(5, 150, 105, 0.05);
        }

        .ultra-form-control.is-invalid {
            border-color: var(--ultra-error);
            background-color: rgba(220, 38, 38, 0.05);
        }

        .ultra-form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }

        /* ===== TOGGLE CLIENTE ===== */
        .ultra-client-toggle {
            display: flex;
            background: var(--ultra-background);
            border-radius: var(--ultra-radius);
            padding: 0.25rem;
            margin-bottom: 2rem;
        }

        .ultra-toggle-btn {
            flex: 1;
            padding: 0.75rem 1rem;
            border: none;
            background: transparent;
            border-radius: var(--ultra-radius);
            font-weight: 500;
            transition: var(--ultra-transition);
            cursor: pointer;
        }

        .ultra-toggle-btn.active {
            background: var(--ultra-primary);
            color: white;
            box-shadow: var(--ultra-shadow);
        }

        /* ===== BUSCA DE CLIENTE ===== */
        .ultra-client-search {
            position: relative;
        }

        .ultra-search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--ultra-surface);
            border: 1px solid var(--ultra-border);
            border-radius: var(--ultra-radius);
            box-shadow: var(--ultra-shadow-lg);
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }

        .ultra-search-item {
            padding: 0.75rem 1rem;
            cursor: pointer;
            transition: var(--ultra-transition);
            border-bottom: 1px solid var(--ultra-border);
        }

        .ultra-search-item:hover {
            background: var(--ultra-background);
        }

        .ultra-search-item:last-child {
            border-bottom: none;
        }

        /* ===== PREVIEW DO CLIENTE ===== */
        .ultra-client-preview {
            background: linear-gradient(135deg, var(--ultra-background) 0%, rgba(30, 64, 175, 0.05) 100%);
            border: 1px solid var(--ultra-border);
            border-radius: var(--ultra-radius-lg);
            padding: 1.5rem;
            margin-top: 1rem;
        }

        .ultra-client-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .ultra-info-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .ultra-info-label {
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--ultra-text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .ultra-info-value {
            font-weight: 500;
            color: var(--ultra-text-primary);
        }

        /* ===== ITENS DE SERVIÇO ===== */
        .ultra-service-item {
            background: var(--ultra-background);
            border: 1px solid var(--ultra-border);
            border-radius: var(--ultra-radius-lg);
            padding: 1.5rem;
            margin-bottom: 1rem;
            position: relative;
            transition: var(--ultra-transition);
        }

        .ultra-service-item:hover {
            box-shadow: var(--ultra-shadow);
        }

        .ultra-service-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .ultra-service-fields {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto;
            gap: 1rem;
            align-items: end;
        }

        .ultra-remove-service {
            background: var(--ultra-error);
            color: white;
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--ultra-transition);
        }

        .ultra-remove-service:hover {
            background: #B91C1C;
            transform: scale(1.1);
        }

        .ultra-add-service {
            background: var(--ultra-secondary);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: var(--ultra-radius);
            font-weight: 500;
            cursor: pointer;
            transition: var(--ultra-transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .ultra-add-service:hover {
            background: var(--ultra-secondary-light);
            transform: translateY(-1px);
        }

        /* ===== PAINEL LATERAL (RESUMO) ===== */
        .ultra-sidebar {
            position: sticky;
            top: 2rem;
            height: fit-content;
        }

        .ultra-summary {
            background: var(--ultra-surface);
            border-radius: var(--ultra-radius-lg);
            padding: 2rem;
            box-shadow: var(--ultra-shadow);
            margin-bottom: 2rem;
        }

        .ultra-summary-title {
            font-family: 'Poppins', sans-serif;
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--ultra-text-primary);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .ultra-summary-section {
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--ultra-border);
        }

        .ultra-summary-section:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .ultra-summary-label {
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--ultra-text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }

        .ultra-summary-value {
            font-weight: 500;
            color: var(--ultra-text-primary);
        }

        .ultra-total-display {
            background: linear-gradient(135deg, var(--ultra-primary) 0%, var(--ultra-primary-light) 100%);
            color: white;
            padding: 1.5rem;
            border-radius: var(--ultra-radius-lg);
            text-align: center;
            margin-bottom: 2rem;
        }

        .ultra-total-label {
            font-size: 0.875rem;
            opacity: 0.9;
            margin-bottom: 0.5rem;
        }

        .ultra-total-value {
            font-family: 'Poppins', sans-serif;
            font-size: 2rem;
            font-weight: 700;
        }

        /* ===== ULTRA ASSISTANT ===== */
        .ultra-assistant {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            z-index: 1000;
        }

        .ultra-assistant-btn {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--ultra-accent) 0%, var(--ultra-accent-light) 100%);
            color: white;
            border: none;
            box-shadow: var(--ultra-shadow-lg);
            cursor: pointer;
            transition: var(--ultra-transition);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            animation: pulse 2s infinite;
        }

        .ultra-assistant-btn:hover {
            transform: scale(1.1);
            box-shadow: var(--ultra-shadow-xl);
        }

        @keyframes pulse {
            0%, 100% { box-shadow: var(--ultra-shadow-lg), 0 0 0 0 rgba(234, 88, 12, 0.4); }
            50% { box-shadow: var(--ultra-shadow-lg), 0 0 0 10px rgba(234, 88, 12, 0); }
        }

        .ultra-assistant-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            z-index: 1001;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .ultra-assistant-content {
            background: var(--ultra-surface);
            border-radius: var(--ultra-radius-xl);
            padding: 2rem;
            max-width: 500px;
            width: 100%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: var(--ultra-shadow-xl);
        }

        /* ===== BOTÕES DE NAVEGAÇÃO ===== */
        .ultra-navigation {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--ultra-border);
        }

        .ultra-btn {
            padding: 0.75rem 1.5rem;
            border-radius: var(--ultra-radius);
            font-weight: 500;
            text-decoration: none;
            transition: var(--ultra-transition);
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .ultra-btn-primary {
            background: var(--ultra-primary);
            color: white;
        }

        .ultra-btn-primary:hover {
            background: var(--ultra-primary-dark);
            transform: translateY(-1px);
            color: white;
        }

        .ultra-btn-secondary {
            background: var(--ultra-background);
            color: var(--ultra-text-primary);
            border: 1px solid var(--ultra-border);
        }

        .ultra-btn-secondary:hover {
            background: var(--ultra-border);
            color: var(--ultra-text-primary);
        }

        .ultra-btn-success {
            background: var(--ultra-secondary);
            color: white;
        }

        .ultra-btn-success:hover {
            background: var(--ultra-secondary-light);
            color: white;
            transform: translateY(-1px);
        }

        /* ===== UPLOAD DE FOTOS ===== */
        .ultra-upload-area {
            border: 2px dashed var(--ultra-border);
            border-radius: var(--ultra-radius-lg);
            padding: 2rem;
            text-align: center;
            transition: var(--ultra-transition);
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .ultra-upload-area:hover {
            border-color: var(--ultra-primary);
            background: rgba(30, 64, 175, 0.02);
        }

        .ultra-upload-area.dragover {
            border-color: var(--ultra-primary);
            background: rgba(30, 64, 175, 0.05);
        }

        .ultra-upload-input {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .ultra-upload-content {
            pointer-events: none;
        }

        .ultra-upload-icon {
            font-size: 3rem;
            color: var(--ultra-primary);
            margin-bottom: 1rem;
        }

        .ultra-upload-text {
            color: var(--ultra-text-secondary);
            margin-bottom: 0.5rem;
        }

        .ultra-upload-hint {
            font-size: 0.875rem;
            color: var(--ultra-text-secondary);
        }

        .ultra-photo-preview {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .ultra-photo-item {
            position: relative;
            border-radius: var(--ultra-radius);
            overflow: hidden;
            aspect-ratio: 1;
        }

        .ultra-photo-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .ultra-photo-remove {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            background: var(--ultra-error);
            color: white;
            border: none;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 0.75rem;
        }

        /* ===== ALERTAS E NOTIFICAÇÕES ===== */
        .ultra-alert {
            padding: 1rem 1.5rem;
            border-radius: var(--ultra-radius);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .ultra-alert-success {
            background: rgba(5, 150, 105, 0.1);
            border: 1px solid rgba(5, 150, 105, 0.2);
            color: var(--ultra-success);
        }

        .ultra-alert-error {
            background: rgba(220, 38, 38, 0.1);
            border: 1px solid rgba(220, 38, 38, 0.2);
            color: var(--ultra-error);
        }

        /* ===== RESPONSIVIDADE ===== */
        @media (max-width: 1024px) {
            .ultra-main-layout {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .ultra-sidebar {
                position: static;
                order: -1;
            }
            
            .ultra-assistant {
                bottom: 1rem;
                right: 1rem;
            }
        }

        @media (max-width: 768px) {
            .ultra-container {
                padding: 0 0.5rem;
            }
            
            .ultra-main-layout {
                padding: 1rem 0;
            }
            
            .ultra-header {
                padding: 1rem;
                margin-bottom: 1rem;
            }
            
            .ultra-header h1 {
                font-size: 1.5rem;
            }
            
            .ultra-header-content {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
            
            .ultra-wizard,
            .ultra-content,
            .ultra-summary {
                padding: 1rem;
            }
            
            .ultra-progress-bar {
                flex-wrap: wrap;
                gap: 1rem;
            }
            
            .ultra-step {
                flex: 1;
                min-width: 120px;
            }
            
            .ultra-form-row {
                grid-template-columns: 1fr;
            }
            
            .ultra-service-fields {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }
            
            .ultra-navigation {
                flex-direction: column;
                gap: 1rem;
            }
            
            .ultra-btn {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .ultra-step-circle {
                width: 40px;
                height: 40px;
                font-size: 0.75rem;
            }
            
            .ultra-step-label {
                font-size: 0.75rem;
            }
            
            .ultra-assistant-btn {
                width: 50px;
                height: 50px;
                font-size: 1.25rem;
            }
        }

        /* ===== ANIMAÇÕES ADICIONAIS ===== */
        .ultra-fade-in {
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .ultra-slide-up {
            animation: slideUp 0.5s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ===== ESTADOS DE LOADING ===== */
        .ultra-loading {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .ultra-spinner {
            width: 16px;
            height: 16px;
            border: 2px solid transparent;
            border-top: 2px solid currentColor;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* ===== MELHORIAS DE ACESSIBILIDADE ===== */
        .ultra-sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        .ultra-focus-visible:focus-visible {
            outline: 2px solid var(--ultra-primary);
            outline-offset: 2px;
        }

        /* ===== CUSTOMIZAÇÕES ESPECÍFICAS ===== */
        .ultra-client-toggle .ultra-toggle-btn:first-child.active {
            background: var(--ultra-secondary);
        }

        .ultra-client-toggle .ultra-toggle-btn:last-child.active {
            background: var(--ultra-primary);
        }

        .ultra-service-item:first-child {
            border-color: var(--ultra-primary);
            background: rgba(30, 64, 175, 0.02);
        }

        .ultra-total-display.updated {
            animation: pulse-total 0.5s ease;
        }

        @keyframes pulse-total {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.02); }
        }
    </style>
</head>
<body>
    <?php require __DIR__ . '/navbar.php'; ?>

    <div class="ultra-container">
        <!-- Header Inteligente -->
        <div class="ultra-header">
            <div class="ultra-header-content">
                <h1>
                    <i class="fas fa-file-invoice-dollar"></i>
                    Criar Novo Orçamento
                </h1>
                <div class="ultra-header-actions">
                    <button type="button" class="ultra-btn-header" onclick="saveAsDraft()">
                        <i class="fas fa-cloud-upload-alt"></i>
                        Salvar Rascunho
                    </button>
                    <a href="lista_orcamentos.php" class="ultra-btn-header">
                        <i class="fas fa-times"></i>
                        Sair
                    </a>
                </div>
            </div>
        </div>

        <!-- Layout Principal -->
        <div class="ultra-main-layout">
            <!-- Conteúdo Principal -->
            <div class="ultra-main-content">
                <!-- Wizard de Progresso -->
                <div class="ultra-wizard">
                    <div class="ultra-progress-bar">
                        <div class="ultra-progress-line" id="progressLine"></div>
                        <div class="ultra-step active" data-step="1">
                            <div class="ultra-step-circle">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="ultra-step-label">Cliente</div>
                        </div>
                        <div class="ultra-step" data-step="2">
                            <div class="ultra-step-circle">
                                <i class="fas fa-list"></i>
                            </div>
                            <div class="ultra-step-label">Serviços</div>
                        </div>
                        <div class="ultra-step" data-step="3">
                            <div class="ultra-step-circle">
                                <i class="fas fa-cog"></i>
                            </div>
                            <div class="ultra-step-label">Detalhes</div>
                        </div>
                        <div class="ultra-step" data-step="4">
                            <div class="ultra-step-circle">
                                <i class="fas fa-check"></i>
                            </div>
                            <div class="ultra-step-label">Revisão</div>
                        </div>
                    </div>
                </div>

                <!-- Alertas -->
                <?php if ($error): ?>
                    <div class="ultra-alert ultra-alert-error">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="ultra-alert ultra-alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>

                <!-- Formulário Principal -->
                <form id="orcamentoForm" method="POST" enctype="multipart/form-data">
                    <!-- Etapa 1: Informações do Cliente -->
                    <div class="ultra-content">
                        <div class="ultra-step-content active" id="step1">
                            <h2 class="ultra-section-title">
                                <i class="fas fa-user"></i>
                                Informações do Cliente
                            </h2>

                            <!-- Toggle Cliente Existente/Novo -->
                            <div class="ultra-client-toggle">
                                <button type="button" class="ultra-toggle-btn active" id="existingClientBtn">
                                    <i class="fas fa-search"></i>
                                    Cliente Existente
                                </button>
                                <button type="button" class="ultra-toggle-btn" id="newClientBtn">
                                    <i class="fas fa-user-plus"></i>
                                    Novo Cliente
                                </button>
                            </div>

                            <!-- Busca de Cliente Existente -->
                            <div id="existingClientSection">
                                <div class="ultra-form-group">
                                    <label class="ultra-form-label required">Selecionar Cliente:</label>
                                    <div class="ultra-client-search">
                                        <input type="text" class="ultra-form-control" id="clientSearch" placeholder="Digite o nome do cliente...">
                                        <div class="ultra-search-results" id="searchResults"></div>
                                    </div>
                                    <input type="hidden" name="cliente_id" id="selectedClientId">
                                </div>

                                <!-- Preview do Cliente Selecionado -->
                                <div class="ultra-client-preview" id="clientPreview" style="display: none;">
                                    <div class="ultra-client-info" id="clientInfo">
                                        <!-- Dados do cliente serão inseridos aqui via JavaScript -->
                                    </div>
                                </div>
                            </div>

                            <!-- Formulário de Novo Cliente -->
                            <div id="newClientSection" style="display: none;">
                                <div class="ultra-form-row">
                                    <div class="ultra-form-group">
                                        <label class="ultra-form-label required">Tipo de Cliente:</label>
                                        <select name="tipo_cliente" class="ultra-form-control" id="tipoCliente">
                                            <option value="Pessoa Física">Pessoa Física</option>
                                            <option value="Pessoa Jurídica">Pessoa Jurídica</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="ultra-form-row">
                                    <div class="ultra-form-group">
                                        <label class="ultra-form-label required" id="nomeLabel">Nome Completo:</label>
                                        <input type="text" name="nome" class="ultra-form-control" id="nomeCliente" placeholder="Digite o nome completo">
                                    </div>
                                    <div class="ultra-form-group">
                                        <label class="ultra-form-label" id="documentoLabel">CPF:</label>
                                        <input type="text" name="cnpj_cpf" class="ultra-form-control" id="documentoCliente" placeholder="000.000.000-00">
                                    </div>
                                </div>

                                <div class="ultra-form-row">
                                    <div class="ultra-form-group">
                                        <label class="ultra-form-label required">Telefone:</label>
                                        <input type="text" name="telefone" class="ultra-form-control" id="telefoneCliente" placeholder="(00) 00000-0000">
                                    </div>
                                    <div class="ultra-form-group">
                                        <label class="ultra-form-label">E-mail:</label>
                                        <input type="email" name="email" class="ultra-form-control" id="emailCliente" placeholder="cliente@email.com">
                                    </div>
                                </div>

                                <div class="ultra-form-row">
                                    <div class="ultra-form-group">
                                        <label class="ultra-form-label">Endereço:</label>
                                        <input type="text" name="endereco" class="ultra-form-control" id="enderecoCliente" placeholder="Rua, Avenida...">
                                    </div>
                                    <div class="ultra-form-group">
                                        <label class="ultra-form-label">Número:</label>
                                        <input type="text" name="numero" class="ultra-form-control" id="numeroCliente" placeholder="123">
                                    </div>
                                </div>

                                <div class="ultra-form-row">
                                    <div class="ultra-form-group">
                                        <label class="ultra-form-label">Bairro:</label>
                                        <input type="text" name="bairro" class="ultra-form-control" id="bairroCliente" placeholder="Nome do bairro">
                                    </div>
                                    <div class="ultra-form-group">
                                        <label class="ultra-form-label">Cidade:</label>
                                        <input type="text" name="cidade" class="ultra-form-control" id="cidadeCliente" placeholder="Nome da cidade">
                                    </div>
                                </div>

                                <div class="ultra-form-row">
                                    <div class="ultra-form-group">
                                        <label class="ultra-form-label">UF:</label>
                                        <select name="uf" class="ultra-form-control" id="ufCliente">
                                            <option value="">Selecione...</option>
                                            <option value="AC">AC</option>
                                            <option value="AL">AL</option>
                                            <option value="AP">AP</option>
                                            <option value="AM">AM</option>
                                            <option value="BA">BA</option>
                                            <option value="CE">CE</option>
                                            <option value="DF">DF</option>
                                            <option value="ES">ES</option>
                                            <option value="GO">GO</option>
                                            <option value="MA">MA</option>
                                            <option value="MT">MT</option>
                                            <option value="MS">MS</option>
                                            <option value="MG">MG</option>
                                            <option value="PA">PA</option>
                                            <option value="PB">PB</option>
                                            <option value="PR">PR</option>
                                            <option value="PE">PE</option>
                                            <option value="PI">PI</option>
                                            <option value="RJ">RJ</option>
                                            <option value="RN">RN</option>
                                            <option value="RS">RS</option>
                                            <option value="RO">RO</option>
                                            <option value="RR">RR</option>
                                            <option value="SC">SC</option>
                                            <option value="SP">SP</option>
                                            <option value="SE">SE</option>
                                            <option value="TO">TO</option>
                                        </select>
                                    </div>
                                    <div class="ultra-form-group">
                                        <label class="ultra-form-label">CEP:</label>
                                        <input type="text" name="cep" class="ultra-form-control" id="cepCliente" placeholder="00000-000">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Etapa 2: Serviços e Itens -->
                        <div class="ultra-step-content" id="step2">
                            <h2 class="ultra-section-title">
                                <i class="fas fa-list"></i>
                                Itens do Orçamento
                            </h2>

                            <div id="servicosContainer">
                                <!-- Serviços serão adicionados aqui dinamicamente -->
                            </div>

                            <button type="button" class="ultra-add-service" onclick="addService()">
                                <i class="fas fa-plus"></i>
                                Adicionar Item/Serviço
                            </button>
                        </div>

                        <!-- Etapa 3: Detalhes e Configurações -->
                        <div class="ultra-step-content" id="step3">
                            <h2 class="ultra-section-title">
                                <i class="fas fa-cog"></i>
                                Detalhes do Orçamento
                            </h2>

                            <div class="ultra-form-group">
                                <label class="ultra-form-label">Forma de Pagamento:</label>
                                <select name="forma_pagamento" class="ultra-form-control">
                                    <option value="A Combinar">A Combinar</option>
                                    <option value="À Vista">À Vista</option>
                                    <option value="Cartão de Crédito">Cartão de Crédito</option>
                                    <option value="Cartão de Débito">Cartão de Débito</option>
                                    <option value="PIX">PIX</option>
                                    <option value="Transferência Bancária">Transferência Bancária</option>
                                    <option value="Boleto Bancário">Boleto Bancário</option>
                                    <option value="Parcelado">Parcelado</option>
                                </select>
                            </div>

                            <div class="ultra-form-group">
                                <label class="ultra-form-label">Descrição Detalhada dos Serviços:</label>
                                <textarea name="descricao_servicos" class="ultra-form-control" rows="4" placeholder="Descreva detalhadamente os serviços que serão executados..."></textarea>
                            </div>

                            <div class="ultra-form-group">
                                <label class="ultra-form-label">Observações Adicionais:</label>
                                <textarea name="observacoes" class="ultra-form-control" rows="3" placeholder="Observações, condições especiais, prazos..."></textarea>
                            </div>

                            <div class="ultra-form-group">
                                <label class="ultra-form-label">Informações Extras:</label>
                                <textarea name="informacoes_extras" class="ultra-form-control" rows="3" placeholder="Informações adicionais relevantes..."></textarea>
                            </div>

                            <div class="ultra-form-group">
                                <label class="ultra-form-label">Fotos (máximo 5):</label>
                                <div class="ultra-upload-area" id="uploadArea">
                                    <input type="file" name="fotos[]" multiple accept="image/*" class="ultra-upload-input" id="fotosInput">
                                    <div class="ultra-upload-content">
                                        <div class="ultra-upload-icon">
                                            <i class="fas fa-cloud-upload-alt"></i>
                                        </div>
                                        <div class="ultra-upload-text">Clique ou arraste fotos aqui</div>
                                        <div class="ultra-upload-hint">PNG, JPG, GIF até 5MB cada</div>
                                    </div>
                                </div>
                                <div class="ultra-photo-preview" id="photoPreview"></div>
                            </div>
                        </div>

                        <!-- Etapa 4: Revisão e Finalização -->
                        <div class="ultra-step-content" id="step4">
                            <h2 class="ultra-section-title">
                                <i class="fas fa-check"></i>
                                Revisão do Orçamento
                            </h2>

                            <div id="reviewContent">
                                <!-- Conteúdo da revisão será gerado dinamicamente -->
                            </div>
                        </div>

                        <!-- Navegação -->
                        <div class="ultra-navigation">
                            <button type="button" class="ultra-btn ultra-btn-secondary" id="prevBtn" onclick="previousStep()" style="display: none;">
                                <i class="fas fa-arrow-left"></i>
                                Voltar
                            </button>
                            <div></div>
                            <button type="button" class="ultra-btn ultra-btn-primary" id="nextBtn" onclick="nextStep()">
                                Próximo
                                <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Painel Lateral (Resumo) -->
            <div class="ultra-sidebar">
                <div class="ultra-summary">
                    <h3 class="ultra-summary-title">
                        <i class="fas fa-file-invoice"></i>
                        Resumo do Orçamento
                    </h3>

                    <div class="ultra-summary-section">
                        <div class="ultra-summary-label">Cliente:</div>
                        <div class="ultra-summary-value" id="summaryClient">Nenhum cliente selecionado</div>
                    </div>

                    <div class="ultra-summary-section">
                        <div class="ultra-summary-label">Serviços/Itens: (<span id="summaryItemCount">0</span>)</div>
                        <div id="summaryItems">
                            <div style="color: var(--ultra-text-secondary); font-style: italic;">Nenhum item adicionado.</div>
                        </div>
                    </div>
                </div>

                <div class="ultra-total-display" id="totalDisplay">
                    <div class="ultra-total-label">Total Estimado:</div>
                    <div class="ultra-total-value" id="totalValue">R$ 0,00</div>
                </div>

                <!-- Ultra Assistant -->
                <div class="ultra-assistant">
                    <button type="button" class="ultra-assistant-btn" onclick="toggleAssistant()">
                        <i class="fas fa-robot"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal do Ultra Assistant -->
    <div class="ultra-assistant-modal" id="assistantModal">
        <div class="ultra-assistant-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h3 style="margin: 0; color: var(--ultra-primary);">
                    <i class="fas fa-robot"></i>
                    Ultra Assistant
                </h3>
                <button type="button" onclick="toggleAssistant()" style="background: none; border: none; font-size: 1.5rem; color: var(--ultra-text-secondary); cursor: pointer;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div id="assistantChat">
                <div style="background: var(--ultra-background); padding: 1rem; border-radius: var(--ultra-radius); margin-bottom: 1rem;">
                    <strong>🤖 Olá!</strong> Sou seu assistente de orçamentos. Como posso ajudar hoje?
                </div>
                
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1rem;">
                    <button type="button" class="ultra-btn ultra-btn-secondary" style="font-size: 0.875rem; padding: 0.5rem 1rem;" onclick="assistantSuggestServices()">
                        💡 Sugerir Serviços
                    </button>
                    <button type="button" class="ultra-btn ultra-btn-secondary" style="font-size: 0.875rem; padding: 0.5rem 1rem;" onclick="assistantValidatePrices()">
                        💰 Validar Preços
                    </button>
                    <button type="button" class="ultra-btn ultra-btn-secondary" style="font-size: 0.875rem; padding: 0.5rem 1rem;" onclick="assistantOptimize()">
                        🚀 Otimizar Orçamento
                    </button>
                </div>
                
                <div style="display: flex; gap: 0.5rem;">
                    <input type="text" placeholder="Pergunte algo..." style="flex: 1; padding: 0.75rem; border: 1px solid var(--ultra-border); border-radius: var(--ultra-radius);" id="assistantInput">
                    <button type="button" class="ultra-btn ultra-btn-primary" onclick="sendAssistantMessage()">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ===== ULTRA BUDGET CREATOR - JAVASCRIPT CONTROLLER =====
        
        // Dados globais
        let currentStep = 1;
        let selectedClient = null;
        let services = [];
        let totalValue = 0;
        
        // Dados dos clientes e itens (do PHP)
        const clientesExistentes = <?= json_encode($clientes_existentes) ?>;
        const itensExistentes = <?= json_encode($itens_existentes) ?>;
        
        // ===== INICIALIZAÇÃO =====
        document.addEventListener('DOMContentLoaded', function() {
            initializeWizard();
            initializeClientSearch();
            initializeFormValidation();
            initializeFileUpload();
            updateProgressBar();
            addService(); // Adicionar primeiro serviço automaticamente
        });
        
        // ===== WIZARD DE ETAPAS =====
        function initializeWizard() {
            updateStepVisibility();
            updateNavigationButtons();
        }
        
        function nextStep() {
            if (validateCurrentStep()) {
                if (currentStep < 4) {
                    currentStep++;
                    updateStepVisibility();
                    updateNavigationButtons();
                    updateProgressBar();
                    
                    if (currentStep === 4) {
                        generateReview();
                    }
                }
            }
        }
        
        function previousStep() {
            if (currentStep > 1) {
                currentStep--;
                updateStepVisibility();
                updateNavigationButtons();
                updateProgressBar();
            }
        }
        
        function goToStep(step) {
            if (step >= 1 && step <= 4) {
                currentStep = step;
                updateStepVisibility();
                updateNavigationButtons();
                updateProgressBar();
                
                if (currentStep === 4) {
                    generateReview();
                }
            }
        }
        
        function updateStepVisibility() {
            // Atualizar conteúdo das etapas
            document.querySelectorAll('.ultra-step-content').forEach(content => {
                content.classList.remove('active');
            });
            document.getElementById(`step${currentStep}`).classList.add('active');
            
            // Atualizar indicadores do wizard
            document.querySelectorAll('.ultra-step').forEach((step, index) => {
                step.classList.remove('active', 'completed');
                if (index + 1 < currentStep) {
                    step.classList.add('completed');
                } else if (index + 1 === currentStep) {
                    step.classList.add('active');
                }
            });
        }
        
        function updateNavigationButtons() {
            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');
            
            prevBtn.style.display = currentStep > 1 ? 'inline-flex' : 'none';
            
            if (currentStep === 4) {
                nextBtn.innerHTML = '<i class="fas fa-save"></i> Finalizar Orçamento';
                nextBtn.onclick = function() { submitForm(); };
            } else {
                nextBtn.innerHTML = 'Próximo <i class="fas fa-arrow-right"></i>';
                nextBtn.onclick = function() { nextStep(); };
            }
        }
        
        function updateProgressBar() {
            const progressLine = document.getElementById('progressLine');
            const progress = ((currentStep - 1) / 3) * 100;
            progressLine.style.width = progress + '%';
        }
        
        // ===== VALIDAÇÃO DE ETAPAS =====
        function validateCurrentStep() {
            switch (currentStep) {
                case 1:
                    return validateClientStep();
                case 2:
                    return validateServicesStep();
                case 3:
                    return validateDetailsStep();
                case 4:
                    return true;
                default:
                    return true;
            }
        }
        
        function validateClientStep() {
            const isExistingClient = document.getElementById('existingClientBtn').classList.contains('active');
            
            if (isExistingClient) {
                if (!selectedClient) {
                    showAlert('Por favor, selecione um cliente existente.', 'error');
                    return false;
                }
            } else {
                const nome = document.getElementById('nomeCliente').value.trim();
                const telefone = document.getElementById('telefoneCliente').value.trim();
                
                if (!nome) {
                    showAlert('O nome do cliente é obrigatório.', 'error');
                    return false;
                }
                
                if (!telefone) {
                    showAlert('O telefone do cliente é obrigatório.', 'error');
                    return false;
                }
            }
            
            return true;
        }
        
        function validateServicesStep() {
            if (services.length === 0) {
                showAlert('Adicione pelo menos um item/serviço ao orçamento.', 'error');
                return false;
            }
            
            for (let i = 0; i < services.length; i++) {
                const service = services[i];
                if (!service.nome || !service.quantidade || !service.valor_unitario) {
                    showAlert(`Complete todos os campos do item ${i + 1}.`, 'error');
                    return false;
                }
            }
            
            return true;
        }
        
        function validateDetailsStep() {
            return true; // Detalhes são opcionais
        }
        
        // ===== GERENCIAMENTO DE CLIENTES =====
        function initializeClientSearch() {
            const searchInput = document.getElementById('clientSearch');
            const searchResults = document.getElementById('searchResults');
            
            searchInput.addEventListener('input', function() {
                const query = this.value.toLowerCase().trim();
                
                if (query.length < 2) {
                    searchResults.style.display = 'none';
                    return;
                }
                
                const filteredClients = clientesExistentes.filter(client => 
                    client.nome.toLowerCase().includes(query) ||
                    (client.telefone && client.telefone.includes(query)) ||
                    (client.email && client.email.toLowerCase().includes(query))
                );
                
                displaySearchResults(filteredClients);
            });
            
            // Fechar resultados ao clicar fora
            document.addEventListener('click', function(e) {
                if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                    searchResults.style.display = 'none';
                }
            });
        }
        
        function displaySearchResults(clients) {
            const searchResults = document.getElementById('searchResults');
            
            if (clients.length === 0) {
                searchResults.innerHTML = '<div class="ultra-search-item">Nenhum cliente encontrado</div>';
            } else {
                searchResults.innerHTML = clients.map(client => `
                    <div class="ultra-search-item" onclick="selectClient(${client.id})">
                        <strong>${client.nome}</strong><br>
                        <small>${client.telefone || ''} ${client.email ? '• ' + client.email : ''}</small>
                    </div>
                `).join('');
            }
            
            searchResults.style.display = 'block';
        }
        
        function selectClient(clientId) {
            const client = clientesExistentes.find(c => c.id == clientId);
            if (client) {
                selectedClient = client;
                document.getElementById('selectedClientId').value = clientId;
                document.getElementById('clientSearch').value = client.nome;
                document.getElementById('searchResults').style.display = 'none';
                
                displayClientPreview(client);
                updateSummaryClient(client);
            }
        }
        
        function displayClientPreview(client) {
            const preview = document.getElementById('clientPreview');
            const info = document.getElementById('clientInfo');
            
            info.innerHTML = `
                <div class="ultra-info-item">
                    <div class="ultra-info-label">Nome</div>
                    <div class="ultra-info-value">${client.nome}</div>
                </div>
                <div class="ultra-info-item">
                    <div class="ultra-info-label">Tipo</div>
                    <div class="ultra-info-value">${client.tipo_cliente}</div>
                </div>
                <div class="ultra-info-item">
                    <div class="ultra-info-label">Telefone</div>
                    <div class="ultra-info-value">${client.telefone || 'Não informado'}</div>
                </div>
                <div class="ultra-info-item">
                    <div class="ultra-info-label">E-mail</div>
                    <div class="ultra-info-value">${client.email || 'Não informado'}</div>
                </div>
                <div class="ultra-info-item">
                    <div class="ultra-info-label">Endereço</div>
                    <div class="ultra-info-value">${client.endereco ? `${client.endereco}, ${client.numero || 'S/N'}` : 'Não informado'}</div>
                </div>
                <div class="ultra-info-item">
                    <div class="ultra-info-label">Cidade</div>
                    <div class="ultra-info-value">${client.cidade ? `${client.cidade}/${client.uf}` : 'Não informado'}</div>
                </div>
            `;
            
            preview.style.display = 'block';
        }
        
        function toggleClientType() {
            const existingBtn = document.getElementById('existingClientBtn');
            const newBtn = document.getElementById('newClientBtn');
            const existingSection = document.getElementById('existingClientSection');
            const newSection = document.getElementById('newClientSection');
            
            existingBtn.addEventListener('click', function() {
                existingBtn.classList.add('active');
                newBtn.classList.remove('active');
                existingSection.style.display = 'block';
                newSection.style.display = 'none';
                selectedClient = null;
                document.getElementById('selectedClientId').value = '';
            });
            
            newBtn.addEventListener('click', function() {
                newBtn.classList.add('active');
                existingBtn.classList.remove('active');
                newSection.style.display = 'block';
                existingSection.style.display = 'none';
                selectedClient = null;
                document.getElementById('clientPreview').style.display = 'none';
            });
        }
        
        // Inicializar toggle de cliente
        document.addEventListener('DOMContentLoaded', function() {
            toggleClientType();
        });
        
        // ===== GERENCIAMENTO DE SERVIÇOS =====
        function addService() {
            const serviceId = 'service_' + Date.now();
            const container = document.getElementById('servicosContainer');
            
            const serviceHtml = `
                <div class="ultra-service-item" id="${serviceId}">
                    <div class="ultra-service-fields">
                        <div class="ultra-form-group">
                            <label class="ultra-form-label required">Item/Serviço:</label>
                            <input type="text" class="ultra-form-control service-name" placeholder="Ex: Limpeza Pós-Obra" onchange="updateService('${serviceId}')">
                        </div>
                        <div class="ultra-form-group">
                            <label class="ultra-form-label required">Qtde:</label>
                            <input type="number" class="ultra-form-control service-quantity" value="1" min="0.01" step="0.01" onchange="updateService('${serviceId}')">
                        </div>
                        <div class="ultra-form-group">
                            <label class="ultra-form-label required">Valor Unit.:</label>
                            <input type="text" class="ultra-form-control service-price" placeholder="R$ 0,00" onchange="updateService('${serviceId}')">
                        </div>
                        <div class="ultra-form-group">
                            <button type="button" class="ultra-remove-service" onclick="removeService('${serviceId}')" title="Remover serviço">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', serviceHtml);
            
            // Aplicar máscara de dinheiro
            const priceInput = document.querySelector(`#${serviceId} .service-price`);
            applyMoneyMask(priceInput);
            
            // Adicionar serviço ao array
            services.push({
                id: serviceId,
                nome: '',
                quantidade: 1,
                valor_unitario: 0
            });
            
            updateSummary();
        }
        
        function removeService(serviceId) {
            if (services.length <= 1) {
                showAlert('Deve haver pelo menos um item no orçamento.', 'error');
                return;
            }
            
            document.getElementById(serviceId).remove();
            services = services.filter(s => s.id !== serviceId);
            updateSummary();
        }
        
        function updateService(serviceId) {
            const serviceElement = document.getElementById(serviceId);
            const nome = serviceElement.querySelector('.service-name').value;
            const quantidade = parseFloat(serviceElement.querySelector('.service-quantity').value) || 0;
            const valorStr = serviceElement.querySelector('.service-price').value;
            const valor_unitario = parseFloat(valorStr.replace(/[^\d,]/g, '').replace(',', '.')) || 0;
            
            const serviceIndex = services.findIndex(s => s.id === serviceId);
            if (serviceIndex !== -1) {
                services[serviceIndex] = {
                    id: serviceId,
                    nome: nome,
                    quantidade: quantidade,
                    valor_unitario: valor_unitario
                };
            }
            
            updateSummary();
        }
        
        // ===== RESUMO E CÁLCULOS =====
        function updateSummary() {
            updateSummaryItems();
            updateTotalValue();
        }
        
        function updateSummaryClient(client) {
            const summaryClient = document.getElementById('summaryClient');
            if (client) {
                summaryClient.textContent = client.nome;
            } else {
                summaryClient.textContent = 'Nenhum cliente selecionado';
            }
        }
        
        function updateSummaryItems() {
            const summaryItems = document.getElementById('summaryItems');
            const summaryItemCount = document.getElementById('summaryItemCount');
            
            if (services.length === 0) {
                summaryItems.innerHTML = '<div style="color: var(--ultra-text-secondary); font-style: italic;">Nenhum item adicionado.</div>';
                summaryItemCount.textContent = '0';
                return;
            }
            
            const validServices = services.filter(s => s.nome && s.quantidade > 0 && s.valor_unitario > 0);
            summaryItemCount.textContent = validServices.length;
            
            if (validServices.length === 0) {
                summaryItems.innerHTML = '<div style="color: var(--ultra-text-secondary); font-style: italic;">Nenhum item válido.</div>';
                return;
            }
            
            summaryItems.innerHTML = validServices.map(service => {
                const subtotal = service.quantidade * service.valor_unitario;
                return `
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-size: 0.875rem;">
                        <span>${service.nome}</span>
                        <span>R$ ${subtotal.toFixed(2).replace('.', ',')}</span>
                    </div>
                `;
            }).join('');
        }
        
        function updateTotalValue() {
            const validServices = services.filter(s => s.nome && s.quantidade > 0 && s.valor_unitario > 0);
            totalValue = validServices.reduce((total, service) => {
                return total + (service.quantidade * service.valor_unitario);
            }, 0);
            
            const totalDisplay = document.getElementById('totalValue');
            totalDisplay.textContent = `R$ ${totalValue.toFixed(2).replace('.', ',')}`;
            
            // Animação de atualização
            document.getElementById('totalDisplay').classList.add('updated');
            setTimeout(() => {
                document.getElementById('totalDisplay').classList.remove('updated');
            }, 500);
        }
        
        // ===== UPLOAD DE FOTOS =====
        function initializeFileUpload() {
            const uploadArea = document.getElementById('uploadArea');
            const fileInput = document.getElementById('fotosInput');
            const preview = document.getElementById('photoPreview');
            
            // Drag and drop
            uploadArea.addEventListener('dragover', function(e) {
                e.preventDefault();
                uploadArea.classList.add('dragover');
            });
            
            uploadArea.addEventListener('dragleave', function(e) {
                e.preventDefault();
                uploadArea.classList.remove('dragover');
            });
            
            uploadArea.addEventListener('drop', function(e) {
                e.preventDefault();
                uploadArea.classList.remove('dragover');
                
                const files = Array.from(e.dataTransfer.files);
                handleFileSelection(files);
            });
            
            // File input change
            fileInput.addEventListener('change', function() {
                const files = Array.from(this.files);
                handleFileSelection(files);
            });
        }
        
        function handleFileSelection(files) {
            const preview = document.getElementById('photoPreview');
            const maxFiles = 5;
            
            if (files.length > maxFiles) {
                showAlert(`Máximo de ${maxFiles} fotos permitidas.`, 'error');
                return;
            }
            
            preview.innerHTML = '';
            
            files.forEach((file, index) => {
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const photoItem = document.createElement('div');
                        photoItem.className = 'ultra-photo-item';
                        photoItem.innerHTML = `
                            <img src="${e.target.result}" alt="Preview ${index + 1}">
                            <button type="button" class="ultra-photo-remove" onclick="removePhoto(this)">
                                <i class="fas fa-times"></i>
                            </button>
                        `;
                        preview.appendChild(photoItem);
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
        
        function removePhoto(button) {
            button.parentElement.remove();
        }
        
        // ===== REVISÃO DO ORÇAMENTO =====
        function generateReview() {
            const reviewContent = document.getElementById('reviewContent');
            
            // Dados do cliente
            let clientData = '';
            if (selectedClient) {
                clientData = `
                    <h4>Cliente Selecionado:</h4>
                    <p><strong>${selectedClient.nome}</strong><br>
                    ${selectedClient.telefone || ''} ${selectedClient.email ? '• ' + selectedClient.email : ''}<br>
                    ${selectedClient.endereco ? selectedClient.endereco + ', ' + (selectedClient.numero || 'S/N') : ''}</p>
                `;
            } else {
                const nome = document.getElementById('nomeCliente').value;
                const telefone = document.getElementById('telefoneCliente').value;
                const email = document.getElementById('emailCliente').value;
                clientData = `
                    <h4>Novo Cliente:</h4>
                    <p><strong>${nome}</strong><br>
                    ${telefone} ${email ? '• ' + email : ''}</p>
                `;
            }
            
            // Itens do orçamento
            const validServices = services.filter(s => s.nome && s.quantidade > 0 && s.valor_unitario > 0);
            const itemsHtml = validServices.map(service => {
                const subtotal = service.quantidade * service.valor_unitario;
                return `
                    <tr>
                        <td>${service.nome}</td>
                        <td>${service.quantidade}</td>
                        <td>R$ ${service.valor_unitario.toFixed(2).replace('.', ',')}</td>
                        <td>R$ ${subtotal.toFixed(2).replace('.', ',')}</td>
                    </tr>
                `;
            }).join('');
            
            reviewContent.innerHTML = `
                <div style="background: var(--ultra-background); padding: 1.5rem; border-radius: var(--ultra-radius-lg); margin-bottom: 2rem;">
                    ${clientData}
                </div>
                
                <div style="background: var(--ultra-background); padding: 1.5rem; border-radius: var(--ultra-radius-lg); margin-bottom: 2rem;">
                    <h4>Itens do Orçamento:</h4>
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: var(--ultra-primary); color: white;">
                                <th style="padding: 0.75rem; text-align: left;">Item/Serviço</th>
                                <th style="padding: 0.75rem; text-align: center;">Qtde</th>
                                <th style="padding: 0.75rem; text-align: right;">Valor Unit.</th>
                                <th style="padding: 0.75rem; text-align: right;">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${itemsHtml}
                        </tbody>
                        <tfoot>
                            <tr style="background: var(--ultra-secondary); color: white; font-weight: bold;">
                                <td colspan="3" style="padding: 0.75rem; text-align: right;">TOTAL:</td>
                                <td style="padding: 0.75rem; text-align: right;">R$ ${totalValue.toFixed(2).replace('.', ',')}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                    <button type="button" class="ultra-btn ultra-btn-success" onclick="submitForm()">
                        <i class="fas fa-save"></i>
                        Salvar Orçamento
                    </button>
                    <button type="button" class="ultra-btn ultra-btn-primary" onclick="submitForm(true)">
                        <i class="fas fa-file-pdf"></i>
                        Gerar PDF
                    </button>
                </div>
            `;
        }
        
        // ===== ULTRA ASSISTANT =====
        function toggleAssistant() {
            const modal = document.getElementById('assistantModal');
            modal.style.display = modal.style.display === 'flex' ? 'none' : 'flex';
        }
        
        function assistantSuggestServices() {
            addAssistantMessage('🤖 Baseado no perfil do cliente, sugiro: Limpeza de Estofados, Enceramento de Piso.');
        }
        
        function assistantValidatePrices() {
            addAssistantMessage('💰 Preço abaixo da média. Você pode aumentar sua margem de lucro.');
        }
        
        function assistantOptimize() {
            addAssistantMessage('🚀 Baseado no perfil do cliente, sugiro: Limpeza de Estofados, Enceramento de Piso.');
        }
        
        function sendAssistantMessage() {
            const input = document.getElementById('assistantInput');
            const message = input.value.trim();
            
            if (message) {
                addAssistantMessage('🤖 Obrigado pela pergunta! Estou analisando...');
                input.value = '';
            }
        }
        
        function addAssistantMessage(message) {
            const chat = document.getElementById('assistantChat');
            const messageDiv = document.createElement('div');
            messageDiv.style.cssText = 'background: var(--ultra-background); padding: 1rem; border-radius: var(--ultra-radius); margin-bottom: 1rem;';
            messageDiv.innerHTML = message;
            
            // Inserir antes dos botões
            const buttons = chat.querySelector('div[style*="display: flex"]');
            chat.insertBefore(messageDiv, buttons);
        }
        
        // ===== UTILITÁRIOS =====
        function applyMoneyMask(input) {
            input.addEventListener('input', function() {
                let value = this.value.replace(/\D/g, '');
                value = (value / 100).toFixed(2);
                value = value.replace('.', ',');
                value = value.replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.');
                this.value = 'R$ ' + value;
            });
        }
        
        function showAlert(message, type = 'info') {
            // Implementar sistema de notificações toast
            console.log(`${type.toUpperCase()}: ${message}`);
            alert(message); // Temporário
        }
        
        function saveAsDraft() {
            showAlert('Funcionalidade de rascunho será implementada.', 'info');
        }
        
        function submitForm(generatePdf = false) {
            if (!validateCurrentStep()) {
                return;
            }
            
            // Preparar dados dos serviços
            const servicosInput = document.createElement('input');
            servicosInput.type = 'hidden';
            servicosInput.name = 'servicos';
            servicosInput.value = JSON.stringify(services.filter(s => s.nome && s.quantidade > 0 && s.valor_unitario > 0));
            
            const form = document.getElementById('orcamentoForm');
            form.appendChild(servicosInput);
            
            if (generatePdf) {
                const pdfInput = document.createElement('input');
                pdfInput.type = 'hidden';
                pdfInput.name = 'gerar_pdf';
                pdfInput.value = '1';
                form.appendChild(pdfInput);
            }
            
            form.submit();
        }
        
        function initializeFormValidation() {
            // Máscaras e validações
            const telefoneInputs = document.querySelectorAll('#telefoneCliente');
            telefoneInputs.forEach(input => {
                input.addEventListener('input', function() {
                    let value = this.value.replace(/\D/g, '');
                    if (value.length <= 11) {
                        value = value.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
                        if (value.length < 14) {
                            value = value.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
                        }
                    }
                    this.value = value;
                });
            });
            
            const cepInputs = document.querySelectorAll('#cepCliente');
            cepInputs.forEach(input => {
                input.addEventListener('input', function() {
                    let value = this.value.replace(/\D/g, '');
                    value = value.replace(/(\d{5})(\d{3})/, '$1-$2');
                    this.value = value;
                });
            });
        }
        
        // ===== EVENT LISTENERS =====
        document.addEventListener('DOMContentLoaded', function() {
            // Clique nos steps do wizard
            document.querySelectorAll('.ultra-step').forEach(step => {
                step.addEventListener('click', function() {
                    const stepNumber = parseInt(this.dataset.step);
                    if (stepNumber <= currentStep || this.classList.contains('completed')) {
                        goToStep(stepNumber);
                    }
                });
            });
            
            // Enter no input do assistant
            document.getElementById('assistantInput').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    sendAssistantMessage();
                }
            });
            
            // Fechar modal do assistant ao clicar fora
            document.getElementById('assistantModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    toggleAssistant();
                }
            });
        });
    </script>
</body>
</html>

