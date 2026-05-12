<div class="d-flex flex-column flex-shrink-0 p-3 text-white bg-dark" style="width: 280px; height: 100vh; position: fixed;">
    <a href="index.php" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none">
        <i class="fas fa-map-marked-alt me-2 text-success"></i>
        <span class="fs-4 fw-bold">Monitoramento</span>
    </a>
    <hr>
    <ul class="nav nav-pills flex-column mb-auto">
        <li class="nav-item">
            <a href="index.php" class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active bg-success' : ''; ?>">
                <i class="fas fa-chart-pie me-2"></i> Painel Geral
            </a>
        </li>
        <li>
            <a href="fazendas.php" class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'fazendas.php' ? 'active bg-success' : ''; ?>">
                <i class="fas fa-republican me-2"></i> Cadastro de Fazendas
            </a>
        </li>
        <li>
            <a href="talhoes_cadastro.php" class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'talhoes_cadastro.php' ? 'active bg-success' : ''; ?>">
                <i class="fas fa-draw-polygon me-2"></i> Desenhar Talhões
            </a>
        </li>

        <li>
            <a href="ocorrencias.php" class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'ocorrencias.php' ? 'active bg-success' : ''; ?>">
                <i class="fas fa-bug me-2 text-danger"></i> Alertas de Pragas
            </a>
        </li>

    </ul>
    <hr>
    <div class="dropdown">
        <a href="../index.php" class="btn btn-outline-light w-100 rounded-pill">
            <i class="fas fa-sign-out-alt me-2"></i> Sair do Módulo
        </a>
    </div>
</div>

<style>
    .nav-link:hover { background-color: rgba(25, 135, 84, 0.2); }
</style>