<?php
/**
 * BDSoft Workspace - Exportação Premium (PDF/Excel)
 * Localização: gestaominhaseconomias/public/exportar.php
 */
session_start();
require_once '../../config.php';

if (!isset($_SESSION['usuario_id'])) { 
    die("Acesso negado. Por favor, faça login no Workspace."); 
}

$usuario_id = $_SESSION['usuario_id'];
$mes = $_GET['mes'] ?? date('m');
$ano = $_GET['ano'] ?? date('Y');
$f_tipo = $_GET['f_tipo'] ?? '';
$f_banco = $_GET['f_banco'] ?? '';
$type = $_GET['type'] ?? 'pdf';

// Monta a URL de retorno para manter os filtros ao clicar em "Voltar"
$url_retorno = "index.php?p=transacoes&mes=$mes&ano=$ano";

// 1. BUSCA DE DADOS FILTRADOS NO BANCO
$sql = "SELECT m.*, c.nome as cat_nome, b.nome as banco_nome 
        FROM minhaseconomias_movimentacoes m 
        LEFT JOIN minhaseconomias_categorias c ON m.categoria_id = c.id 
        LEFT JOIN minhaseconomias_contas b ON m.conta_id = b.id 
        WHERE m.usuario_id = ? AND MONTH(m.data_pagamento) = ? AND YEAR(m.data_pagamento) = ?";

if(!empty($f_tipo)) $sql .= " AND m.tipo = '$f_tipo'";
if(!empty($f_banco)) $sql .= " AND m.conta_id = '$f_banco'";

$sql .= " ORDER BY m.data_pagamento ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$usuario_id, $mes, $ano]);
$dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. CÁLCULO DE TOTAIS
$total_receitas = 0;
$total_despesas = 0;
foreach($dados as $d) {
    if($d['tipo'] == 'Receita') $total_receitas += $d['valor'];
    else $total_despesas += $d['valor'];
}
$balanco = $total_receitas - $total_despesas;

$meses = ["01"=>"Janeiro","02"=>"Fevereiro","03"=>"Março","04"=>"Abril","05"=>"Maio","06"=>"Junho","07"=>"Julho","08"=>"Agosto","09"=>"Setembro","10"=>"Outubro","11"=>"Novembro","12"=>"Dezembro"];
$mes_extenso = $meses[$mes];

