<?php
/**
 * BDSoft Workspace - PROJETOS (LOBBY)
 */
session_start();
require_once '../config.php';

if (!isset($_SESSION['usuario_id'])) { 
    header("Location: ../login.php"); 
    exit; 
}

$user_id = $_SESSION['usuario_id'];
$user_nivel = $_SESSION['usuario_nivel'];

// Buscar Projetos
$sql = "SELECT DISTINCT q.*, u.nome as criador_nome 
        FROM quadros_projetos q
        LEFT JOIN usuarios u ON q.usuario_id = u.id
        LEFT JOIN quadro_membros qm ON q.id = qm.quadro_id
        WHERE q.tipo = 'Publico' OR q.usuario_id = :uid OR qm.usuario_id = :uid
        ORDER BY q.data_criacao DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([':uid' => $user_id]);
$quadros = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lista de usuários para o modal de compartilhar
$usuarios_sistema = $pdo->query("SELECT id, nome, usuario FROM usuarios WHERE status = 'ativo' ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projetos - BDSoft Workspace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary-blue: #1a73e8; }
        body { background:#f4f7f9; font-family:'Segoe UI', sans-serif; }
        .board-card { 
            background:#fff; border-radius:16px; border:1px solid #e0e6ed; 
            transition:0.3s; position:relative; overflow:hidden; 
            display:block; text-decoration:none; color:inherit; height: 100%;
        }
        .board-card:hover { transform:translateY(-5px); border-color:#1a73e8; box-shadow:0 10px 20px rgba(0,0,0,0.05); }
        .actions-menu { position:absolute; top:10px; left:10px; display:none; gap:5px; z-index:100; }
        .board-card:hover .actions-menu { display:flex; }
        .btn-action { width:30px; height:30px; background:#fff; border:1px solid #ddd; border-radius:8px; display:flex; align-items:center; justify-content:center; cursor:pointer; }
        .modal-content { border-radius: 20px; border: none; }
    </style>
</head>
<body class="p-4">

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-5">
        <h2 class="fw-bold text-dark mb-0">Gestão de Projetos</h2>
        <div>
            <a href="../portal.php" class="btn btn-light border rounded-pill me-2 fw-bold px-4">PORTAL</a>
            <button class="btn btn-primary rounded-pill px-4 fw-bold shadow" data-bs-toggle="modal" data-bs-target="#modalNovo">+ NOVO PROJETO</button>
        </div>
    </div>

    <div class="row g-4">
        <?php foreach ($quadros as $q): 
            $eh_dono = ($q['usuario_id'] == $user_id || $user_nivel === 'admin');
        ?>
        <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12">
            <div class="board-card shadow-sm">
                <div class="actions-menu">
                    <?php if($eh_dono): ?>
                        <div class="btn-action" onclick="editQ(<?php echo $q['id']; ?>, '<?php echo addslashes($q['nome']); ?>')" title="Editar"><i class="fas fa-edit text-primary"></i></div>
                        <div class="btn-action" data-bs-toggle="modal" data-bs-target="#modalShare<?php echo $q['id']; ?>"><i class="fas fa-user-plus text-info"></i></div>
                        <div class="btn-action" onclick="delQ(<?php echo $q['id']; ?>)"><i class="fas fa-trash-alt text-danger"></i></div>
                    <?php endif; ?>
                </div>

                <a href="quadro.php?id=<?php echo $q['id']; ?>" class="text-decoration-none text-dark">
                    <div style="height:120px; background:#f8fafc; display:flex; align-items:center; justify-content:center; position:relative;">
                        <i class="fas <?php echo $q['tipo']=='Privado'?'fa-lock text-danger':'fa-globe text-success'; ?> position-absolute top-0 end-0 m-3"></i>
                        <i class="fas fa-project-diagram fa-4x text-primary opacity-25"></i>
                    </div>
                    <div class="p-3 text-center border-top bg-white">
                        <div class="fw-bold text-truncate mb-1"><?php echo htmlspecialchars($q['nome']); ?></div>
                        <small class="text-muted d-block" style="font-size: 11px;">Proprietário: <?php echo htmlspecialchars($q['criador_nome']); ?></small>
                    </div>
                </a>
            </div>
        </div>

        <!-- MODAL COMPARTILHAR -->
        <div class="modal fade" id="modalShare<?php echo $q['id']; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content shadow-lg">
                    <div class="modal-header border-0 pt-4 px-4">
                        <h5 class="fw-bold">Compartilhar: <?php echo htmlspecialchars($q['nome']); ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <form action="acoes.php" method="POST" class="mb-4">
                            <input type="hidden" name="acao" value="add_membro">
                            <input type="hidden" name="quadro_id" value="<?php echo $q['id']; ?>">
                            <div class="input-group">
                                <select name="usuario_id" class="form-select bg-light" required>
                                    <option value="">Convidar usuário...</option>
                                    <?php foreach($usuarios_sistema as $us): ?>
                                        <option value="<?php echo $us['id']; ?>"><?php echo $us['nome']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-primary px-3">CONVIDAR</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- MODAL: NOVO QUADRO -->
<div class="modal fade" id="modalNovo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form id="formNovoQuadro" class="modal-content shadow-lg">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="fw-bold">Criar Novo Projeto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="small fw-bold text-muted">NOME DO PROJETO</label>
                    <input type="text" name="nome_quadro" id="input_nome_quadro" class="form-control form-control-lg bg-light border-0" placeholder="Ex: Financeiro" required>
                </div>
                <div class="mb-2">
                    <label class="small fw-bold text-muted">VISIBILIDADE</label>
                    <select name="tipo" class="form-select bg-light border-0">
                        <option value="Privado">Privado (Apenas eu vejo)</option>
                        <option value="Publico">Público (Todos veem)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="submit" id="btnSalvarQuadro" class="btn btn-primary w-100 rounded-pill py-3 fw-bold shadow">CRIAR AGORA</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: POP-UP DE SUCESSO -->
<div class="modal fade" id="modalSucessoQuadro" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg text-center" style="border-radius: 25px;">
            <div class="modal-body p-5">
                <i class="fas fa-check-circle fa-5x text-success mb-4"></i>
                <h4 class="fw-bold">Sucesso!</h4>
                <p class="text-muted small">O projeto <strong id="nomeQuadroCriado"></strong> foi criado e configurado.</p>
                <div class="spinner-border spinner-border-sm text-primary mt-3"></div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('formNovoQuadro').onsubmit = function(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSalvarQuadro');
    const nomeQuadro = document.getElementById('input_nome_quadro').value;
    
    btn.disabled = true;
    btn.innerHTML = 'Processando...';

    const formData = new FormData(this);
    formData.append('acao', 'criar_quadro');

    fetch('acoes.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'ok') {
            bootstrap.Modal.getInstance(document.getElementById('modalNovo')).hide();
            document.getElementById('nomeQuadroCriado').innerText = nomeQuadro;
            new bootstrap.Modal(document.getElementById('modalSucessoQuadro')).show();
            
            setTimeout(() => {
                window.location.href = 'quadro.php?id=' + data.id;
            }, 2000);
        } else {
            alert('Erro: ' + data.msg);
            btn.disabled = false;
            btn.innerHTML = 'CRIAR AGORA';
        }
    })
    .catch(err => {
        console.error(err);
        alert('Erro na requisição.');
        btn.disabled = false;
    });
};

function delQ(id) {
    if(confirm("Deseja excluir este quadro e tudo que há nele?")) {
        const fd = new FormData();
        fd.append('acao', 'deletar_quadro_completo');
        fd.append('id', id);
        fetch('acoes.php', {method:'POST', body:fd}).then(() => location.reload());
    }
}
</script>
</body>
</html>