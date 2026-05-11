<?php
/**
 * BDSoft Workspace - PROVISIONAMENTO (VERSÃO COMPLETA COM BI, EDIÇÃO E FLUXO)
 * Local: agrocampo/provisoes.php
 */
session_start();
require_once '../config.php';

if (!isset($_SESSION['usuario_id'])) { 
    header("Location: ../login.php"); 
    exit; 
}

$user_id = $_SESSION['usuario_id'];

// 1. BUSCAR TODAS AS PROVISÕES (CARDS)
$stmt = $pdo->prepare("SELECT * FROM agro_provisoes WHERE usuario_id = ? ORDER BY data_criacao DESC");
$stmt->execute([$user_id]);
$provisoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. BUSCAR DADOS PARA O BI (PROJEÇÃO DE PAGAMENTOS PENDENTES)
$stmt_bi = $pdo->prepare("
    SELECT 
        DATE_FORMAT(data_vencimento, '%Y-%m') as mes_referencia,
        SUM(CASE WHEN agro_provisoes.tipo = 'Entrada' THEN valor_parcela ELSE 0 END) as total_entrada,
        SUM(CASE WHEN agro_provisoes.tipo = 'Saida' THEN valor_parcela ELSE 0 END) as total_saida
    FROM agro_provisoes_parcelas 
    INNER JOIN agro_provisoes ON agro_provisoes.id = agro_provisoes_parcelas.provisao_id
    WHERE agro_provisoes.usuario_id = ? 
      AND agro_provisoes_parcelas.status = 'Pendente'
      AND data_vencimento >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
    GROUP BY mes_referencia
    ORDER BY mes_referencia ASC
    LIMIT 24
");
$stmt_bi->execute([$user_id]);
$dados_bi_brutos = $stmt_bi->fetchAll(PDO::FETCH_ASSOC);
$json_dados_bi = json_encode($dados_bi_brutos);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Provisionamento & BI - AgroCampo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f7f4; display: flex; min-height: 100vh; font-family: 'Segoe UI', Tahoma, sans-serif; margin: 0; }
        .main-wrapper { flex: 1; margin-left: 280px; padding: 40px; width: calc(100% - 280px); transition: 0.3s; }
        .card-prov { border: none; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); background: #fff; height: 100%; transition: transform 0.2s; border-top: 5px solid #ccc; }
        .card-prov.entrada { border-top-color: #198754; }
        .card-prov.saida { border-top-color: #dc3545; }
        .card-prov:hover { transform: translateY(-5px); }
        .table-parcelas { font-size: 12px; margin-top: 15px; display: none; background: #fafafa; border-radius: 10px; padding: 10px; border: 1px solid #eee; }
        .btn-eye, .btn-edit { cursor: pointer; color: #1a73e8; font-size: 1.1rem; transition: 0.2s; }
        .bi-section { background: #ffffff; border-radius: 24px; padding: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.03); }
        .calc-preview { background: #e8f0fe; border: 2px dashed #1a73e8; border-radius: 15px; padding: 15px; margin: 15px 0; }
        @media (max-width: 991px) { .main-wrapper { margin-left: 0; width: 100%; padding: 20px; } }
        .scroll-grid { max-height: 400px; overflow-y: auto; }
    </style>
</head>
<body>

<?php include 'sidebar_agro.php'; ?>

<div class="main-wrapper">
    
    <?php if(isset($_GET['sucesso'])): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-pill mb-4 text-center">
            <i class="fas fa-check-circle me-2"></i> Operação realizada com sucesso!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h2 class="fw-bold text-dark mb-0">Provisionamento Financeiro</h2>
            <p class="text-muted small">Gestão de Fluxo Futuro (Entradas e Saídas)</p>
        </div>
        <button class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalNovoProv">
            <i class="fas fa-plus me-2"></i>NOVA PROVISÃO
        </button>
    </div>

    <div class="row g-4 mb-5">
        <?php foreach($provisoes as $p): 
            $classe_tipo = ($p['tipo'] == 'Entrada') ? 'entrada' : 'saida';
            $stmt_p = $pdo->prepare("SELECT * FROM agro_provisoes_parcelas WHERE provisao_id = ? ORDER BY parcela_numero ASC");
            $stmt_p->execute([$p['id']]);
            $parcelas = $stmt_p->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <div class="col-md-6 col-lg-4">
            <div class="card card-prov <?php echo $classe_tipo; ?> p-4 shadow-sm">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="badge <?php echo $p['tipo'] == 'Entrada' ? 'bg-success' : 'bg-danger'; ?> rounded-pill">
                        <?php echo strtoupper($p['tipo']); ?>
                    </span>
                    <div class="d-flex gap-3">
                        <i class="fas fa-eye btn-eye" onclick="toggleTabela(<?php echo $p['id']; ?>)"></i>
                        <i class="fas fa-edit btn-edit" onclick='abrirModalEditar(<?php echo json_encode($p); ?>)'></i>
                        <a href="acoes.php?acao=excluir_provisao&id=<?php echo $p['id']; ?>" class="text-danger opacity-50" onclick="return confirm('Excluir este acordo?')">
                            <i class="fas fa-trash-alt"></i>
                        </a>
                    </div>
                </div>

                <h5 class="fw-bold text-dark text-truncate mb-1"><?php echo htmlspecialchars($p['nome_provisao']); ?></h5>
                <h3 class="fw-bold <?php echo $p['tipo'] == 'Entrada' ? 'text-success' : 'text-primary'; ?> mb-0">
                    R$ <?php echo number_format($p['valor_total'], 2, ',', '.'); ?>
                </h3>
                <p class="text-muted small mb-3"><?php echo $p['quantidade_parcelas']; ?>x parcelas geradas.</p>

                <div class="table-parcelas" id="tabela_<?php echo $p['id']; ?>">
                    <table class="table table-sm table-borderless mt-2 mb-0">
                        <thead>
                            <tr class="text-muted small" style="border-bottom: 1px solid #eee;">
                                <th>PARC.</th>
                                <th>VENCIMENTO</th>
                                <th class="text-end">STATUS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($parcelas as $pa): ?>
                            <tr>
                                <td>#<?php echo $pa['parcela_numero']; ?></td>
                                <td><?php echo date('d/m/Y', strtotime($pa['data_vencimento'])); ?></td>
                                <td class="text-end">
                                    <span class="badge <?php echo $pa['status'] == 'Pendente' ? 'bg-warning text-dark' : 'bg-success'; ?>" style="font-size: 9px;">
                                        <?php echo $pa['status']; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- SEÇÃO BI -->
    <div class="bi-section mt-5">
        <h4 class="fw-bold text-dark mb-4"><i class="fas fa-chart-line me-2 text-success"></i>Projeção de Fluxo de Caixa (BI)</h4>
        <div class="row g-4">
            <div class="col-lg-8">
                <canvas id="chartProvisoes" style="width: 100%; height: 350px;"></canvas>
            </div>
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                    <div class="bg-dark p-3 text-white fw-bold small">RESUMO POR PERÍODO</div>
                    <div class="scroll-grid">
                        <table class="table table-hover mb-0" id="tabelaResumoBI">
                            <thead class="table-light">
                                <tr style="font-size: 11px;"><th>MÊS</th><th class="text-end">ENTRADAS</th><th class="text-end">SAÍDAS</th></tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL NOVO -->
<div class="modal fade" id="modalNovoProv" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form action="acoes.php" method="POST" class="modal-content border-0 shadow-lg" style="border-radius:25px;">
            <div class="modal-header border-0 bg-primary text-white p-4">
                <h5 class="fw-bold mb-0">Novo Parcelamento</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="acao" value="gerar_provisao">
                
                <div class="mb-3">
                    <label class="small fw-bold text-muted text-uppercase">Descrição do Acordo</label>
                    <input type="text" name="nome" class="form-control" placeholder="Ex: Venda de Soja ou Parcela Trator" required>
                </div>

                <div class="mb-3">
                    <label class="small fw-bold text-muted text-uppercase">Tipo de Lançamento</label>
                    <select name="tipo" class="form-select fw-bold">
                        <option value="Saida">Saída (Contas a Pagar)</option>
                        <option value="Entrada">Entrada (Receitas a Receber)</option>
                    </select>
                </div>

                <div class="row">
                    <div class="col-7 mb-3">
                        <label class="small fw-bold text-muted text-uppercase">Valor Total (R$)</label>
                        <input type="text" id="calc_total" name="valor_bruto_input" class="form-control form-control-lg fw-bold" placeholder="0,00" required>
                    </div>
                    <div class="col-5 mb-3">
                        <label class="small fw-bold text-muted text-uppercase">Parcelas</label>
                        <input type="number" id="calc_qtd" name="parcelas" class="form-control form-control-lg fw-bold" value="1" min="1" required>
                    </div>
                </div>

                <div class="calc-preview text-center">
                    <span class="text-muted small text-uppercase fw-bold">Valor Mensal</span>
                    <h2 class="fw-bold text-dark mb-0" id="display_parcela">R$ 0,00</h2>
                </div>

                <div class="mb-3">
                    <label class="small fw-bold text-muted text-uppercase">Data da 1ª Parcela</label>
                    <input type="date" name="data_inicio" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="submit" class="btn btn-primary w-100 rounded-pill py-3 fw-bold shadow">GERAR CRONOGRAMA</button>
            </div>
        </form>
    </div>
</div>

<!-- SCRIPTS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
$(document).ready(function(){
    $('#calc_total').mask('#.##0,00', {reverse: true});
    $('#calc_total, #calc_qtd').on('keyup change', function() {
        let total = parseFloat($('#calc_total').val().replace(/\./g, '').replace(',', '.')) || 0;
        let qtd = parseInt($('#calc_qtd').val()) || 1;
        $('#display_parcela').text((total / qtd).toLocaleString('pt-br', {style: 'currency', currency: 'BRL'}));
    });
});

function toggleTabela(id) { $('#tabela_' + id).slideToggle(); }

/** BI LOGIC **/
const dadosBI = <?php echo $json_dados_bi; ?>;
const ctx = document.getElementById('chartProvisoes').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: dadosBI.map(d => d.mes_referencia),
        datasets: [
            { label: 'Entradas', data: dadosBI.map(d => d.total_entrada), backgroundColor: '#198754' },
            { label: 'Saídas', data: dadosBI.map(d => d.total_saida), backgroundColor: '#dc3545' }
        ]
    },
    options: { responsive: true, maintainAspectRatio: false }
});

const tbody = document.querySelector('#tabelaResumoBI tbody');
dadosBI.forEach(d => {
    tbody.innerHTML += `<tr>
        <td>${d.mes_referencia}</td>
        <td class="text-end text-success">R$ ${parseFloat(d.total_entrada).toLocaleString('pt-BR')}</td>
        <td class="text-end text-danger">R$ ${parseFloat(d.total_saida).toLocaleString('pt-BR')}</td>
    </tr>`;
});
</script>
</body>
</html>