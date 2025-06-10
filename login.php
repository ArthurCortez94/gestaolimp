<?php
session_start();
require_once __DIR__ . '/config.php'; // Caminho relativo correto na raiz

// Gerar e verificar token CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function verifyCsrfToken() {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("CSRF token inválido");
    }
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken(); // Verificar token CSRF

    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $senha = $_POST['senha'];

    if (!isset($_POST['consent']) || $_POST['consent'] !== '1') {
        $error = "Você precisa concordar com a Política de Privacidade.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, nome, email, senha, cargo FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($senha, $user['senha'])) {
                // Registrar dados na sessão
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_cargo'] = $user['cargo'];
                $_SESSION['user_name'] = $user['nome'];
                $_SESSION['user_last_active'] = time();

                // Regenerar o CSRF token após login bem-sucedido
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                // Registrar log de login bem-sucedido
                registrarLog($user['id'], "Login bem-sucedido para {$user['email']}");

                // Redirecionamento seguro com caminho absoluto na raiz
                switch ($_SESSION['user_cargo']) {
                    case 'admin':
                    case 'atendente':
                        header("Location: /dashboard.php");
                        break;
                    case 'tecnico':
                        header("Location: /dashboard_tecnico.php");
                        break;
                    default:
                        header("Location: /acesso_negado.php");
                }
                exit();
            } else {
                $error = "Credenciais inválidas!";
                registrarLog(0, "Tentativa de login falhou para {$email}");
            }
        } catch (PDOException $e) {
            error_log('Login error: ' . $e->getMessage());
            $error = "Erro no sistema. Tente novamente mais tarde.";
            registrarLog(0, "Erro de login para {$email}: " . $e->getMessage());
        }
    }
}

// Redirecionar usuários já logados
if (isset($_SESSION['user_id'])) {
    switch ($_SESSION['user_cargo']) {
        case 'tecnico':
            header("Location: /dashboard_tecnico.php");
            break;
        default:
            header("Location: /dashboard.php");
    }
    exit();
}

// Processar logout
if (isset($_GET['logout']) && $_GET['logout'] == '1') {
    session_unset();
    session_destroy();
    // Regenerar a sessão e o CSRF token após logout
    session_start();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $success = "Você saiu com sucesso.";
}

