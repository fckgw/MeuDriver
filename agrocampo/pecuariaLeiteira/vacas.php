<?php
/**
 * BDSoft Workspace - REBANHO
 * Local: agrocampo/pecuariaLeiteira/vacas.php
 */
session_start();
require_once '../../config.php';
if (!isset($_SESSION['usuario_id'])) { header("Location: ../../login.php"); exit; }
$user_id = $_SESSION['usuario_id'];

// Busca as vacas do usuário
$stmt = $pdo->prepare("SELECT * FROM agro_leite_vacas WHERE usuario_id = ? ORDER BY nome ASC");
$stmt->execute([$user_id]);
$vacas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Rebanho Leiteiro - AgroCampo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f7f4; display: flex; min-height: 100vh; font-family: 'Segoe UI', sans-serif; }
        .main-wrapper { flex: 1; margin-left: 280px; padding: 40px; }
        .card-agro { border: none; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); background: #fff; }
    </style>
</head>
<body>
<?php include 'sidebar_leite.php'; ?>
<div class="main-wrapper">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold">Gestão do Rebanho</h2>
        <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#modalNovaVaca">
            <i class="fas fa-plus me-2"></i>CADASTRAR VACA
        </button>
    </div>

    <div class="card card-agro overflow-hidden">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-uppercase small fw-bold">
                <tr>
                    <th class="ps-4">Brinco</th>
                    <th>Nome</th>
                    <th>Raça</th>
                    <th>Situação</th>
                    <th>Lote</th>
                    <th class="text-center">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($vacas as $v): ?>
                <tr>
                    <td class="ps-4 fw-bold"><?php echo $v['codigo_brinco']; ?></td>
                    <td><?php echo htmlspecialchars($v['nome']); ?></td>
                    <td><?php echo $v['raca']; ?></td>
                    <td><span class="badge bg-<?php echo ($v['status'] == 'Ativa' ? 'success' : 'secondary'); ?>"><?php echo $v['status']; ?></span></td>
                    <td><?php echo $v['lote']; ?></td>
                    <td class="text-center">
                        <a href="acoes_leite.php?del_vaca=<?php echo $v['id']; ?>" class="btn btn-sm text-danger" onclick="return confirm('Excluir animal?')"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL CADASTRAR -->
<div class="modal fade" id="modalNovaVaca" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form action="acoes_leite.php" method="POST" class="modal-content border-0 shadow-lg" style="border-radius:20px;">
            <input type="hidden" name="acao" value="cadastrar_vaca">
            <div class="modal-header bg-dark text-white p-4">
                <h5 class="fw-bold mb-0">Nova Vaca Leiteira</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row">
                    <div class="col-md-4 mb-3"><label class="small fw-bold">BRINCO</label><input type="text" name="codigo_brinco" class="form-control" required></div>
                    <div class="col-md-8 mb-3"><label class="small fw-bold">NOME</label><input type="text" name="nome" class="form-control" required></div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3"><label class="small fw-bold">RAÇA</label><input type="text" name="raca" class="form-control"></div>
                    <div class="col-md-4 mb-3"><label class="small fw-bold">NASCIMENTO</label><input type="date" name="data_nascimento" class="form-control"></div>
                    <div class="col-md-4 mb-3"><label class="small fw-bold">SITUAÇÃO</label>
                        <select name="status" class="form-select">
                            <option value="Ativa">Ativa</option>
                            <option value="Seca">Seca</option>
                            <option value="Prenha">Prenha</option>
                            <option value="Em Tratamento">Em Tratamento</option>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="small fw-bold">PESO (KG)</label><input type="number" step="0.01" name="peso" class="form-control"></div>
                    <div class="col-md-6 mb-3"><label class="small fw-bold">LOTE</label><input type="text" name="lote" class="form-control"></div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="submit" class="btn btn-primary w-100 rounded-pill py-2 fw-bold">SALVAR ANIMAL</button>
            </div>
        </form>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>