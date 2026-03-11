<?php
/**
 * BDSoft Workspace - ADMINISTRAÇÃO DE USUÁRIOS
 * Versão Completa: Listagem, Edição de Acesso (Admin/Membro), Vigência e Espaço (GB)
 */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'config.php';

// Verificação de Segurança
if (!isset($_SESSION['usuario_id'])) { 
    header("Location: login.php"); 
    exit; 
}

// =========================================================================
// PROCESSADOR DE SALVAMENTO (Fica aqui para evitar erro de caminhos)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'editar_acesso_usuario') {
    try {
        $id = (int)$_POST['usuario_id'];
        $nivel = $_POST['nivel']; // Vai chegar 'admin' ou 'membro'
        
        if ($nivel === 'admin') {
            // Se for admin, o acesso é vitalício e ilimitado, limpa as datas e o espaço
            $stmt = $pdo->prepare("UPDATE usuarios SET nivel = 'admin', data_inicio = NULL, data_fim = NULL, espaco_gb = NULL WHERE id = ?");
            $stmt->execute([$id]);
        } else {
            // Se for membro, grava a data do pacote e o limite de espaço em GB
            $data_ini = $_POST['data_inicio'];
            $data_fim = $_POST['data_fim'];
            $espaco = !empty($_POST['espaco_gb']) ? (int)$_POST['espaco_gb'] : 5; // Padrão de 5GB se vier vazio
            
            $stmt = $pdo->prepare("UPDATE usuarios SET nivel = 'membro', data_inicio = ?, data_fim = ?, espaco_gb = ? WHERE id = ?");
            $stmt->execute([$data_ini, $data_fim, $espaco, $id]);
        }
        echo "Sucesso";
    } catch (Exception $e) {
        echo "Erro: " . $e->getMessage();
    }
    exit; // Para a execução do script aqui após responder ao JavaScript
}
// =========================================================================

// =========================================================================
// AUTO-CORREÇÃO DO BANCO DE DADOS (Cria as colunas se elas não existirem)
// =========================================================================
try {
    $checkColsData = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'data_inicio'")->rowCount();
    if ($checkColsData == 0) {
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN data_inicio DATE NULL, ADD COLUMN data_fim DATE NULL");
    }
    
    // Auto-criação da coluna de Espaço (GB)
    $checkColsEspaco = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'espaco_gb'")->rowCount();
    if ($checkColsEspaco == 0) {
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN espaco_gb INT NULL DEFAULT 5");
    }
} catch (Exception $e) {}
// =========================================================================

