<?php
session_start();
require_once 'config.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Função para gerar número da ordem de serviço
function gerarNumeroOrdem(PDO $pdo): string {
    $stmt = $pdo->query("SELECT MAX(id) FROM ordens_servico");
    $ultimo_id = $stmt->fetchColumn() ?: 0;
    return 'OS-' . str_pad($ultimo_id + 1, 3, '0', STR_PAD_LEFT) . '/' . date('Y');
}

// Carregar todos os orçamentos disponíveis para o <select>
try {
    $stmt = $pdo->query("
        SELECT o.id, o.numero_orcamento, c.nome AS cliente_nome 
        FROM orcamentos o 
        JOIN clientes c ON o.cliente_id = c.id 
        ORDER BY o.numero_orcamento DESC
    ");
    $orcamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao carregar orçamentos: " . $e->getMessage());
}

// Carregar técnicos da tabela usuarios
$tecnicos = [];
try {
    $stmt = $pdo->query("SELECT id, nome FROM usuarios WHERE cargo = 'tecnico' AND ativo = 1 ORDER BY nome");
    $tecnicos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao carregar técnicos: " . $e->getMessage());
}

// Processar o formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Dados enviados pelo formulário
        $orcamento_id = (int)$_POST['orcamento_id'];
        $tecnico_id = (int)$_POST['tecnico_id'];
        $data_servico = $_POST['data_servico'];
        $hora_servico = $_POST['hora_servico'];
        $previsao_conclusao = $_POST['previsao_conclusao'];
        $observacoes = $_POST['observacoes'] ?? null;
        $informacoes_extras = $_POST['informacoes_extras'] ?? null;

        // Validações
        if (empty($orcamento_id)) {
            throw new Exception("Orçamento é obrigatório.");
        }
        if (empty($tecnico_id)) {
            throw new Exception("Técnico responsável é obrigatório.");
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_servico)) {
            throw new Exception("Data do serviço inválida.");
        }
        if (!preg_match('/^\d{2}:\d{2}$/', $hora_servico)) {
            throw new Exception("Hora do serviço inválida.");
        }

        // Buscar os dados do orçamento selecionado
        $stmt = $pdo->prepare("
            SELECT o.*, c.* 
            FROM orcamentos o 
            JOIN clientes c ON o.cliente_id = c.id 
            WHERE o.id = ?
        ");
        $stmt->execute([$orcamento_id]);
        $orcamento = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$orcamento) {
            throw new Exception("Orçamento não encontrado para o ID: $orcamento_id.");
        }

        // Buscar o nome do técnico selecionado
        $stmt = $pdo->prepare("SELECT nome FROM usuarios WHERE id = ?");
        $stmt->execute([$tecnico_id]);
        $tecnico_nome = $stmt->fetchColumn();
        if (!$tecnico_nome) {
            throw new Exception("Técnico não encontrado.");
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

        $servicos = json_decode($orcamento['descricao_servico'], true) ?: [];

        // Montar os dados para salvar e gerar o PDF
        $dados = [
            'tipo_cliente' => $orcamento['tipo_cliente'],
            'nome' => $orcamento['nome'],
            'razao_social' => $orcamento['razao_social'] ?? null,
            'nome_fantasia' => $orcamento['nome_fantasia'] ?? null,
            'cnpj_cpf' => $orcamento['cnpj_cpf'],
            'endereco' => $orcamento['endereco'],
            'numero' => $orcamento['numero'],
            'complemento' => $orcamento['complemento'] ?? null,
            'bairro' => $orcamento['bairro'],
            'cidade' => $orcamento['cidade'],
            'uf' => $orcamento['uf'],
            'cep' => $orcamento['cep'],
            'telefone' => $orcamento['telefone'],
            'email' => $orcamento['email'] ?? null,
            'servicos' => $servicos,
            'tecnico_id' => $tecnico_id,
            'tecnico_nome' => $tecnico_nome,
            'data_servico' => $data_servico,
            'hora_servico' => $hora_servico,
            'previsao_conclusao' => $previsao_conclusao,
            'observacoes' => $observacoes,
            'informacoes_extras' => $informacoes_extras,
            'data_emissao' => date('Y-m-d'),
            'status' => 'Agendado', // Status padrão
            'total' => $orcamento['total'],
            'forma_pagamento' => $orcamento['forma_pagamento'],
            'aos_cuidados' => 'Não especificado' // Pode ser ajustado conforme necessário
        ];

        $cliente_id = $orcamento['cliente_id'];
        $numero_ordem = gerarNumeroOrdem($pdo);

        // Extrair apenas o número da OS (ex.: "048" de "OS-048/2025")
        $numero_os = $numero_ordem;
        if (preg_match('/OS-(\d+)/', $numero_ordem, $matches)) {
            $numero_os = $matches[1];
        } else {
            $numero_os = str_replace('/', '_', $numero_ordem); // Fallback
        }

        // Sanitizar o nome do cliente
        $cliente_nome = preg_replace('/[^A-Za-z0-9\-_]/', '', str_replace(' ', '_', $orcamento['nome']));
        $cliente_nome = substr($cliente_nome, 0, 50);

        // Definir o nome do arquivo PDF
        $pdf_filename = "Ordem_Servico_{$numero_os}_{$cliente_nome}.pdf";

        // Atualizar o status do orçamento para "Agendado"
        $stmt = $pdo->prepare("UPDATE orcamentos SET status = 'Agendado' WHERE id = ?");
        $stmt->execute([$orcamento_id]);

        // Inserir a ordem de serviço com status "Agendado"
        $caminho_pdf = null;
        $stmt = $pdo->prepare("INSERT INTO ordens_servico (
            cliente_id, orcamento_id, tecnico_id, numero_ordem, data_emissao, data_servico, hora_servico, previsao_conclusao, 
            status, descricao_servicos, observacoes, informacoes_extras, total, caminho_pdf
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $cliente_id, $orcamento_id, $dados['tecnico_id'], $numero_ordem, $dados['data_emissao'], $dados['data_servico'], $dados['hora_servico'],
            $dados['previsao_conclusao'], $dados['status'], json_encode($dados['servicos']),
            $dados['observacoes'], $dados['informacoes_extras'], $dados['total'], $caminho_pdf
        ]);
        $ordem_id = $pdo->lastInsertId();

        // Inserir na agenda de serviços
        $stmt = $pdo->prepare("INSERT INTO agenda_servicos (ordem_id, data_agendamento, hora_agendamento) VALUES (?, ?, ?)");
        $stmt->execute([$ordem_id, $dados['data_servico'], $dados['hora_servico']]);

        // Gerar o PDF
        require_once 'vendor/autoload.php';
        $options = new Options();
        $options->setIsHtml5ParserEnabled(true);
        $options->setIsRemoteEnabled(true);

        $dompdf = new Dompdf($options);
        $html = gerarTemplatePDF($dados, $cliente_id, $numero_ordem, $dados_empresa);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Salvar o PDF no servidor
        $salvar_no_servidor = true;
        if ($salvar_no_servidor) {
            $diretorio = __DIR__ . "/Uploads/ordens_servico/Ordem_Servico_{$numero_os}/";
            $caminho_pdf = $diretorio . $pdf_filename;
            $caminho_pdf_relativo = "/Uploads/ordens_servico/Ordem_Servico_{$numero_os}/{$pdf_filename}";

            if (!is_dir($diretorio)) {
                mkdir($diretorio, 0777, true);
            }

            $output = $dompdf->output();
            file_put_contents($caminho_pdf, $output);

            // Atualizar o campo caminho_pdf na tabela ordens_servico
            $stmt = $pdo->prepare("UPDATE ordens_servico SET caminho_pdf = ? WHERE id = ?");
            $stmt->execute([$caminho_pdf_relativo, $ordem_id]);
        }

        // Exibir o PDF e redirecionar
        $dompdf->stream($pdf_filename, ['Attachment' => false]);
        header("Refresh: 1; url=lista_ordens_servico.php?id=$ordem_id&success=Ordem+criada+com+sucesso");
        exit();

    } catch (Exception $e) {
        die("Erro ao processar ordem de serviço: " . $e->getMessage());
    }
}

