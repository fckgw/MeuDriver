<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>


<?php
session_start();

// Se a sessão existir, manda para o Dashboard
if (isset($_SESSION['usuario_id'])) {
    header("Location: dashboard.php");
    exit;
} else {
    // Caso contrário, manda para o Login
    header("Location: login.php");
    exit;
}
?>