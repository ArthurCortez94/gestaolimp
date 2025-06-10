<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UltraLimp</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
    .navbar {
        background: #f8f9fa !important;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .navbar-brand img {
        height: 40px;
    }
    
    .nav-link {
        color: #2A5C82 !important;
        font-weight: 500;
        margin: 0 8px;
        border-radius: 8px;
        transition: all 0.3s;
    }
    
    .nav-link:hover,
    .nav-link.active {
        background: #2A5C82 !important;
        color: white !important;
    }
    
    .dropdown-menu {
        border: none;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light">
    <div class="container">
        <!-- Logo -->
        <a class="navbar-brand" href="#">
            <img src="https://ultralimpnatal.com.br/wp-content/uploads/2025/01/logo-ultralimp.webp" alt="UltraLimp">
        </a>

        <!-- Botão Hamburguer -->
        <button class="navbar-toggler" 
                type="button" 
                data-bs-toggle="collapse" 
                data-bs-target="#navbarNav"
                aria-controls="navbarNav"
                aria-expanded="false"
                aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Itens do Menu -->
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto"> <!-- Alinhamento à direita -->
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-home me-2"></i>Dashboard
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link" href="clientes.php">
                        <i class="fas fa-users me-2"></i>Clientes
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link" href="servicos.php">
                        <i class="fas fa-clipboard-list me-2"></i>Serviços
                    </a>
                </li>
                
                <li class="nav-item dropdown"> <!-- Exemplo de dropdown -->
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-cog me-2"></i>Mais
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="financeiro.php">Financeiro</a></li>
                        <li><a class="dropdown-item" href="relatorios.php">Relatórios</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php">Sair</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Bootstrap JS e Popper.js (OBRIGATÓRIO NO FINAL DO BODY) -->
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>

</body>
</html>