<?php
/**
 * BDSoft Workspace - Minhas Economias
 * View: Transações (Grid Completo com Filtros e Exportação)
 */
$usuario_id = $_SESSION['usuario_id'];

// --- 1. CAPTURA DE FILTROS ESPECÍFICOS ---
$f_banco  = $_GET['f_banco'] ?? '';
$f_status = $_GET['f_status'] ?? '';
$f_tipo   = $_GET['f_tipo'] ?? '';
$venc_hoje = isset($_GET['vencimento_hoje']) ? true : false;

// --- 2. BUSCAR DADOS PARA OS DROPBOXES ---
$lista_bancos = $pdo->query("SELECT id, nome FROM minhaseconomias_contas WHERE usuario_id = $usuario_id AND status = 1 ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
$lista_cats = $pdo->query("SELECT id, nome FROM minhaseconomias_categorias WHERE usuario_id = $usuario_id ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);

// --- 3. CONSTRUÇÃO DA QUERY DINÂMICA ---
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
    $params[] = $mes_filtro; 
    $params[] = $ano_filtro;

    if($f_banco)  { $sql .= " AND m.conta_id = ?"; $params[] = $f_banco; }
    if($f_status) { $sql .= " AND m.status = ?"; $params[] = $f_status; }
    if($f_tipo)   { $sql .= " AND m.tipo = ?"; $params[] = $f_tipo; }
}

$stmt = $pdo->prepare($sql . " ORDER BY m.data_vencimento DESC");
$stmt->execute($params);
$lancamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="card card-finance p-4 bg-white shadow-sm border-0 mb-4">
    <!-- Cabeçalho com Ações -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h5 class="fw-bold m-0 text-dark">
                <i class="fas fa-exchange-alt me-2 text-primary"></i>
                <?= $venc_hoje ? "Vencimentos de Hoje" : "Extrato de Transações" ?>
            </h5>
            <small class="text-muted"><?= $venc_hoje ? date('d/m/Y') : "Período: $mes_filtro/$ano_filtro" ?></small>
        </div>
        <div class="d-flex gap-2">
            <!-- Botões de Exportação -->
            <a href="exportar.php?type=excel&<?= http_build_query($_GET) ?>" class="btn btn-outline-success btn-sm rounded-pill px-3 fw-bold">
                <i class="fas fa-file-excel me-1"></i> Excel
            </a>
            <a href="exportar.php?type=pdf&<?= http_build_query($_GET) ?>" class="btn btn-outline-danger btn-sm rounded-pill px-3 fw-bold">
                <i class="fas fa-file-pdf me-1"></i> PDF
            </a>
            <button class="btn btn-success btn-sm rounded-pill px-4 fw-bold shadow-sm" onclick="mostrarLinhaAdicionar()">
                <i class="fas fa-plus me-1"></i> NOVO
            </button>
        </div>
    </div>

    <!-- Barra de Filtros Avançados -->
    <form method="GET" class="row g-2 mb-4 bg-light p-3 rounded-4 border">
        <input type="hidden" name="p" value="transacoes">
        <div class="col-md-2">
            <select name="mes" class="form-select form-select-sm border-0 shadow-sm">
                <?php $m_nomes = ["01"=>"Jan","02"=>"Fev","03"=>"Mar","04"=>"Abr","05"=>"Mai","06"=>"Jun","07"=>"Jul","08"=>"Ago","09"=>"Set","10"=>"Out","11"=>"Nov","12"=>"Dez"];
                foreach($m_nomes as $k=>$v) echo "<option value='$k' ".($mes_filtro==$k?'selected':'').">$v</option>"; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select name="ano" class="form-select form-select-sm border-0 shadow-sm">
                <?php for($i=2024; $i<=2026; $i++) echo "<option value='$i' ".($ano_filtro==$i?'selected':'').">$i</option>"; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select name="f_banco" class="form-select form-select-sm border-0 shadow-sm">
                <option value="">Todos Bancos</option>
                <?php foreach($lista_bancos as $b) echo "<option value='{$b['id']}' ".($f_banco==$b['id']?'selected':'').">{$b['nome']}</option>"; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select name="f_status" class="form-select form-select-sm border-0 shadow-sm">
                <option value="">Todos Status</option>
                <option value="Pago" <?= $f_status=='Pago'?'selected':'' ?>>Pago</option>
                <option value="Futuro" <?= $f_status=='Futuro'?'selected':'' ?>>Futuro</option>
                <option value="Atrasado" <?= $f_status=='Atrasado'?'selected':'' ?>>Atrasado</option>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-dark btn-sm w-100 rounded-pill fw-bold">FILTRAR</button>
        </div>
        <div class="col-md-2">
            <a href="index.php?p=transacoes" class="btn btn-link btn-sm text-muted">Limpar</a>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr class="small text-muted text-uppercase" style="font-size: 10px;">
                    <th>Vencimento</th>
                    <th>Descrição</th>
                    <th>Conta</th>
                    <th>Valor</th>
                    <th>Status</th>
                    <th class="text-center">Ações</th>
                </tr>
            </thead>
            <tbody>
                <!-- LINHA DINÂMICA: ADIÇÃO/EDIÇÃO -->
                <tr id="linhaAdd" style="display: none; background: #e8f0fe;">
                    <form method="POST">
                        <input type="hidden" name="id_transacao" id="id_transacao">
                        <td><input type="date" name="data_transacao" id="data_t" class="form-control form-control-sm border-0" required></td>
                        <td><input type="text" name="descricao" id="desc_t" class="form-control form-control-sm border-0" placeholder="Ex: Internet" required></td>
                        <td>
                            <select name="conta_id" id="conta_t" class="form-select form-select-sm border-0" required>
                                <?php foreach($lista_bancos as $b) echo "<option value='{$b['id']}'>{$b['nome']}</option>"; ?>
                            </select>
                        </td>
                        <td><input type="text" name="valor" id="valorTransacao" class="form-control form-control-sm border-0 fw-bold" placeholder="0,00" required></td>
                        <td>
                            <select name="status_transacao" id="status_t" class="form-select form-select-sm border-0 fw-bold">
                                <option value="Futuro">Futuro</option>
                                <option value="Atrasado">Atrasado</option>
                                <option value="Pago">Pago</option>
                            </select>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <select name="tipo_transacao" id="tipo_t" class="form-select form-select-sm border-0">
                                    <option value="Despesa">Saída</option>
                                    <option value="Receita">Entrada</option>
                                </select>
                                <button type="submit" name="btn_salvar_transacao" class="btn btn-sm btn-primary rounded-circle"><i class="fas fa-check"></i></button>
                                <button type="button" class="btn btn-sm btn-light border rounded-circle" onclick="ocultarLinhaAdicionar()"><i class="fas fa-times"></i></button>
                            </div>
                        </td>
                        <input type="hidden" name="categoria_id" id="cat_t" value="1">
                    </form>
                </tr>

                <?php foreach($lancamentos as $l): ?>
                <tr style="font-size: 13px;">
                    <td class="text-muted"><?= date('d/m/y', strtotime($l['data_vencimento'])) ?></td>
                    <td class="fw-bold text-dark"><?= htmlspecialchars($l['descricao']) ?></td>
                    <td class="small"><?= $l['banco_nome'] ?></td>
                    <td class="fw-bold <?= $l['tipo']=='Receita' ? 'text-success':'text-danger' ?>">
                        R$ <?= number_format($l['valor'], 2, ',', '.') ?>
                    </td>
                    <td>
                        <?php 
                            $cor = ['Pago'=>'bg-success','Atrasado'=>'bg-danger','Futuro'=>'bg-primary'];
                            echo "<span class='badge {$cor[$l['status']]} rounded-pill px-2' style='font-size:9px;'>{$l['status']}</span>";
                        ?>
                    </td>
                    <td class="text-center">
                        <div class="btn-group">
                            <button class="btn btn-link btn-sm text-primary p-1" onclick="editarTransacao(<?= $l['id'] ?>, '<?= $l['data_vencimento'] ?>', '<?= addslashes($l['descricao']) ?>', <?= $l['categoria_id'] ?>, <?= $l['conta_id'] ?>, '<?= $l['valor'] ?>', '<?= $l['tipo'] ?>', '<?= $l['status'] ?>')">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-link btn-sm text-danger p-1" onclick="confirmarExcluirTransacao(<?= $l['id'] ?>, '<?= addslashes($l['descricao']) ?>')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($lancamentos)): ?>
                    <tr><td colspan="6" class="text-center py-5 text-muted">Nenhum registro para este período.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal de Exclusão de Transação -->
<div class="modal fade" id="modalExcluirTransacao" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg text-center rounded-4">
            <div class="modal-body p-5">
                <i class="fas fa-trash-alt text-danger fa-3x mb-3 opacity-25"></i>
                <h4 class="fw-bold">Remover Transação?</h4>
                <p class="text-muted" id="txtNomeTExcluir"></p>
                <form method="POST">
                    <input type="hidden" name="id_transacao_excluir" id="id_t_excluir">
                    <div class="d-grid gap-2">
                        <button type="submit" name="btn_excluir_transacao" class="btn btn-danger rounded-pill py-2 fw-bold shadow">Confirmar Exclusão</button>
                        <button type="button" class="btn btn-light rounded-pill py-2" data-bs-dismiss="modal">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>