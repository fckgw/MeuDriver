<?php
/**
 * BDSoft Workspace - DASHBOARD DEFINITIVO (FULL VERSION)
 * Segurança: Logout Automático após 3 minutos de inatividade.
 * Funcionalidades: Mover, Renomear, Excluir, Compartilhar, Upload AJAX, DOWNLOAD.
 */

session_start();

// 1. Verificação de Segurança e Logout por Inatividade (PHP)
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

// Define o tempo limite de inatividade em segundos (3 minutos = 180s)
$tempo_limite = 180; 
if (isset($_SESSION['ultima_atividade']) && (time() - $_SESSION['ultima_atividade'] > $tempo_limite)) {
    session_unset();
    session_destroy();
    header("Location: login.php?msg=Sua sessao expirou por inatividade.");
    exit;
}
$_SESSION['ultima_atividade'] = time(); // Atualiza o tempo da última atividade

require_once 'config.php';

$user_id = $_SESSION['usuario_id'];
$user_nivel = $_SESSION['usuario_nivel']; 
$pasta_atual = isset($_GET['pasta']) ? (int)$_GET['pasta'] : null;

// Função Breadcrumbs (Trilha)
function gerarCaminhoTrilha($pdo, $id, $user_id) {
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

// Estatísticas de Quota
$stmtQuota = $pdo->prepare("SELECT espaco_gb, nivel FROM usuarios WHERE id = ?");
$stmtQuota->execute([$user_id]);
$dados_user = $stmtQuota->fetch(PDO::FETCH_ASSOC);

$is_admin = (strtolower($dados_user['nivel'] ?? '') === 'admin');
$quota_gb = ($is_admin) ? 9999 : ((!empty($dados_user['espaco_gb'])) ? (int)$dados_user['espaco_gb'] : 5); 
$quota_maxima_bytes = $quota_gb * 1073741824; 

$stmtUso = $pdo->prepare("SELECT SUM(tamanho) FROM arquivos WHERE usuario_id = ?");
$stmtUso->execute([$user_id]);
$tamanho_usado = (float)$stmtUso->fetchColumn() ?: 0;

$porcentagem_uso = ($quota_maxima_bytes > 0) ? round(($tamanho_usado / $quota_maxima_bytes) * 100) : 0;
if ($porcentagem_uso > 100) $porcentagem_uso = 100;
$cor_barra = ($porcentagem_uso > 90) ? 'bg-danger' : (($porcentagem_uso > 75) ? 'bg-warning' : 'bg-primary');

$nome_pasta_titulo = "Meu Drive";
if ($pasta_atual) {
    $stmtN = $pdo->prepare("SELECT nome FROM pastas WHERE id = ? AND usuario_id = ?");
    $stmtN->execute([$pasta_atual, $user_id]);
    $nome_pasta_titulo = $stmtN->fetchColumn() ?: "Meu Drive";
}

function formatarBytes($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' bytes';
}

$modo_view = isset($_GET['view']) ? $_GET['view'] : (isset($_COOKIE['view_pref']) ? $_COOKIE['view_pref'] : 'grid');
setcookie('view_pref', $modo_view, time() + (86400 * 30), "/");
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workspace Drive - <?php echo htmlspecialchars($nome_pasta_titulo); ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.css">
    
    <style>
        :root { --sidebar-w: 280px; --primary-blue: #1a73e8; }
        body { background-color: #f8f9fa; min-height: 100vh; font-family: 'Segoe UI', system-ui, sans-serif; margin: 0; display: flex; }
        
        .sidebar { width: var(--sidebar-w); background: #fff; height: 100vh; position: fixed; left: 0; top: 0; border-right: 1px solid #dee2e6; z-index: 1050; display: flex; flex-direction: column; transition: transform 0.3s ease; }
        .main-content { flex: 1; margin-left: var(--sidebar-w); min-width: 0; transition: margin-left 0.3s ease; width: 100%; }
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); z-index: 1040; }

        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .sidebar-overlay.show { display: block; }
        }

        .nav-link { color: #3c4043; font-weight: 500; padding: 12px 24px; border-radius: 0 30px 30px 0; border: none; }
        .nav-link.active { background-color: #e8f0fe; color: var(--primary-blue); }
        
        .item-box { background: #fff; border: 1px solid #dadce0; border-radius: 12px; cursor: pointer; transition: 0.2s; position: relative; height: 100%; }
        .item-box:hover { box-shadow: 0 1px 4px rgba(60,64,67,0.3); }
        .item-box.drag-over { border: 2px dashed var(--primary-blue); background: #e8f0fe; }

        .file-thumb { height: 120px; display: flex; align-items: center; justify-content: center; background: #f8f9fa; border-radius: 12px 12px 0 0; font-size: 3rem; position: relative; }
        .file-thumb img { width: 100%; height: 100%; object-fit: cover; border-radius: 12px 12px 0 0; }

        .item-checkbox { position: absolute; top: 12px; left: 12px; z-index: 20; width: 18px; height: 18px; display: none; }
        .item-box:hover .item-checkbox, .item-checkbox:checked { display: block; }

        #bulk-bar { display: none; background: var(--primary-blue); color: #fff; padding: 15px 25px; position: sticky; top: 60px; z-index: 900; border-radius: 0 0 15px 15px; margin: 0 10px; justify-content: space-between; align-items: center; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .brand-logo { color: var(--primary-blue); font-size: 1.4rem; font-weight: 800; }
    </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<!-- SIDEBAR -->
<div class="sidebar shadow-sm" id="sidebar">
    <div class="p-4 d-flex justify-content-between align-items-center">
        <div class="brand-logo"><i class="fas fa-cloud me-2"></i>Workspace <span class="text-dark">Drive</span></div>
        <button class="btn btn-light d-lg-none" onclick="toggleSidebar()"><i class="fas fa-times"></i></button>
    </div>
    
    <div class="p-3">
        <button class="btn btn-primary w-100 rounded-pill py-2 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalUpload">
            <i class="fas fa-cloud-upload-alt me-2"></i> Novo Upload
        </button>
    </div>
    
    <nav class="nav flex-column mb-auto">
        <a class="nav-link <?php echo !$pasta_atual ? 'active' : ''; ?>" href="dashboard.php"><i class="fas fa-hdd me-3"></i> Meu Drive</a>
        <a class="nav-link" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#modalPasta"><i class="fas fa-folder-plus me-3"></i> Nova Pasta</a>
    </nav>

    <div class="p-4 border-top">
        <div class="small fw-bold mb-1 d-flex justify-content-between"><span>Espaço</span><span><?php echo ($is_admin)?'Ilimitado':$porcentagem_uso.'%'; ?></span></div>
        <div class="progress mb-2" style="height: 8px; border-radius: 10px; background: #eee;"><div class="progress-bar <?php echo $cor_barra; ?>" style="width: <?php echo ($is_admin?100:$porcentagem_uso); ?>%"></div></div>
        <div class="text-muted small" style="font-size: 11px;"><?php echo formatarBytes($tamanho_usado); ?> de <?php echo formatarBytes($quota_maxima_bytes); ?></div>
        <a href="logout.php" class="btn btn-sm btn-outline-danger w-100 rounded-pill mt-3 fw-bold">Sair do Sistema</a>
    </div>
</div>

<div class="main-content">
    <nav class="navbar navbar-expand-lg bg-white border-bottom px-3 sticky-top">
        <button class="btn btn-white border me-2 d-lg-none text-primary" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        
        <nav aria-label="breadcrumb" class="flex-grow-1 overflow-hidden">
            <ol class="breadcrumb mb-0 flex-nowrap">
                <li class="breadcrumb-item"><a href="dashboard.php" ondrop="finalizarArraste(event, 0)" ondragover="permitirArraste(event)"><i class="fas fa-home"></i></a></li>
                <?php $passos = gerarCaminhoTrilha($pdo, $pasta_atual, $user_id); foreach($passos as $p): ?>
                    <li class="breadcrumb-item text-truncate"><a href="dashboard.php?pasta=<?php echo $p['id']; ?>" ondrop="finalizarArraste(event, <?php echo $p['id']; ?>)" ondragover="permitirArraste(event)"><?php echo htmlspecialchars($p['nome']); ?></a></li>
                <?php endforeach; ?>
            </ol>
        </nav>
    </nav>

    <div id="bulk-bar" class="shadow">
        <span><b id="label-selecionados">0</b> selecionados</span>
        <div>
            <button class="btn btn-sm btn-light rounded-pill px-3 me-2 fw-bold" onclick="abrirModalMover()"><i class="fas fa-file-export me-1"></i> Mover</button>
            <button class="btn btn-sm btn-danger rounded-pill px-4 fw-bold" onclick="excluirSelecaoMassa()">Excluir</button>
        </div>
    </div>

    <div class="p-3 p-lg-4">
        <h4 class="mb-4 fw-bold text-dark d-flex align-items-center">
            <i class="fas fa-folder-open text-warning me-3"></i> <?php echo htmlspecialchars($nome_pasta_titulo); ?>
        </h4>

        <!-- PASTAS -->
        <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-2 g-lg-3 mb-5">
            <?php
            $stmtP = $pdo->prepare("SELECT * FROM pastas WHERE usuario_id = ? AND " . ($pasta_atual ? "pai_id = $pasta_atual" : "pai_id IS NULL"));
            $stmtP->execute([$user_id]);
            while($f = $stmtP->fetch()):
            ?>
            <div class="col" draggable="true" ondragstart="iniciarArraste(event, 'pasta', <?php echo $f['id']; ?>)" ondrop="finalizarArraste(event, <?php echo $f['id']; ?>)" ondragover="permitirArraste(event)" ondragleave="removerEfeitoArraste(event)">
                <div class="item-box p-3 d-flex align-items-center justify-content-between shadow-sm">
                    <a href="dashboard.php?pasta=<?php echo $f['id']; ?>" class="text-decoration-none text-dark d-flex align-items-center flex-grow-1 overflow-hidden">
                        <i class="fas fa-folder fa-2x text-warning me-3"></i>
                        <span class="text-truncate fw-bold"><?php echo htmlspecialchars($f['nome']); ?></span>
                    </a>
                    <div class="dropdown">
                        <button class="btn btn-link btn-sm text-muted p-0" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v"></i></button>
                        <ul class="dropdown-menu dropdown-menu-end border-0 shadow">
                            <li><a class="dropdown-item" href="javascript:void(0)" onclick="abrirModalCompartilhar('pasta', <?php echo $f['id']; ?>, '<?php echo addslashes($f['nome']); ?>')"><i class="fas fa-share-alt me-2 text-primary"></i> Compartilhar</a></li>
                            <li><a class="dropdown-item" href="javascript:void(0)" onclick="abrirModalRenomearPasta(<?php echo $f['id']; ?>, '<?php echo addslashes($f['nome']); ?>')"><i class="fas fa-edit me-2 text-success"></i> Renomear</a></li>
                            <li><a class="dropdown-item text-danger" href="pastas_acoes.php?del_pasta=<?php echo $f['id']; ?>" onclick="return confirm('Excluir pasta?')"><i class="fas fa-trash me-2"></i> Excluir</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>

        <!-- ARQUIVOS -->
        <div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-5 row-cols-xl-6 g-2 g-lg-3">
            <?php
            $stmtA = $pdo->prepare("SELECT * FROM arquivos WHERE usuario_id = ? AND " . ($pasta_atual ? "pasta_id = $pasta_atual" : "pasta_id IS NULL") . " ORDER BY id DESC");
            $stmtA->execute([$user_id]);
            while($a = $stmtA->fetch()):
                $ext = strtolower(pathinfo($a['nome_original'], PATHINFO_EXTENSION));
                $path = "uploads/user_" . $user_id . "/" . $a['nome_sistema'];
                $isImg = in_array($ext, ['jpg','jpeg','png','webp','gif']);
                
                $iconClass = "fa-file-alt text-secondary";
                switch($ext) {
                    case 'pdf': $iconClass = "fa-file-pdf text-danger"; break;
                    case 'doc': case 'docx': $iconClass = "fa-file-word text-primary"; break;
                    case 'xls': case 'xlsx': $iconClass = "fa-file-excel text-success"; break;
                    case 'mp4': case 'mov': case 'avi': case 'mkv': $iconClass = "fa-file-video text-info"; break;
                }
            ?>
            <div class="col" draggable="true" ondragstart="iniciarArraste(event, 'arquivo', <?php echo $a['id']; ?>)">
                <div class="item-box overflow-hidden shadow-sm">
                    <input type="checkbox" class="item-checkbox form-check-input" value="<?php echo $a['id']; ?>" onclick="contarSelecionados()">
                    <div class="file-thumb">
                        <a href="<?php echo $path; ?>" data-fancybox="gallery" data-caption="<?php echo htmlspecialchars($a['nome_original']); ?>">
                            <?php if($isImg): ?><img src="<?php echo $path; ?>" alt="P"><?php else: ?><i class="fas <?php echo $iconClass; ?>"></i><?php endif; ?>
                        </a>
                    </div>
                    <div class="p-2 border-top bg-white d-flex justify-content-between align-items-center">
                        <div class="overflow-hidden">
                            <div class="text-truncate small fw-bold" style="font-size: 11px;" title="<?php echo htmlspecialchars($a['nome_original']); ?>"><?php echo htmlspecialchars($a['nome_original']); ?></div>
                            <div class="text-muted" style="font-size: 9px;"><?php echo date('d/m/y H:i', strtotime($a['data_upload'])); ?></div>
                        </div>
                        <div class="d-flex align-items-center">
                            <!-- BOTÃO DOWNLOAD -->
                            <a href="download.php?id=<?php echo $a['id']; ?>" class="text-primary me-2" title="Baixar Arquivo">
                                <i class="fas fa-download" style="font-size: 12px;"></i>
                            </a>
                            <!-- BOTÃO COMPARTILHAR -->
                            <a href="javascript:void(0)" class="text-muted" onclick="abrirModalCompartilhar('arquivo', <?php echo $a['id']; ?>, '<?php echo addslashes($a['nome_original']); ?>')" title="Compartilhar">
                                <i class="fas fa-share-alt" style="font-size: 12px;"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
</div>

<!-- MODAL COMPARTILHAR -->
<div class="modal fade" id="modalCompartilhar" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content p-4 border-0 shadow-lg">
    <h5 class="fw-bold"><i class="fas fa-share-alt text-primary me-2"></i> Compartilhar <span id="share_nome_item"></span></h5>
    <input type="hidden" id="share_tipo"><input type="hidden" id="share_id">
    <div class="mt-3">
        <label class="small fw-bold text-muted">PERMISSÃO</label>
        <select id="share_permissao" class="form-select mb-4">
            <option value="visualizar">Apenas Visualizar</option>
            <option value="editar">Pode Editar / Subir Arquivos</option>
        </select>
        <button class="btn btn-success w-100 rounded-pill fw-bold shadow" onclick="processarCompartilhamento('whatsapp')">
            <i class="fab fa-whatsapp me-2"></i>COMPARTILHAR NO WHATSAPP
        </button>
    </div>
</div></div></div>

<!-- MODAL UPLOAD -->
<div class="modal fade" id="modalUpload" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content p-4 text-center border-0 shadow-lg">
    <h5 class="fw-bold mb-3">Upload de Arquivos</h5>
    <input type="file" id="fileInput" class="form-control mb-3" multiple>
    <button onclick="enviarArquivosAJAX()" class="btn btn-primary w-100 rounded-pill fw-bold py-2">SUBIR ARQUIVOS</button>
</div></div></div>

<!-- MODAL PROGRESSO -->
<div class="modal fade" id="modalProgresso" data-bs-backdrop="static"><div class="modal-dialog modal-dialog-centered"><div class="modal-content p-4 text-center border-0 shadow-lg">
    <h5 id="msgStatus" class="fw-bold text-dark">Processando...</h5>
    <div class="progress mb-2" style="height: 14px; border-radius: 10px;"><div id="barP" class="progress-bar progress-bar-striped progress-bar-animated bg-primary" style="width: 0%"></div></div>
    <div class="d-flex justify-content-between small fw-bold"><div id="txtP" class="text-primary">0%</div><div id="timeRemaining" class="text-muted">Calculando...</div></div>
    <button type="button" class="btn btn-sm btn-outline-danger rounded-pill mt-3 px-4 fw-bold" onclick="abortarUpload()">CANCELAR</button>
</div></div></div>

<!-- MODAL RENOMEAR -->
<div class="modal fade" id="modalRenomearPasta" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form action="pastas_acoes.php" method="POST" class="modal-content border-0 shadow-lg">
    <div class="modal-header border-0"><h5>Renomear Pasta</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <input type="hidden" name="acao" value="renomear_pasta"><input type="hidden" name="pasta_id" id="renomear_pasta_id">
        <input type="text" name="novo_nome" id="renomear_novo_nome" class="form-control form-control-lg fw-bold" required autofocus>
    </div>
    <div class="modal-footer border-0"><button type="submit" class="btn btn-primary w-100 rounded-pill fw-bold">SALVAR</button></div>
</form></div></div>

<!-- MODAL NOVA PASTA -->
<div class="modal fade" id="modalPasta" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form action="pastas_acoes.php" method="POST" class="modal-content border-0 shadow-lg">
    <input type="hidden" name="acao" value="criar_pasta"><div class="modal-header border-0"><h5>Nova Pasta</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <input type="text" name="nome_pasta" class="form-control form-control-lg" placeholder="Nome da pasta" required>
        <input type="hidden" name="pai_id" value="<?php echo $pasta_atual; ?>">
    </div>
    <div class="modal-footer border-0"><button type="submit" class="btn btn-primary w-100 rounded-pill fw-bold">CRIAR</button></div>
</form></div></div>

<!-- MODAL MOVER -->
<div class="modal fade" id="modalMover" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content p-4 border-0 shadow-lg">
    <h5 class="fw-bold mb-3"><i class="fas fa-file-export text-primary me-2"></i> Mover para...</h5>
    <div class="list-group" id="lista_pastas_mover"></div>
</div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.umd.js"></script>

<script>
    Fancybox.bind("[data-fancybox]", {});
    let xhrUpload = null;
    const modalProg = new bootstrap.Modal(document.getElementById('modalProgresso'));

    // --- LÓGICA DE INATIVIDADE (3 MINUTOS) ---
    let inactivityTimer;
    function resetTimer() {
        clearTimeout(inactivityTimer);
        inactivityTimer = setTimeout(() => {
            alert("Sua sessão expirou por inatividade de 3 minutos.");
            window.location.href = 'logout.php';
        }, 180000);
    }
    window.onload = resetTimer;
    document.onmousemove = resetTimer;
    document.onkeydown = resetTimer;
    document.onclick = resetTimer;
    document.onscroll = resetTimer;

    function toggleSidebar() { 
        document.getElementById('sidebar').classList.toggle('show'); 
        document.getElementById('sidebarOverlay').classList.toggle('show'); 
    }

    // ARRASTE
    function iniciarArraste(e, tipo, id) { e.dataTransfer.setData("tipo", tipo); e.dataTransfer.setData("id", id); }
    function permitirArraste(e) { e.preventDefault(); if(e.currentTarget.classList.contains('item-box')) e.currentTarget.classList.add('drag-over'); }
    function removerEfeitoArraste(e) { e.currentTarget.classList.remove('drag-over'); }
    function finalizarArraste(e, pDestino) {
        e.preventDefault(); e.currentTarget.classList.remove('drag-over');
        const tipo = e.dataTransfer.getData("tipo"); const id = e.dataTransfer.getData("id");
        if(tipo === 'pasta' && id == pDestino) return;
        const url = tipo === 'arquivo' ? `pastas_acoes.php?mover_arq=${id}&para_pasta=${pDestino}` : `pastas_acoes.php?mover_pasta=${id}&para_pasta=${pDestino}`;
        fetch(url).then(() => location.reload());
    }

    // COMPARTILHAR
    function abrirModalCompartilhar(tipo, id, nome) {
        document.getElementById('share_tipo').value = tipo;
        document.getElementById('share_id').value = id;
        document.getElementById('share_nome_item').innerText = nome;
        new bootstrap.Modal(document.getElementById('modalCompartilhar')).show();
    }
    function processarCompartilhamento(via) {
        const fd = new FormData();
        fd.append('tipo', document.getElementById('share_tipo').value);
        fd.append('id', document.getElementById('share_id').value);
        fd.append('permissao', document.getElementById('share_permissao').value);
        fd.append('via', via);
        fetch('compartilhar_acao.php', { method: 'POST', body: fd }).then(r => r.json()).then(res => {
            if(res.url_whatsapp) { window.open(res.url_whatsapp, '_blank'); }
        });
    }

    // UPLOAD AJAX
    function enviarArquivosAJAX() {
        const fi = document.getElementById('fileInput'); if(!fi.files.length) return;
        const bar = document.getElementById('barP'); const txt = document.getElementById('txtP');
        const timeLab = document.getElementById('timeRemaining'); const msg = document.getElementById('msgStatus');
        modalProg.show();
        let start = new Date().getTime();
        xhrUpload = new XMLHttpRequest();
        xhrUpload.open('POST', 'upload.php', true);
        xhrUpload.upload.onprogress = (e) => {
            if (e.lengthComputable) {
                const pct = Math.round((e.loaded / e.total) * 100);
                bar.style.width = pct + '%'; txt.innerText = pct + '%';
                msg.innerText = "Enviando...";
                let dur = (new Date().getTime() - start) / 1000;
                let bps = e.loaded / dur;
                let rem = (e.total - e.loaded) / bps;
                if(rem > 0 && pct > 3) {
                    let m = Math.floor(rem/60); let s = Math.round(rem%60);
                    timeLab.innerText = "Faltam " + (m > 0 ? m + "m " : "") + s + "s";
                }
            }
        };
        xhrUpload.onload = () => location.reload();
        const fd = new FormData(); for(let f of fi.files) fd.append('arquivos[]', f);
        fd.append('pasta_id', '<?php echo $pasta_atual; ?>');
        xhrUpload.send(fd);
    }
    function abortarUpload() { if(xhrUpload) { xhrUpload.abort(); location.reload(); } }

    // MOVER EM MASSA
    function abrirModalMover() {
        fetch('obter_pastas_json.php').then(r => r.json()).then(pastas => {
            let html = `<a href="javascript:void(0)" onclick="confirmarMover(null)" class="list-group-item list-group-item-action fw-bold text-primary"><i class="fas fa-home me-2"></i> Raiz do Drive</a>`;
            pastas.forEach(p => { html += `<a href="javascript:void(0)" onclick="confirmarMover(${p.id})" class="list-group-item list-group-item-action"><i class="fas fa-folder text-warning me-2"></i> ${p.nome}</a>`; });
            document.getElementById('lista_pastas_mover').innerHTML = html;
            new bootstrap.Modal(document.getElementById('modalMover')).show();
        });
    }
    function confirmarMover(dest) {
        const ids = Array.from(document.querySelectorAll('.item-checkbox:checked')).map(x => x.value);
        const fd = new FormData(); ids.forEach(id => fd.append('ids[]', id)); fd.append('destino_id', dest);
        fetch('mover_multiplos.php', { method: 'POST', body: fd }).then(() => location.reload());
    }

    function abrirModalRenomearPasta(id, nome) {
        document.getElementById('renomear_pasta_id').value = id;
        document.getElementById('renomear_novo_nome').value = nome;
        new bootstrap.Modal(document.getElementById('modalRenomearPasta')).show();
    }

    function contarSelecionados() {
        const n = document.querySelectorAll('.item-checkbox:checked').length;
        document.getElementById('bulk-bar').style.display = n > 0 ? 'flex' : 'none';
        document.getElementById('label-selecionados').innerText = n + " selecionados";
    }

    function excluirSelecaoMassa() {
        if(!confirm("Excluir permanentemente?")) return;
        const ids = Array.from(document.querySelectorAll('.item-checkbox:checked')).map(x => x.value);
        const fd = new FormData(); ids.forEach(id => fd.append('ids[]', id));
        fetch('excluir_multiplos.php', { method: 'POST', body: fd }).then(() => location.reload());
    }
</script>
</body>
</html>