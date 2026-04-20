<?php
/**
 * BDSoft Workspace - Exportação Premium Responsiva
 * Nome do Arquivo Fixo: Relatório - Workspace Cloud_Data_e_Hora_Atual
 * Localização: gestaominhaseconomias/public/exportar.php
 */
session_start();
require_once '../../config.php';

if (!isset($_SESSION['usuario_id'])) { 
    die("Acesso negado."); 
}

$usuario_id = $_SESSION['usuario_id'];

// 1. GERAÇÃO DO NOME ÚNICO DO ARQUIVO (DATA E HORA)
$data_hora_atual = date('d-m-Y_H-i');
$nome_arquivo_unico = "Relatório - Workspace Cloud_ " . $data_hora_atual;

// 2. CAPTURA INTEGRAL DOS FILTROS DA URL (GET)
$mes      = $_GET['mes'] ?? date('m');
$ano      = $_GET['ano'] ?? date('Y');
$f_banco  = $_GET['f_banco'] ?? '';
$f_cat    = $_GET['f_cat'] ?? '';
$f_status = $_GET['f_status'] ?? '';
$f_tipo   = $_GET['f_tipo'] ?? '';
$type     = $_GET['type'] ?? 'pdf';

// Monta URL de retorno preservando exatamente o que o usuário estava filtrando
$url_retorno = "index.php?p=transacoes&mes=$mes&ano=$ano&f_banco=$f_banco&f_cat=$f_cat&f_status=$f_status&f_tipo=$f_tipo";

// 3. CONSTRUÇÃO DA QUERY FIEL AO GRID DE TRANSAÇÕES
$sql = "SELECT m.*, c.nome as cat_nome, b.nome as banco_nome 
        FROM minhaseconomias_movimentacoes m 
        LEFT JOIN minhaseconomias_categorias c ON m.categoria_id = c.id 
        LEFT JOIN minhaseconomias_contas b ON m.conta_id = b.id 
        WHERE m.usuario_id = ? AND MONTH(m.data_vencimento) = ? AND YEAR(m.data_vencimento) = ?";

$params = [$usuario_id, $mes, $ano];

if(!empty($f_banco))  { $sql .= " AND m.conta_id = ?"; $params[] = $f_banco; }
if(!empty($f_cat))    { $sql .= " AND m.categoria_id = ?"; $params[] = $f_cat; }
if(!empty($f_status)) { $sql .= " AND m.status = ?"; $params[] = $f_status; }
if(!empty($f_tipo))   { $sql .= " AND m.tipo = ?"; $params[] = $f_tipo; }

$sql .= " ORDER BY m.data_vencimento ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Busca nomes para o cabeçalho informativo
$nome_banco_header = !empty($f_banco) ? $pdo->query("SELECT nome FROM minhaseconomias_contas WHERE id = $f_banco")->fetchColumn() : "Todos os Bancos";
$nome_cat_header = !empty($f_cat) ? $pdo->query("SELECT nome FROM minhaseconomias_categorias WHERE id = $f_cat")->fetchColumn() : "Todas as Categorias";

// Totais do Período Filtrado
$total_receitas = 0; $total_despesas = 0;
foreach($dados as $d) {
    if($d['tipo'] == 'Receita') $total_receitas += $d['valor'];
    else $total_despesas += $d['valor'];
}
$balanco = $total_receitas - $total_despesas;

$meses = ["01"=>"Janeiro","02"=>"Fevereiro","03"=>"Março","04"=>"Abril","05"=>"Maio","06"=>"Junho","07"=>"Julho","08"=>"Agosto","09"=>"Setembro","10"=>"Outubro","11"=>"Novembro","12"=>"Dezembro"];
$mes_extenso = $meses[$mes];

