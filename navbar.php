<?php
/**
 * Ultra Menu - Componente de Menu Moderno e Independente
 * Versão: 2.0
 * Desenvolvido para ser incluído em qualquer página sem conflitos
 */

// Verificar se a sessão já foi iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Obter página atual para destacar item ativo
$currentPage = basename($_SERVER['PHP_SELF']);

// Configurações do menu (podem ser customizadas)
$ultraMenuConfig = [
    'logo_url' => 'https://ultralimpnatal.com.br/wp-content/uploads/2025/01/logo-ultralimp-white.webp',
    'logo_alt' => 'Ultra Multiservice',
    'home_url' => '/dashboard.php',
    'enable_search' => true,
    'enable_notifications' => true,
    'user_name' => $_SESSION['user_name'] ?? 'Usuário',
    'user_role' => $_SESSION['user_role'] ?? 'Atendente'
];
?>

<!-- Ultra Menu CSS - Estilos Encapsulados -->
<style id="ultra-menu-styles">
/* Reset e Variáveis CSS para o Ultra Menu */
.ultra-menu-container {
    --ultra-primary: #1E3A8A;
    --ultra-primary-light: #2563EB;
    --ultra-primary-lighter: #3B82F6;
    --ultra-accent: #F59E0B;
    --ultra-accent-dark: #D97706;
    --ultra-success: #10B981;
    --ultra-white: #FFFFFF;
    --ultra-gray: #64748B;
    --ultra-gray-light: #F8FAFC;
    --ultra-gray-border: #E2E8F0;
    --ultra-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    --ultra-shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    --ultra-transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    --ultra-border-radius: 8px;
    
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

/* Menu Principal */
.ultra-navbar {
    background: linear-gradient(135deg, var(--ultra-primary) 0%, var(--ultra-primary-light) 50%, var(--ultra-primary-lighter) 100%);
    backdrop-filter: blur(10px);
    border: none;
    box-shadow: var(--ultra-shadow-lg);
    padding: 0.75rem 1rem;
    position: relative;
    z-index: 1050;
}

.ultra-navbar.fixed-top {
    position: fixed;
    top: 0;
    width: 100%;
}

/* Logo */
.ultra-brand {
    display: flex;
    align-items: center;
    text-decoration: none;
    transition: var(--ultra-transition);
}

.ultra-brand:hover {
    transform: scale(1.05);
}

.ultra-brand img {
    height: 40px;
    width: auto;
    filter: brightness(1.1);
    transition: var(--ultra-transition);
}

.ultra-brand:hover img {
    filter: brightness(1.2) drop-shadow(0 4px 8px rgba(0,0,0,0.2));
}

/* Navegação */
.ultra-nav {
    display: flex;
    align-items: center;
    list-style: none;
    margin: 0;
    padding: 0;
    gap: 0.25rem;
}

.ultra-nav-link {
    color: var(--ultra-white) !important;
    font-weight: 500;
    padding: 0.625rem 1rem;
    border-radius: var(--ultra-border-radius);
    transition: var(--ultra-transition);
    text-decoration: none;
    display: flex;
    align-items: center;
    position: relative;
    overflow: hidden;
}

.ultra-nav-link::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
    transition: left 0.5s;
}

.ultra-nav-link:hover::before {
    left: 100%;
}

.ultra-nav-link:hover,
.ultra-nav-link.active {
    color: var(--ultra-accent) !important;
    background: rgba(255, 255, 255, 0.15);
    transform: translateY(-1px);
    box-shadow: var(--ultra-shadow);
}

.ultra-nav-link i {
    margin-right: 0.5rem;
    font-size: 0.9rem;
}

/* Dropdowns Modernos */
.ultra-dropdown-menu {
    background: var(--ultra-white);
    border: none;
    border-radius: var(--ultra-border-radius);
    box-shadow: var(--ultra-shadow-lg);
    padding: 0.5rem 0;
    min-width: 250px;
    margin-top: 0.5rem;
}

.ultra-dropdown-item {
    color: var(--ultra-gray) !important;
    padding: 0.625rem 1rem;
    transition: var(--ultra-transition);
    text-decoration: none;
    display: flex;
    align-items: center;
    border-left: 3px solid transparent;
}

