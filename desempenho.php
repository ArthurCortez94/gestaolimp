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

// Verificar se é uma requisição AJAX para detalhes do técnico
if (isset($_GET['action']) && $_GET['action'] === 'get_tecnico_details') {
    $tecnico_id = (int)$_GET['tecnico_id'];
    try {
        $stmt = $pdo->prepare("
            SELECT 
                u.nome,
                COUNT(os.id) AS total_servicos,
                SUM(os.total) AS total_valor,
                SUM(d.valor_pago) AS total_pago,
                (SUM(os.total) - SUM(COALESCE(d.valor_pago, 0))) AS lucro_total,
                SUM(CASE WHEN os.status = 'Concluída' THEN 1 ELSE 0 END) AS concluidos,
                SUM(CASE WHEN os.status = 'Aberta' THEN 1 ELSE 0 END) AS pendentes,
                SUM(CASE WHEN os.status = 'Atrasado' THEN 1 ELSE 0 END) AS atrasados
            FROM usuarios u
            LEFT JOIN ordens_servico os ON u.id = os.tecnico_id
            LEFT JOIN diarias d ON d.ordem_id = os.id AND d.tecnico_id = u.id
            WHERE u.id = ? AND u.cargo = 'tecnico'
            GROUP BY u.id, u.nome
        ");
        $stmt->execute([$tecnico_id]);
        $detalhes = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($detalhes) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => [
                    'nome' => $detalhes['nome'],
                    'total_servicos' => $detalhes['total_servicos'],
                    'total_valor' => number_format($detalhes['total_valor'] ?? 0, 2, ',', '.'),
                    'total_pago' => number_format($detalhes['total_pago'] ?? 0, 2, ',', '.'),
                    'lucro_total' => number_format($detalhes['lucro_total'] ?? 0, 2, ',', '.'),
                    'concluidos' => $detalhes['concluidos'],
                    'pendentes' => $detalhes['pendentes'],
                    'atrasados' => $detalhes['atrasados']
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Técnico não encontrado']);
        }
        exit;
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao carregar detalhes: ' . $e->getMessage()]);
        exit;
    }
}

// Consulta principal para o relatório
try {
    $tecnicos = $pdo->query("
        SELECT 
            u.id,
            u.nome,
            u.data_cadastro,
            COUNT(os.id) AS total_servicos,
            SUM(os.total) AS total_valor,
            AVG(os.total) AS media_valor,
            SUM(CASE WHEN os.status = 'Concluída' THEN 1 ELSE 0 END) AS concluidos,
            SUM(CASE WHEN os.status = 'Aberta' THEN 1 ELSE 0 END) AS pendentes,
            SUM(CASE WHEN os.status = 'Atrasado' THEN 1 ELSE 0 END) AS atrasados,
            MIN(os.data_servico) AS primeiro_servico,
            MAX(os.data_servico) AS ultimo_servico,
            SUM(d.valor_pago) AS total_pago,
            (SUM(os.total) - SUM(COALESCE(d.valor_pago, 0))) AS lucro_total
        FROM usuarios u
        LEFT JOIN ordens_servico os ON u.id = os.tecnico_id
        LEFT JOIN diarias d ON d.ordem_id = os.id AND d.tecnico_id = u.id
        WHERE u.cargo = 'tecnico'
        GROUP BY u.id, u.nome, u.data_cadastro
        ORDER BY total_valor DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao carregar dados: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Técnicos - UltraLimp</title>
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

    .table-tecnicos {
        background: #f8fafc;
        border-radius: 8px;
        overflow: hidden;
    }

    .table-tecnicos th {
        background: var(--primary);
        color: white;
        border: none;
    }

    .table-tecnicos td {
        vertical-align: middle;
        border-bottom: 1px solid #ddd;
    }

    .progress-bar-custom {
        background-color: var(--secondary);
    }

    .total-geral {
        background: var(--primary);
        color: white;
        padding: 1rem;
        border-radius: 8px;
        font-size: 1.2rem;
        margin-top: 1.5rem;
    }

    @media (max-width: 768px) {
        .dashboard-card {
            padding: 1rem;
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
        
        .table-tecnicos {
            font-size: 0.9rem;
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
                <h2 class="mb-0"><i class="fas fa-users-cog me-2"></i>Relatório de Técnicos</h2>
                <div class="d-flex gap-2">
                    <a href="dashboard.php" class="btn btn-outline-light">
                        <i class="fas fa-arrow-left me-1"></i>Voltar ao Dashboard
                    </a>
                </div>
            </div>
        </div>

        <!-- Seção de Relatório -->
        <div class="dashboard-card">
            <h4><i class="fas fa-chart-bar me-2"></i>Dados dos Técnicos</h4>
            <table class="table table-tecnicos">
                <thead>
                    <tr>
                        <th>Técnico</th>
                        <th>Total Serviços</th>
                        <th>Valor Total</th>
                        <th>Total Pago</th>
                        <th>Lucro Líquido</th>
                        <th>Média por Serviço</th>
                        <th>Taxa Conclusão</th>
                        <th>Último Serviço</th>
                        <th>Detalhes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tecnicos as $tecnico): ?>
                    <tr>
                        <td>
                            <?= htmlspecialchars($tecnico['nome']) ?>
                            <br>
                            <small class="text-muted">Cadastrado em: <?= date('d/m/Y', strtotime($tecnico['data_cadastro'])) ?></small>
                        </td>
                        <td><?= $tecnico['total_servicos'] ?></td>
                        <td>R$ <?= number_format($tecnico['total_valor'] ?? 0, 2, ',', '.') ?></td>
                        <td>R$ <?= number_format($tecnico['total_pago'] ?? 0, 2, ',', '.') ?></td>
                        <td>R$ <?= number_format($tecnico['lucro_total'] ?? 0, 2, ',', '.') ?></td>
                        <td>R$ <?= number_format($tecnico['media_valor'] ?? 0, 2, ',', '.') ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress w-100">
                                    <?php $percent = $tecnico['total_servicos'] > 0 ? ($tecnico['concluidos'] / $tecnico['total_servicos']) * 100 : 0; ?>
                                    <div class="progress-bar progress-bar-custom" 
                                         style="width: <?= $percent ?>%">
                                    </div>
                                </div>
                                <span><?= round($percent) ?>%</span>
                            </div>
                        </td>
                        <td><?= $tecnico['ultimo_servico'] ? date('d/m/Y', strtotime($tecnico['ultimo_servico'])) : 'N/A' ?></td>
                        <td>
                            <button class="btn btn-sm btn-primary" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#detalhesTecnico"
                                    data-id="<?= $tecnico['id'] ?>">
                                <i class="fas fa-search"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Modal de Detalhes -->
        <div class="modal fade" id="detalhesTecnico" tabindex="-1" aria-labelledby="detalhesTecnicoLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="detalhesTecnicoLabel">Detalhes do Técnico</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="loadingMessage">Carregando detalhes do técnico...</div>
                        <div id="tecnicoDetails" style="display: none;">
                            <h6 id="tecnicoNome"></h6>
                            <div class="row">
                                <div class="col-md-4">
                                    <p><strong>Total de Serviços:</strong> <span id="totalServicos"></span></p>
                                    <p><strong>Valor Total Gerado:</strong> R$ <span id="totalValor"></span></p>
                                    <p><strong>Total Pago:</strong> R$ <span id="totalPago"></span></p>
                                </div>
                                <div class="col-md-4">
                                    <p><strong>Lucro Líquido:</strong> R$ <span id="lucroTotal"></span></p>
                                    <p><strong>Serviços Concluídos:</strong> <span id="concluidos"></span></p>
                                    <p><strong>Serviços Pendentes:</strong> <span id="pendentes"></span></p>
                                </div>
                                <div class="col-md-4">
                                    <p><strong>Serviços Atrasados:</strong> <span id="atrasados"></span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.querySelectorAll('.btn-primary[data-bs-target="#detalhesTecnico"]').forEach(button => {
        button.addEventListener('click', function() {
            const tecnicoId = this.getAttribute('data-id');
            const modalBody = document.querySelector('#detalhesTecnico .modal-body');
            const loadingMessage = document.getElementById('loadingMessage');
            const tecnicoDetails = document.getElementById('tecnicoDetails');

            // Mostrar mensagem de carregamento
            loadingMessage.style.display = 'block';
            tecnicoDetails.style.display = 'none';

            // Requisição AJAX para buscar os detalhes
            fetch(`?action=get_tecnico_details&tecnico_id=${tecnicoId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Preencher os dados no modal
                        document.getElementById('tecnicoNome').textContent = data.data.nome;
                        document.getElementById('totalServicos').textContent = data.data.total_servicos;
                        document.getElementById('totalValor').textContent = data.data.total_valor;
                        document.getElementById('totalPago').textContent = data.data.total_pago;
                        document.getElementById('lucroTotal').textContent = data.data.lucro_total;
                        document.getElementById('concluidos').textContent = data.data.concluidos;
                        document.getElementById('pendentes').textContent = data.data.pendentes;
                        document.getElementById('atrasados').textContent = data.data.atrasados;

                        // Esconder mensagem de carregamento e mostrar os detalhes
                        loadingMessage.style.display = 'none';
                        tecnicoDetails.style.display = 'block';
                    } else {
                        loadingMessage.textContent = data.message || 'Erro ao carregar os detalhes.';
                    }
                })
                .catch(error => {
                    loadingMessage.textContent = 'Erro ao carregar os detalhes: ' + error.message;
                });
        });
    });
    </script>
</body>
</html>