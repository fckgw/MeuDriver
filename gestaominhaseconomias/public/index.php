<?php
/**
 * BDSoft Workspace - Minhas Economias
 * Controlador Principal - Versão BI Premium Final (RESTAURADO)
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../../login.php");
    exit;
}

require_once '../../config.php'; 
$usuario_id = $_SESSION['usuario_id'];

// --- PADRONIZAÇÃO DE FILTROS GLOBAIS ---
$mes_filtro = isset($_GET['mes']) ? str_pad($_GET['mes'], 2, "0", STR_PAD_LEFT) : date('m');
$ano_filtro = $_GET['ano'] ?? date('Y');

$data_de = $_GET['data_de'] ?? $_GET['data_inicio'] ?? date('Y-m-01', strtotime("$ano_filtro-$mes_filtro-01"));
$data_ate = $_GET['data_ate'] ?? $_GET['data_fim'] ?? date('Y-m-t', strtotime("$ano_filtro-$mes_filtro-01"));

$data_inicio = $data_de; 
$data_fim = $data_ate;

$f_status = $_GET['f_status'] ?? '';
$f_banco  = $_GET['f_banco'] ?? ''; 
$f_tipo   = $_GET['f_tipo'] ?? '';
$f_cat    = $_GET['f_cat'] ?? '';

$filtros_contexto_url = "mes=$mes_filtro&ano=$ano_filtro&data_de=$data_de&data_ate=$data_ate&f_status=$f_status&f_banco=$f_banco&f_tipo=$f_tipo&f_cat=$f_cat";

// --- VARREDURA AUTOMÁTICA DE ATRASADOS ---
$pdo->prepare("UPDATE minhaseconomias_movimentacoes SET status = 'Atrasado' WHERE usuario_id = ? AND status = 'Futuro' AND data_vencimento < ?")
    ->execute([$usuario_id, date('Y-m-d')]);

// --- PROCESSAMENTO DE REQUISIÇÕES POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. AÇÃO: SALVAR OU EDITAR CATEGORIA (RESTAURADO)
    if (isset($_POST['btn_salvar_categoria'])) {
        $id_cat = !empty($_POST['id_categoria']) ? (int)$_POST['id_categoria'] : null;
        $nome_cat = trim($_POST['nome']);
        $tipo_cat = $_POST['tipo']; // Agora aceita 'Ambos'
        $id_pai = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

        try {
            if ($id_cat) {
                // EDITAR
                $sql = "UPDATE minhaseconomias_categorias SET nome=?, tipo=?, parent_id=? WHERE id=? AND usuario_id=?";
                $pdo->prepare($sql)->execute([$nome_cat, $tipo_cat, $id_pai, $id_cat, $usuario_id]);
            } else {
                // INSERIR NOVO
                $sql = "INSERT INTO minhaseconomias_categorias (usuario_id, parent_id, nome, tipo, icone) VALUES (?,?,?,?,'fa-tag')";
                $pdo->prepare($sql)->execute([$usuario_id, $id_pai, $nome_cat, $tipo_cat]);
            }
            header("Location: index.php?p=categorias&success=ok&$filtros_contexto_url"); exit;
        } catch (PDOException $e) { die("Erro ao salvar categoria: " . $e->getMessage()); }
    }

    // 2. AÇÃO: EXCLUIR CATEGORIA
    if (isset($_POST['btn_excluir_categoria'])) {
        $id_excluir = (int)$_POST['id_categoria_excluir'];
        $pdo->prepare("DELETE FROM minhaseconomias_categorias WHERE id=? AND usuario_id=?")->execute([$id_excluir, $usuario_id]);
        header("Location: index.php?p=categorias&success=deleted&$filtros_contexto_url"); exit;
    }

    // 3. AÇÃO: SALVAR OU EDITAR CONTA
    if (isset($_POST['btn_salvar_conta'])) {
        $id_conta = !empty($_POST['id_conta']) ? (int)$_POST['id_conta'] : null;
        $nome_conta = trim($_POST['nome']);
        $tipo_conta = $_POST['tipo'] ?? 'Banco';
        $status_conta = isset($_POST['status']) ? 1 : 0; 
        $saldo_bruto = str_replace(',', '.', str_replace('.', '', $_POST['valor_inicial']));

        if ($id_conta) {
            $pdo->prepare("UPDATE minhaseconomias_contas SET nome=?, tipo=?, saldo_inicial=?, status=? WHERE id=? AND usuario_id=?")
                ->execute([$nome_conta, $tipo_conta, $saldo_bruto, $status_conta, $id_conta, $usuario_id]);
        } else {
            $pdo->prepare("INSERT INTO minhaseconomias_contas (usuario_id, nome, tipo, saldo_inicial, status) VALUES (?,?,?,?,?)")
                ->execute([$usuario_id, $nome_conta, $tipo_conta, $saldo_bruto, $status_conta]);
        }
        header("Location: index.php?success=conta_ok&$filtros_contexto_url"); exit;
    }

    // 4. AÇÃO: EXCLUIR CONTA COM TRANSFERÊNCIA
    if (isset($_POST['btn_excluir_conta_total'])) {
        $id_excluir = (int)$_POST['id_conta_excluir'];
        $id_destino = !empty($_POST['id_conta_destino']) ? (int)$_POST['id_conta_destino'] : null;
        try {
            $pdo->beginTransaction();
            if ($id_destino) {
                $pdo->prepare("UPDATE minhaseconomias_movimentacoes SET conta_id=? WHERE conta_id=? AND usuario_id=? AND status IN ('Futuro','Atrasado')")
                    ->execute([$id_destino, $id_excluir, $usuario_id]);
            }
            $pdo->prepare("DELETE FROM minhaseconomias_movimentacoes WHERE conta_id=? AND usuario_id=?")->execute([$id_excluir, $usuario_id]);
            $pdo->prepare("DELETE FROM minhaseconomias_contas WHERE id=? AND usuario_id=?")->execute([$id_excluir, $usuario_id]);
            $pdo->commit();
            header("Location: index.php?success=deleted&$filtros_contexto_url"); exit;
        } catch (Exception $e) { $pdo->rollBack(); die($e->getMessage()); }
    }

    // 5. AÇÃO: SALVAR OU EDITAR TRANSAÇÃO (COM LOG)
    if (isset($_POST['btn_salvar_transacao'])) {
        $id_t = !empty($_POST['id_transacao']) ? (int)$_POST['id_transacao'] : null;
        $valor = str_replace(',', '.', str_replace('.', '', $_POST['valor']));
        $st = $_POST['status_transacao'];
        $dt = $_POST['data_transacao'];
        $dt_p = ($st == 'Pago') ? $dt : null;
        $acao_log = $id_t ? 'EDIÇÃO' : 'INSERÇÃO';

        if ($id_t) {
            $pdo->prepare("UPDATE minhaseconomias_movimentacoes SET conta_id=?, categoria_id=?, descricao=?, valor=?, data_vencimento=?, data_pagamento=?, status=?, tipo=? WHERE id=? AND usuario_id=?")
                ->execute([$_POST['conta_id'], $_POST['categoria_id'], trim($_POST['descricao']), $valor, $dt, $dt_p, $st, $_POST['tipo_transacao'], $id_t, $usuario_id]);
            $id_final = $id_t;
        } else {
            $stmt = $pdo->prepare("INSERT INTO minhaseconomias_movimentacoes (usuario_id, conta_id, categoria_id, descricao, valor, data_vencimento, data_pagamento, status, tipo) VALUES (?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$usuario_id, $_POST['conta_id'], $_POST['categoria_id'], trim($_POST['descricao']), $valor, $dt, $dt_p, $st, $_POST['tipo_transacao']]);
            $id_final = $pdo->lastInsertId();
        }

        $pdo->prepare("INSERT INTO minhaseconomias_logs (usuario_id, transacao_id, data_transacao, acao, valor, status) VALUES (?,?,?,?,?,?)")
            ->execute([$usuario_id, $id_final, $dt, $acao_log, $valor, $st]);

        header("Location: " . $_SERVER['HTTP_REFERER']); exit;
    }

    // 6. AÇÃO: EXCLUIR TRANSAÇÃO (COM LOG)
    if (isset($_POST['btn_excluir_transacao_confirmado'])) {
        $id_excluir = (int)$_POST['id_transacao_excluir'];
        $stmt_info = $pdo->prepare("SELECT valor, status, data_vencimento FROM minhaseconomias_movimentacoes WHERE id = ? AND usuario_id = ?");
        $stmt_info->execute([$id_excluir, $usuario_id]);
        $info = $stmt_info->fetch();
        if ($info) {
            $pdo->prepare("INSERT INTO minhaseconomias_logs (usuario_id, transacao_id, data_transacao, acao, valor, status) VALUES (?,?,?,?,?,?)")
                ->execute([$usuario_id, $id_excluir, $info['data_vencimento'], 'EXCLUSÃO', $info['valor'], $info['status']]);
            $pdo->prepare("DELETE FROM minhaseconomias_movimentacoes WHERE id=? AND usuario_id=?")->execute([$id_excluir, $usuario_id]);
        }
        header("Location: index.php?p=transacoes&success=deleted&$filtros_contexto_url"); exit;
    }

    // 7. AÇÃO: FLAG BI (ROBÔ)
    if (isset($_POST['btn_flag_bi'])) {
        $pdo->prepare("UPDATE minhaseconomias_movimentacoes SET bi_analise=? WHERE id=? AND usuario_id=?")
            ->execute([(int)$_POST['status_bi'], (int)$_POST['id_transacao'], $usuario_id]);
        header("Location: " . $_SERVER['HTTP_REFERER']); exit;
    }
}

// RENDERIZAÇÃO
include '../includes/header.php';
$pagina = $_GET['p'] ?? 'dashboard';
switch ($pagina) {
    case 'transacoes': include '../views/transacoes.php'; break;
    case 'bi':         include '../views/bi.php';         break;
    case 'categorias': include '../views/categorias.php'; break;
    default:           include '../views/dashboard.php';  break;
}
include '../includes/footer.php';