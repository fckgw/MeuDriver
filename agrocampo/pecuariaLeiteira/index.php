<?php
/**
 * BDSoft Workspace - PAINEL PECUÁRIA LEITEIRA
 * Local: agrocampo/pecuariaLeiteira/index.php
 */
session_start();

// ATIVA EXIBIÇÃO DE ERROS PARA DESCOBRIR O MOTIVO DO 500
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Ajuste do caminho do config conforme a estrutura da sua URL
require_once '../../config.php'; 

if (!isset($_SESSION['usuario_id'])) { 
    header("Location: ../../login.php"); 
    exit; 
}

$user_id = $_SESSION['usuario_id'];
$hoje = date('Y-m-d');

try {
    // KPI 1: Produção de hoje
    $stmt_litros = $pdo->prepare("SELECT SUM(litros) FROM agro_leite_ordenha o INNER JOIN agro_leite_vacas v ON o.vaca_id = v.id WHERE v.usuario_id = ? AND o.data = ?");
    $stmt_litros->execute([$user_id, $hoje]);
    $litros_hoje = $stmt_litros->fetchColumn() ?: 0;

    // KPI 2: Vacas em lactação (Ativas)
    $stmt_ativas = $pdo->prepare("SELECT COUNT(*) FROM agro_leite_vacas WHERE usuario_id = ? AND status = 'Ativa'");
    $stmt_ativas->execute([$user_id]);
    $vacas_ativas = $stmt_ativas->fetchColumn() ?: 0;

    // KPI 3: Vacas Prenhas
    $stmt_prenhas = $pdo->prepare("SELECT COUNT(*) FROM agro_leite_vacas WHERE usuario_id = ? AND status = 'Prenha'");
    $stmt_prenhas->execute([$user_id]);
    $vacas_prenhas = $stmt_prenhas->fetchColumn() ?: 0;

} catch (PDOException $e) {
    // Caso a tabela não exista, o erro aparecerá aqui
    die("Erro no Banco de Dados: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pecuária Leiteira - AgroCampo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f7f4; display: flex; min-height: 100vh; font-family: 'Segoe UI', sans-serif; margin: 0; }
        .main-wrapper { flex: 1; margin-left: 280px; padding: 40px; width: calc(100% - 280px); transition: 0.3s; }
        .card-kpi { border: none; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); background: #fff; }
        @media (max-width: 991px) { .main-wrapper { margin-left: 0; width: 100%; padding: 20px; } }
    </style>
</head>
<body>

<?php 
// Verifica se o arquivo existe antes de incluir
if(file_exists('sidebar_leite.php')) {
    include 'sidebar_leite.php'; 
} else {
    echo "Menu não encontrado!";
}
?>

<div class="main-wrapper">
    <h2 class="fw-bold text-dark mb-4">Dashboard Leiteiro</h2>
    
    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="card card-kpi p-4 border-start border-primary border-5">
                <small class="text-muted fw-bold text-uppercase">Produção Hoje</small>
                <h2 class="fw-bold text-primary mb-0"><?php echo number_format($litros_hoje, 1, ',', '.'); ?> L</h2>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-kpi p-4 border-start border-success border-5">
                <small class="text-muted fw-bold text-uppercase">Lactação (Ativas)</small>
                <h2 class="fw-bold text-success mb-0"><?php echo $vacas_ativas; ?> Matrizes</h2>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-kpi p-4 border-start border-warning border-5">
                <small class="text-muted fw-bold text-uppercase">Confirmadas Prenhas</small>
                <h2 class="fw-bold text-warning mb-0"><?php echo $vacas_prenhas; ?></h2>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card card-kpi p-4 h-100">
                <h5 class="fw-bold mb-3"><i class="fas fa-chart-area me-2"></i>Histórico de Produção</h5>
                <p class="text-muted small">Os dados aparecerão aqui conforme os lançamentos de ordenha forem realizados.</p>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card card-kpi p-4 bg-dark text-white h-100">
                <h5 class="fw-bold mb-3"><i class="fas fa-bell me-2 text-warning"></i>Alertas</h5>
                <div class="small mb-3 text-info"><i class="fas fa-info-circle me-2"></i> Nenhuma vaca para secagem hoje.</div>
                <div class="small text-danger"><i class="fas fa-exclamation-triangle me-2"></i> 0 Animais em tratamento.</div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>