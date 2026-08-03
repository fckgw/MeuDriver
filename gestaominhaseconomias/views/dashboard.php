<?php
/**
 * BDSoft Workspace - Minhas Economias
 * View: Dashboard Principal - Versão Restaurada e Interativa
 */
$usuario_id = $_SESSION['usuario_id'];
$hoje_sql = date('Y-m-d');
$meses_nomes = ["01"=>"Janeiro","02"=>"Fevereiro","03"=>"Março","04"=>"Abril","05"=>"Maio","06"=>"Junho","07"=>"Julho","08"=>"Agosto","09"=>"Setembro","10"=>"Outubro","11"=>"Novembro","12"=>"Dezembro"];

// --- 0. CAPTURA DE FILTROS ---
$mes_filtro = $_GET['mes'] ?? date('m');
$ano_filtro = $_GET['ano'] ?? date('Y');
$data_de    = $_GET['data_de'] ?? date('Y-m-01', strtotime("$ano_filtro-$mes_filtro-01"));
$data_ate   = $_GET['data_ate'] ?? date('Y-m-t', strtotime("$ano_filtro-$mes_filtro-01"));
$f_cat_id   = $_GET['f_cat'] ?? '';

// --- 1. ATUALIZAÇÃO AUTOMÁTICA DE STATUS ---
$pdo->prepare("UPDATE minhaseconomias_movimentacoes SET status = 'Atrasado' WHERE usuario_id = ? AND status = 'Futuro' AND data_vencimento < ?")
    ->execute([$usuario_id, $hoje_sql]);

// --- 2. CONTABILIZAÇÃO DE ATRASADOS ---
$stmt_atraso = $pdo->prepare("SELECT COUNT(*) as total, SUM(valor) as valor_total FROM minhaseconomias_movimentacoes WHERE usuario_id = ? AND status = 'Atrasado' AND tipo = 'Despesa'");
$stmt_atraso->execute([$usuario_id]);
$dados_atraso = $stmt_atraso->fetch();
$total_atrasados = $dados_atraso['total'] ?? 0;
$valor_atrasados = $dados_atraso['valor_total'] ?? 0;

// --- 3. SALDO REAL NAS CONTAS (COLUNA LATERAL) ---
$sql_contas = "SELECT c.*, 
    (c.saldo_inicial + 
    IFNULL((SELECT SUM(valor) FROM minhaseconomias_movimentacoes WHERE conta_id = c.id AND tipo = 'Receita' AND status = 'Pago'), 0) - 
    IFNULL((SELECT SUM(valor) FROM minhaseconomias_movimentacoes WHERE conta_id = c.id AND tipo = 'Despesa' AND status = 'Pago'), 0)) as saldo_real 
    FROM minhaseconomias_contas c WHERE c.usuario_id = ? ORDER BY c.status DESC, c.nome ASC";
$stmt_c = $pdo->prepare($sql_contas);
$stmt_c->execute([$usuario_id]);
$contas = $stmt_c->fetchAll(PDO::FETCH_ASSOC);

$total_hoje = 0;
foreach($contas as $c) { if($c['status'] == 1) $total_hoje += $c['saldo_real']; }

// --- 4. CÁLCULOS FINANCEIROS DO PERÍODO ---
$where_filtros = " AND data_vencimento BETWEEN '$data_de' AND '$data_ate'";
if($f_cat_id) $where_filtros .= " AND categoria_id = " . (int)$f_cat_id;

// Realizado
$stmt_r = $pdo->prepare("SELECT SUM(CASE WHEN tipo='Receita' THEN valor ELSE 0 END) as rec, SUM(CASE WHEN tipo='Despesa' THEN valor ELSE 0 END) as desp FROM minhaseconomias_movimentacoes WHERE usuario_id=? AND status='Pago' $where_filtros");
$stmt_r->execute([$usuario_id]);
$res_pago = $stmt_r->fetch();

