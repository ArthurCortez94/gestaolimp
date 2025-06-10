<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm fixed-top">
    <div class="container-fluid px-3">
        <a class="navbar-brand" href="dashboard_tecnico.php">
            <img src="https://ultralimpnatal.com.br/wp-content/uploads/2025/01/logo-ultralimp-white.webp" alt="UltraLimp" style="height: 35px;">
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'dashboard_tecnico.php' ? 'active' : '' ?>" href="dashboard_tecnico.php">
                        <i class="fas fa-chart-pie me-1"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= str_contains($currentPage, 'servicos') || $currentPage === 'agendamento_tecnico.php' || $currentPage === 'lista_servicos_tecnico.php' ? 'active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-concierge-bell me-1"></i> Serviços
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="agendamento_tecnico.php"><i class="fas fa-calendar-check me-2"></i>Meus Agendamentos</a></li>
                        <li><a class="dropdown-item" href="lista_servicos_tecnico.php"><i class="fas fa-tasks me-2"></i>Lista de Serviços</a></li>
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= str_contains($currentPage, 'tickets') || $currentPage === 'tickets_tecnico.php' ? 'active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-ticket-alt me-1"></i> Tickets
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="tickets_tecnico.php"><i class="fas fa-list me-2"></i>Meus Tickets</a></li>
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= str_contains($currentPage, 'gastos') || $currentPage === 'registrar_gasto.php' || $currentPage === 'lista_gastos_tecnico.php' ? 'active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-dollar-sign me-1"></i> Gastos
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="registrar_gasto.php"><i class="fas fa-plus me-2"></i>Registrar Gasto</a></li>
                        <li><a class="dropdown-item" href="lista_gastos_tecnico.php"><i class="fas fa-list me-2"></i>Lista de Gastos</a></li>
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= str_contains($currentPage, 'relatorios') || $currentPage === 'relatorios_tecnico.php' ? 'active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-chart-bar me-1"></i> Relatórios
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="relatorios_tecnico.php"><i class="fas fa-concierge-bell me-2"></i>Desempenho Pessoal</a></li>
                    </ul>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="logout.php">
                        <i class="fas fa-sign-out-alt me-1"></i> Sair
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<style>
    .navbar {
        background: linear-gradient(120deg, #1E3A8A 0%, #2563EB 100%);
        padding: 0.75rem 1rem;
    }
    .navbar-brand img {
        height: 35px;
        transition: transform 0.3s ease;
    }
    .navbar-brand:hover img {
        transform: scale(1.1);
    }
    .navbar-nav .nav-link {
        color: white;
        font-weight: 500;
        padding: 0.5rem 1rem;
        transition: all 0.3s ease;
    }
    .navbar-nav .nav-link:hover, .navbar-nav .nav-link.active {
        color: #F59E0B;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 8px;
    }
    .dropdown-menu {
        background: white;
        border: none;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        border-radius: 8px;
    }
    .dropdown-item {
        color: #111827;
        padding: 0.5rem 1rem;
        transition: all 0.3s ease;
    }
    .dropdown-item:hover {
        background: #F3F4F6;
        color: #1E3A8A;
    }
    .dropdown-divider {
        border-color: #E5E7EB;
    }
    @media (max-width: 991px) {
        .navbar-nav {
            padding: 1rem;
        }
        .navbar-nav .nav-link {
            padding: 0.75rem 1rem;
        }
        .dropdown-menu {
            width: 100%;
            margin-top: 0.5rem;
        }
    }
</style>