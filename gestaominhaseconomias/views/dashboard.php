<?php
/**
 * Dashboard Completo - Versão BI + Alertas + Gestão de Contas
 */
$hoje_sql = date('Y-m-d');

// 1. ALERTAS
$stmt_atraso = $pdo->prepare("SELECT COUNT(*) as total, SUM(valor) as v_total FROM minhaseconomias_movimentacoes WHERE usuario_id = ? AND status = 'Atrasado' AND tipo = 'Despesa'");
$stmt_atraso->execute([$usuario_id]);
$atrasados = $stmt_atraso->fetch();

$stmt_hoje = $pdo->prepare("SELECT COUNT(*) FROM minhaseconomias_movimentacoes WHERE usuario_id = ? AND data_vencimento = ? AND status != 'Pago'");
$stmt_hoje->execute([$usuario_id, $hoje_sql]);
$vencendo_hoje = $stmt_hoje->fetchColumn();

// 2. BUSCA DE CONTAS (SIDEBAR)
$stmt_c = $pdo->prepare("SELECT c.*, 
    (SELECT COUNT(*) FROM minhaseconomias_movimentacoes WHERE conta_id = c.id AND status IN ('Futuro', 'Atrasado')) as pendentes,
    (c.saldo_inicial + IFNULL((SELECT SUM(valor) FROM minhaseconomias_movimentacoes WHERE conta_id = c.id AND tipo = 'Receita' AND status = 'Pago'), 0) - IFNULL((SELECT SUM(valor) FROM minhaseconomias_movimentacoes WHERE conta_id = c.id AND tipo = 'Despesa' AND status = 'Pago'), 0)) as saldo_real 
    FROM minhaseconomias_contas c WHERE c.usuario_id = ? ORDER BY c.status DESC, c.nome ASC");
$stmt_c->execute([$usuario_id]);
$contas = $stmt_c->fetchAll(PDO::FETCH_ASSOC);

$total_saldo_hoje = 0;
foreach($contas as $c) { if($c['status'] == 1) $total_saldo_hoje += $c['saldo_real']; }

// 3. CÁLCULOS DO PERÍODO
$where = " AND data_vencimento BETWEEN '$data_de' AND '$data_ate'";
if($f_banco) $where .= " AND conta_id = " . (int)$f_banco;

$res_pago = $pdo->query("SELECT SUM(CASE WHEN tipo='Receita' THEN valor ELSE 0 END) as rec, SUM(CASE WHEN tipo='Despesa' THEN valor ELSE 0 END) as desp FROM minhaseconomias_movimentacoes WHERE usuario_id=$usuario_id AND status='Pago' $where")->fetch();
$res_proj = $pdo->query("SELECT SUM(CASE WHEN tipo='Receita' AND status='Futuro' THEN valor ELSE 0 END) as rec_f, SUM(CASE WHEN tipo='Despesa' AND status='Futuro' THEN valor ELSE 0 END) as desp_f, SUM(CASE WHEN status='Atrasado' THEN valor ELSE 0 END) as desp_a FROM minhaseconomias_movimentacoes WHERE usuario_id=$usuario_id $where")->fetch();

$previsao = ($total_saldo_hoje + ($res_proj['rec_f'] ?? 0)) - (($res_proj['desp_f'] ?? 0) + ($res_proj['desp_a'] ?? 0));

// 4. DADOS GRÁFICO PIZZA
$dados_pizza = $pdo->query("SELECT c.nome, SUM(m.valor) as total FROM minhaseconomias_movimentacoes m JOIN minhaseconomias_categorias c ON m.categoria_id = c.id WHERE m.usuario_id = $usuario_id AND m.tipo = 'Despesa' AND m.data_vencimento BETWEEN '$data_de' AND '$data_ate' GROUP BY c.id ORDER BY total DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    .card-finance { border-radius: 20px; border: none; }
    .chart-container { position: relative; height: 260px; width: 100%; }
    .card-indicador { transition: transform 0.2s; cursor: pointer; text-decoration: none !important; }
    .card-indicador:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.05) !important; }
    .pulse-atrasado { animation: pulse-red 2s infinite; }
    @keyframes pulse-red { 0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.4); } 70% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); } 100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); } }
