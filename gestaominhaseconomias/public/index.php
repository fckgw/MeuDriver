<?php
/**
 * BDSoft Workspace - Minhas Economias
 * Controlador Principal - Versão BI Premium Estabilizada
 */

// Ativa exibição de erros para diagnóstico
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

// --- CAPTURA DE FILTROS PARA PERSISTÊNCIA NA URL ---
$mes_filtro = isset($_GET['mes']) ? str_pad($_GET['mes'], 2, "0", STR_PAD_LEFT) : date('m');
$ano_filtro = $_GET['ano'] ?? date('Y');
$filtro_banco = $_GET['f_banco'] ?? '';
$filtro_status = $_GET['f_status'] ?? '';
$filtro_tipo = $_GET['f_tipo'] ?? '';
$filtro_categoria = $_GET['f_cat'] ?? '';
$ordenacao_data = $_GET['ordem'] ?? 'ASC';

// --- PADRONIZAÇÃO DE DATAS PARA TODAS AS VIEWS (RESOLVE OS WARNINGS) ---
// O Dashboard usa data_de/data_ate. O Transacoes usa data_inicio/data_fim.
// Definimos ambos aqui para que todas as views funcionem perfeitamente.
$data_de = $_GET['data_de'] ?? $_GET['data_inicio'] ?? date('Y-m-01', strtotime("$ano_filtro-$mes_filtro-01"));
$data_ate = $_GET['data_ate'] ?? $_GET['data_fim'] ?? date('Y-m-t', strtotime("$ano_filtro-$mes_filtro-01"));

// Criamos os aliases (apelidos) para a página de transações
$data_inicio = $data_de;
$data_fim = $data_ate;

$filtros_contexto_url = "mes=$mes_filtro&ano=$ano_filtro&f_banco=$filtro_banco&f_status=$filtro_status&f_tipo=$filtro_tipo&f_cat=$filtro_categoria&ordem=$ordenacao_data&data_de=$data_de&data_ate=$data_ate";

// --- VARREDURA AUTOMÁTICA: ATUALIZA STATUS PARA ATRASADO ---
$data_hoje_referencia = date('Y-m-d');
$pdo->prepare("UPDATE minhaseconomias_movimentacoes SET status = 'Atrasado' WHERE usuario_id = ? AND status = 'Futuro' AND data_vencimento < ?")
    ->execute([$usuario_id, $data_hoje_referencia]);

// --- PROCESSAMENTO DE REQUISIÇÕES POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // AÇÃO BI: MARCAR ITEM PARA ESTUDO (FLAG ROBÔ)
    if (isset($_POST['btn_flag_bi'])) {
        $id_transacao = (int)$_POST['id_transacao'];
        $status_bi = (int)$_POST['status_bi'];
        $pdo->prepare("UPDATE minhaseconomias_movimentacoes SET bi_analise = ? WHERE id = ? AND usuario_id = ?")
            ->execute([$status_bi, $id_transacao, $usuario_id]);
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    }

    // AÇÃO: SALVAR OU EDITAR CONTA (BANCO)
    if (isset($_POST['btn_salvar_conta'])) {
        $id_conta = !empty($_POST['id_conta']) ? (int)$_POST['id_conta'] : null;
        $nome_conta = trim($_POST['nome']);
        $tipo_conta = $_POST['tipo'] ?? 'Banco';
        $status_conta = isset($_POST['status']) ? 1 : 0;
        $saldo_bruto = $_POST['valor_inicial'] ?? '0';
        $saldo_sem_ponto = str_replace('.', '', $saldo_bruto);
        $saldo_decimal = str_replace(',', '.', $saldo_sem_ponto);

        if ($id_conta) {
            $pdo->prepare("UPDATE minhaseconomias_contas SET nome = ?, tipo = ?, saldo_inicial = ?, status = ? WHERE id = ? AND usuario_id = ?")
                ->execute([$nome_conta, $tipo_conta, $saldo_decimal, $status_conta, $id_conta, $usuario_id]);
        } else {
            $pdo->prepare("INSERT INTO minhaseconomias_contas (usuario_id, nome, tipo, saldo_inicial, status) VALUES (?, ?, ?, ?, ?)")
                ->execute([$usuario_id, $nome_conta, $tipo_conta, $saldo_decimal, $status_conta]);
        }
        header("Location: index.php?success=conta_ok&$filtros_contexto_url"); exit;
    }

    // AÇÃO: SALVAR OU EDITAR TRANSAÇÃO (LÓGICA LANÇAR)
    if (isset($_POST['btn_salvar_transacao'])) {
        $id_transacao = !empty($_POST['id_transacao']) ? (int)$_POST['id_transacao'] : null;
        $valor_bruto = $_POST['valor'];
        $valor_sem_ponto = str_replace('.', '', $valor_bruto);
        $valor_decimal_final = str_replace(',', '.', $valor_sem_ponto);
        
        $status_selecionado = $_POST['status_transacao'];
        $data_vencimento = $_POST['data_transacao'];
        $data_pagamento = ($status_selecionado == 'Pago') ? $data_vencimento : null;

        if ($id_transacao) {
            $pdo->prepare("UPDATE minhaseconomias_movimentacoes SET conta_id=?, categoria_id=?, descricao=?, valor=?, data_vencimento=?, data_pagamento=?, status=?, tipo=? WHERE id=? AND usuario_id=?")
                ->execute([$_POST['conta_id'], $_POST['categoria_id'], trim($_POST['descricao']), $valor_decimal_final, $data_vencimento, $data_pagamento, $status_selecionado, $_POST['tipo_transacao'], $id_transacao, $usuario_id]);
        } else {
            $pdo->prepare("INSERT INTO minhaseconomias_movimentacoes (usuario_id, conta_id, categoria_id, descricao, valor, data_vencimento, data_pagamento, status, tipo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$usuario_id, $_POST['conta_id'], $_POST['categoria_id'], trim($_POST['descricao']), $valor_decimal_final, $data_vencimento, $data_pagamento, $status_selecionado, $_POST['tipo_transacao']]);
        }
        header("Location: index.php?p=transacoes&success=ok&$filtros_contexto_url"); exit;
    }

    // AÇÃO: EXCLUIR TRANSAÇÃO
    if (isset($_POST['btn_excluir_transacao'])) {
        $pdo->prepare("DELETE FROM minhaseconomias_movimentacoes WHERE id=? AND usuario_id=?")->execute([(int)$_POST['id_transacao_excluir'], $usuario_id]);
        header("Location: index.php?p=transacoes&success=deleted&$filtros_contexto_url"); exit;
    }
}

// RENDERIZAÇÃO DO TEMPLATE
include '../includes/header.php';
$pagina_solicitada = $_GET['p'] ?? 'dashboard';

switch ($pagina_solicitada) {
    case 'transacoes': include '../views/transacoes.php'; break;
    case 'categorias': include '../views/categorias.php'; break;
    case 'controle':   include '../views/controle.php'; break;
    case 'bi':         include '../views/bi.php'; break;
    default:           include '../views/dashboard.php'; break;
}

include '../includes/footer.php';