.ultra-dropdown-item:hover {
    color: var(--ultra-primary) !important;
    background: var(--ultra-gray-light);
    border-left-color: var(--ultra-accent);
    transform: translateX(4px);
}

.ultra-dropdown-item i {
    margin-right: 0.75rem;
    width: 16px;
    text-align: center;
    color: var(--ultra-accent);
}

.ultra-dropdown-divider {
    border-color: var(--ultra-gray-border);
    margin: 0.5rem 1rem;
}

/* Busca Inteligente */
.ultra-search-container {
    position: relative;
    margin-right: 1rem;
}

.ultra-search-btn {
    background: rgba(255, 255, 255, 0.15);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: var(--ultra-white);
    padding: 0.5rem 1rem;
    border-radius: var(--ultra-border-radius);
    font-size: 0.9rem;
    cursor: pointer;
    transition: var(--ultra-transition);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    min-width: 180px;
}

.ultra-search-btn:hover {
    background: rgba(255, 255, 255, 0.25);
    transform: scale(1.02);
}

.ultra-search-btn i {
    color: var(--ultra-accent);
}

.ultra-search-shortcut {
    background: rgba(255, 255, 255, 0.2);
    padding: 0.125rem 0.375rem;
    border-radius: 4px;
    font-size: 0.75rem;
    margin-left: auto;
}

/* Modal de Busca */
.ultra-search-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(4px);
    z-index: 2000;
    display: none;
    align-items: flex-start;
    justify-content: center;
    padding-top: 10vh;
}

.ultra-search-modal.show {
    display: flex;
}

.ultra-search-content {
    background: var(--ultra-white);
    border-radius: 12px;
    box-shadow: var(--ultra-shadow-lg);
    width: 90%;
    max-width: 600px;
    max-height: 80vh;
    overflow: hidden;
    animation: slideInDown 0.3s ease-out;
}

@keyframes slideInDown {
    from {
        opacity: 0;
        transform: translateY(-50px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.ultra-search-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--ultra-gray-border);
}

.ultra-search-input {
    width: 100%;
    border: none;
    outline: none;
    font-size: 1.25rem;
    font-weight: 500;
    color: var(--ultra-primary);
    background: transparent;
}

.ultra-search-input::placeholder {
    color: var(--ultra-gray);
}

.ultra-search-results {
    padding: 1rem;
    max-height: 400px;
    overflow-y: auto;
}

.ultra-search-category {
    margin-bottom: 1.5rem;
}

.ultra-search-category-title {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--ultra-gray);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.75rem;
}

.ultra-search-item {
    display: flex;
    align-items: center;
    padding: 0.75rem;
    border-radius: var(--ultra-border-radius);
    cursor: pointer;
    transition: var(--ultra-transition);
    margin-bottom: 0.25rem;
}

.ultra-search-item:hover {
    background: var(--ultra-gray-light);
}

.ultra-search-item-icon {
    width: 40px;
    height: 40px;
    border-radius: var(--ultra-border-radius);
    background: linear-gradient(135deg, var(--ultra-primary) 0%, var(--ultra-primary-light) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--ultra-white);
    margin-right: 1rem;
}

.ultra-search-item-content {
    flex: 1;
}

.ultra-search-item-title {
    font-weight: 600;
    color: var(--ultra-primary);
    margin-bottom: 0.25rem;
}

.ultra-search-item-desc {
    font-size: 0.85rem;
    color: var(--ultra-gray);
}

/* Notificações */
.ultra-notifications {
    position: relative;
    margin-right: 1rem;
}

.ultra-notification-btn {
    background: rgba(255, 255, 255, 0.15);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: var(--ultra-white);
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: var(--ultra-transition);
    position: relative;
}

.ultra-notification-btn:hover {
    background: rgba(255, 255, 255, 0.25);
    transform: scale(1.05);
}

.ultra-notification-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: var(--ultra-accent);
    color: var(--ultra-white);
    border-radius: 50%;
    width: 18px;
    height: 18px;
    font-size: 0.7rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
}

