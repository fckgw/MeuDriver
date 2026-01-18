<?php
/**
 * BDSoft Workspace - PROJETOS / ACOES
 * Local: projetos/acoes.php
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../config.php';

if (!isset($_SESSION['usuario_id'])) { exit("Sessão expirada."); }
$user_id_sessao = $_SESSION['usuario_id'];
$user_nivel = $_SESSION['usuario_nivel'] ?? 'usuario';

try {
    // --- 1. BUSCAR EVIDÊNCIA (GET) ---
    if (isset($_GET['acao']) && $_GET['acao'] === 'get_evidencia') {
        $stmt = $pdo->prepare("SELECT evidencias FROM tarefas_projetos WHERE id = ?");
        $stmt->execute([(int)$_GET['id']]);
        echo $stmt->fetchColumn() ?: "";
        exit;
    }

    // --- 2. EXCLUIR QUADRO COMPLETO (GET) ---
    if (isset($_GET['acao']) && $_GET['acao'] === 'deletar_quadro_completo') {
        $id_quadro = (int)$_GET['id'];
        $stmt = $pdo->prepare("DELETE FROM quadros_projetos WHERE id = ? AND (usuario_id = ? OR ? = 'admin')");
        $stmt->execute([$id_quadro, $user_id_sessao, $user_nivel]);
        header("Location: index.php");
        exit;
    }

    // --- 3. EXCLUIR GRUPO (GET) ---
    if (isset($_GET['del_grupo'])) {
        $id_g = (int)$_GET['del_grupo'];
        $id_q = (int)$_GET['quadro_id'];
        $pdo->prepare("DELETE FROM tarefas_projetos WHERE grupo_id = ?")->execute([$id_g]);
        $pdo->prepare("DELETE FROM projetos_grupos WHERE id = ?")->execute([$id_g]);
        header("Location: quadro.php?id=" . $id_q);
        exit;
    }

    // --- 4. EXCLUIR TAREFA (GET) ---
    if (isset($_GET['excluir_tarefa'])) {
        $id_t = (int)$_GET['excluir_tarefa'];
        $id_q = (int)$_GET['id_quadro'];
        $pdo->prepare("DELETE FROM tarefas_projetos WHERE id = ?")->execute([$id_t]);
        header("Location: quadro.php?id=" . $id_q);
        exit;
    }

    // --- PROCESSAMENTO POST ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $acao = $_POST['acao'] ?? '';

        // --- 5. CRIAR NOVO QUADRO (COM STATUS AUTOMÁTICOS) ---
        if ($acao === 'criar_quadro') {
            $nome = trim($_POST['nome']);
            $privado = (int)$_POST['privado'];
            $tipo = ($privado === 1) ? 'Privado' : 'Publico';

            $pdo->beginTransaction();
            $stmtQ = $pdo->prepare("INSERT INTO quadros_projetos (nome, tipo, usuario_id, data_criacao) VALUES (?, ?, ?, NOW())");
            $stmtQ->execute([$nome, $tipo, $user_id_sessao]);
            $qid = $pdo->lastInsertId();

            // GERA STATUS PADRÃO PARA O NOVO QUADRO
            $status_padrao = [['Novo', '#c4c4c4'],['Trabalhando', '#fdab3d'],['Travado', '#e44258'],['Concluído', '#00ca72']];
            $primeiro_status_id = null;
            foreach ($status_padrao as $st) {
                $stmtS = $pdo->prepare("INSERT INTO quadros_status (quadro_id, label, cor) VALUES (?, ?, ?)");
                $stmtS->execute([$qid, $st[0], $st[1]]);
                if (!$primeiro_status_id) $primeiro_status_id = $pdo->lastInsertId();
            }

            // Criar Grupo inicial
            $pdo->prepare("INSERT INTO projetos_grupos (nome, quadro_id, cor) VALUES ('Minhas Tarefas', ?, '#1a73e8')")->execute([$qid]);
            $pdo->prepare("INSERT INTO quadro_membros (quadro_id, usuario_id) VALUES (?, ?)")->execute([$qid, $user_id_sessao]);
            
            $pdo->commit();
            header("Location: index.php");
            exit;
        }

        // --- 6. EDITAR NOME DO QUADRO ---
        if ($acao === 'editar_nome_quadro') {
            $pdo->prepare("UPDATE quadros_projetos SET nome = ? WHERE id = ?")->execute([$_POST['nome'], (int)$_POST['id']]);
            echo "Sucesso"; exit;
        }

        // --- 7. NOVO GRUPO ---
        if ($acao === 'novo_grupo') {
            $pdo->prepare("INSERT INTO projetos_grupos (nome, quadro_id, cor) VALUES (?, ?, ?)")
                ->execute([trim($_POST['nome_grupo']), (int)$_POST['quadro_id'], $_POST['cor']]);
            header("Location: quadro.php?id=" . $_POST['quadro_id']); exit;
        }

        // --- 8. NOVA TAREFA (FIX: STATUS ID) ---
        if ($acao === 'nova_tarefa') {
            $qid = (int)$_POST['quadro_id'];
            $stmt_st = $pdo->prepare("SELECT id FROM quadros_status WHERE quadro_id = ? ORDER BY id ASC LIMIT 1");
            $stmt_st->execute([$qid]);
            $st_id = $stmt_st->fetchColumn();
            $pdo->prepare("INSERT INTO tarefas_projetos (titulo, grupo_id, quadro_id, usuario_id, status_id) VALUES (?, ?, ?, ?, ?)")
                ->execute([trim($_POST['titulo']), (int)$_POST['grupo_id'], $qid, $user_id_sessao, $st_id]);
            echo "Sucesso"; exit;
        }

        // --- 9. ATUALIZAÇÕES GERAIS (AJAX) ---
        if ($acao === 'atualizar_campo_tarefa') {
            $pdo->prepare("UPDATE tarefas_projetos SET {$_POST['campo']} = ? WHERE id = ?")
                ->execute([$_POST['valor'], (int)$_POST['id']]);
            echo "Sucesso"; exit;
        }
        
        if ($acao === 'atualizar_campo_grupo') {
            $pdo->prepare("UPDATE projetos_grupos SET {$_POST['campo']} = ? WHERE id = ?")
                ->execute([$_POST['valor'], (int)$_POST['id']]);
            echo "Sucesso"; exit;
        }

        if ($acao === 'salvar_evidencia') {
            $pdo->prepare("UPDATE tarefas_projetos SET evidencias = ? WHERE id = ?")
                ->execute([$_POST['conteudo'], (int)$_POST['id']]);
            echo "Sucesso"; exit;
        }
    }
} catch (Exception $e) { echo "Erro: " . $e->getMessage(); }