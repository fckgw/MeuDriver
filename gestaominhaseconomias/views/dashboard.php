<?php
/**
 * BDSoft Workspace - Minhas Economias
 * View: Dashboard Principal (PF) - COMPLETO, SINCRONIZADO E COM AUTO-UPDATE
 */
$usuario_id = $_SESSION['usuario_id'];
$hoje_sql = date('Y-m-d');
$meses_nomes = ["01"=>"Janeiro","02"=>"Fevereiro","03"=>"Março","04"=>"Abril","05"=>"Maio","06"=>"Junho","07"=>"Julho","08"=>"Agosto","09"=>"Setembro","10"=>"Outubro","11"=>"Novembro","12"=>"Dezembro"];

// --- 0. REGRA AUTOMÁTICA: ATUALIZAR STATUS ATRASADO ---
// Garante que qualquer item 'Futuro' com data menor que hoje vire 'Atrasado' antes de carregar os cálculos
$sql_auto_update = "UPDATE minhaseconomias_movimentacoes SET status = 'Atrasado' 
                    WHERE usuario_id = ? AND status = 'Futuro' AND data_vencimento < ?";
$pdo->prepare($sql_auto_update)->execute([$usuario_id, $hoje_sql]);

// --- NOVO: FILTRO DE BANCO / CONTA ---
$conta_filtro = (isset($_GET['f_conta']) && $_GET['f_conta'] !== '') ? (int)$_GET['f_conta'] : null;
$where_conta = $conta_filtro ? " AND conta_id = $conta_filtro" : "";

// --- 1. VARREDURA DE PENDÊNCIAS HOJE ---
$stmt_p = $pdo->prepare("SELECT COUNT(*) as total FROM minhaseconomias_movimentacoes WHERE usuario_id = ? AND status IN ('Futuro','Atrasado') AND data_vencimento = ?");
$stmt_p->execute([$usuario_id, $hoje_sql]);
$pendencias_hoje = $stmt_p->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// --- 1.1 VARREDURA DE ITENS ATRASADOS (Geral) ---
$stmt_a = $pdo->prepare("SELECT COUNT(*) as total FROM minhaseconomias_movimentacoes WHERE usuario_id = ? AND status = 'Atrasado' AND data_vencimento < ? $where_conta");
$stmt_a->execute([$usuario_id, $hoje_sql]);
$total_atrasados = $stmt_a->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// --- 2. SALDO REAL NAS CONTAS ---
$sql_c = "SELECT c.*, 
    (c.saldo_inicial + 
    IFNULL((SELECT SUM(valor) FROM minhaseconomias_movimentacoes WHERE conta_id = c.id AND tipo = 'Receita' AND status = 'Pago'), 0) - 
    IFNULL((SELECT SUM(valor) FROM minhaseconomias_movimentacoes WHERE conta_id = c.id AND tipo = 'Despesa' AND status = 'Pago'), 0)) as saldo_real 
    FROM minhaseconomias_contas c WHERE c.usuario_id = ? ORDER BY c.status DESC, c.nome ASC";
$stmt = $pdo->prepare($sql_c); $stmt->execute([$usuario_id]);
$contas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Cálculo do Total Geral (ou da conta filtrada)
$total_hoje = 0; 
foreach($contas as $c) {
    if($c['status'] == 1) {
        if(!$conta_filtro || ($conta_filtro == $c['id'])) {
            $total_hoje += $c['saldo_real'];
        }
    }
}

// --- 3. REALIZADO NO MÊS (PAGO) ---
$sql_r = "SELECT SUM(CASE WHEN tipo='Receita' THEN valor ELSE 0 END) as rec, 
                 SUM(CASE WHEN tipo='Despesa' THEN valor ELSE 0 END) as desp 
          FROM minhaseconomias_movimentacoes 
          WHERE usuario_id=? AND status='Pago' AND MONTH(data_pagamento)=? AND YEAR(data_pagamento)=? $where_conta";
$stmt_r = $pdo->prepare($sql_r); $stmt_r->execute([$usuario_id, $mes_filtro, $ano_filtro]);
$pago = $stmt_r->fetch(PDO::FETCH_ASSOC);

