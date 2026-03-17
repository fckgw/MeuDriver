<?php
/**
 * BDSoft Workspace - PROJETOS / QUADRO
 */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../config.php';

if (!isset($_SESSION['usuario_id'])) { 
    header("Location: ../login.php"); 
    exit; 
}

$id_quadro = (isset($_GET['id'])) ? (int)$_GET['id'] : 0;
$user_id = $_SESSION['usuario_id'];
$hoje = date('Y-m-d');

// 1. Validar o Quadro Atual
$stmtQ = $pdo->prepare("SELECT * FROM quadros_projetos WHERE id = ?");
$stmtQ->execute([$id_quadro]);
$quadro = $stmtQ->fetch(PDO::FETCH_ASSOC);

if (!$quadro) {
    die("<div style='text-align:center; padding:50px; font-family:sans-serif;'><h2>❌ Quadro não encontrado</h2><a href='index.php'>Voltar para Projetos</a></div>");
}

// 2. Carregar Combos e Filtros
$stmt_meus = $pdo->prepare("SELECT id, nome FROM quadros_projetos WHERE usuario_id = ? OR tipo = 'Publico' ORDER BY nome ASC");
$stmt_meus->execute([$user_id]);
$meus_projetos = $stmt_meus->fetchAll(PDO::FETCH_ASSOC);

$stmtS = $pdo->prepare("SELECT * FROM quadros_status WHERE quadro_id = ? ORDER BY id ASC");
$stmtS->execute([$id_quadro]);
$lista_status = $stmtS->fetchAll(PDO::FETCH_ASSOC);

function obterCorStatus($lista, $status_id) {
    foreach($lista as $s) { if($s['id'] == $status_id) return $s['cor']; }
    return "#c4c4c4";
}

