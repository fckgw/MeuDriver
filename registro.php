<?php
/**
 * BDSoft Workspace - CADASTRO COMPLETO COM TERMOS E SMTP
 * Localização: public_html/registro.php
 */
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Carregamento da biblioteca PHPMailer
require 'includes/PHPMailer/Exception.php';
require 'includes/PHPMailer/PHPMailer.php';
require 'includes/PHPMailer/SMTP.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'config.php';

$mensagem_erro = "";
$sucesso = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome    = trim($_POST['nome']);
    $cpf     = trim($_POST['cpf']);
    $rg      = trim($_POST['rg']);
    $email   = trim($_POST['email']);
    $cupom   = trim($_POST['cupom']);
    $aceite  = isset($_POST['termo_aceite']) ? true : false;

    try {
        // 1. Validar se CPF ou E-mail já existem (Evita o erro de Duplicate Entry 1062)
        $stmt_check = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = ? OR cpf = ? LIMIT 1");
        $stmt_check->execute([$email, $cpf]);

        if ($stmt_check->rowCount() > 0) {
            $mensagem_erro = "ALERTA: Já existe um cadastro com este E-mail ou CPF no sistema.";
        } elseif (!$aceite) {
            $mensagem_erro = "Você precisa ler e aceitar os termos de confiabilidade.";
        } else {
            // 2. Gerar Senha Temporária Aleatória
            $senha_temp = substr(str_shuffle("abcdefghjkmnpqrstuvwxyz23456789"), 0, 8);
            $senha_hash = password_hash($senha_temp, PASSWORD_DEFAULT);

            $pdo->beginTransaction();

            // 3. Inserir Usuário no Banco (trocar_senha = 1 força a troca no primeiro login)
            // Nota: Adicionamos o campo 'cpf' e 'rg' que seu banco exige
            $sql = "INSERT INTO usuarios (nome, cpf, rg, usuario, senha, trocar_senha, data_criacao, nivel, status, espaco_gb) 
                    VALUES (?, ?, ?, ?, ?, 1, NOW(), 'membro', 'ativo', 5)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nome, $cpf, $rg, $email, $senha_hash]);
            $novo_id = $pdo->lastInsertId();

            // 4. Enviar E-mail via SMTP (Configurações BDSoft / Office 365)
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
            $mail->addAddress($email, $nome);

            $mail->isHTML(true);
            $mail->Subject = 'Seu Acesso ao Workspace Drive';
            $mail->Body    = "
                <div style='font-family: sans-serif; color: #333; max-width: 600px; border: 1px solid #eee; padding: 20px; border-radius: 15px;'>
                    <h2 style='color: #1a73e8;'>Bem-vindo, $nome!</h2>
                    <p>Sua conta no <strong>Workspace Drive</strong> foi criada.</p>
                    <p>Para sua segurança, sua senha é temporária e você deverá alterá-la no seu primeiro acesso.</p>
                    
                    <div style='background: #f8f9fa; padding: 15px; border-radius: 10px; border-left: 5px solid #1a73e8; margin: 20px 0;'>
                        <strong>URL do Painel:</strong> <a href='https://workspace.bdsoft.com.br'>workspace.bdsoft.com.br</a><br>
                        <strong>Seu Usuário:</strong> $email<br>
                        <strong>Senha Temporária:</strong> <span style='font-size: 18px; color: #d93025; font-weight: bold;'>$senha_temp</span>
                    </div>

                    <p style='font-size: 12px; color: #777;'>Se você não solicitou este cadastro, por favor ignore este e-mail.</p>
                    <hr style='border: 0; border-top: 1px solid #eee;'>
                    <p style='font-size: 11px; color: #999; text-align: center;'>BDSoftech Cloud Services</p>
                </div>";

            $mail->send();
            $pdo->commit();
            $sucesso = true;
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $mensagem_erro = "Falha no processo: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Conta - Workspace Drive</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Segoe UI', system-ui, sans-serif; padding: 20px; }
        .reg-card { width: 100%; max-width: 500px; background: #ffffff; padding: 40px; border-radius: 24px; box-shadow: 0 20px 40px rgba(0,0,0,0.06); border: 1px solid #f0f0f0; }
        .brand-icon { color: #1a73e8; font-size: 3rem; margin-bottom: 0.5rem; }
        .form-label { font-size: 0.75rem; font-weight: 700; color: #5f6368; text-transform: uppercase; margin-bottom: 5px; }
        .form-control { padding: 12px; border-radius: 12px; border: 1px solid #dadce0; font-size: 0.95rem; }
        .form-control:focus { border-color: #1a73e8; box-shadow: 0 0 0 4px rgba(26,115,232,0.1); }
        .btn-primary { padding: 14px; font-weight: 700; border-radius: 12px; background-color: #1a73e8; border: none; transition: 0.3s; }
        .btn-primary:hover { background-color: #1557b0; transform: translateY(-1px); }
        .btn-primary:disabled { background-color: #bdc1c6; transform: none; }
        .termo-link { color: #1a73e8; text-decoration: none; font-weight: 600; cursor: pointer; }
        .termo-link:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="reg-card">
    <div class="text-center mb-4">
        <div class="brand-icon"><i class="fas fa-cloud"></i></div>
        <h3 class="fw-bold">Workspace <span class="text-primary">Drive</span></h3>
        <p class="text-muted small">Crie sua conta e receba seu acesso por e-mail</p>
    </div>

    <?php if($sucesso): ?>
        <div class="alert alert-success text-center border-0 shadow-sm p-4 rounded-4">
            <i class="fas fa-paper-plane fa-3x mb-3"></i>
            <h5 class="fw-bold">Cadastro Realizado!</h5>
            <p class="small">Sua senha temporária foi enviada para:<br><strong><?php echo htmlspecialchars($email); ?></strong></p>
            <hr>
            <p class="small text-muted">Verifique também sua caixa de <b>Spam</b>.</p>
            <a href="login.php" class="btn btn-primary w-100 rounded-pill mt-2">IR PARA O LOGIN</a>
        </div>
    <?php else: ?>
        
        <?php if($mensagem_erro): ?>
            <div class="alert alert-danger py-2 small text-center border-0 shadow-sm mb-4">
                <i class="fas fa-exclamation-circle me-1"></i> <?php echo $mensagem_erro; ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="formCadastro">
            <div class="mb-3">
                <label class="form-label">Nome Completo</label>
                <input type="text" name="nome" class="form-control" placeholder="Ex: João da Silva" required value="<?php echo $_POST['nome'] ?? ''; ?>">
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">CPF</label>
                    <input type="text" name="cpf" id="cpf" class="form-control" placeholder="000.000.000-00" required value="<?php echo $_POST['cpf'] ?? ''; ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">RG</label>
                    <input type="text" name="rg" id="rg" class="form-control" placeholder="00.000.000-0" required value="<?php echo $_POST['rg'] ?? ''; ?>">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Seu Melhor E-mail</label>
                <input type="email" name="email" class="form-control" placeholder="nome@provedor.com" required value="<?php echo $_POST['email'] ?? ''; ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Cupom de Bonus (Opcional)</label>
                <input type="text" name="cupom" class="form-control" placeholder="Digite o código" value="<?php echo $_POST['cupom'] ?? ''; ?>">
            </div>

            <div class="mb-4">
                <div class="form-check small">
                    <input class="form-check-input" type="checkbox" name="termo_aceite" id="checkTermo" required>
                    <label class="form-check-label text-muted" for="checkTermo">
                        Li e concordo com o <span class="termo-link" data-bs-toggle="modal" data-bs-target="#modalTermos">Termo de Confiabilidade</span>
                    </label>
                </div>
            </div>

            <button type="submit" id="btnRegistrar" class="btn btn-primary w-100 shadow-sm text-uppercase">Finalizar e Receber Senha</button>
            
            <div class="text-center mt-4">
                <p class="small text-muted">Já tem acesso? <a href="login.php" class="text-decoration-none fw-bold text-primary">Fazer Login</a></p>
            </div>
        </form>
    <?php endif; ?>
</div>

<!-- MODAL DOS TERMOS -->
<div class="modal fade" id="modalTermos" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 bg-light">
                <h5 class="modal-title fw-bold text-dark"><i class="fas fa-file-contract text-primary me-2"></i> Termos e Privacidade</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 text-muted" style="font-size: 0.85rem; line-height: 1.6;">
                <p class="fw-bold text-dark">Projeto: Workspace Drive / Mercado Inteligente</p>
                <p class="fw-bold text-dark">Empresa Desenvolvedora: BDSoftech Cloud</p>
                <hr>
                <h6>1. APRESENTAÇÃO</h6>
                <p>O presente documento estabelece as diretrizes legais e éticas para o uso do sistema Workspace Drive, assegurando transparência no tratamento de dados pessoais em conformidade com a Lei Geral de Proteção de Dados (LGPD - Lei nº 13.709/2018).</p>

                <h6>2. DEFINIÇÕES</h6>
                <p>Usuário: pessoa física que utiliza o ecossistema Workspace Drive.<br>
                Dados Pessoais: informações que identifiquem uma pessoa física.</p>

                <h6>3. COLETA DE DADOS</h6>
                <p>O sistema coleta: Nome, CPF, RG, E-mail, Telefone para fins de autenticação, segurança de conta e faturamento.</p>

                <h6>4. FINALIDADE</h6>
                <p>Os dados serão usados para cadastro, autenticação, controle de quota de armazenamento e análise inteligente de mercado regional.</p>

                <h6>5. COMPARTILHAMENTO</h6>
                <p>A BDSoftech NÃO comercializa dados pessoais com terceiros. O acesso é restrito à administração do sistema e ao próprio usuário.</p>

                <h6>6. ACEITE</h6>
                <p>Ao clicar em “ACEITO” e prosseguir com o cadastro, o usuário concorda integralmente com estes termos.</p>

                <div class="bg-light p-3 rounded-3 mt-3">
                    <small>Dúvidas: suporte@bdsoft.com.br | (31) 97195-7751</small><br>
                    <small>Siga-nos: <a href="https://www.instagram.com/bdsoftech/" target="_blank">@bdsoftech</a></small>
                </div>
            </div>
            <div class="modal-footer border-0 p-3">
                <button type="button" class="btn btn-primary w-100 rounded-pill fw-bold" data-bs-dismiss="modal" onclick="document.getElementById('checkTermo').checked = true;">LI E ACEITO OS TERMOS</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    $(document).ready(function(){
        $('#cpf').mask('000.000.000-00');
        $('#rg').mask('00.000.000-0');

        // Impede cliques múltiplos no botão de registro
        $('#formCadastro').on('submit', function() {
            $('#btnRegistrar').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i> PROCESSANDO...');
        });
    });
</script>
</body>
</html>