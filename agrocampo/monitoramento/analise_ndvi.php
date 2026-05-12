<?php
/**
 * BDSoft Workspace - ANÁLISE NDVI (SATÉLITE)
 * Localização: agrocampo/monitoramento/analise_ndvi.php
 */
session_start();
require_once '../../config.php';

if (!isset($_SESSION['usuario_id'])) { header("Location: ../../login.php"); exit; }

$user_id = $_SESSION['usuario_id'];

// Busca talhões para o seletor
$stmt = $pdo->prepare("SELECT t.id, t.nome, f.nome as fazenda FROM agro_talhoes t INNER JOIN agro_fazendas f ON t.fazenda_id = f.id WHERE f.usuario_id = ?");
$stmt->execute([$user_id]);
$talhoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Análise NDVI - AgroCampo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #0b0f0b; color: #fff; display: flex; font-family: 'Segoe UI', sans-serif; }
        .main-wrapper { flex: 1; margin-left: 280px; padding: 40px; width: calc(100% - 280px); }
        .ndvi-container { background: #1a1f1a; border-radius: 20px; padding: 20px; border: 1px solid #2ecc71; position: relative; overflow: hidden; }
        .ndvi-map-img { width: 100%; border-radius: 15px; filter: contrast(1.2) brightness(1.1); display: none; }
        .loading-ia { height: 400px; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .scanner-line { width: 100%; height: 2px; background: #2ecc71; position: absolute; top: 0; left: 0; box-shadow: 0 0 15px #2ecc71; animation: scan 3s infinite linear; display: none; }
        @keyframes scan { from { top: 0; } to { top: 100%; } }
        .legenda-item { font-size: 0.8rem; display: flex; align-items: center; gap: 8px; margin-bottom: 5px; }
        .cor-box { width: 15px; height: 15px; border-radius: 3px; }
        @media (max-width: 991px) { .main-wrapper { margin-left: 0; width: 100%; } }
    </style>
</head>
<body>

<?php include 'sidebar_monitoramento.php'; ?>

<div class="main-wrapper">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-success mb-0"><i class="fas fa-satellite-dish me-2"></i>Análise Espectral NDVI</h2>
            <p class="text-muted small">Índice de Vegetação por Diferença Normalizada (IA Felipinho)</p>
        </div>
        <div class="col-md-3">
            <select id="talhao_select" class="form-select bg-dark text-white border-success">
                <option value="">Selecione o Talhão...</option>
                <?php foreach($talhoes as $t): ?>
                    <option value="<?php echo $t['id']; ?>"><?php echo $t['fazenda']; ?> - <?php echo $t['nome']; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-9">
            <div class="ndvi-container shadow-lg">
                <div id="loading" class="loading-ia">
                    <i class="fas fa-robot fa-3x mb-3 text-success fa-bounce"></i>
                    <h5 class="fw-bold">Felipinho está processando os dados...</h5>
                    <p class="text-muted small">Solicitando imagens do satélite Sentinel-2</p>
                </div>

                <div id="scanner" class="scanner-line"></div>
                <img id="mapa_ndvi" src="https://upload.wikimedia.org/wikipedia/commons/e/e0/NDVI_mapping.jpg" class="ndvi-map-img" alt="Mapa NDVI">
            </div>
        </div>

        <div class="col-lg-3">
            <div class="card bg-dark border-secondary p-4 mb-4">
                <h6 class="fw-bold text-success mb-3">Legenda de Saúde</h6>
                <div class="legenda-item"><div class="cor-box" style="background: #00441b;"></div> Vigour Alto (0.8 - 1.0)</div>
                <div class="legenda-item"><div class="cor-box" style="background: #41ab5d;"></div> Crescimento Normal</div>
                <div class="legenda-item"><div class="cor-box" style="background: #f7fcb9;"></div> Estresse Moderado</div>
                <div class="legenda-item"><div class="cor-box" style="background: #ef3b2c;"></div> Solo Exposto / Praga</div>
                <hr class="border-secondary">
                <div id="ia_report" style="display: none;">
                    <p class="small fst-italic">"Notei uma mancha amarela no centro. Verifique o bico dos pulverizadores ou se há incidência de percevejo."</p>
                    <button class="btn btn-sm btn-outline-success w-100 mt-2" onclick="window.print()">GERAR PDF</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('talhao_select').addEventListener('change', function() {
    if(this.value !== "") {
        // Reinicia visual
        document.getElementById('loading').style.display = 'flex';
        document.getElementById('mapa_ndvi').style.display = 'none';
        document.getElementById('scanner').style.display = 'none';
        document.getElementById('ia_report').style.display = 'none';

        // Simula processamento da IA (3 segundos)
        setTimeout(() => {
            document.getElementById('loading').style.display = 'none';
            document.getElementById('mapa_ndvi').style.display = 'block';
            document.getElementById('scanner').style.display = 'block';
            document.getElementById('ia_report').style.display = 'block';
        }, 3000);
    }
});
</script>
</body>
</html>