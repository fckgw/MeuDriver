<?php
/**
 * BI - Assistente Financeiro Inteligente
 */
$stmt_top = $pdo->prepare("SELECT c.nome, SUM(m.valor) as total FROM minhaseconomias_movimentacoes m JOIN minhaseconomias_categorias c ON m.categoria_id = c.id WHERE m.usuario_id=? AND m.tipo='Despesa' AND m.data_vencimento BETWEEN ? AND ? GROUP BY c.id ORDER BY total DESC LIMIT 5");
$stmt_top->execute([$usuario_id, $data_de, $data_ate]);
$top_categorias = $stmt_top->fetchAll(PDO::FETCH_ASSOC);

$stmt_ajuda = $pdo->prepare("SELECT m.*, c.nome as cat_nome FROM minhaseconomias_movimentacoes m JOIN minhaseconomias_categorias c ON m.categoria_id = c.id WHERE m.usuario_id=? AND m.bi_analise=1");
$stmt_ajuda->execute([$usuario_id]);
$itens_flag = $stmt_ajuda->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-md-12"><h3 class="fw-bold"><i class="fas fa-brain text-primary me-2"></i>BI Especial - Assistente de Melhoria</h3></div>
    </div>
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm p-4 rounded-4 h-100">
                <h6 class="fw-bold mb-4">Top 5 Categorias (Fugas de Caixa)</h6>
                <div style="position: relative; height:350px;"><canvas id="chartBI_Top"></canvas></div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card bg-dark text-white border-0 shadow-sm p-4 rounded-4 mb-4">
                <h6 class="text-primary fw-bold text-uppercase small">Plano de Conversão para Receita</h6>
                <?php foreach($itens_flag as $item): 
                    $poupanca = $item['valor'] * 12.68;
                ?>
                    <div class="mb-3 border-bottom border-secondary pb-2">
                        <div class="d-flex justify-content-between small"><span><?= htmlspecialchars($item['descricao']) ?></span><span class="text-danger fw-bold">R$ <?= number_format($item['valor'],2,',','.') ?></span></div>
                        <div class="text-success x-small mt-1"><i class="fas fa-lightbulb"></i> Se economizar e investir, terá <b>R$ <?= number_format($poupanca,2,',','.') ?></b> em 1 ano.</div>
                    </div>
                <?php endforeach; ?>
                <?php if(empty($itens_flag)) echo "<p class='small text-muted'>Nenhum gasto marcado com a Flag de robô.</p>"; ?>
            </div>
            <div class="card border-0 shadow-sm p-4 rounded-4 bg-light border-start border-4 border-info">
                <h6 class="fw-bold small mb-2">Insight do BI</h6>
                <p class="small mb-0">Seu maior gasto é em <b><?= $top_categorias[0]['nome'] ?? 'N/A' ?></b>. Reduzir 15% aqui equilibra seu fluxo.</p>
            </div>
        </div>
    </div>
</div>
<script>
    new Chart(document.getElementById('chartBI_Top'), { type: 'doughnut', data: { labels: [<?php foreach($top_categorias as $c) echo "'".$c['nome']."',"; ?>], datasets: [{ data: [<?php foreach($top_categorias as $c) echo $c['total'].","; ?>], backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b'] }] }, options: { responsive: true, maintainAspectRatio: false, cutout: '75%', plugins: { legend: { position: 'bottom' } } } });
</script>