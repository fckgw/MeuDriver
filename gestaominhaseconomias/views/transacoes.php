<?php
/**
 * View: Transações Completa - BI, Logs, Editar e Deletar (Pop-ups)
 */
$usuario_id = $_SESSION['usuario_id'];

// Buscas para Filtros e Modal
$contas_ativas = $pdo->query("SELECT id, nome FROM minhaseconomias_contas WHERE usuario_id=$usuario_id AND status=1 ORDER BY nome ASC")->fetchAll();
$categorias_ac = $pdo->query("SELECT id, nome FROM minhaseconomias_categorias WHERE usuario_id=$usuario_id ORDER BY nome ASC")->fetchAll();
$veiculos_list = $pdo->query("SELECT id, modelo, placa FROM minhaseconomias_veiculos WHERE usuario_id=$usuario_id")->fetchAll();

// Query Principal
$sql = "SELECT m.*, c.nome as cat_nome, b.nome as banco_nome FROM minhaseconomias_movimentacoes m 
        LEFT JOIN minhaseconomias_categorias c ON m.categoria_id = c.id 
        LEFT JOIN minhaseconomias_contas b ON m.conta_id = b.id 
        WHERE m.usuario_id = ? AND m.data_vencimento BETWEEN ? AND ?";
$params = [$usuario_id, $data_inicio, $data_fim];

if($f_status){ $sql .= " AND m.status = ?"; $params[] = $f_status; }
if($f_banco) { $sql .= " AND m.conta_id = ?"; $params[] = $f_banco; }

$stmt_trans = $pdo->prepare($sql . " ORDER BY m.data_vencimento DESC");
$stmt_trans->execute($params);
$lancamentos = $stmt_trans->fetchAll();
?>

