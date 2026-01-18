<?php
/**
 * BDSoft Workspace - PROJETOS (LOBBY)
 * Local: projetos/index.php
 */
session_start();
require_once '../config.php';

if (!isset($_SESSION['usuario_id'])) { header("Location: ../login.php"); exit; }
$user_id = $_SESSION['usuario_id'];
$user_nivel = $_SESSION['usuario_nivel'];

$sql = "SELECT DISTINCT q.*, u.nome as criador_nome 
        FROM quadros_projetos q
        LEFT JOIN usuarios u ON q.usuario_id = u.id
        LEFT JOIN quadro_membros qm ON q.id = qm.quadro_id
        WHERE q.tipo = 'Publico' OR q.usuario_id = :uid OR qm.usuario_id = :uid
        ORDER BY q.data_criacao DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([':uid' => $user_id]);
$quadros = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Projetos - BDSoft Workspace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background:#f4f7f9; font-family:'Segoe UI',sans-serif; }
        .board-card { background:#fff; border-radius:16px; border:1px solid #e0e6ed; transition:0.3s; position:relative; overflow:hidden; display:block; text-decoration:none; color:inherit; }
        .board-card:hover { transform:translateY(-5px); border-color:#1a73e8; box-shadow:0 10px 20px rgba(0,0,0,0.05); }
        .actions-menu { position:absolute; top:10px; left:10px; display:none; gap:5px; z-index:100; }
        .board-card:hover .actions-menu { display:flex; }
        .btn-action { width:30px; height:30px; background:#fff; border:1px solid #ddd; border-radius:8px; display:flex; align-items:center; justify-content:center; cursor:pointer; }
        .btn-action:hover { background:#f8f9fa; }
    </style>
</head>
<body class="p-4">
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-5">
        <h2 class="fw-bold text-dark mb-0">Gestão de Projetos</h2>
        <div>
            <a href="../portal.php" class="btn btn-light border rounded-pill me-2 fw-bold">PORTAL</a>
            <button class="btn btn-primary rounded-pill px-4 fw-bold shadow" data-bs-toggle="modal" data-bs-target="#modalNovo">+ NOVO QUADRO</button>
        </div>
    </div>

    <div class="row g-4">
        <?php foreach ($quadros as $q): 
            $pode_editar = ($q['usuario_id'] == $user_id || $user_nivel === 'admin');
        ?>
        <div class="col-md-3">
            <div class="board-card shadow-sm">
                <?php if($pode_editar): ?>
                <div class="actions-menu">
                    <div class="btn-action" onclick="funcaoEditar(<?php echo $q['id']; ?>, '<?php echo addslashes($q['nome']); ?>')" title="Editar Nome"><i class="fas fa-edit text-primary small"></i></div>
                    <div class="btn-action" onclick="funcaoDeletar(<?php echo $q['id']; ?>)" title="Excluir"><i class="fas fa-trash-alt text-danger small"></i></div>
                </div>
                <?php endif; ?>

                <a href="quadro.php?id=<?php echo $q['id']; ?>" class="text-decoration-none text-dark">
                    <div style="height:100px; background:#f8fafc; display:flex; align-items:center; justify-content:center; position:relative;">
                        <i class="fas <?php echo $q['tipo']=='Privado'?'fa-lock text-danger':'fa-globe text-success'; ?> position-absolute top-0 end-0 m-3"></i>
                        <i class="fas fa-project-diagram fa-3x text-primary opacity-25"></i>
                    </div>
                    <div class="p-3 text-center border-top">
                        <div class="fw-bold text-truncate"><?php echo htmlspecialchars($q['nome']); ?></div>
                        <small class="text-muted">Criador: <?php echo htmlspecialchars($q['criador_nome']); ?></small>
                    </div>
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- MODAL NOVO -->
<div class="modal fade" id="modalNovo" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form action="acoes.php" method="POST" class="modal-content shadow-lg border-0">
    <div class="modal-header border-0 bg-light"><h5>Novo Workspace</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body p-4">
        <input type="hidden" name="acao" value="criar_quadro">
        <label class="small fw-bold">NOME DO PROJETO</label><input type="text" name="nome" class="form-control mb-3" required>
        <label class="small fw-bold">PRIVACIDADE</label><select name="privado" class="form-select"><option value="0">Público</option><option value="1">Privado</option></select>
    </div>
    <div class="modal-footer border-0"><button type="submit" class="btn btn-primary w-100 py-3 rounded-pill fw-bold">CRIAR AGORA</button></div>
</form></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function funcaoEditar(id, nome) {
    const novo = prompt("Novo nome para o quadro:", nome);
    if (novo && novo.trim() !== "" && novo !== nome) {
        const fd = new FormData();
        fd.append('acao', 'editar_nome_quadro');
        fd.append('id', id);
        fd.append('nome', novo.trim());
        fetch('acoes.php', { method: 'POST', body: fd }).then(res => {
            if(res.ok) location.reload();
            else alert("Erro ao salvar.");
        });
    }
}

function funcaoDeletar(id) {
    if (confirm("Você tem certeza? Isso apagará todas as tarefas e grupos deste projeto.")) {
        window.location.href = 'acoes.php?acao=deletar_quadro_completo&id=' + id;
    }
}
</script>
</body>
</html>