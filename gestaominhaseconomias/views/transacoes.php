<?php
/**
 * View: Transações com Filtros Avançados e Exportação
 */
$usuario_id = $_SESSION['usuario_id'];

// --- CAPTURA DE FILTROS ESPECÍFICOS ---
$f_banco = $_GET['f_banco'] ?? '';
$f_tipo = $_GET['f_tipo'] ?? '';
$f_cat = $_GET['f_cat'] ?? '';

// 1. Carregar Dados para os Filtros
$bancos = $pdo->prepare("SELECT id, nome FROM minhaseconomias_contas WHERE usuario_id = ? AND status = 1");
$bancos->execute([$usuario_id]);
$lista_bancos = $bancos->fetchAll(PDO::FETCH_ASSOC);

$categorias = $pdo->prepare("SELECT id, nome FROM minhaseconomias_categorias WHERE usuario_id = ? ORDER BY nome ASC");
$categorias->execute([$usuario_id]);
$lista_cats = $categorias->fetchAll(PDO::FETCH_ASSOC);

// 2. CONSTRUÇÃO DA QUERY DINÂMICA
$sql = "SELECT m.*, c.nome as cat_nome, b.nome as banco_nome 
        FROM minhaseconomias_movimentacoes m 
        LEFT JOIN minhaseconomias_categorias c ON m.categoria_id = c.id 
        LEFT JOIN minhaseconomias_contas b ON m.conta_id = b.id 
        WHERE m.usuario_id = ? AND MONTH(m.data_pagamento) = ? AND YEAR(m.data_pagamento) = ?";

$params = [$usuario_id, $mes_filtro, $ano_filtro];

if (!empty($f_banco)) { $sql .= " AND m.conta_id = ?"; $params[] = $f_banco; }
if (!empty($f_tipo))  { $sql .= " AND m.tipo = ?"; $params[] = $f_tipo; }
if (!empty($f_cat))   { $sql .= " AND m.categoria_id = ?"; $params[] = $f_cat; }

