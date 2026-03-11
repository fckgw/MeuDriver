<?php
/**
 * BDSoft Workspace - Processamento de Upload com Validação de Quota Real
 */
session_start();
require_once 'config.php';

// Configurações de Servidor
set_time_limit(1800);
ini_set('memory_limit', '1024M');

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    exit(json_encode(['status' => 'error', 'message' => 'Sessão expirada.']));
}

$user_id = $_SESSION['usuario_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['arquivos'])) {
    
    $arquivos = $_FILES['arquivos'];
    $pasta_id = (!empty($_POST['pasta_id']) && $_POST['pasta_id'] !== 'null') ? (int)$_POST['pasta_id'] : null;

    // 1. BUSCAR QUOTA ATUALIZADA (O que você definiu no admin_usuarios.php)
    $stmtUser = $pdo->prepare("SELECT nivel, espaco_gb FROM usuarios WHERE id = ?");
    $stmtUser->execute([$user_id]);
    $uData = $stmtUser->fetch(PDO::FETCH_ASSOC);

    $is_admin = (strtolower($uData['nivel']) === 'admin');
    $quota_gb = (!empty($uData['espaco_gb'])) ? (int)$uData['espaco_gb'] : 5;

    // 2. VERIFICAÇÃO DE QUOTA (Apenas para Membros)
    if (!$is_admin) {
        $quota_maxima_bytes = $quota_gb * 1073741824;

        $stmtUso = $pdo->prepare("SELECT SUM(tamanho) FROM arquivos WHERE usuario_id = ?");
        $stmtUso->execute([$user_id]);
        $uso_atual_bytes = (float)$stmtUso->fetchColumn() ?: 0;

        $tamanho_subindo = array_sum($arquivos['size']);

        if (($uso_atual_bytes + $tamanho_subindo) > $quota_maxima_bytes) {
            exit(json_encode([
                'status' => 'error', 
                'message' => 'Você atingiu seu limite de armazenamento (' . $quota_gb . 'GB). Exclua arquivos para continuar.'
            ]));
        }
    }

    // 3. DIRETÓRIO
    $diretorio = "uploads/user_" . $user_id . "/";
    if (!is_dir($diretorio)) {
        mkdir($diretorio, 0777, true);
    }

    $sucessos = 0;

    // 4. PROCESSAR CADA ARQUIVO
    foreach ($arquivos['name'] as $k => $nome_original) {
        if ($arquivos['error'][$k] === UPLOAD_ERR_OK) {
            
            $extensao = strtolower(pathinfo($nome_original, PATHINFO_EXTENSION));
            $nome_sistema = uniqid("bds_") . "_" . date("His") . "." . $extensao;
            $caminho_final = $diretorio . $nome_sistema;

            if (move_uploaded_file($arquivos['tmp_name'][$k], $caminho_final)) {
                $sql = "INSERT INTO arquivos (nome_original, nome_sistema, caminho, tipo, tamanho, usuario_id, pasta_id, data_upload) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $nome_original, 
                    $nome_sistema, 
                    $caminho_final, 
                    $arquivos['type'][$k], 
                    $arquivos['size'][$k], 
                    $user_id, 
                    $pasta_id
                ]);
                $sucessos++;
            }
        }
    }

    echo json_encode(['status' => 'success', 'message' => "Upload de $sucessos arquivo(s) concluído."]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Nenhum arquivo detectado pelo servidor.']);
}