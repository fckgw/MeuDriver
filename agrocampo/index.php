<?php
session_start();
require_once '../config.php';
if (!isset($_SESSION['usuario_id'])) { header("Location: ../login.php"); exit; }
$user_id = $_SESSION['usuario_id'];

// Estatísticas Rápidas
$total_t = $pdo->prepare("SELECT COUNT(*) FROM agro_talhoes WHERE usuario_id = ?");
$total_t->execute([$user_id]);
$qtd_talhoes = $total_t->fetchColumn();

$stmt_talhoes = $pdo->prepare("SELECT * FROM agro_talhoes WHERE usuario_id = ? ORDER BY nome ASC");
$stmt_talhoes->execute([$user_id]);
$talhoes = $stmt_talhoes->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8"><title>AgroCampo - Painel Geral</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'sidebar_agro.php'; ?>
    <div class="main-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-5">
            <h2 class="fw-bold">Monitoramento de Campo</h2>
            <button class="btn btn-success rounded-pill px-4 fw-bold" data-bs-toggle="modal" data-bs-target="#modalNovoTalhao">+ NOVO TALHÃO</button>
        </div>

        <div class="row g-4 mb-5">
            <div class="col-md-4">
                <div class="card p-4 border-0 shadow-sm rounded-4">
                    <small class="text-muted fw-bold">ÁREAS MAPEADAS</small>
                    <h2 class="fw-bold text-success mt-2"><?php echo $qtd_talhoes; ?> Talhões</h2>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <?php foreach($talhoes as $t): ?>
            <div class="col-md-4">
                <div class="card p-4 border-0 shadow-sm rounded-4 border-bottom border-success border-5">
                    <h5 class="fw-bold"><?php echo htmlspecialchars($t['nome']); ?></h5>
                    <p class="text-muted small">Cultura: <?php echo $t['cultura_atual']; ?> | <?php echo $t['area_ha']; ?> ha</p>
                    <a href="acoes.php?del_talhao=<?php echo $t['id']; ?>" class="text-danger small" onclick="return confirm('Excluir?')"><i class="fas fa-trash"></i> Excluir</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- MODAL NOVO TALHÃO -->
    <div class="modal fade" id="modalNovoTalhao" tabindex="-1"><div class="modal-dialog"><form action="acoes.php" method="POST" class="modal-content"><div class="modal-body p-4">
        <input type="hidden" name="acao" value="novo_talhao">
        <label class="small fw-bold">NOME DA ÁREA</label><input type="text" name="nome" class="form-control mb-3" required>
        <div class="row">
            <div class="col-6"><label class="small fw-bold">HECTARES</label><input type="number" step="0.01" name="area_ha" class="form-control" required></div>
            <div class="col-6"><label class="small fw-bold">CULTURA</label><input type="text" name="cultura" class="form-control"></div>
        </div>
        <button class="btn btn-success w-100 rounded-pill py-2 mt-4 fw-bold">CADASTRAR</button>
    </div></form></div></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>