<?php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ids'])) {
    $ids = $_POST['ids'];
    $destino_id = ($_POST['destino_id'] === 'null' || $_POST['destino_id'] === '') ? null : (int)$_POST['destino_id'];
    $user_id = $_SESSION['usuario_id'];

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "UPDATE arquivos SET pasta_id = ? WHERE usuario_id = ? AND id IN ($placeholders)";
    
    $stmt = $pdo->prepare($sql);
    $params = array_merge([$destino_id, $user_id], $ids);
    $stmt->execute($params);
    
    echo "Sucesso";
}