<?php
/**
 * SISTEMA DE DRIVE PROFISSIONAL - GESTÃO DE USUÁRIOS (ADMIN)
 * Local: driverbds.tecnologia.ws
 */

session_start();
require_once 'config.php';

// 1. Verificação de Segurança: Apenas Administradores acessam esta página
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_nivel'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$admin_id_logado = $_SESSION['usuario_id'];
$mensagem_feedback = "";

// 2. Processar a Criação de Novo Usuário pelo Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_cadastrar_usuario'])) {
    
    $nome_completo = trim($_POST['nome']);
    $cpf_formatado = trim($_POST['cpf']);
    $usuario_email = trim($_POST['usuario']);
    $senha_digitada = $_POST['senha'];
    
    // Converte MB para Bytes (MB * 1024 * 1024)
    $quota_em_mb = (int)$_POST['quota_mb'];
    $quota_em_bytes = $quota_em_mb * 1048576; 

    $senha_hash = password_hash($senha_digitada, PASSWORD_DEFAULT);

    try {
        $query_insert = "INSERT INTO usuarios (nome, cpf, usuario, senha, quota_limite, data_criacao, nivel, status) 
                         VALUES (?, ?, ?, ?, ?, NOW(), 'usuario', 'ativo')";
        
        $stmt_insert = $pdo->prepare($query_insert);
        $stmt_insert->execute([$nome_completo, $cpf_formatado, $usuario_email, $senha_hash, $quota_em_bytes]);

        // --- ENVIO DE E-MAIL DE BOAS-VINDAS (VIA ADMIN) ---
        $assunto = "Sua conta DriveBDS foi criada";
        $corpo = "
        <html>
        <body style='font-family: sans-serif; color: #333;'>
            <h2 style='color: #1a73e8;'>Olá, $nome_completo!</h2>
            <p>O administrador criou sua conta de acesso ao <strong>DriveBDS</strong>.</p>
            <p><strong>URL de Acesso:</strong> https://driverbds.tecnologia.ws/</p>
            <p><strong>Seu Usuário:</strong> $usuario_email</p>
            <p><strong>Sua Senha Inicial:</strong> $senha_digitada</p>
            <p><strong>Espaço Disponível:</strong> $quota_em_mb MB</p>
            <hr>
            <p style='font-size: 12px; color: #666;'>Recomendamos alterar sua senha após o primeiro acesso.</p>
        </body>
        </html>";

        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: suporte@driverbds.tecnologia.ws" . "\r\n";

        @mail($usuario_email, $assunto, $corpo, $headers);

        if (function_exists('registrarLog')) {
            registrarLog($pdo, $admin_id_logado, "Admin", "Criou o usuário: $usuario_email com quota de $quota_em_mb MB.");
        }

        $mensagem_feedback = "<div class='alert alert-success shadow-sm'>Usuário <strong>$nome_completo</strong> cadastrado e e-mail enviado!</div>";

    } catch (Exception $e) {
        $mensagem_feedback = "<div class='alert alert-danger shadow-sm'>Erro: E-mail ou CPF já cadastrado.</div>";
    }
}

// 3. Processar Ações (Ativar, Suspender, Excluir)
if (isset($_GET['acao']) && isset($_GET['id'])) {
    $id_alvo = (int)$_GET['id'];

    if ($id_alvo !== $admin_id_logado) {
        if ($_GET['acao'] === 'excluir') {
            $pdo->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$id_alvo]);
            registrarLog($pdo, $admin_id_logado, "Admin", "Excluiu o usuário ID: $id_alvo");
        } 
        elseif ($_GET['acao'] === 'suspender') {
            $pdo->prepare("UPDATE usuarios SET status = 'suspenso' WHERE id = ?")->execute([$id_alvo]);
            registrarLog($pdo, $admin_id_logado, "Admin", "Suspendeu o usuário ID: $id_alvo");
        } 
        elseif ($_GET['acao'] === 'ativar') {
            // Renova os 14 dias resetando a data de criação para HOJE
            $pdo->prepare("UPDATE usuarios SET status = 'ativo', data_criacao = NOW() WHERE id = ?")->execute([$id_alvo]);
            registrarLog($pdo, $admin_id_logado, "Admin", "Reativou/Renovou o usuário ID: $id_alvo");
        }
    }
    header("Location: admin_usuarios.php");
    exit;
}

