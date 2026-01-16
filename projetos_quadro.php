<?php
/**
 * BDSoft Workspace - VISUALIZAÇÃO DO QUADRO (VERSÃO ESTÁVEL)
 * Local: projetos_quadro.php
 */

session_start();
require_once 'config.php';

// 1. Verificação de Sessão
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$user_id_logado = $_SESSION['usuario_id'];
$id_quadro = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// 2. Validar Existência e Permissão de Acesso ao Quadro
$stmt_check = $pdo->prepare("SELECT * FROM quadros_projetos WHERE id = ?");
$stmt_check->execute([$id_quadro]);
$quadro = $stmt_check->fetch();

if (!$quadro) {
    die("<div style='padding:100px; text-align:center; font-family:sans-serif;'><h2>❌ Quadro não encontrado ou removido</h2><a href='projetos_home.php' class='btn btn-primary'>Voltar para Home</a></div>");
}

// Verificação de Privacidade: Se for Privado, somente criador ou membros acessam (Admin sempre acessa)
if ($quadro['tipo'] === 'Privado' && $_SESSION['usuario_nivel'] !== 'admin' && $quadro['usuario_id'] != $user_id_logado) {
    $stmt_membro = $pdo->prepare("SELECT 1 FROM quadro_membros WHERE quadro_id = ? AND usuario_id = ?");
    $stmt_membro->execute([$id_quadro, $user_id_logado]);
    if (!$stmt_membro->fetch()) {
        die("<div style='padding:100px; text-align:center; font-family:sans-serif;'><h2>🔒 Acesso Restrito</h2><p>Este workspace é privado.</p><a href='projetos_home.php'>Voltar</a></div>");
    }
}

// 3. Carregar Status Disponíveis para este Quadro (Dinamismo de Cores)
$stmt_st = $pdo->prepare("SELECT * FROM quadros_status WHERE quadro_id = ? ORDER BY id ASC");
$stmt_st->execute([$id_quadro]);
$lista_status = $stmt_st->fetchAll(PDO::FETCH_ASSOC);

// 4. Carregar Grupos de Tarefas
$stmt_gr = $pdo->prepare("SELECT * FROM projetos_grupos WHERE quadro_id = ? ORDER BY id ASC");
$stmt_gr->execute([$id_quadro]);
$grupos = $stmt_gr->fetchAll(PDO::FETCH_ASSOC);

/**
 * Função Auxiliar para buscar a cor do status dinamicamente
 */