/* Perfil do Usuário */
.ultra-user-profile {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: var(--ultra-white);
    text-decoration: none;
    padding: 0.5rem 1rem;
    border-radius: var(--ultra-border-radius);
    transition: var(--ultra-transition);
}

.ultra-user-profile:hover {
    color: var(--ultra-accent) !important;
    background: rgba(255, 255, 255, 0.1);
}

.ultra-user-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--ultra-accent) 0%, var(--ultra-accent-dark) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.8rem;
}

.ultra-user-info {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
}

.ultra-user-name {
    font-weight: 600;
    font-size: 0.9rem;
    line-height: 1.2;
}

.ultra-user-role {
    font-size: 0.75rem;
    opacity: 0.8;
    line-height: 1;
}

/* Responsividade */
@media (max-width: 991px) {
    .ultra-search-container,
    .ultra-notifications {
        display: none;
    }
    
    .ultra-user-info {
        display: none;
    }
    
    .ultra-nav {
        flex-direction: column;
        width: 100%;
        padding: 1rem 0;
    }
    
    .ultra-nav-link {
        width: 100%;
        justify-content: flex-start;
        padding: 0.75rem 1rem;
    }
    
    .ultra-dropdown-menu {
        position: static;
        box-shadow: none;
        background: rgba(255, 255, 255, 0.1);
        margin: 0.5rem 0 0 1rem;
    }
    
    .ultra-dropdown-item {
        color: rgba(255, 255, 255, 0.9) !important;
        border-left-color: transparent;
    }
    
    .ultra-dropdown-item:hover {
        color: var(--ultra-accent) !important;
        background: rgba(255, 255, 255, 0.1);
    }
}

@media (max-width: 768px) {
    .ultra-brand img {
        height: 32px;
    }
    
    .ultra-navbar {
        padding: 0.5rem 1rem;
    }
}

/* Estados de foco para acessibilidade */
.ultra-nav-link:focus,
.ultra-dropdown-item:focus,
.ultra-search-btn:focus,
.ultra-notification-btn:focus {
    outline: 2px solid var(--ultra-accent);
    outline-offset: 2px;
}

/* Animações de entrada */
.ultra-nav-item {
    animation: fadeInUp 0.6s ease-out;
    animation-fill-mode: both;
}

.ultra-nav-item:nth-child(1) { animation-delay: 0.1s; }
.ultra-nav-item:nth-child(2) { animation-delay: 0.2s; }
.ultra-nav-item:nth-child(3) { animation-delay: 0.3s; }
.ultra-nav-item:nth-child(4) { animation-delay: 0.4s; }
.ultra-nav-item:nth-child(5) { animation-delay: 0.5s; }
.ultra-nav-item:nth-child(6) { animation-delay: 0.6s; }
.ultra-nav-item:nth-child(7) { animation-delay: 0.7s; }
.ultra-nav-item:nth-child(8) { animation-delay: 0.8s; }

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>