// Projeção
$stmt_f = $pdo->prepare("SELECT 
    SUM(CASE WHEN tipo='Receita' AND status='Futuro' THEN valor ELSE 0 END) as rec_f, 
    SUM(CASE WHEN tipo='Despesa' AND status='Futuro' THEN valor ELSE 0 END) as desp_f,
    SUM(CASE WHEN status='Atrasado' THEN valor ELSE 0 END) as desp_a 
    FROM minhaseconomias_movimentacoes WHERE usuario_id=? $where_filtros");
$stmt_f->execute([$usuario_id]);
$res_proj = $stmt_f->fetch();

$previsao_final = ($total_hoje + ($res_proj['rec_f'] ?? 0)) - (($res_proj['desp_f'] ?? 0) + ($res_proj['desp_a'] ?? 0));

// Dados para o Gráfico de Pizza (Despesas por Categoria)
$stmt_pizza = $pdo->prepare("SELECT c.nome, SUM(m.valor) as total FROM minhaseconomias_movimentacoes m JOIN minhaseconomias_categorias c ON m.categoria_id = c.id WHERE m.usuario_id = ? AND m.tipo = 'Despesa' AND m.data_vencimento BETWEEN ? AND ? GROUP BY c.id ORDER BY total DESC");
$stmt_pizza->execute([$usuario_id, $data_de, $data_ate]);
$dados_pizza = $stmt_pizza->fetchAll(PDO::FETCH_ASSOC);

// Buscar categorias para o filtro
$categorias = $pdo->query("SELECT id, nome FROM minhaseconomias_categorias WHERE usuario_id = $usuario_id ORDER BY nome ASC")->fetchAll();

function getIcone($t) { return ['Banco'=>'fa-university','Cartao'=>'fa-credit-card','Empresa'=>'fa-briefcase'][$t] ?? 'fa-wallet'; }
?>

<style>
    .pulse-atrasado { animation: pulse-red 2s infinite; border: none; }
    @keyframes pulse-red { 
        0% { box-shadow: 0 0 0 0 rgba(220, 53, 53, 0.7); } 
        70% { box-shadow: 0 0 0 10px rgba(220, 53, 53, 0); } 
        100% { box-shadow: 0 0 0 0 rgba(220, 53, 53, 0); } 
    }
    .card-finance { border-radius: 20px; border: none; }
    .chart-container { position: relative; height: 250px; width: 100%; }
    .card-indicador { transition: transform 0.2s; cursor: pointer; text-decoration: none !important; }
    .card-indicador:hover { transform: scale(1.02); }
</style>

<!-- AVISO DE ATRASO (Banner Original) -->
<?php if($total_atrasados > 0): ?>
<div class="alert alert-danger shadow-sm pulse-atrasado d-flex justify-content-between align-items-center mb-4" style="border-radius: 15px;">
    <div class="text-danger">
        <i class="fas fa-exclamation-triangle fa-lg me-2"></i>
        <strong>SISTEMA DE ALERTA:</strong> Você possui <b><?= $total_atrasados ?></b> conta(s) em atraso (<b>R$ <?= number_format($valor_atrasados, 2, ',', '.') ?></b>).
    </div>
    <a href="index.php?p=transacoes&f_status=Atrasado" class="btn btn-danger btn-sm rounded-pill px-4 fw-bold shadow">RESOLVER AGORA</a>
</div>
<?php endif; ?>

<div class="row g-4">
    <!-- COLUNA ESQUERDA (ESTRUTURA ORIGINAL) -->
    <div class="col-12 col-lg-3">
        <?php if($total_atrasados > 0): ?>
        <div class="card bg-dark text-white mb-4 shadow" style="border-radius: 20px; border-left: 6px solid #dc3545;">
            <div class="card-body p-3">
                <small class="text-danger fw-bold text-uppercase">Pendência Crítica</small>
                <h4 class="fw-bold mb-1">R$ <?= number_format($valor_atrasados, 2, ',', '.') ?></h4>
            </div>
        </div>
        <?php endif; ?>

        <div class="card card-finance p-4 bg-white shadow-sm mb-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h6 class="fw-bold m-0">Minhas Contas</h6>
                <button class="btn btn-primary btn-sm rounded-circle shadow" onclick="novaConta()"><i class="fas fa-plus"></i></button>
            </div>
            <div class="account-scroll pe-1" style="max-height: 400px; overflow-y: auto;">
                <?php foreach($contas as $c): ?>
                <div class="d-flex align-items-center justify-content-between p-2 mb-2 rounded-3 border bg-light">
                    <div class="d-flex align-items-center overflow-hidden">
                        <div class="rounded-3 text-white d-flex align-items-center justify-content-center me-2" style="background: <?= $c['cor'] ?>; min-width: 32px; height: 32px;"><i class="fas <?= getIcone($c['tipo']) ?> fa-xs"></i></div>
                        <div class="text-truncate">
                            <div class="small fw-bold text-dark"><?= $c['nome'] ?></div>
                            <div class="text-muted" style="font-size: 9px;">R$ <?= number_format($c['saldo_real'], 2, ',', '.') ?></div>
                        </div>
                    </div>
                    <button class="btn btn-link btn-sm text-primary p-1" onclick="editarConta(<?= $c['id'] ?>, '<?= addslashes($c['nome']) ?>', '<?= $c['saldo_inicial'] ?>', '<?= $c['tipo'] ?>')"><i class="fas fa-edit"></i></button>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="mt-4 p-3 bg-primary bg-opacity-10 rounded-4 text-center">
                <small class="text-muted d-block text-uppercase fw-bold" style="font-size: 8px;">Saldo Total Hoje</small>
                <h4 class="fw-bold text-primary m-0">R$ <?= number_format($total_hoje, 2, ',', '.') ?></h4>
            </div>
            <div class="mt-2 p-3 bg-light rounded-4 text-center">
                <small class="text-muted d-block text-uppercase fw-bold" style="font-size: 8px;">Previsto para o Mês</small>
                <h5 class="fw-bold m-0 <?= $previsao_final >= 0 ? 'text-success' : 'text-danger' ?>">R$ <?= number_format($previsao_final, 2, ',', '.') ?></h5>
            </div>
        </div>
    </div>

    <!-- ÁREA PRINCIPAL -->
    <div class="col-12 col-lg-9">
        <!-- BARRA DE FILTROS ORIGINAL -->
        <div class="card card-finance p-4 mb-4 border-0 shadow-sm bg-white">
            <form method="GET" action="index.php" class="row g-3 align-items-end">
                <input type="hidden" name="p" value="dashboard">
                <div class="col-md-2">
                    <label class="small fw-bold">MÊS</label>
                    <select name="mes" class="form-select border-0 bg-light rounded-pill px-3 shadow-sm">
                        <?php foreach($meses_nomes as $k=>$v) echo "<option value='$k' ".($mes_filtro==$k?'selected':'').">$v</option>"; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="small fw-bold">DE:</label>
                    <input type="date" name="data_de" class="form-control border-0 bg-light rounded-pill px-3 shadow-sm" value="<?= $data_de ?>">
                </div>
                <div class="col-md-2">
                    <label class="small fw-bold">ATÉ:</label>
                    <input type="date" name="data_ate" class="form-control border-0 bg-light rounded-pill px-3 shadow-sm" value="<?= $data_ate ?>">
                </div>
                <div class="col-md-3">
                    <label class="small fw-bold">CATEGORIA</label>
                    <select name="f_cat" class="form-select border-0 bg-light rounded-pill px-3 shadow-sm">
                        <option value="">Todas</option>
                        <?php foreach($categorias as $cat) echo "<option value='{$cat['id']}' ".($f_cat_id==$cat['id']?'selected':'').">{$cat['nome']}</option>"; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100 rounded-pill fw-bold shadow">ATUALIZAR</button>
                </div>
            </form>
        </div>

        <!-- INDICADORES CLICÁVEIS -->
        <div class="row g-3 mb-4 text-center">
            <div class="col-6 col-md-3">
                <a href="index.php?p=transacoes&f_tipo=Receita&f_status=Pago" class="card border-0 shadow-sm p-3 bg-white h-100 card-indicador">
                    <small class="text-muted fw-bold d-block mb-1" style="font-size: 9px;">RECEBIDO</small>
                    <span class="text-success fw-bold">R$ <?= number_format($res_pago['rec'], 2, ',', '.') ?></span>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="index.php?p=transacoes&f_tipo=Despesa&f_status=Pago" class="card border-0 shadow-sm p-3 bg-white h-100 card-indicador">
                    <small class="text-muted fw-bold d-block mb-1" style="font-size: 9px;">PAGO</small>
                    <span class="text-danger fw-bold">R$ <?= number_format($res_pago['desp'], 2, ',', '.') ?></span>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="index.php?p=transacoes&f_tipo=Receita&f_status=Futuro" class="card border-0 shadow-sm p-3 bg-white h-100 card-indicador">
                    <small class="text-muted fw-bold d-block mb-1" style="font-size: 9px;">A RECEBER</small>
                    <span class="text-primary fw-bold">R$ <?= number_format($res_proj['rec_f'], 2, ',', '.') ?></span>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="index.php?p=transacoes&f_tipo=Despesa&f_status=Futuro" class="card border-0 shadow-sm p-3 bg-white h-100 card-indicador">
                    <small class="text-muted fw-bold d-block mb-1" style="font-size: 9px;">A PAGAR (FUTURO)</small>
                    <span class="text-warning fw-bold">R$ <?= number_format($res_proj['desp_f'], 2, ',', '.') ?></span>
                </a>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-12 col-xl-6">
                <div class="card card-finance p-4 bg-white shadow-sm h-100">
                    <h6 class="fw-bold mb-4 small text-uppercase">Balanço Realizado</h6>
                    <div class="chart-container"><canvas id="chartRealizado"></canvas></div>
                </div>
            </div>
            <div class="col-12 col-xl-6">
                <div class="card card-finance p-4 bg-white shadow-sm h-100">
                    <h6 class="fw-bold mb-4 small text-uppercase text-primary">Projeção de Despesas</h6>
                    <div class="chart-container"><canvas id="chartProjecao"></canvas></div>
                </div>
            </div>
        </div>

        <!-- NOVO GRÁFICO DE PIZZA ABAIXO -->
        <div class="row">
            <div class="col-12">
                <div class="card card-finance p-4 bg-white shadow-sm">
                    <h6 class="fw-bold mb-4 small text-uppercase text-center text-muted">Distribuição de Despesas por Categoria (%)</h6>
                    <div class="chart-container" style="height: 350px;"><canvas id="chartPizza"></canvas></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: GERENCIAR CONTA (ORIGINAL) -->
<div class="modal fade" id="modalConta" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form action="index.php" method="POST" class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pt-4 px-4"><h5 class="fw-bold" id="labelModal">Gerenciar Conta</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-4">
                <input type="hidden" name="id_conta" id="id_conta">
                <div class="mb-3"><label class="small fw-bold">NOME</label><input type="text" name="nome" id="nome_c" class="form-control" required></div>
                <div class="row g-3">
                    <div class="col-md-6"><label class="small fw-bold">TIPO</label><select name="tipo" id="tipo_c" class="form-select"><option value="Banco">Banco</option><option value="Carteira">Carteira</option><option value="Cartao">Cartão</option></select></div>
                    <div class="col-md-6"><label class="small fw-bold">SALDO INICIAL</label><input type="text" name="valor_inicial" id="valor_c" class="form-control" placeholder="0,00"></div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4"><button type="submit" name="btn_salvar_conta" class="btn btn-primary w-100 rounded-pill py-3 fw-bold shadow">SALVAR</button></div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const globalOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        onClick: (evt, activeEls, chart) => {
            if (activeEls.length > 0) {
                const idx = activeEls[0].index;
                let url = "index.php?p=transacoes";
                if (chart.canvas.id === 'chartRealizado') {
                    url += (idx === 0) ? "&f_tipo=Receita&f_status=Pago" : "&f_tipo=Despesa&f_status=Pago";
                } else if (chart.canvas.id === 'chartProjecao') {
                    const st = (idx === 0) ? 'Pago' : (idx === 1 ? 'Futuro' : 'Atrasado');
                    url += `&f_status=${st}`;
                }
                window.location.href = url;
            }
        }
    };

    new Chart(document.getElementById('chartRealizado'), {
        type: 'bar',
        data: { labels: ['Entradas', 'Saídas'], datasets: [{ data: [<?= (float)$res_pago['rec'] ?>, <?= (float)$res_pago['desp'] ?>], backgroundColor: ['#2ecc71', '#e74c3c'], borderRadius: 10 }] },
        options: globalOptions
    });

    new Chart(document.getElementById('chartProjecao'), {
        type: 'bar',
        data: { labels: ['Pago', 'Pendente', 'Atrasado'], datasets: [{ data: [<?= (float)$res_pago['desp'] ?>, <?= (float)$res_proj['desp_f'] ?>, <?= (float)$res_proj['desp_a'] ?>], backgroundColor: ['#28a745', '#ffc107', '#dc3545'], borderRadius: 10 }] },
        options: globalOptions
    });

    new Chart(document.getElementById('chartPizza'), {
        type: 'doughnut',
        data: {
            labels: [<?php foreach($dados_pizza as $d) echo "'" . $d['nome'] . "',"; ?>],
            datasets: [{
                data: [<?php foreach($dados_pizza as $d) echo $d['total'] . ","; ?>],
                backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#858796', '#5a5c69'],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: true, position: 'right' },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const val = context.parsed;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const perc = ((val / total) * 100).toFixed(1) + "%";
                            return `${context.label}: R$ ${val.toLocaleString('pt-BR')} (${perc})`;
                        }
                    }
                }
            }
        }
    });

    function novaConta() {
        document.getElementById('id_conta').value = '';
        document.getElementById('nome_c').value = '';
        document.getElementById('valor_c').value = '';
        document.getElementById('valor_c').readOnly = false;
        document.getElementById('labelModal').innerText = 'Nova Conta';
        new bootstrap.Modal(document.getElementById('modalConta')).show();
    }

    function editarConta(id, nome, valor, tipo) {
        document.getElementById('id_conta').value = id;
        document.getElementById('nome_c').value = nome;
        document.getElementById('valor_c').value = valor;
        document.getElementById('valor_c').readOnly = true;
        document.getElementById('tipo_c').value = tipo;
        document.getElementById('labelModal').innerText = 'Editar Conta';
        new bootstrap.Modal(document.getElementById('modalConta')).show();
    }
</script>