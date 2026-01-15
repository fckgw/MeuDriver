<?php
session_start();
include 'config.php';

if (!isset($_SESSION['usuario_id'])) { die("Acesso negado"); }

$user_id = $_SESSION['usuario_id'];

// Verifica se recebeu os IDs via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ids'])) {
    $ids = $_POST['ids']; // Array de IDs selecionados

    if (!empty($ids)) {
        // Criar uma string de interrogações para o SQL (ex: ?,?,?)
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        // 1. Buscar nomes dos arquivos para apagar do HD
        $sql = "SELECT nome_sistema FROM arquivos WHERE usuario_id = ? AND id IN ($placeholders)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge([$user_id], $ids));
        $arquivos = $stmt->fetchAll();

        foreach ($arquivos as $arq) {
            $caminho = "uploads/" . $arq['nome_sistema'];
            if (file_exists($caminho)) {
                unlink($caminho); // Apaga o arquivo físico
            }
        }

        // 2. Apagar do Banco de Dados
        $sqlDel = "DELETE FROM arquivos WHERE usuario_id = ? AND id IN ($placeholders)";
        $stmtDel = $pdo->prepare($sqlDel);
        $stmtDel->execute(array_merge([$user_id], $ids));

        echo "Sucesso";
    }
}