<!-- Google Fonts - Inter -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<!-- Ultra Menu HTML -->
<div class="ultra-menu-container">
    <nav class="ultra-navbar navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container-fluid px-3">
            <!-- Logo -->
            <a class="ultra-brand navbar-brand" href="<?= $ultraMenuConfig['home_url'] ?>">
                <img src="<?= $ultraMenuConfig['logo_url'] ?>" alt="<?= $ultraMenuConfig['logo_alt'] ?>">
            </a>

            <!-- Toggle para Mobile -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#ultraMainNav" aria-controls="ultraMainNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Navegação Principal -->
            <div class="collapse navbar-collapse" id="ultraMainNav">
                <ul class="ultra-nav navbar-nav ms-auto">
                    <!-- Dashboard -->
                    <li class="ultra-nav-item nav-item">
                        <a class="ultra-nav-link nav-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>" href="/dashboard.php">
                            <i class="fas fa-chart-pie"></i> Dashboard
                        </a>
                    </li>

                    <!-- Clientes -->
                    <li class="ultra-nav-item nav-item dropdown">
                        <a class="ultra-nav-link nav-link dropdown-toggle <?= str_contains($currentPage, 'clientes') ? 'active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-users"></i> Clientes
                        </a>
                        <ul class="ultra-dropdown-menu dropdown-menu">
                            <li><a class="ultra-dropdown-item dropdown-item" href="/cadastro.php"><i class="fas fa-user-plus"></i>Novo Cliente</a></li>
                            <li><a class="ultra-dropdown-item dropdown-item" href="/lista.php"><i class="fas fa-list"></i>Lista de Clientes</a></li>
                            <li><hr class="ultra-dropdown-divider dropdown-divider"></li>
                            <li><a class="ultra-dropdown-item dropdown-item" href="/segmentos.php"><i class="fas fa-tags"></i>Segmentos</a></li>
                        </ul>
                    </li>

                    <!-- Serviços -->
                    <li class="ultra-nav-item nav-item dropdown">
                        <a class="ultra-nav-link nav-link dropdown-toggle <?= str_contains($currentPage, 'servicos') || $currentPage === 'criar_orcamento.php' || $currentPage === 'lista_orcamentos.php' || $currentPage === 'lista_ordens_servico.php' || $currentPage === 'agendamento.php' || $currentPage === 'empreitadas_list.php' || $currentPage === 'empreitada_form.php' || $currentPage === 'empreitada_detalhes.php' ? 'active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-concierge-bell"></i> Serviços
                        </a>
                        <ul class="ultra-dropdown-menu dropdown-menu">
                            <li><a class="ultra-dropdown-item dropdown-item" href="/criar_orcamento.php"><i class="fas fa-file-invoice"></i>Criar Orçamento</a></li>
                            <li><a class="ultra-dropdown-item dropdown-item" href="lista_orcamentos.php"><i class="fas fa-list"></i>Listagem de Orçamentos</a></li>
                            <li><a class="ultra-dropdown-item dropdown-item" href="lista_ordens_servico.php"><i class="fas fa-clipboard-list"></i>Lista de Ordens de Serviço</a></li>
                            <li><a class="ultra-dropdown-item dropdown-item" href="agendamento.php"><i class="fas fa-calendar-check"></i>Agendamento</a></li>
                            <li><a class="ultra-dropdown-item dropdown-item" href="servicos/lista.php"><i class="fas fa-tasks"></i>Todos Serviços</a></li>
                            <li><hr class="ultra-dropdown-divider dropdown-divider"></li>
                            <li class="dropdown-submenu">
                                <a class="ultra-dropdown-item dropdown-item dropdown-toggle" href="#" data-bs-toggle="dropdown" aria-expanded="false"><i class="fas fa-briefcase"></i>Empreitada</a>
                                <ul class="ultra-dropdown-menu dropdown-menu">
                                    <li><a class="ultra-dropdown-item dropdown-item" href="empreitadas_list.php"><i class="fas fa-list"></i>Listar Empreitadas</a></li>
                                    <li><a class="ultra-dropdown-item dropdown-item" href="empreitada_form.php"><i class="fas fa-plus"></i>Criar Empreitada</a></li>
                                </ul>
                            </li>
                            <li><a class="ultra-dropdown-item dropdown-item" href="servicos/categorias.php"><i class="fas fa-layer-group"></i>Categorias</a></li>
                        </ul>
                    </li>

                    <!-- Lavanderia -->
                    <li class="ultra-nav-item nav-item dropdown">
                        <a class="ultra-nav-link nav-link dropdown-toggle <?= str_contains($currentPage, 'lavanderia') ? 'active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-tshirt"></i> Lavanderia
                        </a>
                        <ul class="ultra-dropdown-menu dropdown-menu">
                            <li><a class="ultra-dropdown-item dropdown-item" href="lavanderia.php"><i class="fas fa-plus"></i>Cadastrar Item</a></li>
                            <li><a class="ultra-dropdown-item dropdown-item" href="lavanderia_list.php"><i class="fas fa-list"></i>Listar Itens</a></li>
                        </ul>
                    </li>

                    <!-- Financeiro -->
                    <li class="ultra-nav-item nav-item dropdown">
                        <a class="ultra-nav-link nav-link dropdown-toggle <?= str_contains($currentPage, 'financeiro') || $currentPage === 'contas.php' || $currentPage === 'lucro_detalhado.php' ? 'active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-coins"></i> Financeiro
                        </a>
                        <ul class="ultra-dropdown-menu dropdown-menu">
                            <li><a class="ultra-dropdown-item dropdown-item" href="/contas.php"><i class="fas fa-money-bill-wave"></i>Contas a Pagar</a></li>
                            <li><a class="ultra-dropdown-item dropdown-item" href="historico_tecnico.php"><i class="fas fa-history"></i>Histórico Técnico</a></li>
                            <li><a class="ultra-dropdown-item dropdown-item" href="financeiro.php"><i class="fas fa-wallet"></i>Financeiro Geral</a></li>
                            <li><hr class="ultra-dropdown-divider dropdown-divider"></li>
                            <li><a class="ultra-dropdown-item dropdown-item" href="/lucro_detalhado.php"><i class="fas fa-file-invoice-dollar"></i>Relatórios</a></li>
                        </ul>
                    </li>

                    <!-- Relatórios -->
                    <li class="ultra-nav-item nav-item dropdown">
                        <a class="ultra-nav-link nav-link dropdown-toggle <?= str_contains($currentPage, 'relatorios') ? 'active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-chart-bar"></i> Relatórios
                        </a>
                        <ul class="ultra-dropdown-menu dropdown-menu">
                            <li><a class="ultra-dropdown-item dropdown-item" href="relatorios/servicos.php"><i class="fas fa-concierge-bell"></i>Desempenho de Serviços</a></li>
                            <li><a class="ultra-dropdown-item dropdown-item" href="relatorios/clientes.php"><i class="fas fa-user-tie"></i>Clientes Ativos</a></li>
                            <li><a class="ultra-dropdown-item dropdown-item" href="relatorios/financeiro.php"><i class="fas fa-wallet"></i>Saúde Financeira</a></li>
                            <li><a class="ultra-dropdown-item dropdown-item" href="desempenho.php"><i class="fas fa-chart-area"></i>Desempenho Geral</a></li>
                            <li><hr class="ultra-dropdown-divider dropdown-divider"></li>
                            <li><a class="ultra-dropdown-item dropdown-item" href="relatorios/personalizado.php"><i class="fas fa-cog"></i>Personalizado</a></li>
                        </ul>
                    </li>

                    <!-- Configurações -->
                    <li class="ultra-nav-item nav-item dropdown">
                        <a class="ultra-nav-link nav-link dropdown-toggle <?= str_contains($currentPage, 'config') || $currentPage === 'dados_empresa.php' ? 'active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-cogs"></i> Configurações
                        </a>
                        <ul class="ultra-dropdown-menu dropdown-menu">
                            <li><a class="ultra-dropdown-item dropdown-item" href="usuarios.php"><i class="fas fa-list"></i>Listar Usuários</a></li>
                            <li><a class="ultra-dropdown-item dropdown-item" href="cadastrar_usuario.php"><i class="fas fa-user-plus"></i>Cadastrar Usuários</a></li>
                            <li><a class="ultra-dropdown-item dropdown-item" href="/dados_empresa.php"><i class="fas fa-building"></i>Dados da Empresa</a></li>
                            <li><hr class="ultra-dropdown-divider dropdown-divider"></li>
                            <li><a class="ultra-dropdown-item dropdown-item" href="config/integracao.php"><i class="fas fa-plug"></i>Integrações</a></li>
                        </ul>
                    </li>
                </ul>

                <!-- Área de Ações (Busca, Notificações, Perfil) -->
                <div class="d-flex align-items-center ms-3">
                    <?php if ($ultraMenuConfig['enable_search']): ?>
                    <!-- Busca Inteligente -->
                    <div class="ultra-search-container">
                        <button class="ultra-search-btn" onclick="ultraOpenSearch()">
                            <i class="fas fa-search"></i>
                            <span class="d-none d-lg-inline">Buscar...</span>
                            <span class="ultra-search-shortcut d-none d-xl-inline">Ctrl+K</span>
                        </button>
                    </div>
                    <?php endif; ?>

                    <?php if ($ultraMenuConfig['enable_notifications']): ?>
                    <!-- Notificações -->
                    <div class="ultra-notifications">
                        <button class="ultra-notification-btn" onclick="ultraShowNotifications()">
                            <i class="fas fa-bell"></i>
                            <span class="ultra-notification-badge">3</span>
                        </button>
                    </div>
                    <?php endif; ?>

                    <!-- Perfil do Usuário -->
                    <a href="/perfil.php" class="ultra-user-profile">
                        <div class="ultra-user-avatar">
                            <?= strtoupper(substr($ultraMenuConfig['user_name'], 0, 2)) ?>
                        </div>
                        <div class="ultra-user-info d-none d-lg-block">
                            <div class="ultra-user-name"><?= htmlspecialchars($ultraMenuConfig['user_name']) ?></div>
                            <div class="ultra-user-role"><?= htmlspecialchars($ultraMenuConfig['user_role']) ?></div>
                        </div>
                    </a>

                    <!-- Sair -->
                    <a href="/logout.php" class="ultra-nav-link ms-2" title="Sair do Sistema">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </div>
    </nav>
