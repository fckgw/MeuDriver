<?php
/**
 * BDSoft Workspace - Minhas Economias
 * View: Transações (Versão Completa com Correção do Editar)
 */
$usuario_id = $_SESSION['usuario_id'];

// 1. BUSCA DE DADOS PARA FILTROS E FORMULÁRIOS
$bancos = $pdo->query("SELECT id, nome FROM minhaseconomias_contas WHERE usuario_id = $usuario_id AND status = 1 ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
$categorias = $pdo->query("SELECT id, nome, parent_id FROM minhaseconomias_categorias WHERE usuario_id = $usuario_id ORDER BY IFNULL(parent_id, id), parent_id IS NOT NULL, nome ASC")->fetchAll(PDO::FETCH_ASSOC);

// 2. CONSTRUÇÃO DA QUERY DINÂMICA DO GRID
$sql_grid = "SELECT m.*, c.nome as cat_nome, b.nome as banco_nome FROM minhaseconomias_movimentacoes m 
             LEFT JOIN minhaseconomias_categorias c ON m.categoria_id = c.id 
             LEFT JOIN minhaseconomias_contas b ON m.conta_id = b.id 
             WHERE m.usuario_id = ? AND MONTH(m.data_vencimento) = ? AND YEAR(m.data_vencimento) = ?";
$params_grid = [$usuario_id, $mes_filtro, $ano_filtro];

if($filtro_banco) { $sql_grid .= " AND m.conta_id = ?"; $params_grid[] = $filtro_banco; }
if($filtro_status) { $sql_grid .= " AND m.status = ?"; $params_grid[] = $filtro_status; }
if($filtro_tipo) { $sql_grid .= " AND m.tipo = ?"; $params_grid[] = $filtro_tipo; }
if($filtro_categoria) { $sql_grid .= " AND m.categoria_id = ?"; $params_grid[] = $filtro_categoria; }

// Ordenação solicitada (Padrão ASC)
$sql_grid .= " ORDER BY m.data_vencimento $ordenacao_data, m.id $ordenacao_data";
$stmt_grid = $pdo->prepare($sql_grid);
$stmt_grid->execute($params_grid);
$lancamentos = $stmt_grid->fetchAll(PDO::FETCH_ASSOC);

// 3. CÁLCULO DOS INDICADORES
$total_e = 0; $total_s = 0; $total_f = 0;
foreach($lancamentos as $item) {
    if($item['status'] == 'Pago') {
        if($item['tipo'] == 'Receita') $total_e += $item['valor']; else $total_s += $item['valor'];
    } else {
        if($item['tipo'] == 'Receita') $total_f += $item['valor']; else $total_f -= $item['valor'];
    }
}
?>

<!-- INDICADORES -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm p-3 bg-white h-100">
            <small class="text-muted fw-bold text-uppercase" style="font-size: 10px;">Entradas (Pagas)</small>
            <div class="h5 fw-bold text-success mb-0">R$ <?= number_format($total_e, 2, ',', '.') ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm p-3 bg-white h-100">
            <small class="text-muted fw-bold text-uppercase" style="font-size: 10px;">Saídas (Pagas)</small>
            <div class="h5 fw-bold text-danger mb-0">R$ <?= number_format($total_s, 2, ',', '.') ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm p-3 bg-white h-100">
            <small class="text-muted fw-bold text-uppercase" style="font-size: 10px;">Balanço</small>
            <div class="h5 fw-bold text-primary mb-0">R$ <?= number_format($total_e - $total_s, 2, ',', '.') ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm p-3 bg-primary text-white h-100 shadow">
            <small class="text-white-50 fw-bold text-uppercase" style="font-size: 10px;">Projeção Mês</small>
            <div class="h5 fw-bold mb-0">R$ <?= number_format($total_f, 2, ',', '.') ?></div>
        </div>
    </div>
</div>

<div class="card p-4 shadow-sm border-0 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h5 class="fw-bold m-0"><i class="fas fa-exchange-alt text-primary me-2"></i>Transações</h5>
        <div class="d-flex gap-2">
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle border-0" data-bs-toggle="dropdown"><i class="fas fa-download"></i></button>
                <ul class="dropdown-menu shadow border-0">
                    <li><a class="dropdown-item small" href="exportar.php?type=pdf&<?= $filtros_contexto_url ?>">PDF</a></li>
                    <li><a class="dropdown-item small" href="exportar.php?type=excel&<?= $filtros_contexto_url ?>">Excel</a></li>
                </ul>
            </div>
            <button class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" onclick="abrirNovo()">+ LANÇAR</button>
        </div>
    </div>

    <!-- FILTROS -->
    <form method="GET" class="row g-2 mb-4 bg-light p-3 rounded-4 border align-items-center">
        <input type="hidden" name="p" value="transacoes">
        <div class="col-md-1">
            <select name="mes" class="form-select form-select-sm border-0 rounded-pill shadow-sm">
                <?php foreach(["01"=>"Jan","02"=>"Fev","03"=>"Mar","04"=>"Abr","05"=>"Mai","06"=>"Jun","07"=>"Jul","08"=>"Ago","09"=>"Set","10"=>"Out","11"=>"Nov","12"=>"Dez"] as $k=>$v) echo "<option value='$k' ".($mes_filtro==$k?'selected':'').">$v</option>"; ?>
            </select>
        </div>
        <div class="col-md-1">
            <select name="ano" class="form-select form-select-sm border-0 rounded-pill shadow-sm">
                <?php for($i=2024;$i<=2026;$i++) echo "<option value='$i' ".($ano_filtro==$i?'selected':'').">$i</option>"; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select name="f_status" class="form-select form-select-sm border-0 rounded-pill shadow-sm">
                <option value="">Status (Todos)</option>
                <option value="Pago" <?= $filtro_status=='Pago'?'selected':'' ?>>Pago</option>
                <option value="Futuro" <?= $filtro_status=='Futuro'?'selected':'' ?>>Futuro</option>
                <option value="Atrasado" <?= $filtro_status=='Atrasado'?'selected':'' ?>>Atrasado</option>
            </select>
        </div>
        <div class="col-md-2">
            <select name="ordem" class="form-select form-select-sm border-0 rounded-pill shadow-sm">
                <option value="ASC" <?= $ordenacao_data=='ASC'?'selected':'' ?>>Data Crescente</option>
                <option value="DESC" <?= $ordenacao_data=='DESC'?'selected':'' ?>>Data Decrescente</option>
            </select>
        </div>
        <div class="col-md-2">
            <select name="f_banco" class="form-select form-select-sm border-0 rounded-pill shadow-sm">
                <option value="">Todos Bancos</option>
                <?php foreach($bancos as $b) echo "<option value='{$b['id']}' ".($filtro_banco==$b['id']?'selected':'').">{$b['nome']}</option>"; ?>
            </select>
        </div>
        <div class="col-md-1">
            <button type="submit" class="btn btn-dark btn-sm w-100 rounded-pill fw-bold">FILTRAR</button>
        </div>
        <div class="col-md-1">
            <a href="index.php?p=transacoes" class="btn btn-light btn-sm border rounded-pill w-100"><i class="fas fa-eraser"></i></a>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr class="small text-muted" style="font-size: 10px;"><th>DATA</th><th>DESCRIÇÃO</th><th>BANCO</th><th>CATEGORIA</th><th class="text-end">VALOR</th><th>STATUS</th><th class="text-center">AÇÕES</th></tr></thead>
            <tbody style="font-size: 13px;">
                <?php if(empty($lancamentos)) echo "<tr><td colspan='7' class='text-center py-4'>Nenhum lançamento encontrado.</td></tr>"; ?>
                <?php foreach($lancamentos as $l): ?>
                <tr>
                    <td><?= date('d/m/y', strtotime($l['data_vencimento'])) ?></td>
                    <td class="fw-bold"><?= htmlspecialchars($l['descricao']) ?></td>
                    <td class="small text-muted"><?= $l['banco_nome'] ?></td>
                    <td><span class="badge bg-light text-primary border rounded-pill fw-normal"><?= $l['cat_nome'] ?></span></td>
                    <td class="text-end fw-bold <?= $l['tipo']=='Receita'?'text-success':'text-danger' ?>">R$ <?= number_format($l['valor'],2,',','.') ?></td>
                    <td>
                        <?php 
                        $badge=['Pago'=>'bg-success','Futuro'=>'bg-primary','Atrasado'=>'bg-danger']; 
                        echo "<span class='badge {$badge[$l['status']]} rounded-pill px-2' style='font-size:9px'>{$l['status']}</span>"; 
                        ?>
                    </td>
                    <td class="text-center">
                        <button class="btn btn-link btn-sm p-1" onclick='abrirEdicaoTransacao(<?= json_encode($l) ?>)'><i class="fas fa-edit"></i></button>
                        <button class="btn btn-link btn-sm p-1 text-danger" onclick="confirmarExclusaoTransacao(<?= $l['id'] ?>, '<?= addslashes($l['descricao']) ?>')"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL: LANÇAMENTO (NOVO / EDITAR) -->
<div class="modal fade" id="modalTransacao" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form action="index.php?p=transacoes&<?= $filtros_contexto_url ?>" method="POST" class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pt-4 px-4"><h5 class="fw-bold" id="titulo_modal_transacao">Lançamento</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-4">
                <input type="hidden" name="id_transacao" id="input_id_transacao">
                <input type="hidden" name="combustivel_ativo" id="input_combustivel_ativo" value="0">
                <div class="row g-3">
                    <div class="col-md-6"><label class="small fw-bold">DATA</label><input type="date" name="data_transacao" id="input_data_transacao" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
                    <div class="col-md-6"><label class="small fw-bold">VALOR (R$)</label><input type="text" name="valor" id="input_valor_transacao" class="form-control fw-bold text-primary" oninput="mascaraMoeda(this)" placeholder="0,00" required></div>
                    <div class="col-12"><label class="small fw-bold">DESCRIÇÃO</label><input type="text" name="descricao" id="input_descricao_transacao" class="form-control" required></div>
                    
                    <div class="col-12">
                        <label class="small fw-bold">CATEGORIA</label>
                        <input type="text" id="input_busca_categoria" class="form-control" list="list_cats" placeholder="Digite para buscar..." required>
                        <input type="hidden" name="categoria_id" id="input_categoria_id_oculto">
                        <datalist id="list_cats"><?php foreach($categorias as $cat) echo "<option data-id='{$cat['id']}' value='".($cat['parent_id']?"— ":"● ")."{$cat['nome']}'>"; ?></datalist>
                    </div>

                    <div class="col-md-6"><label class="small fw-bold">CONTA</label><select name="conta_id" id="input_conta_transacao" class="form-select"><?php foreach($bancos as $b) echo "<option value='{$b['id']}'>{$b['nome']}</option>"; ?></select></div>
                    <div class="col-md-6"><label class="small fw-bold">TIPO</label><select name="tipo_transacao" id="input_tipo_transacao" class="form-select"><option value="Despesa">Despesa</option><option value="Receita">Receita</option></select></div>
                    <div class="col-12"><label class="small fw-bold">STATUS</label><select name="status_transacao" id="input_status_transacao" class="form-select"><option value="Pago">Pago</option><option value="Futuro">Futuro</option><option value="Atrasado">Atrasado</option></select></div>

                    <!-- GATILHO COMBUSTÍVEL -->
                    <div id="painel_detalhe_combustivel" style="display:none;" class="col-12 bg-light p-3 rounded border border-warning">
                        <small class="fw-bold text-warning"><i class="fas fa-gas-pump me-1"></i>DADOS DO ABASTECIMENTO</small>
                        <div class="row g-2 mt-1">
                            <div class="col-12">
                                <select name="v_id" id="input_veiculo_id" class="form-select form-select-sm">
                                    <option value="">Selecione o Carro...</option>
                                    <?php 
                                    $veics = $pdo->query("SELECT id, modelo, placa FROM minhaseconomias_veiculos WHERE usuario_id = $usuario_id")->fetchAll();
                                    foreach($veics as $v) echo "<option value='{$v['id']}'>{$v['modelo']} ({$v['placa']})</option>"; 
                                    ?>
                                </select>
                            </div>
                            <div class="col-6"><input type="number" name="km" id="input_km" class="form-control form-control-sm" placeholder="KM"></div>
                            <div class="col-6"><input type="text" name="lt" id="input_lt" class="form-control form-control-sm" placeholder="Litros"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="submit" name="btn_salvar_transacao" class="btn btn-primary w-100 rounded-pill py-3 fw-bold">SALVAR LANÇAMENTO</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: EXCLUIR -->
<div class="modal fade" id="modalExcluirTransacao" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <form action="index.php?p=transacoes&<?= $filtros_contexto_url ?>" method="POST" class="modal-content border-0 shadow text-center rounded-4">
            <div class="modal-body p-4">
                <input type="hidden" name="id_transacao_excluir" id="input_id_del_t">
                <i class="fas fa-trash text-danger fa-3x mb-3 opacity-25"></i>
                <h6 class="fw-bold">Excluir lançamento?</h6>
                <p class="small text-muted" id="txt_nome_del_t"></p>
                <div class="d-grid gap-2">
                    <button type="submit" name="btn_excluir_transacao" class="btn btn-danger rounded-pill fw-bold">Sim, Excluir</button>
                    <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Cancelar</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- SCRIPTS JAVASCRIPT -->
<script>
function mascaraMoeda(i){
    let v = i.value.replace(/\D/g,'');
    v = (v/100).toFixed(2) + '';
    v = v.replace(".", ",");
    v = v.replace(/(\d)(\d{3})(\d{3}),/g, "$1.$2.$3,");
    v = v.replace(/(\d)(\d{3}),/g, "$1.$2,");
    i.value = v;
}

// Lógica de Autocomplete e Gatilho de Combustível
document.getElementById('input_busca_categoria').addEventListener('input', function(){
    let opts = document.getElementById('list_cats').childNodes;
    let panel = document.getElementById('painel_detalhe_combustivel');
    let hiddenAtivo = document.getElementById('input_combustivel_ativo');
    for(let o of opts){
        if(o.value === this.value){
            document.getElementById('input_categoria_id_oculto').value = o.getAttribute('data-id');
            if(this.value.toLowerCase().includes('combustivel')){
                panel.style.display = 'block'; hiddenAtivo.value = '1';
            } else {
                panel.style.display = 'none'; hiddenAtivo.value = '0';
            }
            break;
        }
    }
});

function abrirNovo() {
    document.getElementById('input_id_transacao').value = '';
    document.getElementById('input_descricao_transacao').value = '';
    document.getElementById('input_valor_transacao').value = '';
    document.getElementById('input_busca_categoria').value = '';
    document.getElementById('input_categoria_id_oculto').value = '';
    document.getElementById('titulo_modal_transacao').innerText = 'Novo Lançamento';
    document.getElementById('painel_detalhe_combustivel').style.display = 'none';
    document.getElementById('input_combustivel_ativo').value = '0';
    new bootstrap.Modal(document.getElementById('modalTransacao')).show();
}

function abrirEdicaoTransacao(d) {
    // IMPORTANTE: Estes IDs devem existir no HTML acima
    document.getElementById('input_id_transacao').value = d.id;
    document.getElementById('input_data_transacao').value = d.data_vencimento;
    document.getElementById('input_descricao_transacao').value = d.descricao;
    document.getElementById('input_valor_transacao').value = parseFloat(d.valor).toLocaleString('pt-BR',{minimumFractionDigits:2});
    document.getElementById('input_busca_categoria').value = d.cat_nome;
    document.getElementById('input_categoria_id_oculto').value = d.categoria_id;
    document.getElementById('input_conta_transacao').value = d.conta_id;
    document.getElementById('input_tipo_transacao').value = d.tipo;
    document.getElementById('input_status_transacao').value = d.status;
    document.getElementById('titulo_modal_transacao').innerText = 'Editar Lançamento';
    
    // Mostra painel de combustível se a categoria for combustível
    if(d.cat_nome.toLowerCase().includes('combustivel')) {
        document.getElementById('painel_detalhe_combustivel').style.display = 'block';
        document.getElementById('input_combustivel_ativo').value = '1';
    } else {
        document.getElementById('painel_detalhe_combustivel').style.display = 'none';
        document.getElementById('input_combustivel_ativo').value = '0';
    }
    
    new bootstrap.Modal(document.getElementById('modalTransacao')).show();
}

function confirmarExclusaoTransacao(id, nome) {
    document.getElementById('input_id_del_t').value = id;
    document.getElementById('txt_nome_del_t').innerText = nome;
    new bootstrap.Modal(document.getElementById('modalExcluirTransacao')).show();
}
</script>