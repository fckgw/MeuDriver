<?php
/**
 * BDSoft Workspace - CADASTRO DE NOVOS MÓDULOS (APPS)
 */
session_start();
require_once 'config.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_nivel'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// 1. Ação: Novo Módulo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_novo'])) {
    $stmt = $pdo->prepare("INSERT INTO modulos (nome, slug, icone, descricao) VALUES (?, ?, ?, ?)");
    $stmt->execute([$_POST['nome'], $_POST['slug'], $_POST['icone'], $_POST['descricao']]);
    header("Location: admin_modulos.php?success=1"); 
    exit;
}

// 2. Ação: Excluir Módulo
if (isset($_GET['del'])) {
    $stmt = $pdo->prepare("DELETE FROM modulos WHERE id = ?");
    $stmt->execute([(int)$_GET['del']]);
    header("Location: admin_modulos.php"); 
    exit;
}

$modulos = $pdo->query("SELECT * FROM modulos ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Configurar Apps - Workspace Drive</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f8f9fa; font-family: 'Segoe UI', sans-serif; }
        .card-app { border: none; border-radius: 15px; shadow: 0 4px 12px rgba(0,0,0,0.05); }
    </style>
</head>
<body class="p-4">
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="fas fa-cubes text-primary me-2"></i> Configurar Apps do Portal</h3>
        <div>
            <a href="admin_permissoes.php" class="btn btn-warning rounded-pill px-3 fw-bold shadow-sm">DAR PERMISSÕES</a>
            <a href="portal.php" class="btn btn-secondary rounded-pill px-3 ms-2">Voltar</a>
        </div>
    </div>
    
    <div class="card card-app p-4 shadow-sm mb-4">
        <h6 class="fw-bold text-muted mb-3">CADASTRAR NOVO APLICATIVO (SISTEMA)</h6>
        <form method="POST" class="row g-3">
            <div class="col-md-3">
                <label class="small fw-bold">Nome Exibição</label>
                <input type="text" name="nome" class="form-control" placeholder="Ex: agroCampo" required>
            </div>
            <div class="col-md-3">
                <label class="small fw-bold">Link (Pasta/Arquivo)</label>
                <input type="text" name="slug" class="form-control" placeholder="Ex: agro/index.php" required>
            </div>
            <div class="col-md-2">
                <label class="small fw-bold">Ícone FontAwesome</label>
                <input type="text" name="icone" class="form-control" placeholder="Ex: fa-leaf">
            </div>
            <div class="col-md-4">
                <label class="small fw-bold">Descrição Curta</label>
                <div class="input-group">
                    <input type="text" name="descricao" class="form-control" placeholder="Breve resumo do app">
                    <button name="btn_novo" class="btn btn-primary fw-bold px-4">ADICIONAR</button>
                </div>
            </div>
        </form>
    </div>

    <div class="card card-app p-0 shadow-sm overflow-hidden">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-4">App</th>
                    <th>Link Interno</th>
                    <th>Descrição</th>
                    <th class="text-center">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($modulos as $m): ?>
                <tr class="align-middle">
                    <td class="ps-4">
                        <div class="d-flex align-items-center">
                            <div class="bg-light p-2 rounded me-3 text-primary"><i class="fas <?php echo $m['icone']; ?> fa-lg"></i></div>
                            <span class="fw-bold"><?php echo $m['nome']; ?></span>
                        </div>
                    </td>
                    <td><code><?php echo $m['slug']; ?></code></td>
                    <td class="small text-muted"><?php echo $m['descricao']; ?></td>
                    <td class="text-center">
                        <a href="?del=<?php echo $m['id']; ?>" class="btn btn-sm btn-outline-danger border-0" onclick="return confirm('Excluir este módulo do portal?')"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>