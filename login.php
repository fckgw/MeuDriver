<?php
/**
 * BDSoft Workspace - TELA DE LOGIN (SISTEMA CENTRAL)
 * Localização: public_html/login.php
 * Atualização: Redirecionamento Inteligente, Validação de Vigência e Troca de Senha Obrigatória.
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'config.php';

// --- 1. VERIFICAÇÃO DE SESSÃO ATIVA ---
// Se o usuário já estiver logado e NÃO tiver pendência de troca de senha, redireciona.
if (isset($_SESSION['usuario_id']) && !isset($_SESSION['troca_obrigatoria'])) {
    if (isset($_SESSION['redirect_after_login'])) {
        $destino = $_SESSION['redirect_after_login'];
        unset($_SESSION['redirect_after_login']);
        header("Location: " . $destino);
    } else {
        header("Location: index.php");
    }
    exit;
}

$mensagem_erro = "";

// Captura mensagens automáticas (ex: vinda de links compartilhados ou expiração)
if (isset($_GET['msg'])) {
    $mensagem_erro = htmlspecialchars($_GET['msg']);
}

// --- 2. PROCESSAMENTO DO LOGIN (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario_input = trim($_POST['usuario']);
    $senha_input   = trim($_POST['senha']);

    if (!empty($usuario_input) && !empty($senha_input)) {
        try {
            // Busca o usuário pelo e-mail ou login
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE usuario = ? LIMIT 1");
            $stmt->execute([$usuario_input]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Verifica se o usuário existe e se a senha é válida
            if ($user && password_verify($senha_input, $user['senha'])) {
                
                // A. Verificar bloqueio administrativo
                if ($user['status'] === 'suspenso') {
                    $mensagem_erro = "Sua conta está suspensa. Entre em contato com suporte@bdsoft.com.br";
                } else {
                    
                    // B. VALIDAÇÃO DE VIGÊNCIA (Admin, Assinante ou Trial)
                    $acesso_liberado = false;
                    $hoje_obj = new DateTime();
                    $hoje_str = $hoje_obj->format('Y-m-d');

                    if ($user['nivel'] === 'admin') {
                        $acesso_liberado = true; // Administradores não expiram
                    } else {
                        // Regra para Membros com plano definido
                        if (!empty($user['data_fim']) && $user['data_fim'] !== '0000-00-00') {
                            if ($user['data_fim'] >= $hoje_str) {
                                $acesso_liberado = true; 
                            } else {
                                $mensagem_erro = "Sua assinatura expirou em " . date('d/m/Y', strtotime($user['data_fim'])) . ". Contate o administrador.";
                            }
                        } else {
                            // Regra de fallback: Período de Teste (Trial de 14 dias + bônus)
                            $data_criacao = new DateTime($user['data_criacao']);
                            $dias_ativo = $hoje_obj->diff($data_criacao)->days;
                            $bonus = isset($user['dias_bonus_cupom']) ? (int)$user['dias_bonus_cupom'] : 0;

                            if ($dias_ativo <= (14 + $bonus)) {
                                $acesso_liberado = true;
                            } else {
                                $mensagem_erro = "Seu período de teste expirou. Adquira um plano para continuar acessando.";
                            }
                        }
                    }

                    // C. EFETIVAÇÃO DO LOGIN
                    if ($acesso_liberado) {
                        
                        // Configuração das Variáveis de Sessão
                        $_SESSION['usuario_id']      = $user['id'];
                        $_SESSION['usuario_nome']    = $user['nome'];
                        $_SESSION['usuario_usuario'] = $user['usuario'];
                        $_SESSION['usuario_nivel']   = $user['nivel'];
                        $_SESSION['ultima_atividade'] = time();
                        
                        // Informação para exibição no portal
                        $_SESSION['ultimo_acesso_info'] = ($user['ultimo_acesso']) 
                            ? date('d/m/Y H:i', strtotime($user['ultimo_acesso'])) 
                            : "Primeiro Acesso";

                        // --- VERIFICAÇÃO DE TROCA DE SENHA OBRIGATÓRIA ---
                        // Se o Admin criou o usuário com senha temporária (trocar_senha = 1)
                        if (isset($user['trocar_senha']) && (int)$user['trocar_senha'] === 1) {
                            $_SESSION['troca_obrigatoria'] = true;
                            header("Location: mudar_senha.php");
                            exit;
                        }

                        // Atualiza data do último acesso e registra LOG
                        $pdo->prepare("UPDATE usuarios SET ultimo_acesso = NOW() WHERE id = ?")->execute([$user['id']]);
                        
                        if (function_exists('registrarLog')) {
                            registrarLog($pdo, $user['id'], "Login", "Acesso realizado com sucesso.");
                        }

                        // --- REDIRECIONAMENTO POS-LOGIN ---
                        // Se veio de um link compartilhado, volta para ele. Senão, vai para o index.
                        if (isset($_SESSION['redirect_after_login'])) {
                            $destino = $_SESSION['redirect_after_login'];
                            unset($_SESSION['redirect_after_login']);
                            header("Location: " . $destino);
                        } else {
                            header("Location: index.php");
                        }
                        exit;
                    }
                }
            } else {
                $mensagem_erro = "Usuário ou senha incorretos.";
            }
        } catch (PDOException $e) {
            $mensagem_erro = "Erro de comunicação com o servidor.";
        }
    } else {
        $mensagem_erro = "Por favor, preencha todos os campos.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workspace - Acesso</title>
    
    <!-- Bibliotecas: Bootstrap 5 e FontAwesome 6 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body { background-color: #f8f9fa; height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Segoe UI', system-ui, sans-serif; margin: 0; }
        .login-card { width: 100%; max-width: 400px; padding: 40px; border: none; border-radius: 24px; background: #ffffff; box-shadow: 0 20px 40px rgba(0,0,0,0.08); }
        
        .brand-icon { color: #1a73e8; font-size: 3.5rem; margin-bottom: 1rem; }
        .brand-title { font-weight: 800; color: #202124; letter-spacing: -1px; }
        
        .form-label { font-size: 0.75rem; font-weight: 700; color: #5f6368; letter-spacing: 0.5px; }
        .form-control { padding: 12px 15px; border-radius: 12px; border: 1px solid #dadce0; font-size: 0.95rem; }
        .form-control:focus { border-color: #1a73e8; box-shadow: 0 0 0 4px rgba(26,115,232,0.1); }
        
        .input-group-text { background: #fff; border-left: none; cursor: pointer; color: #5f6368; border-radius: 0 12px 12px 0; }
        .input-pass { border-right: none; }
        
        .btn-login { padding: 14px; font-weight: 700; border-radius: 12px; background-color: #1a73e8; border: none; transition: 0.3s; margin-top: 10px; }
        .btn-login:hover { background-color: #1557b0; transform: translateY(-1px); }
        
        .alert-info-custom { background-color: #e8f0fe; color: #1a73e8; border: none; border-radius: 12px; font-size: 0.85rem; font-weight: 600; }
    </style>
</head>
<body>

<div class="login-card">
    <div class="text-center mb-4">
        <!-- ÍCONE DE NUVEM (Workspace Drive) -->
        <div class="brand-icon"><i class="fas fa-cloud"></i></div>
        <h3 class="brand-title">Workspace <span class="text-primary">Cloud</span></h3>
        <p class="text-muted small">Tecnologia Cloud e Gestão de Ativos</p>
    </div>

    <!-- EXIBIÇÃO DE MENSAGENS DE ERRO OU ALERTAS -->
    <?php if(!empty($mensagem_erro)): ?>
        <div class="alert alert-info-custom py-3 px-3 text-center mb-4 shadow-sm">
            <i class="fas fa-info-circle me-2"></i> <?php echo $mensagem_erro; ?>
        </div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
        <div class="mb-3">
            <label class="form-label text-uppercase">E-mail ou Usuário</label>
            <input type="text" name="usuario" class="form-control" placeholder="nome@exemplo.com" required autofocus>
        </div>
        
        <div class="mb-4">
            <label class="form-label text-uppercase">Sua Senha</label>
            <div class="input-group">
                <input type="password" name="senha" id="inputSenha" class="form-control input-pass" placeholder="••••••••" required>
                <span class="input-group-text" onclick="alternarVisibilidade()">
                    <i class="fas fa-eye" id="iconeOlho"></i>
                </span>
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-login w-100 shadow-sm text-uppercase">Entrar no Workspace</button>
    </form>

    <div class="mt-4 text-center">
        <p class="small text-muted">Novo por aqui? <a href="registro.php" class="text-decoration-none fw-bold text-primary">Crie sua conta</a></p>
    </div>
</div>

<script>
    /**
     * Alterna a visibilidade da senha entre texto e asteriscos
     */
    function alternarVisibilidade() {
        const campo = document.getElementById('inputSenha');
        const icone = document.getElementById('iconeOlho');
        if (campo.type === 'password') {
            campo.type = 'text';
            icone.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            campo.type = 'password';
            icone.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }
</script>

</body>
</html>