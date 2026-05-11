<?php
/**
 * BDSoft Workspace - SIDEBAR AGRO DEFINITIVA
 */
$usuario_id_sid = $_SESSION['usuario_id'];
$nivel_sid = $_SESSION['usuario_nivel'];
$pagina_ativa = basename($_SERVER['PHP_SELF']);
?>
<!-- CSS Mestre para evitar sobreposição -->
<style>
    :root {
        --agro-dark: #1a3317;
        --agro-main: #2d5a27;
        --agro-accent: #8bc34a;
        --sidebar-w: 280px;
    }
    body { display: flex; min-height: 100vh; background-color: #f4f7f4; margin: 0; font-family: 'Segoe UI', sans-serif; }
    
    /* Sidebar Fixa na Esquerda */
    .sidebar-agro {
        width: var(--sidebar-w);
        background-color: var(--agro-dark);
        color: #ffffff;
        position: fixed;
        left: 0; top: 0; height: 100vh;
        z-index: 1050;
        display: flex; flex-direction: column;
        box-shadow: 4px 0 10px rgba(0,0,0,0.2);
    }

    /* Empurra o conteúdo para não ficar por baixo do menu */
    .main-wrapper {
        flex: 1;
        margin-left: var(--sidebar-w);
        padding: 40px;
        width: calc(100% - var(--sidebar-w));
        transition: 0.3s;
    }

    .sidebar-agro .nav-link {
        color: rgba(255,255,255,0.6);
        padding: 15px 25px;
        font-weight: 500;
        text-decoration: none;
        display: flex; align-items: center;
        transition: 0.2s;
    }

    .sidebar-agro .nav-link:hover { background: rgba(255,255,255,0.05); color: white; }
    .sidebar-agro .nav-link.active {
        background: var(--agro-main);
        color: white;
        border-left: 5px solid var(--agro-accent);
    }
    .sidebar-agro i { width: 25px; margin-right: 10px; font-size: 1.1rem; }

    @media (max-width: 991px) {
        .sidebar-agro { left: -280px; }
        .sidebar-agro.active { left: 0; }
        .main-wrapper { margin-left: 0; width: 100%; padding: 20px; }
    }
</style>

<div class="sidebar-agro shadow" id="sidebar">
    <div class="p-4 text-center border-bottom border-secondary">
        <h4 class="fw-bold mb-0 text-success"><i class="fas fa-seedling me-2"></i>AgroCampo</h4>
        <small class="opacity-50">BDSoft Workspace</small>
    </div>
    
    <nav class="nav flex-column mt-3">
        <a class="nav-link <?php echo ($pagina_ativa == 'index.php') ? 'active' : ''; ?>" href="index.php">
            <i class="fas fa-th"></i> Painel Geral
        </a>
        <a class="nav-link <?php echo ($pagina_ativa == 'financeiro.php') ? 'active' : ''; ?>" href="financeiro.php">
            <i class="fas fa-hand-holding-usd"></i> Fluxo de Caixa
        </a>
        <a class="nav-link <?php echo ($pagina_ativa == 'provisoes.php') ? 'active' : ''; ?>" href="provisoes.php">
            <i class="fas fa-calendar-alt"></i> Provisionamento
        </a>

        <a href="farmacia.php" class="nav-link text-white">
            <i class="fas fa-first-aid me-2"></i> Farmácia Rural
        </a>

        <a class="nav-link <?php echo ($pagina_ativa == 'relatorio_financeiro.php') ? 'active' : ''; ?>" href="relatorio_financeiro.php">
            <i class="fas fa-chart-bar"></i> Relatórios BI
        </a>
        
        <?php if ($_SESSION['usuario_nivel'] === 'admin'): ?>
            <hr class="mx-3 opacity-25">
            <a class="nav-link text-info <?php echo ($pagina_ativa == 'admin_permissoes.php') ? 'active' : ''; ?>" href="admin_permissoes.php">
                <i class="fas fa-user-lock"></i> Gestão de Acessos
            </a>
        <?php endif; ?>

        <a class="nav-link mt-4" href="../portal.php"><i class="fas fa-arrow-left"></i> Workspace</a>
        <a class="nav-link text-danger mt-auto" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a>
    </nav>
</div>