</style>

<!-- BANNER FIXO ATRASO -->
<?php if($atrasados['total'] > 0): ?>
<div class="alert alert-danger pulse-atrasado shadow-sm d-flex justify-content-between align-items-center mb-4" style="border-radius: 15px;">
    <div class="fw-bold"><i class="fas fa-exclamation-triangle me-2"></i> VOCÊ TEM <?= $atrasados['total'] ?> CONTA(S) EM ATRASO (R$ <?= number_format($atrasados['v_total'], 2, ',', '.') ?>)</div>
    <a href="index.php?p=transacoes&f_status=Atrasado" class="btn btn-danger btn-sm rounded-pill fw-bold">RESOLVER AGORA</a>
</div>
<?php endif; ?>

<div class="row g-4">
    <!-- SIDEBAR: GESTÃO DE CONTAS -->
    <div class="col-lg-3">
        <div class="card card-finance p-4 bg-white shadow-sm mb-4 text-center border-0">
            <small class="fw-bold text-muted small">SALDO TOTAL HOJE</small>
            <h3 class="fw-bold text-primary">R$ <?= number_format($total_saldo_hoje, 2, ',', '.') ?></h3>
            <hr>
            <small class="fw-bold text-muted small">PREVISÃO FINAL DO MÊS</small>
            <h4 class="fw-bold <?= $previsao >= 0 ? 'text-success' : 'text-danger' ?>">R$ <?= number_format($previsao, 2, ',', '.') ?></h4>
        </div>

        <div class="card p-3 bg-white shadow-sm border-0" style="border-radius: 20px;">
            <div class="d-flex justify-content-between mb-3 px-2">
                <h6 class="fw-bold m-0">Minhas Contas</h6>
                <button class="btn btn-primary btn-sm rounded-circle shadow" onclick="novaConta()"><i class="fas fa-plus"></i></button>
            </div>
            <div class="account-scroll" style="max-height: 400px; overflow-y: auto;">
                <?php foreach($contas as $c): ?>
                <div class="d-flex justify-content-between align-items-center p-2 border-bottom <?= $c['status'] == 0 ? 'opacity-50' : '' ?>">
                    <div class="small">
                        <span class="fw-bold d-block"><?= $c['nome'] ?></span>
                        <span class="text-muted" style="font-size: 10px;">R$ <?= number_format($c['saldo_real'], 2, ',', '.') ?></span>
                    </div>
                    <div class="d-flex gap-1">
                        <button class="btn btn-sm text-primary p-1" onclick='abrirEditarConta(<?= json_encode($c) ?>)'><i class="fas fa-edit"></i></button>
                        <button class="btn btn-sm text-danger p-1" onclick='solicitarExclusaoConta(<?= json_encode($c) ?>)'><i class="fas fa-trash"></i></button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ÁREA PRINCIPAL -->
    <div class="col-lg-9">
        <!-- BARRA DE FILTROS AVANÇADA -->
        <div class="card p-4 mb-4 border-0 shadow-sm bg-white" style="border-radius: 20px;">
            <form method="GET" class="row g-3 align-items-end">
                <input type="hidden" name="p" value="dashboard">
                <div class="col-md-3"><label class="small fw-bold text-muted text-uppercase">Data Início</label><input type="date" name="data_de" class="form-control rounded-pill" value="<?= $data_de ?>"></div>
                <div class="col-md-3"><label class="small fw-bold text-muted text-uppercase">Data Fim</label><input type="date" name="data_ate" class="form-control rounded-pill" value="<?= $data_ate ?>"></div>
                <div class="col-md-3"><label class="small fw-bold text-muted text-uppercase">Filtrar Conta</label>
                    <select name="f_banco" class="form-select rounded-pill">
                        <option value="">Todas Ativas</option>
                        <?php foreach($contas as $cb): if($cb['status']==1): ?><option value="<?= $cb['id'] ?>" <?= $f_banco==$cb['id']?'selected':'' ?>><?= $cb['nome'] ?></option><?php endif; endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3"><button type="submit" class="btn btn-primary w-100 rounded-pill fw-bold shadow">ATUALIZAR PAINEL</button></div>
            </form>
        </div>

        <!-- INDICADORES CLICÁVEIS -->
        <div class="row g-3 mb-4">
            <div class="col-md-3"><a href="index.php?p=transacoes&f_tipo=Receita&f_status=Pago" class="card p-3 border-0 shadow-sm text-center card-indicador"><small class="fw-bold text-muted small">RECEBIDO</small><span class="text-success fw-bold">R$ <?= number_format($res_pago['rec'], 2, ',', '.') ?></span></a></div>
            <div class="col-md-3"><a href="index.php?p=transacoes&f_tipo=Despesa&f_status=Pago" class="card p-3 border-0 shadow-sm text-center card-indicador"><small class="fw-bold text-muted small">PAGO</small><span class="text-danger fw-bold">R$ <?= number_format($res_pago['desp'], 2, ',', '.') ?></span></a></div>
            <div class="col-md-3"><a href="index.php?p=transacoes&f_status=Futuro" class="card p-3 border-0 shadow-sm text-center card-indicador"><small class="fw-bold text-muted small">PENDENTE</small><span class="text-warning fw-bold">R$ <?= number_format($res_proj['desp_f'], 2, ',', '.') ?></span></a></div>
            <div class="col-md-3"><a href="index.php?p=transacoes&f_status=Atrasado" class="card p-3 border-0 shadow-sm text-center card-indicador"><small class="fw-bold text-muted small">EM ATRASO</small><span class="text-danger fw-bold">R$ <?= number_format($res_proj['desp_a'], 2, ',', '.') ?></span></a></div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-6"><div class="card p-4 border-0 shadow-sm h-100"><h6 class="fw-bold text-muted small">BALANÇO (CLICÁVEL)</h6><div class="chart-container"><canvas id="chartBal"></canvas></div></div></div>
            <div class="col-md-6"><div class="card p-4 border-0 shadow-sm h-100"><h6 class="fw-bold text-muted small">FLUXO DE SAÍDAS</h6><div class="chart-container"><canvas id="chartProj"></canvas></div></div></div>
        </div>

        <div class="row"><div class="col-12"><div class="card p-4 border-0 shadow-sm"><h6 class="fw-bold text-muted small text-center">DESPESAS POR CATEGORIA (%)</h6><div class="chart-container" style="height:350px;"><canvas id="chartPizza"></canvas></div></div></div></div>
    </div>
