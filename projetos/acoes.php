<?php
/**
 * BDSoft Workspace - PROJETOS / ACOES
 * Local: projetos/acoes.php
 */
session_start();
require_once '../config.php';

if (!isset($_SESSION['usuario_id'])) { exit; }
$user_id_sessao = $_SESSION['usuario_id'];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $acao = $_POST['acao'];

        // --- SALVAR EVIDÊNCIAS ---
        if ($acao === 'salvar_evidencia') {
            $pdo->prepare("UPDATE tarefas_projetos SET evidencias = ? WHERE id = ?")
                ->execute([$_POST['conteudo'], (int)$_POST['id']]);
            echo "Sucesso"; exit;
        }

        // --- NOVO GRUPO ---
        if ($acao === 'novo_grupo') {
            $pdo->prepare("INSERT INTO projetos_grupos (nome, quadro_id, cor) VALUES (?, ?, ?)")
                ->execute([trim($_POST['nome_grupo']), (int)$_POST['quadro_id'], $_POST['cor']]);
            header("Location: quadro.php?id=" . $_POST['quadro_id']); exit;
        }

        // --- ATUALIZAR CAMPO DO GRUPO ---
        if ($acao === 'atualizar_campo_grupo') {
            $pdo->prepare("UPDATE projetos_grupos SET {$_POST['campo']} = ? WHERE id = ?")
                ->execute([$_POST['valor'], (int)$_POST['id']]);
            echo "Sucesso"; exit;
        }

        // --- NOVA TAREFA ---
        if ($acao === 'nova_tarefa') {
            $qid = (int)$_POST['quadro_id'];
            $stmt_st = $pdo->prepare("SELECT id FROM quadros_status WHERE quadro_id = ? ORDER BY id ASC LIMIT 1");
            $stmt_st->execute([$qid]);
            $st_id = $stmt_st->fetchColumn();

            $pdo->prepare("INSERT INTO tarefas_projetos (titulo, grupo_id, quadro_id, usuario_id, status_id) VALUES (?, ?, ?, ?, ?)")
                ->execute([trim($_POST['titulo']), (int)$_POST['grupo_id'], $qid, $user_id_sessao, $st_id]);
            echo "Sucesso"; exit;
        }

        // --- ATUALIZAR CAMPO TAREFA (INCLUINDO DATAS) ---
        if ($acao === 'atualizar_campo_tarefa') {
            $campo = $_POST['campo'];
            $permitidos = ['status_id', 'prioridade', 'data_inicio', 'data_fim', 'titulo'];
            if (in_array($campo, $permitidos)) {
                $pdo->prepare("UPDATE tarefas_projetos SET $campo = ? WHERE id = ?")
                    ->execute([$_POST['valor'], (int)$_POST['id']]);
                echo "Sucesso";
            }
            exit;
        }
    }

    if (isset($_GET['acao']) && $_GET['acao'] === 'get_evidencia') {
        $stmt = $pdo->prepare("SELECT evidencias FROM tarefas_projetos WHERE id = ?");
        $stmt->execute([(int)$_GET['id']]);
        echo $stmt->fetchColumn() ?: ""; exit;
    }

    if (isset($_GET['del_grupo'])) {
        $pdo->prepare("DELETE FROM tarefas_projetos WHERE grupo_id = ?")->execute([(int)$_GET['del_grupo']]);
        $pdo->prepare("DELETE FROM projetos_grupos WHERE id = ?")->execute([(int)$_GET['del_grupo']]);
        header("Location: quadro.php?id=" . $_GET['quadro_id']); exit;
    }

} catch (Exception $e) { echo "Erro: " . $e->getMessage(); }