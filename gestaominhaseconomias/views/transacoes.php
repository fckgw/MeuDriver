<?php
/**
 * BDSoft Workspace - Minhas Economias
 * View: Transações (PF) - COMPLETA
 * Localização: gestaominhaseconomias/views/transacoes.php
 */

$usuario_id = $_SESSION['usuario_id'];
$hoje_sql = date('Y-m-d');

// --- 0. REGRA AUTOMÁTICA: ATUALIZAR STATUS ATRASADO ---
// Transforma automaticamente "Futuro" em "Atrasado" se a data de vencimento já passou.
$sql_auto_update = "UPDATE minhaseconomias_movimentacoes 
                    SET status = 'Atrasado' 
                    WHERE usuario_id = ? 
                    AND status = 'Futuro' 
                    AND data_vencimento < ?";
$pdo->prepare($sql_auto_update)->execute([$usuario_id, $hoje_sql]);

// --- 1. CAPTURA DE FILTROS (VIA GET) ---
$f_banco  = $_GET['f_banco'] ?? '';
$f_status = $_GET['f_status'] ?? '';
$f_tipo   = $_GET['f_tipo'] ?? '';
$f_cat    = $_GET['f_cat'] ?? '';
$venc_hoje = isset($_GET['vencimento_hoje']) ? true : false;

