<?php
session_start();
require_once 'config.php';

// Incluir o autoload do Composer para carregar o dompdf
require_once 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Habilitar exibição de erros para depuração (remova isso em produção)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Verificar se o ID do registro da lavanderia foi fornecido
if (!isset($_GET['id'])) {
    die("ID do registro da lavanderia não fornecido.");
}

$lavanderia_id = (int)$_GET['id'];

// Carregar os dados do registro da lavanderia
try {
    $stmt = $pdo->prepare("
        SELECT 
            l.id,
            l.cliente_id,
            l.itens,
            l.valor_total_geral,
            l.status,
            l.data_prevista_entrega,
            l.data_coleta,
            l.data_entrega,
            l.observacao,
            l.created_at,
            c.nome AS cliente_nome,
            c.tipo_cliente,
            c.razao_social,
            c.nome_fantasia,
            c.cnpj_cpf,
            c.endereco,
            c.numero,
            c.complemento,
            c.bairro,
            c.cidade,
            c.uf,
            c.cep,
            c.telefone,
            c.email
        FROM lavanderia l
        JOIN clientes c ON l.cliente_id = c.id
        WHERE l.id = ?
    ");
    $stmt->execute([$lavanderia_id]);
    $lavanderia = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lavanderia) {
        die("Registro da lavanderia não encontrado para o ID: $lavanderia_id.");
    }
} catch (PDOException $e) {
    die("Erro ao carregar dados da lavanderia: " . $e->getMessage());
}

// Decodificar o campo 'itens' (JSON)
$lavanderia['itens'] = json_decode($lavanderia['itens'], true) ?: [];

// Preparar os dados para o PDF
$dados = [
    'tipo_cliente' => $lavanderia['tipo_cliente'] ?? 'Não especificado',
    'nome' => $lavanderia['cliente_nome'] ?? 'Não especificado',
    'razao_social' => $lavanderia['razao_social'] ?? null,
    'nome_fantasia' => $lavanderia['nome_fantasia'] ?? null,
    'cnpj_cpf' => $lavanderia['cnpj_cpf'] ?? 'Não informado', // CPF não obrigatório
    'endereco' => $lavanderia['endereco'] ?? 'Não especificado',
    'numero' => $lavanderia['numero'] ?? '',
    'complemento' => $lavanderia['complemento'] ?? null,
    'bairro' => $lavanderia['bairro'] ?? 'Não especificado',
    'cidade' => $lavanderia['cidade'] ?? 'Não especificado',
    'uf' => $lavanderia['uf'] ?? 'Não especificado',
    'cep' => $lavanderia['cep'] ?? 'Não especificado',
    'telefone' => $lavanderia['telefone'] ?? 'Não especificado',
    'email' => $lavanderia['email'] ?? null,
    'itens' => $lavanderia['itens'],
    'valor_total_geral' => $lavanderia['valor_total_geral'] ?? 0,
    'status' => $lavanderia['status'] ?? 'Desconhecido',
    'data_coleta' => $lavanderia['data_coleta'] ?? null,
    'data_prevista_entrega' => $lavanderia['data_prevista_entrega'] ?? null,
    'data_entrega' => $lavanderia['data_entrega'] ?? null,
    'observacao' => $lavanderia['observacao'] ?? null,
    'data_emissao' => $lavanderia['created_at'] ?? date('Y-m-d'),
];

$cliente_id = $lavanderia['cliente_id'];
$numero_ordem = "LAV-{$lavanderia['id']}"; // Exemplo: LAV-001

