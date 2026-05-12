<?php
/**
 * BDSoft Workspace - CICLO REPRODUTIVO
 */
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (file_exists('../config.php')) { require_once '../config.php'; } 
elseif (file_exists('../../config.php')) { require_once '../../config.php'; }

if (!isset($_SESSION['usuario_id'])) { header("Location: ../../login.php"); exit; }
$user_id = $_SESSION['usuario_id'];

// Busca vacas
$stmt_v = $pdo->prepare("SELECT id, codigo_brinco, nome FROM agro_leite_vacas WHERE usuario_id = ? ORDER BY nome ASC");
$stmt_v->execute([$user_id]);
$vacas = $stmt_v->fetchAll(PDO::FETCH_ASSOC);

// Busca histórico reprodutivo
$stmt_r = $pdo->prepare("SELECT r.*, v.codigo_brinco, v.nome as vaca_nome 
                         FROM agro_leite_reproducao r 
                         INNER JOIN agro_leite_vacas v ON r.vaca_id = v.id 
                         WHERE v.usuario_id = ? 
                         ORDER BY r.data_cio DESC");
$stmt_r->execute([$user_id]);
$reproducoes = $stmt_r->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Reprodução - AgroCampo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f7f4; display: flex; min-height: 100vh; margin: 0; font-family: 'Segoe UI', sans-serif; }
        .main-wrapper { flex: 1; margin-left: 280px; padding: 40px; width: calc(100% - 280px); transition: 0.3s; }
        .card-agro { border: none; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); background: #fff; }
        @media (max-width: 991px) { .main-wrapper { margin-left: 0; width: 100%; padding: 20px; } }
    </style>
</head>
<body>
<?php include 'sidebar_leite.php'; ?>
<div class="main-wrapper">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold">Gestão Reprodutiva</h2>
        <button class="btn btn-danger rounded-pill px-4 fw-bold shadow" data-bs-toggle="modal" data-bs-target="#modalReproducao">
            <i class="fas fa-heart me-2"></i>NOVO REGISTRO
        </button>
    </div>

    <div class="card card-agro overflow-hidden">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small">
                <tr><th>Vaca</th><th>Data Cio</th><th>Tipo</th><th>Status</th><th>Previsão Parto</th><th>Ações</th></tr>
            </thead>
            <tbody>
                <?php if(empty($reproducoes)): ?>
                    <tr><td colspan="6" class="text-center py-4 text-muted">Nenhum registro reprodutivo encontrado.</td></tr>
                <?php else: ?>
                    <?php foreach($reproducoes as $r): ?>
                    <tr>
                        <td><strong><?php echo $r['codigo_brinco']; ?></strong> - <?php echo $r['vaca_nome']; ?></td>
                        <td><?php echo date('d/m/Y', strtotime($r['data_cio'])); ?></td>
                        <td><span class="badge bg-light text-dark border"><?php echo $r['tipo']; ?></span></td>
                        <td>
                            <span class="badge <?php echo ($r['status_gestacao'] == 'Prenha' ? 'bg-success' : 'bg-secondary'); ?>">
                                <?php echo $r['status_gestacao']; ?>
                            </span>
                        </td>
                        <td class="fw-bold text-success">
                            <?php echo $r['previsao_parto'] ? date('d/m/Y', strtotime($r['previsao_parto'])) : '---'; ?>
                        </td>
                        <td><a href="acoes_leite.php?del_repro=<?php echo $r['id']; ?>" class="text-danger"><i class="fas fa-trash"></i></a></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL REPRODUÇÃO -->
<div class="modal fade" id="modalReproducao" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form action="acoes_leite.php" method="POST" class="modal-content border-0 shadow-lg" style="border-radius:20px;">
            <input type="hidden" name="acao" value="registrar_reproducao">
            <div class="modal-header bg-danger text-white border-0 p-4">
                <h5 class="fw-bold mb-0">Registro de Cio / Inseminação</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="small fw-bold">SELECIONE A VACA</label>
                    <select name="vaca_id" class="form-select" required>
                        <option value="">Escolha...</option>
                        <?php foreach($vacas as $v): ?>
                        <option value="<?php echo $v['id']; ?>"><?php echo $v['codigo_brinco'] . " - " . $v['nome']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="small fw-bold">DATA DO CIO</label><input type="date" name="data_cio" class="form-control" value="<?php echo date('Y-m-d'); ?>" required></div>
                    <div class="col-md-6 mb-3">
                        <label class="small fw-bold">INSEMINADA?</label>
                        <select name="inseminada" class="form-select"><option value="Sim">Sim</option><option value="Não">Não</option></select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="small fw-bold">TIPO DE REPRODUÇÃO</label>
                        <select name="tipo" class="form-select"><option value="Inseminação">Inseminação Artificial</option><option value="Monta Natural">Monta Natural</option></select>
                    </div>
                    <div class="col-md-6 mb-3"><label class="small fw-bold">TOURO / SÊMEN UTILIZADO</label><input type="text" name="touro_semen" class="form-control" placeholder="Identificação do macho"></div>
                </div>
                <div class="mb-0">
                    <label class="small fw-bold">DIAGNÓSTICO GESTACIONAL</label>
                    <select name="status_gestacao" class="form-select">
                        <option value="Confirmar">Aguardando Confirmação (Toque/US)</option>
                        <option value="Prenha">Prenha (Confirmada)</option>
                        <option value="Vazia">Vazia / Falhou</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="submit" class="btn btn-danger w-100 rounded-pill py-2 fw-bold">SALVAR REGISTRO</button>
            </div>
        </form>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>