// --- 2. BUSCAR DADOS PARA OS SELETORES (DROPDOWNS) ---
// Busca contas/bancos ativos
$lista_bancos = $pdo->query("SELECT id, nome FROM minhaseconomias_contas WHERE usuario_id = $usuario_id AND status = 1 ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);

// Busca categorias organizadas
$sql_cat_list = "SELECT id, nome, parent_id FROM minhaseconomias_categorias WHERE usuario_id = $usuario_id ORDER BY IFNULL(parent_id, id), parent_id IS NOT NULL, nome";
$todas_categorias = $pdo->query($sql_cat_list)->fetchAll(PDO::FETCH_ASSOC);

// --- 3. CONSTRUÇÃO DA QUERY DINÂMICA DE LANÇAMENTOS ---
$sql = "SELECT m.*, c.nome as cat_nome, b.nome as banco_nome 
        FROM minhaseconomias_movimentacoes m 
        LEFT JOIN minhaseconomias_categorias c ON m.categoria_id = c.id 
        LEFT JOIN minhaseconomias_contas b ON m.conta_id = b.id 
        WHERE m.usuario_id = ?";
$params = [$usuario_id];

if($venc_hoje) {
    // Filtro especial para pendências de hoje (usado pelo alerta do dashboard)
    $sql .= " AND m.status IN ('Futuro','Atrasado') AND m.data_vencimento = ?";
    $params[] = $hoje_sql;
} else {
    // Filtros normais por Mês, Ano e critérios específicos
    $sql .= " AND MONTH(m.data_vencimento) = ? AND YEAR(m.data_vencimento) = ?";
    $params[] = $mes_filtro; 
    $params[] = $ano_filtro;
    
    if($f_banco)  { $sql .= " AND m.conta_id = ?"; $params[] = $f_banco; }
    if($f_status) { $sql .= " AND m.status = ?"; $params[] = $f_status; }
    if($f_tipo)   { $sql .= " AND m.tipo = ?"; $params[] = $f_tipo; }
    if($f_cat)    { $sql .= " AND m.categoria_id = ?"; $params[] = $f_cat; }
}

$stmt = $pdo->prepare($sql . " ORDER BY m.data_vencimento DESC, m.id DESC");
$stmt->execute($params);
$lancamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- 4. CÁLCULO DOS TOTAIS BASEADO NO FILTRO ATIVO ---
$total_recebido = 0;   // Receitas PAGAS
$total_pago = 0;       // Despesas PAGAS
$total_futuro = 0;     // Projeção (Tudo que não está pago)
$count_atrasados = 0;  // Contador para o alerta visual

foreach($lancamentos as $l) {
    if($l['status'] == 'Pago') {
        if($l['tipo'] == 'Receita') $total_recebido += $l['valor'];
        else $total_pago += $l['valor'];
    } else {
        // Se não está pago, entra na projeção futura (Receita soma, Despesa subtrai)
        if($l['tipo'] == 'Receita') $total_futuro += $l['valor'];
        else $total_futuro -= $l['valor'];
        
        if($l['status'] == 'Atrasado') $count_atrasados++;
    }
}
$balanco_realizado = $total_recebido - $total_pago;
?>

<!-- PAINEL DE INDICADORES DINÂMICOS (Baseado no Filtro) -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
            <small class="text-muted fw-bold text-uppercase" style="font-size: 9px;">Recebido (Pagas)</small>
            <div class="h5 fw-bold text-success mb-0">R$ <?= number_format($total_recebido, 2, ',', '.') ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
            <small class="text-muted fw-bold text-uppercase" style="font-size: 9px;">Pago (Saídas)</small>
            <div class="h5 fw-bold text-danger mb-0">R$ <?= number_format($total_pago, 2, ',', '.') ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
            <small class="text-muted fw-bold text-uppercase" style="font-size: 9px;">Balanço Realizado</small>
            <div class="h5 fw-bold <?= $balanco_realizado >= 0 ? 'text-primary' : 'text-danger' ?> mb-0">R$ <?= number_format($balanco_realizado, 2, ',', '.') ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-primary text-white h-100 shadow">
            <small class="text-white-50 fw-bold text-uppercase" style="font-size: 9px;">Projeção Futuro</small>
            <div class="h5 fw-bold mb-0">R$ <?= number_format($total_futuro, 2, ',', '.') ?></div>
        </div>
    </div>
</div>

<?php if($count_atrasados > 0): ?>
<div class="alert alert-danger border-0 shadow-sm rounded-4 d-flex align-items-center mb-4" role="alert">
    <i class="fas fa-exclamation-triangle fa-2x me-3 opacity-75"></i>
    <div>
        <h6 class="fw-bold mb-0">Atenção: <?= $count_atrasados ?> lançamento(s) atrasado(s)!</h6>
        <small>Estes itens já passaram do vencimento no filtro aplicado.</small>
    </div>
</div>
<?php endif; ?>

<div class="card card-finance p-4 bg-white shadow-sm border-0 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h5 class="fw-bold m-0 text-dark"><i class="fas fa-exchange-alt me-2 text-primary"></i>Transações</h5>
            <small class="text-muted"><?= $venc_hoje ? "Pendências de Hoje" : "Filtrado por: $mes_filtro/$ano_filtro" ?></small>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-primary rounded-pill px-4 fw-bold shadow" onclick="window.abrirModalNovaTransacao()">+ LANÇAR</button>
            <div class="dropdown">
                <button class="btn btn-outline-secondary btn-sm rounded-pill px-3 dropdown-toggle fw-bold" data-bs-toggle="dropdown">Exportar</button>
                <ul class="dropdown-menu border-0 shadow">
                    <li><a class="dropdown-item" href="exportar.php?type=excel&<?= http_build_query($_GET) ?>"><i class="fas fa-file-excel me-2 text-success"></i>Excel / CSV</a></li>
                    <li><a class="dropdown-item" href="exportar.php?type=pdf&<?= http_build_query($_GET) ?>"><i class="fas fa-file-pdf me-2 text-danger"></i>Relatório PDF</a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Barra de Filtros -->
    <form method="GET" class="row g-2 mb-4 bg-light p-3 rounded-4 border">
        <input type="hidden" name="p" value="transacoes">
        <div class="col-6 col-md-1">
            <select name="mes" class="form-select form-select-sm border-0 rounded-pill shadow-sm">
                <?php 
                $m_n=["01"=>"Jan","02"=>"Fev","03"=>"Mar","04"=>"Abr","05"=>"Mai","06"=>"Jun","07"=>"Jul","08"=>"Ago","09"=>"Set","10"=>"Out","11"=>"Nov","12"=>"Dez"]; 
                foreach($m_n as $k=>$v) echo "<option value='$k' ".($mes_filtro==$k?'selected':'').">$v</option>"; 
                ?>
            </select>
        </div>
        <div class="col-6 col-md-1">
            <select name="ano" class="form-select form-select-sm border-0 rounded-pill shadow-sm">
                <?php for($i=2024; $i<=2026; $i++) echo "<option value='$i' ".($ano_filtro==$i?'selected':'').">$i</option>"; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select name="f_banco" class="form-select form-select-sm border-0 shadow-sm rounded-pill px-3">
                <option value="">Todos os Bancos</option>
                <?php foreach($lista_bancos as $b) echo "<option value='{$b['id']}' ".(($f_banco==$b['id'])?'selected':'').">{$b['nome']}</option>"; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select name="f_cat" class="form-select form-select-sm border-0 shadow-sm rounded-pill px-3">
                <option value="">Categorias</option>
                <?php foreach($todas_categorias as $cat) echo "<option value='{$cat['id']}' ".(($f_cat==$cat['id'])?'selected':'').">".($cat['parent_id']?"— ":"● ")."{$cat['nome']}</option>"; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select name="f_status" class="form-select form-select-sm border-0 shadow-sm rounded-pill px-3">
                <option value="">Situação</option>
                <option value="Pago" <?= $f_status=='Pago'?'selected':'' ?>>Pago</option>
                <option value="Futuro" <?= $f_status=='Futuro'?'selected':'' ?>>Futuro</option>
                <option value="Atrasado" <?= $f_status=='Atrasado'?'selected':'' ?>>Atrasado</option>
            </select>
        </div>
        <div class="col-md-2">
            <select name="f_tipo" class="form-select form-select-sm border-0 shadow-sm rounded-pill px-3">
                <option value="">Tipo</option>
                <option value="Receita" <?= $f_tipo=='Receita'?'selected':'' ?>>Receitas</option>
                <option value="Despesa" <?= $f_tipo=='Despesa'?'selected':'' ?>>Despesas</option>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-1">
            <button type="submit" class="btn btn-dark btn-sm w-100 rounded-pill fw-bold">FILTRAR</button>
            <a href="index.php?p=transacoes" class="btn btn-light btn-sm w-100 rounded-pill border" title="Limpar Filtros"><i class="fas fa-eraser"></i></a>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr class="small text-muted text-uppercase" style="font-size: 10px;">
                    <th>Data</th>
                    <th>Descrição</th>
                    <th>Banco</th>
                    <th>Categoria</th>
                    <th class="text-end">Valor</th>
                    <th class="text-center">Situação</th>
                    <th class="text-center">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($lancamentos)): ?>
                    <tr><td colspan="7" class="text-center py-5 text-muted small">Nenhum lançamento encontrado para os filtros aplicados.</td></tr>
                <?php endif; ?>
                <?php foreach($lancamentos as $l): ?>
                <tr style="font-size: 13px;">
                    <td style="white-space: nowrap;"><?= date('d/m/y', strtotime($l['data_vencimento'])) ?></td>
                    <td class="fw-bold text-dark"><?= htmlspecialchars($l['descricao']) ?></td>
                    <td class="text-muted small"><?= $l['banco_nome'] ?></td>
                    <td><span class="badge bg-light text-primary border rounded-pill fw-normal"><?= $l['cat_nome'] ?? 'Sem Categoria' ?></span></td>
                    <td class="fw-bold text-end <?= $l['tipo']=='Receita' ? 'text-success':'text-danger' ?>">
                        R$ <?= number_format($l['valor'], 2, ',', '.') ?>
                    </td>
                    <td class="text-center">
                        <?php 
                        $cor=['Pago'=>'bg-success','Atrasado'=>'bg-danger','Futuro'=>'bg-primary']; 
                        echo "<span class='badge {$cor[$l['status']]} rounded-pill px-2' style='font-size:9px;'>{$l['status']}</span>"; 
                        ?>
                    </td>
                    <td class="text-center">
                        <div class="btn-group">
                            <button class="btn btn-link btn-sm text-primary p-1" title="Editar" onclick="window.prepararEdicaoTransacao(<?= $l['id'] ?>, '<?= $l['data_vencimento'] ?>', '<?= addslashes($l['descricao']) ?>', <?= $l['categoria_id'] ?>, <?= $l['conta_id'] ?>, '<?= $l['valor'] ?>', '<?= $l['tipo'] ?>', '<?= $l['status'] ?>')">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-link btn-sm text-danger p-1" title="Excluir" onclick="window.confirmarExcluirTransacao(<?= $l['id'] ?>, '<?= addslashes($l['descricao']) ?>')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL TRANSAÇÃO (Novo/Editar) -->