</div>

<!-- POP-UP AUTOMÁTICO HOJE -->
<?php if($vencendo_hoje > 0): ?>
<div class="modal fade" id="modalHoje" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 shadow-lg rounded-4"><div class="modal-header bg-primary text-white border-0"><h5><i class="fas fa-bell me-2"></i>Contas do Dia</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body text-center p-5"><h4>Atenção!</h4><p>Você tem <b><?= $vencendo_hoje ?></b> conta(s) para pagar hoje.</p><div class="d-grid mt-4"><a href="index.php?p=transacoes&f_status=Futuro&data_inicio=<?= $hoje_sql ?>&data_fim=<?= $hoje_sql ?>" class="btn btn-primary rounded-pill fw-bold">VER CONTAS DE HOJE</a></div></div><div class="modal-footer border-0"><button class="btn btn-light" data-bs-dismiss="modal">Fechar</button></div></div></div></div>
<script>document.addEventListener("DOMContentLoaded", function() { new bootstrap.Modal(document.getElementById('modalHoje')).show(); });</script>
<?php endif; ?>

<!-- MODAIS DE GESTÃO DE CONTA -->
<div class="modal fade" id="modalConta" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="POST" class="modal-content border-0 shadow-lg rounded-4"><div class="modal-header"><h5>Gerenciar Conta</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body p-4"><input type="hidden" name="id_conta" id="id_c"><div class="mb-3"><label class="small fw-bold">NOME</label><input type="text" name="nome" id="nome_c" class="form-control" required></div><div class="row g-3"><div class="col-6"><label class="small fw-bold">TIPO</label><select name="tipo" id="tipo_c" class="form-select"><option value="Banco">Banco</option><option value="Carteira">Carteira</option><option value="Cartao">Cartão</option></select></div><div class="col-6"><label class="small fw-bold">SALDO INICIAL</label><input type="text" name="valor_inicial" id="val_c" class="form-control"></div></div><div class="form-check form-switch mt-3"><input class="form-check-input" type="checkbox" name="status" id="st_c" value="1" checked><label class="form-check-label small fw-bold">Conta Ativa (Aparece em Lançamentos)</label></div></div><div class="modal-footer border-0"><button type="submit" name="btn_salvar_conta" class="btn btn-primary w-100 rounded-pill py-2 shadow fw-bold">SALVAR</button></div></form></div></div>

