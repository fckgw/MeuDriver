<?php
/**
 * BDSoft Workspace - AGRO FINANCEIRO (AÇÕES)
 * Localização: agrocampo/acoes.php
 */

session_start();
require_once '../config.php';

// Verificação de segurança: Usuário deve estar logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit;
}

$usuario_id_sessao = $_SESSION['usuario_id'];
$acao = $_POST['acao'] ?? $_GET['acao'] ?? '';

/**
 * --- AÇÃO: LER XML COMEVAP (IMPORTAÇÃO PARA TEMPORÁRIO) ---
 */
if ($acao == 'importar_xml_comevap') {
    if (isset($_FILES['xml_file']) && $_FILES['xml_file']['error'] == 0) {
        try {
            $xml = simplexml_load_file($_FILES['xml_file']['tmp_name']);
            // Identifica o fornecedor (Emitente)
            $fornecedor = (string)($xml->infNFe->emit->xNome ?? $xml->infCFe->emit->xNome ?? 'COMEVAP');
            // Identifica a data de emissão
            $data_emissao = (string)($xml->infNFe->ide->dEmi ?? $xml->infCFe->ide->dEmi ?? date('Y-m-d'));

            // Percorre os itens da nota (SAT ou NFe)
            $itens = $xml->infNFe->det ?? $xml->infCFe->det;
            foreach ($itens as $item) {
                $nome_produto = (string)($item->prod->xProd);
                $valor_produto = (float)($item->prod->vItem);

                $comando_temp = $pdo->prepare("INSERT INTO agro_financeiro_temp (usuario_id, descricao, fornecedor, valor, data_vencimento, status) VALUES (?, ?, ?, ?, ?, 'Pago')");
                $comando_temp->execute([$usuario_id_sessao, $nome_produto, $fornecedor, $valor_produto, $data_emissao]);
            }
            header("Location: financeiro.php?sucesso=xml_lido");
        } catch (Exception $erro_xml) {
            header("Location: financeiro.php?erro=xml_corrompido");
        }
    }
    exit;
}

/**
 * --- AÇÃO: FINALIZAR IMPORTAÇÃO (TABELA TEMPORÁRIA PARA OFICIAL) ---
 */
if ($acao == 'finalizar_importacao') {
    $selecionados = $_POST['selecionados'] ?? [];
    foreach ($selecionados as $id_temporario) {
        // Formata o valor de Real para Decimal de banco de dados
        $valor_formatado = str_replace(',', '.', $_POST["valor_$id_temporario"]);
        $status_final = $_POST["status_$id_temporario"];
        $data_vencimento_final = $_POST["data_$id_temporario"];
        
        $sql_oficial = "INSERT INTO agro_financeiro (tipo, descricao, fornecedor, valor, data_vencimento, status, usuario_id, data_pagamento) 
                        VALUES ('Saida', ?, ?, ?, ?, ?, ?, ?)";
        
        $data_pagamento = ($status_final == 'Pago') ? $data_vencimento_final : null;
        
        $stmt_oficial = $pdo->prepare($sql_oficial);
        $stmt_oficial->execute([
            $_POST["desc_$id_temporario"], 
            $_POST["forn_$id_temporario"], 
            $valor_formatado, 
            $data_vencimento_final, 
            $status_final, 
            $usuario_id_sessao, 
            $data_pagamento
        ]);

        // Remove da tabela temporária após migrar
        $pdo->prepare("DELETE FROM agro_financeiro_temp WHERE id = ?")->execute([$id_temporario]);
    }
    header("Location: financeiro.php?sucesso=migracao_concluida");
    exit;
}

/**
 * --- AÇÃO: GERAR NOVA PROVISÃO (PARCELAMENTO) ---
 * Correção para evitar tela branca e aceitar Entrada ou Saída
 */
