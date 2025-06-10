<?php
session_start();
require_once 'config.php';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Processar exclusão de ordens
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    try {
        // Iniciar transação para garantir consistência
        $pdo->beginTransaction();

        // Deletar registros relacionados em ordens_servico_confirmacoes
        $stmt = $pdo->prepare("DELETE FROM ordens_servico_confirmacoes WHERE ordem_id = ?");
        $stmt->execute([$delete_id]);

        // Deletar registros relacionados em fotos_servico
        $stmt = $pdo->prepare("DELETE FROM fotos_servico WHERE ordem_id = ?");
        $stmt->execute([$delete_id]);

        // Deletar registros relacionados em historico_ordens (NOVA ETAPA)
        $stmt = $pdo->prepare("DELETE FROM historico_ordens WHERE ordem_id = ?");
        $stmt->execute([$delete_id]);

        // Deletar registros em agenda_servicos
        $stmt = $pdo->prepare("DELETE FROM agenda_servicos WHERE ordem_id = ?");
        $stmt->execute([$delete_id]);

        // Deletar a ordem em ordens_servico
        $stmt = $pdo->prepare("DELETE FROM ordens_servico WHERE id = ?");
        $stmt->execute([$delete_id]);

        // Confirmar transação
        $pdo->commit();

        header("Location: lista_ordens_servico.php?success=Ordem+excluída+com+sucesso");
        exit;
    } catch (PDOException $e) {
        // Reverter transação em caso de erro
        $pdo->rollBack();
        die("Erro ao excluir ordem: " . $e->getMessage());
    }
}

// Processar alteração de status
if (isset($_POST['alterar_status'])) {
    $ordem_id = (int)$_POST['ordem_id'];
    $novo_status = $_POST['status'];
    $valid_statuses = ['Aberta', 'Agendado', 'Em Andamento', 'Concluída', 'Atrasado', 'Cancelada'];
    
    if (in_array($novo_status, $valid_statuses)) {
        try {
            // Iniciar transação
            $pdo->beginTransaction();

            // Atualizar status na tabela ordens_servico
            $stmt = $pdo->prepare("UPDATE ordens_servico SET status = ? WHERE id = ?");
            $stmt->execute([$novo_status, $ordem_id]);

            // Atualizar status na tabela orcamentos associada
            $stmt = $pdo->prepare("UPDATE orcamentos SET status = ? WHERE id = (SELECT orcamento_id FROM ordens_servico WHERE id = ?)");
            $stmt->execute([$novo_status, $ordem_id]);

            // Confirmar transação
            $pdo->commit();

            header("Location: lista_ordens_servico.php?success=Status+alterado+com+sucesso");
            exit;
        } catch (PDOException $e) {
            // Reverter transação em caso de erro
            $pdo->rollBack();
            die("Erro ao alterar status: " . $e->getMessage());
        }
    }
}