// --- 4. PROJEÇÃO (FUTURO + ATRASADO) ---
$sql_f = "SELECT SUM(CASE WHEN tipo='Receita' THEN valor ELSE 0 END) as rec, 
                 SUM(CASE WHEN tipo='Despesa' THEN valor ELSE 0 END) as desp 
          FROM minhaseconomias_movimentacoes 
          WHERE usuario_id=? AND status IN ('Futuro','Atrasado') AND MONTH(m.data_vencimento)=? AND YEAR(m.data_vencimento)=? $where_conta";
// Pequeno ajuste na query acima para evitar erro de ambiguidade se houver joins futuros
$sql_f = str_replace("MONTH(m.data_vencimento)", "MONTH(data_vencimento)", $sql_f);
$sql_f = str_replace("YEAR(m.data_vencimento)", "YEAR(data_vencimento)", $sql_f);

$stmt_f = $pdo->prepare($sql_f); $stmt_f->execute([$usuario_id, $mes_filtro, $ano_filtro]);
$futuro = $stmt_f->fetch(PDO::FETCH_ASSOC);

$previsao_final = ($total_hoje + ($futuro['rec'] ?? 0)) - ($futuro['desp'] ?? 0);

// --- 5. DADOS POR CATEGORIA ---
$tipo_cat_filtro = $_GET['cat_tipo'] ?? 'Despesa';
$stmt_cat = $pdo->prepare("SELECT c.nome, SUM(m.valor) as total FROM minhaseconomias_movimentacoes m INNER JOIN minhaseconomias_categorias c ON m.categoria_id = c.id WHERE m.usuario_id = ? AND m.tipo = ? AND MONTH(m.data_vencimento) = ? AND YEAR(m.data_vencimento) = ? $where_conta GROUP BY c.nome ORDER BY total DESC LIMIT 6");
$stmt_cat->execute([$usuario_id, $tipo_cat_filtro, $mes_filtro, $ano_filtro]);
$dados_cat = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);
$labels_cat = []; $values_cat = [];
foreach($dados_cat as $dc) { $labels_cat[] = $dc['nome']; $values_cat[] = (float)$dc['total']; }

function getIconeBanco($t) { return ['Banco'=>'fa-university','Cartao'=>'fa-credit-card','Empresa'=>'fa-briefcase'][$t] ?? 'fa-wallet'; }
?>

<style>
    .card-indicator { cursor: pointer; transition: 0.3s; border: 2px solid transparent !important; border-radius: 18px !important; }
    .card-indicator:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0,0,0,0.08) !important; }
    .hover-receita:hover { border-color: #2ecc71 !important; }
    .hover-pago:hover { border-color: #e74c3c !important; }
    .hover-receber:hover { border-color: #3498db !important; }
    .hover-pagar:hover { border-color: #f1c40f !important; }
    .pulse-atrasado { animation: pulse-red 2s infinite; }
    @keyframes pulse-red { 0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); } 70% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); } 100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); } }
</style>

<script>
    window.dadosReal = { receita: <?= (float)($pago['rec'] ?? 0) ?>, despesa: <?= (float)($pago['desp'] ?? 0) ?> };
    window.dadosFuturo = { receita: <?= (float)($futuro['rec'] ?? 0) ?>, despesa: <?= (float)($futuro['desp'] ?? 0) ?> };
    window.dadosCategorias = { labels: <?= json_encode($labels_cat) ?>, values: <?= json_encode($values_cat) ?> };
    window.filtroAtual = { mes: '<?= $mes_filtro ?>', ano: '<?= $ano_filtro ?>', conta: '<?= $conta_filtro ?>' };
    window.pendenciasHoje = <?= (int)$pendencias_hoje ?>;
</script>

