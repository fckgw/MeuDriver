<?php
/**
 * BDSoft Workspace - Exportação Premium Responsiva
 * Localização: gestaominhaseconomias/public/exportar.php
 */
session_start();
require_once '../../config.php';

if (!isset($_SESSION['usuario_id'])) { 
    die("Acesso negado. Por favor, faça login."); 
}

$usuario_id = $_SESSION['usuario_id'];
$mes = $_GET['mes'] ?? date('m');
$ano = $_GET['ano'] ?? date('Y');
$f_tipo = $_GET['f_tipo'] ?? '';
$f_banco = $_GET['f_banco'] ?? '';
$type = $_GET['type'] ?? 'pdf';

$url_retorno = "index.php?p=transacoes&mes=$mes&ano=$ano";

// 1. BUSCA DE DADOS
$sql = "SELECT m.*, c.nome as cat_nome, b.nome as banco_nome 
        FROM minhaseconomias_movimentacoes m 
        LEFT JOIN minhaseconomias_categorias c ON m.categoria_id = c.id 
        LEFT JOIN minhaseconomias_contas b ON m.conta_id = b.id 
        WHERE m.usuario_id = ? AND MONTH(m.data_vencimento) = ? AND YEAR(m.data_vencimento) = ?";

if(!empty($f_tipo)) $sql .= " AND m.tipo = '$f_tipo'";
if(!empty($f_banco)) $sql .= " AND m.conta_id = '$f_banco'";

$sql .= " ORDER BY m.data_vencimento ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$usuario_id, $mes, $ano]);
$dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_receitas = 0; $total_despesas = 0;
foreach($dados as $d) {
    if($d['tipo'] == 'Receita') $total_receitas += $d['valor'];
    else $total_despesas += $d['valor'];
}
$balanco = $total_receitas - $total_despesas;

$meses = ["01"=>"Janeiro","02"=>"Fevereiro","03"=>"Março","04"=>"Abril","05"=>"Maio","06"=>"Junho","07"=>"Julho","08"=>"Agosto","09"=>"Setembro","10"=>"Outubro","11"=>"Novembro","12"=>"Dezembro"];
$mes_extenso = $meses[$mes];

