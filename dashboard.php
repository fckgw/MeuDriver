<?php
/**
 * SISTEMA DE DRIVE PROFISSIONAL - DASHBOARD
 * Local: driverbds.tecnologia.ws
 */

session_start();

// 1. Verificação de Segurança: Usuário está logado?
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config.php';

// 2. Definições de Variáveis e Filtros
$user_id = $_SESSION['usuario_id'];
$user_nivel = $_SESSION['usuario_nivel']; // 'admin' ou 'usuario'
$pasta_atual = isset($_GET['pasta']) ? (int)$_GET['pasta'] : null;

// 3. Função para Gerar o Caminho das Pastas (Breadcrumbs)
function gerarCaminhoBreadcrumb($pdo, $id, $user_id) {
    $caminho = [];
    while ($id) {
        $stmt = $pdo->prepare("SELECT id, nome, pai_id FROM pastas WHERE id = ? AND usuario_id = ?");
        $stmt->execute([$id, $user_id]);
        $p = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$p) break;
        array_unshift($caminho, $p);
        $id = $p['pai_id'];
    }
    return $caminho;
}

// 4. Buscar Nome da Pasta Atual para o Título
$nome_pasta_exibicao = "Meu Drive";
if ($pasta_atual) {
    $stmtPasta = $pdo->prepare("SELECT nome FROM pastas WHERE id = ? AND usuario_id = ?");
    $stmtPasta->execute([$pasta_atual, $user_id]);
    $nome_pasta_exibicao = $stmtPasta->fetchColumn() ?: "Meu Drive";
}

// 5. Estatísticas de Armazenamento e Quota
$stmtQuota = $pdo->prepare("SELECT quota_limite FROM usuarios WHERE id = ?");
$stmtQuota->execute([$user_id]);
$quota_maxima = $stmtQuota->fetchColumn() ?: 1073741824; // 1GB padrão

$stmtUso = $pdo->prepare("SELECT SUM(tamanho) FROM arquivos WHERE usuario_id = ?");
$stmtUso->execute([$user_id]);
$tamanho_usado = $stmtUso->fetchColumn() ?: 0;

// Cálculo de porcentagem (Se for admin, visualmente mostramos 100% ou fixo)
$porcentagem_uso = ($user_nivel === 'admin') ? 0 : round(($tamanho_usado / $quota_maxima) * 100);

// 6. Preferência de Visualização (Grade ou Lista) via Cookie
$modo_view = isset($_GET['view']) ? $_GET['view'] : (isset($_COOKIE['drive_view_pref']) ? $_COOKIE['drive_view_pref'] : 'grid');
setcookie('drive_view_pref', $modo_view, time() + (86400 * 30), "/");

