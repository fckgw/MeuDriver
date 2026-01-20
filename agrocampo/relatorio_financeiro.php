<?php
/**
 * BDSoft Workspace - AGRO CAMPO (RELATÓRIO BI FINANCEIRO)
 * Localização: public_html/agrocampo/relatorio_financeiro.php
 */

session_start();
require_once '../config.php';

// 1. Verificação de Segurança
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit;
}

$usuario_id_logado = $_SESSION['usuario_id'];
$usuario_nivel    = $_SESSION['usuario_nivel'];

// 2. Verificação de Permissão Específica para o Submódulo Financeiro
// Admins acessam tudo, usuários comuns dependem da tabela de permissões
if ($usuario_nivel !== 'admin') {
    $stmt_permissao = $pdo->prepare("SELECT 1 FROM usuarios_agro_permissões up 
                                     INNER JOIN agro_submodulos s ON up.submodulo_id = s.id 
                                     WHERE up.usuario_id = ? AND s.slug = 'financeiro.php'");
    $stmt_permissao->execute([$usuario_id_logado]);
    if (!$stmt_permissao->fetch()) {
        die("<div style='text-align:center; padding:50px; font-family:sans-serif;'><h2>🔒 Acesso Restrito</h2><p>Você não possui permissão para acessar os relatórios financeiros.</p><a href='index.php'>Voltar ao Painel</a></div>");
    }
}

// 3. Captura de Filtros de Pesquisa
$data_inicio    = isset($_GET['d_inicio']) && !empty($_GET['d_inicio']) ? $_GET['d_inicio'] : date('Y-m-01');
$data_fim       = isset($_GET['d_fim']) && !empty($_GET['d_fim']) ? $_GET['d_fim'] : date('Y-m-t');
$filtro_forn    = isset($_GET['f_fornecedor']) ? trim($_GET['f_fornecedor']) : '';
$filtro_metodo  = isset($_GET['f_metodo']) ? $_GET['f_metodo'] : '';

// 4. Construção da Consulta SQL Dinâmica
$sql_relatorio = "SELECT * FROM agro_financeiro 
                  WHERE usuario_id = :uid 
                  AND data_vencimento BETWEEN :inicio AND :fim";

$parametros_sql = [
    ':uid'    => $usuario_id_logado,
    ':inicio' => $data_inicio,
    ':fim'    => $data_fim
];

if (!empty($filtro_forn)) {
    $sql_relatorio .= " AND fornecedor LIKE :fornecedor";
    $parametros_sql[':fornecedor'] = "%$filtro_forn%";
}

if (!empty($filtro_metodo)) {
    $sql_relatorio .= " AND metodo_pagamento = :metodo";
    $parametros_sql[':metodo'] = $filtro_metodo;
}

$sql_relatorio .= " ORDER BY data_vencimento ASC";

$stmt_dados = $pdo->prepare($sql_relatorio);
$stmt_dados->execute($parametros_sql);
$lista_resultados = $stmt_dados->fetchAll(PDO::FETCH_ASSOC);

// 5. Cálculos de Totais para o BI
$total_entradas_valor = 0;
$total_saidas_valor   = 0;

foreach ($lista_resultados as $item) {
    if ($item['tipo'] === 'Entrada') {
        $total_entradas_valor += $item['valor'];
    } else {
        $total_saidas_valor += $item['valor'];
    }
}

$saldo_periodo = $total_entradas_valor - $total_saidas_valor;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios BI - AgroCampo</title>
    
    <!-- CSS: Bootstrap 5 e FontAwesome 6 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Estilos para a versão de impressão (PDF) */
        @media print {
            .no-print { display: none !important; }
            .main-wrapper { margin-left: 0 !important; padding: 0 !important; width: 100% !important; }
            body { background-color: #ffffff !important; }
            .card-report { box-shadow: none !important; border: 1px solid #eee !important; }
        }
        
        .card-report { background: #ffffff; border: none; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .resumo-box { padding: 20px; border-radius: 12px; background-color: #f8f9fa; border: 1px solid #eee; }
    </style>
</head>
<body>

<!-- INCLUSÃO DO MENU LATERAL -->
<?php include 'sidebar_agro.php'; ?>

<!-- CONTEÚDO PRINCIPAL (COM WRAPPER PARA EVITAR SOBREPOSIÇÃO) -->
<div class="main-wrapper">
    
    <!-- CABEÇALHO DA PÁGINA -->
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <div>
            <h2 class="fw-bold text-dark mb-0">Relatórios & BI Financeiro</h2>
            <p class="text-muted">Análise de movimentações, fornecedores e métodos de pagamento.</p>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-danger rounded-pill px-4 fw-bold shadow-sm">
                <i class="fas fa-file-pdf me-2"></i>EXPORTAR RELATÓRIO
            </button>
            <a href="financeiro.php" class="btn btn-light border rounded-pill px-4 fw-bold">VOLTAR</a>
        </div>
    </div>

    <!-- FORMULÁRIO DE FILTROS -->
    <div class="card card-report p-4 mb-4 no-print">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="small fw-bold text-muted mb-1">DATA INICIAL</label>
                <input type="date" name="d_inicio" class="form-control border-0 bg-light" value="<?php echo $data_inicio; ?>">
            </div>
            <div class="col-md-3">
                <label class="small fw-bold text-muted mb-1">DATA FINAL</label>
                <input type="date" name="d_fim" class="form-control border-0 bg-light" value="<?php echo $data_fim; ?>">
            </div>
            <div class="col-md-3">
                <label class="small fw-bold text-muted mb-1">FORNECEDOR / CLIENTE</label>
                <input type="text" name="f_fornecedor" class="form-control border-0 bg-light" placeholder="Todos" value="<?php echo htmlspecialchars($filtro_forn); ?>">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-dark w-100 rounded-pill fw-bold py-2 shadow-sm">
                    <i class="fas fa-filter me-2"></i>FILTRAR DADOS
                </button>
            </div>
        </form>
    </div>

    <!-- RESULTADO DO RELATÓRIO / BI -->
    <div class="card card-report p-5">
        <div class="text-center mb-5">
            <h3 class="fw-bold mb-1">Resumo Financeiro AgroCampo</h3>
            <p class="text-muted">Período de <?php echo date('d/m/Y', strtotime($data_inicio)); ?> até <?php echo date('d/m/Y', strtotime($data_fim)); ?></p>
        </div>

        <!-- BOXES DE TOTAIS -->
        <div class="row g-4 mb-5 text-center">
            <div class="col-md-4">
                <div class="resumo-box">
                    <small class="text-muted fw-bold d-block mb-1">TOTAL DE ENTRADAS</small>
                    <h3 class="text-success fw-bold">R$ <?php echo number_format($total_entradas_valor, 2, ',', '.'); ?></h3>
                </div>
            </div>
            <div class="col-md-4">
                <div class="resumo-box">
                    <small class="text-muted fw-bold d-block mb-1">TOTAL DE SAÍDAS</small>
                    <h3 class="text-danger fw-bold">R$ <?php echo number_format($total_saidas_valor, 2, ',', '.'); ?></h3>
                </div>
            </div>
            <div class="col-md-4">
                <div class="resumo-box bg-dark text-white">
                    <small class="opacity-75 fw-bold d-block mb-1">SALDO DO PERÍODO</small>
                    <h3 class="fw-bold mb-0">R$ <?php echo number_format($saldo_periodo, 2, ',', '.'); ?></h3>
                </div>
            </div>
        </div>

        <!-- TABELA DETALHADA -->
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr class="small text-muted">
                        <th>VENCIMENTO</th>
                        <th>FORNECEDOR</th>
                        <th>DESCRIÇÃO</th>
                        <th>MÉTODO</th>
                        <th>VALOR</th>
                        <th>STATUS</th>
                    </tr>
                </thead>
                <tbody style="font-size: 14px;">
                    <?php if (empty($lista_resultados)): ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted">Nenhum registro encontrado para o filtro selecionado.</td></tr>
                    <?php else: ?>
                        <?php foreach($lista_resultados as $row): ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($row['data_vencimento'])); ?></td>
                            <td class="fw-bold"><?php echo htmlspecialchars($row['fornecedor']); ?></td>
                            <td class="text-muted"><?php echo htmlspecialchars($row['descricao']); ?></td>
                            <td><span class="badge bg-light text-dark border"><?php echo $row['metodo_pagamento']; ?></span></td>
                            <td class="fw-bold <?php echo ($row['tipo'] === 'Entrada') ? 'text-success' : 'text-danger'; ?>">
                                R$ <?php echo number_format($row['valor'], 2, ',', '.'); ?>
                            </td>
                            <td>
                                <span class="badge rounded-pill <?php echo ($row['status'] === 'Pago') ? 'bg-success' : 'bg-warning text-dark'; ?>">
                                    <?php echo $row['status']; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="text-center mt-5 opacity-50 small no-print">
            <hr>
            Relatório gerado em <?php echo date('d/m/Y H:i'); ?> pelo BDSoft Workspace.
        </div>
    </div>
</div>

<!-- SCRIPTS: Bootstrap Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>