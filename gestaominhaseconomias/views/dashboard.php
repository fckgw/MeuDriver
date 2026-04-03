<?php
/**
 * BDSoft Workspace - Minhas Economias
 * View: Dashboard Principal com Gráfico Funcional
 */

$usuario_id = $_SESSION['usuario_id'];

// --- 1. BUSCAR CONTAS E CALCULAR SALDO REAL ---
// Soma Saldo Inicial + Receitas Pagas - Despesas Pagas
$sql_contas = "
    SELECT c.*, 
    (c.saldo_inicial + 
        IFNULL((SELECT SUM(valor) FROM minhaseconomias_movimentacoes WHERE conta_id = c.id AND tipo = 'Receita' AND status = 'Pago'), 0) - 
        IFNULL((SELECT SUM(valor) FROM minhaseconomias_movimentacoes WHERE conta_id = c.id AND tipo = 'Despesa' AND status = 'Pago'), 0)
    ) as saldo_real
    FROM minhaseconomias_contas c 
    WHERE c.usuario_id = ? 
    ORDER BY c.status DESC, c.nome ASC";

$stmt = $pdo->prepare($sql_contas);
$stmt->execute([$usuario_id]);
$contas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_disponivel = 0;
foreach ($contas as $c) {
    if ($c['status'] == 1) {
        $total_disponivel += $c['saldo_real'];
    }
}

// --- 2. BUSCAR TOTAIS PARA O GRÁFICO (Mês/Ano Filtrados) ---
$filtro_pj = "";
if ($visao_atual == 'pf') $filtro_pj = " AND origem_pj = 0";
if ($visao_atual == 'pj') $filtro_pj = " AND origem_pj = 1";

// Soma Receitas do Mês
$stmtR = $pdo->prepare("SELECT SUM(valor) as total FROM minhaseconomias_movimentacoes WHERE usuario_id = ? AND tipo = 'Receita' AND status = 'Pago' AND MONTH(data_pagamento) = ? AND YEAR(data_pagamento) = ? $filtro_pj");
$stmtR->execute([$usuario_id, $mes_filtro, $ano_filtro]);
$val_receitas_grafico = $stmtR->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Soma Despesas do Mês
$stmtD = $pdo->prepare("SELECT SUM(valor) as total FROM minhaseconomias_movimentacoes WHERE usuario_id = ? AND tipo = 'Despesa' AND status = 'Pago' AND MONTH(data_pagamento) = ? AND YEAR(data_pagamento) = ? $filtro_pj");
$stmtD->execute([$usuario_id, $mes_filtro, $ano_filtro]);
$val_despesas_grafico = $stmtD->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

function getIcone($tipo) {
    $map = ['Banco' => 'fa-university', 'Cartao' => 'fa-credit-card', 'Empresa' => 'fa-briefcase'];
    return $map[$tipo] ?? 'fa-wallet';
}
?>

<!-- INJEÇÃO DE DADOS PARA O GRÁFICO (Obrigatório para o footer.php ler) -->
<script>
    window.dadosReceitas = <?= (float)$val_receitas_grafico ?>;
    window.dadosDespesas = <?= (float)$val_despesas_grafico ?>;
</script>

