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

// Filtros Globais de Data (Sincronizados)
$mes_filtro = isset($_GET['mes']) ? str_pad($_GET['mes'], 2, "0", STR_PAD_LEFT) : date('m');
$ano_filtro = $_GET['ano'] ?? date('Y');
$_SESSION['me_visao'] = 'pf'; // Forçado PF conforme pedido
$visao_atual = 'pf';

// --- PROCESSAMENTO DE AÇÕES (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. SALVAR / EDITAR CONTA
    if (isset($_POST['btn_salvar_conta'])) {
        $nome = trim($_POST['nome']);
        $valor = str_replace(',', '.', $_POST['valor_inicial']);
        $status = isset($_POST['status']) ? 1 : 0;
        $tipo = $_POST['tipo'] ?? 'Carteira';
        $id_c = $_POST['id_conta'] ?? null;
        if ($id_c) {
            $stmt = $pdo->prepare("UPDATE minhaseconomias_contas SET nome=?, saldo_inicial=?, status=?, tipo=? WHERE id=? AND usuario_id=?");
            $stmt->execute([$nome, $valor, $status, $tipo, $id_c, $usuario_id]);
            $res = "conta_ok";
        } else {
            $stmt = $pdo->prepare("INSERT INTO minhaseconomias_contas (usuario_id, nome, tipo, saldo_inicial, status, cor) VALUES (?,?,?,?,?,?)");
            $stmt->execute([$usuario_id, $nome, $tipo, $valor, $status, '#1a73e8']);
            $res = "conta_ok";
        }
        header("Location: index.php?p=dashboard&success=$res&mes=$mes_filtro&ano=$ano_filtro"); exit;
    }

    // 2. EXCLUIR CONTA
    if (isset($_POST['btn_excluir_conta'])) {
        $pdo->prepare("DELETE FROM minhaseconomias_contas WHERE id = ? AND usuario_id = ?")->execute([(int)$_POST['id_conta_excluir'], $usuario_id]);
        header("Location: index.php?p=dashboard&success=conta_excluida&mes=$mes_filtro&ano=$ano_filtro"); exit;
    }

    // 3. SALVAR / EDITAR TRANSAÇÃO
    if (isset($_POST['btn_salvar_transacao'])) {
        $id_t = $_POST['id_transacao'] ?? null;
        $data_venc = $_POST['data_transacao'];
        $desc = trim($_POST['descricao']);
        $cat_id = (int)$_POST['categoria_id'];
        $conta_id = (int)$_POST['conta_id'];
        $valor = str_replace(['.', ','], ['', '.'], $_POST['valor']);
        $tipo = $_POST['tipo_transacao'];
        $status_t = $_POST['status_transacao'] ?? 'Futuro';
        $data_pagto = ($status_t == 'Pago') ? $data_venc : null;

        if ($id_t) {
            $sql = "UPDATE minhaseconomias_movimentacoes SET conta_id=?, categoria_id=?, descricao=?, valor=?, data_vencimento=?, data_pagamento=?, status=?, tipo=? WHERE id=? AND usuario_id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$conta_id, $cat_id, $desc, $valor, $data_venc, $data_pagto, $status_t, $tipo, $id_t, $usuario_id]);
        } else {
            $sql = "INSERT INTO minhaseconomias_movimentacoes (usuario_id, conta_id, categoria_id, descricao, valor, data_vencimento, data_pagamento, status, tipo, origem_pj) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$usuario_id, $conta_id, $cat_id, $desc, $valor, $data_venc, $data_pagto, $status_t, $tipo]);
        }
        header("Location: index.php?p=transacoes&success=transacao_ok&mes=$mes_filtro&ano=$ano_filtro"); exit;
    }

    // 4. EXCLUIR TRANSAÇÃO
    if (isset($_POST['btn_excluir_transacao'])) {
        $pdo->prepare("DELETE FROM minhaseconomias_movimentacoes WHERE id = ? AND usuario_id = ?")->execute([(int)$_POST['id_transacao_excluir'], $usuario_id]);
        header("Location: index.php?p=transacoes&success=transacao_excluida&mes=$mes_filtro&ano=$ano_filtro"); exit;
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