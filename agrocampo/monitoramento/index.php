<?php
/**
 * BDSoft Workspace - PAINEL GERAL DE MONITORAMENTO
 * Localização: agrocampo/monitoramento/index.php
 */
session_start();
require_once '../../config.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../../login.php");
    exit;
}

$user_id = $_SESSION['usuario_id'];

// --- BUSCA DE DADOS PARA KPIs ---

// 1. Total de Fazendas
$stmt_faz = $pdo->prepare("SELECT COUNT(*) FROM agro_fazendas WHERE usuario_id = ?");
$stmt_faz->execute([$user_id]);
$total_fazendas = $stmt_faz->fetchColumn();

// 2. Área Total Monitorada (Soma de todos os talhões)
$stmt_area = $pdo->prepare("SELECT SUM(area_hectares) FROM agro_talhoes t INNER JOIN agro_fazendas f ON t.fazenda_id = f.id WHERE f.usuario_id = ?");
$stmt_area->execute([$user_id]);
$area_total_ha = $stmt_area->fetchColumn() ?: 0;

// 3. Talhões Ativos (Em plantio ou colheita)
$stmt_ativos = $pdo->prepare("SELECT COUNT(*) FROM agro_talhoes t INNER JOIN agro_fazendas f ON t.fazenda_id = f.id WHERE f.usuario_id = ? AND t.status IN ('Plantado', 'Colheita')");
$stmt_ativos->execute([$user_id]);
$talhoes_ativos = $stmt_ativos->fetchColumn();

// 4. Últimos Talhões Cadastrados para o Grid
$stmt_grid = $pdo->prepare("SELECT t.*, f.nome as fazenda_nome FROM agro_talhoes t INNER JOIN agro_fazendas f ON t.fazenda_id = f.id WHERE f.usuario_id = ? ORDER BY t.id DESC LIMIT 6");
$stmt_grid->execute([$user_id]);
$talhoes_recentes = $stmt_grid->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel de Monitoramento - AgroCampo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f7f4; display: flex; min-height: 100vh; margin: 0; font-family: 'Segoe UI', sans-serif; }
        /* Wrapper para compensar o menu lateral fixo */
        .main-wrapper { flex: 1; margin-left: 280px; padding: 40px; width: calc(100% - 280px); transition: 0.3s; }
        .card-kpi { border: none; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); background: #fff; }
        .status-badge { font-size: 0.7rem; padding: 4px 10px; border-radius: 20px; font-weight: bold; }
        @media (max-width: 991px) { .main-wrapper { margin-left: 0; width: 100%; padding: 20px; } }
    </style>
</head>
<body>

<?php 
// Chamando o NOVO menu lateral que você gostou
include 'sidebar_monitoramento.php'; 
?>

<div class="main-wrapper">
    
    <!-- CABEÇALHO -->
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h2 class="fw-bold text-dark mb-0">Monitoramento de Talhões</h2>
            <p class="text-muted small">Visão geral das suas áreas e produtividade agrícola.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="talhoes_cadastro.php" class="btn btn-success rounded-pill px-4 fw-bold shadow">
                <i class="fas fa-draw-polygon me-2"></i>DESENHAR NOVO ÁREA
            </a>
        </div>
    </div>

    <!-- KPIs DE MONITORAMENTO (SUBSTITUINDO O FINANCEIRO) -->
    <div class="row g-4 mb-5 text-center">
        <div class="col-md-3">
            <div class="card card-kpi p-3 border-start border-success border-5">
                <small class="fw-bold text-muted text-uppercase">Área Monitorada</small>
                <h4 class="text-success fw-bold mb-0 mt-1"><?php echo number_format($area_total_ha, 2, ',', '.'); ?> <span class="small">ha</span></h4>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-kpi p-3 border-start border-primary border-5">
                <small class="fw-bold text-muted text-uppercase">Fazendas</small>
                <h4 class="text-primary fw-bold mb-0 mt-1"><?php echo $total_fazendas; ?> <span class="small">Unid.</span></h4>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-kpi p-3 border-start border-warning border-5">
                <small class="fw-bold text-muted text-uppercase">Talhões Ativos</small>
                <h4 class="text-warning fw-bold mb-0 mt-1"><?php echo $talhoes_ativos; ?></h4>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-kpi p-3 border-start border-danger border-5">
                <small class="fw-bold text-muted text-uppercase">Alertas de Pragas</small>
                <h4 class="text-danger fw-bold mb-0 mt-1">0</h4>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- GRID DE TALHÕES RECENTES -->
        <div class="col-lg-8">
            <div class="card card-kpi p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold mb-0">Situação das Áreas</h5>
                    <a href="talhoes_cadastro.php" class="btn btn-sm btn-outline-dark rounded-pill">Ver Todos</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr class="small text-muted">
                                <th>TALHÃO</th>
                                <th>FAZENDA</th>
                                <th>ÁREA</th>
                                <th>SOLO</th>
                                <th class="text-center">STATUS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($talhoes_recentes)): ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">Nenhum talhão desenhado ainda.</td></tr>
                            <?php else: ?>
                                <?php foreach($talhoes_recentes as $tr): 
                                    $cor = match($tr['status']) {
                                        'Plantado' => 'bg-success',
                                        'Colheita' => 'bg-info text-white',
                                        'Preparando' => 'bg-warning text-dark',
                                        default => 'bg-secondary text-white'
                                    };
                                ?>
                                <tr>
                                    <td class="fw-bold text-dark"><?php echo $tr['nome']; ?></td>
                                    <td class="small"><?php echo $tr['fazenda_nome']; ?></td>
                                    <td class="fw-bold"><?php echo $tr['area_hectares']; ?> ha</td>
                                    <td><span class="badge bg-light text-dark border"><?php echo $tr['tipo_solo'] ?: 'Não Inf.'; ?></span></td>
                                    <td class="text-center">
                                        <span class="status-badge <?php echo $cor; ?> text-uppercase"><?php echo $tr['status']; ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- SIDEBAR DE INTELIGÊNCIA (CONFORME PDF) -->
        <div class="col-lg-4">
            <div class="card card-kpi p-4 h-100 bg-dark text-white">
                <h5 class="fw-bold mb-4"><i class="fas fa-brain text-success me-2"></i>IA Agrícola</h5>
                
                <div class="p-3 bg-secondary bg-opacity-25 rounded-4 mb-3 border border-secondary">
                    <small class="text-success fw-bold text-uppercase">Sugestão de Cultura</small>
                    <p class="mb-0 mt-1 small">Baseado na época do ano (Maio) e região, a janela para **Milho Safrinha** está fechando. Recomenda-se agilizar o plantio.</p>
                </div>

                <div class="p-3 bg-secondary bg-opacity-25 rounded-4 mb-3 border border-secondary">
                    <small class="text-info fw-bold text-uppercase">Clima Previsto</small>
                    <div class="d-flex align-items-center mt-2">
                        <i class="fas fa-cloud-rain fa-2x me-3"></i>
                        <div>
                            <span class="d-block fw-bold">15mm previstos</span>
                            <small class="opacity-75">Para os próximos 3 dias.</small>
                        </div>
                    </div>
                </div>

                <div class="mt-auto">
                    <button class="btn btn-outline-success w-100 rounded-pill fw-bold">SOLICITAR ANÁLISE NDVI</button>
                </div>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>