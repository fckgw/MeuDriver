<?php
include 'config.php';

$usuario = 'Administrator';
$senha_pura = 'Fckgw!151289';
$senha_hash = password_hash($senha_pura, PASSWORD_DEFAULT);

try {
    // Limpa se já existir e cria de novo
    $pdo->prepare("DELETE FROM usuarios WHERE usuario = ?")->execute([$usuario]);
    
    $sql = "INSERT INTO usuarios (nome, usuario, senha) VALUES (?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['Administrador', $usuario, $senha_hash]);

    echo "<h3>Usuário configurado com sucesso!</h3>";
    echo "Usuário: " . $usuario . "<br>";
    echo "Senha: " . $senha_pura . "<br>";
    echo "<br><a href='login.php'>Ir para o Login</a>";
    
    // Deleta o próprio arquivo por segurança após rodar
    // unlink(__FILE__); 
} catch (Exception $e) {
    die("Erro ao criar usuário: " . $e->getMessage());
}
?>