<?php
/**
 * BDSoft Workspace - PROJETOS / QUADRO
 * Versão Final: Filtros, Edição/Exclusão de Grupos e Exclusão de Tarefas
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

// --- CAPTURA DE FILTROS ---
$f_grupo    = $_GET['f_grupo'] ?? '';
$f_status   = $_GET['f_status'] ?? '';
$f_mes      = $_GET['f_mes'] ?? '';
$f_data_ini = $_GET['f_data_ini'] ?? '';
$f_data_fim = $_GET['f_data_fim'] ?? '';

// 1. Validar o Quadro Atual
$stmtQ = $pdo->prepare("SELECT * FROM quadros_projetos WHERE id = ?");
$stmtQ->execute([$id_quadro]);
$quadro = $stmtQ->fetch(PDO::FETCH_ASSOC);

if (!$quadro) {
    die("<div style='text-align:center; padding:50px; font-family:sans-serif;'><h2>❌ Quadro não encontrado</h2><a href='index.php'>Voltar para Projetos</a></div>");
}

// 2. Carregar Lista de Projetos (ComboBox 1)
$stmt_meus = $pdo->prepare("SELECT id, nome FROM quadros_projetos WHERE usuario_id = ? OR tipo = 'Publico' ORDER BY nome ASC");
$stmt_meus->execute([$user_id]);
$meus_projetos = $stmt_meus->fetchAll(PDO::FETCH_ASSOC);

// 3. Carregar Grupos do Quadro (Para o Filtro de Grupo)
$stmt_all_g = $pdo->prepare("SELECT id, nome FROM projetos_grupos WHERE quadro_id = ? ORDER BY id ASC");
$stmt_all_g->execute([$id_quadro]);
$lista_filtros_grupos = $stmt_all_g->fetchAll(PDO::FETCH_ASSOC);

// 4. Carregar Status do Quadro
$stmtS = $pdo->prepare("SELECT * FROM quadros_status WHERE quadro_id = ? ORDER BY id ASC");
$stmtS->execute([$id_quadro]);
$lista_status = $stmtS->fetchAll(PDO::FETCH_ASSOC);

// 5. Lista de Meses para filtro
$stmt_m = $pdo->prepare("SELECT DISTINCT DATE_FORMAT(data_fim, '%Y-%m') as mes_ano FROM tarefas_projetos WHERE quadro_id = ? AND data_fim IS NOT NULL ORDER BY data_fim DESC");
$stmt_m->execute([$id_quadro]);
$lista_meses = $stmt_m->fetchAll(PDO::FETCH_COLUMN);

/**
 * FUNÇÕES AUXILIARES
 */
function obterCorStatus($lista, $status_id) {
    foreach($lista as $s) { if($s['id'] == $status_id) return $s['cor']; }
    return "#c4c4c4";
}

