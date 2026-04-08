<?php
/**
 * BDSoft Workspace - Minhas Economias
 * Controlador Principal - Sincronizado
 */
session_start();

// 1. VERIFICAÇÃO DE ACESSO
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../../login.php");
    exit;
}

require_once '../../config.php'; 
$usuario_id = $_SESSION['usuario_id'];

// 2. FILTROS GLOBAIS (Sincronizados)
$mes_filtro = isset($_GET['mes']) ? str_pad($_GET['mes'], 2, "0", STR_PAD_LEFT) : date('m');
$ano_filtro = $_GET['ano'] ?? date('Y');

// NOVO FILTRO: Banco / Conta
$conta_filtro = (isset($_GET['f_conta']) && $_GET['f_conta'] !== '') ? (int)$_GET['f_conta'] : null;

// Variável para manter os filtros nas URLs de redirecionamento
$url_params = "mes=$mes_filtro&ano=$ano_filtro" . ($conta_filtro ? "&f_conta=$conta_filtro" : "");

// --- 3. VARREDURA DE PENDÊNCIAS (Hoje e Atrasados) ---
// Mantendo a lógica conforme solicitado para alimentar o modal de pendências
try {
    $hoje = date('Y-m-d');
    $sql_pend = "SELECT COUNT(*) FROM minhaseconomias_movimentacoes 
                 WHERE usuario_id = ? AND status != 'Pago' AND data_vencimento <= ?";
    $stmt_pend = $pdo->prepare($sql_pend);
    $stmt_pend->execute([$usuario_id, $hoje]);
    $total_pendencias = $stmt_pend->fetchColumn();
    
    // Passa para o JavaScript via window
    echo "<script>window.pendenciasHoje = $total_pendencias; window.filtroAtual = {mes: '$mes_filtro', ano: '$ano_filtro'};</script>";
} catch (Exception $e) {
    $total_pendencias = 0;
}