$sql .= " ORDER BY m.data_pagamento DESC, m.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$lancamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="card card-finance p-4 bg-white shadow-sm border-0 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="fw-bold m-0 text-dark"><i class="fas fa-list-ul me-2 text-primary"></i>Transações Detalhadas</h5>
        <div class="d-flex gap-2">
            <!-- BOTÕES EXPORTAR -->
            <a href="exportar.php?type=excel&<?= http_build_query($_GET) ?>" class="btn btn-outline-success btn-sm rounded-pill px-3 fw-bold">
                <i class="fas fa-file-excel me-1"></i> Excel
            </a>
            <a href="exportar.php?type=pdf&<?= http_build_query($_GET) ?>" class="btn btn-outline-danger btn-sm rounded-pill px-3 fw-bold">
                <i class="fas fa-file-pdf me-1"></i> PDF
            </a>
            <button class="btn btn-primary btn-sm rounded-pill px-3 fw-bold" onclick="mostrarLinhaAdicionar()">
                <i class="fas fa-plus me-1"></i> Adicionar
            </button>
        </div>
    </div>

    <!-- FILTROS AVANÇADOS -->
    <form method="GET" class="row g-2 mb-4 bg-light p-3 rounded-4 border">
        <input type="hidden" name="p" value="transacoes">
        <div class="col-md-2">
            <select name="mes" class="form-select form-select-sm border-0 shadow-sm">
                <?php $m_nomes = ["01"=>"Jan","02"=>"Fev","03"=>"Mar","04"=>"Abr","05"=>"Mai","06"=>"Jun","07"=>"Jul","08"=>"Ago","09"=>"Set","10"=>"Out","11"=>"Nov","12"=>"Dez"];
                foreach($m_nomes as $k=>$v) echo "<option value='$k' ".($mes_filtro==$k?'selected':'').">$v</option>"; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select name="f_banco" class="form-select form-select-sm border-0 shadow-sm">
                <option value="">Todos Bancos</option>
                <?php foreach($lista_bancos as $b) echo "<option value='{$b['id']}' ".($f_banco==$b['id']?'selected':'').">{$b['nome']}</option>"; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select name="f_tipo" class="form-select form-select-sm border-0 shadow-sm">
                <option value="">Todos Tipos</option>
                <option value="Receita" <?= $f_tipo=='Receita'?'selected':'' ?>>Receita</option>
                <option value="Despesa" <?= $f_tipo=='Despesa'?'selected':'' ?>>Despesa</option>
            </select>
        </div>
        <div class="col-md-2">
            <select name="f_cat" class="form-select form-select-sm border-0 shadow-sm">
                <option value="">Todas Categorias</option>
                <?php foreach($lista_cats as $c) echo "<option value='{$c['id']}' ".($f_cat==$c['id']?'selected':'').">{$c['nome']}</option>"; ?>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-dark btn-sm w-100 rounded-pill fw-bold">Filtrar</button>
        </div>
        <div class="col-md-2">
            <a href="index.php?p=transacoes" class="btn btn-link btn-sm text-muted">Limpar</a>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr class="small text-muted text-uppercase">
                    <th>Data</th>
                    <th>Descrição</th>
                    <th>Categoria</th>
                    <th>Banco</th>
                    <th>Valor</th>
                    <th class="text-center">Ações</th>
                </tr>
            </thead>
            <tbody>
                <!-- LINHA ADICIONAR (Sempre pronta no topo) -->
                <tr id="linhaAdd" style="display:none; background:#f8f9ff;">
                    <form method="POST">
                        <input type="hidden" name="id_transacao" id="id_transacao">
                        <td><input type="date" name="data_transacao" id="data_t" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>"></td>
                        <td><input type="text" name="descricao" id="desc_t" class="form-control form-control-sm" placeholder="Descrição"></td>
                        <td>
                            <select name="categoria_id" id="cat_t" class="form-select form-select-sm">
                                <?php foreach($lista_cats as $c) echo "<option value='{$c['id']}'>{$c['nome']}</option>"; ?>
                            </select>
                        </td>
                        <td>
                            <select name="conta_id" id="conta_t" class="form-select form-select-sm">
                                <?php foreach($lista_bancos as $b) echo "<option value='{$b['id']}'>{$b['nome']}</option>"; ?>
                            </select>
                        </td>
                        <td><input type="text" name="valor" id="valorTransacao" class="form-control form-control-sm" placeholder="0,00"></td>
                        <td>
                            <div class="d-flex gap-1">
                                <select name="tipo_transacao" id="tipo_t" class="form-select form-select-sm" style="width:90px;">
                                    <option value="Despesa">Saída</option>
                                    <option value="Receita">Entrada</option>
                                </select>
                                <button type="submit" name="btn_salvar_transacao" class="btn btn-sm btn-primary"><i class="fas fa-check"></i></button>
                                <button type="button" class="btn btn-sm btn-light" onclick="ocultarLinhaAdicionar()"><i class="fas fa-times"></i></button>
                            </div>
                        </td>
                    </form>
                </tr>

                <?php foreach($lancamentos as $l): ?>
                <tr>
                    <td class="small text-muted"><?= date('d/m/y', strtotime($l['data_pagamento'])) ?></td>
                    <td class="fw-bold"><?= htmlspecialchars($l['descricao']) ?></td>
                    <td><span class="badge bg-light text-muted border"><?= $l['cat_nome'] ?></span></td>
                    <td class="small"><?= $l['banco_nome'] ?></td>
                    <td class="fw-bold <?= $l['tipo']=='Receita'?'text-success':'text-danger' ?>">
                        R$ <?= number_format($l['valor'], 2, ',', '.') ?>
                    </td>
                    <td class="text-center">
                        <button class="btn btn-link btn-sm text-primary p-0 me-2" onclick="editarTransacao(<?= $l['id'] ?>, '<?= $l['data_pagamento'] ?>', '<?= addslashes($l['descricao']) ?>', <?= $l['categoria_id'] ?>, <?= $l['conta_id'] ?>, '<?= $l['valor'] ?>', '<?= $l['tipo'] ?>')"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-link btn-sm text-danger p-0" onclick="confirmarExcluirTransacao(<?= $l['id'] ?>, '<?= addslashes($l['descricao']) ?>')"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Excluir Transação -->
<div class="modal fade" id="modalExcluirTransacao" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg text-center rounded-4">
            <div class="modal-body p-5">
                <i class="fas fa-trash text-danger fa-3x mb-3 opacity-25"></i>
                <h5 class="fw-bold">Excluir Transação?</h5>
                <p class="text-muted" id="txtNomeTExcluir"></p>
                <form method="POST">
                    <input type="hidden" name="id_transacao_excluir" id="id_t_excluir">
                    <button type="submit" name="btn_excluir_transacao" class="btn btn-danger rounded-pill px-4 fw-bold">Sim, Excluir</button>
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                </form>
            </div>
        </div>
    </div>
</div>