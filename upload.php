<?php
/**
 * SISTEMA DE DRIVE PROFISSIONAL - PROCESSAMENTO DE UPLOAD
 * Localização: public_html/upload.php
 * 
 * Este arquivo processa múltiplos arquivos, verifica permissões,
 * isola diretórios por usuário e controla o limite de espaço (quota).
 */

// 1. Configurações de Exibição de Erros (Essencial para depuração na Locaweb)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. Aumentar limites de tempo para arquivos grandes (como vídeos)
set_time_limit(1800); // Define o tempo máximo de execução para 30 minutos
ini_set('memory_limit', '1024M'); // Aumenta o limite de memória para processar os arquivos

session_start();

// 3. Verificação de Segurança: O usuário está autenticado?
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403); // Acesso Proibido
    echo json_encode([
        'status' => 'error',
        'message' => 'Sessão expirada ou acesso negado. Por favor, realize o login novamente.'
    ]);
    exit;
}

require_once 'config.php';

$usuario_id = $_SESSION['usuario_id'];
$usuario_nivel = $_SESSION['usuario_nivel']; // 'admin' ou 'usuario'

// 4. Verificar se a requisição contém arquivos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['arquivos'])) {
    
    $arquivos = $_FILES['arquivos'];
    
    // Capturar o ID da pasta destino (se estiver dentro de uma pasta)
    $pasta_id = (isset($_POST['pasta_id']) && $_POST['pasta_id'] !== 'null' && $_POST['pasta_id'] !== '') ? (int)$_POST['pasta_id'] : null;

    // --- LÓGICA DE QUOTA (LIMITE DE ARMAZENAMENTO) ---

    // Buscar o limite do usuário no banco de dados
    $instrucao_quota = $pdo->prepare("SELECT quota_limite FROM usuarios WHERE id = ?");
    $instrucao_quota->execute([$usuario_id]);
    $dados_usuario = $instrucao_quota->fetch(PDO::FETCH_ASSOC);
    $quota_limite = $dados_usuario['quota_limite'] ?? 1073741824; // 1GB padrão se não houver valor

    // Somar o consumo atual do usuário
    $instrucao_consumo = $pdo->prepare("SELECT SUM(tamanho) FROM arquivos WHERE usuario_id = ?");
    $instrucao_consumo->execute([$usuario_id]);
    $consumo_atual = (float)$instrucao_consumo->fetchColumn() ?: 0;

    // Calcular o tamanho total do novo upload
    $tamanho_upload_atual = array_sum($arquivos['size']);

    // Se NÃO for administrador, validar se o espaço é suficiente
    if ($usuario_nivel !== 'admin') {
        if (($consumo_atual + $tamanho_upload_atual) > $quota_limite) {
            http_response_code(400); // Requisição inválida (falta de espaço)
            echo json_encode([
                'status' => 'error',
                'message' => 'Espaço insuficiente! O limite de 1GB foi atingido ou será excedido por este upload.'
            ]);
            exit;
        }
    }

    // --- PREPARAÇÃO DO DIRETÓRIO FÍSICO ---

    // Definir pasta exclusiva do usuário (user_1, user_2, etc)
    $diretorio_base = "uploads/user_" . $usuario_id . "/";

    // Criar o diretório se ele não existir fisicamente
    if (!is_dir($diretorio_base)) {
        // Criar com permissão 0755 (Padrão de segurança Locaweb)
        mkdir($diretorio_base, 0755, true);
        
        // Criar um arquivo index.php vazio dentro para impedir listagem direta via URL
        file_put_contents($diretorio_base . "index.php", "<?php // Acesso negado ?>");
    }

    $contador_sucesso = 0;
    $lista_erros = [];

    // --- PROCESSAMENTO INDIVIDUAL DE CADA ARQUIVO ---

    foreach ($arquivos['name'] as $indice => $nome_original) {
        
        // Verificar se houve erro no envio via PHP (erro de servidor ou tamanho post_max_size)
        if ($arquivos['error'][$indice] === UPLOAD_ERR_OK) {
            
            $tmp_name = $arquivos['tmp_name'][$indice];
            $tamanho_arquivo = $arquivos['size'][$indice];
            $tipo_mime = $arquivos['type'][$indice];
            
            // Gerar um nome de sistema único e seguro para evitar conflitos
            $extensao = strtolower(pathinfo($nome_original, PATHINFO_EXTENSION));
            $nome_unico_sistema = uniqid("drive_bds_") . "_" . date("His") . "." . $extensao;
            $caminho_final_disco = $diretorio_base . $nome_unico_sistema;

            // Mover o arquivo da pasta temporária para a pasta do usuário
            if (move_uploaded_file($tmp_name, $caminho_final_disco)) {
                
                try {
                    // Gravar os metadados no Banco de Dados
                    $sql_insert = "INSERT INTO arquivos (nome_original, nome_sistema, caminho, tipo, tamanho, usuario_id, pasta_id, data_upload) 
                                   VALUES (:orig, :sist, :path, :tipo, :tam, :user, :pasta, NOW())";
                    
                    $stmt_insert = $pdo->prepare($sql_insert);
                    $stmt_insert->execute([
                        ':orig'  => $nome_original,
                        ':sist'  => $nome_unico_sistema,
                        ':path'  => $caminho_final_disco,
                        ':tipo'  => $tipo_mime,
                        ':tam'   => $tamanho_arquivo,
                        ':user'  => $usuario_id,
                        ':pasta' => $pasta_id
                    ]);
                    
                    $contador_sucesso++;
                    
                } catch (PDOException $erro_db) {
                    // Se falhar o banco de dados, removemos o arquivo físico para não deixar lixo no servidor
                    if (file_exists($caminho_final_disco)) {
                        unlink($caminho_final_disco);
                    }
                    $lista_erros[] = "Erro ao registrar o arquivo $nome_original no banco de dados.";
                }

            } else {
                $lista_erros[] = "Não foi possível mover o arquivo $nome_original para o destino final.";
            }
        } else {
            $lista_erros[] = "O arquivo $nome_original apresentou erro no upload (Código: " . $arquivos['error'][$indice] . ").";
        }
    }

    // --- REGISTRO DE LOG E RESPOSTA FINAL ---

    if ($contador_sucesso > 0) {
        // Registrar a ação no sistema de auditoria
        if (function_exists('registrarLog')) {
            registrarLog($pdo, $usuario_id, "Upload", "O usuário enviou $contador_sucesso arquivo(s) com sucesso.");
        }
        
        echo json_encode([
            'status' => (count($lista_erros) === 0) ? 'success' : 'partial_success',
            'message' => "$contador_sucesso arquivo(s) enviado(s) com sucesso.",
            'errors' => $lista_erros
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Nenhum arquivo pôde ser processado.',
            'errors' => $lista_erros
        ]);
    }

} else {
    // Caso o arquivo seja acessado diretamente sem envio de formulário
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Requisição inválida ou nenhum arquivo selecionado.'
    ]);
}
exit;