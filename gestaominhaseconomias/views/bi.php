<?php
/**
 * View: BI - Assistente Financeiro Inteligente
 */

// 1. Maiores gastos por categoria no período
$stmt_top = $pdo->prepare("
    SELECT c.nome, SUM(m.valor) as total 
    FROM minhaseconomias_movimentacoes m
    JOIN minhaseconomias_categorias c ON m.categoria_id = c.id
    WHERE m.usuario_id = ? AND m.tipo = 'Despesa' AND m.data_vencimento BETWEEN ? AND ?
    GROUP BY c.id ORDER BY total DESC LIMIT 5
");
$stmt_top->execute([$usuario_id, $data_de, $data_ate]);
$top_gastos = $stmt_top->fetchAll(PDO::FETCH_ASSOC);

// 2. Itens marcados com Flag para Melhoria
$stmt_flag = $pdo->prepare("
    SELECT m.*, c.nome as cat_nome 
    FROM minhaseconomias_movimentacoes m
    JOIN minhaseconomias_categorias c ON m.categoria_id = c.id
    WHERE m.usuario_id = ? AND m.bi_analise = 1
");
$stmt_flag->execute([$usuario_id]);
$itens_flag = $stmt_flag->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <h3 class="fw-bold"><i class="fas fa-robot text-primary me-2"></i>Assistente Financeiro BI</h3>
            <p class="text-muted small">Análise focada em transformar despesas em receitas.</p>
        </div>
    </div>

    <div class="row g-4">
        <!-- Gráfico de Gastos -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm p-4 rounded-4 h-100">
                <h6 class="fw-bold mb-4">Concentração de Despesas por Categoria</h6>
                <div style="position: relative; height:350px; width:100%">
                    <canvas id="chartBI_Top"></canvas>
                </div>
            </div>
        </div>

        <!-- Insights e Melhorias -->
        <div class="col-lg-5">
            <div class="card bg-dark text-white border-0 shadow-sm p-4 rounded-4 mb-4">
                <h6 class="text-primary fw-bold text-uppercase small mb-3">Plano de Conversão (Insight)</h6>
                <?php 
                $total_bi = 0;
                foreach($itens_flag as $item): 
                    $total_bi += $item['valor'];
                    $lucro_anual = $item['valor'] * 12.68; // Exemplo 1% a.m.
                ?>
                    <div class="mb-3 border-bottom border-secondary pb-2 small">
                        <div class="d-flex justify-content-between">
                            <span><?= htmlspecialchars($item['descricao']) ?></span>
                            <span class="text-danger">R$ <?= number_format($item['valor'], 2, ',', '.') ?></span>
                        </div>
                        <div class="text-success mt-1">
                            <i class="fas fa-chart-line"></i> Se investido, vira <strong>R$ <?= number_format($lucro_anual, 2, ',', '.') ?></strong> em 1 ano.
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if(empty($itens_flag)) echo "<p class='small text-muted mt-3 text-center'>Marque transações com o Robô na página de Transações para analisarmos aqui.</p>"; ?>
            </div>

            <div class="card border-0 shadow-sm p-4 rounded-4 bg-light border-start border-4 border-primary">
                <h6 class="fw-bold mb-2">Diagnóstico Geral</h6>
                <p class="small mb-0">Seu maior ralo financeiro hoje é em <strong><?= $top_gastos[0]['nome'] ?? 'N/A' ?></strong>. Reduzir 20% deste item criará um fluxo positivo imediato no seu saldo previsto.</p>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    new Chart(document.getElementById('chartBI_Top'), {
        type: 'doughnut',
        data: {
            labels: [<?php foreach($top_gastos as $g) echo "'" . $g['nome'] . "',"; ?>],
            datasets: [{
                data: [<?php foreach($top_gastos as $g) echo $g['total'] . ","; ?>],
                backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '75%',
            plugins: { legend: { position: 'bottom' } }
        }
    });
</script>