function calcularSituacaoTarefa($t, $hoje, $lista_status) {
    $nome_status = '';
    foreach($lista_status as $s) { if($s['id'] == $t['status_id']) $nome_status = $s['label']; }

    if (stripos($nome_status, 'Concluído') !== false || stripos($nome_status, 'Concluido') !== false) {
        $data_finalizacao = $t['data_conclusao'] ?? $hoje;
        return ($data_finalizacao <= $t['data_fim']) 
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
        .filter-item { width: 180px; }

        .group-card { background: #ffffff; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 35px; border: 1px solid #eee; overflow: hidden; }
        .group-header { padding: 10px 20px; display: flex; align-items: center; justify-content: space-between; border-left: 8px solid; background: #fff; height: 60px; }
        .group-title-input { border:none; font-weight: bold; font-size: 1.1rem; background: transparent; width: 300px; outline: none; }
        .group-title-input:focus { border-bottom: 1px dashed #ccc; }

        .table-clean { width: 100%; border-collapse: collapse; }
        .table-clean th { background: #fafafa; padding: 12px; font-size: 10px; color: #6c757d; text-transform: uppercase; border-bottom: 1px solid #eee; }
        .task-row { border-bottom: 1px solid #f8f9fa; transition: 0.2s; cursor: pointer; height: 50px; }
        .task-row:hover { background-color: #f0f7ff; }

        .status-select { border:none; color:white; font-weight:bold; border-radius:6px; padding:6px 12px; width: 100%; cursor:pointer; text-align-last:center; outline:none; appearance:none; font-size:11px; }
        .group-collapsed { display: none !important; }
        .offcanvas { width: 45% !important; border-left: none; box-shadow: -10px 0 30px rgba(0,0,0,0.1); }
        
        /* Dropdown Clean */
        .btn-action-group { color: #aaa; transition: 0.3s; }
        .btn-action-group:hover { color: #333; background: #eee; }
        
        @media (max-width: 991px) { .sidebar-mini { display:none; } .main-wrapper { margin-left: 0; } .offcanvas { width: 100% !important; } }
    </style>
</head>
<body>

<!-- SIDEBAR DE ÍCONES -->
<div class="sidebar-mini shadow no-print">
    <a href="../portal.php" title="Portal Workspace"><i class="fas fa-th-large fa-2x"></i></a>
    <a href="index.php" title="Meus Projetos"><i class="fas fa-project-diagram fa-lg"></i></a>
    <hr class="w-75 opacity-25">
    <button class="btn btn-primary btn-sm rounded-circle shadow" data-bs-toggle="modal" data-bs-target="#modalNovoGrupo"><i class="fas fa-plus"></i></button>
</div>

<div class="main-wrapper">
    <!-- NAVBAR PRINCIPAL -->
    <nav class="nav-board d-flex justify-content-between align-items-center shadow-sm">
        <h5 class="fw-bold mb-0 text-dark"><?php echo htmlspecialchars($quadro['nome']); ?></h5>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-dark btn-sm rounded-pill px-3 fw-bold" data-bs-toggle="modal" data-bs-target="#modalStatus">ETIQUETAS</button>
            <button class="btn btn-primary btn-sm rounded-pill px-3 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalNovoGrupo">+ GRUPO</button>
            <a href="relatorios.php?id=<?php echo $id_quadro; ?>" class="btn btn-info btn-sm text-white rounded-pill px-4 fw-bold">DASHBOARD BI</a>
        </div>
    </nav>

    <!-- BARRA DE FILTROS -->
    <div class="filter-section no-print shadow-sm">
        <form method="GET" action="quadro.php" class="d-flex flex-wrap gap-2 align-items-center w-100">
            <input type="hidden" name="id" value="<?php echo $id_quadro; ?>">
            <span class="small fw-bold text-muted"><i class="fas fa-filter me-1"></i> FILTRAR:</span>

            <!-- ComboBox 1: Projetos -->
            <select class="form-select form-select-sm filter-item border-light" onchange="window.location.href='quadro.php?id='+this.value">
                <?php foreach($meus_projetos as $mp) { ?>
                    <option value="<?php echo $mp['id']; ?>" <?php echo ($id_quadro == $mp['id']) ? 'selected' : ''; ?>>
                        Projeto: <?php echo htmlspecialchars($mp['nome']); ?>
                    </option>
                <?php } ?>
            </select>

            <!-- ComboBox 2: Grupos -->
            <select name="f_grupo" class="form-select form-select-sm filter-item border-light">
                <option value="">Grupo: Todos</option>
                <?php foreach($lista_filtros_grupos as $lg) { ?>
                    <option value="<?php echo $lg['id']; ?>" <?php echo ($f_grupo == $lg['id']) ? 'selected' : ''; ?>>
                        Grupo: <?php echo htmlspecialchars($lg['nome']); ?>
                    </option>
                <?php } ?>
            </select>

            <!-- Status -->
            <select name="f_status" class="form-select form-select-sm filter-item border-light">
                <option value="">Status: Todos</option>
                <?php foreach($lista_status as $st) { ?>
                    <option value="<?php echo $st['id']; ?>" <?php echo ($f_status == $st['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($st['label']); ?>
                    </option>
                <?php } ?>
            </select>

            <!-- Período -->
            <div class="d-flex align-items-center gap-1 border rounded px-2 bg-light">
                <small class="fw-bold text-muted" style="font-size: 9px;">DE:</small>
                <input type="date" name="f_data_ini" class="form-control form-control-sm border-0 bg-transparent" value="<?php echo $f_data_ini; ?>" style="width:125px;">
                <small class="fw-bold text-muted" style="font-size: 9px;">ATÉ:</small>
                <input type="date" name="f_data_fim" class="form-control form-control-sm border-0 bg-transparent" value="<?php echo $f_data_fim; ?>" style="width:125px;">
            </div>

            <button type="submit" class="btn btn-sm btn-dark rounded-pill px-3 fw-bold">APLICAR</button>
            <?php if($f_grupo || $f_status || $f_mes || ($f_data_ini && $f_data_fim)) { ?>
                <a href="quadro.php?id=<?php echo $id_quadro; ?>" class="btn btn-sm btn-link text-danger text-decoration-none small">Limpar</a>
            <?php } ?>
        </form>
    </div>

    <div class="p-4">
        <?php 
        // Lógica de carregar grupos
        $sql_grupos_grid = "SELECT * FROM projetos_grupos WHERE quadro_id = ?";
        $params_g = [$id_quadro];
        if(!empty($f_grupo)) {
            $sql_grupos_grid .= " AND id = ?";
            $params_g[] = $f_grupo;
        }
        $stmtG_grid = $pdo->prepare($sql_grupos_grid . " ORDER BY id ASC");
        $stmtG_grid->execute($params_g);
        $grupos_render = $stmtG_grid->fetchAll(PDO::FETCH_ASSOC);

        foreach($grupos_render as $g) { 
        ?>
        <div class="group-card">
            <div class="group-header" style="border-left-color: <?php echo $g['cor']; ?>;">
                <!-- Esquerda: Olho e Título -->
                <div class="d-flex align-items-center gap-3">
                    <i class="fas fa-eye text-muted cursor-pointer" onclick="toggleVisibilidade(<?php echo $g['id']; ?>)" title="Expandir/Recolher"></i>
                    <input type="text" class="group-title-input" style="color:<?php echo $g['cor']; ?>;" value="<?php echo htmlspecialchars($g['nome']); ?>" onblur="ajaxUpdateGrupo(<?php echo $g['id']; ?>, 'nome', this.value)">
                </div>

                <!-- Direita: Ações do Grupo (Novo) -->
                <div class="dropdown">
                    <button class="btn btn-sm btn-action-group rounded-circle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-ellipsis-v"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                        <li><h6 class="dropdown-header small text-muted">AÇÕES DO GRUPO</h6></li>
                        <li>
                            <a class="dropdown-item" href="javascript:void(0)" onclick="abrirModalEditarGrupo(<?php echo $g['id']; ?>, '<?php echo htmlspecialchars(addslashes($g['nome'])); ?>', '<?php echo $g['cor']; ?>')">
                                <i class="fas fa-pen text-primary me-2 w-25"></i> Editar / Cor
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="javascript:void(0)" onclick="excluirGrupo(<?php echo $g['id']; ?>)">
                                <i class="fas fa-trash me-2 w-25"></i> Excluir Grupo
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div id="wrap_<?php echo $g['id']; ?>">
                <table class="table-clean">
                    <thead>
                        <tr>
                            <th style="width:50px;"></th>
                            <th>NOME DA TAREFA / ITEM</th>
                            <th style="width:180px;" class="text-center">SITUAÇÃO</th>
                            <th style="width:180px;" class="text-center">STATUS</th>
                            <th style="width:120px;" class="text-center">AÇÕES</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql_t = "SELECT * FROM tarefas_projetos WHERE grupo_id = ? AND quadro_id = ?";
                        $params_t = [$g['id'], $id_quadro];

                        if($f_status) { $sql_t .= " AND status_id = ?"; $params_t[] = $f_status; }
                        if($f_mes) { $sql_t .= " AND DATE_FORMAT(data_fim, '%Y-%m') = ?"; $params_t[] = $f_mes; }
                        if($f_data_ini && $f_data_fim) { $sql_t .= " AND data_fim BETWEEN ? AND ?"; $params_t[] = $f_data_ini; $params_t[] = $f_data_fim; }

                        $stmtT = $pdo->prepare($sql_t . " ORDER BY id ASC");
                        $stmtT->execute($params_t);
                        $tarefas_exibicao = $stmtT->fetchAll(PDO::FETCH_ASSOC);

                        foreach($tarefas_exibicao as $t) {
                            $cor_fundo_status = obterCorStatus($lista_status, $t['status_id']);
                        ?>
                        <tr class="task-row">
                            <td class="text-center"><input type="checkbox" class="form-check-input"></td>
                            <td onclick="abrirPainelDetalhes(<?php echo $t['id']; ?>, '<?php echo addslashes($t['titulo']); ?>')">
                                <span class="fw-bold text-dark"><?php echo htmlspecialchars($t['titulo']); ?></span>
                            </td>
                            <td class="text-center" onclick="abrirPainelDetalhes(<?php echo $t['id']; ?>)">
                                <?php echo calcularSituacaoTarefa($t, $hoje, $lista_status); ?>
                            </td>
                            <td class="p-2">
                                <select class="status-select shadow-sm" style="background-color:<?php echo $cor_fundo_status; ?>" onchange="ajaxUpdateTarefa(<?php echo $t['id']; ?>, 'status_id', this.value); location.reload();">
                                    <option value="">Selecione...</option>
                                    <?php foreach($lista_status as $s_op) { ?>
                                        <option value="<?php echo $s_op['id']; ?>" <?php echo ($t['status_id'] == $s_op['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($s_op['label']); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </td>
                            <td class="text-center text-nowrap">
                                <button class="btn btn-sm btn-light border rounded-pill px-3 fw-bold" onclick="abrirPainelDetalhes(<?php echo $t['id']; ?>, '<?php echo addslashes($t['titulo']); ?>')">Abrir</button>
                                <!-- BOTÃO EXCLUIR TAREFA -->
                                <button class="btn btn-sm text-danger ms-1 rounded-circle" onclick="excluirTarefa(<?php echo $t['id']; ?>)" title="Excluir Tarefa">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php } ?>
                        
                        <?php if(!$f_status && !$f_mes && !$f_data_ini) { ?>
                        <tr><td colspan="5" class="p-2 bg-light bg-opacity-25"><input type="text" class="form-control form-control-sm border-0 bg-transparent text-primary fw-bold px-3" placeholder="+ Adicionar tarefa..." onkeypress="if(event.key==='Enter') addTarefaRapida(this.value, <?php echo $g['id']; ?>)"></td></tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php } ?>
    </div>
</div>

<!-- PAINEL LATERAL (OFFCANVAS) -->
<div class="offcanvas offcanvas-end shadow-lg" tabindex="-1" id="painelTarefa">
    <div class="offcanvas-header border-bottom"><h5 class="fw-bold mb-0 text-primary" id="painelTitulo"></h5><button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button></div>
    <div class="offcanvas-body p-4 bg-light">
        <div class="card border-0 shadow-sm p-4 mb-4 rounded-4">
            <div class="row g-3">
                <div class="col-6"><label class="small fw-bold text-muted">Início</label><input type="date" id="p_inicio" class="form-control" onchange="salvarDetalhePainel('data_inicio', this.value)"></div>
                <div class="col-6"><label class="small fw-bold text-muted">Prazo</label><input type="date" id="p_fim" class="form-control" onchange="salvarDetalhePainel('data_fim', this.value)"></div>
                <div class="col-12 mt-3"><label class="small fw-bold text-muted">Justificativa / Comentário</label><textarea id="p_justificativa" class="form-control" rows="2" onblur="salvarDetalhePainel('justificativa', this.value)"></textarea></div>
                <div class="col-12 text-end mt-2"><button class="btn btn-sm btn-success px-4 rounded-pill fw-bold" onclick="location.reload()">SALVAR PRAZOS</button></div>
            </div>
        </div>
        <div class="card border-0 shadow-sm p-4 rounded-4">
            <h6 class="fw-bold text-muted mb-3 small uppercase">Timeline / Prints</h6>
            <div id="editor-timeline" contenteditable="true" placeholder="Cole um print (Ctrl+V)..."></div>
            <input type="hidden" id="edit_up_id" value="0">
            <div class="text-end mt-2 mb-4">
                <button class="btn btn-light btn-sm rounded-pill px-3" id="btnCancelUp" style="display:none;" onclick="resetarEdicaoTimeline()">Cancelar</button>
                <button class="btn btn-primary btn-sm rounded-pill px-4 fw-bold shadow" id="btnSaveUp" onclick="salvarUpdateTimeline()">PUBLICAR</button>
            </div>
            <div id="timeline-feed"></div>
        </div>
    </div>
</div>

<!-- MODAL STATUS -->
<div class="modal fade" id="modalStatus" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content p-4 border-0 shadow-lg">
    <h5 class="fw-bold mb-4">Gerenciar Etiquetas de Status</h5>
    <div class="input-group mb-4"><input type="text" id="ns_label" class="form-control" placeholder="Novo status..."><input type="color" id="ns_color" class="form-control form-control-color" value="#1a73e8"><button class="btn btn-primary" onclick="adicionarNovoStatus()">ADD</button></div>
    <div class="list-group"><?php foreach($lista_status as $ls) { ?><div class="list-group-item d-flex justify-content-between align-items-center"><span><i class="fas fa-circle me-2" style="color:<?php echo $ls['cor']; ?>;"></i> <?php echo htmlspecialchars($ls['label']); ?></span><button class="btn btn-sm text-danger" onclick="excluirStatus(<?php echo $ls['id']; ?>)"><i class="fas fa-trash"></i></button></div><?php } ?></div>
</div></div></div>

<!-- MODAL NOVO GRUPO -->
<div class="modal fade" id="modalNovoGrupo" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form action="acoes.php" method="POST" class="modal-content shadow border-0"><div class="modal-body p-4">
    <input type="hidden" name="acao" value="novo_grupo"><input type="hidden" name="quadro_id" value="<?php echo $id_quadro; ?>">
    <label class="small fw-bold mb-1">NOME DO GRUPO</label><input type="text" name="nome_grupo" class="form-control mb-3" required autofocus><label class="small fw-bold mb-1">COR</label><input type="color" name="cor" class="form-control form-control-color w-100" value="#1a73e8"><button type="submit" class="btn btn-primary w-100 rounded-pill mt-4 fw-bold shadow">CRIAR GRUPO</button>
</div></form></div></div>

<!-- MODAL EDITAR GRUPO (NOVO) -->
<div class="modal fade" id="modalEditarGrupo" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow border-0">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Editar Grupo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="edit_grupo_id">
                <label class="small fw-bold mb-1 text-muted">NOME DO GRUPO</label>
                <input type="text" id="edit_grupo_nome" class="form-control mb-3 fw-bold">
                
                <label class="small fw-bold mb-1 text-muted">COR DE DESTAQUE</label>
                <input type="color" id="edit_grupo_cor" class="form-control form-control-color w-100 mb-4">
                
                <button type="button" class="btn btn-primary w-100 rounded-pill fw-bold shadow" onclick="salvarEdicaoGrupo()">SALVAR ALTERAÇÕES</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let curId = 0; 
const offcanvasElement = document.getElementById('painelTarefa');
const drawer = new bootstrap.Offcanvas(offcanvasElement);
const modalEditGrupo = new bootstrap.Modal(document.getElementById('modalEditarGrupo'));

function toggleVisibilidade(id) { document.getElementById('wrap_'+id).classList.toggle('group-collapsed'); }
window.onload = function() {
    let hid = JSON.parse(localStorage.getItem('h_q<?php echo $id_quadro; ?>')) || [];
    hid.forEach(id => { const w = document.getElementById('wrap_'+id); if(w) w.classList.add('group-collapsed'); });
};
function ajaxUpdateGrupo(id, campo, valor) { const fd = new FormData(); fd.append('acao', 'atualizar_campo_grupo'); fd.append('id', id); fd.append('campo', campo); fd.append('valor', valor); fetch('acoes.php', { method: 'POST', body: fd }).then(() => location.reload()); }
function ajaxUpdateTarefa(id, campo, valor) { const fd = new FormData(); fd.append('acao', 'atualizar_campo_tarefa'); fd.append('id', id); fd.append('campo', campo); fd.append('valor', valor); fetch('acoes.php', { method: 'POST', body: fd }); }
function addTarefaRapida(t, g) { if(!t.trim()) return; const fd = new FormData(); fd.append('acao', 'nova_tarefa_completa'); fd.append('titulo', t); fd.append('grupo_id', g); fd.append('quadro_id', <?php echo $id_quadro; ?>); fd.append('data_inicio', '<?php echo $hoje; ?>'); fd.append('data_fim', '<?php echo $hoje; ?>'); fetch('acoes.php', { method: 'POST', body: fd }).then(() => location.reload()); }

// --- FUNÇÕES DE DETALHES TAREFA ---
function abrirPainelDetalhes(id, titulo) {
    curId = id; document.getElementById('painelTitulo').innerText = titulo;
    const fd = new FormData(); fd.append('acao', 'get_full_task'); fd.append('id', id);
    fetch('acoes.php', { method:'POST', body:fd }).then(r => r.json()).then(data => {
        document.getElementById('p_inicio').value = data.data_inicio || '';
        document.getElementById('p_fim').value = data.data_fim || '';
        document.getElementById('p_justificativa').value = data.justificativa || '';
        carregarTimeline(); drawer.show();
    });
}
function carregarTimeline() { fetch('acoes.php?acao=get_updates&id='+curId).then(r => r.text()).then(h => { document.getElementById('timeline-feed').innerHTML = h; }); }
function salvarUpdateTimeline() {
    const ed = document.getElementById('editor-timeline'); const upId = document.getElementById('edit_up_id').value;
    const fd = new FormData(); fd.append('acao', 'salvar_update'); fd.append('id', curId); fd.append('update_id', upId); fd.append('conteudo', ed.innerHTML);
    fetch('acoes.php', { method:'POST', body:fd }).then(() => { ed.innerHTML = ""; resetarEdicaoTimeline(); carregarTimeline(); });
}
function salvarDetalhePainel(campo, valor) {
    const fd = new FormData(); fd.append('acao', 'atualizar_campo_tarefa'); fd.append('id', curId); fd.append('campo', campo); fd.append('valor', valor);
    fetch('acoes.php', { method: 'POST', body: fd });
}
function resetarEdicaoTimeline() { document.getElementById('editor-timeline').innerHTML = ""; document.getElementById('edit_up_id').value = 0; document.getElementById('btnSaveUp').innerText = "SALVAR"; document.getElementById('btnCancelUp').style.display = 'none'; }
function adicionarNovoStatus() {
    const l = document.getElementById('ns_label').value; const c = document.getElementById('ns_color').value;
    const fd = new FormData(); fd.append('acao', 'add_status'); fd.append('quadro_id', <?php echo $id_quadro; ?>); fd.append('label', l); fd.append('cor', c);
    fetch('acoes.php', { method:'POST', body:fd }).then(() => location.reload());
}
function excluirStatus(id) { if(confirm("Excluir status?")) { const fd = new FormData(); fd.append('acao', 'excluir_status'); fd.append('status_id', id); fetch('acoes.php', { method:'POST', body:fd }).then(() => location.reload()); } }

// --- NOVAS FUNÇÕES PARA GRUPOS (EDITAR/EXCLUIR) ---
function abrirModalEditarGrupo(id, nome, cor) {
    document.getElementById('edit_grupo_id').value = id;
    document.getElementById('edit_grupo_nome').value = nome;
    document.getElementById('edit_grupo_cor').value = cor;
    modalEditGrupo.show();
}

function salvarEdicaoGrupo() {
    const id = document.getElementById('edit_grupo_id').value;
    const nome = document.getElementById('edit_grupo_nome').value;
    const cor = document.getElementById('edit_grupo_cor').value;

    const fd = new FormData();
    fd.append('acao', 'editar_grupo_full');
    fd.append('grupo_id', id);
    fd.append('nome', nome);
    fd.append('cor', cor);

    fetch('acoes.php', { method: 'POST', body: fd }).then(() => location.reload());
}

function excluirGrupo(id) {
    if(confirm("ATENÇÃO: Tem certeza que deseja excluir este grupo?\nTodas as tarefas vinculadas a ele também serão excluídas.")) {
        const fd = new FormData();
        fd.append('acao', 'excluir_grupo');
        fd.append('grupo_id', id);
        fetch('acoes.php', { method: 'POST', body: fd }).then(() => location.reload());
    }
}

// --- NOVA FUNÇÃO EXCLUIR TAREFA ---
function excluirTarefa(id) {
    if(confirm("Tem certeza que deseja excluir esta tarefa permanentemente?")) {
        const fd = new FormData();
        fd.append('acao', 'excluir_tarefa');
        fd.append('id', id);
        fetch('acoes.php', { method: 'POST', body: fd }).then(() => location.reload());
    }
}

document.getElementById('editor-timeline').addEventListener('paste', function(e) {
    const items = (e.clipboardData || e.originalEvent.clipboardData).items;
    for (let i in items) {
        if (items[i].kind === 'file') {
            e.preventDefault();
            const blob = items[i].getAsFile(); const r = new FileReader();
            r.onload = function(ev) { const img = document.createElement('img'); img.src = ev.target.result; document.getElementById('editor-timeline').appendChild(img); };
            r.readAsDataURL(blob);
        }
    }
});
</script>
</body>
</html>