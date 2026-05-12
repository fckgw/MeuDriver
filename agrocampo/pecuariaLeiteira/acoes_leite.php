<?php
session_start();
require_once '../../config.php';

if (!isset($_SESSION['usuario_id'])) { exit; }
$user_id = $_SESSION['usuario_id'];
$acao = $_POST['acao'] ?? $_GET['acao'] ?? '';

// CADASTRO
if ($acao == 'cadastrar_vaca') {
    $stmt = $pdo->prepare("INSERT INTO agro_leite_vacas (codigo_brinco, nome, raca, data_nascimento, peso_inicial, lote, status, usuario_id) VALUES (?,?,?,?,?,?,?,?)");
    $stmt->execute([$_POST['codigo_brinco'], $_POST['nome'], $_POST['raca'], $_POST['data_nascimento'], $_POST['peso'], $_POST['lote'], $_POST['status'], $user_id]);
    header("Location: vacas.php?sucesso=1");
}

// REGISTRAR OCORRÊNCIA DE SAÚDE
if ($acao == 'registrar_ocorrencia') {
    $custo = str_replace(',', '.', $_POST['custo']) ?: 0;
    $stmt = $pdo->prepare("INSERT INTO agro_leite_ocorrencias (vaca_id, data, tipo, descricao, veterinario, medicamentos, custo, status) VALUES (?,?,?,?,?,?,?,?)");
    $stmt->execute([$_POST['vaca_id'], $_POST['data'], $_POST['tipo'], $_POST['descricao'], $_POST['veterinario'], $_POST['medicamentos'], $custo, $_POST['status']]);
    
    // Se for mastite ou doença, atualiza o status da vaca para "Em Tratamento"
    if($_POST['tipo'] == 'Mastite' || $_POST['tipo'] == 'Doença' || $_POST['status'] == 'Pendente') {
        $pdo->prepare("UPDATE agro_leite_vacas SET status = 'Em Tratamento' WHERE id = ?")->execute([$_POST['vaca_id']]);
    }
    
    header("Location: saude.php?sucesso=1");
    exit;
}

// EXCLUIR OCORRÊNCIA
if (isset($_GET['del_ocorrencia'])) {
    $pdo->prepare("DELETE FROM agro_leite_ocorrencias WHERE id = ?")->execute([$_GET['del_ocorrencia']]);
    header("Location: saude.php?sucesso=1");
    exit;
}

// EDITAR ORDENHA
if ($acao == 'editar_ordenha') {
    $id = $_POST['id'];
    $litros = str_replace(',', '.', $_POST['litros']);
    $turno = $_POST['turno'];
    $qualidade = $_POST['qualidade'];
    $ccs = $_POST['ccs'];
    $temp = $_POST['temperatura'];

    $stmt = $pdo->prepare("UPDATE agro_leite_ordenha SET litros=?, turno=?, qualidade=?, ccs=?, temperatura=? WHERE id=?");
    $stmt->execute([$litros, $turno, $qualidade, $ccs, $temp, $id]);

    header("Location: ordenha.php?sucesso=editado");
    exit;
}

// EXCLUIR ORDENHA
if (isset($_GET['del_ordenha'])) {
    $id = $_GET['del_ordenha'];
    $stmt = $pdo->prepare("DELETE FROM agro_leite_ordenha WHERE id = ?");
    $stmt->execute([$id]);

    header("Location: ordenha.php?sucesso=1");
    exit;
}





// ORDENHA
if ($acao == 'lancar_ordenha') {
    $stmt = $pdo->prepare("INSERT INTO agro_leite_ordenha (vaca_id, data, turno, litros, qualidade, ccs, temperatura, responsavel) VALUES (?,?,?,?,?,?,?,?)");
    $stmt->execute([$_POST['vaca_id'], $_POST['data'], $_POST['turno'], str_replace(',', '.', $_POST['litros']), $_POST['qualidade'], $_POST['ccs'], $_POST['temperatura'], $_POST['responsavel']]);
    header("Location: ordenha.php?sucesso=1");
}

// REPRODUÇÃO
if ($acao == 'registrar_reproducao') {
    $previsao = ($_POST['status_gestacao'] == 'Prenha') ? date('Y-m-d', strtotime($_POST['data_cio'] . ' + 283 days')) : null;
    $stmt = $pdo->prepare("INSERT INTO agro_leite_reproducao (vaca_id, data_cio, inseminada, tipo, touro_semen, status_gestacao, previsao_parto) VALUES (?,?,?,?,?,?,?)");
    $stmt->execute([$_POST['vaca_id'], $_POST['data_cio'], $_POST['inseminada'], $_POST['tipo'], $_POST['touro_semen'], $_POST['status_gestacao'], $previsao]);
    header("Location: reproducao.php?sucesso=1");
}

// EXCLUIR
if (isset($_GET['del_vaca'])) {
    $pdo->prepare("DELETE FROM agro_leite_vacas WHERE id = ? AND usuario_id = ?")->execute([$_GET['del_vaca'], $user_id]);
    header("Location: vacas.php?sucesso=1");
}