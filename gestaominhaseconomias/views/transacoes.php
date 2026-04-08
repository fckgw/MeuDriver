<?php
/**
 * BDSoft Workspace - Minhas Economias
 * View: Transações (PF) - Completa com Filtros e Auto-Update de Status
 * Localização: gestaominhaseconomias/views/transacoes.php
 */

$usuario_id = $_SESSION['usuario_id'];
$hoje_sql = date('Y-m-d');

// --- 0. REGRA DE NEGÓCIO AUTOMÁTICA: ATUALIZAR STATUS ATRASADO ---
// Se o status for 'Futuro' e a data de vencimento for menor que hoje, vira 'Atrasado'
$sql_update_atrasados = "UPDATE minhaseconomias_movimentacoes 
                         SET status = 'Atrasado' 
                         WHERE usuario_id = ? 
                         AND status = 'Futuro' 
                         AND data_vencimento < ?";
$pdo->prepare($sql_update_atrasados)->execute([$usuario_id, $hoje_sql]);

// 1. CAPTURA DE FILTROS
$f_banco  = $_GET['f_banco'] ?? '';
$f_status = $_GET['f_status'] ?? '';
$f_tipo   = $_GET['f_tipo'] ?? '';
$f_cat    = $_GET['f_cat'] ?? '';
$venc_hoje = isset($_GET['vencimento_hoje']) ? true : false;

