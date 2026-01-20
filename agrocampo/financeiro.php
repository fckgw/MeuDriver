<?php
/**
 * BDSoft Workspace - FINANCEIRO AGRO
 */
session_start();
require_once '../config.php';

if (!isset($_SESSION['usuario_id'])) { header("Location: ../login.php"); exit; }
$user_id = $_SESSION['usuario_id'];
$hoje = date('Y-m-d');

// Estatísticas corrigidas
$stmt_stats = $pdo->prepare("SELECT 
    SUM(CASE WHEN status = 'Pendente' AND data_vencimento < ? THEN valor ELSE 0 END) as vencido,
    SUM(CASE WHEN status = 'Pago' AND MONTH(data_pagamento) = MONTH(CURDATE()) THEN valor ELSE 0 END) as pago_mes
    FROM agro_financeiro WHERE usuario_id = ?");
$stmt_stats->execute([$hoje, $user_id]);
$stats = $stmt_stats->fetch();

$stmt_lista = $pdo->prepare("SELECT * FROM agro_financeiro WHERE usuario_id = ? ORDER BY status ASC, data_vencimento ASC");
$stmt_lista->execute([$user_id]);
$contas = $stmt_lista->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financeiro - AgroCampo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<?php include 'sidebar_agro.php'; ?>

<div class="main-wrapper">
    <!-- Botão de Menu Mobile -->
    <button class="btn btn-success d-lg-none mb-3" onclick="document.getElementById('sidebar').classList.toggle('active')">
        <i class="fas fa-bars"></i> Menu
    </button>

    <div class="d-flex justify-content-between align-items-center mb-5">
        <h2 class="fw-bold">Contas Pagar/Receber</h2>
        <div class="d-flex gap-2">
            <button class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalXML">LER XML COMEVAP</button>
            <button class="btn btn-success rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalNovo">+ NOVO</button>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card p-4 card-agro border-start border-danger border-5">
                <small class="fw-bold text-muted text-uppercase">Vencido</small>
                <h3 class="text-danger fw-bold">R$ <?php echo number_format($stats['vencido']??0,2,',','.'); ?></h3>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card p-4 card-agro border-start border-success border-5">
                <small class="fw-bold text-muted text-uppercase">Pago no Mês</small>
                <h3 class="text-success fw-bold">R$ <?php echo number_format($stats['pago_mes']??0,2,',','.'); ?></h3>
            </div>
        </div>
    </div>

    <div class="card card-agro overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Vencimento</th>
                        <th>Fornecedor / Descrição</th>
                        <th>Valor</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($contas as $c): ?>
                    <tr>
                        <td class="ps-4 small"><?php echo date('d/m/Y', strtotime($c['data_vencimento'])); ?></td>
                        <td>
                            <div class="fw-bold"><?php echo htmlspecialchars($c['fornecedor']); ?></div>
                            <small class="text-muted"><?php echo htmlspecialchars($c['descricao']); ?></small>
                        </td>
                        <td class="fw-bold text-dark">R$ <?php echo number_format($c['valor'], 2, ',', '.'); ?></td>
                        <td class="text-center">
                            <button onclick="baixar(<?php echo $c['id']; ?>, '<?php echo $c['status']; ?>')" 
                                    class="btn btn-sm <?php echo $c['status']=='Pago'?'btn-success':'btn-warning'; ?> rounded-pill px-3 fw-bold">
                                <?php echo $c['status']; ?>
                            </button>
                        </td>
                        <td class="text-center">
                            <a href="acoes.php?del_fin=<?php echo $c['id']; ?>" class="text-danger opacity-50" onclick="return confirm('Excluir?')"><i class="fas fa-trash-alt"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Novo Lançamento -->
<div class="modal fade" id="modalNovo" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form action="acoes.php" method="POST" class="modal-content border-0 shadow-lg"><div class="modal-header border-0 bg-success text-white"><h5>Novo Lançamento</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body p-4"><input type="hidden" name="acao" value="novo_fin"><div class="row mb-3"><div class="col-6"><label class="small fw-bold">TIPO</label><select name="tipo" class="form-select"><option value="Saida">Saída</option><option value="Entrada">Entrada</option></select></div><div class="col-6"><label class="small fw-bold">VALOR</label><input type="number" step="0.01" name="valor" class="form-control" required></div></div><div class="mb-3"><label class="small fw-bold">FORNECEDOR</label><input type="text" name="fornecedor" class="form-control" required></div><div class="mb-3"><label class="small fw-bold">DESCRIÇÃO</label><input type="text" name="descricao" class="form-control" required></div><div class="row"><div class="col-6"><label class="small fw-bold">VENCIMENTO</label><input type="date" name="data_vencimento" class="form-control" value="<?php echo $hoje; ?>" required></div><div class="col-6"><label class="small fw-bold">MÉTODO</label><select name="metodo_pagamento" class="form-select"><option>Boleto</option><option>PIX</option><option>Consignado</option></select></div></div></div><div class="modal-footer border-0"><button class="btn btn-success w-100 rounded-pill py-2 fw-bold shadow">SALVAR</button></div></form></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function baixar(id, status) {
    if(status === 'Pago') { window.location.href = 'acoes.php?acao=estornar_pagamento&id=' + id; }
    else { const d = prompt("Confirme a Data do Pagamento:", "<?php echo date('d/m/Y'); ?>"); if(d) window.location.href = `acoes.php?acao=confirmar_pagamento&id=${id}&data=${d}`; }
}
</script>
</body>
</html>