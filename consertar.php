<?php
// consertar.php
require_once 'config.php';

// Dados que queremos definir
$usuario_alvo = 'Administrator';
$cpf_alvo     = '000.000.000-00';
$senha_pura   = 'Fckgw!151289';
$novo_hash    = password_hash($senha_pura, PASSWORD_DEFAULT);

try {
    // 1. Primeiro, tentamos localizar o usuário pelo CPF (que causou o erro)
    $stmt = $pdo->prepare("SELECT id, usuario FROM usuarios WHERE cpf = ? OR usuario = ?");
    $stmt->execute([$cpf_alvo, $usuario_alvo]);
    $user = $stmt->fetch();

    if ($user) {
        // Se encontrou, vamos atualizar esse registro específico pelo ID
        $update = $pdo->prepare("UPDATE usuarios SET 
            usuario = ?, 
            senha = ?, 
            nivel = 'admin', 
            status = 'ativo',
            cpf = ?
            WHERE id = ?");
        $update->execute([$usuario_alvo, $novo_hash, $cpf_alvo, $user['id']]);
        
        echo "✅ Usuário localizado (ID: {$user['id']}) e atualizado com sucesso!";
        echo "<br>Novo Usuário: <b>$usuario_alvo</b>";
        echo "<br>Nova Senha: <b>$senha_pura</b>";
    } else {
        // Se não encontrou ninguém com esse CPF ou Nome, vamos criar do zero
        $insert = $pdo->prepare("INSERT INTO usuarios (nome, cpf, usuario, senha, nivel, status, quota_limite) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $insert->execute(['Administrador', $cpf_alvo, $usuario_alvo, $novo_hash, 'admin', 'ativo', 10737418240]);
        echo "✅ Usuário $usuario_alvo criado do zero com sucesso!";
    }

    echo "<br><br><a href='login.php'>Clique aqui para tentar o Login agora</a>";

} catch (PDOException $e) {
    echo "❌ Erro persistente: " . $e->getMessage();
}
?>