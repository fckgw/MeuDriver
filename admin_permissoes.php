<?php
/**
 * BDSoft Workspace - GESTÃO DE PERMISSÕES DE MÓDULOS
 * Localização: public_html/admin_permissoes.php
 */
session_start();
require_once 'config.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_nivel'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$mensagem = "";

// 1. Processar Salvamento de Permissões
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_salvar_permissoes'])) {
    $uid = (int)$_POST['user_id'];
    $modulos_selecionados = $_POST['modulos'] ?? [];

    try {
        $pdo->beginTransaction();
        
        // Limpa permissões atuais do usuário
        $stmtDel = $pdo->prepare("DELETE FROM usuarios_modulos WHERE usuario_id = ?");
        $stmtDel->execute([$uid]);

        // Insere as novas permissões
        if (!empty($modulos_selecionados)) {
            $stmtIns = $pdo->prepare("INSERT INTO usuarios_modulos (usuario_id, modulo_id) VALUES (?, ?)");
            foreach ($modulos_selecionados as $mid) {
                $stmtIns->execute([$uid, (int)$mid]);
            }
        }

        $pdo->commit();
        $mensagem = "<div class='alert alert-success shadow-sm'>Permissões atualizadas com sucesso!</div>";
    } catch (Exception $e) {
        $pdo->rollBack();
        $mensagem = "<div class='alert alert-danger'>Erro: " . $e->getMessage() . "</div>";
    }
}

// 2. Buscar Usuários para o select
$usuarios = $pdo->query("SELECT id, nome, usuario FROM usuarios WHERE nivel != 'admin' ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);

// 3. Buscar Todos os Módulos disponíveis
$todos_modulos = $pdo->query("SELECT * FROM modulos ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);

// 4. Se um usuário for selecionado, buscar as permissões dele
$user_selecionado = isset($_GET['uid']) ? (int)$_GET['uid'] : 0;
$permissoes_atuais = [];
if ($user_selecionado > 0) {
    $stmtP = $pdo->prepare("SELECT modulo_id FROM usuarios_modulos WHERE usuario_id = ?");
    $stmtP->execute([$user_selecionado]);
    $permissoes_atuais = $stmtP->fetchAll(PDO::FETCH_COLUMN);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Permissões de Módulos - Workspace Drive</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f8f9fa; font-family: 'Segoe UI', sans-serif; }
        .card-perm { border-radius: 15px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .modulo-item { transition: 0.2s; border-radius: 10px; margin-bottom: 8px; }
        .modulo-item:hover { background: #e8f0fe; }
    </style>
</head>
<body class="p-4">

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold text-dark"><i class="fas fa-key text-warning me-2"></i> Atribuir Módulos aos Usuários</h3>
        <a href="portal.php" class="btn btn-outline-secondary rounded-pill px-4">Voltar ao Portal</a>
    </div>

    <?php echo $mensagem; ?>

    <div class="row">
        <!-- Coluna: Seleção de Usuário -->
        <div class="col-md-4">
            <div class="card card-perm p-4 mb-4">
                <label class="small fw-bold text-muted mb-2">1. SELECIONE O USUÁRIO</label>
                <div class="list-group list-group-flush">
                    <?php foreach($usuarios as $u): ?>
                        <a href="?uid=<?php echo $u['id']; ?>" class="list-group-item list-group-item-action border-0 rounded-3 mb-1 <?php echo ($user_selecionado == $u['id']) ? 'active bg-primary' : ''; ?>">
                            <div class="fw-bold"><?php echo htmlspecialchars($u['nome']); ?></div>
                            <small class="<?php echo ($user_selecionado == $u['id']) ? 'text-white-50' : 'text-muted'; ?>">@<?php echo $u['usuario']; ?></small>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Coluna: Seleção de Módulos -->
        <div class="col-md-8">
            <?php if ($user_selecionado > 0): ?>
                <form method="POST" class="card card-perm p-4">
                    <input type="hidden" name="user_id" value="<?php echo $user_selecionado; ?>">
                    <label class="small fw-bold text-muted mb-3">2. MARQUE OS MÓDULOS PERMITIDOS</label>
                    
                    <div class="row">
                        <?php foreach($todos_modulos as $m): ?>
                            <div class="col-md-6">
                                <div class="p-3 border modulo-item d-flex align-items-center">
                                    <div class="form-check form-switch flex-grow-1">
                                        <input class="form-check-input" type="checkbox" name="modulos[]" value="<?php echo $m['id']; ?>" id="mod_<?php echo $m['id']; ?>" <?php echo in_array($m['id'], $permissoes_atuais) ? 'checked' : ''; ?>>
                                        <label class="form-check-label fw-bold ms-2" for="mod_<?php echo $m['id']; ?>">
                                            <i class="fas <?php echo $m['icone']; ?> me-2 text-primary"></i> <?php echo $m['nome']; ?>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="mt-4">
                        <button type="submit" name="btn_salvar_permissoes" class="btn btn-primary w-100 rounded-pill py-2 fw-bold shadow">SALVAR ACESSOS</button>
                    </div>
                </form>
            <?php else: ?>
                <div class="alert alert-light text-center p-5 border shadow-sm">
                    <i class="fas fa-arrow-left fa-3x text-muted mb-3"></i>
                    <h5>Selecione um usuário à esquerda para gerenciar seus aplicativos.</h5>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>