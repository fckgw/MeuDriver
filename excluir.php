<?php
include 'config.php';

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    // Buscar dados do arquivo para apagar o arquivo físico
    $stmt = $pdo->prepare("SELECT nome_sistema FROM arquivos WHERE id = ?");
    $stmt->execute([$id]);
    $arquivo = $stmt->fetch();

    if ($arquivo) {
        $caminho = "uploads/" . $arquivo['nome_sistema'];
        
        // Apaga o arquivo da pasta
        if (file_exists($caminho)) {
            unlink($caminho);
        }

        // Apaga o registro do banco
        $stmt = $pdo->prepare("DELETE FROM arquivos WHERE id = ?");
        $stmt->execute([$id]);
    }
}

header("Location: index.php");
?>