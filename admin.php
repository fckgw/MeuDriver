<?php
session_start();
include 'config.php';

// Só permite se for admin
$stmt = $pdo->prepare("SELECT nivel FROM usuarios WHERE id = ?");
$stmt->execute([$_SESSION['usuario_id']]);
if($stmt->fetchColumn() !== 'admin') die("Acesso Negado");

// Lógica para criar novo usuário ou alterar quota (simplificada)
if(isset($_POST['novo_user'])) {
    $user = $_POST['user'];
    $pass = password_hash($_POST['pass'], PASSWORD_DEFAULT);
    $quota = $_POST['quota_mb'] * 1024 * 1024; // Converte MB para Bytes
    
    $ins = $pdo->prepare("INSERT INTO usuarios (nome, usuario, senha, quota_limite) VALUES (?, ?, ?, ?)");
    $ins->execute([$_POST['nome'], $user, $pass, $quota]);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Painel Admin - Gerenciar Usuários</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light p-5">
    <div class="container bg-white p-4 shadow-sm rounded">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Gerenciar Usuários</h2>
            <a href="dashboard.php" class="btn btn-secondary">Voltar ao Drive</a>
        </div>

        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Usuário</th>
                    <th>Uso / Limite</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $users = $pdo->query("SELECT *, (SELECT SUM(tamanho) FROM arquivos WHERE usuario_id = usuarios.id) as usado FROM usuarios")->fetchAll();
                foreach($users as $u):
                    $pct = ($u['usado'] / $u['quota_limite']) * 100;
                ?>
                <tr>
                    <td><?php echo $u['nome']; ?></td>
                    <td><?php echo $u['usuario']; ?></td>
                    <td>
                        <div class="progress" style="height: 10px; width: 150px;">
                            <div class="progress-bar" style="width: <?php echo $pct; ?>%"></div>
                        </div>
                        <small><?php echo round($u['usado']/1024/1024, 2); ?>MB / <?php echo $u['quota_limite']/1024/1024; ?>MB</small>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-primary">Editar</button>
                        <button class="btn btn-sm btn-danger">Excluir</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <hr>
        <h4>Criar Novo Usuário</h4>
        <form method="POST" class="row g-3">
            <input type="hidden" name="novo_user" value="1">
            <div class="col-md-3"><input type="text" name="nome" class="form-control" placeholder="Nome Real" required></div>
            <div class="col-md-3"><input type="text" name="user" class="form-control" placeholder="Username" required></div>
            <div class="col-md-2"><input type="password" name="pass" class="form-control" placeholder="Senha" required></div>
            <div class="col-md-2"><input type="number" name="quota_mb" class="form-control" placeholder="Quota em MB" required></div>
            <div class="col-md-2"><button type="submit" class="btn btn-success w-100">Criar Usuário</button></div>
        </form>
    </div>
</body>
</html>