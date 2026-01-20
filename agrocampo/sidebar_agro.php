<?php
/**
 * BDSoft Workspace - SIDEBAR AGRO 
 * Local: agrocampo/sidebar_agro.php
 */
$usuario_id_sid = $_SESSION['usuario_id'];
$nivel_sid = $_SESSION['usuario_nivel'];
$pagina_atual = basename($_SERVER['PHP_SELF']);

function validaAcessoAgro($pdo, $uid, $arquivo, $nivel) {
    if ($nivel === 'admin') return true;
    $stmt = $pdo->prepare("SELECT 1 FROM usuarios_agro_permissões up 
                           INNER JOIN agro_submodulos s ON up.submodulo_id = s.id 
                           WHERE up.usuario_id = ? AND s.slug = ?");
    $stmt->execute([$uid, $arquivo]);
    return $stmt->fetch() ? true : false;
}
?>
<style>
    :root { --agro-green-dark: #1e3d1a; --agro-green-primary: #2d5a27; --agro-green-light: #8bc34a; --sidebar-w: 280px; }
    body { background-color: #f4f7f4; margin: 0; display: flex; min-height: 100vh; font-family: 'Segoe UI', sans-serif; }
    
    .sidebar { width: var(--sidebar-w); background: var(--agro-green-dark); color: white; position: fixed; height: 100vh; z-index: 1050; display: flex; flex-direction: column; box-shadow: 4px 0 10px rgba(0,0,0,0.1); }
    .main-wrapper { flex: 1; margin-left: var(--sidebar-w); padding: 40px; width: calc(100% - var(--sidebar-w)); min-height: 100vh; }
    
    .sidebar .nav-link { color: rgba(255,255,255,0.7); padding: 15px 25px; font-weight: 500; border: none; transition: 0.2s; display: flex; align-items: center; }
    .sidebar .nav-link i { width: 25px; margin-right: 10px; }
    .sidebar .nav-link:hover { background: rgba(255,255,255,0.05); color: white; }
    .sidebar .nav-link.active { background: var(--agro-green-primary); color: white; border-left: 5px solid var(--agro-green-light); }

    @media (max-width: 991px) {
        .sidebar { left: calc(-1 * var(--sidebar-w)); }
        .sidebar.active { left: 0; }
        .main-wrapper { margin-left: 0; width: 100%; padding: 20px; }
    }
</style>

<div class="sidebar" id="sidebar">
    <div class="p-4 text-center">
        <h4 class="fw-bold mb-0 text-success"><i class="fas fa-seedling me-2"></i>AgroCampo</h4>
        <small class="text-white opacity-50">BDSoft Workspace</small>
    </div>
    
    <nav class="nav flex-column mt-3">
        <?php if (validaAcessoAgro($pdo, $usuario_id_sid, 'index.php', $nivel_sid)): ?>
            <a class="nav-link <?php echo ($pagina_atual == 'index.php') ? 'active' : ''; ?>" href="index.php"><i class="fas fa-chart-line"></i> Painel Geral</a>
        <?php endif; ?>

        <?php if (validaAcessoAgro($pdo, $usuario_id_sid, 'financeiro.php', $nivel_sid)): ?>
            <a class="nav-link <?php echo ($pagina_atual == 'financeiro.php') ? 'active' : ''; ?>" href="financeiro.php"><i class="fas fa-hand-holding-usd"></i> Contas Pagar/Receber</a>
            <a class="nav-link <?php echo ($pagina_atual == 'relatorio_financeiro.php') ? 'active' : ''; ?>" href="relatorio_financeiro.php"><i class="fas fa-file-invoice-dollar"></i> Relatórios BI</a>
        <?php endif; ?>

        <?php if (validaAcessoAgro($pdo, $usuario_id_sid, 'ordenha.php', $nivel_sid)): ?>
            <a class="nav-link <?php echo ($pagina_atual == 'ordenha.php') ? 'active' : ''; ?>" href="ordenha.php"><i class="fas fa-cow"></i> Ordenha Prática</a>
        <?php endif; ?>

        <hr class="mx-3 opacity-25">

        <?php if ($nivel_sid === 'admin'): ?>
            <a class="nav-link text-info <?php echo ($pagina_atual == 'admin_permissoes.php') ? 'active' : ''; ?>" href="admin_permissoes.php"><i class="fas fa-user-lock"></i> Gestão de Acessos</a>
        <?php endif; ?>

        <a class="nav-link mt-4" href="../portal.php"><i class="fas fa-th"></i> Workspace</a>
        <a class="nav-link text-danger mt-auto" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a>
    </nav>
</div>