// 7. Função para Formatação de Tamanho de Arquivo
function converterTamanho($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' bytes';
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Drive Pro - <?php echo htmlspecialchars($nome_pasta_exibicao); ?></title>
    
    <!-- CSS: Bootstrap 5, FontAwesome 6 e Fancybox 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.css">
    
    <style>
        :root { --sidebar-w: 280px; --drive-blue: #1a73e8; }
        body { background-color: #f8f9fa; display: flex; min-height: 100vh; font-family: 'Segoe UI', system-ui, sans-serif; overflow-x: hidden; margin: 0; }
        
        /* Sidebar */
        .sidebar { width: var(--sidebar-w); background: #ffffff; height: 100vh; position: fixed; border-right: 1px solid #dee2e6; z-index: 1000; display: flex; flex-direction: column; transition: 0.3s; }
        .main-content { flex: 1; margin-left: var(--sidebar-w); transition: 0.3s; min-width: 0; }
        
        .nav-link { color: #3c4043; font-weight: 500; padding: 12px 24px; border-radius: 0 30px 30px 0; margin-bottom: 2px; border: none; }
        .nav-link:hover { background-color: #f1f3f4; }
        .nav-link.active { background-color: #e8f0fe; color: var(--drive-blue); }

        /* Itens (Cards) */
        .item-box { background: #ffffff; border: 1px solid #dadce0; border-radius: 12px; transition: 0.2s; position: relative; height: 100%; cursor: pointer; }
        .item-box:hover { box-shadow: 0 1px 3px 1px rgba(60,64,67,0.15); border-color: transparent; }
        .item-box.drag-over { border: 2px dashed var(--drive-blue) !important; background-color: #e8f0fe !important; }

        .file-thumb { height: 140px; display: flex; align-items: center; justify-content: center; background: #f8f9fa; border-radius: 12px 12px 0 0; overflow: hidden; position: relative; }
        .file-thumb img { width: 100%; height: 100%; object-fit: cover; }
        
        /* Seleção */
        .item-checkbox { position: absolute; top: 12px; left: 12px; z-index: 20; width: 18px; height: 18px; cursor: pointer; display: none; }
        .item-box:hover .item-checkbox, .item-checkbox:checked { display: block; }

        /* Barra de Ações em Massa */
        #bulk-bar { display: none; background: var(--drive-blue); color: #fff; padding: 12px 25px; border-radius: 0 0 15px 15px; margin: 0 20px 20px 20px; align-items: center; justify-content: space-between; position: sticky; top: 70px; z-index: 900; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }

        @media (max-width: 992px) {
            .sidebar { left: calc(-1 * var(--sidebar-w)); }
            .sidebar.active { left: 0; }
            .main-content { margin-left: 0; }
        }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar shadow-sm" id="sidebar">
    <div class="p-4"><h4 class="text-primary fw-bold mb-0"><i class="fas fa-cloud me-2"></i>DriveBDS</h4></div>
    
    <!-- INFO USUÁRIO E ÚLTIMO ACESSO -->
    <div class="px-4 py-3 bg-light border-top border-bottom">
        <div class="fw-bold text-dark text-truncate small"><?php echo htmlspecialchars($_SESSION['usuario_nome']); ?></div>
        <div class="text-muted" style="font-size: 10px;">
            <i class="fas fa-history me-1"></i> Acesso: <?php echo $_SESSION['ultimo_acesso_formatado']; ?>
        </div>
    </div>

    <div class="p-3">
        <button class="btn btn-white border shadow-sm w-100 rounded-pill py-2 fw-bold text-start px-3" data-bs-toggle="modal" data-bs-target="#modalUpload">
            <span class="text-primary fs-4 me-2">+</span> Novo Upload
        </button>
    </div>

    <nav class="nav flex-column mb-auto">
        <a class="nav-link <?php echo !$pasta_atual ? 'active' : ''; ?>" href="dashboard.php"><i class="fas fa-home me-3"></i> Meu Drive</a>
        <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#modalPasta"><i class="fas fa-folder-plus me-3"></i> Criar Pasta</a>
        
        <hr class="mx-3">
        
        <?php if($user_nivel === 'admin'): ?>
            <div class="px-4 mb-2 small text-muted fw-bold">GERENCIAMENTO</div>
            <a class="nav-link text-dark fw-bold" href="logs.php">
                <i class="fas fa-list-ul me-3 text-info"></i> CONSULTAR LOGS
            </a>
            <a class="nav-link text-danger fw-bold" href="admin_usuarios.php">
                <i class="fas fa-user-shield me-3"></i> ADMINISTRAÇÃO
            </a>
        <?php endif; ?>
    </nav>

    <!-- PAINEL DE QUOTA / ARMAZENAMENTO -->
    <div class="p-4 border-top">
        <?php if($user_nivel === 'admin'): ?>
            <div class="d-flex justify-content-between mb-1 small fw-bold text-primary">
                <span>Armazenamento</span>
                <span>Ilimitado</span>
            </div>
            <div class="progress mb-2" style="height: 6px;"><div class="progress-bar bg-primary" style="width: 100%"></div></div>
            <div class="text-muted" style="font-size: 11px;">Uso atual: <?php echo converterTamanho($tamanho_usado); ?></div>
        <?php else: ?>
            <div class="d-flex justify-content-between mb-1 small fw-bold"><span>Uso</span><span><?php echo $porcentagem_uso; ?>%</span></div>
            <div class="progress mb-2" style="height: 6px;"><div class="progress-bar bg-primary" style="width: <?php echo $porcentagem_uso; ?>%"></div></div>
            <div class="text-muted small"><?php echo converterTamanho($tamanho_usado); ?> de <?php echo converterTamanho($quota_maxima); ?></div>
        <?php endif; ?>
        <a href="logout.php" class="btn btn-sm btn-outline-danger w-100 rounded-pill mt-3 fw-bold">Sair do Sistema</a>
    </div>
</div>

<!-- CONTEÚDO PRINCIPAL -->
<div class="main-content">
    <nav class="navbar navbar-expand-lg bg-white border-bottom px-4 sticky-top">
        <div class="container-fluid p-0">
            <button class="btn btn-light d-lg-none me-2" onclick="document.getElementById('sidebar').classList.toggle('active')"><i class="fas fa-bars"></i></button>
            
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none">Meu Drive</a></li>
                    <?php 
                    $passos = gerarCaminhoBreadcrumb($pdo, $pasta_atual, $user_id);
                    foreach($passos as $p): 
                    ?>
                        <li class="breadcrumb-item"><a href="dashboard.php?pasta=<?php echo $p['id']; ?>" class="text-decoration-none"><?php echo htmlspecialchars($p['nome']); ?></a></li>
                    <?php endforeach; ?>
                </ol>
            </nav>

            <div class="ms-auto">
                <div class="btn-group shadow-sm">
                    <a href="?pasta=<?php echo $pasta_atual; ?>&view=grid" class="btn btn-sm btn-white border <?php echo $modo_view == 'grid' ? 'active bg-light' : ''; ?>"><i class="fas fa-th-large"></i></a>
                    <a href="?pasta=<?php echo $pasta_atual; ?>&view=list" class="btn btn-sm btn-white border <?php echo $modo_view == 'list' ? 'active bg-light' : ''; ?>"><i class="fas fa-list"></i></a>
                </div>
            </div>
        </div>
    </nav>

    <!-- BARRA DE AÇÕES EM MASSA -->
    <div id="bulk-bar">
        <div class="d-flex align-items-center">
            <button class="btn btn-sm btn-light rounded-pill px-3 me-3" onclick="selecionarTudo()">Selecionar Tudo</button>
            <span id="label-selecionados">0 itens selecionados</span>
        </div>
        <button class="btn btn-sm btn-danger rounded-pill px-4 fw-bold" onclick="excluirMassa()"><i class="fas fa-trash me-2"></i>Excluir Seleção</button>
    </div>

    <div class="p-4">
        <!-- SEÇÃO: PASTAS -->
        <h6 class="text-muted small fw-bold mb-3">PASTAS</h6>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-3 mb-5">
            <?php
            $sqlP = "SELECT * FROM pastas WHERE usuario_id = ? AND " . ($pasta_atual ? "pai_id = $pasta_atual" : "pai_id IS NULL");
            $stmtP = $pdo->prepare($sqlP);
            $stmtP->execute([$user_id]);
            while($folder = $stmtP->fetch()):
            ?>
            <div class="col" draggable="true" ondragstart="iniciarDrag(event, 'pasta', <?php echo $folder['id']; ?>)" ondrop="finalizarDrop(event, <?php echo $folder['id']; ?>)" ondragover="permitirDrop(event)" ondragleave="removerEfeito(event)">
                <div class="item-box p-3 d-flex align-items-center justify-content-between">
                    <a href="dashboard.php?pasta=<?php echo $folder['id']; ?>" class="text-decoration-none text-dark d-flex align-items-center flex-grow-1 overflow-hidden">
                        <i class="fas fa-folder fa-2x text-warning me-3"></i>
                        <span class="text-truncate fw-medium"><?php echo htmlspecialchars($folder['nome']); ?></span>
                    </a>
                    <a href="pastas_acoes.php?del_pasta=<?php echo $folder['id']; ?>" class="text-danger opacity-25" onclick="return confirm('Excluir pasta e arquivos?')"><i class="fas fa-trash-alt"></i></a>
                </div>
            </div>
            <?php endwhile; ?>
        </div>

        <!-- SEÇÃO: ARQUIVOS -->
        <h6 class="text-muted small fw-bold mb-3">ARQUIVOS</h6>
        <?php if($modo_view == 'grid'): ?>
            <div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-5 row-cols-xl-6 g-3">
                <?php
                $sqlA = "SELECT * FROM arquivos WHERE usuario_id = ? AND " . ($pasta_atual ? "pasta_id = $pasta_atual" : "pasta_id IS NULL") . " ORDER BY id DESC";
                $stmtA = $pdo->prepare($sqlA);
                $stmtA->execute([$user_id]);
                while($a = $stmtA->fetch()):
                    $ext = strtolower(pathinfo($a['nome_original'], PATHINFO_EXTENSION));
                    $path = "uploads/user_" . $user_id . "/" . $a['nome_sistema'];
                    $isImg = in_array($ext, ['jpg','jpeg','png','webp','gif']);
                    $url_completa = "https://" . $_SERVER['HTTP_HOST'] . "/" . $path;
                    
                    $link_view = $path; $f_type = 'gallery';
                    if(in_array($ext, ['doc','docx','xls','xlsx','pdf'])) {
                        $link_view = "https://docs.google.com/viewer?url=" . urlencode($url_completa) . "&embedded=true";
                        $f_type = 'iframe';
                    }
                ?>
                <div class="col" draggable="true" ondragstart="iniciarDrag(event, 'arquivo', <?php echo $a['id']; ?>)">
                    <div class="item-box overflow-hidden">
                        <input type="checkbox" class="item-checkbox form-check-input" value="<?php echo $a['id']; ?>" onclick="contarCheck()">
                        <div class="file-thumb">
                            <a href="<?php echo $link_view; ?>" data-fancybox="<?php echo ($f_type === 'gallery' ? 'gallery' : ''); ?>" data-type="<?php echo ($f_type === 'iframe' ? 'iframe' : ''); ?>" data-caption="<?php echo htmlspecialchars($a['nome_original']); ?>">
                                <?php if($isImg): ?><img src="<?php echo $path; ?>" alt="Preview">
                                <?php elseif(in_array($ext, ['mp4','webm'])): ?><i class="fas fa-file-video fa-3x text-warning"></i><i class="fas fa-play position-absolute text-white"></i>
                                <?php elseif($ext == 'pdf'): ?><i class="fas fa-file-pdf fa-3x text-danger"></i>
                                <?php else: ?><i class="fas fa-file-alt fa-3x text-primary"></i><?php endif; ?>
                            </a>
                        </div>
                        <div class="p-2 border-top bg-white d-flex align-items-center justify-content-between">
                            <div class="text-truncate small fw-medium" title="<?php echo htmlspecialchars($a['nome_original']); ?>"><?php echo htmlspecialchars($a['nome_original']); ?></div>
                            <div class="dropdown">
                                <button class="btn btn-link btn-sm text-muted p-0" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                                    <li><a class="dropdown-item" href="<?php echo $path; ?>" download><i class="fas fa-download me-2"></i> Baixar</a></li>
                                    <li><a class="dropdown-item text-danger" href="excluir.php?id=<?php echo $a['id']; ?>" onclick="return confirm('Excluir arquivo?')"><i class="fas fa-trash-alt me-2"></i> Excluir</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <!-- VISUALIZAÇÃO EM LISTA -->
            <div class="bg-white border rounded shadow-sm overflow-hidden">
                <div class="d-flex border-bottom p-2 fw-bold small bg-light text-muted">
                    <div class="flex-grow-1 ps-5">Nome do Arquivo</div>
                    <div style="width: 180px;">Data de Criação</div>
                    <div style="width: 100px;">Tamanho</div>
                </div>
                <?php $stmtA->execute(); while($a = $stmtA->fetch()): ?>
                <div class="list-row" draggable="true" ondragstart="iniciarDrag(event, 'arquivo', <?php echo $a['id']; ?>)">
                    <input type="checkbox" class="item-checkbox form-check-input me-3" value="<?php echo $a['id']; ?>" onclick="contarCheck()">
                    <div class="flex-grow-1 d-flex align-items-center">
                        <i class="fas fa-file-alt text-primary me-3"></i>
                        <span class="small fw-medium"><?php echo htmlspecialchars($a['nome_original']); ?></span>
                    </div>
                    <div class="small text-muted" style="width: 180px;"><?php echo date('d/m/Y H:i', strtotime($a['data_upload'])); ?></div>
                    <div class="small text-muted" style="width: 100px;"><?php echo converterTamanho($a['tamanho']); ?></div>
                </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- MODAL: UPLOAD -->
<div class="modal fade" id="modalUpload" tabindex="-1"><div class="modal-dialog"><div class="modal-content p-4 text-center border-0 shadow-lg">
    <h5 class="fw-bold mb-3">Upload de Arquivos</h5>
    <div class="p-5 border border-2 border-dashed rounded-4 bg-light mb-3">
        <i class="fas fa-cloud-upload-alt fa-3x text-primary mb-3"></i>
        <input type="file" id="fileIn" class="form-control" multiple>
    </div>
    <input type="hidden" id="pasta_id_hidden" value="<?php echo $pasta_atual; ?>">
    <button onclick="executarUpload()" class="btn btn-primary w-100 rounded-pill py-2 fw-bold">ENVIAR AGORA</button>
</div></div></div>

<!-- MODAL: PROGRESSO -->
<div class="modal fade" id="modalProgresso" data-bs-backdrop="static"><div class="modal-dialog modal-dialog-centered"><div class="modal-content p-4 shadow-lg text-center border-0">
    <h5 class="fw-bold mb-3" id="msgStatus">Enviando arquivos...</h5>
    <div class="progress mb-2" style="height: 15px; border-radius: 10px;"><div id="barP" class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%"></div></div>
    <div id="txtP" class="fw-bold text-primary">0%</div>
</div></div></div>

<!-- MODAL: NOVA PASTA -->
<div class="modal fade" id="modalPasta" tabindex="-1"><div class="modal-dialog"><form action="pastas_acoes.php" method="POST" class="modal-content border-0 shadow-lg">
    <div class="modal-header border-0"><h5>Nova Pasta</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <input type="text" name="nome_pasta" class="form-control form-control-lg" placeholder="Nome da pasta" required autofocus>
        <input type="hidden" name="pai_id" value="<?php echo $pasta_atual; ?>">
    </div>
    <div class="modal-footer border-0"><button type="submit" class="btn btn-primary px-5 rounded-pill fw-bold">CRIAR</button></div>
</form></div></div>

<!-- SCRIPTS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.umd.js"></script>
<script>
    Fancybox.bind("[data-fancybox]", {});

    // DRAG & DROP LOGIC
    function iniciarDrag(e, t, id) { e.dataTransfer.setData("t", t); e.dataTransfer.setData("id", id); }
    function permitirDrop(e) { e.preventDefault(); e.currentTarget.classList.add('drag-over'); }
    function removerEfeito(e) { e.currentTarget.classList.remove('drag-over'); }
    function finalizarDrop(e, pDest) {
        e.preventDefault(); e.currentTarget.classList.remove('drag-over');
        const t = e.dataTransfer.getData("t"); const id = e.dataTransfer.getData("id");
        if(t === 'pasta' && id == pDest) return;
        const url = t === 'arquivo' ? `pastas_acoes.php?mover_arq=${id}&para_pasta=${pDest}` : `pastas_acoes.php?mover_pasta=${id}&para_pasta=${pDest}`;
        fetch(url, { headers: {'X-Requested-With': 'XMLHttpRequest'} }).then(() => location.reload());
    }

    // MULTI-SELEÇÃO
    function contarCheck() {
        const n = document.querySelectorAll('.item-checkbox:checked').length;
        document.getElementById('bulk-bar').style.display = n > 0 ? 'flex' : 'none';
        document.getElementById('label-selecionados').innerText = n + ' itens selecionados';
    }
    function selecionarTudo() {
        const c = document.querySelectorAll('.item-checkbox'); const all = Array.from(c).every(x => x.checked);
        c.forEach(x => x.checked = !all); contarCheck();
    }
    function excluirMassa() {
        if(!confirm("Deseja excluir os itens selecionados permanentemente?")) return;
        const ids = Array.from(document.querySelectorAll('.item-checkbox:checked')).map(x => x.value);
        const fd = new FormData(); ids.forEach(id => fd.append('ids[]', id));
        fetch('excluir_multiplos.php', { method: 'POST', body: fd }).then(() => location.reload());
    }

    // UPLOAD AJAX
    function executarUpload() {
        const fi = document.getElementById('fileIn'); if(!fi.files.length) return;
        const modal = new bootstrap.Modal(document.getElementById('modalProgresso'));
        const bar = document.getElementById('barP'); const txt = document.getElementById('txtP');
        const status = document.getElementById('msgStatus');
        modal.show();
        const fd = new FormData(); for(let f of fi.files) fd.append('arquivos[]', f);
        fd.append('pasta_id', document.getElementById('pasta_id_hidden').value);
        const xhr = new XMLHttpRequest();
        xhr.upload.onprogress = (e) => {
            const p = Math.round((e.loaded / e.total) * 100);
            bar.style.width = p+'%'; txt.innerText = p+'%';
            if(p === 100) status.innerText = "Processando no servidor...";
        };
        xhr.onload = () => {
            const res = JSON.parse(xhr.responseText);
            if(res.status === 'error') { alert(res.message); modal.hide(); } else { location.reload(); }
        };
        xhr.open("POST", "upload.php"); xhr.send(fd);
    }
</script>
</body>
</html>