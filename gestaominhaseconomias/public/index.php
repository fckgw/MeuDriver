<?php
/**
 * BDSoft Workspace - Minhas Economias
 * Controlador Principal - Versão Completa e Estabilizada
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

$filtros_contexto_url = "mes=$mes_filtro&ano=$ano_filtro&f_banco=$filtro_banco&f_status=$filtro_status&f_tipo=$filtro_tipo&f_cat=$filtro_categoria&ordem=$ordenacao_data";

// --- VARREDURA AUTOMÁTICA: ATUALIZA STATUS PARA ATRASADO ---
$data_hoje_referencia = date('Y-m-d');
$pdo->prepare("UPDATE minhaseconomias_movimentacoes SET status = 'Atrasado' WHERE usuario_id = ? AND status = 'Futuro' AND data_vencimento < ?")
    ->execute([$usuario_id, $data_hoje_referencia]);

// --- PROCESSAMENTO DE REQUISIÇÕES POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // AÇÃO: SALVAR OU EDITAR CONTA (BANCO)
    // Ajustado para bater exatamente com os nomes dos inputs do seu dashboard.php
    if (isset($_POST['btn_salvar_conta'])) {
        $id_conta = !empty($_POST['id_conta']) ? (int)$_POST['id_conta'] : null;
        $nome_conta = trim($_POST['nome']); // No seu HTML: name="nome"
        $tipo_conta = $_POST['tipo'] ?? 'Banco'; // No seu HTML: name="tipo"
        $status_conta = isset($_POST['status']) ? 1 : 0; // No seu HTML: name="status"
        
        // Tratamento do valor monetário do saldo inicial
        // No seu HTML: name="valor_inicial"
        $saldo_bruto = $_POST['valor_inicial'] ?? '0';
        $saldo_sem_ponto = str_replace('.', '', $saldo_bruto);
        $saldo_decimal = str_replace(',', '.', $saldo_sem_ponto);

        try {
            if ($id_conta) {
                // EDITAR CONTA EXISTENTE
                $sql = "UPDATE minhaseconomias_contas SET nome = ?, tipo = ?, saldo_inicial = ?, status = ? WHERE id = ? AND usuario_id = ?";
                $pdo->prepare($sql)->execute([$nome_conta, $tipo_conta, $saldo_decimal, $status_conta, $id_conta, $usuario_id]);
            } else {
                // INSERIR NOVA CONTA
                $sql = "INSERT INTO minhaseconomias_contas (usuario_id, nome, tipo, saldo_inicial, status) VALUES (?, ?, ?, ?, ?)";
                $pdo->prepare($sql)->execute([$usuario_id, $nome_conta, $tipo_conta, $saldo_decimal, $status_conta]);
            }
            header("Location: index.php?success=conta_ok&$filtros_contexto_url"); 
            exit;
        } catch (PDOException $e) {
            die("Erro ao salvar conta: " . $e->getMessage());
        }
    }

    // AÇÃO: EXCLUIR CONTA
    if (isset($_POST['btn_excluir_conta'])) {
        $id_conta_excluir = (int)$_POST['id_conta_excluir'];
        $pdo->prepare("DELETE FROM minhaseconomias_contas WHERE id = ? AND usuario_id = ?")
            ->execute([$id_conta_excluir, $usuario_id]);
        header("Location: index.php?success=conta_deleted&$filtros_contexto_url"); 
        exit;
    }

    // AÇÃO: SALVAR OU EDITAR CATEGORIA
    if (isset($_POST['btn_salvar_categoria'])) {
        $id_categoria = !empty($_POST['id_categoria']) ? (int)$_POST['id_categoria'] : null;
        $id_categoria_pai = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $nome_categoria = trim($_POST['nome']);
        $tipo_categoria = $_POST['tipo'];

        if ($id_categoria) {
            $pdo->prepare("UPDATE minhaseconomias_categorias SET nome=?, tipo=?, parent_id=? WHERE id=? AND usuario_id=?")
                ->execute([$nome_categoria, $tipo_categoria, $id_categoria_pai, $id_categoria, $usuario_id]);
        } else {
            $pdo->prepare("INSERT INTO minhaseconomias_categorias (usuario_id, parent_id, nome, tipo, icone) VALUES (?,?,?,?,'fa-tag')")
                ->execute([$usuario_id, $id_categoria_pai, $nome_categoria, $tipo_categoria]);
        }
        header("Location: index.php?p=categorias&success=ok&$filtros_contexto_url"); exit;
    }

    // AÇÃO: EXCLUIR CATEGORIA
    if (isset($_POST['btn_excluir_categoria'])) {
        $pdo->prepare("DELETE FROM minhaseconomias_categorias WHERE id=? AND usuario_id=?")
            ->execute([(int)$_POST['id_categoria_excluir'], $usuario_id]);
        header("Location: index.php?p=categorias&success=deleted&$filtros_contexto_url"); exit;
    }

    // AÇÃO: SALVAR OU EDITAR TRANSAÇÃO FINANCEIRA
    if (isset($_POST['btn_salvar_transacao'])) {
        $id_transacao = !empty($_POST['id_transacao']) ? (int)$_POST['id_transacao'] : null;
        $valor_bruto = $_POST['valor'];
        $valor_sem_ponto = str_replace('.', '', $valor_bruto);
        $valor_decimal_final = str_replace(',', '.', $valor_sem_ponto);
        
        $status_selecionado = $_POST['status_transacao'];
        $data_vencimento = $_POST['data_transacao'];
        $data_pagamento = ($status_selecionado == 'Pago') ? $data_vencimento : null;

        if ($id_transacao) {
            $sql_update = "UPDATE minhaseconomias_movimentacoes SET conta_id=?, categoria_id=?, descricao=?, valor=?, data_vencimento=?, data_pagamento=?, status=?, tipo=? WHERE id=? AND usuario_id=?";
            $pdo->prepare($sql_update)->execute([$_POST['conta_id'], $_POST['categoria_id'], trim($_POST['descricao']), $valor_decimal_final, $data_vencimento, $data_pagamento, $status_selecionado, $_POST['tipo_transacao'], $id_transacao, $usuario_id]);
            $movimentacao_id_vinculo = $id_transacao;
        } else {
            $sql_insert = "INSERT INTO minhaseconomias_movimentacoes (usuario_id, conta_id, categoria_id, descricao, valor, data_vencimento, data_pagamento, status, tipo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql_insert);
            $stmt->execute([$usuario_id, $_POST['conta_id'], $_POST['categoria_id'], trim($_POST['descricao']), $valor_decimal_final, $data_vencimento, $data_pagamento, $status_selecionado, $_POST['tipo_transacao']]);
            $movimentacao_id_vinculo = $pdo->lastInsertId();
        }

        if (isset($_POST['combustivel_ativo']) && $_POST['combustivel_ativo'] == '1' && !empty($_POST['veiculo_id'])) {
            $id_veiculo = $_POST['veiculo_id'];
            $km_atual_informado = (float)$_POST['km_lancamento'];
            $litros_abastecidos = (float)str_replace(',', '.', $_POST['litros_lancamento']);
            $stmt_km = $pdo->prepare("SELECT km_atual FROM minhaseconomias_combustivel WHERE veiculo_id = ? AND km_atual < ? ORDER BY km_atual DESC LIMIT 1");
            $stmt_km->execute([$id_veiculo, $km_atual_informado]);
            $ultimo_registro_km = $stmt_km->fetch();
            $km_rodado_calculado = ($ultimo_registro_km) ? ($km_atual_informado - $ultimo_registro_km['km_atual']) : 0;
            $media_consumo_calculada = ($km_rodado_calculado > 0 && $litros_abastecidos > 0) ? ($km_rodado_calculado / $litros_abastecidos) : 0;
            $pdo->prepare("DELETE FROM minhaseconomias_combustivel WHERE movimentacao_id = ?")->execute([$movimentacao_id_vinculo]);
            $sql_combustivel = "INSERT INTO minhaseconomias_combustivel (movimentacao_id, veiculo_id, km_atual, litros, km_rodado, media_kml, usuario_id, data_abastecimento, valor_total) VALUES (?,?,?,?,?,?,?,?,?)";
            $pdo->prepare($sql_combustivel)->execute([$movimentacao_id_vinculo, $id_veiculo, $km_atual_informado, $litros_abastecidos, $km_rodado_calculado, $media_consumo_calculada, $usuario_id, $data_vencimento, $valor_decimal_final]);
        }
        header("Location: index.php?p=transacoes&success=ok&$filtros_contexto_url"); exit;
    }

    // AÇÃO: EXCLUIR TRANSAÇÃO
    if (isset($_POST['btn_excluir_transacao'])) {
        $pdo->prepare("DELETE FROM minhaseconomias_movimentacoes WHERE id=? AND usuario_id=?")->execute([(int)$_POST['id_transacao_excluir'], $usuario_id]);
        header("Location: index.php?p=transacoes&success=deleted&$filtros_contexto_url"); exit;
    }

    // AÇÃO: SALVAR VEÍCULO
    if (isset($_POST['btn_salvar_veiculo'])) {
        $id_veiculo = !empty($_POST['id_veiculo']) ? (int)$_POST['id_veiculo'] : null;
        if ($id_veiculo) {
            $pdo->prepare("UPDATE minhaseconomias_veiculos SET marca=?, modelo=?, placa=? WHERE id=? AND usuario_id=?")
                ->execute([$_POST['marca'], $_POST['modelo'], $_POST['placa'], $id_veiculo, $usuario_id]);
        } else {
            $pdo->prepare("INSERT INTO minhaseconomias_veiculos (usuario_id, marca, modelo, placa) VALUES (?,?,?,?)")
                ->execute([$usuario_id, $_POST['marca'], $_POST['modelo'], $_POST['placa']]);
        }
        header("Location: index.php?p=controle&success=ok"); exit;
    }

    // AÇÃO: EXCLUIR VEÍCULO
    if (isset($_POST['btn_excluir_veiculo'])) {
        $pdo->prepare("DELETE FROM minhaseconomias_veiculos WHERE id=? AND usuario_id=?")->execute([(int)$_POST['id_veiculo_excluir'], $usuario_id]);
        header("Location: index.php?p=controle&success=deleted"); exit;
    }

    // AÇÃO: SALVAR ABASTECIMENTO
    if (isset($_POST['btn_salvar_abastecimento'])) {
        $id_abastecimento = !empty($_POST['id_abastecimento']) ? (int)$_POST['id_abastecimento'] : null;
        $veiculo_id = (int)$_POST['veiculo_id'];
        $data_abastecimento = $_POST['data_abastecimento'];
        $km_atual = (float)$_POST['km_atual'];
        $litros = (float)str_replace(',', '.', $_POST['litros']);
        $valor_total = (float)str_replace(['.', ','], ['', '.'], $_POST['valor_total']);

        $query_anterior = $pdo->prepare("SELECT km_atual FROM minhaseconomias_combustivel WHERE veiculo_id = ? AND km_atual < ? AND usuario_id = ? ORDER BY km_atual DESC LIMIT 1");
        $query_anterior->execute([$veiculo_id, $km_atual, $usuario_id]);
        $registro_anterior = $query_anterior->fetch(PDO::FETCH_ASSOC);

        $km_rodado = 0; $media_kml = 0;
        if ($registro_anterior) {
            $km_rodado = $km_atual - $registro_anterior['km_atual'];
            if ($litros > 0) { $media_kml = $km_rodado / $litros; }
        }

        if ($id_abastecimento) {
            $sql_update = "UPDATE minhaseconomias_combustivel SET veiculo_id = ?, data_abastecimento = ?, km_atual = ?, litros = ?, valor_total = ?, km_rodado = ?, media_kml = ? WHERE id = ? AND usuario_id = ?";
            $pdo->prepare($sql_update)->execute([$veiculo_id, $data_abastecimento, $km_atual, $litros, $valor_total, $km_rodado, $media_kml, $id_abastecimento, $usuario_id]);
        } else {
            $sql_insert = "INSERT INTO minhaseconomias_combustivel (usuario_id, veiculo_id, data_abastecimento, km_atual, litros, valor_total, km_rodado, media_kml) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $pdo->prepare($sql_insert)->execute([$usuario_id, $veiculo_id, $data_abastecimento, $km_atual, $litros, $valor_total, $km_rodado, $media_kml]);
        }
        header("Location: index.php?p=controle&success=abastecimento_ok"); exit;
    }

    // AÇÃO: EXCLUIR ABASTECIMENTO
    if (isset($_POST['btn_excluir_abastecimento'])) {
        $pdo->prepare("DELETE FROM minhaseconomias_combustivel WHERE id = ? AND usuario_id = ?")->execute([(int)$_POST['id_abastecimento_excluir'], $usuario_id]);
        header("Location: index.php?p=controle&success=abastecimento_deletado"); exit;
    }
}

// RENDERIZAÇÃO DO TEMPLATE
include '../includes/header.php';
$pagina_solicitada = $_GET['p'] ?? 'dashboard';

switch ($pagina_solicitada) {
    case 'transacoes': 
        include '../views/transacoes.php'; 
        break;
    case 'categorias': 
        include '../views/categorias.php'; 
        break;
    case 'controle':   
        include '../views/controle.php'; 
        break;
    default: 
        include '../views/dashboard.php'; 
        break;
}

include '../includes/footer.php';