if ($acao == 'gerar_provisao') {
    $nome_acordo   = $_POST['nome'] ?? $_POST['nome_provisao'];
    $tipo_fluxo    = $_POST['tipo'] ?? 'Saida'; 
    $quantidade    = (int)$_POST['parcelas'] ?? (int)$_POST['qtd_parcelas'];
    $valor_bruto   = str_replace(['.', ','], ['', '.'], ($_POST['valor_bruto_input'] ?? $_POST['valor_parcela']));
    $data_primeira = $_POST['data_inicio'];
    
    // Se o valor enviado for o total, calculamos a parcela. Se for o valor da parcela, calculamos o total.
    // Aqui assumimos que o valor enviado é o VALOR DA PARCELA conforme a tela de Provisões.
    $valor_da_parcela = $valor_bruto; 
    $valor_total_acumulado = $valor_da_parcela * $quantidade;

    try {
        $pdo->beginTransaction();

        // 1. Insere o cabeçalho da Provisão
        $stmt_prov = $pdo->prepare("INSERT INTO agro_provisoes (nome_provisao, tipo, valor_total, quantidade_parcelas, usuario_id) VALUES (?, ?, ?, ?, ?)");
        $stmt_prov->execute([$nome_acordo, $tipo_fluxo, $valor_total_acumulado, $quantidade, $usuario_id_sessao]);
        $provisao_id_gerado = $pdo->lastInsertId();

        // 2. Gera as parcelas mensais
        for ($i = 0; $i < $quantidade; $i++) {
            $data_parcela = date('Y-m-d', strtotime("+$i month", strtotime($data_primeira)));
            $numero_da_parcela = $i + 1;

            $stmt_parcelas = $pdo->prepare("INSERT INTO agro_provisoes_parcelas (provisao_id, parcela_numero, data_vencimento, valor_parcela, status) VALUES (?, ?, ?, ?, 'Pendente')");
            $stmt_parcelas->execute([$provisao_id_gerado, $numero_da_parcela, $data_parcela, $valor_da_parcela]);
        }

        $pdo->commit();
        header("Location: provisoes.php?sucesso=1");
    } catch (Exception $erro_provisao) {
        $pdo->rollBack();
        die("Erro ao processar provisão: " . $erro_provisao->getMessage());
    }
    exit;
}

/**
 * --- AÇÃO: SALVAR EDIÇÃO DE LANÇAMENTO OFICIAL ---
 */
if ($acao == 'editar_fin') {
    $id_editar = $_POST['id_registro'];
    $valor_editado = str_replace(['.', ','], ['', '.'], $_POST['valor']);
    $status_editado = $_POST['status'];
    $data_vencimento_editada = $_POST['data_vencimento'];
    $data_pagamento_editada = ($status_editado == 'Pago') ? date('Y-m-d') : null;

    $sql_update = "UPDATE agro_financeiro SET tipo=?, fornecedor=?, descricao=?, valor=?, data_vencimento=?, status=?, data_pagamento=? WHERE id=? AND usuario_id=?";
    $stmt_update = $pdo->prepare($sql_update);
    $stmt_update->execute([
        $_POST['tipo'], 
        $_POST['fornecedor'], 
        $_POST['descricao'], 
        $valor_editado, 
        $data_vencimento_editada, 
        $status_editado, 
        $data_pagamento_editada, 
        $id_editar, 
        $usuario_id_sessao
    ]);
    
    header("Location: financeiro.php?sucesso=registro_atualizado");
    exit;
}

/**
 * --- AÇÃO: NOVO LANÇAMENTO MANUAL ---
 */
if ($acao == 'novo_fin') {
    $valor_novo = str_replace(['.', ','], ['', '.'], $_POST['valor']);
    $status_novo = $_POST['status'];
    $data_vencimento_nova = $_POST['data_vencimento'];
    $data_pagamento_nova = ($status_novo == 'Pago') ? date('Y-m-d') : null;

    $comando_novo = $pdo->prepare("INSERT INTO agro_financeiro (tipo, descricao, fornecedor, valor, data_vencimento, status, usuario_id, data_pagamento) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $comando_novo->execute([
        $_POST['tipo'], 
        $_POST['descricao'], 
        $_POST['fornecedor'], 
        $valor_novo, 
        $data_vencimento_nova, 
        $status_novo, 
        $usuario_id_sessao, 
        $data_pagamento_nova
    ]);
    
    header("Location: financeiro.php?sucesso=lancamento_salvo");
    exit;
}

/**
 * --- AÇÃO: EXCLUIR LANÇAMENTO ---
 */
if (isset($_GET['del_fin'])) {
    $id_excluir = $_GET['del_fin'];
    $pdo->prepare("DELETE FROM agro_financeiro WHERE id = ? AND usuario_id = ?")->execute([$id_excluir, $usuario_id_sessao]);
    header("Location: financeiro.php?sucesso=registro_removido");
    exit;
}

/**
 * --- AÇÃO: EXCLUIR PROVISÃO ---
 */
if ($acao == 'excluir_provisao' || isset($_GET['excluir_provisao'])) {
    $id_provisao = $_POST['id'] ?? $_GET['id'] ?? $_GET['excluir_provisao'];
    $pdo->prepare("DELETE FROM agro_provisoes WHERE id = ? AND usuario_id = ?")->execute([$id_provisao, $usuario_id_sessao]);
    header("Location: provisoes.php?sucesso=provisao_removida");
    exit;
}

/**
 * --- AÇÃO: LIMPAR TEMPORÁRIOS ---
 */
if ($acao == 'limpar_temp') {
    $pdo->prepare("DELETE FROM agro_financeiro_temp WHERE usuario_id = ?")->execute([$usuario_id_sessao]);
    header("Location: financeiro.php?sucesso=temp_limpo");
    exit;
}
?>