<div class="card p-4 border-0 shadow-sm bg-white" style="border-radius: 20px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="fw-bold m-0"><i class="fas fa-exchange-alt me-2 text-primary"></i>Movimentações</h5>
        <div class="d-flex gap-2">
            <a href="index.php?p=bi" class="btn btn-info btn-sm text-white rounded-pill px-3 fw-bold shadow-sm"><i class="fas fa-robot"></i> BI</a>
            <button class="btn btn-primary btn-sm rounded-pill px-3 fw-bold shadow-sm" onclick="abrirNovoLancamento()"><i class="fas fa-plus"></i> LANÇAR</button>
            <a href="exportar.php?<?= $_SERVER['QUERY_STRING'] ?>" class="btn btn-outline-success btn-sm rounded-pill px-3 fw-bold shadow-sm"><i class="fas fa-file-excel"></i> EXPORTAR</a>
            <a href="index.php?p=dashboard" class="btn btn-outline-secondary btn-sm rounded-pill px-3 fw-bold shadow-sm">VOLTAR</a>
        </div>
    </div>

    <!-- Filtros de Pesquisa -->
    <form method="GET" class="row g-2 mb-4 bg-light p-3 rounded-4 border">
        <input type="hidden" name="p" value="transacoes">
        <div class="col-md-2"><label class="small fw-bold text-muted">INÍCIO</label><input type="date" name="data_inicio" class="form-control form-control-sm rounded-pill" value="<?= $data_inicio ?>"></div>
        <div class="col-md-2"><label class="small fw-bold text-muted">FIM</label><input type="date" name="data_fim" class="form-control form-control-sm rounded-pill" value="<?= $data_fim ?>"></div>
        <div class="col-md-2"><label class="small fw-bold text-muted">STATUS</label>
            <select name="f_status" class="form-select form-select-sm rounded-pill">
                <option value="">Todos</option>
                <option value="Pago" <?= $f_status=='Pago'?'selected':'' ?>>Pago</option>
                <option value="Futuro" <?= $f_status=='Futuro'?'selected':'' ?>>Futuro</option>
                <option value="Atrasado" <?= $f_status=='Atrasado'?'selected':'' ?>>Atrasado</option>
            </select>
        </div>
        <div class="col-md-3"><label class="small fw-bold text-muted">CONTA</label>
            <select name="f_banco" class="form-select form-select-sm rounded-pill">
                <option value="">Todas as Contas</option>
                <?php foreach($contas_ativas as $ca) echo "<option value='{$ca['id']}' ".($f_banco==$ca['id']?'selected':'').">{$ca['nome']}</option>"; ?>
            </select>
        </div>
        <div class="col-md-1 d-flex align-items-end"><button type="submit" class="btn btn-dark btn-sm w-100 rounded-pill fw-bold">FILTRAR</button></div>
    </form>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light"><tr class="small text-muted"><th>DATA</th><th>DESCRIÇÃO</th><th>VALOR</th><th>STATUS</th><th class="text-center">BI</th><th class="text-end">AÇÕES</th></tr></thead>
            <tbody>
                <?php foreach($lancamentos as $l): ?>
                <tr style="font-size: 13px;">
                    <td><?= date('d/m/Y', strtotime($l['data_vencimento'])) ?></td>
                    <td><span class="fw-bold"><?= htmlspecialchars($l['descricao']) ?></span><br><small class="text-muted"><?= $l['cat_nome'] ?> | <?= $l['banco_nome'] ?></small></td>
                    <td class="<?= $l['tipo']=='Receita' ? 'text-success' : 'text-danger' ?> fw-bold">R$ <?= number_format($l['valor'], 2, ',', '.') ?></td>
                    <td><span class="badge <?= $l['status']=='Pago' ? 'bg-success' : ($l['status']=='Atrasado'?'bg-danger':'bg-primary') ?> rounded-pill px-3"><?= $l['status'] ?></span></td>
                    <td class="text-center">
                        <form method="POST" style="display:inline;"><input type="hidden" name="id_transacao" value="<?= $l['id'] ?>"><input type="hidden" name="status_bi" value="<?= $l['bi_analise']?'0':'1' ?>"><button type="submit" name="btn_flag_bi" class="btn btn-sm <?= $l['bi_analise']?'btn-warning shadow':'btn-outline-light border text-dark' ?> rounded-circle"><i class="fas fa-robot"></i></button></form>
                    </td>
                    <td class="text-end">
                        <div class="d-flex justify-content-end gap-1">
                            <button class="btn btn-sm text-primary" onclick='editarTransacao(<?= json_encode($l) ?>)' title="Editar"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-sm text-danger" onclick='confirmarExclusao(<?= json_encode($l) ?>)' title="Excluir"><i class="fas fa-trash"></i></button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL LANÇAMENTO / EDICAO -->
<div class="modal fade" id="modalLancar" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="POST" id="formL" class="modal-content border-0 shadow-lg rounded-4"><div class="modal-header bg-dark text-white"><h5 id="modalLTitulo">Novo Lançamento</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body p-4"><input type="hidden" name="id_transacao" id="l_id"><div class="mb-3"><label class="small fw-bold">DESCRIÇÃO</label><input type="text" name="descricao" id="l_desc" class="form-control rounded-3" required></div><div class="row mb-3"><div class="col-6"><label class="small fw-bold">VALOR (R$)</label><input type="text" name="valor" id="l_val" class="form-control rounded-3 fw-bold text-primary" required placeholder="0,00"></div><div class="col-6"><label class="small fw-bold">DATA</label><input type="date" name="data_transacao" id="l_data" class="form-control rounded-3" value="<?= date('Y-m-d') ?>"></div></div><div class="row mb-3"><div class="col-6"><label class="small fw-bold">TIPO</label><select name="tipo_transacao" id="l_tipo" class="form-select rounded-3"><option value="Despesa">Despesa</option><option value="Receita">Receita</option></select></div><div class="col-6"><label class="small fw-bold">STATUS</label><select name="status_transacao" id="l_st" class="form-select rounded-3"><option value="Pago">Pago</option><option value="Futuro">Futuro</option><option value="Atrasado">Atrasado</option></select></div></div><div class="mb-3"><label class="small fw-bold">CONTA</label><select name="conta_id" id="l_conta" class="form-select rounded-3"><?php foreach($contas_ativas as $ca) echo "<option value='{$ca['id']}'>{$ca['nome']}</option>"; ?></select></div><div class="mb-3"><label class="small fw-bold">CATEGORIA (AUTO-COMPLETE)</label><input type="text" id="ac_cat" list="dl_cats" class="form-control rounded-3" placeholder="Digite para buscar..." required><datalist id="dl_cats"><?php foreach($categorias_ac as $ct) echo "<option data-id='{$ct['id']}' value='{$ct['nome']}'>"; ?></datalist><input type="hidden" name="categoria_id" id="l_cat_id"></div></div><div class="modal-footer border-0 p-4 pt-0"><button type="submit" name="btn_salvar_transacao" class="btn btn-primary w-100 rounded-pill py-3 fw-bold shadow">SALVAR LANÇAMENTO</button></div></form></div></div>