<div class="row g-4">
    <!-- COLUNA ESQUERDA -->
    <div class="col-12 col-lg-3">
        
        <!-- NOVO ALERTA: CONTAS ATRASADAS -->
        <?php if($total_atrasados > 0): ?>
        <div class="card border-0 shadow-sm mb-3 bg-dark text-white pulse-atrasado" style="border-radius: 15px; border-left: 5px solid #dc3545 !important;">
            <div class="card-body p-3 d-flex align-items-center">
                <i class="fas fa-exclamation-triangle text-danger fa-2x me-3"></i>
                <div>
                    <div class="fw-bold text-danger">Contas Atrasadas!</div>
                    <small>Você possui <?= $total_atrasados ?> item(ns) fora do prazo.</small>
                </div>
            </div>
            <a href="index.php?p=transacoes&f_status=Atrasado" class="btn btn-danger btn-sm m-2 fw-bold rounded-pill shadow-sm">RESOLVER AGORA</a>
        </div>
        <?php endif; ?>

        <!-- VARREDURA NORMAL: PENDÊNCIAS HOJE -->
        <?php if($pendencias_hoje > 0): ?>
        <div class="card border-0 shadow-sm mb-4 bg-danger text-white pulse-alert" style="border-radius: 15px;">
            <div class="card-body p-3 d-flex align-items-center"><i class="fas fa-bell fa-2x me-3"></i><div><div class="fw-bold">Pendências Hoje</div><small>Existem <?= $pendencias_hoje ?> itens vencendo.</small></div></div>
            <a href="index.php?p=transacoes&vencimento_hoje=1" class="btn btn-light btn-sm m-2 fw-bold text-danger rounded-pill shadow-sm">VER AGORA</a>
        </div>
        <?php endif; ?>

        <div class="card card-finance p-4 bg-white shadow-sm mb-4 border-0">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h6 class="fw-bold m-0">Minhas Contas</h6>
                <button class="btn btn-primary btn-sm rounded-circle shadow" onclick="window.abrirModalNovaConta()"><i class="fas fa-plus"></i></button>
            </div>
            <div class="account-scroll pe-1" style="max-height: 400px; overflow-y: auto;">
                <?php foreach($contas as $c): ?>
                <div class="d-flex align-items-center justify-content-between p-2 mb-2 rounded-3 border <?= $c['status']==0 ? 'opacity-25' : '' ?> <?= ($conta_filtro == $c['id']) ? 'border-primary' : '' ?>" style="background: #fcfcfc">
                    <div class="d-flex align-items-center overflow-hidden">
                        <div class="rounded-3 text-white d-flex align-items-center justify-content-center me-2" style="background: <?= $c['cor'] ?>; min-width: 32px; height: 32px;"><i class="fas <?= getIconeBanco($c['tipo']) ?> fa-xs"></i></div>
                        <div class="text-truncate">
                            <div class="small fw-bold text-dark"><?= htmlspecialchars($c['nome']) ?></div>
                            <div class="text-muted" style="font-size: 9px;">R$ <?= number_format($c['saldo_real'], 2, ',', '.') ?></div>
                        </div>
                    </div>
                    <div class="d-flex">
                        <button class="btn btn-link btn-sm text-primary p-1" onclick="window.prepararEdicao(<?= $c['id'] ?>, '<?= addslashes($c['nome']) ?>', '<?= $c['saldo_inicial'] ?>', <?= $c['status'] ?>, '<?= $c['tipo'] ?>')"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-link btn-sm text-danger p-1" onclick="window.confirmarExclusao(<?= $c['id'] ?>, '<?= addslashes($c['nome']) ?>')"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="mt-4 p-3 bg-primary bg-opacity-10 rounded-4 text-center">
                <small class="text-muted d-block text-uppercase fw-bold" style="font-size: 8px;">Saldo <?= $conta_filtro ? 'na Conta' : 'Hoje' ?></small>
                <h4 class="fw-bold text-primary m-0">R$ <?= number_format($total_hoje, 2, ',', '.') ?></h4>
            </div>
        </div>
        
        <div class="card card-finance p-4 <?= $previsao_final >= 0 ? 'bg-success bg-opacity-10' : 'bg-danger bg-opacity-10' ?> border-0 shadow-sm text-center">
            <small class="text-muted d-block text-uppercase fw-bold mb-1" style="font-size: 8px;">Previsão p/ Final do Mês</small>
            <h4 class="fw-bold <?= $previsao_final >= 0 ? 'text-success' : 'text-danger' ?> m-0">R$ <?= number_format($previsao_final, 2, ',', '.') ?></h4>
        </div>
    </div>

    <!-- ÁREA PRINCIPAL -->
    <div class="col-12 col-lg-9">
        <div class="card card-finance p-4 mb-4 border-0 shadow-sm bg-white">
            <form method="GET" action="index.php" class="row g-3 align-items-end">
                <input type="hidden" name="p" value="dashboard">
                <div class="col-md-3">
                    <label class="small fw-bold">Mês</label>
                    <select name="mes" class="form-select border-0 bg-light rounded-pill px-3 shadow-sm">
                        <?php foreach($meses_nomes as $k=>$v) echo "<option value='$k' ".($mes_filtro==$k?'selected':'').">$v</option>"; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="small fw-bold">Ano</label>
                    <select name="ano" class="form-select border-0 bg-light rounded-pill px-3 shadow-sm">
                        <?php for($i=2024; $i<=2026; $i++) echo "<option value='$i' ".($ano_filtro==$i?'selected':'').">$i</option>"; ?>
                    </select>
                </div>
                <!-- FILTRO BANCO -->
                <div class="col-md-4">
                    <label class="small fw-bold">Banco / Conta</label>
                    <select name="f_conta" class="form-select border-0 bg-light rounded-pill px-3 shadow-sm">
                        <option value="">Todos os Bancos</option>
                        <?php foreach($contas as $c): if($c['status']==1): ?>
                            <option value="<?= $c['id'] ?>" <?= ($conta_filtro == $c['id']) ? 'selected' : '' ?>><?= $c['nome'] ?></option>
                        <?php endif; endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100 rounded-pill fw-bold">FILTRAR</button>
                    <button type="button" onclick="resetarFiltros()" class="btn btn-light rounded-pill border shadow-sm px-3" title="Resetar Filtros"><i class="fas fa-sync-alt"></i></button>
                </div>
            </form>
        </div>

        <!-- QUADRINHOS INDICADORES -->
        <div class="row g-3 mb-4">
            <?php 
                $params_url = "&mes=$mes_filtro&ano=$ano_filtro" . ($conta_filtro ? "&f_conta=$conta_filtro" : "");
            ?>
            <div class="col-6 col-md-3"><div onclick="window.location.href='index.php?p=transacoes&f_tipo=Receita&f_status=Pago<?=$params_url?>'" class="card card-indicator hover-receita p-3 bg-white shadow-sm text-center border-0"><small class="text-muted d-block fw-bold" style="font-size: 8px;">RECEBIDO</small><span class="text-success fw-bold">R$ <?= number_format($pago['rec'] ?? 0, 2, ',', '.') ?></span></div></div>
            <div class="col-6 col-md-3"><div onclick="window.location.href='index.php?p=transacoes&f_tipo=Despesa&f_status=Pago<?=$params_url?>'" class="card card-indicator hover-pago p-3 bg-white shadow-sm text-center border-0"><small class="text-muted d-block fw-bold" style="font-size: 8px;">PAGO</small><span class="text-danger fw-bold">R$ <?= number_format($pago['desp'] ?? 0, 2, ',', '.') ?></span></div></div>
            <div class="col-6 col-md-3"><div onclick="window.location.href='index.php?p=transacoes&f_tipo=Receita&f_status=Futuro<?=$params_url?>'" class="card card-indicator hover-receber p-3 bg-white shadow-sm text-center border-0"><small class="text-muted d-block fw-bold" style="font-size: 8px;">A RECEBER</small><span class="text-primary fw-bold">R$ <?= number_format($futuro['rec'] ?? 0, 2, ',', '.') ?></span></div></div>
            <div class="col-6 col-md-3"><div onclick="window.location.href='index.php?p=transacoes&f_tipo=Despesa&f_status=Futuro<?=$params_url?>'" class="card card-indicator hover-pagar p-3 bg-white shadow-sm text-center border-0"><small class="text-muted d-block fw-bold" style="font-size: 8px;">A PAGAR</small><span class="text-warning fw-bold">R$ <?= number_format($futuro['desp'] ?? 0, 2, ',', '.') ?></span></div></div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-12 col-xl-6"><div class="card card-finance p-4 bg-white shadow-sm h-100 border-0"><h6 class="fw-bold mb-4 small text-uppercase">Balanço Realizado</h6><div style="height: 300px;"><canvas id="chartRealizado"></canvas></div></div></div>
            <div class="col-12 col-xl-6"><div class="card card-finance p-4 bg-white shadow-sm h-100 border-0"><h6 class="fw-bold mb-4 small text-uppercase text-primary">Projeção Futura</h6><div style="height: 300px;"><canvas id="chartFuturo"></canvas></div></div></div>
        </div>

        <div class="card card-finance p-4 bg-white shadow-sm border-0">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h6 class="fw-bold small text-uppercase">Distribuição por Categoria</h6>
                <div class="btn-group btn-group-sm rounded-pill overflow-hidden border">
                    <a href="index.php?p=dashboard&mes=<?=$mes_filtro?>&ano=<?=$ano_filtro?><?= $conta_filtro ? "&f_conta=$conta_filtro" : "" ?>&cat_tipo=Despesa" class="btn <?= $tipo_cat_filtro == 'Despesa' ? 'btn-danger' : 'btn-light' ?>">Saídas</a>
                    <a href="index.php?p=dashboard&mes=<?=$mes_filtro?>&ano=<?=$ano_filtro?><?= $conta_filtro ? "&f_conta=$conta_filtro" : "" ?>&cat_tipo=Receita" class="btn <?= $tipo_cat_filtro == 'Receita' ? 'btn-success' : 'btn-light' ?>">Entradas</a>
                </div>
            </div>
            <div style="height: 350px;"><canvas id="chartCategorias"></canvas></div>
        </div>
    </div>
