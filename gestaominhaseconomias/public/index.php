<?php
/**
 * BDSoft Workspace - Minhas Economias
 * Controlador Principal - Sincronizado
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
$_SESSION['me_visao'] = 'pf';
$visao_atual = 'pf';

// --- PROCESSAMENTO DE AÇÕES (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. SALVAR / EDITAR CATEGORIA
    if (isset($_POST['btn_salvar_categoria'])) {
        $nome = trim($_POST['nome']);
        $tipo_c = $_POST['tipo'] ?? 'AMBOS';
        $parent_id = !empty($_POST['pai_id']) ? (int)$_POST['pai_id'] : null;
        $id_cat = $_POST['id_categoria'] ?? null;
        $origem = $_POST['origem_requisicao'] ?? 'categorias';

        try {
            if (!empty($id_cat)) {
                // Modo Edição
                $sql = "UPDATE minhaseconomias_categorias SET nome = ?, tipo = ?, parent_id = ? WHERE id = ? AND usuario_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nome, $tipo_c, $parent_id, $id_cat, $usuario_id]);
            } else {
                // Modo Novo Registro
                $sql = "INSERT INTO minhaseconomias_categorias (usuario_id, parent_id, nome, tipo, icone) VALUES (?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$usuario_id, $parent_id, $nome, $tipo_c, 'fa-tag']);
            }
            
            $goto = ($origem == 'transacoes') ? "transacoes" : "categorias";
            header("Location: index.php?p=$goto&success=cat_ok&mes=$mes_filtro&ano=$ano_filtro");
            exit;
        } catch (Exception $e) {
            die("Erro ao processar categoria: " . $e->getMessage());
        }
    }

    // 2. EXCLUIR CATEGORIA
    if (isset($_POST['btn_excluir_categoria'])) {
        $id_del_cat = (int)$_POST['id_categoria_excluir'];
        try {
            // Impede excluir a categoria padrão
            if($id_del_cat == 999) die("Ação não permitida.");

            $stmt = $pdo->prepare("DELETE FROM minhaseconomias_categorias WHERE id = ? AND usuario_id = ?");
            $stmt->execute([$id_del_cat, $usuario_id]);
            header("Location: index.php?p=categorias&success=cat_excluida");
            exit;
        } catch (Exception $e) {
            die("Erro ao excluir: " . $e->getMessage());
        }
    }

    // 3. SALVAR / EDITAR CONTA (MINHAS CONTAS)
    if (isset($_POST['btn_salvar_conta'])) {
        $nome_c = trim($_POST['nome']);
        $valor_c = str_replace(',', '.', $_POST['valor_inicial']);
        $status_c = isset($_POST['status']) ? 1 : 0;
        $tipo_c = $_POST['tipo'] ?? 'Carteira';
        $id_c = $_POST['id_conta'] ?? null;
        if ($id_c) {
            $pdo->prepare("UPDATE minhaseconomias_contas SET nome=?, saldo_inicial=?, status=?, tipo=? WHERE id=? AND usuario_id=?")->execute([$nome_c, $valor_c, $status_c, $tipo_c, $id_c, $usuario_id]);
        } else {
            $pdo->prepare("INSERT INTO minhaseconomias_contas (usuario_id, nome, tipo, saldo_inicial, status, cor) VALUES (?,?,?,?,?,?)")->execute([$usuario_id, $nome_c, $tipo_c, $valor_c, $status_c, '#1a73e8']);
        }
        header("Location: index.php?p=dashboard&success=conta_ok&mes=$mes_filtro&ano=$ano_filtro"); exit;
    }

    // 4. EXCLUIR CONTA
    if (isset($_POST['btn_excluir_conta'])) {
        $pdo->prepare("DELETE FROM minhaseconomias_contas WHERE id = ? AND usuario_id = ?")->execute([(int)$_POST['id_conta_excluir'], $usuario_id]);
        header("Location: index.php?p=dashboard&success=conta_excluida&mes=$mes_filtro&ano=$ano_filtro"); exit;
    }

    // 5. SALVAR / EDITAR TRANSAÇÃO
    if (isset($_POST['btn_salvar_transacao'])) {
        $id_t = $_POST['id_transacao'] ?? null;
        $data_v = $_POST['data_transacao'];
        $desc = trim($_POST['descricao']);
        $cat_id = (int)$_POST['categoria_id'];
        $conta_id = (int)$_POST['conta_id'];
        $valor_t = str_replace(['.', ','], ['', '.'], $_POST['valor']);
        $tipo_t = $_POST['tipo_transacao'];
        $status_t = $_POST['status_transacao'] ?? 'Futuro';
        $data_p = ($status_t == 'Pago') ? $data_v : null;

        if ($id_t) {
            $sql = "UPDATE minhaseconomias_movimentacoes SET conta_id=?, categoria_id=?, descricao=?, valor=?, data_vencimento=?, data_pagamento=?, status=?, tipo=? WHERE id=? AND usuario_id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$conta_id, $cat_id, $desc, $valor_t, $data_v, $data_p, $status_t, $tipo_t, $id_t, $usuario_id]);
        } else {
            $sql = "INSERT INTO minhaseconomias_movimentacoes (usuario_id, conta_id, categoria_id, descricao, valor, data_vencimento, data_pagamento, status, tipo, origem_pj) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$usuario_id, $conta_id, $cat_id, $desc, $valor_t, $data_v, $data_p, $status_t, $tipo_t]);
        }
        header("Location: index.php?p=transacoes&success=transacao_ok&mes=$mes_filtro&ano=$ano_filtro"); exit;
    }

    // 6. EXCLUIR TRANSAÇÃO
    if (isset($_POST['btn_excluir_transacao'])) {
        $pdo->prepare("DELETE FROM minhaseconomias_movimentacoes WHERE id = ? AND usuario_id = ?")->execute([(int)$_POST['id_transacao_excluir'], $usuario_id]);
        header("Location: index.php?p=transacoes&success=transacao_excluida&mes=$mes_filtro&ano=$ano_filtro"); exit;
    }
}

// --- 5. RENDERIZAÇÃO DA VIEW ---
$pagina = $_GET['p'] ?? 'dashboard';
include '../includes/header.php';

switch ($pagina) {
    case 'dashboard': include '../views/dashboard.php'; break;
    case 'transacoes': include '../views/transacoes.php'; break;
    case 'categorias': include '../views/categorias.php'; break;
    default: include '../views/dashboard.php'; break;
}

include '../includes/footer.php';