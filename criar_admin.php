<?php
require_once 'config.php';

try {
    $pdo = new PDO(
        "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
        DB_USER, 
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $dados = [
        'nome' => 'Arthur Cortez',
        'email' => 'contato@ultralimpnatal;com.br',
        'senha' => password_hash('251213abc', PASSWORD_DEFAULT),
        'cargo' => 'admin'
    ];

    $stmt = $pdo->prepare("
        INSERT INTO usuarios 
        (nome, email, senha, cargo) 
        VALUES (:nome, :email, :senha, :cargo)
    ");
    
    $stmt->execute($dados);
    echo "Admin criado com sucesso!";
    
} catch (PDOException $e) {
    die("Erro: " . $e->getMessage());
}