// --- 4. EXPORTAÇÃO EXCEL (CSV) COM NOME FIXO SOLICITADO ---
if ($type == 'excel') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$nome_arquivo_unico.'.csv"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM para o Excel abrir com acentos corretos
    fputcsv($output, ['Workspace Cloud - Minhas Economias']);
    fputcsv($output, ['Filtro Banco:', $nome_banco_header]);
    fputcsv($output, ['Filtro Categoria:', $nome_cat_header]);
    fputcsv($output, ['Periodo:', $mes_extenso . '/' . $ano]);
    fputcsv($output, ['Gerado em:', date('d/m/Y H:i')]);
    fputcsv($output, ['']);
    fputcsv($output, ['Data', 'Descricao', 'Categoria', 'Banco', 'Valor', 'Tipo', 'Status']);
    foreach ($dados as $row) {
        fputcsv($output, [
            date('d/m/Y', strtotime($row['data_vencimento'])), 
            $row['descricao'], 
            $row['cat_nome'], 
            $row['banco_nome'], 
            number_format($row['valor'], 2, ',', '.'), 
            $row['tipo'], 
            $row['status']
        ]);
    }
    fclose($output); exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- O Title define o nome padrão do arquivo ao salvar em PDF -->
    <title><?= $nome_arquivo_unico ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        body { background-color: #f1f5f9; font-family: 'Inter', sans-serif; color: #1e293b; }
        .print-container { width: 100%; max-width: 1100px; margin: 20px auto; background: #fff; padding: 30px; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .action-bar { background: #0f172a; padding: 12px; position: sticky; top: 0; z-index: 1000; display: flex; justify-content: center; gap: 8px; flex-wrap: wrap; }
        .report-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #f1f5f9; padding-bottom: 20px; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
        .brand-icon { background: #1a73e8; color: #fff; width: 45px; height: 45px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 22px; }
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px; }
        .summary-card { padding: 20px; border-radius: 12px; border: 1px solid #f1f5f9; background: #fff; }
        .card-label { font-size: 9px; font-weight: 800; text-transform: uppercase; color: #64748b; margin-bottom: 5px; }
        .card-value { font-size: 18px; font-weight: 800; }
        .table-modern { width: 100%; border-collapse: collapse; }
        .table-modern th { background: #f8fafc; color: #475569; font-size: 10px; font-weight: 800; text-transform: uppercase; padding: 12px; border-bottom: 2px solid #f1f5f9; }
        .table-modern td { padding: 12px; border-bottom: 1px solid #f1f5f9; font-size: 12px; vertical-align: middle; }
        .status-badge { padding: 4px 12px; border-radius: 50px; font-size: 10px; font-weight: 800; text-transform: uppercase; display: inline-block; min-width: 80px; text-align: center; }
        .status-pago { background-color: #dcfce7; color: #15803d; }
        .status-futuro { background-color: #dbeafe; color: #1d4ed8; }
        .status-atrasado { background-color: #fee2e2; color: #b91c1c; }
        @media (max-width: 768px) {
            .print-container { margin: 0; padding: 15px; border-radius: 0; }
            .col-mobile-hide { display: none; }
        }
        @media print { .no-print { display: none !important; } .print-container { box-shadow: none; margin: 0; padding: 0; width: 100%; max-width: 100%; } }
    </style>
</head>
<body>
    <div class="action-bar no-print">
        <a href="<?= $url_retorno ?>" class="btn btn-outline-light btn-sm rounded-pill px-3"><i class="fas fa-arrow-left"></i> Voltar</a>
        <button onclick="window.print()" class="btn btn-primary btn-sm rounded-pill px-4 fw-bold"><i class="fas fa-file-pdf me-1"></i> PDF / IMPRIMIR</button>
        <a href="exportar.php?type=excel&<?= http_build_query($_GET) ?>" class="btn btn-success btn-sm rounded-pill px-4 fw-bold"><i class="fas fa-file-excel me-1"></i> EXCEL</a>
        <button onclick="compartilharRelatorio()" class="btn btn-whatsapp btn-sm rounded-pill px-4 fw-bold text-white" style="background-color: #25d366; border:none;"><i class="fab fa-whatsapp me-1"></i> WHATSAPP</button>
    </div>

    <div class="print-container">
        <header class="report-header">
            <div class="d-flex align-items-center">
                <div class="brand-icon me-3"><i class="fas fa-wallet"></i></div>
                <div>
                    <h2 class="m-0 fw-800" style="font-size: 20px; letter-spacing: -1px;">Workspace <span class="text-primary">Cloud</span></h2>
                    <small class="text-muted">Minhas Economias - Relatório de Transações</small>
                </div>
            </div>
            <div class="text-md-end text-uppercase" style="font-size: 10px; letter-spacing: 0.5px;">
                <div class="fw-bold">Banco: <span class="text-primary"><?= $nome_banco_header ?></span></div>
                <div class="fw-bold">Categoria: <span class="text-primary"><?= $nome_cat_header ?></span></div>
                <div class="text-muted">Referência: <strong><?= $mes_extenso ?> / <?= $ano ?></strong></div>
            </div>
        </header>

        <section class="summary-grid">
            <div class="summary-card" style="border-left: 4px solid #10b981;">
                <div class="card-label">Entradas (Filtro)</div>
                <div class="card-value text-success">R$ <?= number_format($total_receitas, 2, ',', '.') ?></div>
            </div>
            <div class="summary-card" style="border-left: 4px solid #f43f5e;">
                <div class="card-label">Saídas (Filtro)</div>
                <div class="card-value text-danger">R$ <?= number_format($total_despesas, 2, ',', '.') ?></div>
            </div>
            <div class="summary-card" style="border-left: 4px solid #1a73e8;">
                <div class="card-label">Saldo do Filtro</div>
                <div class="card-value <?= $balanco >= 0 ? 'text-primary' : 'text-danger' ?>">R$ <?= number_format($balanco, 2, ',', '.') ?></div>
            </div>
        </section>

        <div class="table-responsive">
            <table class="table-modern">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Descrição</th>
                        <th class="col-mobile-hide">Categoria</th>
                        <th class="col-mobile-hide">Banco</th>
                        <th class="text-end">Valor</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($dados)): ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted small">Nenhum dado encontrado para os filtros aplicados.</td></tr>
                    <?php endif; ?>
                    <?php foreach($dados as $d): ?>
                    <tr>
                        <td class="text-muted" style="white-space: nowrap;"><?= date('d/m/y', strtotime($d['data_vencimento'])) ?></td>
                        <td class="fw-bold"><?= htmlspecialchars($d['descricao']) ?></td>
                        <td class="col-mobile-hide text-muted small"><?= htmlspecialchars($d['cat_nome'] ?? 'Geral') ?></td>
                        <td class="col-mobile-hide text-muted small"><?= htmlspecialchars($d['banco_nome'] ?? '-') ?></td>
                        <td class="text-end fw-bold <?= $d['tipo']=='Receita'?'text-success':'text-danger' ?>">
                            R$ <?= number_format($d['valor'], 2, ',', '.') ?>
                        </td>
                        <td class="text-center">
                            <?php $slug = strtolower($d['status']); ?>
                            <span class="status-badge status-<?= $slug ?>"><?= $d['status'] ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <footer class="text-center mt-5 pt-4 border-top">
            <div class="fw-bold small text-muted"><?= $nome_arquivo_unico ?></div>
            <div class="small text-muted" style="font-size: 10px;">
                Filtros: Banco [<?= $nome_banco_header ?>] | Cat [<?= $nome_cat_header ?>] | Status [<?= $f_status ?: 'Todos' ?>]
            </div>
            <div class="small text-muted mt-1" style="font-size: 9px;">Desenvolvido por BDSoftech &copy; <?= date('Y') ?></div>
        </footer>
    </div>

    <script>
        async function compartilharRelatorio() {
            const titulo = "<?= $nome_arquivo_unico ?>";
            const texto = `*Relatório Financeiro*\n🏦 Banco: <?= $nome_banco_header ?>\n💰 Balanço Filtro: R$ <?= number_format($balanco, 2, ',', '.') ?>\n\nConfira o arquivo completo:`;
            
            if (navigator.share) {
                try {
                    await navigator.share({ title: titulo, text: texto, url: window.location.href });
                } catch (err) { console.log("Erro ao compartilhar."); }
            } else {
                window.open(`https://api.whatsapp.com/send?text=${encodeURIComponent(texto + "\n" + window.location.href)}`, '_blank');
            }
        }
    </script>
</body>
</html>