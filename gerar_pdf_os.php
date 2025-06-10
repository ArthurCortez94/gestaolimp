<?php
session_start();
require_once 'config.php';
require_once 'vendor/autoload.php'; // Inclui Composer autoload para QR Code e PHPMailer

use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Verificar se o ID da OS foi fornecido
if (!isset($_GET['id'])) {
    die("ID da ordem de serviço não fornecido.");
}

$ordem_id = (int)$_GET['id'];

// Carregar os dados da ordem de serviço
try {
    $stmt = $pdo->prepare("
        SELECT 
            os.id,
            os.numero_ordem,
            os.data_emissao,
            os.data_servico,
            os.hora_servico,
            os.previsao_conclusao,
            os.observacoes,
            os.informacoes_extras,
            os.status,
            os.descricao_servicos,
            os.total,
            os.cliente_id,
            os.orcamento_id,
            os.caminho_pdf,
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
            c.email,
            u.nome AS tecnico_nome,
            o.forma_pagamento
        FROM ordens_servico os
        JOIN clientes c ON os.cliente_id = c.id
        LEFT JOIN orcamentos o ON os.orcamento_id = o.id
        LEFT JOIN usuarios u ON os.tecnico_id = u.id
        WHERE os.id = ?
    ");
    $stmt->execute([$ordem_id]);
    $ordem = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ordem) {
        die("Ordem de serviço não encontrada para o ID: $ordem_id.");
    }
} catch (PDOException $e) {
    die("Erro ao carregar dados da ordem: " . $e->getMessage());
}

// Preparar os dados para o PDF
$dados = [
    'tipo_cliente' => $ordem['tipo_cliente'] ?? 'Não especificado',
    'nome' => $ordem['cliente_nome'] ?? 'Não especificado',
    'razao_social' => $ordem['razao_social'] ?? null,
    'nome_fantasia' => $ordem['nome_fantasia'] ?? null,
    'cnpj_cpf' => $ordem['cnpj_cpf'] ?? 'Não especificado',
    'endereco' => $ordem['endereco'] ?? 'Não especificado',
    'numero' => $ordem['numero'] ?? '',
    'complemento' => $ordem['complemento'] ?? '',
    'bairro' => $ordem['bairro'] ?? 'Não especificado',
    'cidade' => $ordem['cidade'] ?? 'Não especificado',
    'uf' => $ordem['uf'] ?? 'Não especificado',
    'cep' => $ordem['cep'] ?? 'Não especificado',
    'telefone' => $ordem['telefone'] ?? 'Não especificado',
    'email' => $ordem['email'] ?? null,
    'servicos' => json_decode($ordem['descricao_servicos'], true) ?: [],
    'tecnico_id' => $ordem['tecnico_id'] ?? null,
    'tecnico_nome' => $ordem['tecnico_nome'] ?? 'Não atribuído',
    'data_servico' => $ordem['data_servico'] ?? date('Y-m-d'),
    'hora_servico' => $ordem['hora_servico'] ?? '00:00',
    'previsao_conclusao' => $ordem['previsao_conclusao'] ?? date('Y-m-d', strtotime('+7 days')),
    'observacoes' => $ordem['observacoes'] ?? null,
    'informacoes_extras' => $ordem['informacoes_extras'] ?? null,
    'data_emissao' => $ordem['data_emissao'] ?? date('Y-m-d'),
    'status' => $ordem['status'] ?? 'Desconhecido',
    'total' => $ordem['total'] ?? 0,
    'forma_pagamento' => $ordem['forma_pagamento'] ?? 'Não especificado'
];

$cliente_id = $ordem['cliente_id'];
$numero_ordem = $ordem['numero_ordem'];
$caminho_pdf = $ordem['caminho_pdf'];

// Extrair apenas o número da OS (ex.: "048" de "OS-048/2025")
$numero_os = $numero_ordem;
if (preg_match('/OS-(\d+)/', $numero_ordem, $matches)) {
    $numero_os = $matches[1];
} else {
    $numero_os = str_replace('/', '_', $numero_ordem); // Fallback
}

// Sanitizar o nome do cliente
$cliente_nome = preg_replace('/[^A-Za-z0-9\-_]/', '', str_replace(' ', '_', $ordem['cliente_nome']));
$cliente_nome = substr($cliente_nome, 0, 50);

// Definir o nome do arquivo PDF
$pdf_filename = "Ordem_Servico_{$numero_os}_{$cliente_nome}.pdf";

// Gerar QR Code
$qrCode = QrCode::create("https://seusite.com/os/{$numero_ordem}")
    ->setSize(100);
$writer = new PngWriter();
$qrResult = $writer->write($qrCode);
$qrCodePath = __DIR__ . "/Uploads/qr_code_{$numero_os}.png";
$qrResult->saveToFile($qrCodePath);

