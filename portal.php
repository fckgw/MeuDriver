<?php
/**
 * BDSoft Workspace - PORTAL CENTRAL DE TECNOLOGIAS
 * Localização: public_html/portal.php
 * Atualização: SSO para agroCampo, Gestão Administrativa e Identidade Workspace Drive
 */

// 1. Configurações Iniciais e Sessão
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// 2. Verificação de Segurança: O usuário está autenticado?
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

// Lógica de Inatividade (Redireciona após 3 minutos de inatividade no portal)
$tempo_limite = 180; // 3 minutos
if (isset($_SESSION['ultima_atividade']) && (time() - $_SESSION['ultima_atividade'] > $tempo_limite)) {
    session_unset();
    session_destroy();
    header("Location: login.php?msg=Sua sessao expirou por inatividade.");
    exit;
}
$_SESSION['ultima_atividade'] = time();

require_once 'config.php';

$usuario_id    = $_SESSION['usuario_id'];
$usuario_nivel = $_SESSION['usuario_nivel']; 
$usuario_nome  = $_SESSION['usuario_nome'];
$ultimo_acesso = isset($_SESSION['ultimo_acesso_info']) ? $_SESSION['ultimo_acesso_info'] : 'Primeiro Acesso';

// Extrair primeiro nome para saudação
$partes_nome = explode(' ', trim($usuario_nome));
$primeiro_nome = $partes_nome[0];

