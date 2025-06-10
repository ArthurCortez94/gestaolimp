<?php
session_start();

// Destruir a sessão do cliente
session_unset();
session_destroy();

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout - UltraLimp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #F9FAFB; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .logout-card { background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1); padding: 40px; text-align: center; max-width: 400px; }
        .btn-primary { background-color: #1E3A8A; border-color: #1E3A8A; }
    </style>
</head>
<body>
    <div class="logout-card">
        <h2>Sessão Encerrada</h2>
        <p>Você saiu do painel do cliente com sucesso.</p>
        <a href="areadocliente.php" class="btn btn-primary mt-3">Voltar ao Painel</a>
    </div>
</body>
</html>