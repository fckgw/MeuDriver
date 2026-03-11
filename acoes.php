<?php
/**
 * BDSoft Workspace - PROJETOS / ACOES
 * Versão Final Consolidada (Tarefas, Grupos, Status e Permissões de Usuários)
 */
session_start();
require_once '../config.php';
if (!isset($_SESSION['usuario_id'])) { exit; }
$uid_sessao = $_SESSION['usuario_id'];

try {
    // --- COMPARTILHAMENTO ---
    if (isset($_POST['acao']) && $_POST['acao'] === 'add_membro') {
        $pdo->prepare("INSERT IGNORE INTO quadro_membros (quadro_id, usuario_id) VALUES (?, ?)")
            ->execute([$_POST['quadro_id'], $_POST['usuario_id']]);
        echo "Sucesso"; exit;
    }
    if (isset($_GET['acao']) && $_GET['acao'] === 'remover_membro') {
        $pdo->prepare("DELETE FROM quadro_membros WHERE quadro_id = ? AND usuario_id = ?")
            ->execute([$_GET['qid'], $_GET['uid']]);
        header("Location: index.php"); exit;
    }

    // --- STATUS DINÂMICO ---
    if (isset($_POST['acao']) && $_POST['acao'] === 'add_status') {
        $pdo->prepare("INSERT INTO quadros_status (quadro_id, label, cor) VALUES (?, ?, ?)")
            ->execute([$_POST['quadro_id'], $_POST['label'], $_POST['cor']]);
        echo "Sucesso"; exit;
    }
    if (isset($_POST['acao']) && $_POST['acao'] === 'excluir_status') {
        $pdo->prepare("DELETE FROM quadros_status WHERE id = ?")->execute([$_POST['status_id']]);
        echo "Sucesso"; exit;
    }

    // --- BLOCO PRINCIPAL (POST) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $acao = $_POST['acao'];

        // ==========================================
        // MÓDULO: GESTÃO DE USUÁRIOS (ADMINISTRAÇÃO)
        // ==========================================
        if ($acao === 'editar_acesso_usuario') {
            $id = (int)$_POST['usuario_id'];
            $nivel = $_POST['nivel']; // Vai chegar 'admin' ou 'membro'
            
            if ($nivel === 'admin') {
                // Se for admin, o acesso é vitalício, limpa as datas (deixa NULL)
                $stmt = $pdo->prepare("UPDATE usuarios SET nivel = 'admin', data_inicio = NULL, data_fim = NULL WHERE id = ?");
                $stmt->execute([$id]);
            } else {
                // Se for membro, grava a data do pacote escolhido
                $data_ini = $_POST['data_inicio'];
                $data_fim = $_POST['data_fim'];
                
                $stmt = $pdo->prepare("UPDATE usuarios SET nivel = 'membro', data_inicio = ?, data_fim = ? WHERE id = ?");
                $stmt->execute([$data_ini, $data_fim, $id]);
            }
            echo "Sucesso"; exit;
        }

        // ==========================================
        // MÓDULO: QUADROS, GRUPOS E TAREFAS
        // ==========================================
        
        // Excluir Tarefa
        if ($acao === 'excluir_tarefa') {
            $id = (int)$_POST['id'];
            // Opcional: Limpar histórico/updates da tarefa para não deixar lixo no banco
            $pdo->prepare("DELETE FROM tarefas_updates WHERE tarefa_id = ?")->execute([$id]);
            // Excluir a tarefa
            $pdo->prepare("DELETE FROM tarefas_projetos WHERE id = ?")->execute([$id]);
            echo "Sucesso"; exit;
        }

        // Excluir Grupo
        if ($acao === 'excluir_grupo') {
            $gid = (int)$_POST['grupo_id'];
            // Primeiro removemos as tarefas deste grupo
            $pdo->prepare("DELETE FROM tarefas_projetos WHERE grupo_id = ?")->execute([$gid]);
            // Agora removemos o grupo
            $pdo->prepare("DELETE FROM projetos_grupos WHERE id = ?")->execute([$gid]);
            echo "Sucesso"; exit;
        }

        // Editar Grupo (Modal Completo)
        if ($acao === 'editar_grupo_full') {
            $pdo->prepare("UPDATE projetos_grupos SET nome = ?, cor = ? WHERE id = ?")
                ->execute([$_POST['nome'], $_POST['cor'], (int)$_POST['grupo_id']]);
            echo "Sucesso"; exit;
        }

        // Edição Rápida de Grupo (Inline Título)
        if ($acao === 'atualizar_campo_grupo') {
            $allowed_cols = ['nome', 'cor']; 
            if (in_array($_POST['campo'], $allowed_cols)) {
                $pdo->prepare("UPDATE projetos_grupos SET {$_POST['campo']} = ? WHERE id = ?")
                    ->execute([$_POST['valor'], (int)$_POST['id']]);
            }
            echo "Sucesso"; exit;
        }

        if ($acao === 'get_full_task') {
            $stmt = $pdo->prepare("SELECT data_inicio, data_fim, justificativa FROM tarefas_projetos WHERE id = ?");
            $stmt->execute([(int)$_POST['id']]);
            echo json_encode($stmt->fetch(PDO::FETCH_ASSOC) ?:['data_inicio'=>'','data_fim'=>'','justificativa'=>'']);
            exit;
        }

        if ($acao === 'nova_tarefa_completa') {
            $st_id = $pdo->query("SELECT id FROM quadros_status WHERE quadro_id = {$_POST['quadro_id']} ORDER BY id ASC LIMIT 1")->fetchColumn();
            $pdo->prepare("INSERT INTO tarefas_projetos (titulo, grupo_id, quadro_id, usuario_id, status_id, data_inicio, data_fim) VALUES (?,?,?,?,?,?,?)")
                ->execute([$_POST['titulo'], $_POST['grupo_id'], $_POST['quadro_id'], $uid_sessao, $st_id, $_POST['data_inicio'], $_POST['data_fim']]);
            echo "Sucesso"; exit;
        }

        if ($acao === 'atualizar_campo_tarefa') {
            $id = (int)$_POST['id']; $campo = $_POST['campo']; $valor = $_POST['valor'];
            if ($campo === 'status_id') {
                $st = $pdo->prepare("SELECT label FROM quadros_status WHERE id = ?"); $st->execute([$valor]); $lbl = $st->fetchColumn();
                $concluido = (strpos($lbl, 'Concluído') !== false || strpos($lbl, 'Concluido') !== false);
                $sql = $concluido ? "UPDATE tarefas_projetos SET status_id = ?, data_conclusao = CURDATE() WHERE id = ?" : "UPDATE tarefas_projetos SET status_id = ?, data_conclusao = NULL WHERE id = ?";
                $pdo->prepare($sql)->execute([$valor, $id]);
            } else { 
                $pdo->prepare("UPDATE tarefas_projetos SET $campo = ? WHERE id = ?")->execute([$valor, $id]); 
            }
            echo "Sucesso"; exit;
        }

        if ($acao === 'salvar_update') {
            $upId = (int)$_POST['update_id'];
            if($upId > 0) {
                $pdo->prepare("UPDATE tarefas_updates SET conteudo = ? WHERE id = ?")->execute([$_POST['conteudo'], $upId]);
            } else {
                $pdo->prepare("INSERT INTO tarefas_updates (tarefa_id, usuario_id, conteudo, data_criacao) VALUES (?,?,?,NOW())")->execute([$_POST['id'], $uid_sessao, $_POST['conteudo']]);
            }
            echo "Sucesso"; exit;
        }

        if ($acao === 'novo_grupo') {
            $pdo->prepare("INSERT INTO projetos_grupos (nome, quadro_id, cor) VALUES (?,?,?)")
                ->execute([$_POST['nome_grupo'], $_POST['quadro_id'], $_POST['cor']]);
            header("Location: quadro.php?id=".$_POST['quadro_id']); exit;
        }
    }

    // --- BLOCO GET (EXCLUSÃO E BUSCAS VIA URL) ---
    if (isset($_GET['acao']) && $_GET['acao'] === 'deletar_quadro_completo') {
        $pdo->prepare("DELETE FROM quadros_projetos WHERE id = ? AND (usuario_id = ? OR ? = 'admin')")->execute([$_GET['id'], $uid_sessao, $_SESSION['usuario_nivel']]);
        header("Location: index.php"); exit;
    }

    if (isset($_GET['acao']) && $_GET['acao'] === 'get_updates') {
        $stmt = $pdo->prepare("SELECT u.*, us.nome as autor FROM tarefas_updates u INNER JOIN usuarios us ON u.usuario_id = us.id WHERE u.tarefa_id = ? ORDER BY u.data_criacao DESC");
        $stmt->execute([$_GET['id']]);
        $rows = $stmt->fetchAll();
        foreach($rows as $r) {
            echo "<div class='card mb-3 shadow-sm border-0' style='border-radius:12px;'>
                    <div class='card-header bg-white border-0 d-flex justify-content-between align-items-center pt-3 px-3'>
                        <span class='fw-bold text-primary' style='font-size:13px;'>{$r['autor']}</span>
                        <small class='text-muted' style='font-size:10px;'>".date('d/m/Y H:i', strtotime($r['data_criacao']))."</small>
                        <div>
                            <button class='btn btn-link btn-sm text-primary p-0 me-2' onclick='prepararEdicaoUpdate({$r['id']})'><i class='fas fa-pencil-alt'></i></button>
                            <button class='btn btn-link btn-sm text-danger p-0' onclick='excluirUpdate({$r['id']})'><i class='fas fa-trash'></i></button>
                        </div>
                    </div>
                    <div class='card-body pt-0 pb-3 px-3' id='texto_update_{$r['id']}' style='font-size:14px; color:#444;'>
                        {$r['conteudo']}
                    </div>
                  </div>";
        }
        exit;
    }
} catch (Exception $e) { 
    echo "Erro: " . $e->getMessage(); 
}