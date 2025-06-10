<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm fixed-top">
    <div class="container-fluid px-3">
        <a class="navbar-brand" href="admin_dashboard.php">
            <img src="https://ultralimpnatal.com.br/wp-content/uploads/2025/01/logo-ultralimp-white.webp" alt="UltraLimp" style="height: 35px;">
            <span class="ms-2 text-warning">Admin</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav" aria-controls="adminNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="adminNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'admin_dashboard.php' ? 'active' : '' ?>" href="admin_dashboard.php">
                        <i class="fas fa-chart-pie me-1"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= str_contains($currentPage, 'clientes') ? 'active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-users me-1"></i> Clientes
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="cadastro.php"><i class="fas fa-user-plus me-2"></i>Novo Cliente</a></li>
                        <li><a class="dropdown-item" href="lista.php"><i class="fas fa-list me-2"></i>Lista de Clientes</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="segmentos.php"><i class="fas fa-tags me-2"></i>Segmentos</a></li>
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= str_contains($currentPage, 'servicos') || $currentPage === 'criar_orcamento.php' || $currentPage === 'lista_orcamentos.php' || $currentPage === 'lista_ordens_servico.php' || $currentPage === 'agendamento.php' ? 'active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-concierge-bell me-1"></i> Serviços
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="criar_orcamento.php"><i class="fas fa-file-invoice me-2"></i>Criar Orçamento</a></li>
                        <li><a class="dropdown-item" href="lista_orcamentos.php"><i class="fas fa-list me-2"></i>Listagem de Orçamentos</a></li>
                        <li><a class="dropdown-item" href="lista_ordens_servico.php"><i class="fas fa-clipboard-list me-2"></i>Lista de Ordens de Serviço</a></li>
                        <li><a class="dropdown-item" href="agendamento.php"><i class="fas fa-calendar-check me-2"></i>Agendamento</a></li>
                        <li><a class="dropdown-item" href="servicos/lista.php"><i class="fas fa-tasks me-2"></i>Todos Serviços</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="servicos/categorias.php"><i class="fas fa-layer-group me-2"></i>Categorias</a></li>
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= str_contains($currentPage, 'lavanderia') ? 'active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-tshirt me-1"></i> Lavanderia
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="lavanderia.php"><i class="fas fa-plus me-2"></i>Cadastrar Item</a></li>
                        <li><a class="dropdown-item" href="lavanderia_list.php"><i class="fas fa-list me-2"></i>Listar Itens</a></li>
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= str_contains($currentPage, 'financeiro') ? 'active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-coins me-1"></i> Financeiro
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="financeiro/contas_receber.php"><i class="fas fa-hand-holding-usd me-2"></i>Contas a Receber</a></li>
                        <li><a class="dropdown-item" href="financeiro/contas_pagar.php"><i class="fas fa-money-bill-wave me-2"></i>Contas a Pagar</a></li>
                        <li><a class="dropdown-item" href="financeiro/fluxo_caixa.php"><i class="fas fa-chart-line me-2"></i>Fluxo de Caixa</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="financeiro/relatorios.php"><i class="fas fa-file-invoice-dollar me-2"></i>Relatórios</a></li>
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= str_contains($currentPage, 'relatorios') ? 'active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-chart-bar me-1"></i> Relatórios
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="relatorios/servicos.php"><i class="fas fa-concierge-bell me-2"></i>Desempenho de Serviços</a></li>
                        <li><a class="dropdown-item" href="relatorios/clientes.php"><i class="fas fa-user-tie me-2"></i>Clientes Ativos</a></li>
                        <li><a class="dropdown-item" href="relatorios/financeiro.php"><i class="fas fa-wallet me-2"></i>Saúde Financeira</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="relatorios/personalizado.php"><i class="fas fa-cog me-2"></i>Personalizado</a></li>
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= str_contains($currentPage, 'config') || $currentPage === 'usuarios.php' || $currentPage === 'cadastrar_usuario.php' ? 'active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-cogs me-1"></i> Configurações
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="usuarios.php"><i class="fas fa-list me-2"></i>Listar Usuários</a></li>
                        <li><a class="dropdown-item" href="cadastrar_usuario.php"><i class="fas fa-user-plus me-2"></i>Cadastrar Usuários</a></li>
                        <li><a class="dropdown-item" href="config/empresa.php"><i class="fas fa-building me-2"></i>Dados da Empresa</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="config/integracao.php"><i class="fas fa-plug me-2"></i>Integrações</a></li>
                    </ul>
                </li>
                <!-- [FUTURO] Menu para funcionalidades exclusivas do administrador -->
                <!-- Exemplo: 
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-tools me-1"></i> Admin Tools
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="admin/audit.php"><i class="fas fa-search me-2"></i>Auditoria</a></li>
                    </ul>
                </li>
                -->
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
        background: linear-gradient(120deg, #111827 0%, #374151 100%);
        padding: 0.75rem 1rem;
    }
    .navbar-brand img {
        height: 35px;
        transition: transform 0.3s ease;
    }
    .navbar-brand:hover img {
        transform: scale(1.1);
    }
    .navbar-brand .text-warning {
        font-weight: 600;
        font-size: 0.9rem;
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
        color: #374151;
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