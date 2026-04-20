<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['usuario_id']) || !isset($_GET['id'])) {
    die("Acesso negado.");
}

$user_id = $_SESSION['usuario_id'];
$arquivo_id = (int)$_GET['id'];

// Busca os detalhes do arquivo no banco
$stmt = $pdo->prepare("SELECT * FROM arquivos WHERE id = ? AND usuario_id = ?");
$stmt->execute([$arquivo_id, $user_id]);
$arquivo = $stmt->fetch();

if (!$arquivo) {
    die("Arquivo não encontrado ou acesso negado.");
}

$caminho_arquivo = "uploads/user_" . $user_id . "/" . $arquivo['nome_sistema'];

if (file_exists($caminho_arquivo)) {
    // Configura os headers para forçar o download
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $arquivo['nome_original'] . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($caminho_arquivo));
    
    // Limpa o buffer de saída para evitar corrupção de arquivos grandes
    ob_clean();
    flush();
    
    readfile($caminho_arquivo);
    exit;
} else {
    die("Erro: O arquivo físico não foi encontrado no servidor.");
}