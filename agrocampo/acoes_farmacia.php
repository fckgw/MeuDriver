<?php
/**
 * BDSoft Workspace - AÇÕES FARMÁCIA
 * Localização: agrocampo/acoes_farmacia.php
 */
session_start();
require_once '../config.php';

$acao = $_POST['acao'] ?? '';
$user_id = $_SESSION['usuario_id'];

if ($acao == 'novo_produto') {
    $nome = $_POST['nome_produto'];
    $cat = $_POST['categoria'];
    $un = $_POST['unidade_medida'];
    $lote = $_POST['lote'];
    $val = $_POST['data_validade'];
    $qtd = $_POST['quantidade_atual'];
    $min = $_POST['estoque_minimo'];

    $sql = "INSERT INTO agro_farmacia_estoque (nome_produto, categoria, unidade_medida, lote, data_validade, quantidade_atual, estoque_minimo, usuario_id) VALUES (?,?,?,?,?,?,?,?)";
    $pdo->prepare($sql)->execute([$nome, $cat, $un, $lote, $val, $qtd, $min, $user_id]);
    
    header("Location: farmacia.php?sucesso=1");
    exit;
}

if ($acao == 'lancar_movimento') {
    $prod_id = $_POST['produto_id'];
    $tipo = $_POST['tipo_movimento'];
    $qtd = $_POST['quantidade'];
    $motivo = $_POST['motivo'];

    try {
        $pdo->beginTransaction();

        // 1. Registra no histórico
        $stmt = $pdo->prepare("INSERT INTO agro_farmacia_movimentacao (produto_id, tipo_movimento, quantidade, motivo) VALUES (?,?,?,?)");
        $stmt->execute([$prod_id, $tipo, $qtd, $motivo]);

        // 2. Atualiza o saldo no estoque
        if ($tipo == 'Entrada') {
            $pdo->prepare("UPDATE agro_farmacia_estoque SET quantidade_atual = quantidade_atual + ? WHERE id = ?")->execute([$qtd, $prod_id]);
        } else {
            $pdo->prepare("UPDATE agro_farmacia_estoque SET quantidade_atual = quantidade_atual - ? WHERE id = ?")->execute([$qtd, $prod_id]);
        }

        $pdo->commit();
        header("Location: farmacia.php?sucesso=1");
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Erro ao movimentar: " . $e->getMessage());
    }
}