<?php
/**
 * BDSoft Workspace - Minhas Economias
 * Controlador Principal
 */
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../../login.php");
    exit;
}

require_once '../../config.php'; 
$usuario_id = $_SESSION['usuario_id'];

// --- 1. FILTROS GLOBAIS ---
$mes_filtro = $_GET['mes'] ?? date('m');
$ano_filtro = $_GET['ano'] ?? date('Y');
$visao_atual = $_SESSION['me_visao'] ?? 'ambos';

// --- 2. PROCESSAMENTO DE AÇÕES (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // AÇÃO: SALVAR / EDITAR TRANSAÇÃO
    if (isset($_POST['btn_salvar_transacao'])) {
        $id_t = $_POST['id_transacao'] ?? null;
        $data = $_POST['data_transacao'];
        $desc = trim($_POST['descricao']);
        $cat_id = (int)$_POST['categoria_id'];
        $conta_id = (int)$_POST['conta_id'];
        $valor = str_replace(['.', ','], ['', '.'], $_POST['valor']);
        $tipo = $_POST['tipo_transacao'];
        $origem_pj = ($visao_atual == 'pj') ? 1 : 0;

        if ($id_t) {
            $sql = "UPDATE minhaseconomias_movimentacoes SET conta_id=?, categoria_id=?, descricao=?, valor=?, data_vencimento=?, data_pagamento=?, tipo=? WHERE id=? AND usuario_id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$conta_id, $cat_id, $desc, $valor, $data, $data, $tipo, $id_t, $usuario_id]);
        } else {
            $sql = "INSERT INTO minhaseconomias_movimentacoes (usuario_id, conta_id, categoria_id, descricao, valor, data_vencimento, data_pagamento, status, tipo, origem_pj) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pago', ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$usuario_id, $conta_id, $cat_id, $desc, $valor, $data, $data, $tipo, $origem_pj]);
        }
        header("Location: index.php?p=transacoes&mes=$mes_filtro&ano=$ano_filtro");
        exit;
    }

    // AÇÃO: EXCLUIR TRANSAÇÃO
    if (isset($_POST['btn_excluir_transacao'])) {
        $stmt = $pdo->prepare("DELETE FROM minhaseconomias_movimentacoes WHERE id = ? AND usuario_id = ?");
        $stmt->execute([(int)$_POST['id_transacao_excluir'], $usuario_id]);
        header("Location: index.php?p=transacoes&success=deletado");
        exit;
    }

    // AÇÕES DE CONTA
    if (isset($_POST['btn_salvar_conta'])) {
        $nome = trim($_POST['nome']);
        $valor = str_replace(',', '.', $_POST['valor_inicial']);
        $status = isset($_POST['status']) ? 1 : 0;
        $tipo = $_POST['tipo'] ?? 'Carteira';
        $id_c = $_POST['id_conta'] ?? null;
        if ($id_c) {
            $stmt = $pdo->prepare("UPDATE minhaseconomias_contas SET nome=?, saldo_inicial=?, status=?, tipo=? WHERE id=? AND usuario_id=?");
            $stmt->execute([$nome, $valor, $status, $tipo, $id_c, $usuario_id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO minhaseconomias_contas (usuario_id, nome, tipo, saldo_inicial, status, cor) VALUES (?,?,?,?,?,?)");
            $stmt->execute([$usuario_id, $nome, $tipo, $valor, $status, '#1a73e8']);
        }
        header("Location: index.php?p=dashboard"); exit;
    }
}

$pagina = $_GET['p'] ?? 'dashboard';
include '../includes/header.php';

switch ($pagina) {
    case 'dashboard': include '../views/dashboard.php'; break;
    case 'transacoes': include '../views/transacoes.php'; break;
    case 'categorias': include '../views/categorias.php'; break;
    default: include '../views/dashboard.php'; break;
}

include '../includes/footer.php';