<?php
require_once 'config.php';

$mensagem = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    $stmt = $pdo->prepare("SELECT id, nome FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // Em um sistema real, aqui geraríamos um token. 
        // Para simplicidade, enviaremos o link direto para resetar.
        $link = "https://driverbds.tecnologia.ws/resetar_senha.php?id=" . $user['id'];
        
        $assunto = "Recuperacao de Senha - CloudDrive";
        $corpo = "Ola " . $user['nome'] . ",\n\nClique no link abaixo para cadastrar uma nova senha:\n" . $link;
        
        // IMPORTANTE PARA LOCAWEB: O "From" deve ser um e-mail do seu domínio
        $headers = "From: no-reply@tecnologia.ws" . "\r\n" .
                   "Reply-To: souzafelipe@bdsoft.com.br" . "\r\n" .
                   "X-Mailer: PHP/" . phpversion();

        if (mail($email, $assunto, $corpo, $headers)) {
            $mensagem = "Um link de recuperação foi enviado para seu e-mail.";
        } else {
            $mensagem = "Erro ao enviar e-mail. Verifique se o servidor de e-mail está ativo.";
        }
    } else {
        $mensagem = "E-mail não encontrado em nossa base.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Recuperar Senha</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center" style="height:100vh">
    <div class="card mx-auto p-4 shadow" style="width:400px; border-radius:15px;">
        <h4 class="text-center fw-bold">Recuperar Senha</h4>
        <?php if($mensagem) echo "<div class='alert alert-info small'>$mensagem</div>"; ?>
        <form method="POST">
            <div class="mb-3">
                <label class="small fw-bold">SEU E-MAIL CADASTRADO</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <button class="btn btn-primary w-100 fw-bold">ENVIAR LINK</button>
            <a href="login.php" class="d-block text-center mt-3 small text-decoration-none">Voltar ao Login</a>
        </form>
    </div>
</body>
</html>