<?php
/**
 * BDSoft Workspace - AÇÕES DE PASTAS (CORRIGIDO)
 * Local: pastas_acoes.php
 */

// Ativar exibição de erros para debug (pode desativar após testar)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'config.php';

if (!isset($_SESSION['usuario_id'])) {
    die("Erro: Sessão expirada. Faça login novamente.");
}

$user_id = $_SESSION['usuario_id'];

/**
 * FUNÇÃO RECURSIVA: Excluir Conteúdo de Pasta
 */
function excluirPastaRecursiva($pdo, $id_pasta, $id_usuario) {
    // 1. Apagar arquivos físicos e registros
    $stmtArqs = $pdo->prepare("SELECT nome_sistema FROM arquivos WHERE pasta_id = ? AND usuario_id = ?");
    $stmtArqs->execute([$id_pasta, $id_usuario]);
    while ($arq = $stmtArqs->fetch(PDO::FETCH_ASSOC)) {
        $caminho = "uploads/user_" . $id_usuario . "/" . $arq['nome_sistema'];
        if (file_exists($caminho)) unlink($caminho);
    }
    $pdo->prepare("DELETE FROM arquivos WHERE pasta_id = ? AND usuario_id = ?")->execute([$id_pasta, $id_usuario]);

    // 2. Buscar subpastas e repetir
    $stmtSubs = $pdo->prepare("SELECT id FROM pastas WHERE pai_id = ? AND usuario_id = ?");
    $stmtSubs->execute([$id_pasta, $id_usuario]);
    while ($sub = $stmtSubs->fetch(PDO::FETCH_ASSOC)) {
        excluirPastaRecursiva($pdo, $sub['id'], $id_usuario);
    }

    // 3. Deletar a pasta
    $pdo->prepare("DELETE FROM pastas WHERE id = ? AND usuario_id = ?")->execute([$id_pasta, $id_usuario]);
}

// =========================================================================
// 1. AÇÃO: CRIAR NOVA PASTA
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'criar_pasta') {
    $nome = trim($_POST['nome_pasta']);
    $pai_id = (!empty($_POST['pai_id']) && $_POST['pai_id'] !== 'null') ? (int)$_POST['pai_id'] : null;

    if (!empty($nome)) {
        try {
            $sql = "INSERT INTO pastas (nome, usuario_id, pai_id, data_criacao) VALUES (?, ?, ?, NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nome, $user_id, $pai_id]);

            if (function_exists('registrarLog')) {
                registrarLog($pdo, $user_id, "Criar Pasta", "Pasta criada: $nome");
            }

            header("Location: dashboard.php" . ($pai_id ? "?pasta=$pai_id" : ""));
            exit;
        } catch (PDOException $e) {
            die("Erro SQL ao criar pasta: " . $e->getMessage());
        }
    } else {
        die("Erro: O nome da pasta não pode estar vazio.");
    }
}

// =========================================================================
// 2. AÇÃO: RENOMEAR PASTA
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'renomear_pasta') {
    $id_pasta = (int)$_POST['pasta_id'];
    $novo_nome = trim($_POST['novo_nome']);

    if ($id_pasta && !empty($novo_nome)) {
        try {
            $stmt = $pdo->prepare("UPDATE pastas SET nome = ? WHERE id = ? AND usuario_id = ?");
            $stmt->execute([$novo_nome, $id_pasta, $user_id]);

            // Descobrir pai_id para voltar para onde estava
            $stmtP = $pdo->prepare("SELECT pai_id FROM pastas WHERE id = ?");
            $stmtP->execute([$id_pasta]);
            $pai_id = $stmtP->fetchColumn();

            header("Location: dashboard.php" . ($pai_id ? "?pasta=$pai_id" : ""));
            exit;
        } catch (PDOException $e) {
            die("Erro SQL ao renomear: " . $e->getMessage());
        }
    }
}

// =========================================================================
// 3. DRAG & DROP (MOVER)
// =========================================================================
if (isset($_GET['mover_arq']) && isset($_GET['para_pasta'])) {
    $arq_id = (int)$_GET['mover_arq'];
    $destino = (int)$_GET['para_pasta'];
    $destino = ($destino === 0) ? null : $destino;

    $stmt = $pdo->prepare("UPDATE arquivos SET pasta_id = ? WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$destino, $arq_id, $user_id]);
    echo "Sucesso";
    exit;
}

if (isset($_GET['mover_pasta']) && isset($_GET['para_pasta'])) {
    $origem = (int)$_GET['mover_pasta'];
    $destino = (int)$_GET['para_pasta'];
    $destino = ($destino === 0) ? null : $destino;

    if ($origem !== $destino) {
        $stmt = $pdo->prepare("UPDATE pastas SET pai_id = ? WHERE id = ? AND usuario_id = ?");
        $stmt->execute([$destino, $origem, $user_id]);
    }
    echo "Sucesso";
    exit;
}

// =========================================================================
// 4. AÇÃO: EXCLUIR PASTA
// =========================================================================
if (isset($_GET['del_pasta'])) {
    $id_pasta = (int)$_GET['del_pasta'];

    try {
        $stmtP = $pdo->prepare("SELECT pai_id FROM pastas WHERE id = ? AND usuario_id = ?");
        $stmtP->execute([$id_pasta, $user_id]);
        $pai_id = $stmtP->fetchColumn();

        excluirPastaRecursiva($pdo, $id_pasta, $user_id);

        header("Location: dashboard.php" . ($pai_id ? "?pasta=$pai_id" : ""));
        exit;
    } catch (PDOException $e) {
        die("Erro ao excluir pasta: " . $e->getMessage());
    }
}

// Se chegar aqui sem nenhuma ação, volta para o dashboard
header("Location: dashboard.php");
exit;