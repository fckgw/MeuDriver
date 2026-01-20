<?php
session_start();
require_once '../config.php';
if ($_SESSION['usuario_nivel'] !== 'admin') { header("Location: index.php"); exit; }

$usuarios = $pdo->query("SELECT id, nome, usuario, nivel FROM usuarios ORDER BY nome ASC")->fetchAll();
$submodulos = $pdo->query("SELECT * FROM agro_submodulos ORDER BY id ASC")->fetchAll();

$user_id_sel = isset($_GET['uid']) ? (int)$_GET['uid'] : 0;
$permissoes_atuais = ($user_id_sel > 0) ? $pdo->query("SELECT submodulo_id FROM usuarios_agro_permissões WHERE usuario_id = $user_id_sel")->fetchAll(PDO::FETCH_COLUMN) : [];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8"><title>Acessos - AgroCampo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'sidebar_agro.php'; ?>
    <div class="main-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold">Gestão de Acessos</h2>
            <button class="btn btn-primary rounded-pill px-4 fw-bold" data-bs-toggle="modal" data-bs-target="#modalNovoUser">+ NOVO USUÁRIO</button>
        </div>

        <div class="row g-4">
            <div class="col-md-7">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light"><tr><th class="ps-4">COLABORADOR</th><th>NÍVEL</th><th class="text-center">AÇÃO</th></tr></thead>
                        <tbody>
                            <?php foreach($usuarios as $u): ?>
                            <tr class="<?php echo ($user_id_sel == $u['id'])?'table-primary':''; ?>">
                                <td class="ps-4"><b><?php echo $u['nome']; ?></b><br><small><?php echo $u['usuario']; ?></small></td>
                                <td><span class="badge <?php echo $u['nivel']=='admin'?'bg-danger':'bg-secondary'; ?> rounded-pill"><?php echo $u['nivel']; ?></span></td>
                                <td class="text-center">
                                    <?php if($u['nivel']!=='admin'): ?>
                                        <a href="?uid=<?php echo $u['id']; ?>" class="btn btn-sm btn-primary rounded-pill px-3">Permissões</a>
                                    <?php else: ?> <i class="fas fa-check-double text-success"></i> <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="col-md-5">
                <?php if($user_id_sel > 0): ?>
                <div class="card border-0 shadow-lg rounded-4 p-4">
                    <h5 class="fw-bold mb-4">Acessos para: <span class="text-primary"><?php echo $pdo->query("SELECT nome FROM usuarios WHERE id = $user_id_sel")->fetchColumn(); ?></span></h5>
                    <form action="acoes.php" method="POST">
                        <input type="hidden" name="acao" value="salvar_permissoes_agro">
                        <input type="hidden" name="usuario_id" value="<?php echo $user_id_sel; ?>">
                        <?php foreach($submodulos as $sub): ?>
                            <label class="list-group-item d-flex align-items-center py-2">
                                <input class="form-check-input me-3" type="checkbox" name="submodulos[]" value="<?php echo $sub['id']; ?>" <?php echo in_array($sub['id'], $permissoes_atuais)?'checked':''; ?> style="width:22px; height:22px;">
                                <span><?php echo $sub['nome']; ?></span>
                            </label>
                        <?php endforeach; ?>
                        <button class="btn btn-success w-100 rounded-pill py-3 mt-4 fw-bold shadow">SALVAR ACESSOS</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- MODAL NOVO USUÁRIO + PERMISSÕES -->
    <div class="modal fade" id="modalNovoUser" tabindex="-1"><div class="modal-dialog"><form action="acoes.php" method="POST" class="modal-content shadow border-0">
        <div class="modal-header border-0 bg-primary text-white"><h5>Novo Usuário Agro</h5></div>
        <div class="modal-body p-4">
            <input type="hidden" name="acao" value="novo_usuario_agro">
            <label class="small fw-bold">NOME COMPLETO</label><input type="text" name="nome" class="form-control mb-3" required>
            <label class="small fw-bold">CPF</label><input type="text" name="cpf" class="form-control mb-3" required>
            <label class="small fw-bold">E-MAIL (LOGIN)</label><input type="email" name="usuario" class="form-control mb-3" required>
            <label class="small fw-bold">SENHA INICIAL</label><input type="password" name="senha" class="form-control mb-3" required>
            
            <label class="small fw-bold text-primary mt-3">LIBERAR ACESSOS IMEDIATOS:</label>
            <div class="p-3 border rounded bg-light">
                <?php foreach($submodulos as $sub): ?>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="submodulos[]" value="<?php echo $sub['id']; ?>" checked> <label class="form-check-label small"><?php echo $sub['nome']; ?></label></div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="modal-footer border-0"><button class="btn btn-primary w-100 rounded-pill py-2 fw-bold">CRIAR E LIBERAR AGRO</button></div>
    </form></div></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>