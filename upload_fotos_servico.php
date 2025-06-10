<?php
ob_start();
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_cargo'] !== 'tecnico') {
    header("Location: login.php");
    exit();
}

$tecnico_id = $_SESSION['user_id'];
$ordem_id = (int)$_GET['ordem_id'] ?? 0;
$error = '';
$success = '';

// Verificar se a ordem pertence ao técnico e está concluída
$stmt = $pdo->prepare("SELECT numero_ordem, status FROM ordens_servico WHERE id = ? AND tecnico_id = ? AND status = 'Concluída'");
$stmt->execute([$ordem_id, $tecnico_id]);
$ordem = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$ordem) {
    die("Ordem de serviço não encontrada ou não está concluída.");
}

// Processar upload de fotos
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!empty($_FILES['fotos']['name'][0])) {
            $uploadDir = __DIR__ . "/uploads/fotos_servico/Ordem_{$ordem['numero_ordem']}/";
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            foreach ($_FILES['fotos']['tmp_name'] as $key => $tmp_name) {
                $fileName = uniqid() . '_' . basename($_FILES['fotos']['name'][$key]);
                $filePath = $uploadDir . $fileName;
                $filePathRelativo = "/ultralimp/uploads/fotos_servico/Ordem_{$ordem['numero_ordem']}/" . $fileName;

                if (move_uploaded_file($tmp_name, $filePath)) {
                    $stmt = $pdo->prepare("INSERT INTO fotos_servico (ordem_id, caminho_foto) VALUES (?, ?)");
                    $stmt->execute([$ordem_id, $filePathRelativo]);
                } else {
                    $error = "Erro ao fazer upload de uma ou mais fotos.";
                }
            }
            if (!$error) {
                $success = "Fotos enviadas com sucesso!";
            }
        } else {
            $error = "Selecione pelo menos uma foto.";
        }
    } catch (PDOException $e) {
        $error = "Erro ao salvar fotos: " . $e->getMessage();
    }
}

// Buscar fotos existentes
$stmt = $pdo->prepare("SELECT caminho_foto, data_upload FROM fotos_servico WHERE ordem_id = ?");
$stmt->execute([$ordem_id]);
$fotos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enviar Fotos - Ordem #<?= htmlspecialchars($ordem['numero_ordem']) ?> - UltraLimp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #2A5C82;
            --secondary: #4CAF50;
            --accent: #FFC107;
            --light: #f8fafc;
            --dark: #1A3C5A;
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
            padding: 1.5rem;
        }
        .btn-primary { background-color: var(--primary); border-color: var(--primary); }
        .btn-primary:hover { background-color: var(--dark); border-color: var(--dark); }
        .foto-preview { max-width: 200px; margin: 10px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    </style>
</head>
<body>

<?php require __DIR__ . '/navbar_tecnico.php'; ?>

<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="text-primary fw-bold mb-0">Enviar Fotos - Ordem #<?= htmlspecialchars($ordem['numero_ordem']) ?></h2>
        <a href="agendamento_tecnico.php" class="btn btn-primary"><i class="fas fa-arrow-left me-1"></i>Voltar aos Agendamentos</a>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="dashboard-card">
        <h4 class="fw-bold mb-3"><i class="fas fa-camera me-2"></i>Upload de Fotos</h4>
        <form method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="fotos" class="form-label">Selecione as Fotos</label>
                <input type="file" name="fotos[]" id="fotos" class="form-control" multiple accept="image/*" required>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-upload me-2"></i>Enviar Fotos</button>
        </form>

        <?php if (count($fotos) > 0): ?>
            <h4 class="fw-bold mt-4 mb-3"><i class="fas fa-images me-2"></i>Fotos Enviadas</h4>
            <div class="row">
                <?php foreach ($fotos as $foto): ?>
                    <div class="col-md-3">
                        <img src="<?= htmlspecialchars($foto['caminho_foto']) ?>" alt="Foto do serviço" class="foto-preview img-fluid">
                        <p class="text-muted small">Enviada em: <?= date('d/m/Y H:i', strtotime($foto['data_upload'])) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>