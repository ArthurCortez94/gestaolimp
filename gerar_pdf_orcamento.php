<?php
session_start();
require_once 'config.php';
require_once 'vendor/autoload.php'; // Dompdf

use Dompdf\Dompdf;

// Verifica se o ID do orçamento foi passado
if (!isset($_GET['id'])) {
    die("ID do orçamento não fornecido.");
}

$orcamento_id = (int)$_GET['id'];

// Buscar dados do orçamento e do cliente
try {
    $stmt = $pdo->prepare("
        SELECT 
            o.id, o.numero_orcamento, o.validade, o.total, o.forma_pagamento, o.descricao_servico, o.observacao, o.descricao_servicos, o.informacoes_extras,
            c.tipo_cliente, c.nome, c.razao_social, c.nome_fantasia, c.cnpj_cpf, c.endereco, c.numero, c.complemento, 
            c.bairro, c.cidade, c.uf, c.cep, c.telefone, c.email
        FROM orcamentos o
        JOIN clientes c ON o.cliente_id = c.id
        WHERE o.id = ?
    ");
    $stmt->execute([$orcamento_id]);
    $orcamento = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$orcamento) {
        die("Orçamento não encontrado.");
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

    // Preparar o texto da descrição com parágrafos e quebras de linha
    $descricao_servicos = $orcamento['descricao_servicos'] ?? 'Sem descrição fornecida';
    $descricao_servicos = htmlspecialchars($descricao_servicos);
    $paragrafos = array_filter(explode("\n\n", $descricao_servicos));
    $paragrafos_formatados = array_map(function($paragrafo) {
        $linhas = array_filter(explode("\n", $paragrafo));
        return implode('<br>', $linhas);
    }, $paragrafos);
    $descricao_formatada = '<p>' . implode('</p><p>', $paragrafos_formatados) . '</p>';

    // Preparar os dados para o PDF
    $dados = [
        'tipo_cliente' => $orcamento['tipo_cliente'],
        'nome' => $orcamento['nome'],
        'razao_social' => $orcamento['razao_social'],
        'nome_fantasia' => $orcamento['nome_fantasia'],
        'cnpj_cpf' => $orcamento['cnpj_cpf'],
        'endereco' => $orcamento['endereco'],
        'numero' => $orcamento['numero'],
        'complemento' => $orcamento['complemento'],
        'bairro' => $orcamento['bairro'],
        'cidade' => $orcamento['cidade'],
        'uf' => $orcamento['uf'],
        'cep' => $orcamento['cep'],
        'telefone' => $orcamento['telefone'],
        'email' => $orcamento['email'],
        'servicos' => json_decode($orcamento['descricao_servico'], true),
        'forma_pagamento' => $orcamento['forma_pagamento'],
        'observacoes' => $orcamento['observacao'],
        'data_orcamento' => date('Y-m-d'),
        'data_vencimento' => $orcamento['validade'],
        'aos_cuidados' => 'Não especificado',
        'descricao_servicos' => $descricao_formatada,
        'informacoes_extras' => $orcamento['informacoes_extras']
    ];

    // Extrair apenas o número do orçamento (ex.: "048" de "048/2025")
    $numero_orcamento = $orcamento['numero_orcamento'];
    if (preg_match('/^(\d+)/', $numero_orcamento, $matches)) {
        $numero_orcamento = $matches[1]; // Pega apenas o número (ex.: "048")
    } else {
        $numero_orcamento = str_replace('/', '_', $numero_orcamento); // Fallback: substitui "/" por "_"
    }

    // Sanitizar o nome do cliente para o nome do arquivo
    $cliente_nome = preg_replace('/[^A-Za-z0-9\-_]/', '', str_replace(' ', '_', $orcamento['nome']));
    $cliente_nome = substr($cliente_nome, 0, 50); // Limitar a 50 caracteres

    // Nome do arquivo PDF
    $pdf_filename = "Orcamento_{$numero_orcamento}_{$cliente_nome}.pdf";

    // Gerar PDF
    $dompdf = new Dompdf();
    $html = gerarTemplatePDF($dados, $orcamento['id'], $orcamento['numero_orcamento'], $orcamento['total'], $dados_empresa);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $dompdf->stream($pdf_filename, ['Attachment' => true]);

} catch (PDOException $e) {
    die("Erro ao gerar PDF: " . $e->getMessage());
}

// Função para gerar o template do PDF
function gerarTemplatePDF(array $dados, int $cliente_id, string $numero_orcamento, float $valor_total, array $dados_empresa): string {
    $data_geracao = date('d/m/Y H:i');
    $total_formatado = "R\$ " . number_format($valor_total, 2, ',', '.');
    $data_orcamento_formatada = date('d/m/Y', strtotime($dados['data_orcamento']));
    $data_vencimento_formatada = date('d/m/Y', strtotime($dados['data_vencimento']));

    // Geração dinâmica dos serviços
    $servicos_html = '';
    if (!empty($dados['servicos']) && is_array($dados['servicos'])) {
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
    } else {
        $servicos_html = "<tr><td colspan='5' style='text-align: center;'>Nenhum serviço listado</td></tr>";
    }

    // Dados fixos ou do orçamento para vendedor e código do fornecedor
    $vendedor = "Arthur"; // Você pode ajustar isso para buscar do orçamento ou outra fonte
    $cod_fornecedor = "N/A"; // Ajuste conforme necessário

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
            .description p { 
                margin: 0 0 5px 0; /* Espaçamento reduzido entre parágrafos */
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
                        <p>COD FORNECEDOR: {$cod_fornecedor}</p>
                    </td>
                    <td class="right">
                        <p>E-mail: {$dados_empresa['email']}</p>
                        <p>Vendedor: {$vendedor}</p>
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