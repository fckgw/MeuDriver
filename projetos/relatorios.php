<?php
/**
 * BDSoft Workspace - PROJETOS / RELATÓRIOS BI
 * Local: projetos/relatorios.php
 */
session_start();
require_once '../config.php';

if (!isset($_SESSION['usuario_id'])) { header("Location: ../login.php"); exit; }

$id_quadro = (int)$_GET['id'];
$stmt_q = $pdo->prepare("SELECT nome FROM quadros_projetos WHERE id = ?");
$stmt_q->execute([$id_quadro]);
$nome_projeto = $stmt_q->fetchColumn() ?: "Projeto";

$sql = "SELECT t.*, s.label as st_nome, s.cor as st_cor, g.nome as gr_nome 
        FROM tarefas_projetos t
        LEFT JOIN quadros_status s ON t.status_id = s.id
        LEFT JOIN projetos_grupos g ON t.grupo_id = g.id
        WHERE t.quadro_id = :qid ORDER BY t.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([':qid' => $id_quadro]);
$tarefas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$contagem = [];
foreach ($tarefas as $t) {
    $n = $t['st_nome'] ?: 'Sem Status';
    if (!isset($contagem[$n])) $contagem[$n] = ['total' => 0, 'cor' => $t['st_cor'] ?: '#ccc'];
    $contagem[$n]['total']++;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>BI - BDSoft</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background:#f4f7f6; font-family:sans-serif; }
        .card-bi { background:#fff; border-radius:20px; box-shadow:0 10px 30px rgba(0,0,0,0.05); }
        .row-hidden { display:none; }
        @media print { .no-print { display:none; } }
    </style>
</head>
<body class="p-4">

<div class="container-fluid">
    <div class="d-flex justify-content-between mb-5 no-print">
        <h3>BI: <?php echo htmlspecialchars($nome_projeto); ?></h3>
        <div>
            <button onclick="window.print()" class="btn btn-danger btn-sm rounded-pill px-4 shadow-sm">PDF</button>
            <a href="quadro.php?id=<?php echo $id_quadro; ?>" class="btn btn-outline-secondary btn-sm rounded-pill px-4">Voltar</a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-5">
            <div class="card-bi p-5 text-center">
                <h6>Status das Atividades</h6>
                <canvas id="chartBI"></canvas>
            </div>
        </div>
        <div class="col-md-7">
            <div class="card-bi p-4">
                <h6>Detalhamento</h6>
                <table class="table table-sm">
                    <thead><tr><th>Tarefa</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach($tarefas as $task): ?>
                        <tr class="t-row" data-st="<?php echo $task['st_nome']; ?>">
                            <td><?php echo htmlspecialchars($task['titulo']); ?></td>
                            <td><span class="badge" style="background:<?php echo $task['st_cor']; ?>"><?php echo $task['st_nome']; ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    const labels = <?php echo json_encode(array_keys($contagem)); ?>;
    const values = <?php echo json_encode(array_column($contagem, 'total')); ?>;
    const colors = <?php echo json_encode(array_column($contagem, 'cor')); ?>;

    const ctx = document.getElementById('chartBI').getContext('2d');
    const myChart = new Chart(ctx, {
        type: 'doughnut',
        data: { labels: labels, datasets: [{ data: values, backgroundColor: colors, borderWidth: 0 }] },
        options: {
            onClick: (e, el) => {
                if (el.length > 0) {
                    const st = labels[el[0].index];
                    document.querySelectorAll('.t-row').forEach(r => {
                        r.style.display = (r.getAttribute('data-st') === st) ? '' : 'none';
                    });
                }
            }
        }
    });
</script>
</body>
</html>