$usuarios = $pdo->query("SELECT * FROM usuarios ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

function formatarTabelaQuota($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . " GB";
    return number_format($bytes / 1048576, 2) . " MB";
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Usuários - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', sans-serif; }
        .card-custom { border: none; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
        .badge-ativo { background: #e6f4ea; color: #1e8e3e; padding: 6px 12px; border-radius: 20px; font-weight: 600; }
        .badge-suspenso { background: #fce8e6; color: #d93025; padding: 6px 12px; border-radius: 20px; font-weight: 600; }
    </style>
</head>
<body class="p-4">

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold"><i class="fas fa-users-cog me-2 text-primary"></i>Usuários</h2>
        <div>
            <button class="btn btn-primary rounded-pill px-4 fw-bold me-2" data-bs-toggle="modal" data-bs-target="#modalNovo">
                <i class="fas fa-plus me-2"></i>NOVO USUÁRIO
            </button>
            <a href="dashboard.php" class="btn btn-outline-secondary rounded-pill px-4 fw-bold">VOLTAR</a>
        </div>
    </div>

    <?php echo $mensagem_feedback; ?>

    <div class="card card-custom p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr class="text-muted small">
                        <th>NOME</th>
                        <th>USUÁRIO (E-MAIL)</th>
                        <th>LIMITE</th>
                        <th>STATUS</th>
                        <th>CADASTRO</th>
                        <th class="text-center">AÇÕES</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $u): ?>
                    <tr>
                        <td>
                            <div class="fw-bold"><?php echo htmlspecialchars($u['nome']); ?></div>
                            <div class="small text-muted"><?php echo $u['cpf']; ?></div>
                        </td>
                        <td><?php echo htmlspecialchars($u['usuario']); ?></td>
                        <td class="fw-bold"><?php echo formatarTabelaQuota($u['quota_limite']); ?></td>
                        <td>
                            <span class="<?php echo ($u['status'] === 'ativo') ? 'badge-ativo' : 'badge-suspenso'; ?>">
                                <?php echo ucfirst($u['status']); ?>
                            </span>
                        </td>
                        <td class="small"><?php echo date('d/m/Y', strtotime($u['data_criacao'])); ?></td>
                        <td class="text-center">
                            <?php if($u['id'] != $admin_id_logado): ?>
                                <?php if($u['status'] === 'ativo'): ?>
                                    <a href="?acao=suspender&id=<?php echo $u['id']; ?>" class="btn btn-sm btn-light border text-danger" title="Suspender"><i class="fas fa-ban"></i></a>
                                <?php else: ?>
                                    <a href="?acao=ativar&id=<?php echo $u['id']; ?>" class="btn btn-sm btn-success" title="Ativar/Renovar"><i class="fas fa-check"></i></a>
                                <?php endif; ?>
                                <a href="?acao=excluir&id=<?php echo $u['id']; ?>" class="btn btn-sm btn-light border text-dark ms-1" onclick="return confirm('Excluir permanentemente?')"><i class="fas fa-trash-alt"></i></a>
                            <?php else: ?>
                                <span class="text-muted small">Admin Principal</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL NOVO USUÁRIO -->
<div class="modal fade" id="modalNovo" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 bg-light">
                <h5 class="fw-bold mb-0">Cadastrar Usuário</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="small fw-bold">NOME COMPLETO</label>
                    <input type="text" name="nome" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="small fw-bold">CPF</label>
                    <input type="text" name="cpf" id="cpf_admin" class="form-control" placeholder="000.000.000-00" required>
                </div>
                <div class="mb-3">
                    <label class="small fw-bold">E-MAIL DE LOGIN</label>
                    <input type="email" name="usuario" class="form-control" required>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="small fw-bold">SENHA</label>
                        <input type="password" name="senha" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="small fw-bold">QUOTA (EM MB)</label>
                        <input type="number" name="quota_mb" class="form-control" value="1024" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="submit" name="btn_cadastrar_usuario" class="btn btn-primary w-100 py-2 rounded-pill fw-bold">CRIAR CONTA</button>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
<script> $(document).ready(function(){ $('#cpf_admin').mask('000.000.000-00'); }); </script>
</body>
</html>