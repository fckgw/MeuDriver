<?php
require_once 'config.php';

if (!isset($_GET['id'])) die("Acesso inválido.");
$id = (int)$_GET['id'];
$mensagem = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nova_senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
    if ($stmt->execute([$nova_senha, $id])) {
        echo "<script>alert('Senha alterada!'); window.location.href='login.php';</script>";
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Nova Senha</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center" style="height:100vh">
    <div class="card mx-auto p-4 shadow" style="width:400px; border-radius:15px;">
        <h4 class="text-center fw-bold">Nova Senha</h4>
        <form method="POST">
            <div class="mb-3">
                <label class="small fw-bold">DIGITE A NOVA SENHA</label>
                <input type="password" name="senha" class="form-control" required minlength="6">
            </div>
            <button class="btn btn-success w-100 fw-bold">ALTERAR SENHA</button>
        </form>
    </div>
</body>
</html>