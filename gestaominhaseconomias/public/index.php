<?php
/**
 * BDSoft Workspace - Minhas Economias
 * Controlador Principal - Sincronizado com Persistência de Filtros
 */
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../../login.php");
    exit;
}

require_once '../../config.php'; 
$usuario_id = $_SESSION['usuario_id'];

// --- 1. CAPTURA DE FILTROS GLOBAIS ---
$mes_filtro = isset($_GET['mes']) ? str_pad($_GET['mes'], 2, "0", STR_PAD_LEFT) : date('m');
$ano_filtro = $_GET['ano'] ?? date('Y');

// Captura filtros específicos de transações para persistência
$f_banco  = $_GET['f_banco'] ?? '';
$f_status = $_GET['f_status'] ?? '';
$f_tipo   = $_GET['f_tipo'] ?? '';
$f_cat    = $_GET['f_cat'] ?? '';
$venc_hoje = isset($_GET['vencimento_hoje']) ? '1' : '';

// Monta a Query String de filtros para os redirecionamentos (Redirect Context)
$filtros_contexto = "mes=$mes_filtro&ano=$ano_filtro";
if($f_banco)  $filtros_contexto .= "&f_banco=$f_banco";
if($f_status) $filtros_contexto .= "&f_status=$f_status";
if($f_tipo)   $filtros_contexto .= "&f_tipo=$f_tipo";
if($f_cat)    $filtros_contexto .= "&f_cat=$f_cat";
if($venc_hoje) $filtros_contexto .= "&vencimento_hoje=1";

// --- 2. PROCESSAMENTO DE AÇÕES (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // AÇÃO: SALVAR CATEGORIA
    if (isset($_POST['btn_salvar_categoria'])) {
        $nome = trim($_POST['nome']);
        $tipo_c = $_POST['tipo'] ?? 'AMBOS';
        $parent_id = !empty($_POST['pai_id']) ? (int)$_POST['pai_id'] : null;
        $id_cat = $_POST['id_categoria'] ?? null;
        $origem = $_POST['origem_requisicao'] ?? 'categorias';

        if (!empty($id_cat)) {
            $pdo->prepare("UPDATE minhaseconomias_categorias SET nome=?, tipo=?, parent_id=? WHERE id=? AND usuario_id=?")
                ->execute([$nome, $tipo_c, $parent_id, $id_cat, $usuario_id]);
        } else {
            $pdo->prepare("INSERT INTO minhaseconomias_categorias (usuario_id, parent_id, nome, tipo, icone) VALUES (?, ?, ?, ?, 'fa-tag')")
                ->execute([$usuario_id, $parent_id, $nome, $tipo_c]);
        }
        $goto = ($origem == 'transacoes') ? "transacoes" : "categorias";
        header("Location: index.php?p=$goto&success=cat_ok&$filtros_contexto");
        exit;
    }

    // AÇÃO: SALVAR CONTA
    if (isset($_POST['btn_salvar_conta'])) {
        $val_ini = str_replace(['.', ','], ['', '.'], $_POST['valor_inicial']);
        $id_c = $_POST['id_conta'] ?? null;
        if ($id_c) {
            $pdo->prepare("UPDATE minhaseconomias_contas SET nome=?, saldo_inicial=?, status=?, tipo=? WHERE id=? AND usuario_id=?")
                ->execute([trim($_POST['nome']), $val_ini, (isset($_POST['status'])?1:0), $_POST['tipo'], $id_c, $usuario_id]);
        } else {
            $pdo->prepare("INSERT INTO minhaseconomias_contas (usuario_id, nome, tipo, saldo_inicial, status, cor) VALUES (?,?,?,?,?,?)")
                ->execute([$usuario_id, trim($_POST['nome']), $_POST['tipo'], $val_ini, (isset($_POST['status'])?1:0), '#1a73e8']);
        }
        header("Location: index.php?p=dashboard&success=conta_ok&$filtros_contexto"); exit;
    }

    // AÇÃO: SALVAR TRANSAÇÃO
    if (isset($_POST['btn_salvar_transacao'])) {
        $id_t = $_POST['id_transacao'] ?? null;
        $val_t = str_replace(['.', ','], ['', '.'], $_POST['valor']);
        $status_t = $_POST['status_transacao'] ?? 'Futuro';
        $data_v = $_POST['data_transacao'];
        $data_p = ($status_t == 'Pago') ? $data_v : null;

        if ($id_t) {
            $pdo->prepare("UPDATE minhaseconomias_movimentacoes SET conta_id=?, categoria_id=?, descricao=?, valor=?, data_vencimento=?, data_pagamento=?, status=?, tipo=? WHERE id=? AND usuario_id=?")
                ->execute([$_POST['conta_id'], $_POST['categoria_id'], trim($_POST['descricao']), $val_t, $data_v, $data_p, $status_t, $_POST['tipo_transacao'], $id_t, $usuario_id]);
        } else {
            $pdo->prepare("INSERT INTO minhaseconomias_movimentacoes (usuario_id, conta_id, categoria_id, descricao, valor, data_vencimento, data_pagamento, status, tipo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$usuario_id, $_POST['conta_id'], $_POST['categoria_id'], trim($_POST['descricao']), $val_t, $data_v, $data_p, $status_t, $_POST['tipo_transacao']]);
        }
        header("Location: index.php?p=transacoes&success=transacao_ok&$filtros_contexto"); exit;
    }

    // AÇÃO: EXCLUIR TRANSAÇÃO
    if (isset($_POST['btn_excluir_transacao'])) {
        $pdo->prepare("DELETE FROM minhaseconomias_movimentacoes WHERE id=? AND usuario_id=?")
            ->execute([(int)$_POST['id_transacao_excluir'], $usuario_id]);
        header("Location: index.php?p=transacoes&success=transacao_excluida&$filtros_contexto"); exit;
    }
}

// RENDERIZAÇÃO
$pagina = $_GET['p'] ?? 'dashboard';
include '../includes/header.php';
switch ($pagina) {
    case 'dashboard': include '../views/dashboard.php'; break;
    case 'transacoes': include '../views/transacoes.php'; break;
    case 'categorias': include '../views/categorias.php'; break;
    default: include '../views/dashboard.php'; break;
}
include '../includes/footer.php';