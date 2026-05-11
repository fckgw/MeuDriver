<?php
/**
 * BDSoft Workspace - AGRO FINANCEIRO (PÁGINA PRINCIPAL)
 * Localização: agrocampo/financeiro.php
 */

// Garante que o navegador não carregue versão antiga do arquivo
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

session_start();
require_once '../config.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['usuario_id'];
$data_hoje_referencia = date('Y-m-d');

/**
 * --- CONFIGURAÇÃO DE FILTROS ---
 */
$filtro_busca  = $_GET['f_busca'] ?? '';
$filtro_status = $_GET['f_status'] ?? '';
// Padrão do filtro: Início do mês passado até o fim do mês atual para garantir que o GRID não venha vazio
$filtro_inicio = $_GET['f_inicio'] ?? date('Y-m-01', strtotime("-1 month"));
$filtro_fim    = $_GET['f_fim'] ?? date('Y-m-t');

/**
 * --- BLOCO GATILHO: SCANNER DE PROVISÕES (7 DIAS ANTES) ---
 * Este bloco move parcelas de provisão para o financeiro oficial automaticamente.
 */
try {
    $stmt_scanner = $pdo->prepare("SELECT parcelas.*, provisao.nome_provisao, provisao.tipo as fluxo_tipo
        FROM agro_provisoes_parcelas AS parcelas
        INNER JOIN agro_provisoes AS provisao ON parcelas.provisao_id = provisao.id 
        WHERE provisao.usuario_id = ? AND parcelas.status = 'Pendente' 
        AND parcelas.data_vencimento <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)");
    $stmt_scanner->execute([$user_id]);
    $parcelas_para_processar = $stmt_scanner->fetchAll(PDO::FETCH_ASSOC);

    foreach ($parcelas_para_processar as $parcela_alvo) {
        $descricao_oficial = "PROVISÃO: " . $parcela_alvo['nome_provisao'] . " (Parcela " . $parcela_alvo['parcela_numero'] . ")";
        $tipo_fluxo_final = $parcela_alvo['fluxo_tipo']; // Entrada ou Saida

        $comando_oficial = $pdo->prepare("INSERT INTO agro_financeiro 
            (tipo, descricao, fornecedor, valor, categoria, data_vencimento, status, metodo_pagamento, usuario_id, provisao_parcela_id) 
            VALUES (?, ?, 'Provisionamento', ?, 'Parcelamento', ?, 'Pendente', 'Boleto', ?, ?)");
        
        $comando_oficial->execute([
            $tipo_fluxo_final, 
            $descricao_oficial, 
            $parcela_alvo['valor_parcela'], 
            $parcela_alvo['data_vencimento'], 
            $user_id, 
            $parcela_alvo['id']
        ]);
        
        // Atualiza a parcela para não duplicar no financeiro
        $pdo->prepare("UPDATE agro_provisoes_parcelas SET status = 'Gerado' WHERE id = ?")->execute([$parcela_alvo['id']]);
    }
} catch (Exception $erro_scanner) {
    error_log("Erro no scanner de provisões: " . $erro_scanner->getMessage());
}

/**
 * --- BUSCA DE DADOS PARA O GRID ---
 */
// 1. Itens Temporários (Vindos do XML)
$stmt_temporarios = $pdo->prepare("SELECT * FROM agro_financeiro_temp WHERE usuario_id = ? ORDER BY id ASC");
$stmt_temporarios->execute([$user_id]);
$itens_temporarios = $stmt_temporarios->fetchAll(PDO::FETCH_ASSOC);

// 2. Itens Oficiais (GRID Principal)
$sql_principal = "SELECT * FROM agro_financeiro WHERE usuario_id = ? AND data_vencimento BETWEEN ? AND ?";
$parametros_sql = [$user_id, $filtro_inicio, $filtro_fim];

if (!empty($filtro_busca)) {
    $sql_principal .= " AND (descricao LIKE ? OR fornecedor LIKE ?)";
    $parametros_sql[] = "%$filtro_busca%";
    $parametros_sql[] = "%$filtro_busca%";
}

// Lógica para os 4 Status no Filtro
if (!empty($filtro_status)) {
    if ($filtro_status == 'Atrasado') {
        $sql_principal .= " AND status != 'Pago' AND data_vencimento < ?";
        $parametros_sql[] = $data_hoje_referencia;
    } elseif ($filtro_status == 'Futuro') {
        $sql_principal .= " AND status != 'Pago' AND data_vencimento > ?";
        $parametros_sql[] = $data_hoje_referencia;
    } elseif ($filtro_status == 'Pendente') {
        $sql_principal .= " AND status = 'Pendente' AND data_vencimento = ?";
        $parametros_sql[] = $data_hoje_referencia;
    } else {
        $sql_principal .= " AND status = ?";
        $parametros_sql[] = $filtro_status;
    }
}

$sql_principal .= " ORDER BY data_vencimento ASC";
$stmt_oficial = $pdo->prepare($sql_principal);
$stmt_oficial->execute($parametros_sql);
$registros_oficiais = $stmt_oficial->fetchAll(PDO::FETCH_ASSOC);

/**
 * --- FUNÇÕES DE INTERFACE ---
 */
function formatarReal($valor) {
    return "R$ " . number_format($valor, 2, ',', '.');
}

function renderizarBadgeStatus($status_banco, $vencimento, $hoje) {
    if ($status_banco == 'Pago') {
        return '<span class="badge bg-success rounded-pill px-3 py-2">PAGO</span>';
    }
    if ($vencimento < $hoje) {
        return '<span class="badge bg-danger rounded-pill px-3 py-2">ATRASADO</span>';
    }
    if ($vencimento == $hoje) {
        return '<span class="badge bg-warning text-dark rounded-pill px-3 py-2">PENDENTE</span>';
    }
    return '<span class="badge bg-info text-white rounded-pill px-3 py-2">FUTURO</span>';
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financeiro - AgroCampo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f7f4; display: flex; min-height: 100vh; font-family: 'Segoe UI', sans-serif; margin: 0; }
        .main-wrapper { flex: 1; margin-left: 280px; padding: 40px; width: calc(100% - 280px); transition: 0.3s; }
        .card-agro { border: none; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); background: #fff; margin-bottom: 20px; }
        .temp-section { border: 2px dashed #0d6efd; background: #f0f7ff; padding: 25px; border-radius: 15px; margin-bottom: 30px; }
        @media (max-width: 991px) { .main-wrapper { margin-left: 0; width: 100%; padding: 20px; } }
    </style>
</head>
<body>

<?php include 'sidebar_agro.php'; ?>

<div class="main-wrapper">
    
    <!-- CABEÇALHO DE AÇÕES -->
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h2 class="fw-bold text-dark mb-0">Gestão Financeira Profissional</h2>
            <p class="text-muted">Fluxo de caixa, importações XML e provisões.</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-dark rounded-pill px-4 fw-bold shadow" data-bs-toggle="modal" data-bs-target="#modalXML">
                <i class="fas fa-qrcode me-2"></i>LER XML COMEVAP
            </button>
            <button class="btn btn-success rounded-pill px-4 fw-bold shadow" data-bs-toggle="modal" data-bs-target="#modalNovoLancamento">
                <i class="fas fa-plus me-2"></i>NOVA CONTA
            </button>
        </div>
    </div>

    <!-- CONFERÊNCIA DE IMPORTAÇÃO (TABELA TEMPORÁRIA) -->
    <?php if (!empty($itens_temporarios)): ?>
    <div class="temp-section shadow-sm">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="fw-bold text-primary mb-0"><i class="fas fa-tasks me-2"></i>Conferência de Itens do XML</h5>
            <a href="acoes.php?acao=limpar_temp" class="btn btn-outline-danger btn-sm rounded-pill" onclick="return confirm('Deseja descartar estes itens?')">DESCARTAR TUDO</a>
        </div>
        <form action="acoes.php" method="POST">
            <input type="hidden" name="acao" value="finalizar_importacao">
            <div class="table-responsive bg-white rounded-3 shadow-sm p-2">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-uppercase">
                        <tr>
                            <th width="40"><input type="checkbox" id="selecionarTodosTemp" checked></th>
                            <th>Descrição</th>
                            <th>Fornecedor</th>
                            <th>Valor (R$)</th>
                            <th>Vencimento</th>
                            <th>Lançar como</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($itens_temporarios as $item_t): ?>
                        <tr>
                            <td><input type="checkbox" name="selecionados[]" value="<?php echo $item_t['id']; ?>" class="checkItemTemp" checked></td>
                            <td><input type="text" name="desc_<?php echo $item_t['id']; ?>" class="form-control form-control-sm" value="<?php echo htmlspecialchars($item_t['descricao']); ?>"></td>
                            <td><input type="text" name="forn_<?php echo $item_t['id']; ?>" class="form-control form-control-sm" value="<?php echo htmlspecialchars($item_t['fornecedor']); ?>"></td>
                            <td><input type="text" name="valor_<?php echo $item_t['id']; ?>" class="form-control form-control-sm" value="<?php echo number_format($item_t['valor'], 2, ',', ''); ?>"></td>
                            <td><input type="date" name="data_<?php echo $item_t['id']; ?>" class="form-control form-control-sm" value="<?php echo $item_t['data_vencimento']; ?>"></td>
                            <td>
                                <select name="status_<?php echo $item_t['id']; ?>" class="form-select form-select-sm">
                                    <option value="Pago" selected>Pago</option>
                                    <option value="Pendente">Pendente</option>
                                </select>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="text-end mt-4">
                <button type="submit" class="btn btn-primary fw-bold rounded-pill px-5 shadow">EFETIVAR LANÇAMENTOS SELECIONADOS</button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- BARRA DE FILTROS -->
    <div class="card card-agro p-3 mb-4">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="small fw-bold text-muted text-uppercase">Busca Livre</label>
                <input type="text" name="f_busca" class="form-control" placeholder="Item ou Fornecedor" value="<?php echo htmlspecialchars($filtro_busca); ?>">
            </div>
            <div class="col-md-2">
                <label class="small fw-bold text-muted text-uppercase">Status</label>
                <select name="f_status" class="form-select">
                    <option value="">Todos</option>
                    <option value="Pendente" <?php echo $filtro_status == 'Pendente' ? 'selected' : ''; ?>>Pendente (Hoje)</option>
                    <option value="Pago" <?php echo $filtro_status == 'Pago' ? 'selected' : ''; ?>>Pago</option>
                    <option value="Atrasado" <?php echo $filtro_status == 'Atrasado' ? 'selected' : ''; ?>>Atrasado</option>
                    <option value="Futuro" <?php echo $filtro_status == 'Futuro' ? 'selected' : ''; ?>>Futuro</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="small fw-bold text-muted text-uppercase">Data Início</label>
                <input type="date" name="f_inicio" class="form-control" value="<?php echo $filtro_inicio; ?>">
            </div>
            <div class="col-md-2">
                <label class="small fw-bold text-muted text-uppercase">Data Fim</label>
                <input type="date" name="f_fim" class="form-control" value="<?php echo $filtro_fim; ?>">
            </div>
            <div class="col-md-3 d-flex gap-1">
                <button type="submit" class="btn btn-primary w-100 fw-bold">FILTRAR</button>
                <a href="financeiro.php" class="btn btn-outline-secondary" title="Resetar Filtros"><i class="fas fa-eraser"></i></a>
            </div>
        </form>
    </div>

    <!-- GRID OFICIAL (O PRESENTE) -->
    <div class="card card-agro overflow-hidden shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-uppercase small fw-bold">
                    <tr>
                        <th class="ps-4">Vencimento</th>
                        <th>Fornecedor / Descrição</th>
                        <th>Valor (R$)</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($registros_oficiais)): ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted">Nenhum lançamento encontrado para este filtro.</td></tr>
                    <?php else: ?>
                        <?php foreach($registros_oficiais as $conta): 
                            $atrasada = ($conta['status'] != 'Pago' && $conta['data_vencimento'] < $data_hoje_referencia);
                        ?>
                        <tr class="<?php echo $atrasada ? 'table-danger' : ''; ?>">
                            <td class="ps-4 fw-bold">
                                <?php echo date('d/m/Y', strtotime($conta['data_vencimento'])); ?>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($conta['fornecedor'] ?: 'DIVERSOS'); ?></div>
                                <div class="text-muted small"><?php echo htmlspecialchars($conta['descricao']); ?></div>
                            </td>
                            <td class="fw-bold <?php echo $conta['tipo'] == 'Entrada' ? 'text-success' : 'text-danger'; ?>">
                                <?php echo ($conta['tipo'] == 'Entrada' ? '+ ' : '- ') . number_format($conta['valor'], 2, ',', '.'); ?>
                            </td>
                            <td class="text-center">
                                <?php echo renderizarBadgeStatus($conta['status'], $conta['data_vencimento'], $data_hoje_referencia); ?>
                            </td>
                            <td class="text-center">
                                <button class="btn btn-sm text-primary border-0 bg-transparent" onclick='abrirModalEditar(<?php echo json_encode($conta); ?>)'>
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a href="acoes.php?del_fin=<?php echo $conta['id']; ?>" class="btn btn-sm text-danger ms-2" onclick="return confirm('Excluir este lançamento?')">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL: NOVO LANÇAMENTO -->
<div class="modal fade" id="modalNovoLancamento" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form action="acoes.php" method="POST" class="modal-content border-0 shadow-lg" style="border-radius:20px;">
            <div class="modal-header bg-success text-white p-4 border-0">
                <h5 class="fw-bold mb-0">Novo Registro Manual</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="acao" value="novo_fin">
                <div class="row mb-3">
                    <div class="col-6">
                        <label class="small fw-bold">TIPO</label>
                        <select name="tipo" class="form-select">
                            <option value="Saida">Saída (Despesa)</option>
                            <option value="Entrada">Entrada (Receita)</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="small fw-bold">VALOR (R$)</label>
                        <input type="text" id="mask_valor_novo" name="valor" class="form-control" placeholder="0,00" required>
                    </div>
                </div>
                <div class="mb-3"><label class="small fw-bold">FORNECEDOR</label><input type="text" name="fornecedor" class="form-control"></div>
                <div class="mb-3"><label class="small fw-bold">DESCRIÇÃO</label><input type="text" name="descricao" class="form-control" required></div>
                <div class="row">
                    <div class="col-6"><label class="small fw-bold">VENCIMENTO</label><input type="date" name="data_vencimento" class="form-control" value="<?php echo $data_hoje_referencia; ?>" required></div>
                    <div class="col-6">
                        <label class="small fw-bold">STATUS</label>
                        <select name="status" class="form-select">
                            <option value="Pendente">Pendente</option>
                            <option value="Pago">Pago</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="submit" class="btn btn-success w-100 rounded-pill py-2 fw-bold shadow-sm">SALVAR LANÇAMENTO</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: EDITAR LANÇAMENTO -->
<div class="modal fade" id="modalEditarLancamento" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form action="acoes.php" method="POST" class="modal-content border-0 shadow-lg" style="border-radius:20px;">
            <div class="modal-header bg-primary text-white p-4 border-0">
                <h5 class="fw-bold mb-0">Editar Lançamento Oficial</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="acao" value="editar_fin">
                <input type="hidden" name="id_registro" id="edit_id">
                <div class="row mb-3">
                    <div class="col-6">
                        <label class="small fw-bold">TIPO</label>
                        <select name="tipo" id="edit_tipo" class="form-select">
                            <option value="Saida">Saída</option>
                            <option value="Entrada">Entrada</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="small fw-bold">VALOR (R$)</label>
                        <input type="text" id="edit_valor" name="valor" class="form-control" required>
                    </div>
                </div>
                <div class="mb-3"><label class="small fw-bold">FORNECEDOR</label><input type="text" name="fornecedor" id="edit_fornecedor" class="form-control"></div>
                <div class="mb-3"><label class="small fw-bold">DESCRIÇÃO</label><input type="text" name="descricao" id="edit_descricao" class="form-control" required></div>
                <div class="row">
                    <div class="col-6"><label class="small fw-bold">VENCIMENTO</label><input type="date" name="data_vencimento" id="edit_vencimento" class="form-control" required></div>
                    <div class="col-6">
                        <label class="small fw-bold">STATUS</label>
                        <select name="status" id="edit_status" class="form-select">
                            <option value="Pendente">Pendente</option>
                            <option value="Pago">Pago</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="submit" class="btn btn-primary w-100 rounded-pill py-2 fw-bold shadow-sm">ATUALIZAR REGISTRO</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: LER XML -->
<div class="modal fade" id="modalXML" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form action="acoes.php" method="POST" enctype="multipart/form-data" class="modal-content border-0 shadow-lg" style="border-radius:20px;">
            <div class="modal-header bg-dark text-white p-4 border-0">
                <h5 class="fw-bold mb-0">Importar XML Comevap</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <input type="hidden" name="acao" value="importar_xml_comevap">
                <i class="fas fa-qrcode fa-3x text-muted mb-3"></i>
                <p class="text-muted small">Extraia automaticamente os itens da nota SAT ou NFe.</p>
                <input type="file" name="xml_file" class="form-control" accept=".xml" required>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="submit" class="btn btn-dark w-100 rounded-pill py-2 fw-bold">CARREGAR XML</button>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function(){
    $('#mask_valor_novo, #edit_valor').mask('#.##0,00', {reverse: true});
    $('#selecionarTodosTemp').click(function(){ $('.checkItemTemp').prop('checked', this.checked); });
});

function abrirModalEditar(dados) {
    document.getElementById('edit_id').value = dados.id;
    document.getElementById('edit_tipo').value = dados.tipo;
    document.getElementById('edit_fornecedor').value = dados.fornecedor;
    document.getElementById('edit_descricao').value = dados.descricao;
    document.getElementById('edit_vencimento').value = dados.data_vencimento;
    document.getElementById('edit_status').value = dados.status;
    
    // Converte o valor para o formato da máscara
    let valor_mask = parseFloat(dados.valor).toLocaleString('pt-br', {minimumFractionDigits: 2});
    document.getElementById('edit_valor').value = valor_mask;

    var modalEditar = new bootstrap.Modal(document.getElementById('modalEditarLancamento'));
    modalEditar.show();
}
</script>
</body>
</html>