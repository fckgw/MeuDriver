<?php
/**
 * BDSoft Workspace - ADMINISTRAÇÃO DE USUÁRIOS (SISTEMA CENTRAL)
 * Localização: public_html/admin_usuarios.php
 * Atualização: Cadastro via Pop-up com PHPMailer (SMTP), Senha Aleatória e Redirecionamento.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Carregamento da biblioteca PHPMailer (Caminhos relativos ao local do arquivo)
require 'includes/PHPMailer/Exception.php';
require 'includes/PHPMailer/PHPMailer.php';
require 'includes/PHPMailer/SMTP.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'config.php';

// Verificação de Segurança Master
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_nivel'] !== 'admin') { 
    header("Location: login.php"); 
    exit; 
}

// =========================================================================
// 1. PROCESSADOR AJAX: CRIAR NOVO USUÁRIO (Lógica do Registro.php)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'novo_usuario_admin') {
    try {
        $nome    = trim($_POST['nome']);
        $usuario = trim($_POST['email']); // E-mail usado como login
        $cpf     = trim($_POST['cpf']);
        $rg      = trim($_POST['rg']);
        $nivel   = $_POST['nivel'];

        // A. Validar Duplicidade
        $stmt_check = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = ? OR cpf = ? LIMIT 1");
        $stmt_check->execute([$usuario, $cpf]);

        if ($stmt_check->rowCount() > 0) {
            die("ALERTA: Já existe um usuário com este E-mail ou CPF.");
        }

        // B. Gerar Senha Aleatória e Hash
        $senha_temp = substr(str_shuffle("abcdefghjkmnpqrstuvwxyz23456789"), 0, 8);
        $senha_hash = password_hash($senha_temp, PASSWORD_DEFAULT);

        $pdo->beginTransaction();

        // C. Inserir no Banco
        $sql = "INSERT INTO usuarios (nome, cpf, rg, usuario, senha, trocar_senha, data_criacao, nivel, status, espaco_gb) 
                VALUES (?, ?, ?, ?, ?, 1, NOW(), ?, 'ativo', 5)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nome, $cpf, $rg, $usuario, $senha_hash, $nivel]);
        $novo_id = $pdo->lastInsertId();

        // D. Enviar E-mail via SMTP
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'email-ssl.com.br';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'souzafelipe@bdsoft.com.br';
        $mail->Password   = 'BDSoft@2020';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom('souzafelipe@bdsoft.com.br', 'Workspace Drive');
        $mail->addAddress($usuario, $nome);

        $mail->isHTML(true);
        $mail->Subject = 'Suas Credenciais de Acesso - Workspace Drive';
        $mail->Body    = "
            <div style='font-family: sans-serif; color: #333;'>
                <h2>Olá, $nome!</h2>
                <p>Seu acesso ao <strong>Workspace Drive</strong> foi criado pelo administrador.</p>
                <div style='background: #f4f7f6; padding: 20px; border-radius: 10px; border: 1px solid #ddd;'>
                    <strong>URL:</strong> <a href='https://workspace.bdsoft.com.br'>workspace.bdsoft.com.br</a><br>
                    <strong>Usuário:</strong> $usuario<br>
                    <strong>Senha Temporária:</strong> <span style='color:red; font-size:18px; font-weight:bold;'>$senha_temp</span>
                </div>
                <p><strong>Aviso:</strong> Por segurança, você deverá alterar esta senha no seu primeiro login.</p>
            </div>";

        $mail->send();
        $pdo->commit();

        // Retorna o ID para o JavaScript redirecionar para a gestão de módulos
        echo $novo_id;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo "ERRO: " . $e->getMessage();
    }
    exit;
}

// =========================================================================
// 2. PROCESSADOR AJAX: EDITAR ACESSO EXISTENTE
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'editar_acesso_usuario') {
    try {
        $id_usuario = (int)$_POST['usuario_id'];
        $nivel_novo = $_POST['nivel']; 
        
        if ($nivel_novo === 'admin') {
            $stmt = $pdo->prepare("UPDATE usuarios SET nivel = 'admin', data_inicio = NULL, data_fim = NULL, espaco_gb = NULL WHERE id = ?");
            $stmt->execute([$id_usuario]);
        } else {
            $data_fim = !empty($_POST['data_fim']) ? $_POST['data_fim'] : null;
            $espaco   = !empty($_POST['espaco_gb']) ? (int)$_POST['espaco_gb'] : 5;
            $stmt = $pdo->prepare("UPDATE usuarios SET nivel = 'membro', data_fim = ?, espaco_gb = ? WHERE id = ?");
            $stmt->execute([$data_fim, $espaco, $id_usuario]);
        }
        echo "Sucesso";
    } catch (Exception $e) { echo "Erro: " . $e->getMessage(); }
    exit; 
}

// 3. Buscar Lista de Usuários
$stmt = $pdo->prepare("SELECT id, nome, usuario, cpf, nivel, data_fim, espaco_gb FROM usuarios ORDER BY nome ASC");
$stmt->execute();
$lista_usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
$hoje_ref = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Usuários - Workspace Drive</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --sidebar-w: 70px; --primary-blue: #1a73e8; }
        body { background:#f8f9fa; font-family:'Segoe UI', sans-serif; margin:0; display: flex; }
        .sidebar-mini { width: var(--sidebar-w); background:#292f4c; height:100vh; position:fixed; left:0; top:0; z-index:1050; display: flex; flex-direction: column; align-items: center; padding-top: 25px; }
        .sidebar-mini a { color: rgba(255,255,255,0.6); margin-bottom: 30px; transition: 0.3s; text-decoration: none; }
        .sidebar-mini a:hover { color: #fff; transform: scale(1.1); }
        .main-wrapper { flex:1; margin-left: var(--sidebar-w); min-width: 0; }
        .nav-board { background:#ffffff; border-bottom:1px solid #dee2e6; padding:12px 25px; position:sticky; top:0; z-index:900; }
        .card-table { background: #ffffff; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid #eee; overflow: hidden; margin: 25px; }
        .table-custom { width: 100%; border-collapse: collapse; }
        .table-custom th { background: #fafafa; padding: 15px 20px; font-size: 11px; color: #6c757d; text-transform: uppercase; border-bottom: 1px solid #eee; }
        .table-custom td { padding: 15px 20px; border-bottom: 1px solid #f8f9fa; vertical-align: middle; font-size: 14px; }
        .tr-hover:hover { background-color: #f0f7ff; }
    </style>
</head>
<body>

<div class="sidebar-mini shadow no-print">
    <a href="portal.php" title="Portal"><i class="fas fa-th-large fa-2x"></i></a>
    <a href="dashboard.php" title="Arquivos"><i class="fas fa-folder-open fa-lg"></i></a>
    <hr class="w-75 opacity-25">
    <a href="admin_usuarios.php" title="Usuários" class="text-white"><i class="fas fa-users fa-lg"></i></a>
</div>

<div class="main-wrapper">
    <nav class="nav-board d-flex justify-content-between align-items-center shadow-sm">
        <h5 class="fw-bold mb-0 text-dark"><i class="fas fa-user-shield text-primary me-2"></i> Gestão de Usuários</h5>
        <button class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalNovoUsuario">
            <i class="fas fa-plus me-2"></i> NOVO USUÁRIO
        </button>
    </nav>

    <div class="p-2">
        <div class="card-table">
            <table class="table-custom">
                <thead>
                    <tr>
                        <th class="text-center">ID</th>
                        <th>NOME COMPLETO</th>
                        <th class="text-center">NÍVEL</th>
                        <th class="text-center">VENCIMENTO</th>
                        <th class="text-center">QUOTA</th>
                        <th class="text-center">AÇÕES</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($lista_usuarios as $u): $is_admin = ($u['nivel'] === 'admin'); ?>
                    <tr class="tr-hover">
                        <td class="text-center text-muted fw-bold">#<?php echo $u['id']; ?></td>
                        <td>
                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($u['nome']); ?></div>
                            <div class="small text-muted"><?php echo htmlspecialchars($u['usuario']); ?> | CPF: <?php echo $u['cpf']; ?></div>
                        </td>
                        <td class="text-center">
                            <span class="badge <?php echo $is_admin ? 'bg-dark' : 'bg-light text-dark border'; ?> rounded-pill px-3">
                                <?php echo ucfirst($u['nivel']); ?>
                            </span>
                        </td>
                        <td class="text-center"><?php echo ($is_admin) ? 'Vitalício' : ($u['data_fim'] ? date('d/m/Y', strtotime($u['data_fim'])) : '---'); ?></td>
                        <td class="text-center fw-bold"><?php echo $is_admin ? '∞' : $u['espaco_gb'] . ' GB'; ?></td>
                        <td class="text-center">
                            <div class="d-flex justify-content-center gap-2">
                                <a href="admin_permissoes.php?uid=<?php echo $u['id']; ?>" class="btn btn-sm btn-light border rounded-circle text-warning shadow-sm" title="Módulos"><i class="fas fa-key"></i></a>
                                <button class="btn btn-sm btn-light border rounded-circle text-primary shadow-sm" onclick="abrirModalEdicaoAcesso(<?php echo $u['id']; ?>, '<?php echo $u['nivel']; ?>', '<?php echo $u['data_fim']; ?>', '<?php echo $u['espaco_gb']; ?>')"><i class="fas fa-pencil-alt"></i></button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL: NOVO USUÁRIO (Estilo Registro.php) -->
<div class="modal fade" id="modalNovoUsuario" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form id="formNovoUsuario" class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="fas fa-user-plus text-primary me-2"></i> Adicionar Novo Usuário</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="acao" value="novo_usuario_admin">
                <div class="mb-3">
                    <label class="small fw-bold text-muted">NOME COMPLETO</label>
                    <input type="text" name="nome" class="form-control" required placeholder="João da Silva">
                </div>
                <div class="row">
                    <div class="col-6 mb-3"><label class="small fw-bold text-muted">CPF</label><input type="text" name="cpf" id="cpf_mask" class="form-control" required placeholder="000.000.000-00"></div>
                    <div class="col-6 mb-3"><label class="small fw-bold text-muted">RG</label><input type="text" name="rg" id="rg_mask" class="form-control" required placeholder="0.000.000"></div>
                </div>
                <div class="mb-3">
                    <label class="small fw-bold text-muted">E-MAIL (LOGIN)</label>
                    <input type="email" name="email" class="form-control" required placeholder="email@exemplo.com">
                </div>
                <div class="mb-3">
                    <label class="small fw-bold text-muted">NÍVEL INICIAL</label>
                    <select name="nivel" class="form-select">
                        <option value="membro">Membro (Assinante)</option>
                        <option value="admin">Administrador (Master)</option>
                    </select>
                </div>
                <div class="alert alert-warning small border-0 py-2">
                    <i class="fas fa-info-circle me-1"></i> Uma <b>senha temporária</b> será gerada e enviada por e-mail automaticamente.
                </div>
                <button type="submit" id="btnSalvarNovo" class="btn btn-primary w-100 rounded-pill py-3 fw-bold shadow">CRIAR E DEFINIR MÓDULOS</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: EDITAR ACESSO -->
<div class="modal fade" id="modalEditarAcesso" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 p-4">
            <input type="hidden" id="edit_user_id">
            <h5 class="fw-bold mb-3">Configurar Plano</h5>
            <div class="mb-3"><label class="small fw-bold">Nível</label><select id="edit_user_nivel" class="form-select"><option value="membro">Membro</option><option value="admin">Administrador</option></select></div>
            <div class="row g-2 mb-3">
                <div class="col-6"><label class="small fw-bold">Vencimento</label><input type="date" id="edit_user_data_fim" class="form-control"></div>
                <div class="col-6"><label class="small fw-bold">Quota (GB)</label><input type="number" id="edit_user_espaco" class="form-control"></div>
            </div>
            <button type="button" class="btn btn-primary w-100 rounded-pill fw-bold py-2" onclick="salvarConfiguracoes()">SALVAR ALTERAÇÕES</button>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    $(document).ready(function(){
        $('#cpf_mask').mask('000.000.000-00');
        $('#rg_mask').mask('0.000.000');
    });

    // LÓGICA DE CRIAÇÃO
    document.getElementById('formNovoUsuario').onsubmit = function(e) {
        e.preventDefault();
        const btn = document.getElementById('btnSalvarNovo');
        btn.disabled = true; btn.innerHTML = "<i class='fas fa-spinner fa-spin me-2'></i> PROCESSANDO...";
        
        fetch('admin_usuarios.php', { method: 'POST', body: new FormData(this) })
            .then(r => r.text())
            .then(res => {
                if(!isNaN(res)) {
                    alert("✅ Usuário criado e e-mail enviado com sucesso!");
                    window.location.href = 'admin_permissoes.php?uid=' + res.trim();
                } else {
                    alert(res);
                    btn.disabled = false; btn.innerHTML = "CRIAR E DEFINIR MÓDULOS";
                }
            });
    };

    // LÓGICA DE EDIÇÃO
    const modalAcesso = new bootstrap.Modal(document.getElementById('modalEditarAcesso'));
    function abrirModalEdicaoAcesso(id, nivel, dataFim, espacoGb) {
        document.getElementById('edit_user_id').value = id;
        document.getElementById('edit_user_nivel').value = nivel;
        document.getElementById('edit_user_data_fim').value = dataFim;
        document.getElementById('edit_user_espaco').value = espacoGb || '5';
        modalAcesso.show();
    }
    function salvarConfiguracoes() {
        const fd = new FormData();
        fd.append('acao', 'editar_acesso_usuario');
        fd.append('usuario_id', document.getElementById('edit_user_id').value);
        fd.append('nivel', document.getElementById('edit_user_nivel').value);
        fd.append('data_fim', document.getElementById('edit_user_data_fim').value);
        fd.append('espaco_gb', document.getElementById('edit_user_espaco').value);
        fetch('admin_usuarios.php', { method: 'POST', body: fd }).then(r => r.text()).then(d => {
            if(d.trim()==='Sucesso') location.reload(); else alert(d);
        });
    }
</script>
</body>
</html>