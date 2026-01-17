<?php
/**
 * BDSoft Workspace - AGRO CAMPO
 * Local: agrocampo/index.php
 */

// 1. ATIVAR ERROS (Para diagnosticar o Erro 500)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// 2. CAMINHO DO CONFIG (Ajustado para subir um nível)
if (!file_exists('../config.php')) {
    die("Erro Crítico: Arquivo de configuração não encontrado no nível superior.");
}
require_once '../config.php';

// 3. Verificação de Segurança
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['usuario_id'];

// 4. Buscar Dados
try {
    $stmt = $pdo->prepare("SELECT * FROM agro_talhoes WHERE usuario_id = ? ORDER BY nome ASC");
    $stmt->execute([$user_id]);
    $talhoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro no Banco de Dados: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgroCampo - BDSoft Workspace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --agro-green: #2d5a27; --agro-light: #f0f4f0; }
        body { background-color: var(--agro-light); font-family: 'Segoe UI', sans-serif; }
        .nav-agro { background: var(--agro-green); color: white; padding: 15px 30px; }
        .card-talhao { 
            background: white; border: none; border-radius: 20px; 
            transition: 0.3s; border-bottom: 6px solid var(--agro-green);
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }
        .card-talhao:hover { transform: translateY(-5px); box-shadow: 0 12px 30px rgba(0,0,0,0.1); }
        .icon-overlay { font-size: 3rem; color: var(--agro-green); opacity: 0.1; position: absolute; right: 20px; top: 20px; }
    </style>
</head>
<body>

<nav class="nav-agro d-flex justify-content-between align-items-center shadow-sm">
    <div class="d-flex align-items-center">
        <a href="../index.php" class="btn btn-sm btn-outline-light rounded-circle me-3" title="Voltar ao Workspace">
            <i class="fas fa-th"></i>
        </a>
        <h4 class="fw-bold mb-0"><i class="fas fa-seedling me-2"></i>AgroCampo</h4>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-success btn-sm rounded-pill px-4 fw-bold shadow" data-bs-toggle="modal" data-bs-target="#modalNovoTalhao">
            <i class="fas fa-plus me-1"></i> NOVO TALHÃO
        </button>
        <a href="../logout.php" class="btn btn-link text-white text-decoration-none small">Sair</a>
    </div>
</nav>

<div class="container mt-5">
    <div class="row mb-4">
        <div class="col-md-12">
            <h2 class="fw-bold text-dark">Monitoramento de Campo</h2>
            <p class="text-muted">Gestão de talhões e safras para <strong><?php echo $_SESSION['usuario_nome']; ?></strong></p>
        </div>
    </div>

    <div class="row g-4">
        <?php if (empty($talhoes)): ?>
            <div class="col-12 text-center py-5">
                <div class="card p-5 border-0 shadow-sm rounded-4">
                    <i class="fas fa-map-marked-alt fa-4x text-muted mb-3 opacity-25"></i>
                    <h5 class="text-muted">Nenhum talhão registrado.</h5>
                    <p class="small text-muted">Clique em "Novo Talhão" para começar o mapeamento.</p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($talhoes as $t): ?>
            <div class="col-md-4">
                <div class="card card-talhao p-4 position-relative">
                    <i class="fas fa-leaf icon-overlay"></i>
                    <h5 class="fw-bold mb-1 text-dark"><?php echo htmlspecialchars($t['nome']); ?></h5>
                    <div class="text-success small fw-bold mb-4">Cultura: <?php echo htmlspecialchars($t['cultura_atual']); ?></div>
                    
                    <div class="d-flex justify-content-between align-items-end border-top pt-3">
                        <div>
                            <span class="text-muted small">Tamanho:</span><br>
                            <span class="fw-bold h5 mb-0"><?php echo number_format($t['area_ha'], 2, ',', '.'); ?> <small>ha</small></span>
                        </div>
                        <div class="btn-group">
                            <button class="btn btn-sm btn-outline-success rounded-pill px-3">Abrir</button>
                            <a href="acoes.php?del_talhao=<?php echo $t['id']; ?>" class="btn btn-sm btn-link text-danger" onclick="return confirm('Excluir área?')">
                                <i class="fas fa-trash-alt"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- MODAL NOVO TALHÃO -->
<div class="modal fade" id="modalNovoTalhao" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form action="acoes.php" method="POST" class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 bg-success text-white">
                <h5 class="fw-bold mb-0">Novo Talhão / Área</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="acao" value="novo_talhao">
                <div class="mb-3">
                    <label class="small fw-bold mb-1 text-muted">NOME IDENTIFICADOR</label>
                    <input type="text" name="nome" class="form-control form-control-lg" placeholder="Ex: Piquete 04" required>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="small fw-bold mb-1 text-muted">ÁREA (HECTARES)</label>
                        <input type="number" name="area_ha" step="0.01" class="form-control form-control-lg" placeholder="0.00" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="small fw-bold mb-1 text-muted">CULTURA</label>
                        <input type="text" name="cultura" class="form-control form-control-lg" placeholder="Ex: Soja">
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="submit" class="btn btn-success w-100 rounded-pill fw-bold py-3 shadow">CADASTRAR GLEBA</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>