<div class="modal fade" id="modalExcluirConta" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="POST" class="modal-content border-0 shadow-lg rounded-4"><div class="modal-header bg-danger text-white border-0"><h5>Excluir Conta</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body p-4"><input type="hidden" name="id_conta_excluir" id="id_ex_c"><p id="msg_ex" class="fw-bold"></p><div id="div_transf" style="display:none;" class="alert alert-warning">Esta conta tem pendências. Transferir para:<select name="id_conta_destino" class="form-select mt-2"><?php foreach($contas as $cd): ?><option value="<?= $cd['id'] ?>"><?= $cd['nome'] ?></option><?php endforeach; ?></select></div><p class="text-danger small mt-2">Isso apagará permanentemente o histórico desta conta.</p></div><div class="modal-footer border-0"><button type="submit" name="btn_excluir_conta_total" class="btn btn-danger w-100 rounded-pill shadow fw-bold">CONFIRMAR EXCLUSÃO</button></div></form></div></div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const chartOpt = { 
        responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } },
        onClick: (e, els, chart) => {
            if (els.length > 0) {
                const idx = els[0].index;
                let url = "index.php?p=transacoes";
                if (chart.canvas.id === 'chartBal') url += (idx === 0) ? "&f_tipo=Receita&f_status=Pago" : "&f_tipo=Despesa&f_status=Pago";
                else url += (idx === 0) ? "&f_status=Pago" : (idx === 1 ? "&f_status=Futuro" : "&f_status=Atrasado");
                window.location.href = url;
            }
        }
    };
    new Chart(document.getElementById('chartBal'), { type: 'bar', data: { labels: ['Recebido', 'Pago'], datasets: [{ data: [<?= (float)$res_pago['rec'] ?>, <?= (float)$res_pago['desp'] ?>], backgroundColor: ['#1cc88a', '#e74a3b'], borderRadius: 8 }] }, options: chartOpt });
    new Chart(document.getElementById('chartProj'), { type: 'bar', data: { labels: ['Pago', 'Pendente', 'Atrasado'], datasets: [{ data: [<?= (float)$res_pago['desp'] ?>, <?= (float)$res_proj['desp_f'] ?>, <?= (float)$res_proj['desp_a'] ?>], backgroundColor: ['#4e73df', '#f6c23e', '#dc3545'], borderRadius: 8 }] }, options: chartOpt });
    new Chart(document.getElementById('chartPizza'), { type: 'doughnut', data: { labels: [<?php foreach($dados_pizza as $d) echo "'".$d['nome']."',"; ?>], datasets: [{ data: [<?php foreach($dados_pizza as $d) echo $d['total'].","; ?>], backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#858796'] }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right', display: true } } } });

    function novaConta() { document.getElementById('id_c').value = ''; document.getElementById('nome_c').value = ''; document.getElementById('val_c').value = '0,00'; document.getElementById('st_c').checked = true; new bootstrap.Modal(document.getElementById('modalConta')).show(); }
    function abrirEditarConta(d) { document.getElementById('id_c').value = d.id; document.getElementById('nome_c').value = d.nome; document.getElementById('tipo_c').value = d.tipo; document.getElementById('val_c').value = d.saldo_inicial.replace('.',','); document.getElementById('st_c').checked = (d.status == 1); new bootstrap.Modal(document.getElementById('modalConta')).show(); }
    function solicitarExclusaoConta(d) { document.getElementById('id_ex_c').value = d.id; document.getElementById('msg_ex').innerText = 'Excluir ' + d.nome + '?'; document.getElementById('div_transf').style.display = d.pendentes > 0 ? 'block' : 'none'; new bootstrap.Modal(document.getElementById('modalExcluirConta')).show(); }
</script>