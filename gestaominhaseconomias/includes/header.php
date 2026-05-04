<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minhas Economias - Workspace BDS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary-blue: #1a73e8; --bg-body: #f8f9fa; }
        body { background-color: var(--bg-body); font-family: 'Segoe UI', system-ui, sans-serif; }
        .navbar-main { background-color: #ffffff; border-bottom: 1px solid #dadce0; padding: 12px 0; }
        .nav-link { color: #5f6368; font-weight: 500; border-radius: 8px; margin: 0 3px; }
        .nav-link.active { color: var(--primary-blue); background-color: #e8f0fe; }
        .card-finance { border: none; border-radius: 18px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-main sticky-top mb-4 shadow-sm">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold text-primary" href="index.php?p=dashboard">
            <i class="fas fa-wallet me-2"></i>Minhas Economias
        </a>
        
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navBDS">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navBDS">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link <?= $pagina == 'dashboard' ? 'active' : '' ?>" href="index.php?p=dashboard">Início</a></li>
                <li class="nav-item"><a class="nav-link <?= $pagina == 'transacoes' ? 'active' : '' ?>" href="index.php?p=transacoes">Transações</a></li>
                <li class="nav-item"><a class="nav-link <?= $pagina == 'categorias' ? 'active' : '' ?>" href="index.php?p=categorias">Categorias</a></li>
                <li class="nav-item"><a class="nav-link <?= $pagina == 'sonhos' ? 'active' : '' ?>" href="index.php?p=sonhos">Sonhos</a></li>
                
                <li class="nav-item">
                <a class="nav-link <?= ($pagina == 'controle') ? 'active' : '' ?>" href="index.php?p=controle">
                    <i class="fas fa-car"></i> <span>Controle</span>
                </a>
                </li>

                <li class="nav-item"><a class="nav-link <?= $pagina == 'orcamento' ? 'active' : '' ?>" href="index.php?p=orcamento">Orçamento</a></li>
                <li class="nav-item"><a class="nav-link <?= $pagina == 'analise' ? 'active' : '' ?>" href="index.php?p=analise">Análise</a></li>
                <li class="nav-item"><a class="nav-link" href="exportar.php?type=pdf&mes=<?= $mes_filtro ?>&ano=<?= $ano_filtro ?>">Relatórios</a></li>
                <li class="nav-item"><a class="nav-link <?= $pagina == 'config' ? 'active' : '' ?>" href="index.php?p=config">Configurações</a></li>
            </ul>

            <div class="d-flex align-items-center">
                <div class="text-end me-3 d-none d-md-block">
                    <div class="fw-bold small text-dark"><?= $_SESSION['usuario_nome'] ?></div>
                    <div class="text-muted" style="font-size: 10px;">
                        Acesso: <span class="text-primary fw-bold"><?= $_SESSION['ultimo_acesso_info'] ?? date('d/m/Y H:i') ?></span>
                    </div>
                </div>
                <div class="dropdown">
                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 35px; height: 35px; cursor: pointer;" data-bs-toggle="dropdown">
                        <?= strtoupper(substr($_SESSION['usuario_nome'], 0, 1)) ?>
                    </div>
                    <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg mt-2">
                        <li><a class="dropdown-item" href="../../portal.php">Portal Workspace</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="../../logout.php">Sair</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>

<div class="container-fluid px-4">