<!-- MODAL EXCLUSAO -->
<div class="modal fade" id="modalExcluirT" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="POST" class="modal-content border-0 shadow-lg rounded-4"><div class="modal-header bg-danger text-white"><h5>Excluir Lançamento?</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body p-4 text-center"><input type="hidden" name="id_transacao_excluir" id="id_ex_t"><p id="info_ex" class="fw-bold fs-5"></p><p class="text-muted small">Esta ação registrará um LOG de auditoria e não pode ser desfeita.</p></div><div class="modal-footer border-0 p-4 pt-0"><button type="submit" name="btn_excluir_transacao_confirmado" class="btn btn-danger w-100 rounded-pill py-3 fw-bold shadow">SIM, EXCLUIR AGORA</button></div></form></div></div>

<script>
    // Máscara 0,00
    document.getElementById('l_val').addEventListener('input', function(e) {
        let v = e.target.value.replace(/\D/g, '');
        v = (v/100).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        e.target.value = v;
    });

    // Autocomplete Categoria
    document.getElementById('ac_cat').addEventListener('input', function() {
        const opts = document.getElementById('dl_cats').childNodes;
        for(let opt of opts) if(opt.value === this.value) document.getElementById('l_cat_id').value = opt.getAttribute('data-id');
    });

    function abrirNovoLancamento() {
        document.getElementById('modalLTitulo').innerText = 'Novo Lançamento';
        document.getElementById('formL').reset();
        document.getElementById('l_id').value = '';
        new bootstrap.Modal(document.getElementById('modalLancar')).show();
    }

    function editarTransacao(d) {
        document.getElementById('modalLTitulo').innerText = 'Editar Lançamento';
        document.getElementById('l_id').value = d.id;
        document.getElementById('l_desc').value = d.descricao;
        document.getElementById('l_val').value = parseFloat(d.valor).toLocaleString('pt-BR', {minimumFractionDigits: 2});
        document.getElementById('l_data').value = d.data_vencimento;
        document.getElementById('l_tipo').value = d.tipo;
        document.getElementById('l_st').value = d.status;
        document.getElementById('l_conta').value = d.conta_id;
        document.getElementById('l_cat_id').value = d.categoria_id;
        document.getElementById('ac_cat').value = d.cat_nome;
        new bootstrap.Modal(document.getElementById('modalLancar')).show();
    }

    function confirmarExclusao(d) {
        document.getElementById('id_ex_t').value = d.id;
        document.getElementById('info_ex').innerText = d.descricao + ' - R$ ' + parseFloat(d.valor).toLocaleString('pt-BR', {minimumFractionDigits: 2});
        new bootstrap.Modal(document.getElementById('modalExcluirT')).show();
    }
</script>