// Carregar ordens com dados
try {
    $stmt = $pdo->prepare("
        SELECT 
            os.id,
            os.numero_ordem,
            os.data_emissao,
            os.data_servico,
            os.hora_servico,
            u.nome AS tecnico_nome,
            os.status,
            c.nome AS cliente_nome,
            IFNULL(o.total, 0) AS orcamento_total,
            os.caminho_pdf
        FROM ordens_servico os
        LEFT JOIN clientes c ON os.cliente_id = c.id
        LEFT JOIN orcamentos o ON os.orcamento_id = o.id
        LEFT JOIN usuarios u ON os.tecnico_id = u.id
        ORDER BY os.data_emissao DESC
    ");
    $stmt->execute();
    $ordens = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao carregar ordens: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Ordens - UltraLimp</title>
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
            --status-aberto: #17A2B8;
            --status-agendado: #007BFF;
            --status-andamento: #FFC107;
            --status-concluido: #28A745;
            --status-atrasado: #DC3545;
            --status-cancelado: #6C757D;
        }
        body { font-family: 'Inter', sans-serif; background: var(--light); }
        .dashboard-card { background: white; border-radius: 12px; box-shadow: var(--shadow); padding: 1.5rem; transition: transform 0.3s ease; border: 1px solid rgba(203, 213, 225, 0.3); backdrop-filter: blur(4px); }
        .dashboard-card:hover { transform: translateY(-5px); box-shadow: 0 8px 30px rgba(37, 99, 235, 0.15); }
        .table-modern { --bs-table-bg: transparent; --bs-table-striped-bg: #f8fafc; margin-bottom: 0; border-collapse: separate; border-spacing: 0 8px; }
        .table-modern thead th { background: var(--header-gradient); color: white; border: none; padding: 1.2rem 1.5rem; font-weight: 600; letter-spacing: 0.5px; position: relative; }
        .table-modern thead th:first-child { border-radius: 12px 0 0 12px; }
        .table-modern thead th:last-child { border-radius: 0 12px 12px 0; }
        .table-modern thead th:not(:last-child)::after { content: ''; position: absolute; right: 0; top: 25%; height: 50%; width: 1px; background: rgba(255,255,255,0.2); }
        .table-modern tbody td { padding: 1.2rem 1.5rem; background: white; border: none; vertical-align: middle; transition: all 0.2s ease; }
        .table-modern tbody tr { box-shadow: 0 2px 8px rgba(0,0,0,0.05); border-radius: 8px; }
        .table-modern tbody tr:hover td { background: #F8FAFC; transform: translateX(8px); }
        .status-badge { padding: 0.3rem 1rem; border-radius: 20px; font-size: 0.85rem; font-weight: 500; }
        .status-aberto { background-color: var(--status-aberto); color: white; }
        .status-agendado { background-color: var(--status-agendado); color: white; }
        .status-andamento { background-color: var(--status-andamento); color: black; }
        .status-concluido { background-color: var(--status-concluido); color: white; }
        .status-atrasado { background-color: var(--status-atrasado); color: white; }
        .status-cancelado { background-color: var(--status-cancelado); color: white; }
        .column-icon { margin-right: 10px; font-size: 0.95em; opacity: 0.9; }
        @media (max-width: 768px) {
            .table-modern thead th { padding: 0.8rem; font-size: 0.9rem; }
            .table-modern tbody td { padding: 0.8rem; font-size: 0.9rem; }
            .column-icon { display: none; }
        }
        @media (max-width: 480px) {
            .table-modern tbody td { display: flex; flex-direction: column; align-items: start; gap: 0.5rem; }
            .table-modern tbody td::before { content: attr(data-label); font-weight: 600; color: var(--primary); font-size: 0.8rem; }
            .table-modern thead { display: none; }
            .table-modern tbody tr { display: block; margin-bottom: 1rem; }
            .table-modern tbody td { border-bottom: 1px solid #f1f5f9; }
            .table-modern tbody td:last-child { border-bottom: none; }
        }
    </style>
</head>
<body>
    <?php require __DIR__ . '/navbar.php'; ?>

    <div class="container-fluid">
        <div class="header-section mb-4">
            <h2 class="text-dark fw-bold"><i class="fas fa-concierge-bell me-2"></i>Gestão de Ordens de Serviço</h2>
            <a href="criar_ordem_servico.php" class="btn btn-primary px-4 py-2">
                <i class="fas fa-plus me-2"></i>Nova Ordem
            </a>
        </div>

        <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show dashboard-card" role="alert">
            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars(urldecode($_GET['success'])) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <div class="dashboard-card">
            <div class="table-responsive">
                <table class="table table-modern table-hover">
                    <thead>
                        <tr>
                            <th><i class="fas fa-hashtag column-icon"></i>Nº Ordem</th>
                            <th><i class="fas fa-user column-icon"></i>Cliente</th>
                            <th><i class="fas fa-calendar-day column-icon"></i>Emissão</th>
                            <th><i class="fas fa-calendar-check column-icon"></i>Serviço</th>
                            <th><i class="fas fa-clock column-icon"></i>Hora</th>
                            <th><i class="fas fa-user-tie column-icon"></i>Técnico</th>
                            <th><i class="fas fa-tasks column-icon"></i>Status</th>
                            <th><i class="fas fa-dollar-sign column-icon"></i>Valor</th>
                            <th><i class="fas fa-cog column-icon"></i>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ordens)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-4">Nenhuma ordem cadastrada</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($ordens as $ordem): ?>
                                <tr>
                                    <td data-label="Nº Ordem"><?= htmlspecialchars($ordem['numero_ordem']) ?></td>
                                    <td data-label="Cliente"><?= htmlspecialchars($ordem['cliente_nome']) ?></td>
                                    <td data-label="Emissão"><?= date('d/m/Y', strtotime($ordem['data_emissao'])) ?></td>
                                    <td data-label="Serviço">
                                        <?= $ordem['data_servico'] ? date('d/m/Y', strtotime($ordem['data_servico'])) : '<span class="text-muted">N/A</span>' ?>
                                    </td>
                                    <td data-label="Hora">
                                        <?= $ordem['hora_servico'] ? htmlspecialchars($ordem['hora_servico']) : '<span class="text-muted">N/A</span>' ?>
                                    </td>
                                    <td data-label="Técnico"><?= htmlspecialchars($ordem['tecnico_nome'] ?? 'Não atribuído') ?></td>
                                    <td data-label="Status">
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="ordem_id" value="<?= $ordem['id'] ?>">
                                            <select name="status" class="form-select form-select-sm status-badge" onchange="this.form.submit()">
                                                <?php
                                                $status_options = ['Aberta', 'Agendado', 'Em Andamento', 'Concluída', 'Atrasado', 'Cancelada'];
                                                foreach ($status_options as $option) {
                                                    $selected = $ordem['status'] === $option ? 'selected' : '';
                                                    $class = match($option) {
                                                        'Aberta' => 'status-aberto',
                                                        'Agendado' => 'status-agendado',
                                                        'Em Andamento' => 'status-andamento',
                                                        'Concluída' => 'status-concluido',
                                                        'Atrasado' => 'status-atrasado',
                                                        'Cancelada' => 'status-cancelado'
                                                    };
                                                    echo "<option value='$option' class='$class' $selected>$option</option>";
                                                }
                                                ?>
                                            </select>
                                            <input type="hidden" name="alterar_status" value="1">
                                        </form>
                                    </td>
                                    <td data-label="Valor">R$ <?= number_format($ordem['orcamento_total'], 2, ',', '.') ?></td>
                                    <td data-label="Ações">
                                        <div class="d-flex flex-wrap gap-2">
                                            <a href="editar_ordem.php?id=<?= $ordem['id'] ?>" class="btn btn-sm btn-primary px-3 py-2 rounded-pill">
                                                <i class="fas fa-edit me-1"></i><span class="d-none d-md-inline">Editar</span>
                                            </a>
                                            <a href="<?= $ordem['caminho_pdf'] ?: "gerar_pdf_os.php?id={$ordem['id']}" ?>" class="btn btn-sm btn-success px-3 py-2 rounded-pill" target="_blank">
                                                <i class="fas fa-file-pdf me-1"></i><span class="d-none d-md-inline">Ver PDF</span>
                                            </a>
                                            <a href="?delete_id=<?= $ordem['id'] ?>" class="btn btn-sm btn-danger px-3 py-2 rounded-pill" onclick="return confirm('Tem certeza que deseja excluir esta ordem?')">
                                                <i class="fas fa-trash me-1"></i><span class="d-none d-md-inline">Excluir</span>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>