<div class="row g-4">
    <!-- BARRA LATERAL: CONTAS -->
    <div class="col-12 col-lg-3">
        <div class="card card-finance p-4 bg-white mb-4 shadow-sm">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h6 class="fw-bold m-0 text-dark">Minhas Contas</h6>
                <button class="btn btn-primary btn-sm rounded-circle shadow" onclick="abrirModalNovaConta()"><i class="fas fa-plus"></i></button>
            </div>

            <div class="account-scroll" style="max-height: 400px; overflow-y: auto;">
                <?php if (empty($contas)): ?>
                    <p class="text-muted small text-center py-4">Nenhuma conta ativa.</p>
                <?php else: ?>
                    <?php foreach($contas as $c): ?>
                        <div class="account-item d-flex align-items-center justify-content-between p-2 rounded-3 border mb-2 <?= $c['status'] == 0 ? 'bg-light opacity-50' : 'bg-white' ?>">
                            <div class="d-flex align-items-center overflow-hidden">
                                <div class="rounded-3 text-white d-flex align-items-center justify-content-center me-2" style="background: <?= $c['cor'] ?>; min-width: 32px; height: 32px;">
                                    <i class="fas <?= getIcone($c['tipo']) ?> fa-xs"></i>
                                </div>
                                <div class="text-truncate">
                                    <div class="small fw-bold text-dark text-truncate" style="max-width: 80px;"><?= htmlspecialchars($c['nome']) ?></div>
                                    <div class="text-muted" style="font-size: 9px;">R$ <?= number_format($c['saldo_real'], 2, ',', '.') ?></div>
                                </div>
                            </div>
                            <div class="d-flex">
                                <button class="btn btn-link btn-sm text-primary p-1" onclick="prepararEdicao(<?= $c['id'] ?>, '<?= addslashes($c['nome']) ?>', '<?= $c['saldo_inicial'] ?>', <?= $c['status'] ?>, '<?= $c['tipo'] ?>')"><i class="fas fa-edit"></i></button>
                                <button class="btn btn-link btn-sm text-danger p-1" onclick="confirmarExclusao(<?= $c['id'] ?>, '<?= addslashes($c['nome']) ?>')"><i class="fas fa-trash"></i></button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="mt-4 p-3 bg-primary bg-opacity-10 rounded-4 text-center">
                <small class="text-muted d-block text-uppercase fw-bold" style="font-size: 8px;">Saldo Total Disponível</small>
                <h4 class="fw-bold text-primary m-0">R$ <?= number_format($total_disponivel, 2, ',', '.') ?></h4>
            </div>
        </div>
    </div>

    <!-- ÁREA PRINCIPAL -->
    <div class="col-12 col-lg-9">
        <!-- BARRA DE FILTROS -->
        <div class="card card-finance p-4 mb-4 border-0 shadow-sm bg-white">
            <form method="GET" action="index.php" class="row g-4 align-items-end">
                <input type="hidden" name="p" value="dashboard">
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-muted text-uppercase" style="font-size: 10px;">Filtrar Mês</label>
                    <select name="mes" class="form-select border-0 bg-light rounded-pill px-3 shadow-sm">
                        <?php 
                        $meses = ["01"=>"Janeiro","02"=>"Fevereiro","03"=>"Março","04"=>"Abril","05"=>"Maio","06"=>"Junho","07"=>"Julho","08"=>"Agosto","09"=>"Setembro","10"=>"Outubro","11"=>"Novembro","12"=>"Dezembro"];
                        foreach($meses as $m_num => $m_nome) echo "<option value='$m_num' ".($mes_filtro == $m_num ? 'selected' : '').">$m_nome</option>";
                        ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted text-uppercase" style="font-size: 10px;">Ano</label>
                    <select name="ano" class="form-select border-0 bg-light rounded-pill px-3 shadow-sm">
                        <?php for($i=2024; $i<=2026; $i++) echo "<option value='$i' ".($ano_filtro == $i ? 'selected' : '').">$i</option>"; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <button type="submit" class="btn btn-dark w-100 rounded-pill fw-bold shadow-sm">
                        <i class="fas fa-sync-alt me-2"></i>ATUALIZAR FLUXO MENSAL
                    </button>
                </div>
            </form>
        </div>

        <!-- INDICADORES -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card card-finance p-4 border-start border-primary border-5 shadow-sm h-100">
                    <small class="text-muted fw-bold">DISPONÍVEL</small>
                    <h3 class="text-primary fw-bold m-0">R$ <?= number_format($total_disponivel, 2, ',', '.') ?></h3>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-finance p-4 border-start border-success border-5 shadow-sm h-100">
                    <small class="text-muted fw-bold">GANHOS (MÊS)</small>
                    <h3 class="text-success fw-bold m-0">R$ <?= number_format($val_receitas_grafico, 2, ',', '.') ?></h3>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-finance p-4 border-start border-danger border-5 shadow-sm h-100">
                    <small class="text-muted fw-bold">GASTOS (MÊS)</small>
                    <h3 class="text-danger fw-bold m-0">R$ <?= number_format($val_despesas_grafico, 2, ',', '.') ?></h3>
                </div>
            </div>
        </div>

        <!-- GRÁFICO -->
        <div class="card card-finance p-4 bg-white shadow-sm">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h6 class="fw-bold mb-0 text-dark">Fluxo Mensal de Caixa</h6>
                <span class="badge bg-light text-dark border rounded-pill px-3"><?= $meses[$mes_filtro] ?> / <?= $ano_filtro ?></span>
            </div>
            <div class="chart-container" style="position: relative; height: 350px; width: 100%;">
                <canvas id="financeChart"></canvas>
            </div>
        </div>
    </div>
</div>