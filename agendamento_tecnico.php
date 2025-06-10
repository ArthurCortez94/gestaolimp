<?php
ob_start();
session_start();
require_once 'config.php';

// Verificação de segurança
if (!isset($_SESSION['user_id']) || $_SESSION['user_cargo'] !== 'tecnico') {
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

$tecnico_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    file_put_contents('debug.log', "POST Recebido: " . print_r($_POST, true) . "\n", FILE_APPEND);
    try {
        $ordem_id = (int)$_POST['ordem_id'];
        file_put_contents('debug.log', "Ordem ID: $ordem_id\n", FILE_APPEND);

        if (isset($_POST['aceitar'])) {
            file_put_contents('debug.log', "Ação: Aceitar\n", FILE_APPEND);
            $observacao_aceite = trim($_POST['observacao_aceite'] ?? '');

            $stmt = $pdo->prepare("SELECT id FROM ordens_servico_confirmacoes WHERE ordem_id = ? AND tecnico_id = ?");
            $stmt->execute([$ordem_id, $tecnico_id]);
            $confirmacao_existente = $stmt->fetchColumn();

            if ($confirmacao_existente) {
                $stmt = $pdo->prepare("UPDATE ordens_servico_confirmacoes SET acao = 'Aceito', motivo_recusa = NULL, observacao_aceite = ?, data_confirmacao = NOW() WHERE ordem_id = ? AND tecnico_id = ?");
                $stmt->execute([$observacao_aceite, $ordem_id, $tecnico_id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO ordens_servico_confirmacoes (ordem_id, tecnico_id, acao, observacao_aceite) VALUES (?, ?, 'Aceito', ?)");
                $stmt->execute([$ordem_id, $tecnico_id, $observacao_aceite]);
            }
            file_put_contents('debug.log', "Aceitação registrada com observação: $observacao_aceite\n", FILE_APPEND);
            $success = "Serviço aceito com sucesso! O atendente será notificado.";
        } elseif (isset($_POST['recusar'])) {
            file_put_contents('debug.log', "Ação: Recusar\n", FILE_APPEND);
            $motivo_recusa = trim($_POST['motivo_recusa'] ?? '');
            if (empty($motivo_recusa)) {
                $error = "Por favor, informe o motivo da recusa.";
            } else {
                $stmt = $pdo->prepare("SELECT id FROM ordens_servico_confirmacoes WHERE ordem_id = ? AND tecnico_id = ?");
                $stmt->execute([$ordem_id, $tecnico_id]);
                $confirmacao_existente = $stmt->fetchColumn();

                if ($confirmacao_existente) {
                    $stmt = $pdo->prepare("UPDATE ordens_servico_confirmacoes SET acao = 'Recusado', motivo_recusa = ?, observacao_aceite = NULL, data_confirmacao = NOW() WHERE ordem_id = ? AND tecnico_id = ?");
                    $stmt->execute([$motivo_recusa, $ordem_id, $tecnico_id]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO ordens_servico_confirmacoes (ordem_id, tecnico_id, acao, motivo_recusa) VALUES (?, ?, 'Recusado', ?)");
                    $stmt->execute([$ordem_id, $tecnico_id, $motivo_recusa]);
                }
                $stmt = $pdo->prepare("UPDATE ordens_servico SET status = 'Recusado' WHERE id = ? AND tecnico_id = ?");
                $stmt->execute([$ordem_id, $tecnico_id]);
                file_put_contents('debug.log', "Status atualizado para Recusado\n", FILE_APPEND);
                $success = "Serviço recusado com sucesso! O atendente será notificado.";
            }
        } elseif (isset($_POST['remover_aceite'])) {
            file_put_contents('debug.log', "Ação: Remover Aceite\n", FILE_APPEND);
            $stmt = $pdo->prepare("DELETE FROM ordens_servico_confirmacoes WHERE ordem_id = ? AND tecnico_id = ? AND acao = 'Aceito'");
            $stmt->execute([$ordem_id, $tecnico_id]);
            $rows_affected = $stmt->rowCount();
            file_put_contents('debug.log', "Linhas afetadas ao remover aceite: $rows_affected\n", FILE_APPEND);
            if ($rows_affected > 0) {
                $success = "Aceite removido com sucesso!";
            } else {
                $error = "Nenhum aceite encontrado para remover.";
            }
        } elseif (isset($_POST['realizado'])) {
            file_put_contents('debug.log', "Ação: Realizado\n", FILE_APPEND);
            $observacoes_finalizacao = trim($_POST['observacoes_finalizacao'] ?? '');
            $hora_inicio = $_POST['hora_inicio'] ?? null;
            $hora_fim = $_POST['hora_fim'] ?? null;
            $produtos_usados = trim($_POST['produtos_usados'] ?? '');
            $servicos_adicionais = trim($_POST['servicos_adicionais'] ?? '');

            $stmt = $pdo->prepare("
                UPDATE ordens_servico 
                SET status = 'Concluída', 
                    observacoes_finalizacao = ?, 
                    hora_inicio = ?, 
                    hora_fim = ?, 
                    produtos_usados = ?, 
                    servicos_adicionais = ? 
                WHERE id = ? AND tecnico_id = ?
            ");
            $stmt->execute([$observacoes_finalizacao, $hora_inicio, $hora_fim, $produtos_usados, $servicos_adicionais, $ordem_id, $tecnico_id]);
            file_put_contents('debug.log', "Status atualizado para Concluída\n", FILE_APPEND);
            $success = "Serviço realizado com sucesso!";
        } elseif (isset($_POST['cancelar'])) {
            file_put_contents('debug.log', "Ação: Cancelar\n", FILE_APPEND);
            $observacoes_tecnico = trim($_POST['observacoes_tecnico'] ?? '');
            $stmt = $pdo->prepare("UPDATE ordens_servico SET status = 'Cancelada', observacoes_tecnico = ? WHERE id = ? AND tecnico_id = ?");
            $stmt->execute([$observacoes_tecnico, $ordem_id, $tecnico_id]);
            file_put_contents('debug.log', "Status atualizado para Cancelada\n", FILE_APPEND);
            $success = "Serviço cancelado com sucesso!";
        } elseif (isset($_POST['upload_fotos'])) {
            file_put_contents('debug.log', "Ação: Upload Fotos\n", FILE_APPEND);
            if (!empty($_FILES['fotos']['name'][0])) {
                $upload_dir = __DIR__ . "/uploads/fotos_servico/ordem_$ordem_id/";
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                foreach ($_FILES['fotos']['tmp_name'] as $key => $tmp_name) {
                    $file_name = basename($_FILES['fotos']['name'][$key]);
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
                    if (!in_array($file_ext, $allowed_exts)) {
                        $error = "Extensão de arquivo não permitida: $file_name";
                        break;
                    }

                    $new_file_name = uniqid() . '.' . $file_ext;
                    $file_path = $upload_dir . $new_file_name;
                    $file_path_relativo = "/ultralimp/uploads/fotos_servico/ordem_$ordem_id/" . $new_file_name;

                    if (move_uploaded_file($tmp_name, $file_path)) {
                        $stmt = $pdo->prepare("INSERT INTO fotos_servico (ordem_id, caminho_foto, data_upload) VALUES (?, ?, NOW())");
                        $stmt->execute([$ordem_id, $file_path_relativo]);
                        file_put_contents('debug.log', "Foto enviada: $file_path_relativo\n", FILE_APPEND);
                    } else {
                        $error = "Erro ao fazer upload da foto: $file_name";
                        break;
                    }
                }
                if (!$error) {
                    $success = "Fotos enviadas com sucesso!";
                }
            } else {
                $error = "Nenhuma foto selecionada.";
            }
        }
        
        if (!$error) {
            header("Location: " . $_SERVER['PHP_SELF'] . "?success=" . urlencode($success));
            exit();
        }
    } catch (PDOException $e) {
        $error = "Erro ao processar a ação: " . $e->getMessage();
        file_put_contents('debug.log', "Erro: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}

try {
    $stmt = $pdo->prepare("
        SELECT os.*, 
               c.nome AS cliente_nome, 
               c.telefone AS cliente_telefone, 
               c.endereco AS cliente_endereco,
               c.numero AS cliente_numero,
               c.complemento AS cliente_complemento,
               c.bairro AS cliente_bairro,
               c.cidade AS cliente_cidade,
               c.uf AS cliente_uf,
               c.cep AS cliente_cep
        FROM ordens_servico os
        JOIN clientes c ON os.cliente_id = c.id
        WHERE os.tecnico_id = ?
        ORDER BY os.data_servico ASC, os.hora_servico ASC
    ");
    $stmt->execute([$tecnico_id]);
    $ordens_servico = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($ordens_servico as &$ordem) {
        $stmt = $pdo->prepare("SELECT acao, motivo_recusa, observacao_aceite, data_confirmacao FROM ordens_servico_confirmacoes WHERE ordem_id = ? AND tecnico_id = ? ORDER BY data_confirmacao DESC LIMIT 1");
        $stmt->execute([$ordem['id'], $tecnico_id]);
        $ordem['confirmacao'] = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT caminho_foto, data_upload FROM fotos_servico WHERE ordem_id = ?");
        $stmt->execute([$ordem['id']]);
        $ordem['fotos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Verificar se é um retorno
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM historico_ordens WHERE ordem_id = ? AND acao = 'Reaberto para retorno'");
        $stmt->execute([$ordem['id']]);
        $ordem['is_retorno'] = $stmt->fetchColumn() > 0;
    }
    unset($ordem);

} catch (PDOException $e) {
    die("Erro ao buscar ordens de serviço: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Agendamentos - UltraLimp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #2A5C82;
            --secondary: #4CAF50;
            --accent: #FFC107;
            --light: #f8fafc;
            --dark: #1A3C5A;
            --danger: #dc3545;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--light);
            padding-top: 80px;
        }
        .dashboard-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            transition: transform 0.2s;
            padding: 1.5rem;
        }
        .dashboard-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.1);
        }
        .ordem-item {
            border-left: 4px solid;
            padding-left: 10px;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: background-color 0.2s;
            position: relative;
        }
        .ordem-item.agendado { border-color: var(--primary); background-color: rgba(42, 92, 130, 0.05); }
        .ordem-item.aceito { border-color: var(--secondary); background-color: rgba(76, 175, 80, 0.05); }
        .ordem-item.recusado { border-color: var(--danger); background-color: rgba(220, 53, 69, 0.05); }
        .ordem-item.concluída { border-color: var(--secondary); background-color: rgba(76, 175, 80, 0.05); }
        .ordem-item.cancelada { border-color: var(--danger); background-color: rgba(220, 53, 69, 0.05); }
        .ordem-item:hover {
            background-color: rgba(0, 0, 0, 0.05);
        }
        .ordem-item.retorno::after {
            content: "\f021";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            color: var(--danger);
            position: absolute;
            top: 5px;
            right: 10px;
            font-size: 1.2rem;
        }
        .modal-open .dashboard-card:hover {
            transform: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        .btn-primary { background-color: var(--primary); border-color: var(--primary); }
        .btn-primary:hover { background-color: var(--dark); border-color: var(--dark); }
        .btn-success { background-color: var(--secondary); border-color: var(--secondary); }
        .btn-success:hover { background-color: #3d8b40; border-color: #3d8b40; }
        .btn-danger { background-color: var(--danger); border-color: var(--danger); }
        .btn-danger:hover { background-color: #c82333; border-color: #c82333; }
        .btn-warning { background-color: var(--accent); border-color: var(--accent); }
        .btn-warning:hover { background-color: #e0a800; border-color: #e0a800; }
        .btn-retorno {
            background-color: #ff6f61;
            border: none;
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.9rem;
            margin-left: 10px;
            transition: all 0.3s ease;
        }
        .btn-retorno:hover {
            background-color: #ff4535;
            transform: scale(1.05);
        }
        .modal-content {
            border-radius: 15px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
            border: none;
        }
        .modal-header {
            background: linear-gradient(135deg, var(--primary), var(--dark));
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 1.5rem;
        }
        .modal-title {
            font-weight: 600;
            display: flex;
            align-items: center;
        }
        .modal-body {
            padding: 2rem;
            background: #fff;
            border-radius: 0 0 15px 15px;
        }
        .modal-footer {
            border-top: none;
            padding: 1rem 2rem;
        }
        .info-section {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        .info-label {
            font-weight: bold;
            color: var(--primary);
            margin-bottom: 0.25rem;
        }
        .info-text {
            margin-bottom: 0.5rem;
            color: #333;
        }
        .servicos-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 0.5rem;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .servicos-table th, .servicos-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        .servicos-table th {
            background: linear-gradient(135deg, var(--primary), var(--dark));
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9rem;
        }
        .servicos-table td {
            background: white;
            color: #333;
        }
        .servicos-table tr:nth-child(even) td {
            background: #f9f9f9;
        }
        .servicos-table tr:hover td {
            background: #f1f1f1;
        }
        .servicos-table th:first-child {
            border-top-left-radius: 10px;
        }
        .servicos-table th:last-child {
            border-top-right-radius: 10px;
        }
        .servicos-table tr:last-child td:first-child {
            border-bottom-left-radius: 10px;
        }
        .servicos-table tr:last-child td:last-child {
            border-bottom-right-radius: 10px;
        }
        .servicos-table td.text-center {
            text-align: center;
        }
        .servicos-table td.text-end {
            text-align: right;
        }
        .btn-data-hora {
            background-color: var(--accent);
            border: none;
            color: #fff;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-data-hora:hover {
            background-color: #e0a800;
            transform: scale(1.05);
        }
        #motivoRecusaContainer, #observacaoAceiteContainer, #finalizacaoContainer, #uploadFotosContainer {
            display: none;
            margin-top: 1rem;
        }
        .foto-link {
            display: block;
            margin: 5px 0;
            color: var(--primary);
            text-decoration: none;
            font-size: 0.9rem;
        }
        .foto-link:hover {
            text-decoration: underline;
            color: var(--dark);
        }
    </style>
</head>
<body>

<?php require __DIR__ . '/navbar_tecnico.php'; ?>

<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="text-primary fw-bold mb-0">Meus Agendamentos</h2>
        <a href="dashboard.php" class="btn btn-primary"><i class="fas fa-arrow-left me-1"></i>Voltar ao Dashboard</a>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_GET['success']) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="dashboard-card">
        <h4 class="fw-bold mb-3"><i class="fas fa-calendar-check me-2"></i>Ordens de Serviço Designadas</h4>
        <?php if (count($ordens_servico) > 0): ?>
            <?php foreach ($ordens_servico as $ordem): ?>
                <?php
                $status_class = match(strtolower($ordem['status'])) {
                    'agendado' => 'agendado',
                    'aceito' => 'aceito',
                    'recusado' => 'recusado',
                    'concluída' => 'concluída',
                    'cancelada' => 'cancelada',
                    default => 'agendado'
                };
                $servicos = json_decode($ordem['descricao_servicos'] ?? '', true) ?: [];
                $servicos_lista = array_map(function($s) {
                    return htmlspecialchars($s['nome'] ?? '') . ' (Qtd: ' . ($s['quantidade'] ?? 0) . ', R$ ' . number_format($s['valor_unitario'] ?? 0, 2, ',', '.') . ')';
                }, $servicos);
                $servicos_formatados = implode(', ', $servicos_lista);

                $endereco_completo = htmlspecialchars($ordem['cliente_endereco']);
                if (!empty($ordem['cliente_numero'])) {
                    $endereco_completo .= ", " . htmlspecialchars($ordem['cliente_numero']);
                }
                if (!empty($ordem['cliente_complemento'])) {
                    $endereco_completo .= " - " . htmlspecialchars($ordem['cliente_complemento']);
                }
                if (!empty($ordem['cliente_bairro'])) {
                    $endereco_completo .= " - " . htmlspecialchars($ordem['cliente_bairro']);
                }
                if (!empty($ordem['cliente_cidade']) && !empty($ordem['cliente_uf'])) {
                    $endereco_completo .= ", " . htmlspecialchars($ordem['cliente_cidade']) . "/" . htmlspecialchars($ordem['cliente_uf']);
                }
                if (!empty($ordem['cliente_cep'])) {
                    $endereco_completo .= " - CEP: " . htmlspecialchars($ordem['cliente_cep']);
                }
                ?>
                <div class="ordem-item <?= $status_class ?> <?= $ordem['is_retorno'] ? 'retorno' : '' ?>" data-bs-toggle="modal" data-bs-target="#ordemModal_<?= $ordem['id'] ?>">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-semibold">
                                <i class="fas fa-tools me-2"></i>
                                Ordem #<?= htmlspecialchars($ordem['numero_ordem']) ?> - <?= htmlspecialchars($ordem['cliente_nome']) ?>
                                <?php if ($ordem['is_retorno']): ?>
                                    <span class="btn-retorno"><i class="fas fa-undo me-1"></i>Retorno</span>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted">
                                Data: <?= date('d/m/Y', strtotime($ordem['data_servico'])) ?> às <?= $ordem['hora_servico'] ?> 
                                | Status: <?= ucfirst($ordem['status']) ?>
                                <?php if ($ordem['confirmacao']): ?>
                                    | Confirmação: <?= ucfirst($ordem['confirmacao']['acao']) ?>
                                <?php endif; ?>
                            </small>
                        </div>
                        <span class="badge bg-<?= match(strtolower($ordem['status'])) {
                            'agendado' => 'primary',
                            'aceito' => 'success',
                            'recusado' => 'danger',
                            'concluída' => 'success',
                            'cancelada' => 'danger',
                            default => 'secondary'
                        } ?>"><?= ucfirst($ordem['status']) ?></span>
                    </div>
                </div>

                <!-- Modal para Detalhes da Ordem -->
                <div class="modal fade" id="ordemModal_<?= $ordem['id'] ?>" tabindex="-1" aria-labelledby="ordemModalLabel_<?= $ordem['id'] ?>" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="ordemModalLabel_<?= $ordem['id'] ?>">
                                    <i class="fas fa-tools me-2"></i> Ordem de Serviço #<?= htmlspecialchars($ordem['numero_ordem']) ?>
                                    <?php if ($ordem['is_retorno']): ?>
                                        <span class="btn-retorno"><i class="fas fa-undo me-1"></i>Retorno</span>
                                    <?php endif; ?>
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="info-section">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="info-label">Cliente</div>
                                            <div class="info-text"><?= htmlspecialchars($ordem['cliente_nome']) ?></div>
                                            <div class="info-label">Telefone</div>
                                            <div class="info-text"><?= htmlspecialchars($ordem['cliente_telefone']) ?></div>
                                            <div class="info-label">Endereço Completo</div>
                                            <div class="info-text"><?= $endereco_completo ?></div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="info-label">Data e Hora do Serviço</div>
                                            <button class="btn-data-hora">
                                                <i class="fas fa-calendar-alt me-2"></i>
                                                <?= date('d/m/Y', strtotime($ordem['data_servico'])) ?> às <?= $ordem['hora_servico'] ?>
                                            </button>
                                            <div class="info-label mt-3">Previsão de Conclusão</div>
                                            <div class="info-text"><?= date('d/m/Y', strtotime($ordem['previsao_conclusao'])) ?></div>
                                            <div class="info-label">Status</div>
                                            <div class="info-text"><?= ucfirst($ordem['status']) ?></div>
                                            <div class="info-label">Valor Total</div>
                                            <div class="info-text">R$ <?= number_format($ordem['total'], 2, ',', '.') ?></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="info-section">
                                    <h6 class="info-label mb-2">Serviços a Executar</h6>
                                    <?php if (count($servicos) > 0): ?>
                                        <table class="servicos-table">
                                            <thead>
                                                <tr>
                                                    <th>Serviço</th>
                                                    <th>Quantidade</th>
                                                    <th>Valor Unitário</th>
                                                    <th>Subtotal</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($servicos as $servico): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($servico['nome'] ?? '') ?></td>
                                                        <td class="text-center"><?= $servico['quantidade'] ?? 0 ?></td>
                                                        <td class="text-end">R$ <?= number_format($servico['valor_unitario'] ?? 0, 2, ',', '.') ?></td>
                                                        <td class="text-end">R$ <?= number_format(($servico['quantidade'] ?? 0) * ($servico['valor_unitario'] ?? 0), 2, ',', '.') ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php else: ?>
                                        <p class="info-text">Nenhum serviço listado.</p>
                                    <?php endif; ?>
                                </div>

                                <?php if ($ordem['observacoes']): ?>
                                    <div class="info-section">
                                        <h6 class="info-label mb-2">Observações</h6>
                                        <div class="info-text"><?= htmlspecialchars($ordem['observacoes']) ?></div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($ordem['informacoes_extras']): ?>
                                    <div class="info-section">
                                        <h6 class="info-label mb-2">Informações Extras</h6>
                                        <div class="info-text"><?= htmlspecialchars($ordem['informacoes_extras']) ?></div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($ordem['confirmacao']): ?>
                                    <div class="info-section">
                                        <h6 class="info-label mb-2">Confirmação do Técnico</h6>
                                        <div class="info-text">
                                            <strong>Ação:</strong> <?= ucfirst($ordem['confirmacao']['acao']) ?><br>
                                            <?php if ($ordem['confirmacao']['motivo_recusa']): ?>
                                                <strong>Motivo da Recusa:</strong> <?= htmlspecialchars($ordem['confirmacao']['motivo_recusa']) ?><br>
                                            <?php endif; ?>
                                            <?php if ($ordem['confirmacao']['observacao_aceite']): ?>
                                                <strong>Observação ao Aceitar:</strong> <?= htmlspecialchars($ordem['confirmacao']['observacao_aceite']) ?><br>
                                            <?php endif; ?>
                                            <strong>Data:</strong> <?= date('d/m/Y H:i', strtotime($ordem['confirmacao']['data_confirmacao'])) ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($ordem['observacoes_tecnico']): ?>
                                    <div class="info-section">
                                        <h6 class="info-label mb-2">Observações do Técnico</h6>
                                        <div class="info-text"><?= htmlspecialchars($ordem['observacoes_tecnico']) ?></div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($ordem['observacoes_finalizacao'] || $ordem['hora_inicio'] || $ordem['hora_fim'] || $ordem['produtos_usados'] || $ordem['servicos_adicionais']): ?>
                                    <div class="info-section">
                                        <h6 class="info-label mb-2">Detalhes da Finalização</h6>
                                        <div class="info-text">
                                            <?php if ($ordem['observacoes_finalizacao']): ?>
                                                <strong>Observações:</strong> <?= htmlspecialchars($ordem['observacoes_finalizacao']) ?><br>
                                            <?php endif; ?>
                                            <?php if ($ordem['hora_inicio']): ?>
                                                <strong>Hora de Início:</strong> <?= htmlspecialchars($ordem['hora_inicio']) ?><br>
                                            <?php endif; ?>
                                            <?php if ($ordem['hora_fim']): ?>
                                                <strong>Hora de Fim:</strong> <?= htmlspecialchars($ordem['hora_fim']) ?><br>
                                            <?php endif; ?>
                                            <?php if ($ordem['produtos_usados']): ?>
                                                <strong>Produtos Usados:</strong> <?= htmlspecialchars($ordem['produtos_usados']) ?><br>
                                            <?php endif; ?>
                                            <?php if ($ordem['servicos_adicionais']): ?>
                                                <strong>Serviços Adicionais:</strong> <?= htmlspecialchars($ordem['servicos_adicionais']) ?><br>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($ordem['caminho_pdf']): ?>
                                    <div class="info-section">
                                        <h6 class="info-label mb-2">PDF da Ordem</h6>
                                        <a href="<?= htmlspecialchars($ordem['caminho_pdf']) ?>" target="_blank" class="btn btn-primary">
                                            <i class="fas fa-file-pdf me-2"></i>Visualizar PDF
                                        </a>
                                    </div>
                                <?php endif; ?>

                                <?php if (count($ordem['fotos']) > 0): ?>
                                    <div class="info-section">
                                        <h6 class="info-label mb-2">Fotos do Serviço</h6>
                                        <div>
                                            <?php foreach ($ordem['fotos'] as $index => $foto): ?>
                                                <a href="<?= htmlspecialchars($foto['caminho_foto']) ?>" target="_blank" class="foto-link">
                                                    <i class="fas fa-image me-1"></i>Foto <?= $index + 1 ?> (<?= date('d/m/Y H:i', strtotime($foto['data_upload'])) ?>)
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Botões de Ação -->
                                <?php if (in_array(strtolower($ordem['status']), ['agendado']) && (!$ordem['confirmacao'] || $ordem['confirmacao']['acao'] !== 'Aceito')): ?>
                                    <div class="info-section">
                                        <h6 class="info-label mb-2">Confirmação</h6>
                                        <!-- Formulário para Aceitar -->
                                        <form method="POST" action="" class="d-inline-block">
                                            <input type="hidden" name="ordem_id" value="<?= $ordem['id'] ?>">
                                            <button type="button" class="btn btn-success me-2" onclick="toggleObservacaoAceite('observacaoAceiteContainer_<?= $ordem['id'] ?>')">
                                                <i class="fas fa-check me-2"></i>Aceitar
                                            </button>
                                            <div id="observacaoAceiteContainer_<?= $ordem['id'] ?>" class="mt-2" style="display: none;">
                                                <textarea name="observacao_aceite" class="form-control" rows="3" placeholder="Informe uma observação (opcional, ex.: Pode atrasar, Estou com pouco produto)"></textarea>
                                                <button type="submit" name="aceitar" class="btn btn-success mt-2">
                                                    <i class="fas fa-save me-2"></i>Confirmar Aceite
                                                </button>
                                            </div>
                                        </form>
                                        <!-- Formulário para Recusar -->
                                        <form method="POST" action="" class="d-inline-block">
                                            <input type="hidden" name="ordem_id" value="<?= $ordem['id'] ?>">
                                            <button type="button" class="btn btn-danger" onclick="toggleMotivoRecusa('motivoRecusaContainer_<?= $ordem['id'] ?>')">
                                                <i class="fas fa-times me-2"></i>Recusar
                                            </button>
                                            <div id="motivoRecusaContainer_<?= $ordem['id'] ?>" class="mt-2" style="display: none;">
                                                <textarea name="motivo_recusa" class="form-control" rows="3" placeholder="Informe o motivo da recusa" required></textarea>
                                                <button type="submit" name="recusar" class="btn btn-danger mt-2">
                                                    <i class="fas fa-save me-2"></i>Confirmar Recusa
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                <?php elseif (in_array(strtolower($ordem['status']), ['agendado']) && $ordem['confirmacao'] && $ordem['confirmacao']['acao'] === 'Aceito'): ?>
                                    <div class="info-section">
                                        <h6 class="info-label mb-2">Ação</h6>
                                        <!-- Formulário para Serviço Realizado -->
                                        <form method="POST" action="" class="d-inline-block">
                                            <input type="hidden" name="ordem_id" value="<?= $ordem['id'] ?>">
                                            <button type="button" class="btn btn-success me-2" onclick="toggleFinalizacao('finalizacaoContainer_<?= $ordem['id'] ?>')">
                                                <i class="fas fa-check-circle me-2"></i>Serviço Realizado
                                            </button>
                                            <div id="finalizacaoContainer_<?= $ordem['id'] ?>" class="mt-2" style="display: none;">
                                                <div class="mb-3">
                                                    <label for="hora_inicio_<?= $ordem['id'] ?>" class="form-label">Hora de Início</label>
                                                    <input type="time" name="hora_inicio" id="hora_inicio_<?= $ordem['id'] ?>" class="form-control" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="hora_fim_<?= $ordem['id'] ?>" class="form-label">Hora de Fim</label>
                                                    <input type="time" name="hora_fim" id="hora_fim_<?= $ordem['id'] ?>" class="form-control" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="produtos_usados_<?= $ordem['id'] ?>" class="form-label">Produtos Usados</label>
                                                    <textarea name="produtos_usados" id="produtos_usados_<?= $ordem['id'] ?>" class="form-control" rows="2" placeholder="Ex.: Limpador Pluri com Alvfres - 500ml"></textarea>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="servicos_adicionais_<?= $ordem['id'] ?>" class="form-label">Serviços Adicionais</label>
                                                    <textarea name="servicos_adicionais" id="servicos_adicionais_<?= $ordem['id'] ?>" class="form-control" rows="2" placeholder="Ex.: Limpeza de um sofá adicional"></textarea>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="observacoes_finalizacao_<?= $ordem['id'] ?>" class="form-label">Observações para o Atendente</label>
                                                    <textarea name="observacoes_finalizacao" id="observacoes_finalizacao_<?= $ordem['id'] ?>" class="form-control" rows="3" placeholder="Observações adicionais (opcional)"></textarea>
                                                </div>
                                                <button type="submit" name="realizado" class="btn btn-success">
                                                    <i class="fas fa-save me-2"></i>Confirmar Finalização
                                                </button>
                                            </div>
                                        </form>
                                        <!-- Formulário para Cancelar -->
                                        <form method="POST" action="" class="d-inline-block">
                                            <input type="hidden" name="ordem_id" value="<?= $ordem['id'] ?>">
                                            <button type="submit" name="cancelar" class="btn btn-danger me-2">
                                                <i class="fas fa-times-circle me-2"></i>Cancelar
                                            </button>
                                        </form>
                                        <!-- Formulário para Remover Aceite -->
                                        <form method="POST" action="" class="d-inline-block">
                                            <input type="hidden" name="ordem_id" value="<?= $ordem['id'] ?>">
                                            <button type="submit" name="remover_aceite" class="btn btn-warning">
                                                <i class="fas fa-undo me-2"></i>Remover Aceite
                                            </button>
                                        </form>
                                    </div>
                                <?php elseif (strtolower($ordem['status']) === 'concluída'): ?>
                                    <div class="info-section">
                                        <h6 class="info-label mb-2">Ação</h6>
                                        <form method="POST" action="" enctype="multipart/form-data">
                                            <input type="hidden" name="ordem_id" value="<?= $ordem['id'] ?>">
                                            <button type="button" class="btn btn-warning me-2" onclick="toggleUploadFotos('uploadFotosContainer_<?= $ordem['id'] ?>')">
                                                <i class="fas fa-camera me-2"></i>Enviar Fotos do Serviço
                                            </button>
                                            <div id="uploadFotosContainer_<?= $ordem['id'] ?>" class="mt-2" style="display: none;">
                                                <div class="mb-3">
                                                    <label for="fotos_<?= $ordem['id'] ?>" class="form-label">Selecionar Fotos</label>
                                                    <input type="file" name="fotos[]" id="fotos_<?= $ordem['id'] ?>" class="form-control" multiple accept="image/*" required>
                                                </div>
                                                <button type="submit" name="upload_fotos" class="btn btn-warning">
                                                    <i class="fas fa-upload me-2"></i>Enviar
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alert alert-info">Nenhuma ordem de serviço designada no momento.</div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleMotivoRecusa(id) {
    const container = document.getElementById(id);
    container.style.display = container.style.display === 'none' ? 'block' : 'none';
}

function toggleObservacaoAceite(id) {
    const container = document.getElementById(id);
    container.style.display = container.style.display === 'none' ? 'block' : 'none';
}

function toggleFinalizacao(id) {
    const container = document.getElementById(id);
    container.style.display = container.style.display === 'none' ? 'block' : 'none';
}

function toggleUploadFotos(id) {
    const container = document.getElementById(id);
    container.style.display = container.style.display === 'none' ? 'block' : 'none';
}
</script>
</body>
</html>