try {
    // 3. Buscar dados de vigência e plano do usuário
    $stmt_user = $pdo->prepare("SELECT data_criacao, dias_bonus_cupom, nivel, data_inicio, data_fim FROM usuarios WHERE id = :uid LIMIT 1");
    $stmt_user->execute([':uid' => $usuario_id]);
    $dados_cadastro = $stmt_user->fetch(PDO::FETCH_ASSOC);

    if (!$dados_cadastro) {
        session_destroy();
        header("Location: login.php");
        exit;
    }

    // --- LÓGICA DE TRIAL E PAINEL DE VIGÊNCIA ---
    $data_criacao = new DateTime($dados_cadastro['data_criacao']);
    $data_hoje    = new DateTime(date('Y-m-d'));
    $dias_desde_cadastro = $data_hoje->diff($data_criacao)->days;
    $dias_bonus   = (int)$dados_cadastro['dias_bonus_cupom'];
    
    $prazo_total_teste = 14 + $dias_bonus;
    $esta_no_periodo_teste = ($dias_desde_cadastro <= $prazo_total_teste);

    $html_plano = "";
    $html_alerta = "";
    $is_admin = (strtolower($dados_cadastro['nivel']) === 'admin');

    if ($is_admin) {
        $html_plano = "<span class='badge bg-success shadow-sm px-3 py-2 mt-2'><i class='fas fa-infinity me-1'></i> Acesso Vitalício Master</span>";
    } else {
        if (!empty($dados_cadastro['data_inicio']) && !empty($dados_cadastro['data_fim']) && $dados_cadastro['data_fim'] !== '0000-00-00') {
            $dt_ini = date('d/m/Y', strtotime($dados_cadastro['data_inicio']));
            $dt_fim = date('d/m/Y', strtotime($dados_cadastro['data_fim']));
            $fim_obj = new DateTime($dados_cadastro['data_fim']);
            $diferenca = $data_hoje->diff($fim_obj);
            $dias_restantes = $diferenca->days;
            $passou_da_data = $diferenca->invert;

            $html_plano = "
                <div class='d-flex align-items-center gap-3 mt-2'>
                    <div class='bg-light px-3 py-1 rounded border small text-center'>
                        <small class='text-muted fw-bold' style='font-size:8px;'>INÍCIO</small><br>
                        <span class='fw-bold'>$dt_ini</span>
                    </div>
                    <div class='bg-light px-3 py-1 rounded border small text-center'>
                        <small class='text-muted fw-bold' style='font-size:8px;'>VENCIMENTO</small><br>
                        <span class='fw-bold'>$dt_fim</span>
                    </div>
                </div>";

            if ($passou_da_data == 1 && $data_hoje->format('Y-m-d') !== $fim_obj->format('Y-m-d')) {
                $html_alerta = "<div class='alert alert-danger py-2 px-3 mb-0 small fw-bold shadow-sm'><i class='fas fa-exclamation-circle me-1'></i> Plano Expirado. Renove seu acesso.</div>";
            } else {
                $cor_aviso = ($dias_restantes <= 7) ? 'bg-warning text-dark' : 'bg-primary text-white';
                $html_alerta = "<div class='card border-0 shadow-sm {$cor_aviso} px-3 py-2 small fw-bold'>Acesso expira em {$dias_restantes} dias.</div>";
            }
        } else {
            $dias_restantes_trial = max(0, $prazo_total_teste - $dias_desde_cadastro);
            $html_plano = "<span class='badge bg-secondary shadow-sm px-3 py-2 mt-2'><i class='fas fa-clock me-1'></i> Modo de Teste Ativo</span>";
            $html_alerta = "<div class='card border-0 shadow-sm bg-info text-white px-3 py-2 small fw-bold'>Trial: {$dias_restantes_trial} dias restantes.</div>";
        }
    }

    /**
     * 4. CARREGAMENTO DINÂMICO DOS MÓDULOS (APPS)
     */
    if ($is_admin || ($esta_no_periodo_teste && !$passou_da_data)) {
        // Admins e usuários em Trial veem tudo
        $query_modulos = "SELECT * FROM modulos ORDER BY nome ASC";
        $stmt_exec = $pdo->query($query_modulos);
        $meus_modulos = $stmt_exec->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Membros comuns veem apenas o que o Admin liberou na tabela usuarios_modulos
        $query_restrita = "SELECT m.* FROM modulos m 
                           INNER JOIN usuarios_modulos um ON m.id = um.modulo_id 
                           WHERE um.usuario_id = :uid ORDER BY m.nome ASC";
        $stmt_exec = $pdo->prepare($query_restrita);
        $stmt_exec->execute([':uid' => $usuario_id]);
        $meus_modulos = $stmt_exec->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    die("Erro interno ao processar portal: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workspace Cloud - Portal</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root { --primary-blue: #1a73e8; --bg-body: #f8f9fa; --dark-blue: #292f4c; }
        body { background-color: var(--bg-body); font-family: 'Inter', sans-serif; color: #202124; margin: 0; }

        .navbar-top { background: #fff; border-bottom: 1px solid #e0e0e0; padding: 0.8rem 0; }
        .navbar-brand { font-weight: 800; color: var(--primary-blue) !important; font-size: 1.4rem; letter-spacing: -0.5px; }

        /* Estilo dos Cards dos Aplicativos */
        .app-card {
            background: #ffffff;
            border: 1px solid #e0e6ed;
            border-radius: 28px;
            padding: 40px 25px;
            text-align: center;
            transition: all 0.3s cubic-bezier(.25,.8,.25,1);
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            align-items: center;
            height: 100%;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
        }

        .app-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            border-color: var(--primary-blue);
        }

        .app-icon-box {
            font-size: 3rem;
            margin-bottom: 20px;
            width: 90px;
            height: 90px;
            line-height: 90px;
            background-color: #f8fafc;
            border-radius: 22px;
            transition: 0.3s;
        }

        .app-card:hover .app-icon-box {
            background-color: #e8f0fe;
            color: var(--primary-blue);
        }

        .app-title { font-weight: 700; font-size: 1.2rem; margin-bottom: 8px; color: #202124; }
        .app-desc { font-size: 0.85rem; color: #5f6368; line-height: 1.5; }

        /* Seção Administrativa */
        .admin-section {
            background: #fff;
            border-radius: 24px;
            border: 1px dashed #ced4da;
            padding: 35px;
            margin-top: 60px;
        }

        .btn-admin-shortcut {
            background: #fff;
            border: 1px solid #eee;
            border-radius: 15px;
            padding: 20px;
            text-align: left;
            transition: 0.2s;
            text-decoration: none;
            color: inherit;
            display: block;
            height: 100%;
        }

        .btn-admin-shortcut:hover {
            background: #fdfdfd;
            box-shadow: 0 8px 15px rgba(0,0,0,0.05);
            border-color: #ccc;
        }
    </style>
</head>
<body>

<nav class="navbar-top shadow-sm">
    <div class="container d-flex justify-content-between align-items-center">
        <a class="navbar-brand" href="portal.php">
            <i class="fas fa-cloud me-2"></i>Workspace <span class="text-dark">Cloud</span>
        </a>
        <div class="d-flex align-items-center">
            <div class="text-end me-4 d-none d-md-block">
                <div class="small fw-bold text-dark"><?php echo htmlspecialchars($usuario_nome); ?></div>
                <div class="text-muted" style="font-size: 10px;">Acesso: <?php echo $ultimo_acesso; ?></div>
            </div>
            <a href="logout.php" class="btn btn-outline-danger btn-sm rounded-pill px-4 fw-bold">SAIR</a>
        </div>
    </div>
</nav>

<div class="container pb-5">
    
    <div class="row align-items-center mt-5 mb-5">
        <div class="col-lg-6 text-center text-lg-start mb-4">
            <h1 class="fw-bold" style="font-size: 2.5rem;">Olá, <?php echo $primeiro_nome; ?>!</h1>
            <p class="text-muted fs-5">Escolha uma tecnologia para gerenciar seus ativos.</p>
        </div>
        
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 bg-white">
                <div class="card-body p-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div class="text-center text-md-start">
                        <h6 class="fw-bold text-dark mb-1 small text-uppercase">Plano de Acesso</h6>
                        <?php echo $html_plano; ?>
                    </div>
                    <div class="w-100 w-md-auto"><?php echo $html_alerta; ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- GRADE DE MÓDULOS (APPS) -->
    <div class="row g-4">
        <?php if (empty($meus_modulos)): ?>
            <div class="col-12">
                <div class="alert alert-light text-center p-5 border shadow-sm rounded-4">
                    <i class="fas fa-user-lock fa-3x text-muted mb-3 opacity-50"></i>
                    <h5 class="fw-bold">Nenhum módulo ativo</h5>
                    <p class="text-muted mb-0">Contate o administrador para liberar o acesso aos seus aplicativos (Drive, agroCampo, etc).</p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($meus_modulos as $mod): 
                // LÓGICA DE SSO PARA AGROCAMPO:
                // Se o slug for agrocampo, ele aponta para o gerador de token
                $href = ($mod['slug'] == 'agrocampo') ? "ir_para.php?modulo=agrocampo" : htmlspecialchars($mod['slug']);
            ?>
                <div class="col-xl-3 col-lg-4 col-md-6">
                    <a href="<?php echo $href; ?>" class="app-card shadow-sm">
                        <div class="app-icon-box text-primary shadow-sm">
                            <i class="fas <?php echo htmlspecialchars($mod['icone']); ?>"></i>
                        </div>
                        <div class="app-title"><?php echo htmlspecialchars($mod['nome']); ?></div>
                        <div class="app-desc text-center"><?php echo htmlspecialchars($mod['descricao']); ?></div>
                    </a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- SEÇÃO EXCLUSIVA DO ADMINISTRADOR -->
    <?php if ($is_admin): ?>
        <div class="admin-section shadow-sm">
            <div class="d-flex align-items-center mb-4">
                <div class="bg-danger bg-opacity-10 p-3 rounded-circle me-3">
                    <i class="fas fa-user-shield text-danger fa-xl"></i>
                </div>
                <div>
                    <h4 class="fw-bold mb-0">Painel de Controle Administrador</h4>
                    <p class="text-muted mb-0 small text-uppercase fw-bold">Gestão Global do Ecossistema BDSoft</p>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-3">
                    <a href="admin_usuarios.php" class="btn-admin-shortcut shadow-sm">
                        <i class="fas fa-users-cog fa-2x text-danger mb-3"></i>
                        <div class="fw-bold">Usuários e Planos</div>
                        <div class="small text-muted">Gestão de assinaturas e quota de GB.</div>
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="admin_permissoes.php" class="btn-admin-shortcut shadow-sm">
                        <i class="fas fa-key fa-2x text-warning mb-3"></i>
                        <div class="fw-bold">Permissão de Apps</div>
                        <div class="small text-muted">Liberar agroCampo e outros módulos.</div>
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="admin_modulos.php" class="btn-admin-shortcut shadow-sm">
                        <i class="fas fa-cubes fa-2x text-primary mb-3"></i>
                        <div class="fw-bold">Gerenciar Apps</div>
                        <div class="small text-muted">Cadastrar novos sistemas e ícones.</div>
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="logs.php" class="btn-admin-shortcut shadow-sm">
                        <i class="fas fa-history fa-2x text-secondary mb-3"></i>
                        <div class="fw-bold">Logs e Auditoria</div>
                        <div class="small text-muted">Rastrear acessos e atividades.</div>
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>

</div>

<footer class="text-center mt-5 py-5 text-muted small bg-white border-top">
    <div class="container">
        <p class="mb-1 fw-bold">Workspace Cloud &copy; <?php echo date('Y'); ?> | Tecnologia Desenvolvida por BDSoftech</p>
        <p class="mb-0" style="font-size: 10px;">IP de Acesso: <?php echo $_SERVER['REMOTE_ADDR']; ?> - Conexão Criptografada SSL</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Script para Logout por Inatividade (Lado do Cliente) -->
<script>
    let timer;
    function resetTimer() {
        clearTimeout(timer);
        timer = setTimeout(() => {
            alert("Sua sessão expirou por inatividade.");
            window.location.href = 'logout.php';
        }, 180000); // 3 minutos
    }
    window.onload = resetTimer;
    document.onmousemove = resetTimer;
    document.onkeypress = resetTimer;
</script>

</body>
</html>