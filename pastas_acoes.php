<?php
/**
 * SISTEMA DE DRIVE - AÇÕES DE PASTAS
 * Local: pastas_acoes.php
 */

session_start();
require_once 'config.php';

// 1. Verificação de Segurança
if (!isset($_SESSION['usuario_id'])) {
    die("Acesso negado.");
}

$user_id = $_SESSION['usuario_id'];

// --- AÇÃO: CRIAR NOVA PASTA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nome_pasta'])) {
    $nome = trim($_POST['nome_pasta']);
    
    // Captura o pai_id (se estiver dentro de outra pasta)
    $pai_id = (!empty($_POST['pai_id']) && $_POST['pai_id'] !== 'null') ? (int)$_POST['pai_id'] : null;

    if (!empty($nome)) {
        try {
            $sql = "INSERT INTO pastas (nome, usuario_id, pai_id, data_criacao) VALUES (?, ?, ?, NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nome, $user_id, $pai_id]);

            // Registrar Log
            if (function_exists('registrarLog')) {
                registrarLog($pdo, $user_id, "Criar Pasta", "Criou a pasta: $nome");
            }

            // Redireciona de volta para onde o usuário estava
            $url_retorno = "dashboard.php" . ($pai_id ? "?pasta=$pai_id" : "");
            header("Location: $url_retorno");
            exit;

        } catch (PDOException $e) {
            die("Erro ao criar pasta no banco de dados: " . $e->getMessage());
        }
    }
}

// --- AÇÃO: MOVER ARQUIVO PARA PASTA (VIA AJAX/DRAG & DROP) ---
if (isset($_GET['mover_arq']) && isset($_GET['para_pasta'])) {
    $arq_id = (int)$_GET['mover_arq'];
    $pasta_destino = (int)$_GET['para_pasta'];

    try {
        $stmt = $pdo->prepare("UPDATE arquivos SET pasta_id = ? WHERE id = ? AND usuario_id = ?");
        $stmt->execute([$pasta_destino, $arq_id, $user_id]);
        echo "OK";
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        exit;
    }
}

// --- AÇÃO: MOVER PASTA PARA DENTRO DE OUTRA (SUBPASTA) ---
if (isset($_GET['mover_pasta']) && isset($_GET['para_pasta'])) {
    $pasta_origem = (int)$_GET['mover_pasta'];
    $pasta_destino = (int)$_GET['para_pasta'];

    // Impede mover a pasta para dentro dela mesma
    if ($pasta_origem === $pasta_destino) {
        http_response_code(400);
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE pastas SET pai_id = ? WHERE id = ? AND usuario_id = ?");
        $stmt->execute([$pasta_destino, $pasta_origem, $user_id]);
        echo "OK";
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        exit;
    }
}

// --- AÇÃO: EXCLUIR PASTA ---
if (isset($_GET['del_pasta'])) {
    $id_pasta = (int)$_GET['del_pasta'];

    try {
        // 1. Buscar arquivos para deletar fisicamente
        $stmtF = $pdo->prepare("SELECT nome_sistema FROM arquivos WHERE pasta_id = ? AND usuario_id = ?");
        $stmtF->execute([$id_pasta, $user_id]);
        $arquivos = $stmtF->fetchAll();

        foreach ($arquivos as $arq) {
            $caminho = "uploads/user_{$user_id}/" . $arq['nome_sistema'];
            if (file_exists($caminho)) {
                unlink($caminho);
            }
        }

        // 2. Deletar registros da pasta (Arquivos e a Pasta em si)
        // Nota: Se houver subpastas, elas ficarão "órfãs" ou você pode implementar o DELETE CASCADE no banco.
        $pdo->prepare("DELETE FROM arquivos WHERE pasta_id = ? AND usuario_id = ?")->execute([$id_pasta, $user_id]);
        $pdo->prepare("DELETE FROM pastas WHERE id = ? AND usuario_id = ?")->execute([$id_pasta, $user_id]);

        header("Location: dashboard.php");
        exit;

    } catch (Exception $e) {
        die("Erro ao excluir pasta.");
    }
}