function calcularSituacaoTarefa($t, $hoje, $lista_status) {
    $nome_status = '';
    foreach($lista_status as $s) { if($s['id'] == $t['status_id']) $nome_status = $s['label']; }
    if (stripos($nome_status, 'Concluído') !== false || stripos($nome_status, 'Concluido') !== false) {
        return ($t['data_conclusao'] <= $t['data_fim']) 
            ? '<span class="badge bg-success shadow-sm">ENTREGUE NO PRAZO</span>' 
            : '<span class="badge bg-warning text-dark shadow-sm">ENTREGUE COM ATRASO</span>';
    }
    if (empty($t['data_inicio']) || empty($t['data_fim'])) return '<span class="badge bg-light text-muted border">S/ DATA</span>';
    if ($t['data_inicio'] > $hoje) return '<span class="badge bg-secondary opacity-75">AGUARDANDO</span>';
    if ($hoje >= $t['data_inicio'] && $hoje <= $t['data_fim']) return '<span class="badge bg-primary shadow-sm">EM CURSO</span>';
    return '<span class="badge bg-danger shadow-sm">ATRASADO</span>';
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
        :root { --sidebar-mini-w: 70px; --primary-blue: #1a73e8; }
        body { background:#f8f9fa; font-family:'Segoe UI', system-ui, sans-serif; margin:0; display: flex; }
        .sidebar-mini { width: var(--sidebar-mini-w); background:#292f4c; height:100vh; position:fixed; left:0; top:0; z-index:1050; display: flex; flex-direction: column; align-items: center; padding-top: 25px; box-shadow: 2px 0 10px rgba(0,0,0,0.1); }
        .sidebar-mini a { color: rgba(255,255,255,0.6); margin-bottom: 30px; transition: 0.3s; text-decoration: none; }
        .sidebar-mini a:hover { color: #fff; transform: scale(1.1); }
        .main-wrapper { flex:1; margin-left: var(--sidebar-mini-w); min-width: 0; }
        .nav-board { background:#ffffff; border-bottom:1px solid #dee2e6; padding:12px 25px; position:sticky; top:0; z-index:900; }
        .filter-section { background: #fff; border-bottom: 1px solid #eee; padding: 10px 25px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .group-card { background: #ffffff; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 35px; border: 1px solid #eee; overflow: hidden; }
        .group-header { padding: 10px 20px; display: flex; align-items: center; justify-content: space-between; border-left: 8px solid; background: #fff; height: 60px; }
        .group-title-input { border:none; font-weight: bold; font-size: 1.1rem; background: transparent; width: 300px; outline: none; }
        .table-clean { width: 100%; border-collapse: collapse; }
        .table-clean th { background: #fafafa; padding: 12px; font-size: 10px; color: #6c757d; text-transform: uppercase; border-bottom: 1px solid #eee; }
        .task-row { border-bottom: 1px solid #f8f9fa; transition: 0.2s; cursor: pointer; height: 50px; }
        .task-row:hover { background-color: #f0f7ff; }
        .status-select { border:none; color:white; font-weight:bold; border-radius:6px; padding:6px 12px; width: 100%; cursor:pointer; text-align-last:center; outline:none; appearance:none; font-size:11px; }
        .group-collapsed { display: none !important; }
        .offcanvas { width: 45% !important; border-left: none; box-shadow: -10px 0 30px rgba(0,0,0,0.1); }
        #editor-timeline { min-height: 100px; border: 1px solid #ddd; padding: 15px; border-radius: 12px; background: #fff; margin-bottom: 10px; outline: none; overflow-y: auto; }
        #editor-timeline img { max-width: 100%; border-radius: 8px; margin-top: 10px; }
        @media (max-width: 991px) { .sidebar-mini { display:none; } .main-wrapper { margin-left: 0; } .offcanvas { width: 100% !important; } }
    </style>
</head>
<body>

<div class="sidebar-mini shadow no-print">
    <a href="../portal.php" title="Portal Workspace"><i class="fas fa-th-large fa-2x"></i></a>
    <a href="index.php" title="Meus Projetos"><i class="fas fa-project-diagram fa-lg"></i></a>
    <hr class="w-75 opacity-25">
    <button class="btn btn-primary btn-sm rounded-circle shadow" data-bs-toggle="modal" data-bs-target="#modalNovoGrupo"><i class="fas fa-plus"></i></button>
</div>

<div class="main-wrapper">
    <nav class="nav-board d-flex justify-content-between align-items-center shadow-sm">
        <h5 class="fw-bold mb-0 text-dark"><?php echo htmlspecialchars($quadro['nome']); ?></h5>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-dark btn-sm rounded-pill px-3 fw-bold" onclick="abrirModalStatus()">ETIQUETAS</button>
            <button class="btn btn-primary btn-sm rounded-pill px-3 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalNovoGrupo">+ GRUPO</button>
            <a href="relatorios.php?id=<?php echo $id_quadro; ?>" class="btn btn-info btn-sm text-white rounded-pill px-4 fw-bold">DASHBOARD BI</a>
        </div>
    </nav>

    <div class="p-4">
        <?php 
        $stmtG_grid = $pdo->prepare("SELECT * FROM projetos_grupos WHERE quadro_id = ? ORDER BY id ASC");
        $stmtG_grid->execute([$id_quadro]);
        while($g = $stmtG_grid->fetch(PDO::FETCH_ASSOC)) { 
        ?>
        <div class="group-card">
            <div class="group-header" style="border-left-color: <?php echo $g['cor']; ?>;">
                <div class="d-flex align-items-center gap-3">
                    <i class="fas fa-eye text-muted cursor-pointer" id="eye_<?php echo $g['id']; ?>" onclick="toggleVisibilidade(<?php echo $g['id']; ?>)"></i>
                    <input type="text" class="group-title-input" style="color:<?php echo $g['cor']; ?>;" value="<?php echo htmlspecialchars($g['nome']); ?>" onblur="ajaxUpdateGrupo(<?php echo $g['id']; ?>, 'nome', this.value)">
                </div>
                <div class="dropdown">
                    <button class="btn btn-sm rounded-circle border-0 bg-transparent" type="button" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v text-muted"></i></button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                        <li><a class="dropdown-item" href="javascript:void(0)" onclick="abrirModalEditarGrupo(<?php echo $g['id']; ?>, '<?php echo htmlspecialchars(addslashes($g['nome'])); ?>', '<?php echo $g['cor']; ?>')">Editar Grupo</a></li>
                        <li><a class="dropdown-item text-danger" href="javascript:void(0)" onclick="excluirGrupo(<?php echo $g['id']; ?>)">Excluir Grupo</a></li>
                    </ul>
                </div>
            </div>
            <div id="wrap_<?php echo $g['id']; ?>">
                <table class="table-clean">
                    <thead>
                        <tr><th style="width:50px;"></th><th>TAREFA</th><th style="width:180px;" class="text-center">SITUAÇÃO</th><th style="width:180px;" class="text-center">STATUS</th><th style="width:120px;" class="text-center">AÇÕES</th></tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmtT = $pdo->prepare("SELECT * FROM tarefas_projetos WHERE grupo_id = ? AND quadro_id = ? ORDER BY id ASC");
                        $stmtT->execute([$g['id'], $id_quadro]);
                        while($t = $stmtT->fetch()) {
                            $cor_st = obterCorStatus($lista_status, $t['status_id']);
                        ?>
                        <tr class="task-row">
                            <td class="text-center"><input type="checkbox" class="form-check-input"></td>
                            <td onclick="abrirPainelDetalhes(<?php echo $t['id']; ?>, '<?php echo addslashes($t['titulo']); ?>')"><span class="fw-bold text-dark"><?php echo htmlspecialchars($t['titulo']); ?></span></td>
                            <td class="text-center"><?php echo calcularSituacaoTarefa($t, $hoje, $lista_status); ?></td>
                            <td>
                                <select class="status-select shadow-sm" style="background-color:<?php echo $cor_st; ?>" onchange="ajaxUpdateTarefa(<?php echo $t['id']; ?>, 'status_id', this.value); location.reload();">
                                    <option value="">Selecione...</option>
                                    <?php foreach($lista_status as $s_op) { ?>
                                        <option value="<?php echo $s_op['id']; ?>" <?php echo ($t['status_id'] == $s_op['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($s_op['label']); ?></option>
                                    <?php } ?>
                                </select>
                            </td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-light border rounded-pill px-3" onclick="abrirPainelDetalhes(<?php echo $t['id']; ?>, '<?php echo addslashes($t['titulo']); ?>')">Abrir</button>
                                <button class="btn btn-sm text-danger ms-1" onclick="excluirTarefa(<?php echo $t['id']; ?>)"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                        <?php } ?>
                        <tr><td colspan="5" class="p-2"><input type="text" class="form-control form-control-sm border-0 bg-transparent text-primary fw-bold px-3" placeholder="+ Adicionar tarefa..." onkeypress="if(event.key==='Enter') addTarefaRapida(this.value, <?php echo $g['id']; ?>)"></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php } ?>
    </div>
</div>

<!-- PAINEL LATERAL -->
<div class="offcanvas offcanvas-end shadow-lg" tabindex="-1" id="painelTarefa">
    <div class="offcanvas-header border-bottom"><h5 class="fw-bold mb-0 text-primary" id="painelTitulo"></h5><button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button></div>
    <div class="offcanvas-body p-4 bg-light">
        <div class="card border-0 shadow-sm p-4 mb-4 rounded-4">
            <div class="row g-3">
                <div class="col-6"><label class="small fw-bold text-muted">Início</label><input type="date" id="p_inicio" class="form-control" onchange="salvarDetalhePainel('data_inicio', this.value)"></div>
                <div class="col-6"><label class="small fw-bold text-muted">Prazo</label><input type="date" id="p_fim" class="form-control" onchange="salvarDetalhePainel('data_fim', this.value)"></div>
                <div class="col-12 mt-3"><label class="small fw-bold text-muted">Justificativa</label><textarea id="p_justificativa" class="form-control" rows="2" onblur="salvarDetalhePainel('justificativa', this.value)"></textarea></div>
                <div class="col-12 text-end mt-2"><button class="btn btn-sm btn-success px-4 rounded-pill fw-bold" onclick="location.reload()">SALVAR PRAZOS</button></div>
            </div>
        </div>
        <div class="card border-0 shadow-sm p-4 rounded-4">
            <h6 class="fw-bold text-muted mb-3 small uppercase">Timeline / Prints</h6>
            <div id="editor-timeline" contenteditable="true" placeholder="Cole um print ou digite..."></div>
            <input type="hidden" id="edit_up_id" value="0">
            <div class="text-end mt-2 mb-4">
                <button class="btn btn-light btn-sm rounded-pill px-3" id="btnCancelUp" style="display:none;" onclick="resetarEdicaoTimeline()">Cancelar</button>
                <button class="btn btn-primary btn-sm rounded-pill px-4 fw-bold shadow" id="btnSaveUp" onclick="salvarUpdateTimeline()">PUBLICAR</button>
            </div>
            <div id="timeline-feed"></div>
        </div>
    </div>
</div>

<!-- MODAL ETIQUETAS (CORRIGIDO) -->
<div class="modal fade" id="modalStatus" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-4 border-0 shadow-lg rounded-4">
            <h5 class="fw-bold mb-4">Gerenciar Etiquetas</h5>
            <div class="input-group mb-4">
                <input type="text" id="ns_label" class="form-control" placeholder="Novo status...">
                <input type="color" id="ns_color" class="form-control form-control-color" value="#1a73e8">
                <button class="btn btn-primary px-3 fw-bold" onclick="adicionarNovoStatus()">ADD</button>
            </div>
            <div class="list-group list-group-flush" style="max-height: 300px; overflow-y: auto;">
                <?php foreach($lista_status as $ls) { ?>
                <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                    <span><i class="fas fa-circle me-2" style="color:<?php echo $ls['cor']; ?>;"></i> <?php echo htmlspecialchars($ls['label']); ?></span>
                    <button class="btn btn-sm text-danger" onclick="excluirStatus(<?php echo $ls['id']; ?>)"><i class="fas fa-trash"></i></button>
                </div>
                <?php } ?>
            </div>
            <div class="text-end mt-3"><button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Fechar</button></div>
        </div>
    </div>
</div>

<!-- MODAL CONFIRMAÇÃO EXCLUSÃO -->
<div class="modal fade" id="modalConfirmaExclusaoUpdate" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0"><h5 class="modal-title fw-bold text-danger">Confirmar Exclusão</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-4 text-center">
                <p class="mb-0">Deseja realmente excluir permanentemente este registro?</p>
                <div id="itemExcluirPreview" class="p-2 bg-light mt-2 small text-muted fst-italic rounded"></div>
            </div>
            <div class="modal-footer border-0 pt-0 justify-content-center pb-4">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">NÃO</button>
                <button type="button" class="btn btn-danger rounded-pill px-4 fw-bold shadow" id="btnConfirmarExcluirUpdate">SIM, EXCLUIR</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL NOVO GRUPO -->
<div class="modal fade" id="modalNovoGrupo" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form action="acoes.php" method="POST" class="modal-content shadow border-0"><div class="modal-body p-4"><input type="hidden" name="acao" value="novo_grupo"><input type="hidden" name="quadro_id" value="<?php echo $id_quadro; ?>"><label class="small fw-bold mb-1">NOME DO GRUPO</label><input type="text" name="nome_grupo" class="form-control mb-3" required autofocus><label class="small fw-bold mb-1">COR</label><input type="color" name="cor" class="form-control form-control-color w-100" value="#1a73e8"><button type="submit" class="btn btn-primary w-100 rounded-pill mt-4 fw-bold">CRIAR GRUPO</button></div></form></div></div>

<!-- MODAL EDITAR GRUPO -->
<div class="modal fade" id="modalEditarGrupo" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content shadow border-0"><div class="modal-body p-4"><input type="hidden" id="edit_grupo_id"><label class="small fw-bold mb-1">NOME DO GRUPO</label><input type="text" id="edit_grupo_nome" class="form-control mb-3 fw-bold"><label class="small fw-bold mb-1">COR</label><input type="color" id="edit_grupo_cor" class="form-control form-control-color w-100 mb-4"><button type="button" class="btn btn-primary w-100 rounded-pill fw-bold shadow" onclick="salvarEdicaoGrupo()">SALVAR</button></div></div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
var curId = 0; 
var updateIdParaExcluir = 0;
var boardId = <?php echo $id_quadro; ?>;

var drawer = new bootstrap.Offcanvas(document.getElementById('painelTarefa'));
var modalExcluir = new bootstrap.Modal(document.getElementById('modalConfirmaExclusaoUpdate'));
var modalEditGrupo = new bootstrap.Modal(document.getElementById('modalEditarGrupo'));
var modalStatus = new bootstrap.Modal(document.getElementById('modalStatus'));

function abrirModalStatus() { modalStatus.show(); }

function toggleVisibilidade(id) {
    var wrap = document.getElementById('wrap_' + id);
    var eye = document.getElementById('eye_' + id);
    var key = 'closedGroups_q' + boardId;
    var closed = JSON.parse(localStorage.getItem(key)) || [];
    if (wrap.classList.contains('group-collapsed')) {
        wrap.classList.remove('group-collapsed');
        eye.classList.replace('fa-eye-slash', 'fa-eye');
        closed = closed.filter(function(i){ return i !== id; });
    } else {
        wrap.classList.add('group-collapsed');
        eye.classList.replace('fa-eye', 'fa-eye-slash');
        if (closed.indexOf(id) === -1) closed.push(id);
    }
    localStorage.setItem(key, JSON.stringify(closed));
}

document.addEventListener("DOMContentLoaded", function() {
    var closed = JSON.parse(localStorage.getItem('closedGroups_q' + boardId)) || [];
    closed.forEach(function(id) {
        var wrap = document.getElementById('wrap_' + id);
        var eye = document.getElementById('eye_' + id);
        if (wrap && eye) { wrap.classList.add('group-collapsed'); eye.classList.replace('fa-eye', 'fa-eye-slash'); }
    });
});

function ajaxUpdateGrupo(id, campo, valor) {
    var fd = new FormData(); fd.append('acao', 'atualizar_campo_grupo'); fd.append('id', id); fd.append('campo', campo); fd.append('valor', valor); fd.append('quadro_id', boardId);
    fetch('acoes.php', { method: 'POST', body: fd }).then(function() { location.reload(); });
}

function ajaxUpdateTarefa(id, campo, valor) {
    var fd = new FormData(); fd.append('acao', 'atualizar_campo_tarefa'); fd.append('id', id); fd.append('campo', campo); fd.append('valor', valor); fd.append('quadro_id', boardId);
    fetch('acoes.php', { method: 'POST', body: fd });
}

function addTarefaRapida(t, g) {
    if(!t.trim()) return;
    var fd = new FormData(); fd.append('acao', 'nova_tarefa_completa'); fd.append('titulo', t); fd.append('grupo_id', g); fd.append('quadro_id', boardId);
    fetch('acoes.php', { method: 'POST', body: fd }).then(function() { location.reload(); });
}

function abrirPainelDetalhes(id, titulo) {
    curId = id;
    document.getElementById('painelTitulo').innerText = titulo || "Detalhes";
    var fd = new FormData(); fd.append('acao', 'get_full_task'); fd.append('id', id); fd.append('quadro_id', boardId);
    fetch('acoes.php', { method:'POST', body:fd }).then(function(r){ return r.json(); }).then(function(data){
        document.getElementById('p_inicio').value = data.data_inicio || '';
        document.getElementById('p_fim').value = data.data_fim || '';
        document.getElementById('p_justificativa').value = data.justificativa || '';
        carregarTimeline(); drawer.show();
    });
}

function carregarTimeline() {
    fetch('acoes.php?acao=get_updates&id='+curId+'&quadro_id='+boardId)
    .then(function(r){ return r.text(); })
    .then(function(h){ document.getElementById('timeline-feed').innerHTML = h; });
}

function salvarUpdateTimeline() {
    var ed = document.getElementById('editor-timeline');
    var upId = document.getElementById('edit_up_id').value;
    var fd = new FormData(); fd.append('acao', 'salvar_update'); fd.append('id', curId); fd.append('update_id', upId); fd.append('conteudo', ed.innerHTML); fd.append('quadro_id', boardId);
    fetch('acoes.php', { method:'POST', body:fd }).then(function(){
        ed.innerHTML = ""; resetarEdicaoTimeline(); carregarTimeline();
    });
}

function prepararEdicaoUpdate(id) {
    var el = document.getElementById('texto_update_' + id);
    document.getElementById('edit_up_id').value = id;
    document.getElementById('editor-timeline').innerHTML = el.innerHTML;
    document.getElementById('btnSaveUp').innerText = "ATUALIZAR";
    document.getElementById('btnCancelUp').style.display = 'inline-block';
    document.getElementById('editor-timeline').focus();
}

function abrirModalExcluirUpdate(id, preview) {
    updateIdParaExcluir = id;
    document.getElementById('itemExcluirPreview').innerText = '"' + preview + '"';
    modalExcluir.show();
}

document.getElementById('btnConfirmarExcluirUpdate').onclick = function() {
    var fd = new FormData(); fd.append('acao', 'excluir_update'); fd.append('id', updateIdParaExcluir); fd.append('quadro_id', boardId);
    fetch('acoes.php', { method: 'POST', body: fd }).then(function(r){ return r.json(); }).then(function(res){
        if(res.status === 'ok') { modalExcluir.hide(); carregarTimeline(); }
        else { alert("Erro ao excluir"); }
    });
};

function salvarDetalhePainel(campo, valor) {
    var fd = new FormData(); fd.append('acao', 'atualizar_campo_tarefa'); fd.append('id', curId); fd.append('campo', campo); fd.append('valor', valor); fd.append('quadro_id', boardId);
    fetch('acoes.php', { method: 'POST', body: fd });
}

function resetarEdicaoTimeline() {
    document.getElementById('editor-timeline').innerHTML = "";
    document.getElementById('edit_up_id').value = 0;
    document.getElementById('btnSaveUp').innerText = "PUBLICAR";
    document.getElementById('btnCancelUp').style.display = 'none';
}

function adicionarNovoStatus() {
    var l = document.getElementById('ns_label').value; var c = document.getElementById('ns_color').value;
    var fd = new FormData(); fd.append('acao', 'add_status'); fd.append('quadro_id', boardId); fd.append('label', l); fd.append('cor', c);
    fetch('acoes.php', { method:'POST', body:fd }).then(function(){ location.reload(); });
}

function excluirStatus(id) {
    if(confirm("Excluir status?")) {
        var fd = new FormData(); fd.append('acao', 'excluir_status'); fd.append('status_id', id); fd.append('quadro_id', boardId);
        fetch('acoes.php', { method:'POST', body:fd }).then(function(){ location.reload(); });
    }
}

function abrirModalEditarGrupo(id, nome, cor) {
    document.getElementById('edit_grupo_id').value = id;
    document.getElementById('edit_grupo_nome').value = nome;
    document.getElementById('edit_grupo_cor').value = cor;
    modalEditGrupo.show();
}

function salvarEdicaoGrupo() {
    var fd = new FormData();
    fd.append('acao', 'editar_grupo_full');
    fd.append('grupo_id', document.getElementById('edit_grupo_id').value);
    fd.append('nome', document.getElementById('edit_grupo_nome').value);
    fd.append('cor', document.getElementById('edit_grupo_cor').value);
    fd.append('quadro_id', boardId);
    fetch('acoes.php', { method: 'POST', body: fd }).then(function(){ location.reload(); });
}

function excluirGrupo(id) {
    if(confirm("Excluir este grupo e todas as suas tarefas?")) {
        var fd = new FormData(); fd.append('acao', 'excluir_grupo'); fd.append('grupo_id', id); fd.append('quadro_id', boardId);
        fetch('acoes.php', { method: 'POST', body: fd }).then(function(){ location.reload(); });
    }
}

function excluirTarefa(id) {
    if(confirm("Excluir tarefa permanentemente?")) {
        var fd = new FormData(); fd.append('acao', 'excluir_tarefa'); fd.append('id', id); fd.append('quadro_id', boardId);
        fetch('acoes.php', { method: 'POST', body: fd }).then(function(){ location.reload(); });
    }
}

document.getElementById('editor-timeline').addEventListener('paste', function(e) {
    var items = (e.clipboardData || e.originalEvent.clipboardData).items;
    for (var i in items) {
        if (items[i].kind === 'file') {
            e.preventDefault();
            var blob = items[i].getAsFile();
            var r = new FileReader();
            r.onload = function(ev) {
                var img = document.createElement('img'); img.src = ev.target.result;
                img.style.maxWidth = "100%";
                document.getElementById('editor-timeline').appendChild(img);
            };
            r.readAsDataURL(blob);
        }
    }
});
</script>
</body>
</html>