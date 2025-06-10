<?php
// logout.php
session_start();

// Destruir todas as variáveis de sessão
$_SESSION = array();

// Excluir o cookie de sessão
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destruir a sessão
session_destroy();

// Redirecionar para login com mensagem de sucesso
header('Location: login.php?logout=1');
exit();
?>