function obterCorStatus($lista, $status_id) {
    foreach ($lista as $item) {
        if ($item['id'] == $status_id) return $item['cor'];
    }
    return "#c4c4c4"; // Cor padrão caso não encontre
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($quadro['nome']); ?> - BDSoft Workspace</title>
    
    <!-- CSS: Bootstrap, FontAwesome, Fancybox -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root { --monday-bg: #f5f6f8; --primary-blue: #1a73e8; }
        body { background-color: var(--monday-bg); font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; margin: 0; }
        
        .nav-board { background: #fff; border-bottom: 1px solid #dee2e6; padding: 12px 30px; position: sticky; top: 0; z-index: 100; }
        
        /* Estilo Tabela Monday */
        .table-monday { width: 100%; background: #fff; border-radius: 8px; border-collapse: collapse; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 30px; }
        .table-monday th { font-size: 12px; color: #676879; padding: 12px; border-bottom: 1px solid #eee; font-weight: 400; text-align: left; }
        .task-row { border-bottom: 1px solid #eee; transition: 0.2s; }
        .task-row:hover { background-color: #f8fafc; }

        /* Estilo do Status Select */
        .status-select { 
            border: none; color: white; font-weight: bold; border-radius: 4px; padding: 8px; 
            width: 100%; cursor: pointer; appearance: none; text-align-last: center; outline: none; 
            transition: 0.3s;
        }

        /* Editor de Evidências */
        #editor-evidencias { min-height: 450px; border: 1px solid #ddd; padding: 25px; background: #fff; outline: none; border-radius: 12px; overflow-y: auto; font-size: 1.1rem; }
        #editor-evidencias img { max-width: 100%; border-radius: 10px; margin: 15px 0; border: 2px solid #f1f1f1; box-shadow: 0 5px 15px rgba(0,0,0,0.08); }

        .loader-overlay { 
            display:none; position:absolute; top:0; left:0; width:100%; height:100%; 
            background:rgba(255,255,255,0.85); z-index:2000; align-items:center; justify-content:center; 
            flex-direction:column; border-radius: 15px; 
        }
        .cursor-pointer { cursor: pointer; }
    </style>
</head>
<body>

<nav class="nav-board d-flex justify-content-between align-items-center shadow-sm">
    <div class="d-flex align-items-center">
        <a href="projetos_home.php" class="btn btn-sm btn-light border rounded-circle me-3" title="Voltar"><i class="fas fa-arrow-left"></i></a>
        <h4 class="fw-bold mb-0 text-dark"><?php echo htmlspecialchars($quadro['nome']); ?></h4>
        <div class="ms-3">
            <?php if($quadro['tipo'] === 'Privado'): ?>
                <span class="badge bg-danger rounded-pill"><i class="fas fa-lock me-1"></i> Privado</span>
            <?php else: ?>
                <span class="badge bg-success rounded-pill"><i class="fas fa-globe me-1"></i> Público</span>
            <?php endif; ?>
        </div>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold" data-bs-toggle="modal" data-bs-target="#modalMembros">
            <i class="fas fa-users me-1"></i> EQUIPE
        </button>
        <a href="index.php" class="btn btn-primary btn-sm rounded-pill px-4 fw-bold shadow-sm">PORTAL</a>
    </div>
</nav>

<div class="container-fluid p-4">
    <?php foreach($grupos as $grupo): ?>
    <div class="mb-5">
        <h5 class="fw-bold mb-3" style="color: <?php echo $grupo['cor']; ?>">
            <i class="fas fa-caret-down me-2"></i> <?php echo htmlspecialchars($grupo['nome']); ?>
        </h5>
        
        <table class="table-monday">
            <thead>
                <tr>
                    <th style="width: 40px;"></th>
                    <th>ITEM / TAREFA</th>
                    <th style="width: 200px;" class="text-center">STATUS</th>
                    <th style="width: 150px;" class="text-center">PRIORIDADE</th>
                    <th style="width: 160px;" class="text-center">DATA LIMITE</th>
                    <th style="width: 80px;" class="text-center">OBS.</th>
                    <th style="width: 50px;"></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $stmt_tarefas = $pdo->prepare("SELECT * FROM tarefas_projetos WHERE grupo_id = ? ORDER BY id ASC");
                $stmt_tarefas->execute([$grupo['id']]);
                while($task = $stmt_tarefas->fetch()):
                    $cor_bg_status = obterCorStatus($lista_status, $task['status_id']);
                ?>
                <tr class="task-row">
                    <td class="text-center"><input type="checkbox" class="form-check-input"></td>
                    <td class="p-0">
                        <input type="text" class="form-control form-control-sm border-0 bg-transparent fw-medium px-3" 
                               style="height: 45px;"
                               value="<?php echo htmlspecialchars($task['titulo']); ?>" 
                               onblur="atualizarTarefaNoBanco(<?php echo $task['id']; ?>, 'titulo', this.value)">
                    </td>
                    <td class="p-2">
                        <select class="status-select" 
                                style="background-color: <?php echo $cor_bg_status; ?>" 
                                onchange="atualizarTarefaNoBanco(<?php echo $task['id']; ?>, 'status_id', this.value); alterarCorSelect(this);">
                            <?php foreach($lista_status as $st_op): ?>
                                <option value="<?php echo $st_op['id']; ?>" 
                                        data-color="<?php echo $st_op['cor']; ?>" 
                                        <?php echo ($task['status_id'] == $st_op['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($st_op['label']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td class="px-3">
                        <select class="form-select form-select-sm border-0 bg-light fw-bold text-muted" onchange="atualizarTarefaNoBanco(<?php echo $task['id']; ?>, 'prioridade', this.value)">
                            <option value="Baixa" <?php echo $task['prioridade'] === 'Baixa' ? 'selected' : ''; ?>>Baixa</option>
                            <option value="Média" <?php echo $task['prioridade'] === 'Média' ? 'selected' : ''; ?>>Média</option>
                            <option value="Alta" <?php echo $task['prioridade'] === 'Alta' ? 'selected' : ''; ?>>Alta</option>
                            <option value="Crítica" <?php echo $task['prioridade'] === 'Crítica' ? 'selected' : ''; ?>>Crítica</option>
                        </select>
                    </td>
                    <td class="text-center">
                        <input type="date" class="form-control form-control-sm border-0 bg-light text-center" 
                               value="<?php echo $task['data_fim']; ?>" 
                               onchange="atualizarTarefaNoBanco(<?php echo $task['id']; ?>, 'data_fim', this.value)">
                    </td>
                    <td class="text-center">
                        <i class="fas fa-file-signature text-primary fa-lg cursor-pointer" 
                           onclick="abrirEditorEvidencias(<?php echo $task['id']; ?>, '<?php echo addslashes($task['titulo']); ?>')"
                           title="Anotar / Prints"></i>
                    </td>
                    <td class="text-center">
                        <a href="projetos_acoes.php?excluir_tarefa=<?php echo $task['id']; ?>" class="text-danger opacity-25" onclick="return confirm('Deseja excluir permanentemente esta tarefa?')">
                            <i class="fas fa-times"></i>
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
                <!-- Linha de Adição Rápida de Tarefa -->
                <tr>
                    <td></td>
                    <td colspan="6" class="p-2">
                        <input type="text" class="form-control form-control-sm border-0 text-primary fw-bold px-3" 
                               placeholder="+ Adicionar nova tarefa e aperte Enter..." 
                               onkeypress="if(event.key === 'Enter') criarNovaTarefa(this.value, <?php echo $grupo['id']; ?>)">
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <?php endforeach; ?>
</div>

<!-- MODAL: EDITOR DE EVIDÊNCIAS (ESTILO WORD / MONDAY) -->
<div class="modal fade" id="modalEditor" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg position-relative">
            <!-- Loader de Processamento -->
            <div class="loader-overlay" id="editorLoader">
                <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;"></div>
                <div class="fw-bold text-primary">Sincronizando com o Banco de Dados...</div>
            </div>

            <div class="modal-header bg-dark text-white border-0">
                <h5 class="modal-title" id="modalTituloTarefa">Anotações e Evidências</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-light">
                <div id="editor-evidencias" contenteditable="true" placeholder="Digite seu relatório ou cole (Ctrl+V) capturas de tela..."></div>
            </div>
            <div class="modal-footer bg-white border-0">
                <button class="btn btn-outline-secondary rounded-pill px-4 fw-bold" data-bs-dismiss="modal">CANCELAR</button>
                <button class="btn btn-success rounded-pill px-5 fw-bold shadow" id="btnSalvarNotas" onclick="salvarConteudoEditor()">
                    <i class="fas fa-save me-2"></i>SALVAR AGORA
                </button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: GESTÃO DE MEMBROS -->
<div class="modal fade" id="modalMembros" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header"><h5>Membros da Equipe</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <label class="small fw-bold mb-2">ADICIONAR COLABORADOR AO QUADRO:</label>
                <div class="input-group mb-4">
                    <select id="select_convite" class="form-select">
                        <option value="">Selecione um usuário ativo...</option>
                        <?php 
                        $stmt_users = $pdo->prepare("SELECT id, nome FROM usuarios WHERE id NOT IN (SELECT usuario_id FROM quadro_membros WHERE quadro_id = ?) AND status = 'ativo'");
                        $stmt_users->execute([$id_quadro]);
                        while($u = $stmt_users->fetch()) {
                            echo "<option value='{$u['id']}'>{$u['nome']}</option>";
                        }
                        ?>
                    </select>
                    <button class="btn btn-primary" onclick="executarConvite()">Convidar</button>
                </div>
                
                <h6 class="fw-bold mb-3 small text-muted">COLABORADORES ATUAIS:</h6>
                <div class="list-group list-group-flush border rounded">
                    <?php 
                    $stmt_m = $pdo->prepare("SELECT u.id, u.nome, u.usuario FROM usuarios u INNER JOIN quadro_membros qm ON u.id = qm.usuario_id WHERE qm.quadro_id = ?");
                    $stmt_m->execute([$id_quadro]);
                    while($m = $stmt_m->fetch()): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <span class="fw-bold"><?php echo htmlspecialchars($m['nome']); ?></span><br>
                                <small class="text-muted"><?php echo $m['usuario']; ?></small>
                            </div>
                            <?php if($m['id'] != $quadro['usuario_id']): ?>
                                <button class="btn btn-sm text-danger" onclick="removerAcessoMembro(<?php echo $m['id']; ?>)" title="Remover acesso"><i class="fas fa-user-minus"></i></button>
                            <?php else: ?>
                                <span class="badge bg-light text-dark border">Dono</span>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let idTarefaAtiva = 0;
const modalAnotacoes = new bootstrap.Modal(document.getElementById('modalEditor'));

/**
 * Atualiza campos simples (titulo, status, prioridade, data) via AJAX
 */
function atualizarTarefaNoBanco(id, campo, valor) {
    const dados = new FormData();
    dados.append('acao', 'atualizar_campo_tarefa');
    dados.append('id', id);
    dados.append('campo', campo);
    dados.append('valor', valor);

    fetch('projetos_acoes.php', { 
        method: 'POST', 
        body: dados 
    }).then(resposta => {
        if(!resposta.ok) alert("Erro ao sincronizar com o servidor.");
    });
}

/**
 * Altera a cor de fundo do select de status dinamicamente ao escolher
 */
function alterarCorSelect(elemento) {
    const cor = elemento.options[elemento.selectedIndex].getAttribute('data-color');
    elemento.style.backgroundColor = cor;
}

/**
 * Adiciona uma nova tarefa ao grupo (Acionada pelo Enter)
 */
function criarNovaTarefa(titulo, grupo_id) {
    if(!titulo.trim()) return;
    
    const dados = new FormData();
    dados.append('acao', 'nova_tarefa');
    dados.append('titulo', titulo);
    dados.append('grupo_id', grupo_id);
    dados.append('quadro_id', <?php echo $id_quadro; ?>); // Correção de segurança e ID

    fetch('projetos_acoes.php', { 
        method: 'POST', 
        body: dados 
    }).then(resposta => {
        if(resposta.ok) location.reload();
        else alert("Falha ao criar tarefa.");
    });
}

/**
 * Abre o editor rico (Word Style) para anotações e prints
 */
function abrirEditorEvidencias(id, titulo) {
    idTarefaAtiva = id;
    document.getElementById('modalTituloTarefa').innerText = "Tarefa: " + titulo;
    document.getElementById('editor-evidencias').innerHTML = "<div class='text-center p-5'><div class='spinner-border text-primary'></div></div>";
    
    fetch('projetos_acoes.php?acao=get_evidencia&id=' + id)
    .then(r => r.text())
    .then(html => {
        document.getElementById('editor-evidencias').innerHTML = html;
        modalAnotacoes.show();
    });
}

/**
 * Salva o conteúdo do editor contenteditable no banco (incluindo Base64 das fotos coladas)
 */
function salvarConteudoEditor() {
    const loader = document.getElementById('editorLoader');
    const btn = document.getElementById('btnSalvarNotas');
    
    loader.style.display = 'flex';
    btn.disabled = true;

    const dados = new FormData();
    dados.append('acao', 'salvar_evidencia');
    dados.append('id', idTarefaAtiva);
    dados.append('conteudo', document.getElementById('editor-evidencias').innerHTML);

    fetch('projetos_acoes.php', { 
        method: 'POST', 
        body: dados 
    }).then(() => {
        loader.style.display = 'none';
        btn.disabled = false;
        modalAnotacoes.hide();
    }).catch(() => {
        alert("Erro de conexão.");
        loader.style.display = 'none';
        btn.disabled = false;
    });
}

/**
 * Função para convidar membros
 */
function executarConvite() {
    const uId = document.getElementById('select_convite').value;
    if(!uId) return;
    const dados = new FormData();
    dados.append('acao', 'add_membro');
    dados.append('quadro_id', <?php echo $id_quadro; ?>);
    dados.append('usuario_id', uId);
    fetch('projetos_acoes.php', { method: 'POST', body: dados }).then(() => location.reload());
}

/**
 * Função para remover membros
 */
function removerAcessoMembro(uId) {
    if(!confirm("Remover acesso deste colaborador?")) return;
    const dados = new FormData();
    dados.append('acao', 'remover_membro');
    dados.append('quadro_id', <?php echo $id_quadro; ?>);
    dados.append('usuario_id', uId);
    fetch('projetos_acoes.php', { method: 'POST', body: dados }).then(() => location.reload());
}

/**
 * LÓGICA DE CAPTURA DE PRINT (CTRL+V)
 * Bloqueia a duplicação nativa e insere apenas o Base64 gerado pelo JS
 */
document.getElementById('editor-evidencias').addEventListener('paste', function(evento) {
    const itens = (evento.clipboardData || evento.originalEvent.clipboardData).items;
    for (let i in itens) {
        const item = itens[i];
        if (item.kind === 'file') {
            evento.preventDefault(); // MATA A DUPLICAÇÃO
            const arquivoBlob = item.getAsFile();
            const leitor = new FileReader();
            leitor.onload = function(e) {
                const imagem = document.createElement('img');
                imagem.src = e.target.result;
                document.getElementById('editor-evidencias').appendChild(imagem);
            };
            leitor.readAsDataURL(arquivoBlob);
        }
    }
});
</script>
</body>
</html>