</div>

<!-- Modal de Busca -->
<div class="ultra-search-modal" id="ultraSearchModal">
    <div class="ultra-search-content">
        <div class="ultra-search-header">
            <input type="text" class="ultra-search-input" placeholder="Digite para buscar..." id="ultraSearchInput" autocomplete="off">
        </div>
        <div class="ultra-search-results" id="ultraSearchResults">
            <div class="ultra-search-category">
                <div class="ultra-search-category-title">Ações Rápidas</div>
                <div class="ultra-search-item" onclick="ultraNavigateTo('/criar_orcamento.php')">
                    <div class="ultra-search-item-icon">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                    <div class="ultra-search-item-content">
                        <div class="ultra-search-item-title">Criar Orçamento</div>
                        <div class="ultra-search-item-desc">Novo orçamento para cliente</div>
                    </div>
                </div>
                <div class="ultra-search-item" onclick="ultraNavigateTo('/cadastro.php')">
                    <div class="ultra-search-item-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="ultra-search-item-content">
                        <div class="ultra-search-item-title">Novo Cliente</div>
                        <div class="ultra-search-item-desc">Cadastrar novo cliente</div>
                    </div>
                </div>
                <div class="ultra-search-item" onclick="ultraNavigateTo('/agendamento.php')">
                    <div class="ultra-search-item-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="ultra-search-item-content">
                        <div class="ultra-search-item-title">Agendamento</div>
                        <div class="ultra-search-item-desc">Agendar novo serviço</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Ultra Menu JavaScript -->