<div class="modal fade" id="modalTransacao" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-dark" id="labelModalT">Novo Lançamento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="index.php?<?= http_build_query($_GET) ?>" method="POST">
                <input type="hidden" name="id_transacao" id="id_t">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="small fw-bold">DATA VENCIMENTO</label>
                            <input type="date" name="data_transacao" id="input_data_t" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-bold">TIPO</label>
                            <select name="tipo_transacao" id="input_tipo_t" class="form-select">
                                <option value="Despesa">Despesa (Saída)</option>
                                <option value="Receita">Receita (Entrada)</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="small fw-bold">DESCRIÇÃO</label>
                            <input type="text" name="descricao" id="input_desc_t" class="form-control" placeholder="Ex: Aluguel, Supermercado..." required>
                        </div>
                        
                        <div class="col-md-10">
                            <label class="small fw-bold">CATEGORIA</label>
                            <select name="categoria_id" id="input_cat_t" class="form-select">
                                <option value="999">Sem Categoria</option>
                                <?php foreach($todas_categorias as $cat) echo "<option value='{$cat['id']}'>".($cat['parent_id']?"— ":"● ")."{$cat['nome']}</option>"; ?>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="button" class="btn btn-outline-primary w-100 fw-bold" title="Nova Categoria" onclick="window.abrirAtalhoCategoria()" style="height: 38px;">+</button>
                        </div>

                        <div class="col-md-6">
                            <label class="small fw-bold">CONTA / BANCO</label>
                            <select name="conta_id" id="input_conta_t" class="form-select">
                                <?php foreach($lista_bancos as $b) echo "<option value='{$b['id']}'>{$b['nome']}</option>"; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-bold">VALOR (R$)</label>
                            <input type="text" name="valor" id="input_valor_t" class="form-control fw-bold text-primary" placeholder="0,00" required>
                        </div>
                        <div class="col-12">
                            <label class="small fw-bold">STATUS ATUAL</label>
                            <select name="status_transacao" id="input_status_t" class="form-select">
                                <option value="Pago">Pago</option>
                                <option value="Futuro">Futuro</option>
                                <option value="Atrasado">Atrasado</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="submit" name="btn_salvar_transacao" class="btn btn-primary w-100 rounded-pill py-3 fw-bold shadow">
                        SALVAR LANÇAMENTO
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL EXCLUIR TRANSAÇÃO -->
<div class="modal fade" id="modalExcluirTransacao" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg text-center rounded-4">
            <div class="modal-body p-5">
                <i class="fas fa-trash text-danger fa-4x mb-4 opacity-25"></i>
                <h4 class="fw-bold">Remover?</h4>
                <p class="text-muted small" id="txtNomeTExcluir"></p>
                <form action="index.php?<?= http_build_query($_GET) ?>" method="POST">
                    <input type="hidden" name="id_transacao_excluir" id="id_t_excluir">
                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" name="btn_excluir_transacao" class="btn btn-danger rounded-pill fw-bold shadow">SIM, EXCLUIR</button>
                        <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Não</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>