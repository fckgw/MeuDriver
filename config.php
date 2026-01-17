<?php
// Credenciais de Desenvolvimento/Homologação
/*$host = 'driverbds.mysql.dbaas.com.br';
$db   = 'driverbds';
$user = 'driverbds';
$pass = 'BDSoft@1020';*/
/******** Final Credenciais de Desenvolvimento/Homologação************************/

// Credenciais de Produção
$host = 'workspacebds.mysql.dbaas.com.br'; 
$db   = 'workspacebds';
$user = 'workspacebds';
$pass = 'BDSoft@1020';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro de conexão no BDSoft Workspace: " . $e->getMessage());
}

// ESTA FUNÇÃO É OBRIGATÓRIA PARA O LOGIN NÃO DAR TELA BRANCA
function registrarLog($pdo, $usuario_id, $acao, $detalhes) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'];
        $sql = "INSERT INTO logs (usuario_id, acao, detalhes, ip, data_hora) VALUES (?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$usuario_id, $acao, $detalhes, $ip]);
    } catch (Exception $e) {
        // Se falhar o log, o sistema continua para não travar o usuário
    }
}
?>