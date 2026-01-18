<?php
/**
 * BDSoft Workspace - PROJETOS / QUADRO
 * Local: projetos/quadro.php
 */
session_start();
require_once '../config.php';

if (!isset($_SESSION['usuario_id'])) { header("Location: ../login.php"); exit; }

$id_quadro = (int)$_GET['id'];
$user_id = $_SESSION['usuario_id'];

// 1. Validar Quadro
$stmtQ = $pdo->prepare("SELECT * FROM quadros_projetos WHERE id = ?");
$stmtQ->execute([$id_quadro]);
$quadro = $stmtQ->fetch();
if (!$quadro) die("Quadro não encontrado.");

// 2. Carregar Status (ComboBox)
$stmtS = $pdo->prepare("SELECT * FROM quadros_status WHERE quadro_id = ? ORDER BY id ASC");
$stmtS->execute([$id_quadro]);
$meus_status = $stmtS->fetchAll(PDO::FETCH_ASSOC);

// 3. Carregar Grupos
$stmtG = $pdo->prepare("SELECT * FROM projetos_grupos WHERE quadro_id = ? ORDER BY id ASC");
$stmtG->execute([$id_quadro]);
$grupos = $stmtG->fetchAll(PDO::FETCH_ASSOC);

function verCor($lista, $id) {
    foreach($lista as $s) { if($s['id'] == $id) return $s['cor']; }
    return "#c4c4c4";
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($quadro['nome']); ?> - BDSoft Workspace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background:#f5f6f8; font-family:'Segoe UI', sans-serif; margin:0; }
        .nav-board { background:#fff; border-bottom:1px solid #dee2e6; padding:12px 30px; position:sticky; top:0; z-index:1000; }
        .table-monday { width:100%; background:#fff; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,0.1); margin-bottom:25px; border-collapse:collapse; }
        .table-monday th { font-size:11px; color:#676879; padding:12px; border-bottom:1px solid #eee; font-weight:600; text-transform: uppercase; text-align: left; }
        .status-select { border:none; color:white; font-weight:bold; border-radius:4px; padding:6px; width:100%; cursor:pointer; text-align-last:center; outline:none; appearance: none; }
        .date-input { border:none; background:#f8f9fa; border-radius:4px; font-size:12px; padding:5px; width:100%; text-align:center; }
        #editor-evidencias { min-height:450px; border:1px solid #ddd; padding:25px; background:#fff; border-radius:12px; overflow-y:auto; outline:none; }
        #editor-evidencias img { max-width:100%; border-radius:10px; margin:15px 0; box-shadow:0 4px 10px rgba(0,0,0,0.1); }
        .loader { display:none; position:absolute; top:0; left:0; width:100%; height:100%; background:rgba(255,255,255,0.8); z-index:2000; align-items:center; justify-content:center; flex-direction:column; border-radius: 15px; }
    </style>
</head>
<body>

<nav class="nav-board d-flex justify-content-between align-items-center shadow-sm">
    <div class="d-flex align-items-center">
        <a href="index.php" class="btn btn-sm btn-light border rounded-circle me-3"><i class="fas fa-arrow-left"></i></a>
        <h4 class="fw-bold mb-0 text-primary"><?php echo htmlspecialchars($quadro['nome']); ?></h4>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-sm btn-primary rounded-pill px-4 fw-bold" data-bs-toggle="modal" data-bs-target="#modalNovoGrupo">+ NOVO GRUPO</button>
        <a href="../portal.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3 fw-bold">WORKSPACE</a>
    </div>
</nav>

<div class="container-fluid p-4">
    <?php foreach($grupos as $g): ?>
    <div class="mb-5">
        <div class="d-flex align-items-center mb-2 justify-content-between">
            <div class="d-flex align-items-center flex-grow-1">
                <i class="fas fa-caret-down me-2" style="color:<?php echo $g['cor']; ?>;"></i>
                <input type="text" class="border-0 bg-transparent fw-bold" style="color:<?php echo $g['cor']; ?>; font-size:1.1rem; outline:none;" value="<?php echo htmlspecialchars($g['nome']); ?>" onblur="upG(<?php echo $g['id']; ?>, 'nome', this.value)">
                <input type="color" class="form-control form-control-color border-0 p-0 ms-2" style="width:20px; height:20px; background:none;" value="<?php echo $g['cor']; ?>" onchange="upG(<?php echo $g['id']; ?>, 'cor', this.value)">
            </div>
            <a href="acoes.php?del_grupo=<?php echo $g['id']; ?>&quadro_id=<?php echo $id_quadro; ?>" class="text-danger opacity-25" onclick="return confirm('Excluir grupo?')"><i class="fas fa-trash-alt"></i></a>
        </div>
        
        <table class="table-monday">
            <thead>
                <tr>
                    <th style="width:40px;"></th>
                    <th>ITEM / TAREFA</th>
                    <th style="width:180px;" class="text-center">STATUS</th>
                    <th style="width:140px;" class="text-center">INICIAL</th>
                    <th style="width:140px;" class="text-center">FINAL</th>
                    <th style="width:60px;" class="text-center">OBS</th>
                    <th style="width:40px;"></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $stmtT = $pdo->prepare("SELECT * FROM tarefas_projetos WHERE grupo_id = ? ORDER BY id ASC");
                $stmtT->execute([$g['id']]);
                while($t = $stmtT->fetch()):
                    $cor_bg = verCor($meus_status, $t['status_id']);
                ?>
                <tr class="task-row border-bottom">
                    <td class="text-center"><input type="checkbox" class="form-check-input"></td>
                    <td><input type="text" class="form-control form-control-sm border-0 bg-transparent fw-medium" value="<?php echo htmlspecialchars($t['titulo']); ?>" onblur="upT(<?php echo $t['id']; ?>, 'titulo', this.value)"></td>
                    <td class="p-2">
                        <select class="status-select" style="background-color:<?php echo $cor_bg; ?>" onchange="upT(<?php echo $t['id']; ?>, 'status_id', this.value); location.reload();">
                            <?php foreach($meus_status as $s): ?>
                                <option value="<?php echo $s['id']; ?>" <?php echo ($t['status_id']==$s['id'])?'selected':''; ?>><?php echo htmlspecialchars($s['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td class="px-2"><input type="date" class="date-input" value="<?php echo $t['data_inicio']; ?>" onchange="upT(<?php echo $t['id']; ?>, 'data_inicio', this.value)"></td>
                    <td class="px-2"><input type="date" class="date-input" value="<?php echo $t['data_fim']; ?>" onchange="upT(<?php echo $t['id']; ?>, 'data_fim', this.value)"></td>
                    <td class="text-center"><i class="fas fa-file-signature text-primary cursor-pointer fa-lg" onclick="openE(<?php echo $t['id']; ?>, '<?php echo addslashes($t['titulo']); ?>')"></i></td>
                    <td class="text-center"><a href="acoes.php?excluir_tarefa=<?php echo $t['id']; ?>&id_quadro=<?php echo $id_quadro; ?>" class="text-danger opacity-25"><i class="fas fa-times"></i></a></td>
                </tr>
                <?php endwhile; ?>
                <tr>
                    <td></td>
                    <td colspan="7" class="p-2">
                        <input type="text" class="form-control form-control-sm border-0 text-primary fw-bold px-3" placeholder="+ Adicionar Tarefa (Enter)" onkeypress="if(event.key==='Enter') addT(this.value, <?php echo $g['id']; ?>)">
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <?php endforeach; ?>
</div>

<!-- MODAL EDITOR (POP-UP OBS) -->
<div class="modal fade" id="modalEditor" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg position-relative">
            <div class="loader" id="editorLoader"><div class="spinner-border text-primary mb-2"></div><div class="fw-bold">Salvando...</div></div>
            <div class="modal-header bg-dark text-white border-0"><h5 class="modal-title" id="modalTitulo"></h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body bg-light"><div id="editor-evidencias" contenteditable="true"></div></div>
            <div class="modal-footer bg-white border-0">
                <button class="btn btn-outline-secondary rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Fechar</button>
                <button class="btn btn-primary rounded-pill px-5 fw-bold shadow" id="btnSalvar" onclick="saveE()">SALVAR ANOTAÇÕES</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL NOVO GRUPO -->
<div class="modal fade" id="modalNovoGrupo" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form action="acoes.php" method="POST" class="modal-content shadow border-0"><div class="modal-body p-4">
    <input type="hidden" name="acao" value="novo_grupo"><input type="hidden" name="quadro_id" value="<?php echo $id_quadro; ?>">
    <label class="small fw-bold mb-1">NOME DO GRUPO</label><input type="text" name="nome_grupo" class="form-control mb-3" required autofocus>
    <label class="small fw-bold mb-1">COR DO TEMA</label><input type="color" name="cor" class="form-control form-control-color w-100" value="#1a73e8">
    <button type="submit" class="btn btn-primary w-100 rounded-pill mt-4 fw-bold">CRIAR GRUPO</button>
</div></form></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let curId = 0; const modalE = new bootstrap.Modal(document.getElementById('modalEditor'));
function upG(id, c, v) { const fd = new FormData(); fd.append('acao', 'atualizar_campo_grupo'); fd.append('id', id); fd.append('campo', c); fd.append('valor', v); fetch('acoes.php', { method: 'POST', body: fd }).then(() => { if(c === 'cor') location.reload(); }); }
function upT(id, c, v) { const fd = new FormData(); fd.append('acao', 'atualizar_campo_tarefa'); fd.append('id', id); fd.append('campo', c); fd.append('valor', v); fetch('acoes.php', { method: 'POST', body: fd }); }
function addT(t, g) { if(!t.trim()) return; const fd = new FormData(); fd.append('acao', 'nova_tarefa'); fd.append('titulo', t); fd.append('grupo_id', g); fd.append('quadro_id', <?php echo $id_quadro; ?>); fetch('acoes.php', { method: 'POST', body: fd }).then(() => location.reload()); }
function openE(id, t) { curId = id; document.getElementById('modalTitulo').innerText = t; document.getElementById('editor-evidencias').innerHTML = "Carregando..."; modalE.show(); fetch('acoes.php?acao=get_evidencia&id='+id).then(r => r.text()).then(h => { document.getElementById('editor-evidencias').innerHTML = h; }); }
function saveE() { document.getElementById('editorLoader').style.display='flex'; const fd = new FormData(); fd.append('acao', 'salvar_evidencia'); fd.append('id', curId); fd.append('conteudo', document.getElementById('editor-evidencias').innerHTML); fetch('acoes.php', { method: 'POST', body: fd }).then(() => { document.getElementById('editorLoader').style.display='none'; alert("Sincronizado!"); }); }
document.getElementById('editor-evidencias').addEventListener('paste', function(e) { const items = (e.clipboardData || e.originalEvent.clipboardData).items; for (let i in items) { if (items[i].kind === 'file') { e.preventDefault(); const blob = items[i].getAsFile(); const r = new FileReader(); r.onload = function(ev) { const img = document.createElement('img'); img.src = ev.target.result; document.getElementById('editor-evidencias').appendChild(img); }; r.readAsDataURL(blob); } } });
</script>
</body>
</html>