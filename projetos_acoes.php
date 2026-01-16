<?php
/**
 * BDSoft Workspace - MOTOR DE AÇÕES DE PROJETOS
 * Local: projetos_acoes.php
 */

// 1. Configurações de erro para depuração em produção
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'config.php';

// 2. Verificação de Segurança básica
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    exit("Acesso negado.");
}

$user_id_sessao = $_SESSION['usuario_id'];

try {
    // --- AÇÃO: CRIAR NOVO QUADRO (WORKSPACE) ---
    if (isset($_POST['acao']) && $_POST['acao'] === 'criar_quadro') {
        $nome = trim($_POST['nome']);
        $privado = (int)$_POST['privado']; // 0 para Público, 1 para Privado
        $tipo = ($privado === 1) ? 'Privado' : 'Publico';

        $pdo->beginTransaction();

        // Inserir o quadro na tabela unificada
        $stmt_quadro = $pdo->prepare("INSERT INTO quadros_projetos (nome, tipo, usuario_id, data_criacao) VALUES (?, ?, ?, NOW())");
        $stmt_quadro->execute([$nome, $tipo, $user_id_sessao]);
        $id_novo_quadro = $pdo->lastInsertId();

        // Criar os Status iniciais padrão do Monday para este quadro específico
        $status_iniciais = [
            ['Novo', '#c4c4c4'],
            ['Trabalhando', '#fdab3d'],
            ['Travado', '#e44258'],
            ['Concluído', '#00ca72']
        ];
        
        $primeiro_status_id = null;
        foreach ($status_iniciais as $st) {
            $stmt_st = $pdo->prepare("INSERT INTO quadros_status (quadro_id, label, cor) VALUES (?, ?, ?)");
            $stmt_st->execute([$id_novo_quadro, $st[0], $st[1]]);
            if ($primeiro_status_id === null) {
                $primeiro_status_id = $pdo->lastInsertId();
            }
        }

        // Criar o primeiro Grupo de Tarefas (Sprint)
        $stmt_grupo = $pdo->prepare("INSERT INTO projetos_grupos (nome, quadro_id, cor) VALUES ('Grupo de Tarefas', ?, '#1a73e8')");
        $stmt_grupo->execute([$id_novo_quadro]);
        $id_grupo_inicial = $pdo->lastInsertId();

        // Vincular o criador como membro oficial (essencial para quadros privados)
        $stmt_membro = $pdo->prepare("INSERT INTO quadro_membros (quadro_id, usuario_id) VALUES (?, ?)");
        $stmt_membro->execute([$id_novo_quadro, $user_id_sessao]);

        $pdo->commit();
        
        registrarLog($pdo, $user_id_sessao, "Projetos", "Criou o quadro: $nome ($tipo)");
        header("Location: projetos_home.php");
        exit;
    }

    // --- AÇÃO: ADICIONAR NOVA TAREFA (LINHA NO GRID) ---
    if (isset($_POST['acao']) && $_POST['acao'] === 'nova_tarefa') {
        $titulo = trim($_POST['titulo']);
        $grupo_id = (int)$_POST['grupo_id'];
        $quadro_id = (int)$_POST['quadro_id'];

        // Buscar o ID do status 'Novo' deste quadro específico
        $stmt_get_st = $pdo->prepare("SELECT id FROM quadros_status WHERE quadro_id = ? ORDER BY id ASC LIMIT 1");
        $stmt_get_st->execute([$quadro_id]);
        $status_id_inicial = $stmt_get_st->fetchColumn();

        $sql_task = "INSERT INTO tarefas_projetos (titulo, grupo_id, quadro_id, usuario_id, status_id, prioridade) VALUES (?, ?, ?, ?, ?, 'Baixa')";
        $stmt_task = $pdo->prepare($sql_task);
        $stmt_task->execute([$titulo, $grupo_id, $quadro_id, $user_id_sessao, $status_id_inicial]);
        
        echo "Sucesso";
        exit;
    }

    // --- AÇÃO: ATUALIZAR CAMPO DA TAREFA (AJAX) ---
    if (isset($_POST['acao']) && $_POST['acao'] === 'atualizar_campo_tarefa') {
        $task_id = (int)$_POST['id'];
        $campo   = $_POST['campo'];
        $valor   = $_POST['valor'];

        // Lista de campos permitidos para alteração via AJAX
        $campos_permitidos = ['status_id', 'prioridade', 'data_fim', 'titulo'];
        
        if (in_array($campo, $campos_permitidos)) {
            $sql_up = "UPDATE tarefas_projetos SET $campo = ? WHERE id = ?";
            $stmt_up = $pdo->prepare($sql_up);
            $stmt_up->execute([$valor, $task_id]);
            echo "Sucesso";
        }
        exit;
    }

    // --- AÇÃO: SALVAR EVIDÊNCIAS (TEXTO + PRINTS) ---
    if (isset($_POST['acao']) && $_POST['acao'] === 'salvar_evidencia') {
        $id_task = (int)$_POST['id'];
        $html_conteudo = $_POST['conteudo'];

        $stmt_evid = $pdo->prepare("UPDATE tarefas_projetos SET evidencias = ? WHERE id = ?");
        $stmt_evid->execute([$html_conteudo, $id_task]);
        echo "Sucesso";
        exit;
    }

    // --- AÇÃO: BUSCAR EVIDÊNCIAS (PARA O MODAL) ---
    if (isset($_GET['acao']) && $_GET['acao'] === 'get_evidencia') {
        $id_task = (int)$_GET['id'];
        $stmt_fetch = $pdo->prepare("SELECT evidencias FROM tarefas_projetos WHERE id = ?");
        $stmt_fetch->execute([$id_task]);
        $resultado = $stmt_fetch->fetchColumn();
        echo $resultado ?: "";
        exit;
    }

    // --- AÇÃO: EXCLUIR TAREFA ---
    if (isset($_GET['excluir_tarefa'])) {
        $id_del = (int)$_GET['excluir_tarefa'];
        $stmt_del = $pdo->prepare("DELETE FROM tarefas_projetos WHERE id = ?");
        $stmt_del->execute([$id_del]);
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo "Erro Crítico: " . $e->getMessage();
    exit;
}