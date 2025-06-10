<?php
// config.php
declare(strict_types=1);

// 1. Definições do Banco de Dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'ultralimp');
define('DB_USER', 'admin');
define('DB_PASS', '251213@bC');

// 2. Configurações da Aplicação
define('BASE_URL', 'https://147.93.11.46/'); // Altere para seu URL real

// 3. Verificar constantes essenciais
if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER') || !defined('BASE_URL')) {
    die("Erro de configuração: Constantes essenciais não definidas!");
}

// 4. Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

try {
    // 5. Conexão com o Banco de Dados
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]
    );
    
    // 6. Verificação adicional da conexão
    $pdo->query("SELECT 1")->fetchColumn();

} catch (PDOException $e) {
    // 7. Log detalhado e mensagem amigável
    error_log("[" . date('Y-m-d H:i:s') . "] Erro DB: " . $e->getMessage());
    die("Erro de conexão com o banco de dados. Por favor, tente novamente mais tarde.");
}

// 8. Configurações globais
date_default_timezone_set('America/Sao_Paulo');
mb_internal_encoding('UTF-8');