// Buscar todos os usuários cadastrados
$stmt = $pdo->prepare("SELECT id, nome, nivel, data_inicio, data_fim, espaco_gb FROM usuarios ORDER BY nome ASC");
$stmt->execute();
$lista_usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
$hoje = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Usuários - BDSoft Workspace</title>
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
        
        .card-usuarios { background: #ffffff; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border: 1px solid #eee; overflow: hidden; }
        .table-clean { width: 100%; border-collapse: collapse; }
        .table-clean th { background: #fafafa; padding: 15px 20px; font-size: 11px; color: #6c757d; text-transform: uppercase; border-bottom: 1px solid #eee; }
        .table-clean td { padding: 12px 20px; border-bottom: 1px solid #f8f9fa; vertical-align: middle; }
        .table-row:hover { background-color: #f0f7ff; transition: 0.2s; }
    </style>
</head>
<body>

<!-- SIDEBAR DE ÍCONES -->
<div class="sidebar-mini shadow no-print">
    <a href="portal.php" title="Portal Workspace"><i class="fas fa-th-large fa-2x"></i></a>
    <a href="projetos/index.php" title="Meus Projetos"><i class="fas fa-project-diagram fa-lg"></i></a>
    <hr class="w-75 opacity-25">
    <a href="admin_usuarios.php" title="Gestão de Usuários" class="text-white"><i class="fas fa-users fa-lg"></i></a>
</div>

<div class="main-wrapper">
    <!-- NAVBAR PRINCIPAL -->
    <nav class="nav-board d-flex justify-content-between align-items-center shadow-sm">
        <h5 class="fw-bold mb-0 text-dark"><i class="fas fa-users text-primary me-2"></i> Gestão de Usuários e Acessos</h5>
    </nav>

    <div class="p-4">
        <div class="card-usuarios">
            <table class="table-clean">
                <thead>
                    <tr>
                        <th style="width: 50px;" class="text-center">ID</th>
                        <th>NOME DO USUÁRIO</th>
                        <th class="text-center">NÍVEL / ASSINATURA</th>
                        <th class="text-center">VIGÊNCIA</th>
                        <th class="text-center">ESPAÇO</th>
                        <th class="text-center">STATUS</th>
                        <th class="text-center" style="width: 100px;">AÇÕES</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    foreach($lista_usuarios as $u) { 
                        // Regras de Status Visual
                        $is_admin = (strtolower($u['nivel']) === 'admin');
                        $badge_status = '';
                        $texto_vigencia = '';
                        $texto_espaco = '';

                        if ($is_admin) {
                            $badge_status = '<span class="badge bg-success shadow-sm">ATIVO (VITALÍCIO)</span>';
                            $texto_vigencia = '<span class="text-muted fw-bold" style="font-size:12px;"><i class="fas fa-infinity"></i> Ilimitado</span>';
                            $texto_espaco = '<span class="text-muted fw-bold" style="font-size:12px;"><i class="fas fa-infinity"></i> Ilimitado</span>';
                        } else {
                            // Espaço em disco (GB)
                            $gb_liberado = !empty($u['espaco_gb']) ? $u['espaco_gb'] : 5; // Valor visual padrão caso esteja nulo
                            $texto_espaco = "<span class='fw-bold text-dark'>{$gb_liberado} GB</span>";

                            // Datas
                            if (empty($u['data_inicio']) || empty($u['data_fim'])) {
                                $badge_status = '<span class="badge bg-warning text-dark shadow-sm">PENDENTE</span>';
                                $texto_vigencia = '<span class="text-muted small">Sem datas definidas</span>';
                            } else {
                                $dt_ini_br = date('d/m/Y', strtotime($u['data_inicio']));
                                $dt_fim_br = date('d/m/Y', strtotime($u['data_fim']));
                                $texto_vigencia = "<span class='small fw-bold text-dark'>{$dt_ini_br}</span> <small class='text-muted'>até</small> <span class='small fw-bold text-dark'>{$dt_fim_br}</span>";
                                
                                if ($u['data_fim'] < $hoje) {
                                    $badge_status = '<span class="badge bg-danger shadow-sm">VENCIDO</span>';
                                } else {
                                    $badge_status = '<span class="badge bg-primary shadow-sm">ATIVO</span>';
                                }
                            }
                        }
                    ?>
                    <tr class="table-row">
                        <td class="text-center text-muted fw-bold">#<?php echo $u['id']; ?></td>
                        <td class="fw-bold text-dark"><?php echo htmlspecialchars($u['nome']); ?></td>
                        <td class="text-center">
                            <?php if($is_admin): ?>
                                <span class="badge bg-dark rounded-pill px-3">Administrador</span>
                            <?php else: ?>
                                <span class="badge bg-light text-dark border rounded-pill px-3">Membro</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><?php echo $texto_vigencia; ?></td>
                        <td class="text-center"><?php echo $texto_espaco; ?></td>
                        <td class="text-center"><?php echo $badge_status; ?></td>
                        <td class="text-center">
                            <!-- BOTÃO LÁPIS QUE ABRE O MODAL -->
                            <button class="btn btn-sm btn-light border rounded-circle text-primary shadow-sm" 
                                onclick="abrirModalEdicaoAcesso(<?php echo $u['id']; ?>, '<?php echo $u['nivel']; ?>', '<?php echo $u['data_inicio']; ?>', '<?php echo $u['data_fim']; ?>', '<?php echo $u['espaco_gb']; ?>')" 
                                title="Editar Acesso e Módulos">
                                <i class="fas fa-pencil-alt"></i>
                            </button>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL EDITAR ACESSO DO USUÁRIO -->
<div class="modal fade" id="modalEditarAcesso" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark"><i class="fas fa-user-shield text-primary me-2"></i> Configurar Acesso</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="edit_user_id">
                
                <label class="small fw-bold mb-1 text-muted">NÍVEL DE ACESSO</label>
                <select id="edit_user_nivel" class="form-select form-select-lg mb-3 shadow-sm border-light" onchange="toggleDatasAcesso()" style="font-size: 14px; font-weight: 500;">
                    <option value="membro">Membro (Assinante / Cliente)</option>
                    <option value="admin">Administrador (Acesso Vitalício do Sistema)</option>
                </select>
                
                <!-- Campos de Data e Espaço (Apenas para Membros) -->
                <div id="boxDatasAssinatura" class="bg-light p-3 rounded-3 mb-3 border">
                    <p class="small text-muted mb-3"><i class="fas fa-calendar-alt me-1"></i> Defina os limites do contrato deste usuário.</p>
                    
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="small fw-bold mb-1 text-muted" style="font-size: 10px;">DATA INÍCIO</label>
                            <input type="date" id="edit_user_data_ini" class="form-control">
                        </div>
                        <div class="col-6">
                            <label class="small fw-bold mb-1 text-muted" style="font-size: 10px;">DATA FIM (VENCIMENTO)</label>
                            <input type="date" id="edit_user_data_fim" class="form-control">
                        </div>
                    </div>

                    <div class="row g-2 border-top pt-3">
                        <div class="col-12">
                            <label class="small fw-bold mb-1 text-muted" style="font-size: 10px;">ESPAÇO DE ARMAZENAMENTO MÁXIMO</label>
                            <div class="input-group">
                                <input type="number" id="edit_user_espaco" class="form-control text-center fw-bold" min="1" step="1" placeholder="Ex: 5">
                                <span class="input-group-text bg-white text-muted fw-bold border-start-0">Gigabytes (GB)</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Aviso para Administrador -->
                <div id="boxAcessoVitalicio" style="display: none;" class="alert alert-success text-center py-3 mb-3 border-0 shadow-sm">
                    <i class="fas fa-infinity mb-2 fa-2x text-success"></i><br>
                    <span class="fw-bold text-success">Acesso Administrador Ilimitado</span><br>
                    <small class="text-success opacity-75">Sem regras de validade ou limites de armazenamento.</small>
                </div>
                
                <button type="button" id="btnSalvar" class="btn btn-primary w-100 rounded-pill py-2 fw-bold shadow mt-2" onclick="salvarEdicaoAcesso()">SALVAR CONFIGURAÇÕES</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const modalAcesso = new bootstrap.Modal(document.getElementById('modalEditarAcesso'));

function abrirModalEdicaoAcesso(id, nivel, dataIni, dataFim, espacoGb) {
    document.getElementById('edit_user_id').value = id;
    
    const nivelTratado = (nivel && nivel.toLowerCase() === 'admin') ? 'admin' : 'membro';
    document.getElementById('edit_user_nivel').value = nivelTratado;
    
    document.getElementById('edit_user_data_ini').value = (dataIni && dataIni !== '0000-00-00') ? dataIni : '';
    document.getElementById('edit_user_data_fim').value = (dataFim && dataFim !== '0000-00-00') ? dataFim : '';
    
    // Tratamento para o espaço em disco (Padrão visual 5 se tiver vazio)
    document.getElementById('edit_user_espaco').value = (espacoGb && espacoGb > 0) ? espacoGb : '5';
    
    toggleDatasAcesso(); 
    modalAcesso.show();
}

function toggleDatasAcesso() {
    const nivel = document.getElementById('edit_user_nivel').value;
    const boxDatas = document.getElementById('boxDatasAssinatura');
    const boxVitalicio = document.getElementById('boxAcessoVitalicio');

    if (nivel === 'admin') {
        boxDatas.style.display = 'none';
        boxVitalicio.style.display = 'block';
    } else {
        boxDatas.style.display = 'block';
        boxVitalicio.style.display = 'none';
    }
}

function salvarEdicaoAcesso() {
    const id = document.getElementById('edit_user_id').value;
    const nivel = document.getElementById('edit_user_nivel').value;
    const dataIni = document.getElementById('edit_user_data_ini').value;
    const dataFim = document.getElementById('edit_user_data_fim').value;
    const espaco = document.getElementById('edit_user_espaco').value;

    if (nivel === 'membro' && (!dataIni || !dataFim)) {
        alert("⚠️ ATENÇÃO: Para o nível Membro, é obrigatório informar a Data de Início e a Data de Fim do contrato.");
        return;
    }

    // Muda o texto do botão para dar sensação de carregamento
    const btn = document.getElementById('btnSalvar');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
    btn.disabled = true;

    const fd = new FormData();
    fd.append('acao', 'editar_acesso_usuario');
    fd.append('usuario_id', id);
    fd.append('nivel', nivel);
    fd.append('data_inicio', nivel === 'admin' ? '' : dataIni);
    fd.append('data_fim', nivel === 'admin' ? '' : dataFim);
    fd.append('espaco_gb', nivel === 'admin' ? '' : espaco); // Envia o limite em GB

    fetch('admin_usuarios.php', { method: 'POST', body: fd })
        .then(response => response.text())
        .then(data => {
            if(data.trim() === 'Sucesso') {
                alert("✅ Configurações salvas com sucesso!");
                location.reload(); 
            } else {
                alert("❌ Ocorreu um erro no banco de dados:\n" + data);
                btn.innerHTML = 'SALVAR CONFIGURAÇÕES';
                btn.disabled = false;
            }
        })
        .catch(error => {
            alert("❌ Erro de comunicação com o servidor:\n" + error);
            btn.innerHTML = 'SALVAR CONFIGURAÇÕES';
            btn.disabled = false;
        });
}
</script>
</body>
</html>