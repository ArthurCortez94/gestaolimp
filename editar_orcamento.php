<?php
session_start();
require_once 'config.php';

if (!isset($_GET['id'])) {
    header("Location: lista_orcamentos.php");
    exit();
}

$id = (int)$_GET['id'];

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

// Carregar dados do orçamento
try {
    $stmt = $pdo->prepare("
        SELECT o.*, c.id AS cliente_id, c.nome AS cliente_nome, c.tipo_cliente, c.cnpj_cpf, c.endereco, c.numero, 
               c.complemento, c.bairro, c.cidade, c.uf, c.cep, c.telefone, c.email 
        FROM orcamentos o 
        JOIN clientes c ON o.cliente_id = c.id 
        WHERE o.id = ?
    ");
    $stmt->execute([$id]);
    $orcamento = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$orcamento) {
        header("Location: lista_orcamentos.php");
        exit();
    }

    // Carregar serviços de descricao_servico (assumindo JSON)
    $servicos = json_decode($orcamento['descricao_servico'], true);
    if (!is_array($servicos) || empty($servicos)) {
        $servicos = [];
    }

    // Carregar fotos (assumindo JSON)
    $fotos = json_decode($orcamento['fotos'], true);
    if (!is_array($fotos)) {
        $fotos = [];
    }
} catch (PDOException $e) {
    die("Erro ao buscar orçamento: " . $e->getMessage());
}

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

// Buscar dados da empresa selecionada
$dados_empresa = [];
$empresa_id = $_SESSION['selected_empresa_id'] ?? null;
if ($empresa_id) {
    $stmt = $pdo->prepare("SELECT * FROM dados_empresa WHERE id = ?");
    $stmt->execute([$empresa_id]);
    $dados_empresa = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$dados_empresa) {
    // Se não houver empresa selecionada, usar a primeira empresa cadastrada
    $stmt = $pdo->query("SELECT * FROM dados_empresa ORDER BY id DESC LIMIT 1");
    $dados_empresa = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$dados_empresa) {
        die("Nenhum dado de empresa cadastrado. Por favor, cadastre os dados da empresa em dados_empresa.php.");
    }
}

// Processar atualização
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Verificar se um cliente existente foi selecionado ou se é um novo cliente
        if (isset($_POST['cliente_id']) && !empty($_POST['cliente_id'])) {
            // Cliente existente selecionado
            $cliente_id = (int)$_POST['cliente_id'];
            $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
            $stmt->execute([$cliente_id]);
            $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$cliente) {
                throw new Exception("Cliente não encontrado.");
            }
        } else {
            // Novo cliente
            $dados_cliente = [
                'tipo_cliente' => $_POST['tipo_cliente'],
                'nome' => $_POST['nome'],
                'razao_social' => $_POST['razao_social'] ?? null,
                'nome_fantasia' => $_POST['nome_fantasia'] ?? null,
                'cnpj_cpf' => $_POST['cnpj_cpf'] ?? null,
                'endereco' => $_POST['endereco'],
                'numero' => $_POST['numero'],
                'complemento' => $_POST['complemento'] ?? null,
                'bairro' => $_POST['bairro'],
                'cidade' => $_POST['cidade'],
                'uf' => $_POST['uf'],
                'cep' => $_POST['cep'],
                'telefone' => $_POST['telefone'],
                'email' => $_POST['email'] ?? null,
            ];

            // Validação de dados do cliente
            if (!empty($dados_cliente['email']) && !filter_var($dados_cliente['email'], FILTER_VALIDATE_EMAIL)) {
                throw new Exception("E-mail inválido.");
            }
            if (!preg_match('/^\d{5}-?\d{3}$/', str_replace('-', '', $dados_cliente['cep']))) {
                throw new Exception("CEP inválido.");
            }
            if (!preg_match('/^\(\d{2}\)\d{8,9}$/', $dados_cliente['telefone'])) {
                throw new Exception("Telefone inválido. Use o formato (XX)XXXXXXXX ou (XX)XXXXXXXXX.");
            }

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
            $cliente = $dados_cliente; // Usar os dados do novo cliente para o PDF
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
            'forma_pagamento' => $_POST['forma_pagamento'],
            'observacoes' => $_POST['observacoes'] ?? null,
            'data_orcamento' => $orcamento['data_orcamento'], // Mantém a data original
            'data_vencimento' => $_POST['data_vencimento'],
            'aos_cuidados' => $_POST['aos_cuidados'] ?? 'Não especificado',
            'descricao_servicos' => $_POST['descricao_servicos'] ?? '',
            'informacoes_extras' => $_POST['informacoes_extras'] ?? '',
            'fotos' => $fotos // Fotos existentes
        ];

        // Processar upload de novas fotos
        if (!empty($_FILES['fotos']['name'][0])) {
            $uploadDir = 'uploads/orcamentos/';
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
                                $dados['fotos'][] = $fileDest;
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
        }

        // Processar serviços e criar novos itens se necessário
        $servicos = [];
        $valor_total = 0;
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

        // Atualizar orçamento
        $stmt = $pdo->prepare("
            UPDATE orcamentos 
            SET cliente_id = ?, forma_pagamento = ?, observacao = ?, validade = ?, 
                descricao_servico = ?, descricao_servicos = ?, informacoes_extras = ?, total = ?, fotos = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $cliente_id,
            $dados['forma_pagamento'],
            $dados['observacoes'],
            $dados['data_vencimento'],
            json_encode($dados['servicos'], JSON_UNESCAPED_UNICODE),
            $dados['descricao_servicos'],
            $dados['informacoes_extras'],
            $valor_total,
            json_encode($dados['fotos']),
            $id
        ]);

        if (isset($_POST['gerar_pdf'])) {
            require_once 'vendor/autoload.php';
            $dompdf = new Dompdf\Dompdf();
            
            $dompdf->setOptions(['isHtml5ParserEnabled' => true, 'isRemoteEnabled' => true]);
            $html = gerarTemplatePDF($dados, $cliente_id, $orcamento['numero_orcamento'], $valor_total, $dados_empresa);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $dompdf->stream("Orcamento_{$id}.pdf", ['Attachment' => false]);
            exit();
        } else {
            header("Location: lista_orcamentos.php?updated=1");
            exit();
        }
    } catch (Exception $e) {
        die("Erro ao atualizar orçamento: " . $e->getMessage());
    }
}