</div>

<!-- MODAL: GERENCIAR CONTA (Sincronizado) -->
<div class="modal fade" id="modalConta" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 24px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold" id="labelModal">Gerenciar Conta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="index.php?mes=<?=$mes_filtro?>&ano=<?=$ano_filtro?><?= $conta_filtro ? "&f_conta=$conta_filtro" : "" ?>" method="POST">
                <input type="hidden" name="id_conta" id="id_conta">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="small fw-bold">NOME</label>
                        <input type="text" name="nome" id="nome" class="form-control" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="small fw-bold">TIPO</label>
                            <select name="tipo" id="tipo" class="form-select">
                                <option value="Carteira">Carteira</option>
                                <option value="Banco">Banco</option>
                                <option value="Cartao">Cartão</option>
                                <option value="Empresa">Empresa</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-bold">SALDO INICIAL</label>
                            <input type="tel" name="valor_inicial" id="valor" class="form-control" placeholder="0,00" required>
                        </div>
                    </div>
                    <div class="mt-4 p-3 bg-light rounded-4 d-flex justify-content-between align-items-center">
                        <span class="small fw-bold">CONTA ATIVA?</span>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="status" id="status" checked style="width: 2.5em; height: 1.25em;">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4">
                    <button type="submit" name="btn_salvar_conta" class="btn btn-primary w-100 rounded-pill fw-bold py-3 shadow">SALVAR</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: EXCLUIR CONTA -->
