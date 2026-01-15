<?php
/**
 * SISTEMA DE DRIVE - TELA DE CADASTRO PÚBLICO
 * Local: driverbds.tecnologia.ws
 */

require_once 'config.php';

$mensagem = "";
$sucesso = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome    = trim($_POST['nome']);
    $cpf     = trim($_POST['cpf']);
    $usuario = trim($_POST['usuario']); // E-mail usado no login
    $senha   = $_POST['senha'];
    $confirma = $_POST['confirma_senha'];

    if ($senha !== $confirma) {
        $mensagem = "As senhas informadas não coincidem.";
    } elseif (strlen($senha) < 6) {
        $mensagem = "A senha deve ter no mínimo 6 caracteres.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = ? OR cpf = ?");
            $stmt->execute([$usuario, $cpf]);
            
            if ($stmt->rowCount() > 0) {
                $mensagem = "E-mail ou CPF já cadastrado no sistema.";
            } else {
                $hash = password_hash($senha, PASSWORD_DEFAULT);
                $quota_1gb = 1073741824; // 1GB em Bytes

                $sql = "INSERT INTO usuarios (nome, cpf, usuario, senha, quota_limite, data_criacao, nivel, status) 
                        VALUES (?, ?, ?, ?, ?, NOW(), 'usuario', 'ativo')";
                
                $pdo->prepare($sql)->execute([$nome, $cpf, $usuario, $hash, $quota_1gb]);
                $id_novo = $pdo->lastInsertId();

                // --- DISPARO DE E-MAIL DE BOAS-VINDAS ---
                $assunto = "Bem-vindo ao DriveBDS";
                $corpo = "
                <html>
                <body style='font-family: sans-serif; color: #333;'>
                    <h2 style='color: #1a73e8;'>Olá, $nome!</h2>
                    <p>Sua conta foi criada com sucesso no <strong>DriveBDS</strong>.</p>
                    <p><strong>Seu Usuário:</strong> $usuario</p>
                    <p><strong>Seu CPF:</strong> $cpf</p>
                    <hr>
                    <p style='color: #d93025;'><strong>Atenção:</strong> Você tem 14 dias de teste grátis com 1GB de espaço.</p>
                    <p>Acesse agora: <a href='https://driverbds.tecnologia.ws/'>https://driverbds.tecnologia.ws/</a></p>
                </body>
                </html>";

                $headers = "MIME-Version: 1.0" . "\r\n";
                $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                $headers .= "From: suporte@driverbds.tecnologia.ws" . "\r\n";

                @mail($usuario, $assunto, $corpo, $headers);

                if (function_exists('registrarLog')) {
                    registrarLog($pdo, $id_novo, "Registro", "Usuário realizou auto-cadastro.");
                }

                $sucesso = true;
            }
        } catch (Exception $e) {
            $mensagem = "Erro técnico ao processar registro.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Conta - DriveBDS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #ffffff; height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Segoe UI', sans-serif; }
        .card-reg { width: 100%; max-width: 480px; padding: 40px; border-radius: 20px; border: 1px solid #eee; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
    </style>
</head>
<body>

<div class="card-reg">
    <div class="text-center mb-4">
        <h3 class="fw-bold">Cadastre-se</h3>
        <p class="text-muted small">Ganhe 14 dias de teste com 1GB de espaço</p>
    </div>

    <?php if($sucesso): ?>
        <div class="alert alert-success text-center">
            <strong>Sucesso!</strong> Sua conta foi criada.<br>
            <a href="login.php" class="btn btn-success btn-sm mt-2 rounded-pill px-4">Ir para Login</a>
        </div>
    <?php else: ?>
        
        <?php if($mensagem) echo "<div class='alert alert-danger small text-center'>$mensagem</div>"; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="small fw-bold">NOME COMPLETO</label>
                <input type="text" name="nome" class="form-control" required>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="small fw-bold">CPF</label>
                    <input type="text" name="cpf" id="cpf_reg" class="form-control" placeholder="000.000.000-00" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="small fw-bold">E-MAIL (LOGIN)</label>
                    <input type="email" name="usuario" class="form-control" required>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="small fw-bold">SENHA</label>
                    <input type="password" name="senha" class="form-control" required>
                </div>
                <div class="col-md-6 mb-4">
                    <label class="small fw-bold">CONFIRMAR</label>
                    <input type="password" name="confirma_senha" class="form-control" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2 fw-bold rounded-pill shadow-sm">CRIAR MINHA CONTA</button>
            <div class="text-center mt-3">
                <a href="login.php" class="small text-decoration-none text-muted">Já sou cadastrado. <span class="text-primary fw-bold">Fazer Login</span></a>
            </div>
        </form>
    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
<script> $(document).ready(function(){ $('#cpf_reg').mask('000.000.000-00'); }); </script>
</body>
</html>