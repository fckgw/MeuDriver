<?php
/**
 * BDSoft Workspace - MOTOR DE AÇÕES INTEGRADO
 * Local: projetos_acoes.php
 */

session_start();
require_once 'config.php';

// Verificação de Segurança
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    exit("Acesso negado.");
}

$user_id_sessao = $_SESSION['usuario_id'];

try {
    // --- 1. AÇÃO: BUSCAR EVIDÊNCIA (PARA ABRIR O POP-UP) ---
    if (isset($_GET['acao']) && $_GET['acao'] === 'get_evidencia') {
        $id_task = (int)$_GET['id'];
        $stmt = $pdo->prepare("SELECT evidencias FROM tarefas_projetos WHERE id = ?");
        $stmt->execute([$id_task]);
        echo $stmt->fetchColumn() ?: "";
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $acao = $_POST['acao'];

        // --- 2. AÇÃO: SALVAR EVIDÊNCIAS (TEXTO + PRINTS) ---
        if ($acao === 'salvar_evidencia') {
            $id_task = (int)$_POST['id'];
            $conteudo = $_POST['conteudo'];
            $stmt = $pdo->prepare("UPDATE tarefas_projetos SET evidencias = ? WHERE id = ?");
            $stmt->execute([$conteudo, $id_task]);
            echo "Sucesso";
            exit;
        }

        // --- 3. AÇÃO: CRIAR NOVO GRUPO (SPRINT) ---
        if ($acao === 'novo_grupo') {
            $nome = trim($_POST['nome_grupo']);
            $quadro_id = (int)$_POST['quadro_id'];
            $cor = $_POST['cor'] ?? '#579bfc';
            $stmt = $pdo->prepare("INSERT INTO projetos_grupos (nome, quadro_id, cor) VALUES (?, ?, ?)");
            $stmt->execute([$nome, $quadro_id, $cor]);
            header("Location: projetos_quadro.php?id=" . $quadro_id);
            exit;
        }

        // --- 4. AÇÃO: ATUALIZAR CAMPO DO GRUPO (NOME OU COR) ---
        if ($acao === 'atualizar_campo_grupo') {
            $id_grupo = (int)$_POST['id'];
            $campo = $_POST['campo'];
            $valor = $_POST['valor'];
            $pdo->prepare("UPDATE projetos_grupos SET $campo = ? WHERE id = ?")->execute([$valor, $id_grupo]);
            echo "Sucesso";
            exit;
        }

        // --- 5. AÇÃO: ADICIONAR NOVA TAREFA (LINHA NO GRID) ---
        if ($acao === 'nova_tarefa') {
            $titulo = trim($_POST['titulo']);
            $grupo_id = (int)$_POST['grupo_id'];
            $quadro_id = (int)$_POST['quadro_id'];
            $stmt_st = $pdo->prepare("SELECT id FROM quadros_status WHERE quadro_id = ? ORDER BY id ASC LIMIT 1");
            $stmt_st->execute([$quadro_id]);
            $st_id = $stmt_st->fetchColumn();
            $stmt = $pdo->prepare("INSERT INTO tarefas_projetos (titulo, grupo_id, quadro_id, usuario_id, status_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$titulo, $grupo_id, $quadro_id, $user_id_sessao, $st_id]);
            echo "Sucesso";
            exit;
        }

        // --- 6. AÇÃO: ATUALIZAR CAMPO TAREFA (STATUS, DATA, ETC) ---
        if ($acao === 'atualizar_campo_tarefa') {
            $stmt = $pdo->prepare("UPDATE tarefas_projetos SET {$_POST['campo']} = ? WHERE id = ?");
            $stmt->execute([$_POST['valor'], $_POST['id']]);
            echo "Sucesso";
            exit;
        }
    }

    // --- 7. AÇÃO: EXCLUIR TAREFA ---
    if (isset($_GET['excluir_tarefa'])) {
        $id_t = (int)$_GET['excluir_tarefa'];
        $pdo->prepare("DELETE FROM tarefas_projetos WHERE id = ?")->execute([$id_t]);
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    }

    // --- 8. AÇÃO: EXCLUIR GRUPO ---
    if (isset($_GET['del_grupo'])) {
        $id_g = (int)$_GET['del_grupo'];
        $pdo->prepare("DELETE FROM tarefas_projetos WHERE grupo_id = ?")->execute([$id_g]);
        $pdo->prepare("DELETE FROM projetos_grupos WHERE id = ?")->execute([$id_g]);
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo "Erro: " . $e->getMessage();
}