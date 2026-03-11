<?php
/**
 * BDSoft Workspace - TELA DE LOGIN
 * Localização: public_html/login.php
 * Atualização: Redirecionamento Inteligente para itens compartilhados e Marca "Workspace Drive"
 */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'config.php';

// Se o usuário já estiver logado e NÃO precisar trocar senha, vai para o portal
if (isset($_SESSION['usuario_id']) && !isset($_SESSION['troca_obrigatoria'])) {
    // Verifica se há um destino pendente (ex: link compartilhado)
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

// Capturar mensagem via GET (ex: vinda do view_share.php)
if (isset($_GET['msg'])) {
    $mensagem_erro = htmlspecialchars($_GET['msg']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario_input = trim($_POST['usuario']);
    $senha_input   = trim($_POST['senha']);

    if (!empty($usuario_input) && !empty($senha_input)) {
        try {
            // Busca o usuário pelo e-mail/login
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE usuario = ? LIMIT 1");
            $stmt->execute([$usuario_input]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($senha_input, $user['senha'])) {
                
                // 1. Verificar se a conta está suspensa pelo Admin
                if ($user['status'] === 'suspenso') {
                    $mensagem_erro = "Sua conta está suspensa. Entre em contato com suporte@bdsoft.com.br";
                } else {
                    
                    // 2. NOVA REGRA: Verificar Validade do Acesso (Admin, Membro Assinante ou Teste)
                    $acesso_liberado = false;
                    $hoje_obj = new DateTime();
                    $hoje_str = $hoje_obj->format('Y-m-d');

                    if ($user['nivel'] === 'admin') {
                        $acesso_liberado = true; // Admin tem acesso vitalício
                    } else {
                        // Verifica se o membro tem um Pacote/Assinatura definido no Admin
                        if (!empty($user['data_fim']) && $user['data_fim'] !== '0000-00-00') {
                            if ($user['data_fim'] >= $hoje_str) {
                                $acesso_liberado = true; // Assinatura no prazo!
                            } else {
                                $mensagem_erro = "Sua assinatura expirou em " . date('d/m/Y', strtotime($user['data_fim'])) . ". Contate o administrador.";
                            }
                        } else {
                            // Se NÃO tem assinatura preenchida, cai na regra do Período de Teste
                            $data_criacao = new DateTime($user['data_criacao']);
                            $dias_ativo = $hoje_obj->diff($data_criacao)->days;
                            $bonus = isset($user['dias_bonus_cupom']) ? (int)$user['dias_bonus_cupom'] : 0;

                            if ($dias_ativo <= (14 + $bonus)) {
                                $acesso_liberado = true; // Ainda dentro do período de teste
                            } else {
                                $mensagem_erro = "Seu período de teste expirou. Adquira um plano ou contate o administrador.";
                            }
                        }
                    }

                    // 3. Se passou pela validação acima, faz o Login
                    if ($acesso_liberado) {
                        
                        // --- CONFIGURAÇÃO DA SESSÃO ---
                        $_SESSION['usuario_id']      = $user['id'];
                        $_SESSION['usuario_nome']    = $user['nome'];
                        $_SESSION['usuario_usuario'] = $user['usuario'];
                        $_SESSION['usuario_nivel']   = $user['nivel'];
                        
                        // Informação do último acesso
                        $_SESSION['ultimo_acesso_info'] = ($user['ultimo_acesso']) 
                            ? date('d/m/Y H:i', strtotime($user['ultimo_acesso'])) 
                            : "Primeiro Acesso";

                        // --- VERIFICAÇÃO DE TROCA DE SENHA OBRIGATÓRIA ---
                        if (isset($user['trocar_senha']) && (int)$user['trocar_senha'] === 1) {
                            $_SESSION['troca_obrigatoria'] = true;
                            header("Location: mudar_senha.php");
                            exit;
                        }

                        // Login normal: Atualiza banco e log
                        $pdo->prepare("UPDATE usuarios SET ultimo_acesso = NOW() WHERE id = ?")->execute([$user['id']]);
                        if(function_exists('registrarLog')) {
                            registrarLog($pdo, $user['id'], "Login", "Usuário acessou o Workspace.");
                        }

                        // --- REDIRECIONAMENTO INTELIGENTE (NOVO) ---
                        if (isset($_SESSION['redirect_after_login'])) {
                            $destino = $_SESSION['redirect_after_login'];
                            unset($_SESSION['redirect_after_login']); // Limpa a variável
                            header("Location: " . $destino);
                        } else {
                            header("Location: index.php");
                        }
                        exit;
                    }
                }
            } else {
                $mensagem_erro = "E-mail ou senha incorretos.";
            }
        } catch (PDOException $e) {
            $mensagem_erro = "Erro de conexão com o banco de dados.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workspace Drive - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #ffffff; height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Segoe UI', system-ui, sans-serif; margin: 0; }
        .login-card { width: 100%; max-width: 400px; padding: 40px; border: 1px solid #f0f0f0; border-radius: 20px; box-shadow: 0 15px 35px rgba(0,0,0,0.05); }
        .input-group-text { background: #fff; cursor: pointer; border-left: none; color: #6c757d; }
        .form-control { border-right: none; padding: 12px; }
        .form-control:focus { box-shadow: none; border-color: #dee2e6; }
        .btn-primary { padding: 12px; font-weight: bold; border-radius: 12px; background-color: #1a73e8; border: none; transition: 0.3s; }
        .btn-primary:hover { background-color: #1557b0; }
        .brand-icon { color: #1a73e8; }
    </style>
</head>
<body>

<div class="login-card">
    <div class="text-center mb-4">
        <i class="fas fa-th-large brand-icon fa-4x mb-3"></i>
        <h3 class="fw-bold">Workspace <span class="text-dark">Drive</span></h3>
        <p class="text-muted small">Gerenciador de Tecnologias Cloud</p>
    </div>

    <?php if(!empty($mensagem_erro)): ?>
        <div class="alert alert-info py-2 small text-center fw-bold shadow-sm"><?php echo $mensagem_erro; ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
        <div class="mb-3">
            <label class="form-label small fw-bold text-muted text-uppercase">Usuário ou E-mail</label>
            <input type="text" name="usuario" class="form-control" style="border-right: 1px solid #dee2e6;" placeholder="Digite seu usuário" required autofocus>
        </div>
        
        <div class="mb-4">
            <label class="form-label small fw-bold text-muted text-uppercase">Senha</label>
            <div class="input-group">
                <input type="password" name="senha" id="inputSenha" class="form-control" placeholder="••••••••" required>
                <span class="input-group-text" onclick="alternarVisibilidade()">
                    <i class="fas fa-eye" id="iconeOlho"></i>
                </span>
            </div>
        </div>

        <button type="submit" class="btn btn-primary w-100 shadow-sm">ENTRAR NO SISTEMA</button>
    </form>

    <div class="mt-4 text-center">
        <p class="small text-muted">Ainda não tem conta? <a href="registro.php" class="text-decoration-none fw-bold text-primary">Cadastre-se</a></p>
    </div>
</div>

<script>
    function alternarVisibilidade() {
        const campo = document.getElementById('inputSenha');
        const icone = document.getElementById('iconeOlho');
        if (campo.type === 'password') {
            campo.type = 'text';
            icone.classList.remove('fa-eye');
            icone.classList.add('fa-eye-slash');
        } else {
            campo.type = 'password';
            icone.classList.remove('fa-eye-slash');
            icone.classList.add('fa-eye');
        }
    }
</script>

</body>
</html>