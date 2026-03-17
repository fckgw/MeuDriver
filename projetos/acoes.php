<?php
/**
 * BDSoft Workspace - PROCESSAMENTO DE AÇÕES DOS PROJETOS
 * Localização: projetos/acoes.php
 */
session_start();
require_once '../config.php';

// Verificação de Sessão
if (!isset($_SESSION['usuario_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'erro', 'msg' => 'Sessão expirada']);
    exit;
}

$uid_sessao = $_SESSION['usuario_id'];

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Captura a ação via POST ou GET
    $acao = $_POST['acao'] ?? $_GET['acao'] ?? null;

    if (!$acao) {
        throw new Exception("Ação não informada");
    }

    // Define o cabeçalho JSON para quase todas as ações (exceto listagem de HTML)
    if ($acao !== 'get_updates') {
        header('Content-Type: application/json');
    }

    switch ($acao) {

        // =========================
        // 🏷 STATUS (ETIQUETAS)
        // =========================
        case 'add_status':
            $pdo->prepare("INSERT INTO quadros_status (quadro_id, label, cor) VALUES (?, ?, ?)")
                ->execute([$_POST['quadro_id'], $_POST['label'], $_POST['cor']]);
            echo json_encode(['status' => 'ok']);
            break;

        case 'excluir_status':
            $pdo->prepare("DELETE FROM quadros_status WHERE id = ?")->execute([$_POST['status_id']]);
            echo json_encode(['status' => 'ok']);
            break;

        // =========================
        // 📌 TAREFAS
        // =========================
        case 'excluir_tarefa':
            $id = (int)$_POST['id'];
            // Limpa os updates antes de deletar a tarefa por causa da integridade de dados
            $pdo->prepare("DELETE FROM tarefas_updates WHERE tarefa_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM tarefas_projetos WHERE id = ?")->execute([$id]);
            echo json_encode(['status' => 'ok']);
            break;

        case 'nova_tarefa_completa':
            $quadro_id = (int)$_POST['quadro_id'];
            // Pega o primeiro status disponível para o quadro para não deixar nulo
            $st_id = $pdo->query("SELECT id FROM quadros_status WHERE quadro_id = $quadro_id ORDER BY id ASC LIMIT 1")->fetchColumn();

            $pdo->prepare("
                INSERT INTO tarefas_projetos 
                (titulo, grupo_id, quadro_id, usuario_id, status_id, data_inicio, data_fim) 
                VALUES (?,?,?,?,?,?,?)
            ")->execute([
                $_POST['titulo'],
                $_POST['grupo_id'],
                $quadro_id,
                $uid_sessao,
                $st_id,
                $_POST['data_inicio'],
                $_POST['data_fim']
            ]);
            echo json_encode(['status' => 'ok']);
            break;

        case 'get_full_task':
            $stmt = $pdo->prepare("SELECT data_inicio, data_fim, justificativa FROM tarefas_projetos WHERE id = ?");
            $stmt->execute([(int)$_POST['id']]);
            echo json_encode($stmt->fetch(PDO::FETCH_ASSOC) ?: []);
            break;

        case 'atualizar_campo_tarefa':
            $id = (int)$_POST['id'];
            $campo = $_POST['campo'];
            $valor = $_POST['valor'];
            $allowed = ['titulo','data_inicio','data_fim','justificativa','status_id'];

            if (!in_array($campo, $allowed)) throw new Exception("Campo inválido");

            if ($campo === 'status_id') {
                $st = $pdo->prepare("SELECT label FROM quadros_status WHERE id = ?");
                $st->execute([$valor]);
                $lbl = $st->fetchColumn();
                $concluido = stripos($lbl, 'concluido') !== false || stripos($lbl, 'concluído') !== false;

                $sql = $concluido
                    ? "UPDATE tarefas_projetos SET status_id = ?, data_conclusao = CURDATE() WHERE id = ?"
                    : "UPDATE tarefas_projetos SET status_id = ?, data_conclusao = NULL WHERE id = ?";
                $pdo->prepare($sql)->execute([$valor, $id]);
            } else {
                $pdo->prepare("UPDATE tarefas_projetos SET $campo = ? WHERE id = ?")->execute([$valor, $id]);
            }
            echo json_encode(['status' => 'ok']);
            break;

        // =========================
        // 📝 TIMELINE (UPDATES)
        // =========================
        case 'salvar_update':
            $update_id = isset($_POST['update_id']) ? (int)$_POST['update_id'] : 0;
            $tarefa_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $conteudo = $_POST['conteudo'] ?? '';

            if ($update_id > 0) {
                // Editar existente
                $stmt = $pdo->prepare("UPDATE tarefas_updates SET conteudo = ? WHERE id = ?");
                $stmt->execute([$conteudo, $update_id]);
            } else {
                // Novo registro
                $stmt = $pdo->prepare("INSERT INTO tarefas_updates (tarefa_id, usuario_id, conteudo, data_criacao) VALUES (?, ?, ?, NOW())");
                $stmt->execute([$tarefa_id, $uid_sessao, $conteudo]);
            }
            echo json_encode(['status' => 'ok']);
            break;

        case 'excluir_update':
            // Recebe 'id' vindo do formulário JavaScript
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            if ($id <= 0) throw new Exception("ID inválido para exclusão");

            $pdo->prepare("DELETE FROM tarefas_updates WHERE id = ?")->execute([$id]);
            echo json_encode(['status' => 'ok']);
            break;

        case 'get_updates':
            $stmt = $pdo->prepare("
                SELECT u.*, us.nome as autor 
                FROM tarefas_updates u 
                INNER JOIN usuarios us ON u.usuario_id = us.id 
                WHERE u.tarefa_id = ? 
                ORDER BY u.data_criacao DESC
            ");
            $stmt->execute([$_GET['id']]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach($rows as $r) {
                // Limpeza crítica para não quebrar o JavaScript
                $preview_texto = strip_tags($r['conteudo']);
                $preview_texto = str_replace(["\r", "\n", "'", '"'], " ", $preview_texto); // Remove quebras e aspas
                $preview_texto = mb_strimwidth($preview_texto, 0, 80, "..."); // Limita tamanho
                $preview_js = addslashes($preview_texto); // Escapa para segurança final

                echo "<div class='card mb-3 shadow-sm border-0' style='border-radius:12px;'>
                        <div class='card-header bg-white border-0 d-flex justify-content-between align-items-center pt-3 px-3'>
                            <span class='fw-bold text-primary' style='font-size:13px;'>{$r['autor']}</span>
                            <small class='text-muted' style='font-size:10px;'>".date('d/m/Y H:i', strtotime($r['data_criacao']))."</small>
                            <div>
                                <button class='btn btn-link btn-sm text-primary p-0 me-2' onclick='prepararEdicaoUpdate({$r['id']})' title='Editar'>
                                    <i class='fas fa-pencil-alt'></i>
                                </button>
                                <button class='btn btn-link btn-sm text-danger p-0' onclick='abrirModalExcluirUpdate({$r['id']}, \"$preview_js\")' title='Excluir'>
                                    <i class='fas fa-trash'></i>
                                </button>
                            </div>
                        </div>
                        <div class='card-body pt-0 pb-3 px-3' id='texto_update_{$r['id']}' style='font-size:14px; color:#444;'>
                            {$r['conteudo']}
                        </div>
                    </div>";
            }
            break;

        // =========================
        // 📂 GRUPOS
        // =========================
        case 'novo_grupo':
            $pdo->prepare("INSERT INTO projetos_grupos (nome, quadro_id, cor) VALUES (?,?,?)")
                ->execute([$_POST['nome_grupo'], $_POST['quadro_id'], $_POST['cor']]);
            echo json_encode(['status' => 'ok']);
            break;

        case 'editar_grupo_full':
            $pdo->prepare("UPDATE projetos_grupos SET nome = ?, cor = ? WHERE id = ?")
                ->execute([$_POST['nome'], $_POST['cor'], (int)$_POST['grupo_id']]);
            echo json_encode(['status' => 'ok']);
            break;

        case 'excluir_grupo':
            $gid = (int)$_POST['grupo_id'];
            $pdo->prepare("DELETE FROM tarefas_projetos WHERE grupo_id = ?")->execute([$gid]);
            $pdo->prepare("DELETE FROM projetos_grupos WHERE id = ?")->execute([$gid]);
            echo json_encode(['status' => 'ok']);
            break;

        case 'atualizar_campo_grupo':
            $pdo->prepare("UPDATE projetos_grupos SET {$_POST['campo']} = ? WHERE id = ?")
                ->execute([$_POST['valor'], (int)$_POST['id']]);
            echo json_encode(['status' => 'ok']);
            break;

        default:
            throw new Exception("Ação inválida");
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'erro', 'msg' => $e->getMessage()]);
}