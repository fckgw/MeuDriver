<?php
/**
 * Gateway de Redirecionamento SSO
 * Local: workspace.bdsoft.com.br/ir_para.php
 */
session_start();
require_once 'config.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['usuario_id'];
$modulo_slug = $_GET['modulo'] ?? '';

// 1. Verificar se o usuário tem permissão para o módulo (agroCampo)
// Ajuste a query conforme sua tabela de permissões
$stmt = $pdo->prepare("SELECT m.id FROM modulos m 
                       INNER JOIN usuarios_modulos um ON m.id = um.modulo_id 
                       WHERE um.usuario_id = ? AND m.slug LIKE ?");
$stmt->execute([$user_id, "%$modulo_slug%"]);
$permissao = $stmt->fetch();

if (!$permissao && $_SESSION['usuario_nivel'] !== 'admin') {
    die("Você não tem permissão para acessar este módulo.");
}

// 2. Gerar IToken Único
$token = bin2hex(random_bytes(32));
$expira = date('Y-m-d H:i:s', strtotime('+30 seconds')); // Token vale só 30 segundos

$stmtToken = $pdo->prepare("INSERT INTO sso_tokens (usuario_id, token, expira_em) VALUES (?, ?, ?)");
$stmtToken->execute([$user_id, $token, $expira]);

// 3. Redirecionar para o Subdomínio
// Se o slug for 'agrocampo', mandamos para o novo subdominio
$url_destino = "https://agrocampo.bdsoft.com.br/sso.php?t=" . $token;

header("Location: " . $url_destino);
exit;