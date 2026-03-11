<?php
/**
 * BDSoft Workspace - PROCESSAMENTO DE EXCLUSÃO EM MASSA
 * Local: driverbds.tecnologia.ws
 */

session_start();
require_once 'config.php';

// 1. Verificação de Segurança: Usuário está logado?
if (!isset($_SESSION['usuario_id'])) { 
    die("Erro: Acesso negado. Sessão expirada."); 
}

$user_id = $_SESSION['usuario_id'];

// 2. Verifica se o método é POST e se os IDs foram enviados
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ids'])) {
    
    $ids = $_POST['ids']; // Array de IDs selecionados no Dashboard

    if (!empty($ids) && is_array($ids)) {
        
        // Converte todos os IDs para inteiros por segurança
        $ids = array_map('intval', $ids);

        // Criar os placeholders para a query SQL (ex: ?, ?, ?)
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        try {
            // --- PASSO 1: BUSCAR OS ARQUIVOS PARA APAGAR DO DISCO (HD) ---
            // É fundamental buscar o nome_sistema para localizar o arquivo físico
            $sqlBusca = "SELECT nome_sistema FROM arquivos WHERE usuario_id = ? AND id IN ($placeholders)";
            $stmtBusca = $pdo->prepare($sqlBusca);
            
            // Mesclamos o ID do usuário com o array de IDs dos arquivos para o execute
            $params = array_merge([$user_id], $ids);
            $stmtBusca->execute($params);
            $arquivos = $stmtBusca->fetchAll(PDO::FETCH_ASSOC);

            $contador_fisico = 0;

            foreach ($arquivos as $arq) {
                // Ajuste do Caminho: Sincronizado com a estrutura de pastas por usuário
                $caminho_arquivo = "uploads/user_" . $user_id . "/" . $arq['nome_sistema'];
                
                if (file_exists($caminho_arquivo)) {
                    if (unlink($caminho_arquivo)) {
                        $contador_fisico++;
                    }
                }
            }

            // --- PASSO 2: APAGAR OS REGISTROS DO BANCO DE DADOS ---
            $sqlDel = "DELETE FROM arquivos WHERE usuario_id = ? AND id IN ($placeholders)";
            $stmtDel = $pdo->prepare($sqlDel);
            $stmtDel->execute($params);
            
            $quantidade_deletada = $stmtDel->rowCount();

            // --- PASSO 3: REGISTRAR LOG DE AUDITORIA ---
            if (function_exists('registrarLog')) {
                registrarLog($pdo, $user_id, "Exclusão", "Removeu permanentemente $quantidade_deletada arquivo(s).");
            }

            // Retorno para o AJAX do Dashboard
            echo "Sucesso";

        } catch (Exception $e) {
            // Em caso de erro no banco de dados
            http_response_code(500);
            echo "Erro ao processar exclusão: " . $e->getMessage();
        }
    } else {
        echo "Nenhum item selecionado para exclusão.";
    }
} else {
    http_response_code(405);
    echo "Método de requisição inválido.";
}