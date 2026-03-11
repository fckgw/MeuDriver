<?php
/**
 * BDSoft Workspace - AÇÕES DE PASTAS
 * Localização: pastas_acoes.php
 */

session_start();
require_once 'config.php';

if (!isset($_SESSION['usuario_id'])) { exit; }
$user_id = $_SESSION['usuario_id'];

// --- CRIAR NOVA PASTA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'criar_pasta') {
    $nome = trim($_POST['nome_pasta']);
    $pai_id = (!empty($_POST['pai_id']) && $_POST['pai_id'] !== 'null') ? (int)$_POST['pai_id'] : null;
    if (!empty($nome)) {
        $stmt = $pdo->prepare("INSERT INTO pastas (nome, usuario_id, pai_id, data_criacao) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$nome, $user_id, $pai_id]);
    }
    header("Location: dashboard.php" . ($pai_id ? "?pasta=$pai_id" : ""));
    exit;
}

// --- RENOMEAR PASTA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'renomear_pasta') {
    $id = (int)$_POST['pasta_id'];
    $novo_nome = trim($_POST['novo_nome']);
    if ($id && !empty($novo_nome)) {
        $stmt = $pdo->prepare("UPDATE pastas SET nome = ? WHERE id = ? AND usuario_id = ?");
        $stmt->execute([$novo_nome, $id, $user_id]);
    }
    // Volta para onde estava
    $stmtP = $pdo->prepare("SELECT pai_id FROM pastas WHERE id = ?");
    $stmtP->execute([$id]);
    $pai = $stmtP->fetchColumn();
    header("Location: dashboard.php" . ($pai ? "?pasta=$pai" : ""));
    exit;
}

// --- MOVER ARQUIVO (VIA AJAX / DRAG & DROP) ---
if (isset($_GET['mover_arq']) && isset($_GET['para_pasta'])) {
    $id = (int)$_GET['mover_arq'];
    $destino = ($_GET['para_pasta'] === '0' || $_GET['para_pasta'] === 'null') ? null : (int)$_GET['para_pasta'];
    $stmt = $pdo->prepare("UPDATE arquivos SET pasta_id = ? WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$destino, $id, $user_id]);
    echo "Sucesso";
    exit;
}

// --- MOVER PASTA (VIA AJAX / DRAG & DROP) ---
if (isset($_GET['mover_pasta']) && isset($_GET['para_pasta'])) {
    $id = (int)$_GET['mover_pasta'];
    $destino = ($_GET['para_pasta'] === '0' || $_GET['para_pasta'] === 'null') ? null : (int)$_GET['para_pasta'];
    if ($id !== $destino) {
        $stmt = $pdo->prepare("UPDATE pastas SET pai_id = ? WHERE id = ? AND usuario_id = ?");
        $stmt->execute([$destino, $id, $user_id]);
    }
    echo "Sucesso";
    exit;
}

// --- EXCLUIR PASTA (RECURSIVO) ---
if (isset($_GET['del_pasta'])) {
    $id = (int)$_GET['del_pasta'];
    $stmtP = $pdo->prepare("SELECT pai_id FROM pastas WHERE id = ? AND usuario_id = ?");
    $stmtP->execute([$id, $user_id]);
    $pai = $stmtP->fetchColumn();

    // Função interna simples para recursão
    function apagarRecursivo($pdo, $f_id, $u_id) {
        $st = $pdo->prepare("SELECT nome_sistema FROM arquivos WHERE pasta_id = ? AND usuario_id = ?");
        $st->execute([$f_id, $u_id]);
        while($a = $st->fetch()) {
            @unlink("uploads/user_$u_id/".$a['nome_sistema']);
        }
        $pdo->prepare("DELETE FROM arquivos WHERE pasta_id = ? AND usuario_id = ?")->execute([$f_id, $u_id]);
        $st2 = $pdo->prepare("SELECT id FROM pastas WHERE pai_id = ? AND usuario_id = ?");
        $st2->execute([$f_id, $u_id]);
        while($s = $st2->fetch()) { apagarRecursivo($pdo, $s['id'], $u_id); }
        $pdo->prepare("DELETE FROM pastas WHERE id = ? AND usuario_id = ?")->execute([$f_id, $u_id]);
    }

    apagarRecursivo($pdo, $id, $user_id);
    header("Location: dashboard.php" . ($pai ? "?pasta=$pai" : ""));
    exit;
}