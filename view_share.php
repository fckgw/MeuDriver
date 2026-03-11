<?php
/**
 * BDSoft Workspace - VISUALIZAÇÃO DE COMPARTILHAMENTO (LOGADO)
 * Local: view_share.php
 */
session_start();
require_once 'config.php';

$token = $_GET['t'] ?? '';

if (empty($token)) {
    die("Link de compartilhamento inválido.");
}

// 1. Verificação de Segurança: O usuário está logado?
if (!isset($_SESSION['usuario_id'])) {
    // Salva a URL que ele tentou acessar para redirecionar de volta após o login
    $_SESSION['redirect_after_login'] = "view_share.php?t=" . $token;
    header("Location: login.php?msg=Para visualizar este item, faca login ou cadastre-se.");
    exit;
}

// 2. Buscar o compartilhamento
$stmt = $pdo->prepare("SELECT * FROM compartilhamentos WHERE token = ?");
$stmt->execute([$token]);
$share = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$share) {
    die("Este compartilhamento expirou ou foi removido pelo dono.");
}

// 3. Carregar os dados do item compartilhado
if ($share['arquivo_id']) {
    $stmtItem = $pdo->prepare("SELECT * FROM arquivos WHERE id = ?");
    $stmtItem->execute([$share['arquivo_id']]);
    $item = $stmtItem->fetch(PDO::FETCH_ASSOC);
    $is_folder = false;
    $path = "uploads/user_" . $share['usuario_dono_id'] . "/" . $item['nome_sistema'];
} else {
    $stmtItem = $pdo->prepare("SELECT * FROM pastas WHERE id = ?");
    $stmtItem->execute([$share['pasta_id']]);
    $item = $stmtItem->fetch(PDO::FETCH_ASSOC);
    $is_folder = true;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compartilhado - Workspace Drive</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">

<nav class="navbar navbar-light bg-white border-bottom shadow-sm px-4">
    <div class="brand-logo fw-bold text-primary"><i class="fas fa-cloud me-2"></i>Workspace Drive</div>
    <div class="small fw-bold">Logado como: <?php echo $_SESSION['usuario_nome']; ?></div>
</nav>

<div class="container py-5 text-center">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card border-0 shadow-lg p-5 rounded-4">
                <?php if ($is_folder): ?>
                    <i class="fas fa-folder-open fa-5x text-warning mb-4"></i>
                    <h3 class="fw-bold"><?php echo htmlspecialchars($item['nome']); ?></h3>
                    <p class="text-muted">Você recebeu acesso para <b>visualizar</b> esta pasta.</p>
                    <hr>
                    <a href="dashboard.php?pasta=<?php echo $item['id']; ?>" class="btn btn-primary rounded-pill w-100 fw-bold py-3">ABRIR NO MEU DRIVE</a>
                <?php else: ?>
                    <i class="fas fa-file-alt fa-5x text-primary mb-4"></i>
                    <h3 class="fw-bold"><?php echo htmlspecialchars($item['nome_original']); ?></h3>
                    <p class="text-muted">Arquivo compartilhado com você.</p>
                    <hr>
                    <div class="d-grid gap-2">
                        <a href="<?php echo $path; ?>" download class="btn btn-success rounded-pill fw-bold py-3"><i class="fas fa-download me-2"></i> BAIXAR ARQUIVO</a>
                        <a href="dashboard.php" class="btn btn-light rounded-pill fw-bold">VOLTAR AO MEU DASHBOARD</a>
                    </div>
                <?php endif; ?>
            </div>
            <p class="mt-4 text-muted small">BDSoft Workspace Drive &copy; 2024</p>
        </div>
    </div>
</div>

</body>
</html>