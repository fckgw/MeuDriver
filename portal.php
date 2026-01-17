<?php
/**
 * BDSoft Workspace - PORTAL CENTRAL DE APLICATIVOS
 * Localização: public_html/portal.php
 */

// 1. Iniciar Sessão e Configurações de Erro
session_start();

// 2. Verificação de Segurança: O usuário está autenticado?
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config.php';

// 3. Captura de Dados do Usuário na Sessão
$usuario_id    = $_SESSION['usuario_id'];
$usuario_nome  = $_SESSION['usuario_nome'];
$usuario_nivel = $_SESSION['usuario_nivel']; // 'admin' ou 'usuario'
$ultimo_acesso = isset($_SESSION['ultimo_acesso_info']) ? $_SESSION['ultimo_acesso_info'] : 'Recente';

// Extrair o primeiro nome para uma saudação amigável
$partes_nome = explode(' ', trim($usuario_nome));
$primeiro_nome = $partes_nome[0];

/**
 * 4. LÓGICA DE CARREGAMENTO DOS MÓDULOS (APPS)
 * - Se for Administrador: Busca todos os módulos ativos na tabela 'modulos'.
 * - Se for Usuário Comum: Busca apenas os módulos vinculados a ele na tabela 'usuarios_modulos'.
 */
try {
    if (trim(strtolower($usuario_nivel)) === 'admin') {
        // Administradores têm visão global de todas as tecnologias cadastradas
        $query_modulos = "SELECT * FROM modulos ORDER BY nome ASC";
        $stmt_modulos = $pdo->query($query_modulos);
        $meus_modulos = $stmt_modulos->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Usuários comuns dependem de atribuição manual do administrador
        $query_permissao = "SELECT m.nome, m.slug, m.icone, m.descricao 
                            FROM modulos m 
                            INNER JOIN usuarios_modulos um ON m.id = um.modulo_id 
                            WHERE um.usuario_id = :uid 
                            ORDER BY m.nome ASC";
        $stmt_permissao = $pdo->prepare($query_permissao);
        $stmt_permissao->execute([':uid' => $usuario_id]);
        $meus_modulos = $stmt_permissao->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $erro_sql) {
    // Log do erro interno para o desenvolvedor
    error_log("Erro ao carregar módulos no Portal: " . $erro_sql->getMessage());
    $meus_modulos = [];
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal - BDSoft Workspace</title>
    
    <!-- CSS: Bootstrap 5 e FontAwesome 6 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-color: #1a73e8;
            --bg-body: #f8f9fa;
            --text-dark: #202124;
            --text-muted: #5f6368;
        }

        body {
            background-color: var(--bg-body);
            font-family: 'Inter', sans-serif;
            color: var(--text-dark);
            margin: 0;
            padding: 0;
        }

        /* Navbar Customizada */
        .navbar-workspace {
            background-color: #ffffff;
            border-bottom: 1px solid #e0e0e0;
            padding: 1rem 2rem;
        }

        .navbar-brand {
            font-weight: 700;
            color: var(--primary-color) !important;
            letter-spacing: -0.5px;
        }

        /* Seção de Boas-vindas */
        .welcome-header {
            padding: 60px 0 40px 0;
            text-align: center;
        }

        .welcome-header h1 {
            font-weight: 700;
            font-size: 2.2rem;
            margin-bottom: 10px;
        }

        /* Estilo dos Quadrinhos (Cards de Apps) */
        .app-card {
            background: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 24px;
            padding: 40px 25px;
            text-align: center;
            transition: all 0.3s cubic-bezier(.25,.8,.25,1);
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            align-items: center;
            height: 100%;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .app-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.08);
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .app-icon {
            font-size: 3.5rem;
            margin-bottom: 20px;
            width: 90px;
            height: 90px;
            line-height: 90px;
            background-color: #f8f9fa;
            border-radius: 20px;
            transition: 0.3s;
        }

        .app-card:hover .app-icon {
            background-color: #e8f0fe;
        }

        .app-title {
            font-weight: 700;
            font-size: 1.2rem;
            margin-bottom: 8px;
        }

        .app-description {
            font-size: 0.9rem;
            color: var(--text-muted);
            line-height: 1.4;
        }

        /* Crachá de Admin */
        .admin-badge {
            background-color: #fce8e6;
            color: #d93025;
            font-weight: 700;
            font-size: 0.65rem;
            padding: 4px 10px;
            border-radius: 20px;
            text-transform: uppercase;
            margin-bottom: 15px;
        }

        .btn-logout {
            font-weight: 600;
            border-radius: 30px;
            padding: 8px 20px;
        }
    </style>
</head>
<body>

<!-- BARRA DE NAVEGAÇÃO -->
<nav class="navbar navbar-workspace shadow-sm">
    <div class="container">
        <a class="navbar-brand fs-4" href="index.php">
            <i class="fas fa-th-large me-2"></i>BDSoft Workspace
        </a>
        <div class="ms-auto d-flex align-items-center">
            <div class="text-end me-3 d-none d-md-block">
                <div class="small fw-bold text-dark"><?php echo htmlspecialchars($usuario_nome); ?></div>
                <div class="text-muted" style="font-size: 0.7rem;">Acesso: <?php echo $ultimo_acesso; ?></div>
            </div>
            <a href="logout.php" class="btn btn-outline-danger btn-sm btn-logout">Sair</a>
        </div>
    </div>
</nav>

<div class="container">
    <div class="welcome-header">
        <h1>Olá, <?php echo htmlspecialchars($primeiro_nome); ?>!</h1>
        <p class="text-muted fs-5">Escolha uma ferramenta para começar a trabalhar.</p>
    </div>

    <!-- GRADE DE APLICATIVOS -->
    <div class="row g-4 justify-content-center">
        
        <?php if (empty($meus_modulos)): ?>
            <!-- Alerta caso o usuário não tenha nada liberado -->
            <div class="col-md-6">
                <div class="alert alert-light border shadow-sm p-5 text-center rounded-4">
                    <i class="fas fa-user-lock fa-3x text-warning mb-3"></i>
                    <h4 class="fw-bold">Acesso Restrito</h4>
                    <p class="text-muted">Você ainda não possui módulos vinculados à sua conta.<br>Por favor, solicite a ativação ao administrador do sistema.</p>
                    <a href="logout.php" class="btn btn-primary rounded-pill px-4 mt-3">Fazer Logoff</a>
                </div>
            </div>
        <?php else: ?>
            
            <!-- Listagem Dinâmica de Módulos (Drive, Projetos, AgroCampo) -->
            <?php foreach ($meus_modulos as $modulo): ?>
                <div class="col-lg-4 col-md-6 col-sm-12">
                    <a href="<?php echo htmlspecialchars($modulo['slug']); ?>" class="app-card">
                        <div class="app-icon">
                            <i class="fas <?php echo htmlspecialchars($modulo['icone']); ?>"></i>
                        </div>
                        <div class="app-title"><?php echo htmlspecialchars($modulo['nome']); ?></div>
                        <div class="app-description"><?php echo htmlspecialchars($modulo['descricao']); ?></div>
                    </a>
                </div>
            <?php endforeach; ?>

        <?php endif; ?>

        <!-- CARD EXCLUSIVO DO ADMINISTRADOR -->
        <?php if (trim(strtolower($usuario_nivel)) === 'admin'): ?>
            <div class="col-lg-4 col-md-6 col-sm-12">
                <a href="admin_usuarios.php" class="app-card border-danger border-opacity-25">
                    <div class="admin-badge">Segurança</div>
                    <div class="app-icon text-danger">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="app-title text-danger">Painel Admin</div>
                    <div class="app-description">Gestão de usuários, permissões de módulos, logs do sistema e cotas de espaço.</div>
                </a>
            </div>
        <?php endif; ?>

    </div>
</div>

<footer class="text-center mt-5 py-5 text-muted small">
    &copy; <?php echo date('Y'); ?> <strong>BDSoft Tecnologia</strong> - Todos os direitos reservados.<br>
    Hospedado em ambiente de produção seguro.
</footer>

<!-- JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>