// Função para gerar o PDF
function gerarTemplatePDF(array $dados, int $cliente_id, string $numero_ordem): string {
    $data_geracao = date('d/m/Y H:i');
    $data_emissao_formatada = date('d/m/Y', strtotime($dados['data_emissao']));
    $data_coleta_formatada = $dados['data_coleta'] ? date('d/m/Y', strtotime($dados['data_coleta'])) : 'Não especificada';
    $data_prevista_entrega_formatada = $dados['data_prevista_entrega'] ? date('d/m/Y', strtotime($dados['data_prevista_entrega'])) : 'Não especificada';
    $data_entrega_formatada = $dados['data_entrega'] ? date('d/m/Y', strtotime($dados['data_entrega'])) : 'Não especificada';
    $valor_total_geral_formatado = "R$ " . number_format($dados['valor_total_geral'], 2, ',', '.');

    $itens_html = '';
    foreach ($dados['itens'] as $index => $item) {
        $subtotal = ($item['quantidade'] ?? 0) * ($item['valor_unitario'] ?? 0);
        $bg_color = $index % 2 == 0 ? '#f9f9f9' : '#ffffff';
        $itens_html .= "
            <tr style='background-color: {$bg_color};'>
                <td>" . ($index + 1) . "</td>
                <td>" . htmlspecialchars($item['nome_item'] ?? '') . "</td>
                <td>" . htmlspecialchars($item['quantidade'] ?? '') . "</td>
                <td style='text-align: right;'>R$ " . number_format($item['valor_unitario'] ?? 0, 2, ',', '.') . "</td>
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
            .signature div { width: 45%; }
            .signature p { margin: 0; border-top: 1px solid #e0e0e0; width: 100%; text-align: center; padding-top: 5px; font-size: 12px; }
            .signature .tecnico { text-align: left; }
            .signature .cliente { text-align: right; }
            .footer { position: fixed; bottom: 10px; left: 20px; right: 20px; text-align: center; font-size: 10px; color: #777; border-top: 1px solid #e0e0e0; padding-top: 5px; }
            .page-number:after { content: "Página " counter(page); }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>ULTRA LIMP MULTISERVICE</h1>
            <table class="header-table">
                <tr>
                    <td class="left">
                        <p>CNPJ: 46.731.151/0001-10</p>
                        <p>Endereço: RUA CUMARU, 7831 - PITIMBU</p>
                        <p>Cidade: Natal/RN - CEP: 59067-520</p>
                    </td>
                    <td class="right">
                        <p>Telefone: (84) 9947-6437</p>
                        <p>E-mail: contato@ultralimpnatal.com.br</p>
                        <p>Vendedor: Arthur</p>
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
            <tr><td class="label">Data de Coleta:</td><td>{$data_coleta_formatada}</td></tr>
            <tr><td class="label">Data Prevista de Entrega:</td><td>{$data_prevista_entrega_formatada}</td></tr>
            <tr><td class="label">Data de Entrega:</td><td>{$data_entrega_formatada}</td></tr>
            <tr><td class="label">Status:</td><td>{$dados['status']}</td></tr>
            <tr><td class="label">Valor Total Geral:</td><td>{$valor_total_geral_formatado}</td></tr>
        </table>

        <h4>Itens da Lavanderia</h4>
        <table>
            <tr><th>ITEM</th><th>NOME</th><th>QTD.</th><th>VR. UNIT.</th><th>SUBTOTAL</th></tr>
            {$itens_html}
        </table>

        <table>
            <tr><th>OBSERVAÇÕES</th></tr>
            <tr><td>{$dados['observacao']}</td></tr>
        </table>

        <div class="signature">
            <div class="tecnico">
                
            </div>
            <div class="cliente">
                <p>Assinatura do Cliente</p>
            </div>
        </div>

        <div class="footer">
            <p>ULTRA LIMP MULTISERVICE | CNPJ: 46.731.151/0001-10 | Gerado em: {$data_geracao}</p>
            <p class="page-number"></p>
        </div>
    </body>
    </html>
HTML;
}

// Gerar e salvar o PDF
try {
    $options = new Options();
    $options->setIsHtml5ParserEnabled(true);
    $options->setIsRemoteEnabled(true);

    $dompdf = new Dompdf($options);
    $html = gerarTemplatePDF($dados, $cliente_id, $numero_ordem);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // Definir o caminho para salvar o PDF
    $diretorio = __DIR__ . "/uploads/lavanderia/Ordem_Servico_{$numero_ordem}/";
    $arquivo_pdf = $diretorio . date('Y') . ".pdf"; // Exemplo: Ordem_Servico_LAV-001/2025.pdf

    // Criar o diretório se não existir
    if (!is_dir($diretorio)) {
        if (!mkdir($diretorio, 0777, true)) {
            die("Erro ao criar o diretório: $diretorio");
        }
    }

    // Verificar permissões do diretório
    if (!is_writable($diretorio)) {
        die("O diretório $diretorio não tem permissões de escrita.");
    }

    // Salvar o PDF no servidor
    $output = $dompdf->output();
    if (!file_put_contents($arquivo_pdf, $output)) {
        die("Erro ao salvar o PDF no servidor: $arquivo_pdf");
    }

    // Forçar o download do PDF
    $dompdf->stream("Ordem_Servico_{$numero_ordem}.pdf", ['Attachment' => true]);
} catch (Exception $e) {
    die("Erro ao gerar o PDF: " . $e->getMessage());
}

exit();