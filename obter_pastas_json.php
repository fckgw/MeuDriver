<?php
session_start();
require_once 'config.php';
$stmt = $pdo->prepare("SELECT id, nome FROM pastas WHERE usuario_id = ? ORDER BY nome ASC");
$stmt->execute([$_SESSION['usuario_id']]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));