// Função para gerar o PDF
function gerarTemplatePDF(array $dados, int $cliente_id, string $numero_ordem, array $dados_empresa): string {
    $data_geracao = date('d/m/Y H:i');
    $data_emissao_formatada = date('d/m/Y', strtotime($dados['data_emissao']));
    $data_servico_formatada = date('d/m/Y', strtotime($dados['data_servico'])) . ' às ' . $dados['hora_servico'];
    $previsao_conclusao_formatada = date('d/m/Y', strtotime($dados['previsao_conclusao']));
    $total_formatado = "R$ " . number_format($dados['total'], 2, ',', '.');

    // Dados fixos ou do orçamento para vendedor e código do fornecedor
    $vendedor = "Arthur"; // Você pode ajustar isso para buscar de outra fonte, se necessário
    $cod_fornecedor = "BR01_V005301"; // Você pode ajustar isso para buscar de outra fonte, se necessário

    $servicos_html = '';
    foreach ($dados['servicos'] as $index => $servico) {
        $subtotal = ($servico['quantidade'] ?? 0) * ($servico['valor_unitario'] ?? 0);
        $bg_color = $index % 2 == 0 ? '#f9f9f9' : '#ffffff';
        $servicos_html .= "
            <tr style='background-color: {$bg_color};'>
                <td>" . ($index + 1) . "</td>
                <td>" . htmlspecialchars($servico['nome'] ?? '') . "</td>
                <td>" . htmlspecialchars($servico['quantidade'] ?? '') . "</td>
                <td style='text-align: right;'>R$ " . number_format($servico['valor_unitario'] ?? 0, 2, ',', '.') . "</td>
                <td style='text-align: right;'>R$ " . number_format($subtotal, 2, ',', '.') . "</td>
            </tr>";
    }

    return <<<HTML
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: Helvetica, sans-serif; font-size: 12px; line-height: 1.5; margin: 20px; color: #333; }
            .header { background-color: #f5f6f5; padding: 10px; border-top: 5px solid #2c3e50; margin-bottom: 25px; border-radius: 0 0 8px 8px; }
            .header h1 { font-size: 20px; font-weight: bold; color: #000000; margin-bottom: 10px; text-align: center; }
            .header-table { width: 100%; border-collapse: collapse; border: none; }
            .header-table td { padding: 2px 5px; font-size: 11px; color: #000000; vertical-align: top; border: none; }
            .header-table .left { width: 50%; }
            .header-table .right { width: 50%; text-align: right; }
            h3 { font-size: 18px; font-weight: bold; color: #2c3e50; margin: 30px 0 15px; border-bottom: 2px solid #2c3e50; padding-bottom: 5px; }
            h4 { font-size: 14px; font-weight: bold; color: #2c3e50; margin: 20px 0 10px; }
            table { width: 100%; border-collapse: collapse; margin: 15px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-radius: 5px; overflow: hidden; }
            th, td { padding: 8px; border: 1px solid #e0e0e0; text-align: left; }
            th { background-color: #ecf0f1; font-weight: bold; color: #2c3e50; font-size: 13px; }
            .label { font-weight: bold; color: #2c3e50; }
            .extra-info { margin: 15px 0; font-size: 11px; color: #333; border: 1px solid #e0e0e0; padding: 8px; border-radius: 5px; }
            .signature { margin: 40px 0; display: flex; justify-content: space-between; align-items: center; width: 100%; }
            .signature p { margin: 0; border-top: 1px solid #e0e0e0; width: 200px; text-align: center; padding-top: 5px; }
            .signature .tecnico { flex: 1; margin-right: 20px; }
            .signature .cliente { flex: 1; margin-left: 20px; }
            .footer { position: fixed; bottom: 10px; left: 20px; right: 20px; text-align: center; font-size: 10px; color: #777; border-top: 1px solid #e0e0e0; padding-top: 5px; }
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

        <h3>ORDEM DE SERVIÇO Nº {$numero_ordem} - {$data_emissao_formatada}</h3>

        <table>
            <tr><th colspan="4">DADOS DO CLIENTE</th></tr>
            <tr><td class="label">Cliente:</td><td>{$dados['nome']}</td><td class="label">CNPJ/CPF:</td><td>{$dados['cnpj_cpf']}</td></tr>
            <tr><td class="label">Endereço:</td><td colspan="3">{$dados['endereco']}, {$dados['numero']} - {$dados['bairro']}</td></tr>
            <tr><td class="label">Cidade:</td><td>{$dados['cidade']}/{$dados['uf']}</td><td class="label">CEP:</td><td>{$dados['cep']}</td></tr>
        </table>

        <table>
            <tr><th colspan="2">DETALHES DA ORDEM</th></tr>
            <tr><td class="label">Técnico Responsável:</td><td>{$dados['tecnico_nome']}</td></tr>
            <tr><td class="label">Data e Hora do Serviço:</td><td>{$data_servico_formatada}</td></tr>
            <tr><td class="label">Previsão de Conclusão:</td><td>{$previsao_conclusao_formatada}</td></tr>
            <tr><td class="label">Status:</td><td>{$dados['status']}</td></tr>
            <tr><td class="label">Valor Total:</td><td>{$total_formatado}</td></tr>
            <tr><td class="label">Forma de Pagamento:</td><td>{$dados['forma_pagamento']}</td></tr>
        </table>

        <h4>Serviços a Executar</h4>
        <table>
            <tr><th>ITEM</th><th>NOME</th><th>QTD.</th><th>VR. UNIT.</th><th>SUBTOTAL</th></tr>
            {$servicos_html}
        </table>

        <table>
            <tr><th>OBSERVAÇÕES</th></tr>
            <tr><td>{$dados['observacoes']}</td></tr>
        </table>

        <div class="extra-info">
            <strong>Informações Extras:</strong><br>
            {$dados['informacoes_extras']}
        </div>

        <div class="signature">
            <div class="tecnico">
                <p>Assinatura do Técnico</p>
            </div>
            <div class="cliente">
                <p>Assinatura do Cliente</p>
            </div>
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
    <title>Criar Ordem de Serviço - UltraLimp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        :root { --primary: #2A5C82; --secondary: #4CAF50; --accent: #FFC107; --light: #f8fafc; }
        body { font-family: 'Inter', sans-serif; background: var(--light); }
        .dashboard-card { background: white; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); padding: 1.5rem; margin-bottom: 1.5rem; transition: transform 0.2s; }
        .dashboard-card:hover { transform: translateY(-3px); box-shadow: 0 6px 12px rgba(0,0,0,0.1); }
        .form-section h4 { color: var(--primary); font-weight: bold; margin-bottom: 1rem; }
        .btn-primary { background-color: var(--primary); border-color: var(--primary); }
        .btn-primary:hover { background-color: #224a6b; border-color: #224a6b; }
        .btn-success { background-color: var(--secondary); border-color: var(--secondary); }
        .btn-success:hover { background-color: #3d8b40; border-color: #3d8b40; }
        .servico-item { margin-bottom: 1rem; }
        .form-control:disabled { background-color: #e9ecef; }
        .form-control-plaintext { background-color: transparent !important; border: none !important; box-shadow: none !important; }
    </style>
</head>
<body>
    <?php require __DIR__ . '/navbar.php'; ?>

    <div class="container-fluid px-4 py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="text-primary fw-bold mb-0">Nova Ordem de Serviço</h2>
            <a href="lista_orcamentos.php" class="btn btn-primary"><i class="fas fa-arrow-left me-1"></i>Voltar</a>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_GET['success']) ?></div>
        <?php endif; ?>

        <form method="POST" id="ordemForm">
            <!-- Seleção do Orçamento -->
            <div class="dashboard-card form-section">
                <h4><i class="fas fa-file-invoice me-2"></i>Selecionar Orçamento</h4>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Orçamento</label>
                        <select name="orcamento_id" id="orcamentoSelect" class="form-select" required>
                            <option value="">Selecione um orçamento</option>
                            <?php foreach ($orcamentos as $orc): ?>
                                <option value="<?= htmlspecialchars($orc['id']) ?>">
                                    <?= htmlspecialchars($orc['numero_orcamento'] . ' - ' . $orc['cliente_nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Dados do Cliente (Preenchidos dinamicamente) -->
            <div class="dashboard-card form-section">
                <h4><i class="fas fa-users me-2"></i>Dados do Cliente</h4>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Tipo de Cliente</label>
                        <input type="text" name="tipo_cliente" id="tipoCliente" class="form-control" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Nome Completo/Razão Social</label>
                        <input type="text" name="nome" id="nomeCliente" class="form-control" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">CPF/CNPJ</label>
                        <input type="text" name="cnpj_cpf" id="cnpjCpf" class="form-control" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Endereço</label>
                        <input type="text" name="endereco" id="endereco" class="form-control" readonly>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Número</label>
                        <input type="text" name="numero" id="numero" class="form-control" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Complemento</label>
                        <input type="text" name="complemento" id="complemento" class="form-control" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Bairro</label>
                        <input type="text" name="bairro" id="bairro" class="form-control" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">CEP</label>
                        <input type="text" name="cep" id="cep" class="form-control" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Cidade</label>
                        <input type="text" name="cidade" id="cidade" class="form-control" readonly>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">UF</label>
                        <input type="text" name="uf" id="uf" class="form-control" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Telefone</label>
                        <input type="text" name="telefone" id="telefone" class="form-control" readonly>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">E-mail</label>
                        <input type="email" name="email" id="email" class="form-control" readonly>
                    </div>
                </div>
            </div>

            <!-- Detalhes da Ordem -->
            <div class="dashboard-card form-section">
                <h4><i class="fas fa-tools me-2"></i>Detalhes da Ordem</h4>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Técnico Responsável</label>
                        <select name="tecnico_id" class="form-select" required>
                            <option value="">Selecione um técnico</option>
                            <?php foreach ($tecnicos as $tecnico): ?>
                                <option value="<?= htmlspecialchars($tecnico['id']) ?>">
                                    <?= htmlspecialchars($tecnico['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Data do Serviço</label>
                        <input type="date" name="data_servico" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Hora do Serviço</label>
                        <input type="time" name="hora_servico" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Previsão de Conclusão</label>
                        <input type="date" name="previsao_conclusao" class="form-control" value="<?= date('Y-m-d', strtotime('+7 days')) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Valor Total</label>
                        <input type="text" name="total" id="valorTotal" class="form-control" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Forma de Pagamento</label>
                        <input type="text" name="forma_pagamento" id="formaPagamento" class="form-control" readonly>
                    </div>
                </div>
            </div>

            <!-- Serviços -->
            <div class="dashboard-card form-section">
                <h4><i class="fas fa-concierge-bell me-2"></i>Serviços a Executar</h4>
                <div class="row g-3 mb-3">
                    <div class="col-md-12">
                        <label class="form-label">Descrição do Orçamento</label>
                        <textarea class="form-control" rows="3" id="descricaoServico" readonly></textarea>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-bordered" id="servicosTable">
                        <thead>
                            <tr>
                                <th>Serviço</th>
                                <th>Quantidade</th>
                                <th>Valor Unitário</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Preenchido dinamicamente via JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Observações -->
            <div class="dashboard-card form-section">
                <h4><i class="fas fa-sticky-note me-2"></i>Observações</h4>
                <div class="row g-3">
                    <div class="col-md-12">
                        <textarea name="observacoes" class="form-control" rows="3" placeholder="Instruções para o técnico ou cliente"></textarea>
                    </div>
                </div>
            </div>

            <!-- Informações Extras -->
            <div class="dashboard-card form-section">
                <h4><i class="fas fa-info-circle me-2"></i>Informações Extras</h4>
                <div class="row g-3">
                    <div class="col-md-12">
                        <textarea name="informacoes_extras" class="form-control" rows="3" placeholder="Informações adicionais"></textarea>
                    </div>
                </div>
            </div>

            <div class="text-end">
                <button type="submit" name="salvar" class="btn btn-primary btn-lg me-2">Salvar Ordem de Serviço</button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        flatpickr("input[name='data_servico']", { dateFormat: "Y-m-d" });
        flatpickr("input[name='hora_servico']", { enableTime: true, noCalendar: true, dateFormat: "H:i", time_24hr: true });
        flatpickr("input[name='previsao_conclusao']", { dateFormat: "Y-m-d" });

        $('#orcamentoSelect').on('change', function() {
            var orcamentoId = $(this).val();
            if (orcamentoId) {
                $.ajax({
                    url: 'buscar_orcamento.php',
                    type: 'POST',
                    data: { orcamento_id: orcamentoId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.error) {
                            alert(response.error);
                            return;
                        }
                        $('#tipoCliente').val(response.tipo_cliente);
                        $('#nomeCliente').val(response.nome);
                        $('#cnpjCpf').val(response.cnpj_cpf);
                        $('#endereco').val(response.endereco);
                        $('#numero').val(response.numero);
                        $('#complemento').val(response.complemento);
                        $('#bairro').val(response.bairro);
                        $('#cep').val(response.cep);
                        $('#cidade').val(response.cidade);
                        $('#uf').val(response.uf);
                        $('#telefone').val(response.telefone);
                        $('#email').val(response.email);
                        $('#valorTotal').val('R$ ' + parseFloat(response.total).toFixed(2).replace('.', ','));
                        $('#formaPagamento').val(response.forma_pagamento);
                        var descricaoServico = '';
                        var servicosHtml = '';
                        response.servicos.forEach(function(servico, index) {
                            descricaoServico += '- ' + servico.nome + ' (Qtd: ' + servico.quantidade + ', Valor Unitário: R$ ' + parseFloat(servico.valor_unitario).toFixed(2).replace('.', ',') + ')\n';
                            var subtotal = (servico.quantidade * servico.valor_unitario).toFixed(2).replace('.', ',');
                            servicosHtml += `
                                <tr>
                                    <td><input type="text" class="form-control-plaintext" value="${servico.nome}" readonly></td>
                                    <td><input type="text" class="form-control-plaintext text-center" value="${servico.quantidade}" readonly></td>
                                    <td><input type="text" class="form-control-plaintext text-end" value="R$ ${parseFloat(servico.valor_unitario).toFixed(2).replace('.', ',')}" readonly></td>
                                    <td><input type="text" class="form-control-plaintext text-end" value="R$ ${subtotal}" readonly></td>
                                </tr>
                            `;
                        });
                        $('#descricaoServico').val(descricaoServico);
                        $('#servicosTable tbody').html(servicosHtml);
                    },
                    error: function() {
                        alert('Erro ao buscar dados do orçamento.');
                    }
                });
            } else {
                $('#tipoCliente, #nomeCliente, #cnpjCpf, #endereco, #numero, #complemento, #bairro, #cep, #cidade, #uf, #telefone, #email, #valorTotal, #formaPagamento, #descricaoServico').val('');
                $('#servicosTable tbody').html('');
            }
        });
    </script>
</body>
</html>