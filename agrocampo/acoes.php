<?php
/**
 * BDSoft Workspace - AGRO CAMPO (AÇÕES)
 * Local: agrocampo/acoes.php
 */
session_start();

// Ativar erros
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../config.php';

if (!isset($_SESSION['usuario_id'])) { exit("Sessão expirada."); }
$user_id = $_SESSION['usuario_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'];

    if ($acao === 'novo_talhao') {
        $nome = trim($_POST['nome']);
        $area = $_POST['area_ha'];
        $cultura = trim($_POST['cultura']) ?: 'Vazio';

        try {
            $stmt = $pdo->prepare("INSERT INTO agro_talhoes (nome, area_ha, cultura_atual, usuario_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$nome, $area, $cultura, $user_id]);
            
            if (function_exists('registrarLog')) {
                registrarLog($pdo, $user_id, "Agro", "Mapeou novo talhão: $nome");
            }
            
            header("Location: index.php");
            exit;
        } catch (PDOException $e) {
            die("Erro ao salvar talhão: " . $e->getMessage());
        }
    }
}

// Ação de Deletar
if (isset($_GET['del_talhao'])) {
    $id = (int)$_GET['del_talhao'];
    $stmt = $pdo->prepare("DELETE FROM agro_talhoes WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$id, $user_id]);
    header("Location: index.php");
    exit;
}