<?php
/**
 * SISTEMA DE DRIVE - TELA DE LOGIN OFICIAL
 * Local: driverbds.tecnologia.ws
 */
session_start();
require_once 'config.php';

// Redireciona se já estiver logado
if (isset($_SESSION['usuario_id'])) {
    header("Location: dashboard.php");
    exit;
}

$mensagem_erro = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario_input = trim($_POST['usuario']);
    $senha_input = trim($_POST['senha']);

    if (!empty($usuario_input) && !empty($senha_input)) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE usuario = ? LIMIT 1");
            $stmt->execute([$usuario_input]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($senha_input, $user['senha'])) {
                
                // --- REGRA DE 14 DIAS ---
                $data_criacao = new DateTime($user['data_criacao']);
                $hoje = new DateTime();
                $dias_ativo = $hoje->diff($data_criacao)->days;

                // Admins não são bloqueados pelos 14 dias
                if ($dias_ativo > 14 && $user['nivel'] !== 'admin') {
                    $pdo->prepare("UPDATE usuarios SET status = 'suspenso' WHERE id = ?")->execute([$user['id']]);
                    $mensagem_erro = "Seu período de teste de 14 dias expirou.";
                } elseif ($user['status'] === 'suspenso') {
                    $mensagem_erro = "Sua conta está suspensa. Contate o administrador.";
                } else {
                    // LOGIN SUCESSO
                    $_SESSION['usuario_id'] = $user['id'];
                    $_SESSION['usuario_nome'] = $user['nome'];
                    $_SESSION['usuario_nivel'] = $user['nivel'];
                    $_SESSION['ultimo_acesso_info'] = ($user['ultimo_acesso']) ? date('d/m/Y H:i', strtotime($user['ultimo_acesso'])) : "Primeiro Acesso";

                    // Atualiza data do último acesso
                    $pdo->prepare("UPDATE usuarios SET ultimo_acesso = NOW() WHERE id = ?")->execute([$user['id']]);

                    // Registrar Log
                    if (function_exists('registrarLog')) {
                        registrarLog($pdo, $user['id'], "Login", "Acesso ao painel driverbds.");
                    }

                    header("Location: dashboard.php");
                    exit;
                }
            } else {
                $mensagem_erro = "Usuário ou senha incorretos.";
            }
        } catch (PDOException $e) {
            $mensagem_erro = "Erro técnico: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Drive BDS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #ffffff; height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Segoe UI', sans-serif; }
        .login-card { width: 100%; max-width: 400px; padding: 40px; border-radius: 20px; border: 1px solid #f0f0f0; box-shadow: 0 15px 35px rgba(0,0,0,0.05); }
        .input-group-text { background: #fff; cursor: pointer; border-left: none; color: #6c757d; }
        .form-control { border-right: none; padding: 12px; }
        .form-control:focus { box-shadow: none; border-color: #dee2e6; }
        .btn-primary { padding: 12px; font-weight: bold; border-radius: 10px; }
    </style>
</head>
<body>

<div class="login-card">
    <div class="text-center mb-4">
        <i class="fas fa-cloud text-primary fa-4x mb-3"></i>
        <h3 class="fw-bold text-dark">Drive BDS</h3>
        <p class="text-muted small">Armazenamento Seguro de Dados</p>
    </div>

    <?php if($mensagem_erro): ?>
        <div class="alert alert-danger text-center py-2 small"><?php echo $mensagem_erro; ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label small fw-bold text-muted">USUÁRIO OU E-MAIL</label>
            <input type="text" name="usuario" class="form-control" style="border-right: 1px solid #dee2e6;" placeholder="Administrator" required autofocus>
        </div>
        
        <div class="mb-2">
            <label class="form-label small fw-bold text-muted">SENHA</label>
            <div class="input-group">
                <input type="password" name="senha" id="inputSenha" class="form-control" placeholder="••••••••" required>
                <span class="input-group-text" onclick="togglePass()">
                    <i class="fas fa-eye" id="iconEye"></i>
                </span>
            </div>
        </div>

        <div class="text-end mb-4">
            <a href="esqueceu_senha.php" class="text-decoration-none small text-primary">Esqueceu a senha?</a>
        </div>

        <button type="submit" class="btn btn-primary w-100 shadow-sm">ENTRAR NO PAINEL</button>
    </form>

    <div class="text-center mt-4">
        <p class="small text-muted">Não tem uma conta? <a href="registro.php" class="text-decoration-none fw-bold">Cadastre-se</a></p>
    </div>
</div>

<script>
    function togglePass() {
        const senha = document.getElementById('inputSenha');
        const icone = document.getElementById('iconEye');
        if (senha.type === 'password') {
            senha.type = 'text';
            icone.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            senha.type = 'password';
            icone.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }
</script>
</body>
</html>