// EXPORTAÇÃO EXCEL (CSV)
if ($type == 'excel') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Relatorio_BDS_'.date('dmY').'.csv');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($output, ['Workspace Cloud - Minhas Economias']);
    fputcsv($output, ['Tecnologia Desenvolvida por BDSoftech']);
    fputcsv($output, ['']);
    fputcsv($output, ['Data', 'Descricao', 'Categoria', 'Banco', 'Valor', 'Tipo']);
    foreach ($dados as $row) {
        fputcsv($output, [date('d/m/Y', strtotime($row['data_vencimento'])), $row['descricao'], $row['cat_nome'], $row['banco_nome'], number_format($row['valor'], 2, ',', '.'), $row['tipo']]);
    }
    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório - Workspace Cloud</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        body { background-color: #f1f5f9; font-family: 'Inter', sans-serif; color: #1e293b; margin: 0; padding: 0; }
        .print-container { width: 100%; max-width: 1000px; margin: 20px auto; background: #fff; padding: 30px; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .action-bar { background: #0f172a; padding: 10px; position: sticky; top: 0; z-index: 1000; }
        
        .report-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #f1f5f9; padding-bottom: 20px; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
        .brand-icon { background: #1a73e8; color: #fff; width: 45px; height: 45px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 22px; margin-right: 12px; }
        .brand-text h2 { margin: 0; font-weight: 800; font-size: 20px; letter-spacing: -1px; }
        
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px; }
        .summary-card { padding: 20px; border-radius: 12px; border: 1px solid #f1f5f9; background: #fff; }
        .card-label { font-size: 9px; font-weight: 800; text-transform: uppercase; color: #64748b; margin-bottom: 5px; }
        .card-value { font-size: 18px; font-weight: 800; }

        .table-modern { width: 100%; border-collapse: collapse; }
        .table-modern th { background: #f8fafc; color: #475569; font-size: 10px; font-weight: 800; text-transform: uppercase; padding: 12px; border-bottom: 2px solid #f1f5f9; }
        .table-modern td { padding: 12px; border-bottom: 1px solid #f1f5f9; font-size: 12px; }
        .type-badge { padding: 3px 10px; border-radius: 50px; font-size: 9px; font-weight: 800; text-transform: uppercase; }

        @media (max-width: 768px) {
            .print-container { margin: 0; padding: 15px; border-radius: 0; }
            .brand-text h2 { font-size: 18px; }
            .card-value { font-size: 16px; }
            .table-modern th, .table-modern td { padding: 8px; font-size: 11px; }
        }

        @media print {
            .no-print { display: none; }
            body { background: #fff; }
            .print-container { box-shadow: none; margin: 0; padding: 0; width: 100%; }
        }
    </style>
</head>
<body>
    <div class="action-bar no-print text-center">
        <a href="<?= $url_retorno ?>" class="btn btn-outline-light btn-sm rounded-pill px-4 me-2"><i class="fas fa-arrow-left me-1"></i> Voltar</a>
        <button onclick="window.print()" class="btn btn-primary btn-sm rounded-pill px-4 fw-bold"><i class="fas fa-print me-1"></i> Imprimir</button>
    </div>

    <div class="print-container">
        <header class="report-header">
            <div class="d-flex align-items-center">
                <div class="brand-icon"><i class="fas fa-wallet"></i></div>
                <div class="brand-text"><h2>Workspace <span class="text-primary">Cloud</span></h2><small class="text-muted">Minhas Economias</small></div>
            </div>
            <div class="text-md-end">
                <div class="fw-bold small text-uppercase">Relatório de Movimentação</div>
                <div class="text-primary fw-bold"><?= $mes_extenso ?> / <?= $ano ?></div>
            </div>
        </header>

        <section class="summary-grid">
            <div class="summary-card" style="border-left: 4px solid #10b981;">
                <div class="card-label">Entradas</div>
                <div class="card-value text-success">R$ <?= number_format($total_receitas, 2, ',', '.') ?></div>
            </div>
            <div class="summary-card" style="border-left: 4px solid #f43f5e;">
                <div class="card-label">Saídas</div>
                <div class="card-value text-danger">R$ <?= number_format($total_despesas, 2, ',', '.') ?></div>
            </div>
            <div class="summary-card" style="border-left: 4px solid #1a73e8;">
                <div class="card-label">Balanço</div>
                <div class="card-value <?= $balanco >= 0 ? 'text-primary' : 'text-danger' ?>">R$ <?= number_format($balanco, 2, ',', '.') ?></div>
            </div>
        </section>

        <div class="table-responsive">
            <table class="table-modern">
                <thead>
                    <tr><th>Data</th><th>Descrição</th><th class="text-end">Valor</th><th class="text-center">Tipo</th></tr>
                </thead>
                <tbody>
                    <?php foreach($dados as $d): ?>
                    <tr>
                        <td class="text-muted"><?= date('d/m/y', strtotime($d['data_vencimento'])) ?></td>
                        <td class="fw-bold"><?= htmlspecialchars($d['descricao']) ?></td>
                        <td class="text-end fw-bold">R$ <?= number_format($d['valor'], 2, ',', '.') ?></td>
                        <td class="text-center"><span class="type-badge <?= $d['tipo']=='Receita'?'bg-success text-white':'bg-danger text-white' ?>"><?= $d['tipo'] ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <footer class="text-center mt-5 pt-4 border-top">
            <div class="fw-bold small text-muted">Workspace Cloud - Minhas Economias</div>
            <div class="small text-muted" style="font-size: 10px;">Tecnologia Desenvolvida por <strong>BDSoftech</strong> | <?= date('d/m/Y H:i') ?></div>
        </footer>
    </div>
</body>
</html>