<?php
/**
 * BDSoft Workspace - ACOES DE PROJETOS
 * Local: projetos/acoes.php
 */
session_start();
require_once '../config.php';

if (!isset($_SESSION['usuario_id'])) { http_response_code(403); exit; }
$user_id_sessao = $_SESSION['usuario_id'];

try {
    // --- CRIAR QUADRO ---
    if (isset($_POST['acao']) && $_POST['acao'] === 'criar_quadro') {
        $nome = trim($_POST['nome']);
        $tipo = ($_POST['privado'] == 1) ? 'Privado' : 'Publico';
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO quadros_projetos (nome, tipo, usuario_id, data_criacao) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$nome, $tipo, $user_id_sessao]);
        $qid = $pdo->lastInsertId();
        
        $status = [['Novo','#c4c4c4'],['Trabalhando','#fdab3d'],['Travado','#e44258'],['Concluído','#00ca72']];
        foreach($status as $s) $pdo->prepare("INSERT INTO quadros_status (quadro_id,label,cor) VALUES (?,?,?)")->execute([$qid,$s[0],$s[1]]);
        
        $pdo->prepare("INSERT INTO projetos_grupos (nome, quadro_id, cor) VALUES ('Minhas Tarefas', ?, '#1a73e8')")->execute([$qid]);
        $pdo->prepare("INSERT INTO quadro_membros (quadro_id, usuario_id) VALUES (?, ?)")->execute([$qid, $user_id_sessao]);
        
        $pdo->commit();
        header("Location: index.php"); exit;
    }

    // --- NOVA TAREFA ---
    if (isset($_POST['acao']) && $_POST['acao'] === 'nova_tarefa') {
        $stmt_st = $pdo->prepare("SELECT id FROM quadros_status WHERE quadro_id = ? ORDER BY id ASC LIMIT 1");
        $stmt_st->execute([$_POST['quadro_id']]);
        $st_id = $stmt_st->fetchColumn();
        $stmt = $pdo->prepare("INSERT INTO tarefas_projetos (titulo, grupo_id, quadro_id, usuario_id, status_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['titulo'], $_POST['grupo_id'], $_POST['quadro_id'], $user_id_sessao, $st_id]);
        echo "Sucesso"; exit;
    }

    // --- ATUALIZAR CAMPO ---
    if (isset($_POST['acao']) && $_POST['acao'] === 'atualizar_campo_tarefa') {
        $stmt = $pdo->prepare("UPDATE tarefas_projetos SET {$_POST['campo']} = ? WHERE id = ?");
        $stmt->execute([$_POST['valor'], $_POST['id']]);
        echo "Sucesso"; exit;
    }

    // --- SALVAR EVIDENCIA ---
    if (isset($_POST['acao']) && $_POST['acao'] === 'salvar_evidencia') {
        $stmt = $pdo->prepare("UPDATE tarefas_projetos SET evidencias = ? WHERE id = ?");
        $stmt->execute([$_POST['conteudo'], (int)$_POST['id']]);
        echo "Sucesso"; exit;
    }

    // --- BUSCAR EVIDENCIA ---
    if (isset($_GET['acao']) && $_GET['acao'] === 'get_evidencia') {
        $stmt = $pdo->prepare("SELECT evidencias FROM tarefas_projetos WHERE id = ?");
        $stmt->execute([(int)$_GET['id']]);
        echo $stmt->fetchColumn() ?: ""; exit;
    }

    // --- ENVIAR EMAIL RELATORIO ---
    if (isset($_POST['acao']) && $_POST['acao'] === 'email_relatorio') {
        $para = $_POST['para'];
        $assunto = "Relatório BDSoft Workspace";
        $mensagem = "<html><body><h2>Resumo do Projeto ID: {$_POST['id']}</h2><p>Acesse a plataforma para detalhes.</p></body></html>";
        $headers = "MIME-Version: 1.0\r\nContent-type:text/html;charset=UTF-8\r\nFrom: suporte@driverbds.tecnologia.ws";
        if(mail($para, $assunto, $mensagem, $headers, "-f suporte@driverbds.tecnologia.ws")) echo "Sucesso";
        exit;
    }

} catch (Exception $e) { http_response_code(500); echo $e->getMessage(); }