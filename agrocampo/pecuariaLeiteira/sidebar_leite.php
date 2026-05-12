<div class="d-flex flex-column flex-shrink-0 p-3 text-white bg-dark" style="width: 280px; height: 100vh; position: fixed;">
    <a href="index.php" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none">
        <i class="fas fa-cow me-2 text-primary"></i>
        <span class="fs-4 fw-bold">Pecuária Leiteira</span>
    </a>
    <hr>
    <ul class="nav nav-pills flex-column mb-auto">
        <li class="nav-item">
            <a href="index.php" class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active bg-primary' : ''; ?>">
                <i class="fas fa-chart-line me-2"></i> Dashboard
            </a>
        </li>
        <li>
            <a href="vacas.php" class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'vacas.php' ? 'active bg-primary' : ''; ?>">
                <i class="fas fa-list me-2"></i> Cadastro do Rebanho
            </a>
        </li>
        <li>
            <a href="ordenha.php" class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'ordenha.php' ? 'active bg-primary' : ''; ?>">
                <i class="fas fa-fill-drip me-2"></i> Controle de Ordenha
            </a>
        </li>
        <li>
            <a href="reproducao.php" class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'reproducao.php' ? 'active bg-primary' : ''; ?>">
                <i class="fas fa-heart me-2"></i> Ciclo Reprodutivo
            </a>
        </li>
        <li>
            <a href="saude.php" class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'saude.php' ? 'active bg-primary' : ''; ?>">
                <i class="fas fa-stethoscopes me-2"></i> Saúde e Ocorrências
            </a>
        </li>
    </ul>
    <hr>
    <a href="../index.php" class="btn btn-outline-light w-100 rounded-pill">
        <i class="fas fa-arrow-left me-2"></i> Menu Principal
    </a>
</div>