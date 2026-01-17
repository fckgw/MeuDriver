<?php
/**
 * BDSoft Workspace - QUADRO DE PROJETOS COMPLETO
 * Local: projetos_quadro.php
 */
session_start();
require_once 'config.php';

if (!isset($_SESSION['usuario_id'])) { header("Location: login.php"); exit; }

$id_quadro = (int)$_GET['id'];
$user_id_sessao = $_SESSION['usuario_id'];

// 1. Validar Quadro
$stmtQ = $pdo->prepare("SELECT * FROM quadros_projetos WHERE id = ?");
$stmtQ->execute([$id_quadro]);
$quadro = $stmtQ->fetch();
if (!$quadro) { die("Quadro não encontrado."); }

// 2. Carregar Status e Grupos
$stmtS = $pdo->prepare("SELECT * FROM quadros_status WHERE quadro_id = ? ORDER BY id ASC");
$stmtS->execute([$id_quadro]);
$lista_status = $stmtS->fetchAll(PDO::FETCH_ASSOC);

$stmtG = $pdo->prepare("SELECT * FROM projetos_grupos WHERE quadro_id = ? ORDER BY id ASC");
$stmtG->execute([$id_quadro]);
$grupos = $stmtG->fetchAll(PDO::FETCH_ASSOC);

