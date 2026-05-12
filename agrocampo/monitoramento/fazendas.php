<?php
/**
 * BDSoft Workspace - CADASTRO DE FAZENDAS
 * Localização: agrocampo/monitoramento/fazendas.php
 */
session_start();
require_once '../../config.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../../login.php");
    exit;
}

$user_id = $_SESSION['usuario_id'];

// Busca as fazendas do banco
$stmt = $pdo->prepare("SELECT * FROM agro_fazendas WHERE usuario_id = ? ORDER BY nome ASC");
$stmt->execute([$user_id]);
$fazendas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Fazendas - Monitoramento AgroCampo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f7f4; display: flex; min-height: 100vh; margin: 0; }
        .main-wrapper { flex: 1; margin-left: 280px; padding: 40px; width: calc(100% - 280px); transition: 0.3s; }
        .card-agro { border: none; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); background: #fff; }
        @media (max-width: 991px) { .main-wrapper { margin-left: 0; width: 100%; padding: 20px; } }
    </style>
</head>
<body>

<?php include 'sidebar_monitoramento.php'; ?>

<div class="main-wrapper">
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h2 class="fw-bold text-dark mb-0">Gestão de Propriedades</h2>
            <p class="text-muted small">Cadastre e gerencie os dados principais das suas fazendas.</p>
        </div>
        <button class="btn btn-success rounded-pill px-4 fw-bold shadow" data-bs-toggle="modal" data-bs-target="#modalNovaFazenda">
            <i class="fas fa-plus me-2"></i>NOVA FAZENDA
        </button>
    </div>

    <div class="row g-4">
        <?php if (empty($fazendas)): ?>
            <div class="col-12 text-center py-5 opacity-50">
                <i class="fas fa-republican fa-4x mb-3"></i>
                <h5>Nenhuma fazenda cadastrada.</h5>
            </div>
        <?php else: ?>
            <?php foreach($fazendas as $f): ?>
            <div class="col-md-4">
                <div class="card card-agro p-4 shadow-sm h-100">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="badge bg-success rounded-pill px-3 py-2">ATIVO</span>
                        <a href="acoes_monitoramento.php?excluir_fazenda=<?php echo $f['id']; ?>" class="text-danger" onclick="return confirm('Excluir fazenda e todos os talhões ligados a ela?')">
                            <i class="fas fa-trash"></i>
                        </a>
                    </div>
                    <h5 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($f['nome']); ?></h5>
                    <p class="text-muted small mb-3"><i class="fas fa-user me-1"></i> Prop: <?php echo htmlspecialchars($f['proprietario']); ?></p>
                    
                    <div class="row text-center bg-light rounded-3 p-2 mb-3">
                        <div class="col-6 border-end">
                            <small class="text-muted d-block">Área Total</small>
                            <span class="fw-bold"><?php echo number_format($f['area_total'], 2, ',', '.'); ?> ha</span>
                        </div>
                        <div class="col-6">
                            <small class="text-muted d-block">Cidade</small>
                            <span class="fw-bold"><?php echo $f['cidade']; ?>/<?php echo $f['estado']; ?></span>
                        </div>
                    </div>

                    <a href="talhoes_cadastro.php?fazenda_id=<?php echo $f['id']; ?>" class="btn btn-outline-success w-100 rounded-pill fw-bold">
                        <i class="fas fa-map me-2"></i>CONFIGURAR MAPA
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- MODAL: CADASTRAR FAZENDA (CAMPOS IGUAL BANCO DE DADOS) -->
<div class="modal fade" id="modalNovaFazenda" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form action="acoes_monitoramento.php" method="POST" class="modal-content border-0 shadow-lg" style="border-radius:25px;">
            <div class="modal-header border-0 bg-dark text-white p-4">
                <h5 class="fw-bold mb-0">Cadastro de Nova Propriedade</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="acao" value="cadastrar_fazenda">
                
                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label class="small fw-bold">NOME DA FAZENDA</label>
                        <input type="text" name="nome" class="form-control" placeholder="Ex: Fazenda Santa Maria" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="small fw-bold">ÁREA TOTAL (HA)</label>
                        <input type="number" step="0.01" name="area_total" class="form-control" placeholder="0.00" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="small fw-bold">NOME DO PROPRIETÁRIO</label>
                    <input type="text" name="proprietario" class="form-control" placeholder="Nome completo" required>
                </div>

                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label class="small fw-bold">CIDADE</label>
                        <input type="text" name="cidade" class="form-control" placeholder="Ex: São José dos Campos" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="small fw-bold">ESTADO (UF)</label>
                        <input type="text" name="estado" class="form-control" placeholder="Ex: SP" maxlength="2" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="small fw-bold">LATITUDE (Sede/Entrada)</label>
                        <input type="text" name="latitude" class="form-control" placeholder="-22.9035">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="small fw-bold">LONGITUDE (Sede/Entrada)</label>
                        <input type="text" name="longitude" class="form-control" placeholder="-47.0626">
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="submit" class="btn btn-dark w-100 rounded-pill py-3 fw-bold">SALVAR NO BANCO DE DADOS</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>