<div class="modal fade" id="modalExcluir" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-body p-5 text-center">
                <i class="fas fa-trash text-danger fa-4x mb-4 opacity-25"></i>
                <h4 class="fw-bold text-dark">Excluir conta?</h4>
                <p id="txtNomeExcluir" class="text-muted"></p>
                <form action="index.php" method="POST">
                    <input type="hidden" name="id_conta_excluir" id="id_conta_excluir">
                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" name="btn_excluir_conta" class="btn btn-danger rounded-pill fw-bold py-2 shadow">SIM, EXCLUIR</button>
                        <button type="button" class="btn btn-light rounded-pill py-2" data-bs-dismiss="modal">CANCELAR</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: PENDÊNCIAS HOJE -->
<div class="modal fade" id="modalPendenciasHoje" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-body p-5 text-center">
                <div class="mb-4">
                    <i class="fas fa-bell text-warning fa-4x pulse-alert rounded-circle p-3" style="background: #fffcf0;"></i>
                </div>
                <h4 class="fw-bold text-dark">Lançamentos para Hoje</h4>
                <p class="text-muted">Detectamos transações vencendo hoje em seu fluxo.</p>
                <div class="d-grid gap-2 mt-4">
                    <a href="index.php?p=transacoes&vencimento_hoje=1" class="btn btn-primary rounded-pill py-3 fw-bold shadow">VERIFICAR AGORA</a>
                    <button type="button" class="btn btn-light rounded-pill py-2 text-muted fw-bold" data-bs-dismiss="modal">Tratar depois</button>
                </div>
            </div>
        </div>
    </div>
</div>