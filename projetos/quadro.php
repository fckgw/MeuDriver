<?php
/**
 * BDSoft Workspace - PROJETOS / QUADRO
 */
session_start();
require_once '../config.php';

if (!isset($_SESSION['usuario_id'])) { header("Location: ../login.php"); exit; }

$id_quadro = (int)$_GET['id'];
$user_id = $_SESSION['usuario_id'];

$stmtQ = $pdo->prepare("SELECT * FROM quadros_projetos WHERE id = ?");
$stmtQ->execute([$id_quadro]);
$quadro = $stmtQ->fetch();
if (!$quadro) die("Quadro não encontrado.");

$lista_status = $pdo->prepare("SELECT * FROM quadros_status WHERE quadro_id = ? ORDER BY id ASC");
$lista_status->execute([$id_quadro]);
$meus_status = $lista_status->fetchAll();

$grupos = $pdo->prepare("SELECT * FROM projetos_grupos WHERE quadro_id = ? ORDER BY id ASC");
$grupos->execute([$id_quadro]);
$meus_grupos = $grupos->fetchAll();

function verCor($lista, $id) {
    foreach($lista as $s) { if($s['id'] == $id) return $s['cor']; }
    return "#c4c4c4";
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($quadro['nome']); ?> - BDSoft</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background:#f5f6f8; font-family:'Segoe UI',sans-serif; }
        .nav-board { background:#fff; border-bottom:1px solid #dee2e6; padding:12px 30px; position:sticky; top:0; z-index:1000; }
        .table-monday { width:100%; background:#fff; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,0.1); margin-bottom:20px; border-collapse:collapse; }
        .table-monday th { font-size:12px; color:#676879; padding:12px; border-bottom:1px solid #eee; font-weight:400; text-align: left; }
        .status-select { border:none; color:white; font-weight:bold; border-radius:4px; padding:8px; width:100%; cursor:pointer; text-align-last:center; outline:none; }
        .date-input { border:none; background:#f8f9fa; border-radius:4px; font-size:12px; padding:5px; width:100%; text-align:center; }
        #editor-evidencias { min-height:400px; border:1px solid #ddd; padding:20px; background:#fff; border-radius:10px; overflow-y:auto; outline:none; }
        .loader { display:none; position:absolute; top:0; left:0; width:100%; height:100%; background:rgba(255,255,255,0.8); z-index:2000; align-items:center; justify-content:center; flex-direction:column; border-radius:15px; }
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
        <a href="relatorios.php?id=<?php echo $id_quadro; ?>" class="btn btn-sm btn-info text-white rounded-pill px-3 fw-bold">RELATÓRIOS</a>
    </div>
</nav>

<div class="container-fluid p-4">
    <?php foreach($meus_grupos as $g): ?>
    <div class="mb-5">
        <div class="d-flex align-items-center mb-2">
            <i class="fas fa-caret-down me-2" style="color:<?php echo $g['cor']; ?>;"></i>
            <input type="text" class="border-0 bg-transparent fw-bold" style="color:<?php echo $g['cor']; ?>; font-size:1.1rem; outline:none;" value="<?php echo $g['nome']; ?>" onblur="upG(<?php echo $g['id']; ?>, 'nome', this.value)">
            <a href="acoes.php?del_grupo=<?php echo $g['id']; ?>&quadro_id=<?php echo $id_quadro; ?>" class="ms-3 text-danger opacity-25" onclick="return confirm('Excluir grupo?')"><i class="fas fa-trash-alt"></i></a>
        </div>
        
        <table class="table-monday">
            <thead>
                <tr>
                    <th style="width:40px;"></th>
                    <th>Tarefa</th>
                    <th style="width:180px;" class="text-center">Status</th>
                    <th style="width:140px;" class="text-center">Início</th>
                    <th style="width:140px;" class="text-center">Prazo Final</th>
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
                    <td></td>
                    <td><input type="text" class="form-control form-control-sm border-0 bg-transparent fw-medium" value="<?php echo htmlspecialchars($t['titulo']); ?>" onblur="upT(<?php echo $t['id']; ?>, 'titulo', this.value)"></td>
                    <td class="p-2">
                        <select class="status-select" style="background-color:<?php echo $cor_bg; ?>" onchange="upT(<?php echo $t['id']; ?>, 'status_id', this.value); location.reload();">
                            <?php foreach($meus_status as $s): ?>
                                <option value="<?php echo $s['id']; ?>" <?php echo $t['status_id']==$s['id']?'selected':''; ?>><?php echo htmlspecialchars($s['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td class="px-2"><input type="date" class="date-input" value="<?php echo $t['data_inicio']; ?>" onchange="upT(<?php echo $t['id']; ?>, 'data_inicio', this.value)"></td>
                    <td class="px-2"><input type="date" class="date-input" value="<?php echo $t['data_fim']; ?>" onchange="upT(<?php echo $t['id']; ?>, 'data_fim', this.value)"></td>
                    <td class="text-center"><i class="fas fa-file-signature text-primary cursor-pointer" onclick="openE(<?php echo $t['id']; ?>, '<?php echo addslashes($t['titulo']); ?>')"></i></td>
                    <td class="text-center"><a href="acoes.php?excluir_tarefa=<?php echo $t['id']; ?>" class="text-danger opacity-25"><i class="fas fa-times"></i></a></td>
                </tr>
                <?php endwhile; ?>
                <tr>
                    <td></td>
                    <td colspan="6" class="p-2"><input type="text" class="form-control form-control-sm border-0 text-primary fw-bold" placeholder="+ Adicionar Tarefa (Enter)" onkeypress="if(event.key==='Enter') addT(this.value, <?php echo $g['id']; ?>)"></td>
                </tr>
            </tbody>
        </table>
    </div>
    <?php endforeach; ?>
</div>

<!-- MODAIS E SCRIPTS (Mantenha o Modal de Editor e as funções JS de antes) -->
<div class="modal fade" id="modalEditor" tabindex="-1" data-bs-backdrop="static"><div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content border-0 shadow-lg position-relative"><div class="loader" id="editorLoader"><div class="spinner-border text-primary"></div><div class="mt-2 fw-bold">Sincronizando...</div></div><div class="modal-header bg-dark text-white border-0"><h5 id="modalTitulo"></h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body bg-light"><div id="editor-evidencias" contenteditable="true"></div></div><div class="modal-footer"><button class="btn btn-primary px-5 rounded-pill fw-bold" id="btnSalvar" onclick="saveE()">SALVAR</button></div></div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let curId = 0; const modalE = new bootstrap.Modal(document.getElementById('modalEditor'));
function upG(id, c, v) { const fd = new FormData(); fd.append('acao', 'atualizar_campo_grupo'); fd.append('id', id); fd.append('campo', c); fd.append('valor', v); fetch('acoes.php', { method: 'POST', body: fd }); }
function upT(id, c, v) { const fd = new FormData(); fd.append('acao', 'atualizar_campo_tarefa'); fd.append('id', id); fd.append('campo', c); fd.append('valor', v); fetch('acoes.php', { method: 'POST', body: fd }); }
function addT(t, g) { if(!t) return; const fd = new FormData(); fd.append('acao', 'nova_tarefa'); fd.append('titulo', t); fd.append('grupo_id', g); fd.append('quadro_id', <?php echo $id_quadro; ?>); fetch('acoes.php', { method: 'POST', body: fd }).then(() => location.reload()); }
function openE(id, t) { curId = id; document.getElementById('modalTitulo').innerText = t; document.getElementById('editor-evidencias').innerHTML = "Carregando..."; modalE.show(); fetch('acoes.php?acao=get_evidencia&id='+id).then(r => r.text()).then(h => { document.getElementById('editor-evidencias').innerHTML = h; }); }
function saveE() { document.getElementById('editorLoader').style.display='flex'; const fd = new FormData(); fd.append('acao', 'salvar_evidencia'); fd.append('id', curId); fd.append('conteudo', document.getElementById('editor-evidencias').innerHTML); fetch('acoes.php', { method: 'POST', body: fd }).then(() => { document.getElementById('editorLoader').style.display='none'; alert("Salvo!"); }); }
document.getElementById('editor-evidencias').addEventListener('paste', function(e) { const items = (e.clipboardData || e.originalEvent.clipboardData).items; for (let i in items) { if (items[i].kind === 'file') { e.preventDefault(); const blob = items[i].getAsFile(); const r = new FileReader(); r.onload = function(ev) { const img = document.createElement('img'); img.src = ev.target.result; document.getElementById('editor-evidencias').appendChild(img); }; r.readAsDataURL(blob); } } });
</script>
</body>
</html>