// 2. BUSCAR DADOS PARA SELECTS
$lista_bancos = $pdo->query("SELECT id, nome FROM minhaseconomias_contas WHERE usuario_id = $usuario_id AND status = 1 ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);

$sql_c = "SELECT c1.id, c1.nome, c1.parent_id FROM minhaseconomias_categorias c1 WHERE c1.usuario_id = $usuario_id ORDER BY IFNULL(c1.parent_id, c1.id), c1.parent_id IS NOT NULL, c1.nome";
$todas_categorias = $pdo->query($sql_c)->fetchAll(PDO::FETCH_ASSOC);

// 3. CONSTRUÇÃO DA QUERY DINÂMICA
$sql = "SELECT m.*, c.nome as cat_nome, b.nome as banco_nome 
        FROM minhaseconomias_movimentacoes m 
        LEFT JOIN minhaseconomias_categorias c ON m.categoria_id = c.id 
        LEFT JOIN minhaseconomias_contas b ON m.conta_id = b.id 
        WHERE m.usuario_id = ?";
$params = [$usuario_id];

if($venc_hoje) {
    $sql .= " AND m.status IN ('Futuro','Atrasado') AND m.data_vencimento = CURDATE()";
} else {
    $sql .= " AND MONTH(m.data_vencimento) = ? AND YEAR(m.data_vencimento) = ?";
    $params[] = $mes_filtro; $params[] = $ano_filtro;
    if($f_banco)  { $sql .= " AND m.conta_id = ?"; $params[] = $f_banco; }
    if($f_status) { $sql .= " AND m.status = ?"; $params[] = $f_status; }
    if($f_tipo)   { $sql .= " AND m.tipo = ?"; $params[] = $f_tipo; }
    if($f_cat)    { $sql .= " AND m.categoria_id = ?"; $params[] = $f_cat; }
}

$stmt = $pdo->prepare($sql . " ORDER BY m.data_vencimento DESC, m.id DESC");
$stmt->execute($params);
$lancamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Contagem de Atrasados para Alerta
$total_atrasados = 0;
foreach($lancamentos as $l) if($l['status'] == 'Atrasado') $total_atrasados++;
?>

<!-- ALERTA DE CONTAS ATRASADAS (NOVO) -->
<?php if($total_atrasados > 0): ?>
<div class="alert alert-danger border-0 shadow-sm rounded-4 d-flex align-items-center mb-4" role="alert">
    <i class="fas fa-exclamation-triangle fa-2x me-3 opacity-75"></i>
    <div>
        <h6 class="fw-bold mb-0">Atenção: Você possui <?= $total_atrasados ?> lançamento(s) atrasado(s)!</h6>
        <small>Estes itens já passaram do vencimento e podem incidir juros ou multas.</small>
    </div>
</div>
<?php endif; ?>

<div class="card card-finance p-4 bg-white shadow-sm border-0 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h5 class="fw-bold m-0 text-dark"><i class="fas fa-exchange-alt me-2 text-primary"></i>Transações</h5>
            <small class="text-muted"><?= $venc_hoje ? "Pendências de Hoje" : "$mes_filtro/$ano_filtro" ?></small>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-primary rounded-pill px-4 fw-bold shadow" onclick="window.abrirModalNovaTransacao()">+ LANÇAR</button>
            <div class="dropdown">
                <button class="btn btn-outline-secondary btn-sm rounded-pill px-3 dropdown-toggle fw-bold" data-bs-toggle="dropdown">Exportar</button>
                <ul class="dropdown-menu border-0 shadow">
                    <li><a class="dropdown-item" href="exportar.php?type=excel&<?= http_build_query($_GET) ?>"><i class="fas fa-file-excel me-2 text-success"></i>Excel</a></li>
                    <li><a class="dropdown-item" href="exportar.php?type=pdf&<?= http_build_query($_GET) ?>"><i class="fas fa-file-pdf me-2 text-danger"></i>PDF</a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Barra de Filtros -->
    <form method="GET" class="row g-2 mb-4 bg-light p-3 rounded-4 border">
        <input type="hidden" name="p" value="transacoes">
        <div class="col-6 col-md-1"><select name="mes" class="form-select form-select-sm border-0 rounded-pill"><?php $m_n=["01"=>"Jan","02"=>"Fev","03"=>"Mar","04"=>"Abr","05"=>"Mai","06"=>"Jun","07"=>"Jul","08"=>"Ago","09"=>"Set","10"=>"Out","11"=>"Nov","12"=>"Dez"]; foreach($m_n as $k=>$v) echo "<option value='$k' ".($mes_filtro==$k?'selected':'').">$v</option>"; ?></select></div>
        <div class="col-6 col-md-1"><select name="ano" class="form-select form-select-sm border-0 rounded-pill"><?php for($i=2024; $i<=2026; $i++) echo "<option value='$i' ".($ano_filtro==$i?'selected':'').">$i</option>"; ?></select></div>
        <div class="col-md-2"><select name="f_banco" class="form-select form-select-sm border-0 shadow-sm rounded-pill px-3"><option value="">Bancos</option><?php foreach($lista_bancos as $b) echo "<option value='{$b['id']}' ".(($f_banco==$b['id'])?'selected':'').">{$b['nome']}</option>"; ?></select></div>
        <div class="col-md-2"><select name="f_cat" class="form-select form-select-sm border-0 shadow-sm rounded-pill px-3"><option value="">Categorias</option><?php foreach($todas_categorias as $cat) echo "<option value='{$cat['id']}' ".(($f_cat==$cat['id'])?'selected':'').">".($cat['parent_id']?"— ":"● ")."{$cat['nome']}</option>"; ?></select></div>
        <div class="col-md-2"><select name="f_status" class="form-select form-select-sm border-0 shadow-sm rounded-pill px-3"><option value="">Status</option><option value="Pago" <?= $f_status=='Pago'?'selected':'' ?>>Pago</option><option value="Futuro" <?= $f_status=='Futuro'?'selected':'' ?>>Futuro</option><option value="Atrasado" <?= $f_status=='Atrasado'?'selected':'' ?>>Atrasado</option></select></div>
        <div class="col-md-2 d-flex gap-1"><button type="submit" class="btn btn-dark btn-sm w-100 rounded-pill fw-bold">OK</button><a href="index.php?p=transacoes" class="btn btn-light btn-sm w-100 rounded-pill border"><i class="fas fa-eraser"></i></a></div>
    </form>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light"><tr class="small text-muted text-uppercase" style="font-size: 10px;"><th>Data</th><th>Descrição</th><th>Banco</th><th>Categoria</th><th>Valor</th><th>Situação</th><th class="text-center">Ações</th></tr></thead>
            <tbody>
                <?php if(empty($lancamentos)): ?>
                    <tr><td colspan="7" class="text-center py-4 text-muted small">Nenhum lançamento encontrado para este período.</td></tr>
                <?php endif; ?>
                <?php foreach($lancamentos as $l): ?>
                <tr style="font-size: 13px;">
                    <td><?= date('d/m/y', strtotime($l['data_vencimento'])) ?></td>
                    <td class="fw-bold text-dark"><?= htmlspecialchars($l['descricao']) ?></td>
                    <td><?= $l['banco_nome'] ?></td>
                    <td class="small text-primary fw-bold"><?= $l['cat_nome'] ?? 'Sem Categoria' ?></td>
                    <td class="fw-bold <?= $l['tipo']=='Receita' ? 'text-success':'text-danger' ?>">R$ <?= number_format($l['valor'], 2, ',', '.') ?></td>
                    <td>
                        <?php 
                        $cor=['Pago'=>'bg-success','Atrasado'=>'bg-danger','Futuro'=>'bg-primary']; 
                        $label = $l['status'];
                        echo "<span class='badge {$cor[$l['status']]} rounded-pill px-2' style='font-size:9px;'>$label</span>"; 
                        ?>
                    </td>
                    <td class="text-center">
                        <div class="btn-group">
                            <button class="btn btn-link btn-sm text-primary p-1" onclick="window.prepararEdicaoTransacao(<?= $l['id'] ?>, '<?= $l['data_vencimento'] ?>', '<?= addslashes($l['descricao']) ?>', <?= (int)$l['categoria_id'] ?>, <?= (int)$l['conta_id'] ?>, '<?= $l['valor'] ?>', '<?= $l['tipo'] ?>', '<?= $l['status'] ?>')"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-link btn-sm text-danger p-1" onclick="window.confirmarExcluirTransacao(<?= $l['id'] ?>, '<?= addslashes($l['descricao']) ?>')"><i class="fas fa-trash"></i></button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL TRANSAÇÃO (Lançar/Editar) -->
<div class="modal fade" id="modalTransacao" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pt-4 px-4"><h5 class="modal-title fw-bold text-dark" id="labelModalT">Novo Lançamento</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form action="index.php?p=transacoes&mes=<?= $mes_filtro ?>&ano=<?= $ano_filtro ?>" method="POST">
                <input type="hidden" name="id_transacao" id="id_t">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="small fw-bold">DATA</label><input type="date" name="data_transacao" id="input_data_t" class="form-control" required></div>
                        <div class="col-md-6"><label class="small fw-bold">TIPO</label><select name="tipo_transacao" id="input_tipo_t" class="form-select"><option value="Despesa">Despesa (Saída)</option><option value="Receita">Receita (Entrada)</option></select></div>
                        <div class="col-12"><label class="small fw-bold">DESCRIÇÃO</label><input type="text" name="descricao" id="input_desc_t" class="form-control" required></div>
                        
                        <div class="col-md-10">
                            <label class="small fw-bold">CATEGORIA</label>
                            <select name="categoria_id" id="input_cat_t" class="form-select">
                                <option value="999">Sem Categoria</option>
                                <?php foreach($todas_categorias as $cat) echo "<option value='{$cat['id']}'>".($cat['parent_id']?"— ":"● ")."{$cat['nome']}</option>"; ?>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="button" class="btn btn-outline-primary w-100 fw-bold" onclick="window.abrirAtalhoCategoria()" style="height: 38px;">+</button>
                        </div>

                        <div class="col-md-6"><label class="small fw-bold">CONTA / BANCO</label><select name="conta_id" id="input_conta_t" class="form-select"><?php foreach($lista_bancos as $b) echo "<option value='{$b['id']}'>{$b['nome']}</option>"; ?></select></div>
                        <div class="col-md-6"><label class="small fw-bold">VALOR (R$)</label><input type="text" name="valor" id="input_valor_t" class="form-control fw-bold text-primary" required></div>
                        <div class="col-12"><label class="small fw-bold">STATUS</label><select name="status_transacao" id="input_status_t" class="form-select"><option value="Pago">Pago</option><option value="Futuro">Futuro</option><option value="Atrasado">Atrasado</option></select></div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0"><button type="submit" name="btn_salvar_transacao" class="btn btn-primary w-100 rounded-pill py-3 fw-bold shadow">SALVAR</button></div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL EXCLUIR TRANSAÇÃO -->
<div class="modal fade" id="modalExcluirTransacao" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content border-0 shadow-lg text-center rounded-4"><div class="modal-body p-5"><i class="fas fa-trash text-danger fa-4x mb-4 opacity-25"></i><h4 class="fw-bold">Remover?</h4><p class="text-muted small" id="txtNomeTExcluir"></p><form action="index.php?p=transacoes&mes=<?= $mes_filtro ?>&ano=<?= $ano_filtro ?>" method="POST"><input type="hidden" name="id_transacao_excluir" id="id_t_excluir"><div class="d-grid gap-2 mt-4"><button type="submit" name="btn_excluir_transacao" class="btn btn-danger rounded-pill fw-bold shadow">SIM, EXCLUIR</button><button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Não</button>
</div>
    </form>
        </div>
            </div>
                </div>
                    </div>