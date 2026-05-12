<?php
/**
 * BDSoft Workspace - DESENHO E CADASTRO DE TALHÕES
 * Localização: agrocampo/monitoramento/talhoes_cadastro.php
 */
session_start();
require_once '../../config.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../../login.php");
    exit;
}

$user_id = $_SESSION['usuario_id'];
$fazenda_id_get = $_GET['fazenda_id'] ?? '';

// Busca as fazendas cadastradas para alimentar o Select
$stmt = $pdo->prepare("SELECT * FROM agro_fazendas WHERE usuario_id = ? ORDER BY nome ASC");
$stmt->execute([$user_id]);
$fazendas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Desenhar Talhões - Monitoramento AgroCampo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f7f4; display: flex; min-height: 100vh; margin: 0; font-family: 'Segoe UI', sans-serif; }
        .main-wrapper { flex: 1; margin-left: 280px; padding: 40px; width: calc(100% - 280px); transition: 0.3s; }
        #map { height: 600px; width: 100%; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .card-form { border-radius: 15px; border: none; background: #fff; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .coord-label { font-size: 0.75rem; font-weight: bold; color: #6c757d; }
        @media (max-width: 991px) { .main-wrapper { margin-left: 0; width: 100%; padding: 20px; } }
    </style>
</head>
<body>

<?php 
// Incluindo o NOVO padrão de menu lateral exclusivo do monitoramento
include 'sidebar_monitoramento.php'; 
?>