// ==========================================================
// EXPORTAÇÃO EXCEL (CSV)
// ==========================================================
if ($type == 'excel') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Relatorio_Economias_'.date('dmY').'.csv');
    $output = fopen('php://output', 'w');
    
    // Adiciona o BOM para o Excel reconhecer caracteres especiais (acentos)
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Cabeçalhos de Branding conforme solicitado
    fputcsv($output, ['Workspace Cloud - Minhas Economias']);
    fputcsv($output, ['Tecnologia Desenvolvida por BDSoftech']);
    fputcsv($output, ['']); // Linha em branco
    fputcsv($output, ['Relatório de Periodo:', $mes_extenso . ' / ' . $ano]);
    fputcsv($output, ['Total Receitas:', number_format($total_receitas, 2, ',', '.')]);
    fputcsv($output, ['Total Despesas:', number_format($total_despesas, 2, ',', '.')]);
    fputcsv($output, ['']); 

    // Tabela de dados
    fputcsv($output, ['Data', 'Descricao', 'Categoria', 'Banco', 'Valor', 'Tipo']);
    foreach ($dados as $row) {
        fputcsv($output, [
            date('d/m/Y', strtotime($row['data_pagamento'])), 
            $row['descricao'], 
            $row['cat_nome'], 
            $row['banco_nome'], 
            number_format($row['valor'], 2, ',', '.'), 
            $row['tipo']
        ]);
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
        body { background-color: #f8fafc; font-family: 'Inter', sans-serif; color: #1e293b; -webkit-print-color-adjust: exact; }
        .print-container { max-width: 1000px; margin: 30px auto; background: #fff; padding: 50px; border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
        
        /* Navbar de Ações */
        .action-bar { background: #1e293b; padding: 12px 0; position: sticky; top: 0; z-index: 1000; box-shadow: 0 4px 10px rgba(0,0,0,0.2); }

        /* Header do Relatório */
        .report-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #f1f5f9; padding-bottom: 30px; margin-bottom: 40px; }
        .brand-icon { background: #1a73e8; color: #fff; width: 55px; height: 55px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 26px; margin-right: 18px; box-shadow: 0 4px 12px rgba(26,115,232,0.3); }
        .brand-text h2 { margin: 0; font-weight: 800; font-size: 24px; color: #0f172a; letter-spacing: -1px; }
        .brand-text span { color: #1a73e8; }
        
        /* Cards de Resumo Modernos */
        .summary-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 40px; }
        .summary-card { padding: 25px; border-radius: 18px; border: 1px solid #f1f5f9; background: #fff; transition: 0.3s; }
        .card-label { font-size: 10px; font-weight: 800; text-transform: uppercase; color: #64748b; letter-spacing: 1px; margin-bottom: 8px; }
        .card-value { font-size: 22px; font-weight: 800; }
        
        /* Tabela Modernizada */
        .table-modern { width: 100%; border-collapse: separate; border-spacing: 0; }
        .table-modern th { background: #f8fafc; color: #475569; font-size: 11px; font-weight: 800; text-transform: uppercase; padding: 18px; border-bottom: 2px solid #f1f5f9; }
        .table-modern td { padding: 18px; border-bottom: 1px solid #f1f5f9; font-size: 14px; vertical-align: middle; }
        .type-badge { padding: 5px 12px; border-radius: 50px; font-size: 10px; font-weight: 800; text-transform: uppercase; }
        .bg-income { background: #dcfce7; color: #15803d; }
        .bg-expense { background: #fee2e2; color: #b91c1c; }
        
        /* Rodapé do Relatório */
        .report-footer { margin-top: 60px; text-align: center; border-top: 1px solid #f1f5f9; padding-top: 30px; }
        .footer-brand { font-weight: 700; color: #475569; font-size: 13px; }
        .footer-info { font-size: 11px; color: #94a3b8; margin-top: 5px; }

        @media print {
            .action-bar { display: none; }
            body { background: #fff; padding: 0; }
            .print-container { width: 100%; max-width: 100%; padding: 0; margin: 0; box-shadow: none; }
            .summary-card { border: 1px solid #eee !important; }
        }
    </style>
</head>
<body>

    <!-- BARRA DE AÇÕES (NÃO SAI NA IMPRESSÃO) -->
    <div class="action-bar no-print">
        <div class="container d-flex justify-content-center gap-3">
            <button onclick="window.location.href='<?= $url_retorno ?>'" class="btn btn-outline-light btn-sm rounded-pill px-4">
                <i class="fas fa-arrow-left me-2"></i> VOLTAR PARA TRANSAÇÕES
            </button>
            <button onclick="window.print()" class="btn btn-primary btn-sm rounded-pill px-4 fw-bold shadow">
                <i class="fas fa-print me-2"></i> IMPRIMIR RELATÓRIO
            </button>
        </div>
    </div>

    <div class="print-container">
        <!-- CABEÇALHO DO RELATÓRIO -->
        <header class="report-header">
            <div class="d-flex align-items-center">
                <div class="brand-icon">
                    <i class="fas fa-wallet"></i>
                </div>
                <div class="brand-text">
                    <h2>Workspace <span>Cloud</span></h2>
                    <small class="text-muted fw-bold">Minhas Economias - Gestão Financeira</small>
                </div>
            </div>
            <div class="text-end">
                <div class="fw-bold text-dark" style="font-size: 18px;">Relatório Mensal</div>
                <div class="text-primary fw-bold small"><?= $mes_extenso ?> de <?= $ano ?></div>
            </div>
        </header>

        <!-- CARDS DE RESUMO (KPIs) -->
        <section class="summary-grid">
            <div class="summary-card" style="border-top: 4px solid #10b981;">
                <div class="card-label">Ganhos Totais</div>
                <div class="card-value text-success">R$ <?= number_format($total_receitas, 2, ',', '.') ?></div>
            </div>
            <div class="summary-card" style="border-top: 4px solid #f43f5e;">
                <div class="card-label">Gastos Totais</div>
                <div class="card-value text-danger">R$ <?= number_format($total_despesas, 2, ',', '.') ?></div>
            </div>
            <div class="summary-card" style="border-top: 4px solid #1a73e8; background-color: #f8fafc;">
                <div class="card-label">Balanço do Mês</div>
                <div class="card-value <?= $balanco >= 0 ? 'text-primary' : 'text-danger' ?>">
                    R$ <?= number_format($balanco, 2, ',', '.') ?>
                </div>
            </div>
        </section>

        <!-- GRID DE DADOS -->
        <table class="table-modern">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Descrição</th>
                    <th>Categoria</th>
                    <th>Conta/Banco</th>
                    <th class="text-end">Valor</th>
                    <th class="text-center">Tipo</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($dados)): ?>
                    <tr>
                        <td colspan="6" class="text-center p-5 text-muted">Nenhuma movimentação registrada neste período.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach($dados as $d): ?>
                    <tr>
                        <td class="text-muted small"><?= date('d/m/Y', strtotime($d['data_pagamento'])) ?></td>
                        <td class="fw-bold text-dark"><?= htmlspecialchars($d['descricao']) ?></td>
                        <td><span class="text-muted small"><?= htmlspecialchars($d['cat_nome'] ?? 'S/ Categoria') ?></span></td>
                        <td><span class="text-muted small"><?= htmlspecialchars($d['banco_nome'] ?? 'S/ Conta') ?></span></td>
                        <td class="text-end fw-bold">
                            R$ <?= number_format($d['valor'], 2, ',', '.') ?>
                        </td>
                        <td class="text-center">
                            <span class="type-badge <?= $d['tipo'] == 'Receita' ? 'bg-income' : 'bg-expense' ?>">
                                <?= $d['tipo'] ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- RODAPÉ DE BRANDING -->
        <footer class="report-footer">
            <div class="footer-brand">Workspace Cloud - Minhas Economias</div>
            <div class="footer-info">
                Tecnologia Desenvolvida por <strong>BDSoftech</strong><br>
                Gerado em <?= date('d/m/Y \à\s H:i') ?> | Relatório de uso pessoal e empresarial
            </div>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>