function gerarTemplatePDF(array $dados, int $cliente_id, string $numero_orcamento, float $valor_total, array $dados_empresa): string {
    $data_geracao = date('d/m/Y H:i');
    $total_formatado = "R\$ " . number_format($valor_total, 2, ',', '.');
    $data_orcamento_formatada = date('d/m/Y', strtotime($dados['data_orcamento']));
    $data_vencimento_formatada = date('d/m/Y', strtotime($dados['data_vencimento']));

    $servicos_html = '';
    foreach ($dados['servicos'] as $index => $servico) {
        $subtotal = $servico['quantidade'] * $servico['valor_unitario'];
        $bg_color = $index % 2 == 0 ? '#f9f9f9' : '#ffffff';
        $servicos_html .= "
            <tr style='background-color: {$bg_color};'>
                <td>" . ($index + 1) . "</td>
                <td>{$servico['nome']}</td>
                <td>{$servico['quantidade']}</td>
                <td style='text-align: right;'>R\$ " . number_format($servico['valor_unitario'], 2, ',', '.') . "</td>
                <td style='text-align: right;'>R\$ " . number_format($subtotal, 2, ',', '.') . "</td>
            </tr>";
    }

    $fotos_html = '';
    if (!empty($dados['fotos'])) {
        $fotos_html .= '<h4>Fotos Anexadas</h4><div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin: 20px 0;">';
        foreach ($dados['fotos'] as $foto) {
            $fotos_html .= '<img src="' . str_replace(__DIR__ . '/', '', $foto) . '" style="width: 100%; height: 150px; object-fit: cover; border-radius: 8px;">';
        }
        $fotos_html .= '</div>';
    }

    return <<<HTML
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { 
                font-family: Helvetica, sans-serif; 
                font-size: 12px; 
                line-height: 1.5; 
                margin: 20px; 
                color: #333; 
            }
            .header { 
                background-color: #f5f6f5; 
                padding: 10px; 
                border-top: 5px solid #2c3e50; 
                margin-bottom: 25px; 
                border-radius: 0 0 8px 8px; 
            }
            .header h1 { 
                font-family: Helvetica, sans-serif; 
                font-size: 20px; 
                font-weight: bold; 
                color: #000000; 
                margin-bottom: 10px; 
                text-align: center; 
            }
            .header-table { 
                width: 100%; 
                border-collapse: collapse; 
                border: none; 
            }
            .header-table td { 
                padding: 2px 5px; 
                font-family: Helvetica, sans-serif; 
                font-size: 11px; 
                color: #000000; 
                vertical-align: top; 
                border: none; 
            }
            .header-table .left { 
                width: 50%; 
            }
            .header-table .right { 
                width: 50%; 
                text-align: right; 
            }
            h3 { 
                font-size: 18px; 
                font-weight: bold; 
                color: #2c3e50; 
                margin: 30px 0 15px; 
                border-bottom: 2px solid #2c3e50; 
                padding-bottom: 5px; 
            }
            h4 { 
                font-size: 14px; 
                font-weight: bold; 
                color: #2c3e50; 
                margin: 20px 0 10px; 
            }
            .description { 
                margin: 10px 0 15px; 
                font-size: 12px; 
            }
            table { 
                width: 100%; 
                border-collapse: collapse; 
                margin: 15px 0; 
                box-shadow: 0 2px 4px rgba(0,0,0,0.1); 
                border-radius: 5px; 
                overflow: hidden; 
            }
            th, td { 
                padding: 8px; 
                border: 1px solid #e0e0e0; 
                text-align: left; 
            }
            th { 
                background-color: #ecf0f1; 
                font-weight: bold; 
                color: #2c3e50; 
                font-size: 13px; 
            }
            .label { font-weight: bold; color: #2c3e50; }
            .total-row td { 
                border-top: 2px solid #2c3e50; 
                font-weight: bold; 
                background-color: #ecf0f1; 
            }
            .extra-info { 
                margin: 15px 0; 
                font-size: 11px; 
                color: #333; 
                border: 1px solid #e0e0e0; 
                padding: 8px; 
                border-radius: 5px; 
            }
            .signature { 
                text-align: center; 
                margin-top: 50px; 
                font-size: 11px; 
                color: #666; 
            }
            .footer { 
                position: fixed; 
                bottom: 10px; 
                left: 20px; 
                right: 20px; 
                text-align: center; 
                font-size: 10px; 
                color: #777; 
                border-top: 1px solid #e0e0e0; 
                padding-top: 5px; 
            }
            .page-number:after { content: "Página " counter(page); }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>{$dados_empresa['nome_empresa']}</h1>
            <table class="header-table">
                <tr>
                    <td class="left">
                        <p>CNPJ: {$dados_empresa['cnpj']}</p>
                        <p>Endereço: {$dados_empresa['endereco']}</p>
                        <p>Cidade: {$dados_empresa['cidade']} - CEP: {$dados_empresa['cep']}</p>
                    </td>
                    <td class="right">
                        <p>E-mail: {$dados_empresa['email']}</p>
                        <p>Vendedor: Arthur</p>
                        <p>Aos cuidados de: {$dados['aos_cuidados']}</p>
                    </td>
                </tr>
            </table>
        </div>

        <h3>ORÇAMENTO Nº {$numero_orcamento} - {$data_orcamento_formatada}</h3>

        <table>
            <tr>
                <th colspan="4">DADOS DO CLIENTE</th>
            </tr>
            <tr>
                <td class="label">Cliente:</td>
                <td>{$dados['nome']}</td>
                <td class="label">CNPJ/CPF:</td>
                <td>{$dados['cnpj_cpf']}</td>
            </tr>
            <tr>
                <td class="label">Endereço:</td>
                <td colspan="3">{$dados['endereco']}, {$dados['numero']} - {$dados['bairro']}</td>
            </tr>
            <tr>
                <td class="label">Cidade:</td>
                <td>{$dados['cidade']}/{$dados['uf']}</td>
                <td class="label">CEP:</td>
                <td>{$dados['cep']}</td>
            </tr>
        </table>

        <h4>Descrição dos Serviços</h4>
        <div class="description">{$dados['descricao_servicos']}</div>

        <table>
            <tr>
                <th>ITEM</th>
                <th>NOME</th>
                <th>QTD.</th>
                <th>VR. UNIT.</th>
                <th>SUBTOTAL</th>
            </tr>
            {$servicos_html}
            <tr class="total-row">
                <td colspan="4" style="text-align: right;">TOTAL:</td>
                <td style="text-align: right;">{$total_formatado}</td>
            </tr>
        </table>

        <table>
            <tr>
                <th>VENCIMENTO</th>
                <th>VALOR</th>
                <th>FORMA DE PAGAMENTO</th>
                <th>OBSERVAÇÃO</th>
            </tr>
            <tr>
                <td>{$data_vencimento_formatada}</td>
                <td style="text-align: right;">{$total_formatado}</td>
                <td>{$dados['forma_pagamento']}</td>
                <td>{$dados['observacoes']}</td>
            </tr>
        </table>

        {$fotos_html}

        <div class="extra-info">
            <strong>Informações Extras:</strong><br>
            {$dados['informacoes_extras']}
        </div>

        <div class="signature">
            <p>_________________________________________</p>
            <p>Assinatura do Cliente</p>
        </div>

        <div class="footer">
            <p>{$dados_empresa['nome_empresa']} | CNPJ: {$dados_empresa['cnpj']} | Gerado em: {$data_geracao}</p>
            <p class="page-number"></p>
        </div>
    </body>
    </html>
HTML;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Orçamento - UltraLimp</title>
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
        transition: transform 0.3s ease;
        margin-bottom: 1.5rem;
    }

    .dashboard-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 30px rgba(37, 99, 235, 0.15);
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
        transform: translateY(-2px);
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
        transform: translateY(-2px);
    }

    .servico-item {
        background: #f8fafc;
        border-radius: 8px;
        padding: 1rem;
        margin-bottom: 1rem;
    }

    .total-geral {
        background: var(--primary);
        color: white;
        padding: 1rem;
        border-radius: 8px;
        font-size: 1.2rem;
        margin-top: 1.5rem;
    }

    .form-label {
        font-weight: 500;
        color: var(--dark);
    }

    .upload-fotos {
        border: 2px dashed #ddd;
        border-radius: 8px;
        padding: 1rem;
        margin-bottom: 1rem;
        position: relative;
    }

    .upload-fotos input[type="file"] {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        opacity: 0;
        cursor: pointer;
    }

    .upload-preview {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
        gap: 1rem;
        margin-top: 1rem;
    }

    .upload-preview img {
        width: 100px;
        height: 100px;
        object-fit: cover;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .upload-label {
        text-align: center;
        padding: 2rem;
        color: #666;
    }

    .upload-label i {
        font-size: 2rem;
        display: block;
        margin-bottom: 0.5rem;
    }

    @media (max-width: 768px) {
        .dashboard-card {
            padding: 1rem;
        }
        
        .servico-item .col-md-2 {
            margin-top: 0.5rem;
        }
        
        .btn {
            width: 100%;
            margin-top: 0.5rem;
        }
    }

    @media (max-width: 576px) {
        .header-section h2 {
            font-size: 1.5rem;
        }
        
        .form-section h4 {
            font-size: 1.2rem;
        }
    }
    </style>
</head>
<body>
    <?php require __DIR__ . '/navbar.php'; ?>

    <div class="container-fluid">
        <!-- Cabeçalho -->
        <div class="header-section">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="mb-0"><i class="fas fa-file-invoice-dollar me-2"></i>Editar Orçamento #<?= htmlspecialchars($orcamento['numero_orcamento']) ?></h2>
                <div class="d-flex gap-2">
                    <a href="lista_orcamentos.php" class="btn btn-outline-light">
                        <i class="fas fa-list me-1"></i>Ver Todos
                    </a>
                </div>
            </div>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <!-- Seção de Fotos -->
            <div class="dashboard-card">
                <h4><i class="fas fa-camera me-2"></i>Fotos do Serviço</h4>
                <div class="upload-fotos">
                    <div class="upload-label">
                        <i class="fas fa-cloud-upload-alt"></i>
                        Clique para adicionar fotos (Máx. 5)
                    </div>
                    <input type="file" name="fotos[]" multiple accept="image/*" id="fotosInput">
                    <div class="upload-preview" id="previewContainer">
                        <?php foreach ($fotos as $foto): ?>
                            <img src="<?= htmlspecialchars($foto) ?>" style="width: 100px; height: 100px; object-fit: cover; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        <?php endforeach; ?>
                    </div>
                </div>
                <small class="text-muted">Formatos permitidos: JPG, PNG, GIF. Tamanho máximo por arquivo: 5MB</small>
            </div>

            <!-- Seção de Dados do Cliente -->
            <div class="dashboard-card">
                <h4><i class="fas fa-user-tie me-2"></i>Dados do Cliente</h4>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Selecionar Cliente Existente</label>
                        <select name="cliente_id" id="cliente_id" class="form-select" onchange="preencherDadosCliente(this)">
                            <option value="">Selecione um cliente ou edite os dados abaixo</option>
                            <option value="novo">+ Criar novo cliente</option>
                            <?php foreach ($clientes_existentes as $cliente): ?>
                                <option value="<?= $cliente['id'] ?>" 
                                        data-tipo-cliente="<?= htmlspecialchars($cliente['tipo_cliente']) ?>"
                                        data-nome="<?= htmlspecialchars($cliente['nome']) ?>"
                                        data-razao-social="<?= htmlspecialchars($cliente['razao_social'] ?? '') ?>"
                                        data-nome-fantasia="<?= htmlspecialchars($cliente['nome_fantasia'] ?? '') ?>"
                                        data-cnpj-cpf="<?= htmlspecialchars($cliente['cnpj_cpf'] ?? '') ?>"
                                        data-endereco="<?= htmlspecialchars($cliente['endereco']) ?>"
                                        data-numero="<?= htmlspecialchars($cliente['numero']) ?>"
                                        data-complemento="<?= htmlspecialchars($cliente['complemento'] ?? '') ?>"
                                        data-bairro="<?= htmlspecialchars($cliente['bairro']) ?>"
                                        data-cidade="<?= htmlspecialchars($cliente['cidade']) ?>"
                                        data-uf="<?= htmlspecialchars($cliente['uf']) ?>"
                                        data-cep="<?= htmlspecialchars($cliente['cep']) ?>"
                                        data-telefone="<?= htmlspecialchars($cliente['telefone']) ?>"
                                        data-email="<?= htmlspecialchars($cliente['email'] ?? '') ?>"
                                        <?= $cliente['id'] == $orcamento['cliente_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cliente['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"> </label>
                        <a href="cadastro.php" class="btn btn-primary w-100">Cadastrar Novo Cliente</a>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Tipo de Cliente</label>
                        <select name="tipo_cliente" id="tipo_cliente" class="form-select" required>
                            <option value="Pessoa Física" <?= $orcamento['tipo_cliente'] === 'Pessoa Física' ? 'selected' : '' ?>>Pessoa Física</option>
                            <option value="Pessoa Jurídica" <?= $orcamento['tipo_cliente'] === 'Pessoa Jurídica' ? 'selected' : '' ?>>Pessoa Jurídica</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Nome Completo/Razão Social</label>
                        <input type="text" name="nome" id="nome" class="form-control" value="<?= htmlspecialchars($orcamento['cliente_nome']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">CPF/CNPJ (Opcional)</label>
                        <input type="text" name="cnpj_cpf" id="cnpj_cpf" class="form-control" value="<?= htmlspecialchars($orcamento['cnpj_cpf']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Endereço</label>
                        <input type="text" name="endereco" id="endereco" class="form-control" value="<?= htmlspecialchars($orcamento['endereco']) ?>" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Número</label>
                        <input type="text" name="numero" id="numero" class="form-control" value="<?= htmlspecialchars($orcamento['numero']) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Complemento</label>
                        <input type="text" name="complemento" id="complemento" class="form-control" value="<?= htmlspecialchars($orcamento['complemento'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Bairro</label>
                        <input type="text" name="bairro" id="bairro" class="form-control" value="<?= htmlspecialchars($orcamento['bairro']) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">CEP</label>
                        <input type="text" name="cep" id="cep" class="form-control" value="<?= htmlspecialchars($orcamento['cep']) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Cidade</label>
                        <input type="text" name="cidade" id="cidade" class="form-control" value="<?= htmlspecialchars($orcamento['cidade']) ?>" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">UF</label>
                        <input type="text" name="uf" id="uf" class="form-control" maxlength="2" value="<?= htmlspecialchars($orcamento['uf']) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Telefone</label>
                        <input type="text" name="telefone" id="telefone" class="form-control" value="<?= htmlspecialchars($orcamento['telefone']) ?>" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">E-mail</label>
                        <input type="email" name="email" id="email" class="form-control" value="<?= htmlspecialchars($orcamento['email'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Aos cuidados de</label>
                        <input type="text" name="aos_cuidados" id="aos_cuidados" class="form-control" value="<?= htmlspecialchars($orcamento['aos_cuidados'] ?? 'Não especificado') ?>">
                    </div>
                </div>
            </div>

            <!-- Seção de Serviços -->
            <div class="dashboard-card">
                <h4><i class="fas fa-tasks me-2"></i>Serviços</h4>
                <div class="row g-3 mb-3">
                    <div class="col-md-12">
                        <label class="form-label">Descrição dos Serviços</label>
                        <textarea name="descricao_servicos" class="form-control" rows="3" placeholder="Descreva os serviços aqui"><?= htmlspecialchars($orcamento['descricao_servicos'] ?? '') ?></textarea>
                    </div>
                </div>
                <div id="servicos-container">
                    <?php if (!empty($servicos)): ?>
                        <?php foreach ($servicos as $index => $servico): ?>
                            <div class="servico-item">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <select name="servicos[<?= $index ?>][id_item]" class="form-select item-select" onchange="atualizarCampos(this)" required>
                                            <option value="">Selecione ou crie um item</option>
                                            <option value="novo">+ Criar novo item</option>
                                            <?php foreach ($itens_existentes as $item): ?>
                                                <option value="<?= $item['id'] ?>" data-valor="<?= $item['valor_unitario_padrao'] ?>" <?= $item['id'] == ($servico['id_item'] ?? '') ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($item['nome']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="text" name="servicos[<?= $index ?>][nome]" class="form-control item-nome" value="<?= htmlspecialchars($servico['nome'] ?? '') ?>" style="display: none;">
                                    </div>
                                    <div class="col-md-2">
                                        <input type="number" name="servicos[<?= $index ?>][quantidade]" class="form-control qtd" step="0.01" placeholder="Qtd." value="<?= htmlspecialchars($servico['quantidade'] ?? '') ?>" required>
                                    </div>
                                    <div class="col-md-2">
                                        <input type="number" name="servicos[<?= $index ?>][valor_unitario]" class="form-control unitario" step="0.01" placeholder="Valor Unit." value="<?= htmlspecialchars($servico['valor_unitario'] ?? '') ?>" required>
                                    </div>
                                    <div class="col-md-2">
                                        <input type="text" class="form-control total-item" readonly>
                                    </div>
                                    <div class="col-md-2">
                                        <button type="button" class="btn btn-danger w-100" onclick="removerServico(this)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="servico-item">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <select name="servicos[0][id_item]" class="form-select item-select" onchange="atualizarCampos(this)" required>
                                        <option value="">Selecione ou crie um item</option>
                                        <option value="novo">+ Criar novo item</option>
                                        <?php foreach ($itens_existentes as $item): ?>
                                            <option value="<?= $item['id'] ?>" data-valor="<?= $item['valor_unitario_padrao'] ?>">
                                                <?= htmlspecialchars($item['nome']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="text" name="servicos[0][nome]" class="form-control item-nome" style="display: none;">
                                </div>
                                <div class="col-md-2">
                                    <input type="number" name="servicos[0][quantidade]" class="form-control qtd" step="0.01" placeholder="Qtd." required>
                                </div>
                                <div class="col-md-2">
                                    <input type="number" name="servicos[0][valor_unitario]" class="form-control unitario" step="0.01" placeholder="Valor Unit." required>
                                </div>
                                <div class="col-md-2">
                                    <input type="text" class="form-control total-item" readonly>
                                </div>
                                <div class="col-md-2">
                                    <button type="button" class="btn btn-danger w-100" onclick="removerServico(this)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="total-geral">
                    Total Geral: R$ <span id="total-geral"><?= number_format($orcamento['total'], 2, ',', '.') ?></span>
                </div>
                
                <button type="button" class="btn btn-primary mt-3" onclick="adicionarServico()">
                    <i class="fas fa-plus me-2"></i>Adicionar Serviço
                </button>
            </div>

            <!-- Seção de Pagamento -->
            <div class="dashboard-card">
                <h4><i class="fas fa-credit-card me-2"></i>Pagamento</h4>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Forma de Pagamento</label>
                        <select name="forma_pagamento" class="form-select" required>
                            <option value="A Combinar" <?= $orcamento['forma_pagamento'] === 'A Combinar' ? 'selected' : '' ?>>A Combinar</option>
                            <option value="PIX" <?= $orcamento['forma_pagamento'] === 'PIX' ? 'selected' : '' ?>>PIX</option>
                            <option value="Cartão" <?= $orcamento['forma_pagamento'] === 'Cartão' ? 'selected' : '' ?>>Cartão</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Validade</label>
                        <input type="date" name="data_vencimento" class="form-control" value="<?= htmlspecialchars($orcamento['validade']) ?>" required>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Observações</label>
                        <textarea name="observacoes" class="form-control" rows="2"><?= htmlspecialchars($orcamento['observacao'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Seção de Informações Extras -->
            <div class="dashboard-card">
                <h4><i class="fas fa-info-circle me-2"></i>Informações Extras</h4>
                <div class="row g-3">
                    <div class="col-md-12">
                        <label class="form-label">Informações Adicionais</label>
                        <textarea name="informacoes_extras" class="form-control" rows="3" placeholder="Insira informações extras aqui"><?= htmlspecialchars($orcamento['informacoes_extras'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Botões de Ação -->
            <div class="d-flex justify-content-end gap-3 mb-4">
                <button type="submit" name="salvar" class="btn btn-primary btn-lg">
                    <i class="fas fa-save me-2"></i>Salvar
                </button>
                <button type="submit" name="gerar_pdf" class="btn btn-success btn-lg">
                    <i class="fas fa-file-pdf me-2"></i>Gerar PDF
                </button>
            </div>
        </form>
    </div>

    <!-- Modal para criar novo item -->
    <div class="modal fade" id="novoItemModal" tabindex="-1" aria-labelledby="novoItemModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="novoItemModalLabel">Criar Novo Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="novoItemNome" class="form-label">Nome do Item</label>
                        <input type="text" class="form-control" id="novoItemNome" required>
                    </div>
                    <div class="mb-3">
                        <label for="novoItemValor" class="form-label">Valor Unitário Padrão</label>
                        <input type="number" class="form-control" id="novoItemValor" step="0.01" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="salvarNovoItem">Salvar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/cleave.js@1.6.0/dist/cleave.min.js"></script>
    <script>
    // Função para preencher os campos do cliente ao selecionar um cliente existente
    function preencherDadosCliente(select) {
        const clienteId = select.value;
        if (clienteId === 'novo') {
            // Limpar os campos para criar um novo cliente
            document.getElementById('tipo_cliente').value = 'Pessoa Física';
            document.getElementById('nome').value = '';
            document.getElementById('cnpj_cpf').value = '';
            document.getElementById('endereco').value = '';
            document.getElementById('numero').value = '';
            document.getElementById('complemento').value = '';
            document.getElementById('bairro').value = '';
            document.getElementById('cidade').value = '';
            document.getElementById('uf').value = '';
            document.getElementById('cep').value = '';
            document.getElementById('telefone').value = '';
            document.getElementById('email').value = '';
            return;
        }

        const selectedOption = select.options[select.selectedIndex];
        if (selectedOption) {
            document.getElementById('tipo_cliente').value = selectedOption.getAttribute('data-tipo-cliente') || '';
            document.getElementById('nome').value = selectedOption.getAttribute('data-nome') || '';
            document.getElementById('cnpj_cpf').value = selectedOption.getAttribute('data-cnpj-cpf') || '';
            document.getElementById('endereco').value = selectedOption.getAttribute('data-endereco') || '';
            document.getElementById('numero').value = selectedOption.getAttribute('data-numero') || '';
            document.getElementById('complemento').value = selectedOption.getAttribute('data-complemento') || '';
            document.getElementById('bairro').value = selectedOption.getAttribute('data-bairro') || '';
            document.getElementById('cidade').value = selectedOption.getAttribute('data-cidade') || '';
            document.getElementById('uf').value = selectedOption.getAttribute('data-uf') || '';
            document.getElementById('cep').value = selectedOption.getAttribute('data-cep') || '';
            document.getElementById('telefone').value = selectedOption.getAttribute('data-telefone') || '';
            document.getElementById('email').value = selectedOption.getAttribute('data-email') || '';
        }
    }

    // Auto-completar CEP
    document.getElementById('cep').addEventListener('blur', function() {
        const cep = this.value.replace(/\D/g, '');
        if (cep.length === 8) {
            fetch(`https://viacep.com.br/ws/${cep}/json/`)
                .then(response => response.json())
                .then(data => {
                    if (!data.erro) {
                        document.getElementById('endereco').value = data.logradouro;
                        document.getElementById('bairro').value = data.bairro;
                        document.getElementById('cidade').value = data.localidade;
                        document.getElementById('uf').value = data.uf;
                    }
                });
        }
    });

    // Auto-completar CNPJ
    document.getElementById('cnpj_cpf').addEventListener('blur', function() {
        const cnpj = this.value.replace(/\D/g, '');
        const tipoCliente = document.getElementById('tipo_cliente').value;

        // Verificar se é um CNPJ (14 dígitos) e se o tipo de cliente é "Pessoa Jurídica"
        if (cnpj.length === 14 && tipoCliente === 'Pessoa Jurídica') {
            fetch(`https://brasilapi.com.br/api/cnpj/v1/${cnpj}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Erro ao consultar o CNPJ: ' + response.statusText);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data && data.cnpj) {
                        // Preencher os campos com os dados retornados
                        document.getElementById('nome').value = data.razao_social || '';
                        document.getElementById('razao_social').value = data.razao_social || '';
                        document.getElementById('nome_fantasia').value = data.nome_fantasia || '';
                        document.getElementById('endereco').value = data.logradouro || '';
                        document.getElementById('numero').value = data.numero || '';
                        document.getElementById('complemento').value = data.complemento || '';
                        document.getElementById('bairro').value = data.bairro || '';
                        document.getElementById('cidade').value = data.municipio || '';
                        document.getElementById('uf').value = data.uf || '';
                        document.getElementById('cep').value = data.cep ? data.cep.replace(/\D/g, '') : '';
                        // A BrasilAPI não retorna telefone e e-mail, então deixamos os campos como estão
                    } else {
                        alert('CNPJ inválido ou não encontrado.');
                    }
                })
                .catch(error => {
                    console.error('Erro ao consultar CNPJ:', error);
                    alert('Erro ao consultar o CNPJ. Verifique sua conexão ou tente novamente mais tarde.');
                });
        }
    });

    // Máscaras com Cleave.js
    new Cleave('.form-control[name="cnpj_cpf"]', { blocks: [14], delimiter: '', numericOnly: true });
    new Cleave('.form-control[name="telefone"]', { blocks: [0, 2, 9], delimiters: ['(', ')', ''] });
    new Cleave('.form-control[name="cep"]', { blocks: [5, 3], delimiters: ['-'] });

    // Controle de Serviços
    let servicoCount = <?= count($servicos) ?: 1 ?>;
    let currentSelect = null;
    let tempIdCounter = 0;

    const itensDinamicos = [
        <?php foreach ($itens_existentes as $item): ?>
            { id: '<?= $item['id'] ?>', nome: '<?= addslashes($item['nome']) ?>', valor: '<?= $item['valor_unitario_padrao'] ?>' },
        <?php endforeach; ?>
    ];

    function atualizarCampos(select) {
        const servicoItem = select.closest('.servico-item');
        const inputNome = servicoItem.querySelector('.item-nome');
        const inputUnitario = servicoItem.querySelector('.unitario');
        
        if (select.value === 'novo') {
            currentSelect = select;
            const modal = new bootstrap.Modal(document.getElementById('novoItemModal'));
            modal.show();
            select.value = '';
        } else {
            const selectedOption = select.options[select.selectedIndex];
            const valorPadrao = selectedOption.getAttribute('data-valor');
            inputUnitario.value = valorPadrao || '';
            inputNome.value = selectedOption.text.trim();
            calcularTotal();
        }
    }

    function adicionarItemDinamico(nome, valor) {
        const tempId = `temp-${tempIdCounter++}`;
        itensDinamicos.push({ id: tempId, nome: nome, valor: valor });
        
        document.querySelectorAll('.item-select').forEach(select => {
            const existingOption = select.querySelector(`option[value="${tempId}"]`);
            if (!existingOption) {
                const option = document.createElement('option');
                option.value = tempId;
                option.setAttribute('data-valor', valor);
                option.textContent = nome;
                select.appendChild(option);
            }
        });
        
        return tempId;
    }

    document.getElementById('salvarNovoItem').addEventListener('click', function() {
        const nome = document.getElementById('novoItemNome').value;
        const valor = document.getElementById('novoItemValor').value;

        if (nome && valor) {
            const tempId = adicionarItemDinamico(nome, valor);
            const servicoItem = currentSelect.closest('.servico-item');
            
            currentSelect.value = tempId;
            servicoItem.querySelector('.item-nome').value = nome;
            servicoItem.querySelector('.unitario').value = valor;
            calcularTotal();

            const modal = bootstrap.Modal.getInstance(document.getElementById('novoItemModal'));
            modal.hide();
            document.getElementById('novoItemNome').value = '';
            document.getElementById('novoItemValor').value = '';
        } else {
            alert('Por favor, preencha todos os campos.');
        }
    });

    function calcularTotal() {
        let totalGeral = 0;
        
        document.querySelectorAll('.servico-item').forEach(item => {
            const qtd = parseFloat(item.querySelector('.qtd').value) || 0;
            const unitario = parseFloat(item.querySelector('.unitario').value) || 0;
            const total = qtd * unitario;
            
            item.querySelector('.total-item').value = total.toLocaleString('pt-BR', {
                style: 'currency',
                currency: 'BRL'
            });
            
            totalGeral += total;
        });

        document.getElementById('total-geral').textContent = totalGeral.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function adicionarServico() {
        let selectOptions = `
            <option value="">Selecione ou crie um item</option>
            <option value="novo">+ Criar novo item</option>`;
        
        itensDinamicos.forEach(item => {
            selectOptions += `
                <option value="${item.id}" data-valor="${item.valor}">
                    ${item.nome}
                </option>`;
        });

        const html = `
        <div class="servico-item">
            <div class="row g-3">
                <div class="col-md-4">
                    <select name="servicos[${servicoCount}][id_item]" class="form-select item-select" onchange="atualizarCampos(this)" required>
                        ${selectOptions}
                    </select>
                    <input type="text" name="servicos[${servicoCount}][nome]" class="form-control item-nome" style="display: none;">
                </div>
                <div class="col-md-2">
                    <input type="number" name="servicos[${servicoCount}][quantidade]" class="form-control qtd" step="0.01" placeholder="Qtd." required>
                </div>
                <div class="col-md-2">
                    <input type="number" name="servicos[${servicoCount}][valor_unitario]" class="form-control unitario" step="0.01" placeholder="Valor Unit." required>
                </div>
                <div class="col-md-2">
                    <input type="text" class="form-control total-item" readonly>
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-danger w-100" onclick="removerServico(this)">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>`;

        document.getElementById('servicos-container').insertAdjacentHTML('beforeend', html);
        
        const newItem = document.querySelector('#servicos-container').lastElementChild;
        newItem.querySelector('.qtd').addEventListener('input', calcularTotal);
        newItem.querySelector('.unitario').addEventListener('input', calcularTotal);
        newItem.querySelector('.item-select').addEventListener('change', function() { atualizarCampos(this); });
        
        servicoCount++;
        calcularTotal();
    }

    function removerServico(btn) {
        btn.closest('.servico-item').remove();
        calcularTotal();
    }

    // Script para preview das fotos
    const fotoInput = document.getElementById('fotosInput');
    const previewContainer = document.getElementById('previewContainer');

    fotoInput.addEventListener('change', function() {
        previewContainer.innerHTML = '';
        const files = Array.from(this.files).slice(0, 5);
        
        files.forEach((file, index) => {
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                
                reader.onload = (e) => {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.title = file.name;
                    previewContainer.appendChild(img);
                }
                
                reader.readAsDataURL(file);
            }
        });

        const dataTransfer = new DataTransfer();
        files.forEach(file => dataTransfer.items.add(file));
        this.files = dataTransfer.files;
    });

    // Eventos iniciais
    document.querySelectorAll('.qtd, .unitario').forEach(input => {
        input.addEventListener('input', calcularTotal);
    });
    document.querySelectorAll('.item-select').forEach(select => {
        select.addEventListener('change', function() { atualizarCampos(this); });
    });

    calcularTotal();
    </script>
</body>
</html>