<div class="main-wrapper">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-0">Gestão Geográfica</h2>
            <p class="text-muted small">Delimite as áreas produtivas da sua fazenda diretamente no mapa.</p>
        </div>
        <a href="index.php" class="btn btn-outline-dark rounded-pill px-4 fw-bold">
            <i class="fas fa-undo me-2"></i>PAINEL GERAL
        </a>
    </div>

    <div class="row">
        <!-- ÁREA DO MAPA (COL-8) -->
        <div class="col-lg-8">
            <div class="card p-2 card-form mb-4">
                <div id="map"></div>
                <div class="p-3 text-center bg-light rounded-bottom">
                    <span class="badge bg-dark rounded-pill px-3 py-2">
                        <i class="fas fa-draw-polygon me-2 text-success"></i> Use o ícone de polígono no topo para desenhar o talhão
                    </span>
                </div>
            </div>
        </div>

        <!-- FORMULÁRIO TÉCNICO (COL-4) -->
        <div class="col-lg-4">
            <form action="acoes_monitoramento.php" method="POST" class="card p-4 card-form shadow-sm">
                <input type="hidden" name="acao" value="cadastrar_talhao">
                <input type="hidden" name="coordenadas_json" id="coordenadas_json">

                <h5 class="fw-bold mb-4 text-success border-bottom pb-2">
                    <i class="fas fa-info-circle me-2"></i>Dados do Talhão
                </h5>

                <div class="mb-3">
                    <label class="coord-label">SELECIONE A PROPRIEDADE</label>
                    <select name="fazenda_id" class="form-select shadow-sm" required>
                        <option value="">Escolha a fazenda...</option>
                        <?php foreach($fazendas as $f): ?>
                            <option value="<?php echo $f['id']; ?>" <?php echo ($fazenda_id_get == $f['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($f['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="coord-label">NOME DO TALHÃO</label>
                    <input type="text" name="nome" class="form-control" placeholder="Ex: Lote 05 - Milho" required>
                </div>

                <div class="row">
                    <div class="col-6 mb-3">
                        <label class="coord-label text-primary">LATITUDE</label>
                        <input type="text" id="lat_manual" name="latitude" class="form-control coord-input" placeholder="-22.9035">
                    </div>
                    <div class="col-6 mb-3">
                        <label class="coord-label text-primary">LONGITUDE</label>
                        <input type="text" id="lng_manual" name="longitude" class="form-control coord-input" placeholder="-47.0626">
                    </div>
                </div>

                <div class="row">
                    <div class="col-6 mb-3">
                        <label class="coord-label">ÁREA (HA)</label>
                        <input type="number" step="0.01" name="area_hectares" id="area_auto" class="form-control bg-light fw-bold text-success" readonly placeholder="0.00">
                    </div>
                    <div class="col-6 mb-3">
                        <label class="coord-label">TIPO DE SOLO</label>
                        <input type="text" name="tipo_solo" class="form-control" placeholder="Solo argiloso">
                    </div>
                </div>

                <div class="alert alert-warning mt-3 small border-0 py-2 shadow-sm" style="border-radius: 10px;">
                    <i class="fas fa-lightbulb me-1"></i> Digite Latitude/Longitude para buscar o local automaticamente.
                </div>

                <hr class="my-4">

                <button type="submit" class="btn btn-success w-100 rounded-pill fw-bold py-3 shadow border-0">
                    <i class="fas fa-check-circle me-2"></i>SALVAR NO MAPA
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- API DO GOOGLE MAPS COM SUA CHAVE -->
<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyChUTpowW0eLTwx_t6eQiaZdqF-ZcJm2RI&libraries=drawing,geometry"></script>

<script>
let map;
let drawingManager;
let currentPolygon = null;

function initMap() {
    // Inicia no Brasil Central
    const centroPadrao = { lat: -15.7942, lng: -47.8822 };

    map = new google.maps.Map(document.getElementById("map"), {
        zoom: 4,
        center: centroPadrao,
        mapTypeId: 'satellite',
        disableDefaultUI: false,
        zoomControl: true,
        streetViewControl: false
    });

    drawingManager = new google.maps.drawing.DrawingManager({
        drawingMode: null,
        drawingControl: true,
        drawingControlOptions: {
            position: google.maps.ControlPosition.TOP_CENTER,
            drawingModes: [google.maps.drawing.OverlayType.POLYGON]
        },
        polygonOptions: {
            fillColor: "#2ecc71",
            fillOpacity: 0.3,
            strokeColor: "#27ae60",
            strokeWeight: 3,
            clickable: true,
            editable: true,
            zIndex: 1
        }
    });

    drawingManager.setMap(map);

    // Evento disparado quando o desenho termina
    google.maps.event.addListener(drawingManager, 'overlaycomplete', function(event) {
        if (currentPolygon) { currentPolygon.setMap(null); }
        currentPolygon = event.overlay;
        
        const path = currentPolygon.getPath();
        let coords = [];

        for (let i = 0; i < path.getLength(); i++) {
            coords.push({ lat: path.getAt(i).lat(), lng: path.getAt(i).lng() });
        }

        // Armazena as coordenadas para o banco
        document.getElementById('coordenadas_json').value = JSON.stringify(coords);

        // Calcula Hectares reais
        const areaM2 = google.maps.geometry.spherical.computeArea(path);
        const hectares = (areaM2 / 10000).toFixed(2);
        document.getElementById('area_auto').value = hectares;

        // Preenche campos de Lat/Long com o ponto de ancoragem (primeiro clique)
        document.getElementById('lat_manual').value = path.getAt(0).lat().toFixed(6);
        document.getElementById('lng_manual').value = path.getAt(0).lng().toFixed(6);
    });
}

// Função para mover o mapa quando as coordenadas manuais são preenchidas
function voarParaLocal() {
    const lat = parseFloat(document.getElementById('lat_manual').value);
    const lng = parseFloat(document.getElementById('lng_manual').value);

    if (!isNaN(lat) && !isNaN(lng)) {
        const novaPosicao = new google.maps.LatLng(lat, lng);
        map.setCenter(novaPosicao);
        map.setZoom(17);
        
        new google.maps.Marker({
            position: novaPosicao,
            map: map,
            icon: 'https://maps.google.com/mapfiles/ms/icons/green-dot.png'
        });
    }
}

// Escuta mudanças nos inputs de coordenadas
document.querySelectorAll('.coord-input').forEach(input => {
    input.addEventListener('blur', voarParaLocal);
});

google.maps.event.addDomListener(window, 'load', initMap);
</script>

</body>
</html>