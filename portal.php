<?php
/**
 * BDSoft Workspace - PORTAL CENTRAL DE SELEÇÃO DE TECNOLOGIAS
 * Localização: public_html/portal.php
 * Atualização: Adicionado Painel de Vigência do Plano (Datas e Alertas)
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

require_once 'config.php';

$usuario_id    = $_SESSION['usuario_id'];
$usuario_nivel = $_SESSION['usuario_nivel']; // 'admin' ou 'usuario'
$usuario_nome  = $_SESSION['usuario_nome'];
$ultimo_acesso = isset($_SESSION['ultimo_acesso_info']) ? $_SESSION['ultimo_acesso_info'] : 'Recente';

// Extrair primeiro nome para saudação
$partes_nome = explode(' ', trim($usuario_nome));
$primeiro_nome = $partes_nome[0];

try {
    // 3. Buscar dados de cadastro do usuário para calcular Período e Trial
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
    $data_hoje    = new DateTime();
    $dias_desde_cadastro = $data_hoje->diff($data_criacao)->days;
    $dias_bonus   = (int)$dados_cadastro['dias_bonus_cupom'];
    
    $prazo_total_teste = 14 + $dias_bonus;
    $está_no_periodo_teste = ($dias_desde_cadastro <= $prazo_total_teste);

    // Variáveis Visuais para o Cabeçalho
    $html_plano = "";
    $html_alerta = "";
    $is_admin = (strtolower($dados_cadastro['nivel']) === 'admin');

    if ($is_admin) {
        $html_plano = "<span class='badge bg-success shadow-sm px-3 py-2 mt-2'><i class='fas fa-infinity me-1'></i> Acesso Vitalício Administrador</span>";
    } else {
        // Verifica se tem plano/data contratada definida
        if (!empty($dados_cadastro['data_inicio']) && !empty($dados_cadastro['data_fim']) && $dados_cadastro['data_fim'] !== '0000-00-00') {
            $dt_ini = date('d/m/Y', strtotime($dados_cadastro['data_inicio']));
            $dt_fim = date('d/m/Y', strtotime($dados_cadastro['data_fim']));
            
            $hoje_obj = new DateTime(date('Y-m-d'));
            $fim_obj = new DateTime($dados_cadastro['data_fim']);
            $diferenca = $hoje_obj->diff($fim_obj);
            
            $dias_restantes = $diferenca->days;
            $invertido = $diferenca->invert; // 1 = passou da data
            
            // Design das Datas
            $html_plano = "
                <div class='d-flex align-items-center gap-3 mt-2 justify-content-center justify-content-md-start'>
                    <div class='bg-light px-3 py-2 rounded border shadow-sm text-center'>
                        <small class='text-muted fw-bold' style='font-size:9px;'><i class='fas fa-calendar-check me-1'></i> INÍCIO</small><br>
                        <span class='fw-bold text-dark' style='font-size:13px;'>{$dt_ini}</span>
                    </div>
                    <div class='bg-light px-3 py-2 rounded border shadow-sm text-center'>
                        <small class='text-muted fw-bold' style='font-size:9px;'><i class='fas fa-calendar-times me-1'></i> FIM DO PLANO</small><br>
                        <span class='fw-bold text-dark' style='font-size:13px;'>{$dt_fim}</span>
                    </div>
                </div>
            ";

            if ($invertido == 1 && $hoje_obj->format('Y-m-d') !== $fim_obj->format('Y-m-d')) {
                // PLANO EXPIRADO
                $html_alerta = "
                    <div class='alert alert-danger py-2 px-3 mb-0 shadow-sm border-0 d-flex align-items-center gap-3 text-start'>
                        <i class='fas fa-exclamation-triangle fa-2x'></i>
                        <div>
                            <span class='fw-bold' style='font-size:13px;'>Plano expirado há {$dias_restantes} dias!</span><br>
                            <span style='font-size:11px;'>Contate o Administrador para regularizar o acesso.</span>
                        </div>
                    </div>
                ";
            } else {
                // PLANO ATIVO (Se faltar <= 7 dias, fica amarelo)
                $cor_alerta = ($dias_restantes <= 7) ? 'bg-warning text-dark' : 'bg-primary text-white';
                $icone = ($dias_restantes <= 7) ? 'fa-exclamation-circle' : 'fa-check-circle';
                $msg_extra = ($dias_restantes <= 7) ? 'Para evitar interrupções, renove com antecedência.' : 'Seu plano está regularizado.';

                $html_alerta = "
                    <div class='card border-0 shadow-sm {$cor_alerta} text-start'>
                        <div class='card-body py-2 px-3 d-flex align-items-center gap-3'>
                            <i class='fas {$icone} fa-2x opacity-75'></i>
                            <div>
                                <span class='fw-bold' style='font-size:13px;'>Restam {$dias_restantes} dias para renovação.</span><br>
                                <span style='font-size:11px;' class='opacity-75'>{$msg_extra}</span>
                            </div>
                        </div>
                    </div>
                ";
            }
        } else {
            // Em TRIAL (Sem contrato de data)
            $dias_restantes_trial = $prazo_total_teste - $dias_desde_cadastro;
            if ($dias_restantes_trial < 0) $dias_restantes_trial = 0;
            
            $html_plano = "<span class='badge bg-secondary shadow-sm px-3 py-2 mt-2'><i class='fas fa-stopwatch me-1'></i> Período de Teste Gratuito</span>";
            $html_alerta = "
                <div class='card border-0 shadow-sm bg-info text-white text-start'>
                    <div class='card-body py-2 px-3 d-flex align-items-center gap-3'>
                        <i class='fas fa-info-circle fa-2x opacity-75'></i>
                        <div>
                            <span class='fw-bold' style='font-size:13px;'>Restam {$dias_restantes_trial} dias de teste.</span><br>
                            <span style='font-size:11px;' class='opacity-75'>Contate o Administrador para assinar um plano.</span>
                        </div>
                    </div>
                </div>
            ";
        }
    }

    /**
     * 4. LÓGICA DE CARREGAMENTO DOS MÓDULOS (APPS)
     */
    // (Lógica MANTIDA e intacta baseada na sua regra original)
    if (trim(strtolower($usuario_nivel)) === 'admin' || $está_no_periodo_teste) {
        $query_modulos = "SELECT * FROM modulos ORDER BY nome ASC";
        $stmt_exec = $pdo->query($query_modulos);
        $meus_modulos = $stmt_exec->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $query_restrita = "SELECT m.id, m.nome, m.slug, m.icone, m.descricao 
                           FROM modulos m 
                           INNER JOIN usuarios_modulos um ON m.id = um.modulo_id 
                           WHERE um.usuario_id = :uid 
                           ORDER BY m.nome ASC";
        $stmt_exec = $pdo->prepare($query_restrita);
        $stmt_exec->execute([':uid' => $usuario_id]);
        $meus_modulos = $stmt_exec->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    die("Erro interno ao processar permissões: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workspace - BDSoft Cloud</title>
    
    <!-- CSS: Bootstrap 5, FontAwesome 6 e Google Fonts -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-blue: #1a73e8;
            --dark-text: #202124;
            --muted-text: #5f6368;
            --bg-body: #f8f9fa;
        }

        body {
            background-color: var(--bg-body);
            font-family: 'Inter', sans-serif;
            color: var(--dark-text);
            margin: 0;
            padding: 0;
        }

        /* Topbar */
        .navbar-top {
            background-color: #ffffff;
            border-bottom: 1px solid #e0e0e0;
            padding: 1rem 0;
        }

        .navbar-brand {
            font-weight: 700;
            color: var(--primary-blue) !important;
            font-size: 1.5rem;
            letter-spacing: -0.5px;
        }

        /* Estilo dos Quadrinhos de Tecnologia (Apps) */
        .app-card {
            background: #ffffff;
            border: 1px solid #e0e6ed;
            border-radius: 28px;
            padding: 45px 25px;
            text-align: center;
            transition: all 0.3s cubic-bezier(.25,.8,.25,1);
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            align-items: center;
            height: 100%;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
            position: relative;
        }

        .app-card:hover {
            transform: translateY(-12px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            border-color: var(--primary-blue);
            color: var(--primary-blue);
        }

        .app-icon-box {
            font-size: 3.5rem;
            margin-bottom: 25px;
            width: 100px;
            height: 100px;
            line-height: 100px;
            background-color: #f8fafc;
            border-radius: 24px;
            transition: 0.3s;
        }

        .app-card:hover .app-icon-box {
            background-color: #e8f0fe;
        }

        .app-title {
            font-weight: 700;
            font-size: 1.25rem;
            margin-bottom: 10px;
            color: var(--dark-text);
        }

        .app-card:hover .app-title {
            color: var(--primary-blue);
        }

        .app-desc {
            font-size: 0.9rem;
            color: var(--muted-text);
            line-height: 1.5;
        }

        .btn-logout {
            font-weight: 600;
            border-radius: 50px;
            padding: 8px 25px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar-top shadow-sm">
    <div class="container d-flex justify-content-between align-items-center">
        <a class="navbar-brand" href="index.php">
            <i class="fas fa-th-large me-2"></i>BDSoft Workspace
        </a>
        <div class="d-flex align-items-center">
            <div class="text-end me-4 d-none d-md-block">
                <div class="small fw-bold text-dark"><?php echo htmlspecialchars($usuario_nome); ?></div>
                <div class="text-muted" style="font-size: 11px;">Último acesso: <?php echo $ultimo_acesso; ?></div>
            </div>
            <a href="logout.php" class="btn btn-outline-danger btn-sm btn-logout fw-bold">SAIR</a>
        </div>
    </div>
</nav>

<div class="container pb-5">
    
    <!-- NOVO CABEÇALHO RESPONSIVO: SAUDAÇÃO + PAINEL DE VIGÊNCIA -->
    <div class="row align-items-center mt-5 mb-5">
        
        <!-- Bloco da Esquerda (Saudação) -->
        <div class="col-lg-6 text-center text-lg-start mb-4 mb-lg-0">
            <h1 class="fw-bold mb-2" style="font-size: 2.5rem;">Olá, <?php echo htmlspecialchars($primeiro_nome); ?>!</h1>
            <p class="text-muted fs-5 mb-0">Selecione uma de suas tecnologias disponíveis para começar.</p>
        </div>
        
        <!-- Bloco da Direita (Painel de Assinatura) -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm" style="border-left: 5px solid var(--primary-blue) !important; border-radius: 12px; background: #fff;">
                <div class="card-body p-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div class="text-center text-md-start w-100 w-md-auto">
                        <h6 class="fw-bold text-dark mb-1">
                            <i class="fas fa-id-card text-primary me-1"></i> Acesso Contratado
                        </h6>
                        <?php echo $html_plano; ?>
                    </div>
                    <div class="w-100 w-md-auto">
                        <?php echo $html_alerta; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <div class="row g-4 justify-content-center">
        
        <?php if (empty($meus_modulos)): ?>
            <!-- Alerta: Nenhum módulo encontrado -->
            <div class="col-md-7">
                <div class="card p-5 border-0 shadow-sm rounded-4 text-center">
                    <i class="fas fa-user-lock fa-4x text-warning mb-4 opacity-50"></i>
                    <h4 class="fw-bold">Acesso em processamento</h4>
                    <p class="text-muted">Seu período de teste expirou e você não possui módulos contratados ativos.<br>Por favor, entre em contato com o suporte para liberar seu acesso.</p>
                    <div class="mt-3">
                        <a href="mailto:suporte@bdsoft.com.br" class="btn btn-primary rounded-pill px-4 fw-bold shadow">CONTATO SUPORTE</a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            
            <!-- LISTAGEM DE MÓDULOS (DINÂMICA) -->
            <?php foreach ($meus_modulos as $mod): ?>
                <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12">
                    <a href="<?php echo htmlspecialchars($mod['slug']); ?>" class="app-card">
                        <div class="app-icon-box text-primary">
                            <i class="fas <?php echo htmlspecialchars($mod['icone']); ?>"></i>
                        </div>
                        <div class="app-title"><?php echo htmlspecialchars($mod['nome']); ?></div>
                        <div class="app-desc"><?php echo htmlspecialchars($mod['descricao']); ?></div>
                    </a>
                </div>
            <?php endforeach; ?>

        <?php endif; ?>

        <!-- CARD EXCLUSIVO DE ADMINISTRAÇÃO -->
        <?php if (trim(strtolower($usuario_nivel)) === 'admin'): ?>
            <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12">
                <a href="admin_usuarios.php" class="app-card border-danger border-opacity-25 bg-light bg-opacity-50">
                    <div class="app-icon-box text-danger">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="app-title text-danger">Painel Admin</div>
                    <div class="app-desc">Gestão global de usuários, liberação de planos, auditoria de logs e cupons.</div>
                </a>
            </div>
        <?php endif; ?>

    </div>
</div>

<footer class="text-center mt-5 py-5 text-muted small border-top bg-white">
    <div class="container">
        <p class="mb-1 fw-bold">BDSoft Workspace &copy; <?php echo date('Y'); ?></p>
        <p class="mb-0">Tecnologia Cloud para Pecuária e Gestão de Projetos</p>
        <p class="mt-2" style="font-size: 10px;">Ambiente de Produção Seguro - IP: <?php echo $_SERVER['REMOTE_ADDR']; ?></p>
    </div>
</footer>

<!-- JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>