// Função de log com tratamento de erro
function registrarLog($usuario_id, $acao) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO logs (usuario_id, acao) VALUES (?, ?)");
        $stmt->execute([$usuario_id, $acao]);
    } catch (PDOException $e) {
        error_log("Erro ao registrar log: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <!-- Meta tags essenciais para PWA -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#2A5C82"> <!-- Cor da barra superior -->
    <meta name="apple-mobile-web-app-capable" content="yes"> <!-- Permite modo standalone no iOS -->
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="description" content="Sistema de gestão para Ultra Multiservice">
    <title>Acesso ao Sistema - Ultra Multiservice</title>
    <!-- Vincular o manifesto -->
    <link rel="manifest" href="/manifest.json">
    <!-- Ícone para iOS -->
    <link rel="apple-touch-icon" href="/icons/icon-192x192.png">
    <!-- Estilos existentes -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Estilos globais e variáveis */
        :root {
            --primary: #2A5C82; /* Azul principal */
            --secondary: #4CAF50; /* Verde para sucesso/ênfase */
            --accent: #FFC107; /* Amarelo para destaque */
            --light: #f0f2f5; /* Fundo claro */
            --dark: #1e3a5f; /* Azul escuro para texto/elementos */
            --text-color: #333; /* Cor de texto padrão */
            --border-color: #ced4da;
            --input-bg: #f8f9fa;
            --shadow-light: 0 2px 10px rgba(0,0,0,0.08);
            --shadow-medium: 0 8px 25px rgba(0,0,0,0.15);
            --shadow-heavy: 0 15px 40px rgba(0,0,0,0.2);
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, var(--light) 0%, #e6eef5 100%);
            min-height: 100vh;
            margin: 0;
            overflow-x: hidden;
            color: var(--text-color);
            line-height: 1.6;
        }

        /* Contêiner principal do login */
        .login-container {
            display: flex;
            min-height: 100vh;
            position: relative;
            overflow: hidden;
        }

        /* Seção de Boas-Vindas */
        .welcome-section {
            flex: 1.3;
            background: linear-gradient(145deg, var(--primary) 0%, var(--dark) 100%);
            padding: 4rem 3rem;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
            box-shadow: 5px 0 15px rgba(0,0,0,0.2);
        }

        .welcome-section::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            transform: rotate(30deg);
            pointer-events: none;
            animation: float 20s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: rotate(30deg) translateY(0px); }
            50% { transform: rotate(30deg) translateY(-20px); }
        }

        .brand-logo {
            width: 280px;
            margin-bottom: 2.5rem;
            filter: drop-shadow(0 4px 8px rgba(0,0,0,0.2));
            transition: transform 0.3s ease;
        }

        .brand-logo:hover {
            transform: scale(1.05);
        }

        .welcome-section h1 {
            font-size: 2.8rem;
            line-height: 1.2;
            margin-bottom: 2rem;
            font-weight: 700;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }

        .feature-card {
            background: rgba(255,255,255,0.15);
            padding: 1.8rem;
            border-radius: 16px;
            margin-bottom: 1.5rem;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            cursor: pointer;
        }

        .feature-card:hover {
            transform: translateY(-8px) scale(1.02);
            background: rgba(255,255,255,0.25);
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }

        .feature-icon {
            width: 65px;
            height: 65px;
            background: linear-gradient(135deg, rgba(255,255,255,0.3), rgba(255,255,255,0.1));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1.5rem;
            color: var(--accent);
            font-size: 1.8rem;
            box-shadow: var(--shadow-light);
            transition: all 0.3s ease;
        }

        .feature-card:hover .feature-icon {
            transform: rotate(10deg) scale(1.1);
            background: linear-gradient(135deg, rgba(255,255,255,0.4), rgba(255,255,255,0.2));
        }

        .feature-card h5 {
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }

        .feature-card p {
            font-size: 0.95rem;
            opacity: 0.9;
            margin: 0;
        }

        .system-info {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-top: 2.5rem;
            line-height: 1.6;
            padding: 1.5rem;
            background: rgba(255,255,255,0.1);
            border-radius: 12px;
            backdrop-filter: blur(5px);
        }

        /* Seção do Formulário de Login */
        .login-form-section {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            background: white;
            position: relative;
            z-index: 1;
        }

        .login-card {
            max-width: 480px;
            width: 100%;
            padding: 3rem;
            border-radius: 20px;
            background: white;
            box-shadow: var(--shadow-heavy);
            border: 1px solid #eee;
            position: relative;
            overflow: hidden;
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--accent), var(--secondary));
        }

        .login-card h2 {
            font-size: 2.4rem;
            margin-bottom: 2rem;
            color: var(--primary);
            text-align: center;
            font-weight: 700;
            position: relative;
        }

        .login-card h2::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            border-radius: 2px;
        }

        .form-label {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.7rem;
            font-size: 0.95rem;
            letter-spacing: 0.3px;
        }

        .form-control {
            border-radius: 12px;
            padding: 14px 18px;
            border: 2px solid var(--border-color);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background-color: var(--input-bg);
            font-size: 1rem;
            font-weight: 500;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(42, 92, 130, 0.15);
            background-color: white;
            transform: translateY(-2px);
        }

        .input-group {
            position: relative;
        }

        .input-group-text {
            background: var(--primary);
            border-radius: 12px 0 0 12px;
            border: 2px solid var(--primary);
            color: white;
            padding: 0 18px;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }

        .input-group:focus-within .input-group-text {
            background: var(--dark);
            border-color: var(--primary);
            transform: translateY(-2px);
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--primary);
            cursor: pointer;
            z-index: 10;
            padding: 5px;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .password-toggle:hover {
            background: rgba(42, 92, 130, 0.1);
            transform: translateY(-50%) scale(1.1);
        }

        .btn-login {
            padding: 14px;
            font-weight: 600;
            background: linear-gradient(135deg, var(--primary), var(--dark));
            border: none;
            border-radius: 12px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            color: white;
            font-size: 1.1rem;
            letter-spacing: 0.5px;
            position: relative;
            overflow: hidden;
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn-login:hover::before {
            left: 100%;
        }

        .btn-login:hover {
            background: linear-gradient(135deg, var(--dark), var(--primary));
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(42, 92, 130, 0.3);
            color: white;
        }

        .btn-login:active {
            transform: translateY(-1px);
        }

        .form-check-label {
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
        }

        .form-check-input {
            width: 1.2em;
            height: 1.2em;
            border-radius: 6px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .form-check-input:checked {
            background-color: var(--primary);
            border-color: var(--primary);
            box-shadow: 0 2px 8px rgba(42, 92, 130, 0.3);
        }

        .form-check-input:focus {
            box-shadow: 0 0 0 0.25rem rgba(42, 92, 130, 0.15);
        }

        .forgot-password-link {
            color: var(--primary) !important;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            position: relative;
        }

        .forgot-password-link::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--primary);
            transition: width 0.3s ease;
        }

        .forgot-password-link:hover {
            color: var(--dark) !important;
        }

        .forgot-password-link:hover::after {
            width: 100%;
        }

        /* Mensagens de alerta melhoradas */
        .alert {
            border-radius: 12px;
            font-size: 0.95rem;
            padding: 1.2rem 1.5rem;
            margin-bottom: 1.5rem;
            border: none;
            font-weight: 500;
            position: relative;
            overflow: hidden;
        }

        .alert::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
        }

        .alert-danger {
            background: linear-gradient(135deg, #f8d7da, #f1c2c7);
            color: #721c24;
        }

        .alert-danger::before {
            background: #dc3545;
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
        }

        .alert-success::before {
            background: #28a745;
        }

        .alert .btn-close {
            font-size: 0.8rem;
            padding: 0.6rem 0.8rem;
            opacity: 0.7;
            transition: opacity 0.3s ease;
        }

        .alert .btn-close:hover {
            opacity: 1;
        }

        /* Animações de entrada */
        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .welcome-section {
            animation: slideInLeft 0.8s ease-out;
        }

        .login-form-section {
            animation: slideInRight 0.8s ease-out;
        }

        /* Responsividade melhorada */
        @media (max-width: 992px) {
            .login-container {
                flex-direction: column;
            }
            .welcome-section {
                padding: 3rem 2rem;
                min-height: 45vh;
                box-shadow: 0 5px 15px rgba(0,0,0,0.2);
                animation: slideInLeft 0.6s ease-out;
            }
            .welcome-section h1 {
                font-size: 2.2rem;
                text-align: center;
            }
            .brand-logo {
                width: 220px;
                margin: 0 auto 2rem;
                display: block;
            }
            .login-form-section {
                box-shadow: none;
                padding: 2rem 1rem;
                animation: slideInRight 0.6s ease-out;
            }
            .login-card {
                padding: 2.5rem;
                box-shadow: var(--shadow-medium);
            }
        }

        @media (max-width: 768px) {
            .welcome-section {
                padding: 2.5rem 1.5rem;
                min-height: 40vh;
            }
            .welcome-section h1 {
                font-size: 1.9rem;
            }
            .feature-card {
                padding: 1.5rem;
            }
            .feature-icon {
                width: 55px;
                height: 55px;
                font-size: 1.6rem;
                margin-right: 1rem;
            }
            .login-card {
                padding: 2rem;
            }
            .login-card h2 {
                font-size: 2rem;
            }
            .btn-login {
                font-size: 1rem;
                padding: 12px;
            }
        }

        @media (max-width: 576px) {
            .login-container {
                min-height: auto;
            }
            .welcome-section {
                min-height: 35vh;
                text-align: center;
                padding: 2rem 1rem;
            }
            .brand-logo {
                width: 180px;
            }
            .feature-card {
                flex-direction: column;
                text-align: center;
                padding: 1.2rem;
            }
            .feature-icon {
                margin: 0 auto 1rem;
            }
            .login-form-section {
                padding: 1.5rem;
            }
            .login-card {
                padding: 1.8rem;
                border-radius: 16px;
            }
            .login-card h2 {
                font-size: 1.8rem;
            }
            .form-control, .input-group-text {
                padding: 12px 15px;
            }
        }

        /* Melhorias de acessibilidade */
        .form-control:focus,
        .btn-login:focus,
        .form-check-input:focus {
            outline: 2px solid var(--primary);
            outline-offset: 2px;
        }

        /* Indicador de carregamento */
        .loading {
            opacity: 0.7;
            pointer-events: none;
        }

        .loading .btn-login {
            position: relative;
        }

        .loading .btn-login::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            margin: auto;
            border: 2px solid transparent;
            border-top-color: white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Seção de Boas-Vindas -->
        <div class="welcome-section">
            <img src="https://ultralimpnatal.com.br/wp-content/uploads/2025/01/logo-ultralimp-white.webp" 
                 alt="Ultra Multiservice" 
                 class="brand-logo">
            <h1 class="mb-4">Ultra Multiservice<br>Sistema de Gestão e Controle</h1>
            
            <div class="features">
                <div class="feature-card d-flex align-items-center">
                    <div class="feature-icon">
                        <i class="fas fa-tools"></i>
                    </div>
                    <div>
                        <h5>Gestão de Serviços</h5>
                        <p>Controle completo de orçamentos, tickets e lavanderia</p>
                    </div>
                </div>
                <div class="feature-card d-flex align-items-center">
                    <div class="feature-icon">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <div>
                        <h5>Relatórios Inteligentes</h5>
                        <p>Acompanhe o desempenho da sua equipe em tempo real</p>
                    </div>
                </div>
                <div class="feature-card d-flex align-items-center">
                    <div class="feature-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div>
                        <h5>Colaboração em Equipe</h5>
                        <p>Conecte técnicos e atendentes de forma eficiente</p>
                    </div>
                </div>
            </div>
            <div class="system-info">
                <p><strong>Sistema desenvolvido especialmente para a Ultra Multiservice</strong>, oferecendo controle total sobre operações diárias e proporcionando uma experiência otimizada para toda sua equipe.</p>
            </div>
        </div>

        <!-- Seção de Login -->
        <div class="login-form-section">
            <div class="login-card">
                <h2>Bem-vindo</h2>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                    </div>
                <?php endif; ?>
                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?= htmlspecialchars($success) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" id="loginForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">E-mail</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-envelope"></i>
                            </span>
                            <input type="email" 
                                   name="email" 
                                   class="form-control" 
                                   placeholder="Digite seu e-mail" 
                                   required
                                   autocomplete="email">
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">Senha</label>
                        <div class="input-group position-relative">
                            <span class="input-group-text">
                                <i class="fas fa-lock"></i>
                            </span>
                            <input type="password" 
                                   name="senha" 
                                   id="passwordField"
                                   class="form-control" 
                                   placeholder="Digite sua senha" 
                                   required
                                   autocomplete="current-password">
                            <button type="button" 
                                    class="password-toggle" 
                                    id="togglePassword"
                                    aria-label="Mostrar/ocultar senha">
                                <i class="fas fa-eye" id="toggleIcon"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" 
                               class="form-check-input" 
                               id="consent" 
                               name="consent" 
                               value="1" 
                               required>
                        <label class="form-check-label" for="consent">
                            Li e concordo com a 
                            <a href="/politica-privacidade.php" 
                               target="_blank" 
                               class="forgot-password-link">Política de Privacidade</a>
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-login w-100 mb-4">
                        <i class="fas fa-sign-in-alt me-2"></i>
                        <span>Acessar Sistema</span>
                    </button>
                    
                    <div class="text-center">
                        <small class="text-muted">
                            Esqueceu sua senha? 
                            <a href="/recuperar-senha.php" class="forgot-password-link">
                                Recuperar acesso
                            </a>
                        </small>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle de visibilidade da senha
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordField = document.getElementById('passwordField');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
                this.setAttribute('aria-label', 'Ocultar senha');
            } else {
                passwordField.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
                this.setAttribute('aria-label', 'Mostrar senha');
            }
        });

        // Indicador de carregamento no formulário
        document.getElementById('loginForm').addEventListener('submit', function() {
            const submitButton = this.querySelector('.btn-login');
            const buttonText = submitButton.querySelector('span');
            
            submitButton.classList.add('loading');
            buttonText.textContent = 'Verificando...';
            submitButton.disabled = true;
        });

        // Animação suave para os alertas
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-20px)';
                
                setTimeout(() => {
                    alert.style.transition = 'all 0.5s ease';
                    alert.style.opacity = '1';
                    alert.style.transform = 'translateY(0)';
                }, 100);
            });
        });

        // Registro do Service Worker
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/service-worker.js')
                    .then(reg => console.log('Service Worker registrado com sucesso:', reg))
                    .catch(err => console.log('Erro ao registrar Service Worker:', err));
            });
        }

        // Melhorias de acessibilidade - navegação por teclado
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && e.target.classList.contains('feature-card')) {
                e.target.click();
            }
        });

        // Validação em tempo real
        const emailInput = document.querySelector('input[name="email"]');
        const passwordInput = document.querySelector('input[name="senha"]');
        
        emailInput.addEventListener('blur', function() {
            if (this.value && !this.checkValidity()) {
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-invalid');
            }
        });

        passwordInput.addEventListener('input', function() {
            if (this.value.length > 0 && this.value.length < 6) {
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-invalid');
            }
        });
    </script>
</body>
</html>

