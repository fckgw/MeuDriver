<?php
session_start();
require_once 'config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) { exit; }

$tipo = $_POST['tipo'] ?? ''; 
$id = (int)$_POST['id'];
$permissao = $_POST['permissao'] ?? 'visualizar';
$via = $_POST['via'] ?? '';
$user_id = $_SESSION['usuario_id'];
$token = bin2hex(random_bytes(16));

$sql = "INSERT INTO compartilhamentos (pasta_id, arquivo_id, usuario_dono_id, permissao, token, data_compartilhado) 
        VALUES (?, ?, ?, ?, ?, NOW())";
$stmt = $pdo->prepare($sql);
$stmt->execute([
    ($tipo == 'pasta' ? $id : null),
    ($tipo == 'arquivo' ? $id : null),
    $user_id,
    $permissao,
    $token
]);

$link = "https://workspace.bdsoft.com.br/view_share.php?t=" . $token;

if($via === 'whatsapp') {
    $texto = "Olá, satisfação!%0A%0A"
           . "Estou compartilhando um " . $tipo . " com você no Workspace Drive.%0A%0A"
           . "Acesse aqui: " . $link;
           
    echo json_encode(['status' => 'success', 'url_whatsapp' => "https://api.whatsapp.com/send?text=" . $texto]);
}