// Função para gerar o PDF
function gerarTemplatePDF(array $dados, int $cliente_id, string $numero_ordem, string $qrCodePath): string {
    $data_geracao = date('d/m/Y H:i');
    $data_emissao_formatada = date('d/m/Y', strtotime($dados['data_emissao']));
    $data_servico_formatada = date('d/m/Y', strtotime($dados['data_servico'])) . ' às ' . $dados['hora_servico'];
    $previsao_conclusao_formatada = date('d/m/Y', strtotime($dados['previsao_conclusao']));
    $total_formatado = "R$ " . number_format($dados['total'], 2, ',', '.');

    $endereco_completo = $dados['endereco'] . ", " . $dados['numero'];
    if (!empty(trim($dados['complemento']))) {
        $endereco_completo .= ", " . trim($dados['complemento']);
    }
    $endereco_completo .= " - " . $dados['bairro'];

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
            body { font-family: 'Helvetica', sans-serif; font-size: 12px; line-height: 1.5; margin: 20px; color: #333; }
            .header { background: linear-gradient(to right, #2c3e50, #3498db); padding: 15px; border-radius: 8px; color: white; margin-bottom: 25px; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
            .header h1 { font-size: 24px; font-weight: bold; text-align: center; margin-bottom: 10px; }
            .header-table { width: 100%; border-collapse: collapse; }
            .header-table td { padding: 5px; font-size: 11px; vertical-align: top; }
            .header-table .left { width: 50%; }
            .header-table .right { width: 50%; text-align: right; }
            h3 { font-size: 18px; color: #2c3e50; margin: 30px 0 15px; border-bottom: 2px solid #3498db; padding-bottom: 5px; }
            h4 { font-size: 14px; color: #2c3e50; margin: 20px 0 10px; page-break-before: auto; }
            table { width: 100%; border-collapse: collapse; margin: 15px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-radius: 5px; }
            th, td { padding: 10px; border: 1px solid #e0e0e0; text-align: left; }
            th { background-color: #3498db; color: white; font-weight: bold; font-size: 13px; }
            .label { font-weight: bold; color: #2c3e50; }
            .extra-info { margin: 15px 0; font-size: 11px; border: 1px dashed #e0e0e0; padding: 10px; border-radius: 5px; background: #f9f9f9; }
            .signature { margin: 40px 0; display: flex; justify-content: space-between; align-items: center; width: 100%; page-break-inside: avoid; }
            .signature p { margin: 0; border-top: 1px solid #e0e0e0; width: 200px; text-align: center; padding-top: 5px; }
            .signature .tecnico, .signature .cliente { flex: 1; }
            .footer { position: fixed; bottom: 10px; left: 20px; right: 20px; text-align: center; font-size: 10px; color: #777; border-top: 1px solid #e0e0e0; padding-top: 5px; }
            .page-number:after { content: "Página " counter(page) " de " counter(pages); }
            .qr-code { float: right; margin: 0 0 20px 20px; }
            body::before { content: "CÓPIA"; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-45deg); font-size: 60px; color: rgba(0,0,0,0.1); z-index: -1; }
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

        <img src="{$qrCodePath}" class="qr-code" alt="QR Code">
        <h3>ORDEM DE SERVIÇO Nº {$numero_ordem} - {$data_emissao_formatada}</h3>

        <table>
            <tr><th colspan="4">DADOS DO CLIENTE</th></tr>
            <tr><td class="label">Cliente:</td><td>{$dados['nome']}</td><td class="label">CNPJ/CPF:</td><td>{$dados['cnpj_cpf']}</td></tr>
            <tr><td class="label">Endereço:</td><td colspan="3">{$endereco_completo}</td></tr>
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
               
            </div>
            <div class="cliente">
                <p>Assinatura do Cliente</p>
            </div>
        </div>

        <div class="footer">
            <p>ULTRA LIMP MULTISERVICE. | CNPJ: 46.731.151/0001-10 | Gerado em: {$data_geracao}</p>
            <p class="page-number"></p>
        </div>
    </body>
    </html>
HTML;
}

// Configuração do Dompdf com otimização
$options = new Options();
$options->setIsHtml5ParserEnabled(true);
$options->setIsRemoteEnabled(true);
$options->setDpi(96); // Reduz DPI para otimizar tamanho do arquivo
$options->setDefaultFont('Helvetica');
$options->setTempDir('/tmp'); // Diretório temporário para cache

$dompdf = new Dompdf($options);
$html = gerarTemplatePDF($dados, $cliente_id, $numero_ordem, $qrCodePath);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Usar o caminho_pdf armazenado na tabela
$arquivo_pdf = __DIR__ . $caminho_pdf;
if (!file_exists($arquivo_pdf)) {
    // Regerar o PDF se o arquivo não existir
    $diretorio = __DIR__ . "/Uploads/ordens_servico/Ordem_Servico_{$numero_os}/";
    $arquivo_pdf = $diretorio . $pdf_filename;
    if (!is_dir($diretorio)) {
        mkdir($diretorio, 0777, true);
    }
    $output = $dompdf->output();
    file_put_contents($arquivo_pdf, $output);
}

// Enviar o PDF por e-mail
$mail = new PHPMailer(true);
try {
    // Configurações do servidor SMTP (ajuste conforme seu provedor)
    $mail->isSMTP();
    $mail->Host = 'smtp.seuprovedor.com'; // Ex.: smtp.gmail.com
    $mail->SMTPAuth = true;
    $mail->Username = 'seu_email@provedor.com';
    $mail->Password = 'sua_senha';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    // Remetente e destinatário
    $mail->setFrom('seu_email@provedor.com', 'Ultra Limp Multiservice');
    $mail->addAddress($dados['email'], $dados['nome']);

    // Anexo e conteúdo
    $mail->addAttachment($arquivo_pdf, $pdf_filename);
    $mail->isHTML(true);
    $mail->Subject = "Ordem de Serviço #{$numero_ordem}";
    $mail->Body = "Olá {$dados['nome']},<br><br>Segue em anexo a Ordem de Serviço #{$numero_ordem}.<br>Qualquer dúvida, entre em contato.<br><br>Atenciosamente,<br>Ultra Limp Multiservice";
    $mail->AltBody = "Segue em anexo a Ordem de Serviço #{$numero_ordem}.";

    $mail->send();
    echo "PDF gerado e enviado por e-mail com sucesso!";
} catch (Exception $e) {
    echo "Erro ao enviar o e-mail: {$mail->ErrorInfo}";
}

// Forçar o download do PDF
$dompdf->stream($pdf_filename, ['Attachment' => true]);

// Limpar QR Code temporário
unlink($qrCodePath);

exit();