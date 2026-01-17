<?php
// Configurações do seu novo e-mail
$smtp_host = "email-ssl.com.br"; // Servidor SMTP padrão Locaweb
$smtp_user = "suporte@bdsoft.com.br";
$smtp_pass = "Fckgw!151289";
$smtp_port = 587;

$para = "souzafelipe@bdsoft.com.br";
$assunto = "Teste de Autenticação BDSoft Workspace";
$mensagem = "Se você recebeu este e-mail, a configuração SMTP está funcionando perfeitamente!";

// No PHP Puro, para usar SMTP autenticado de forma simples e profissional, 
// o ideal é usarmos a biblioteca PHPMailer. 
// Mas vou tentar primeiro o envio via mail() com o parâmetro de envelope da Locaweb:

$headers = "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
$headers .= "From: BDSoft Workspace <$smtp_user>" . "\r\n";
$headers .= "Reply-To: $smtp_user" . "\r\n";

if(mail($para, $assunto, $mensagem, $headers, "-f" . $smtp_user)){
    echo "✅ Solicitação de e-mail enviada. Verifique suporte@bdsoft.com.br";
} else {
    echo "❌ Falha no envio. Verifique se o e-mail suporte@bdsoft.com.br já está ativo no painel.";
}
?>