function getCorStatus($lista, $id) {
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
        body { background: #f5f6f8; font-family: 'Segoe UI', sans-serif; margin: 0; }
        .nav-board { background: #fff; border-bottom: 1px solid #dee2e6; padding: 12px 30px; position: sticky; top:0; z-index:1000; }
        
        .group-header { display: flex; align-items: center; justify-content: space-between; margin-top: 35px; }
        .group-name-input { border: 1px solid transparent; background: transparent; font-weight: 700; font-size: 1.1rem; outline: none; padding: 2px 8px; border-radius: 4px; }
        .group-name-input:focus { border-color: #ddd; background: #fff; }
        
        .table-monday { width: 100%; background: #fff; border-radius: 8px; border-collapse: collapse; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .table-monday th { font-size: 12px; color: #676879; padding: 12px; border-bottom: 1px solid #eee; font-weight: 400; text-align: left; }
        .task-row { border-bottom: 1px solid #eee; }
        .task-row:hover { background-color: #f8fafc; }

        .status-select { border: none; color: white; font-weight: bold; border-radius: 4px; padding: 8px; width: 100%; cursor: pointer; text-align-last: center; outline: none; }
        
        /* Editor de Evidências no Pop-up */
        #editor-evidencias { min-height: 450px; border: 1px solid #ddd; padding: 25px; background: #fff; outline: none; border-radius: 12px; overflow-y: auto; }
        #editor-evidencias img { max-width: 100%; border-radius: 10px; margin: 15px 0; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        
        .loader-overlay { display:none; position:absolute; top:0; left:0; width:100%; height:100%; background:rgba(255,255,255,0.85); z-index:2000; align-items:center; justify-content:center; flex-direction:column; border-radius: 15px; }
    </style>
</head>
<body>

<nav class="nav-board d-flex justify-content-between align-items-center shadow-sm">
    <div class="d-flex align-items-center">
        <a href="projetos_home.php" class="btn btn-sm btn-light border rounded-circle me-3"><i class="fas fa-arrow-left"></i></a>
        <h4 class="fw-bold mb-0 text-primary"><?php echo htmlspecialchars($quadro['nome']); ?></h4>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-sm btn-primary rounded-pill px-4 fw-bold" data-bs-toggle="modal" data-bs-target="#modalNovoGrupo">+ NOVO GRUPO</button>
        <a href="projetos_relatorios.php?id=<?php echo $id_quadro; ?>" class="btn btn-sm btn-info text-white rounded-pill px-4 fw-bold shadow-sm">RELATÓRIOS</a>
    </div>
</nav>

<div class="container-fluid p-4">
    <?php foreach($grupos as $g): ?>
    <div class="mb-5">
        <div class="group-header mb-2">
            <div class="d-flex align-items-center flex-grow-1">
                <i class="fas fa-caret-down me-2" style="color: <?php echo $g['cor']; ?>;"></i>
                <input type="text" class="group-name-input" style="color: <?php echo $g['cor']; ?>;" 
                       value="<?php echo htmlspecialchars($g['nome']); ?>" 
                       onblur="updateGrupo(<?php echo $g['id']; ?>, 'nome', this.value)">
                
                <input type="color" class="form-control form-control-color border-0 p-0 ms-2" style="width:20px; height:20px;" 
                       value="<?php echo $g['cor']; ?>" 
                       onchange="updateGrupo(<?php echo $g['id']; ?>, 'cor', this.value)">
            </div>
            <a href="projetos_acoes.php?del_grupo=<?php echo $g['id']; ?>&quadro_id=<?php echo $id_quadro; ?>" 
               class="text-danger opacity-25" onclick="return confirm('Excluir este grupo?')"><i class="fas fa-trash-alt"></i></a>
        </div>
        
        <table class="table-monday">
            <thead>
                <tr>
                    <th style="width: 40px;"></th>
                    <th>Tarefa</th>
                    <th style="width: 200px;" class="text-center">Status</th>
                    <th style="width: 150px;" class="text-center">Prioridade</th>
                    <th style="width: 160px;" class="text-center">Prazo Final</th>
                    <th style="width: 80px;" class="text-center">OBS</th>
                    <th style="width: 40px;"></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $stmtT = $pdo->prepare("SELECT * FROM tarefas_projetos WHERE grupo_id = ? ORDER BY id ASC");
                $stmtT->execute([$g['id']]);
                while($t = $stmtT->fetch()):
                    $cor_bg = getCorStatus($lista_status, $t['status_id']);
                ?>
                <tr class="task-row">
                    <td class="text-center"><input type="checkbox" class="form-check-input"></td>
                    <td><input type="text" class="form-control form-control-sm border-0 bg-transparent fw-medium" value="<?php echo htmlspecialchars($t['titulo']); ?>" onblur="updateTarefa(<?php echo $t['id']; ?>, 'titulo', this.value)"></td>
                    <td class="p-2">
                        <select class="status-select" style="background-color: <?php echo $cor_bg; ?>" onchange="updateTarefa(<?php echo $t['id']; ?>, 'status_id', this.value); location.reload();">
                            <?php foreach($lista_status as $s): ?>
                                <option value="<?php echo $s['id']; ?>" <?php echo ($t['status_id'] == $s['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($s['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td class="px-3">
                        <select class="form-select form-select-sm border-0 bg-light" onchange="updateTarefa(<?php echo $t['id']; ?>, 'prioridade', this.value)">
                            <option value="Baixa" <?php echo $t['prioridade']=='Baixa'?'selected':''; ?>>Baixa</option>
                            <option value="Média" <?php echo $t['prioridade']=='Média'?'selected':''; ?>>Média</option>
                            <option value="Alta" <?php echo $t['prioridade']=='Alta'?'selected':''; ?>>Alta</option>
                        </select>
                    </td>
                    <td><input type="date" class="form-control form-control-sm border-0 bg-light text-center" value="<?php echo $t['data_fim']; ?>" onchange="updateTarefa(<?php echo $t['id']; ?>, 'data_fim', this.value)"></td>
                    <td class="text-center">
                        <!-- COLUNA OBS: ABRE O POP-UP -->
                        <i class="fas fa-file-signature text-primary fa-lg cursor-pointer" onclick="abrirEditor(<?php echo $t['id']; ?>, '<?php echo addslashes($t['titulo']); ?>')"></i>
                    </td>
                    <td class="text-center"><a href="projetos_acoes.php?excluir_tarefa=<?php echo $t['id']; ?>" class="text-danger opacity-25"><i class="fas fa-times"></i></a></td>
                </tr>
                <?php endwhile; ?>
                <tr>
                    <td></td>
                    <td colspan="6" class="p-2">
                        <input type="text" class="form-control form-control-sm border-0 text-primary fw-bold" placeholder="+ Adicionar Tarefa (Enter)" onkeypress="if(event.key==='Enter') addTarefa(this.value, <?php echo $g['id']; ?>)">
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
            <div class="loader-overlay" id="editorLoader"><div class="spinner-border text-primary mb-2"></div><div class="fw-bold">Sincronizando...</div></div>
            <div class="modal-header bg-dark text-white border-0">
                <h5 class="modal-title" id="modalTitulo">Anotações da Tarefa</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light"><div id="editor-evidencias" contenteditable="true"></div></div>
            <div class="modal-footer bg-white border-0">
                <button class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Fechar</button>
                <button class="btn btn-success rounded-pill px-5 fw-bold shadow" id="btnSalvar" onclick="salvarEvidencias()">SALVAR ANOTAÇÕES</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL NOVO GRUPO -->
<div class="modal fade" id="modalNovoGrupo" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered"><form action="projetos_acoes.php" method="POST" class="modal-content border-0 shadow-lg">
        <div class="modal-header"><h5>Novo Grupo</h5></div>
        <div class="modal-body p-4">
            <input type="hidden" name="acao" value="novo_grupo"><input type="hidden" name="quadro_id" value="<?php echo $id_quadro; ?>">
            <input type="text" name="nome_groupo" class="form-control mb-3" placeholder="Nome (Ex: Sprint 2)" required>
            <label class="small fw-bold">COR:</label><input type="color" name="cor" class="form-control form-control-color w-100" value="#1a73e8">
        </div>
        <div class="modal-footer"><button type="submit" class="btn btn-primary w-100 rounded-pill py-2 fw-bold">CRIAR</button></div>
    </form></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let currentTaskId = 0;
const modalObj = new bootstrap.Modal(document.getElementById('modalEditor'));

function updateGrupo(id, campo, valor) {
    const fd = new FormData(); fd.append('acao', 'atualizar_campo_grupo'); fd.append('id', id); fd.append('campo', campo); fd.append('valor', valor);
    fetch('projetos_acoes.php', { method: 'POST', body: fd }).then(() => { if(campo === 'cor') location.reload(); });
}

function updateTarefa(id, campo, valor) {
    const fd = new FormData(); fd.append('acao', 'atualizar_campo_tarefa'); fd.append('id', id); fd.append('campo', campo); fd.append('valor', valor);
    fetch('projetos_acoes.php', { method: 'POST', body: fd });
}

function addTarefa(titulo, grupo_id) {
    if(!titulo) return;
    const fd = new FormData(); fd.append('acao', 'nova_tarefa'); fd.append('titulo', titulo); fd.append('grupo_id', grupo_id); fd.append('quadro_id', <?php echo $id_quadro; ?>);
    fetch('projetos_acoes.php', { method: 'POST', body: fd }).then(() => location.reload());
}

function abrirEditor(id, titulo) {
    currentTaskId = id;
    document.getElementById('modalTitulo').innerText = titulo;
    document.getElementById('editor-evidencias').innerHTML = "Carregando...";
    modalObj.show();
    fetch('projetos_acoes.php?acao=get_evidencia&id='+id).then(r => r.text()).then(html => {
        document.getElementById('editor-evidencias').innerHTML = html;
    });
}

function salvarEvidencias() {
    document.getElementById('editorLoader').style.display = 'flex';
    document.getElementById('btnSalvar').disabled = true;
    const fd = new FormData();
    fd.append('acao', 'salvar_evidencia');
    fd.append('id', currentTaskId);
    fd.append('conteudo', document.getElementById('editor-evidencias').innerHTML);
    fetch('projetos_acoes.php', { method: 'POST', body: fd }).then(() => {
        document.getElementById('editorLoader').style.display = 'none';
        document.getElementById('btnSalvar').disabled = false;
        alert("Salvo com sucesso!");
    });
}

document.getElementById('editor-evidencias').addEventListener('paste', function(e) {
    const items = (e.clipboardData || e.originalEvent.clipboardData).items;
    for (let index in items) {
        const item = items[index];
        if (item.kind === 'file') {
            e.preventDefault();
            const blob = item.getAsFile();
            const reader = new FileReader();
            reader.onload = function(event) {
                const img = document.createElement('img');
                img.src = event.target.result;
                document.getElementById('editor-evidencias').appendChild(img);
            };
            reader.readAsDataURL(blob);
        }
    }
});
</script>
</body>
</html>