<script id="ultra-menu-script">
(function() {
    'use strict';
    
    // Namespace para evitar conflitos
    window.UltraMenu = window.UltraMenu || {};
    
    // Variáveis globais do menu
    let ultraSearchModal = null;
    let ultraSearchInput = null;
    
    // Inicialização do menu
    function ultraInitMenu() {
        ultraSearchModal = document.getElementById('ultraSearchModal');
        ultraSearchInput = document.getElementById('ultraSearchInput');
        
        // Atalhos de teclado
        document.addEventListener('keydown', ultraHandleKeyboard);
        
        // Fechar modal ao clicar fora
        if (ultraSearchModal) {
            ultraSearchModal.addEventListener('click', function(e) {
                if (e.target === ultraSearchModal) {
                    ultraCloseSearch();
                }
            });
        }
        
        // Busca em tempo real
        if (ultraSearchInput) {
            ultraSearchInput.addEventListener('input', ultraHandleSearchInput);
        }
        
        // Gerenciar dropdowns aninhados
        ultraInitDropdowns();
        
        console.log('Ultra Menu inicializado com sucesso!');
    }
    
    // Gerenciar atalhos de teclado
    function ultraHandleKeyboard(e) {
        // Ctrl+K para abrir busca
        if (e.ctrlKey && e.key === 'k') {
            e.preventDefault();
            ultraOpenSearch();
        }
        
        // ESC para fechar modal
        if (e.key === 'Escape') {
            ultraCloseSearch();
        }
    }
    
    // Abrir modal de busca
    window.ultraOpenSearch = function() {
        if (ultraSearchModal) {
            ultraSearchModal.classList.add('show');
            if (ultraSearchInput) {
                ultraSearchInput.focus();
            }
            document.body.style.overflow = 'hidden';
        }
    };
    
    // Fechar modal de busca
    function ultraCloseSearch() {
        if (ultraSearchModal) {
            ultraSearchModal.classList.remove('show');
            if (ultraSearchInput) {
                ultraSearchInput.value = '';
            }
            document.body.style.overflow = '';
        }
    }
    
    // Busca em tempo real
    function ultraHandleSearchInput(e) {
        const query = e.target.value.toLowerCase();
        ultraUpdateSearchResults(query);
    }
    
    // Atualizar resultados da busca
    function ultraUpdateSearchResults(query) {
        const resultsContainer = document.getElementById('ultraSearchResults');
        if (!resultsContainer) return;
        
        if (!query) {
            // Mostrar resultados padrão
            resultsContainer.innerHTML = `
                <div class="ultra-search-category">
                    <div class="ultra-search-category-title">Ações Rápidas</div>
                    <div class="ultra-search-item" onclick="ultraNavigateTo('/criar_orcamento.php')">
                        <div class="ultra-search-item-icon">
                            <i class="fas fa-file-invoice"></i>
                        </div>
                        <div class="ultra-search-item-content">
                            <div class="ultra-search-item-title">Criar Orçamento</div>
                            <div class="ultra-search-item-desc">Novo orçamento para cliente</div>
                        </div>
                    </div>
                    <div class="ultra-search-item" onclick="ultraNavigateTo('/cadastro.php')">
                        <div class="ultra-search-item-icon">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <div class="ultra-search-item-content">
                            <div class="ultra-search-item-title">Novo Cliente</div>
                            <div class="ultra-search-item-desc">Cadastrar novo cliente</div>
                        </div>
                    </div>
                    <div class="ultra-search-item" onclick="ultraNavigateTo('/agendamento.php')">
                        <div class="ultra-search-item-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="ultra-search-item-content">
                            <div class="ultra-search-item-title">Agendamento</div>
                            <div class="ultra-search-item-desc">Agendar novo serviço</div>
                        </div>
                    </div>
                </div>
            `;
            return;
        }
        
        // Simular busca (em produção, seria uma chamada AJAX)
        const searchResults = ultraPerformSearch(query);
        
        if (searchResults.length === 0) {
            resultsContainer.innerHTML = `
                <div style="text-align: center; padding: 2rem; color: var(--ultra-gray);">
                    <i class="fas fa-search" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                    <div>Nenhum resultado encontrado para "${query}"</div>
                </div>
            `;
            return;
        }
        
        // Renderizar resultados
        let html = '<div class="ultra-search-category"><div class="ultra-search-category-title">Resultados</div>';
        searchResults.forEach(item => {
            html += `
                <div class="ultra-search-item" onclick="ultraNavigateTo('${item.url}')">
                    <div class="ultra-search-item-icon">
                        <i class="${item.icon}"></i>
                    </div>
                    <div class="ultra-search-item-content">
                        <div class="ultra-search-item-title">${ultraHighlightQuery(item.title, query)}</div>
                        <div class="ultra-search-item-desc">${item.description}</div>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        
        resultsContainer.innerHTML = html;
    }
    
    // Simular busca
    function ultraPerformSearch(query) {
        const allItems = [
            { title: 'Dashboard', description: 'Visão geral do sistema', url: '/dashboard.php', icon: 'fas fa-chart-pie' },
            { title: 'Criar Orçamento', description: 'Novo orçamento para cliente', url: '/criar_orcamento.php', icon: 'fas fa-file-invoice' },
            { title: 'Lista de Clientes', description: 'Gerenciar clientes', url: '/lista.php', icon: 'fas fa-users' },
            { title: 'Novo Cliente', description: 'Cadastrar novo cliente', url: '/cadastro.php', icon: 'fas fa-user-plus' },
            { title: 'Agendamento', description: 'Agendar novo serviço', url: '/agendamento.php', icon: 'fas fa-calendar-check' },
            { title: 'Lavanderia', description: 'Gerenciar itens de lavanderia', url: '/lavanderia.php', icon: 'fas fa-tshirt' },
            { title: 'Financeiro', description: 'Controle financeiro', url: '/financeiro.php', icon: 'fas fa-coins' },
            { title: 'Relatórios', description: 'Relatórios e análises', url: '/relatorios/', icon: 'fas fa-chart-bar' },
            { title: 'Configurações', description: 'Configurações do sistema', url: '/config/', icon: 'fas fa-cogs' }
        ];
        
        return allItems.filter(item => 
            item.title.toLowerCase().includes(query) || 
            item.description.toLowerCase().includes(query)
        );
    }
    
    // Destacar termo da busca
    function ultraHighlightQuery(text, query) {
        const regex = new RegExp(`(${query})`, 'gi');
        return text.replace(regex, '<mark style="background: var(--ultra-accent); color: white; padding: 0.125rem 0.25rem; border-radius: 4px;">$1</mark>');
    }
    
    // Navegar para URL
    window.ultraNavigateTo = function(url) {
        ultraCloseSearch();
        window.location.href = url;
    };
    
    // Mostrar notificações
    window.ultraShowNotifications = function() {
        ultraShowToast('🔔 Você tem 3 notificações pendentes!', 'info');
    };
    
    // Sistema de toast/notificações
    function ultraShowToast(message, type = 'info') {
        const colors = {
            info: 'var(--ultra-primary)',
            success: 'var(--ultra-success)',
            warning: 'var(--ultra-accent)',
            error: '#EF4444'
        };
        
        const toast = document.createElement('div');
        toast.style.cssText = `
            position: fixed;
            top: 100px;
            right: 20px;
            background: var(--ultra-white);
            color: ${colors[type]};
            padding: 1rem 1.5rem;
            border-radius: var(--ultra-border-radius);
            box-shadow: var(--ultra-shadow-lg);
            border-left: 4px solid ${colors[type]};
            z-index: 9999;
            max-width: 350px;
            animation: slideInRight 0.3s ease-out;
            font-weight: 500;
        `;
        toast.innerHTML = `
            <div style="display: flex; align-items: flex-start; gap: 0.75rem;">
                <i class="fas fa-info-circle" style="color: ${colors[type]}; margin-top: 0.125rem;"></i>
                <span style="flex: 1; line-height: 1.4;">${message}</span>
                <button onclick="this.parentElement.parentElement.remove()" style="background: none; border: none; color: var(--ultra-gray); cursor: pointer; padding: 0; margin-left: 0.5rem;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            if (toast.parentElement) {
                toast.style.animation = 'slideOutRight 0.3s ease-in';
                setTimeout(() => toast.remove(), 300);
            }
        }, 5000);
    }
    
    // Inicializar dropdowns aninhados
    function ultraInitDropdowns() {
        // Gerenciar dropdowns aninhados (ex.: "Empreitada")
        const submenuToggles = document.querySelectorAll('.dropdown-submenu > .dropdown-toggle');
        submenuToggles.forEach(function (toggle) {
            toggle.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                
                const currentSubmenu = toggle.closest('.dropdown-submenu');
                const currentSubmenuMenu = currentSubmenu.querySelector('.dropdown-menu');
                
                // Toggle do submenu atual
                if (currentSubmenuMenu) {
                    const isOpen = currentSubmenuMenu.classList.contains('show');
                    if (!isOpen) {
                        currentSubmenuMenu.classList.add('show');
                        toggle.setAttribute('aria-expanded', 'true');
                    } else {
                        currentSubmenuMenu.classList.remove('show');
                        toggle.setAttribute('aria-expanded', 'false');
                    }
                }
            });
        });
    }
    
    // Adicionar estilos de animação
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(100%);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @keyframes slideOutRight {
            from {
                opacity: 1;
                transform: translateX(0);
            }
            to {
                opacity: 0;
                transform: translateX(100%);
            }
        }
    `;
    document.head.appendChild(style);
    
    // Inicializar quando o DOM estiver pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', ultraInitMenu);
    } else {
        ultraInitMenu();
    }
    
    // Expor funções globais necessárias
    window.UltraMenu.init = ultraInitMenu;
    window.UltraMenu.openSearch = ultraOpenSearch;
    window.UltraMenu.showToast = ultraShowToast;
    
})();
</script>

<!-- Adicionar padding-top ao body para compensar o menu fixo -->
<style>
body {
    padding-top: 80px !important;
}
</style>