// --- 4. PROCESSAMENTO DE AÇÕES (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // AÇÃO: SALVAR / EDITAR CATEGORIA
    if (isset($_POST['btn_salvar_categoria'])) {
        $nome = trim($_POST['nome']);
        $tipo_c = $_POST['tipo'] ?? 'AMBOS';
        $parent_id = !empty($_POST['pai_id']) ? (int)$_POST['pai_id'] : null;
        $id_cat = $_POST['id_categoria'] ?? null;
        $origem = $_POST['origem_requisicao'] ?? 'categorias';

        try {
            if (!empty($id_cat)) {
                $sql = "UPDATE minhaseconomias_categorias SET nome = ?, tipo = ?, parent_id = ? WHERE id = ? AND usuario_id = ?";
                $pdo->prepare($sql)->execute([$nome, $tipo_c, $parent_id, $id_cat, $usuario_id]);
            } else {
                $sql = "INSERT INTO minhaseconomias_categorias (usuario_id, parent_id, nome, tipo, icone) VALUES (?, ?, ?, ?, 'fa-tag')";
                $pdo->prepare($sql)->execute([$usuario_id, $parent_id, $nome, $tipo_c]);
            }
            $goto = ($origem == 'transacoes') ? "transacoes" : "categorias";
            header("Location: index.php?p=$goto&success=cat_ok&$url_params");
            exit;
        } catch (Exception $e) { die("Erro ao processar categoria: " . $e->getMessage()); }
    }

    // AÇÃO: EXCLUIR CATEGORIA
    if (isset($_POST['btn_excluir_categoria'])) {
        $pdo->prepare("DELETE FROM minhaseconomias_categorias WHERE id = ? AND usuario_id = ?")
            ->execute([(int)$_POST['id_categoria_excluir'], $usuario_id]);
        header("Location: index.php?p=categorias&success=cat_excluida&$url_params");
        exit;
    }

    // AÇÃO: SALVAR / EDITAR CONTA (MINHAS CONTAS)
    if (isset($_POST['btn_salvar_conta'])) {
        $id_c = $_POST['id_conta'] ?? null;
        $nome_c = trim($_POST['nome']);
        $tipo_c = $_POST['tipo'] ?? 'Carteira';
        $status_c = isset($_POST['status']) ? 1 : 0;
        
        // Tratamento do Saldo Inicial para garantir cálculos corretos (converte vírgula em ponto)
        $valor_br = $_POST['valor_inicial'] ?? '0,00';
        $saldo_ini = str_replace(['.', ','], ['', '.'], $valor_br);
        if(!is_numeric($saldo_ini)) $saldo_ini = 0.00;

        try {
            if ($id_c) {
                $sql = "UPDATE minhaseconomias_contas SET nome=?, saldo_inicial=?, status=?, tipo=? WHERE id=? AND usuario_id=?";
                $pdo->prepare($sql)->execute([$nome_c, $saldo_ini, $status_c, $tipo_c, $id_c, $usuario_id]);
            } else {
                $sql = "INSERT INTO minhaseconomias_contas (usuario_id, nome, tipo, saldo_inicial, status, cor) VALUES (?,?,?,?,?,?)";
                $pdo->prepare($sql)->execute([$usuario_id, $nome_c, $tipo_c, $saldo_ini, $status_c, '#1a73e8']);
            }
            header("Location: index.php?p=dashboard&success=conta_ok&$url_params");
            exit;
        } catch (Exception $e) { die("Erro ao processar conta: " . $e->getMessage()); }
    }

    // AÇÃO: EXCLUIR CONTA
    if (isset($_POST['btn_excluir_conta'])) {
        $pdo->prepare("DELETE FROM minhaseconomias_contas WHERE id = ? AND usuario_id = ?")
            ->execute([(int)$_POST['id_conta_excluir'], $usuario_id]);
        header("Location: index.php?p=dashboard&success=conta_excluida&$url_params");
        exit;
    }

    // AÇÃO: SALVAR / EDITAR TRANSAÇÃO
    if (isset($_POST['btn_salvar_transacao'])) {
        $id_t = $_POST['id_transacao'] ?? null;
        $val_t = str_replace(['.', ','], ['', '.'], $_POST['valor']);
        $status_t = $_POST['status_transacao'] ?? 'Futuro';
        $data_v = $_POST['data_transacao'];
        $data_p = ($status_t == 'Pago') ? $data_v : null;

        try {
            if ($id_t) {
                $sql = "UPDATE minhaseconomias_movimentacoes SET conta_id=?, categoria_id=?, descricao=?, valor=?, data_vencimento=?, data_pagamento=?, status=?, tipo=? WHERE id=? AND usuario_id=?";
                $pdo->prepare($sql)->execute([$_POST['conta_id'], $_POST['categoria_id'], trim($_POST['descricao']), $val_t, $data_v, $data_p, $status_t, $_POST['tipo_transacao'], $id_t, $usuario_id]);
            } else {
                $sql = "INSERT INTO minhaseconomias_movimentacoes (usuario_id, conta_id, categoria_id, descricao, valor, data_vencimento, data_pagamento, status, tipo, origem_pj) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)";
                $pdo->prepare($sql)->execute([$usuario_id, $_POST['conta_id'], $_POST['categoria_id'], trim($_POST['descricao']), $val_t, $data_v, $data_p, $status_t, $_POST['tipo_transacao']]);
            }
            header("Location: index.php?p=transacoes&success=transacao_ok&$url_params");
            exit;
        } catch (Exception $e) { die("Erro ao salvar transação: " . $e->getMessage()); }
    }

    // AÇÃO: EXCLUIR TRANSAÇÃO
    if (isset($_POST['btn_excluir_transacao'])) {
        $pdo->prepare("DELETE FROM minhaseconomias_movimentacoes WHERE id = ? AND usuario_id = ?")
            ->execute([(int)$_POST['id_transacao_excluir'], $usuario_id]);
        header("Location: index.php?p=transacoes&success=transacao_excluida&$url_params");
        exit;
    }
}

// --- 5. RENDERIZAÇÃO DA VIEW ---
$pagina = $_GET['p'] ?? 'dashboard';

include '../includes/header.php';

// As variáveis $mes_filtro, $ano_filtro e $conta_filtro estarão disponíveis dentro de cada view
switch ($pagina) {
    case 'dashboard': 
        include '../views/dashboard.php'; 
        break;
    case 'transacoes': 
        include '../views/transacoes.php'; 
        break;
    case 'categorias': 
        include '../views/categorias.php'; 
        break;
    default: 
        include '../views/dashboard.php'; 
        break;
}

include '../includes/footer.php';