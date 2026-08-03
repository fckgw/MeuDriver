<?php
/**
 * BDSoft Workspace - Minhas Economias
 * View: Transações Completa - Máscara Monetária, Auto-complete e Status
 */

$usuario_id = $_SESSION['usuario_id'];

// --- CAPTURA DE FILTROS ---
$f_status = $_GET['f_status'] ?? '';
$f_tipo   = $_GET['f_tipo'] ?? '';

// --- BUSCAS PARA O MODAL (SELECTS E AUTO-COMPLETE) ---
// Contas
$stmt_contas = $pdo->prepare("SELECT id, nome FROM minhaseconomias_contas WHERE usuario_id = ? AND status = 1 ORDER BY nome ASC");
$stmt_contas->execute([$usuario_id]);
$lista_contas = $stmt_contas->fetchAll(PDO::FETCH_ASSOC);

// Categorias para o Datalist
$stmt_cats = $pdo->prepare("SELECT id, nome FROM minhaseconomias_categorias WHERE usuario_id = ? ORDER BY nome ASC");
$stmt_cats->execute([$usuario_id]);
$lista_categorias = $stmt_cats->fetchAll(PDO::FETCH_ASSOC);

// --- CONSULTA DA TABELA ---
$sql = "SELECT m.*, c.nome as cat_nome, b.nome as banco_nome 
        FROM minhaseconomias_movimentacoes m 
        LEFT JOIN minhaseconomias_categorias c ON m.categoria_id = c.id 
        LEFT JOIN minhaseconomias_contas b ON m.conta_id = b.id 
        WHERE m.usuario_id = ? AND m.data_vencimento BETWEEN ? AND ?";

$params = [$usuario_id, $data_inicio, $data_fim];

if ($f_status) { $sql .= " AND m.status = ?"; $params[] = $f_status; }
if ($f_tipo)   { $sql .= " AND m.tipo = ?";   $params[] = $f_tipo; }

$stmt_trans = $pdo->prepare($sql . " ORDER BY m.data_vencimento DESC");
$stmt_trans->execute($params);
$lancamentos = $stmt_trans->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="card card-finance p-4 bg-white shadow-sm border-0 mb-4">
    <!-- Cabeçalho de Ações -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h5 class="fw-bold m-0"><i class="fas fa-list-ul me-2 text-primary"></i>Movimentações</h5>
            <small class="text-muted">Período: <?= date('d/m/Y', strtotime($data_inicio)) ?> a <?= date('d/m/Y', strtotime($data_fim)) ?></small>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php?p=bi" class="btn btn-info btn-sm rounded-pill px-3 text-white shadow-sm fw-bold">
                <i class="fas fa-robot me-1"></i> BI
            </a>
            <button class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm fw-bold" onclick="abrirModalLancamento()">
                <i class="fas fa-plus me-1"></i> LANÇAR
            </button>
            <a href="exportar.php?<?= $_SERVER['QUERY_STRING'] ?>" class="btn btn-outline-success btn-sm rounded-pill px-3 fw-bold">
                <i class="fas fa-file-excel me-1"></i> EXPORTAR
            </a>
            <a href="index.php?p=dashboard" class="btn btn-outline-secondary btn-sm rounded-pill px-3 fw-bold">VOLTAR</a>
        </div>
    </div>

    <!-- Barra de Filtros -->
    <form method="GET" action="index.php" class="row g-2 mb-4 bg-light p-3 rounded-4 border">
        <input type="hidden" name="p" value="transacoes">
        <div class="col-md-3">
            <label class="small fw-bold text-muted">INÍCIO</label>
            <input type="date" name="data_inicio" class="form-control form-control-sm rounded-pill px-3" value="<?= $data_inicio ?>">
        </div>
        <div class="col-md-3">
            <label class="small fw-bold text-muted">FIM</label>
            <input type="date" name="data_fim" class="form-control form-control-sm rounded-pill px-3" value="<?= $data_fim ?>">
        </div>
        <div class="col-md-2">
            <label class="small fw-bold text-muted">STATUS</label>
            <select name="f_status" class="form-select form-select-sm rounded-pill px-3">
                <option value="">Todos</option>
                <option value="Pago" <?= $f_status == 'Pago' ? 'selected' : '' ?>>Pago</option>
                <option value="Futuro" <?= $f_status == 'Futuro' ? 'selected' : '' ?>>Futuro</option>
                <option value="Atrasado" <?= $f_status == 'Atrasado' ? 'selected' : '' ?>>Atrasado</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="small fw-bold text-muted">TIPO</label>
            <select name="f_tipo" class="form-select form-select-sm rounded-pill px-3">
                <option value="">Todos</option>
                <option value="Receita" <?= $f_tipo == 'Receita' ? 'selected' : '' ?>>Receita</option>
                <option value="Despesa" <?= $f_tipo == 'Despesa' ? 'selected' : '' ?>>Despesa</option>
            </select>
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-dark btn-sm w-100 rounded-pill fw-bold">FILTRAR</button>
        </div>
    </form>

    <!-- Tabela -->
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr class="small text-muted text-uppercase">
                    <th>Data</th>
                    <th>Descrição</th>
                    <th>Valor</th>
                    <th>Status</th>
                    <th class="text-center">BI</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lancamentos as $l): ?>
                <tr style="font-size: 13px;">
                    <td><?= date('d/m/Y', strtotime($l['data_vencimento'])) ?></td>
                    <td>
                        <div class="fw-bold text-dark"><?= htmlspecialchars($l['descricao']) ?></div>
                        <small class="text-muted"><?= $l['cat_nome'] ?? 'S/ Cat' ?> | <?= $l['banco_nome'] ?? 'S/ Conta' ?></small>
                    </td>
                    <td class="<?= $l['tipo'] == 'Receita' ? 'text-success' : 'text-danger' ?> fw-bold">
                        R$ <?= number_format($l['valor'], 2, ',', '.') ?>
                    </td>
                    <td>
                        <?php 
                        $badge = ['Pago'=>'bg-success', 'Futuro'=>'bg-primary', 'Atrasado'=>'bg-danger'];
                        echo "<span class='badge {$badge[$l['status']]} rounded-pill px-3'>{$l['status']}</span>";
                        ?>
                    </td>
                    <td class="text-center">
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="id_transacao" value="<?= $l['id'] ?>">
                            <input type="hidden" name="status_bi" value="<?= $l['bi_analise'] ? '0' : '1' ?>">
                            <button type="submit" name="btn_flag_bi" class="btn btn-sm <?= $l['bi_analise'] ? 'btn-warning shadow-sm' : 'btn-outline-light border text-dark' ?> rounded-circle">
                                <i class="fas fa-robot"></i>
                            </button>
                        </form>
                    </td>
                    <td class="text-end">
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Excluir?')">
                            <input type="hidden" name="id_transacao_excluir" value="<?= $l['id'] ?>">
                            <button type="submit" name="btn_excluir_transacao" class="btn btn-sm text-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ============================================================
     MODAL DE LANÇAMENTO (POP-UP)
     ============================================================ -->
<div class="modal fade" id="modalLancamento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" id="formNovoLancamento" class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-dark text-white border-0">
                <h5 class="modal-title fw-bold"><i class="fas fa-plus-circle me-2"></i>Novo Lançamento</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                
                <div class="mb-3">
                    <label class="small fw-bold text-muted">DESCRIÇÃO</label>
                    <input type="text" name="descricao" class="form-control rounded-3" required>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label class="small fw-bold text-muted">VALOR (R$)</label>
                        <!-- Máscara aplicada via JS e inputmode numeric para mobile -->
                        <input type="text" name="valor" id="input_valor_mask" class="form-control rounded-3 fw-bold text-primary" 
                               required placeholder="0,00" inputmode="numeric">
                    </div>
                    <div class="col-6">
                        <label class="small fw-bold text-muted">DATA</label>
                        <input type="date" name="data_transacao" class="form-control rounded-3" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label class="small fw-bold text-muted">TIPO</label>
                        <select name="tipo_transacao" class="form-select rounded-3">
                            <option value="Despesa">Despesa (Saída)</option>
                            <option value="Receita">Receita (Entrada)</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="small fw-bold text-muted">STATUS</label>
                        <select name="status_transacao" class="form-select rounded-3">
                            <option value="Pago">Pago / Recebido</option>
                            <option value="Futuro">Pendente (Futuro)</option>
                            <option value="Atrasado">Atrasado</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="small fw-bold text-muted">CONTA / BANCO</label>
                    <select name="conta_id" class="form-select rounded-3">
                        <?php foreach ($lista_contas as $conta): ?>
                            <option value="<?= $conta['id'] ?>"><?= $conta['nome'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="small fw-bold text-muted">CATEGORIA (AUTO-COMPLETE)</label>
                    <input type="text" id="input_cat_ac" list="datalist_cats" class="form-control rounded-3" placeholder="Digite a categoria..." required>
                    <datalist id="datalist_cats">
                        <?php foreach ($lista_categorias as $cat): ?>
                            <option data-id="<?= $cat['id'] ?>" value="<?= $cat['nome'] ?>">
                        <?php endforeach; ?>
                    </datalist>
                    <input type="hidden" name="categoria_id" id="id_cat_hidden">
                </div>

            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="submit" name="btn_salvar_transacao" class="btn btn-primary w-100 rounded-pill py-3 fw-bold shadow">
                    CONCLUIR LANÇAMENTO
                </button>
            </div>
        </form>
    </div>
</div>

<script>
/**
 * MÁSCARA MONETÁRIA (PADRÃO 0,00)
 * Impede a entrada de qualquer caractere que não seja número.
 */
document.getElementById('input_valor_mask').addEventListener('input', function (e) {
    let value = e.target.value.replace(/\D/g, '');
    value = (value / 100).toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
    e.target.value = value;
});

/**
 * LÓGICA AUTO-COMPLETE CATEGORIA
 */
document.getElementById('input_cat_ac').addEventListener('input', function() {
    const val = this.value;
    const options = document.getElementById('datalist_cats').childNodes;
    let foundId = "";
    for (let i = 0; i < options.length; i++) {
        if (options[i].value === val) {
            foundId = options[i].getAttribute('data-id');
            break;
        }
    }
    document.getElementById('id_cat_hidden').value = foundId;
});

/**
 * VALIDAÇÃO ANTES DE ENVIAR
 * Garante que a categoria foi selecionada corretamente do datalist (ID preenchido)
 */
document.getElementById('formNovoLancamento').addEventListener('submit', function(e) {
    const catId = document.getElementById('id_cat_hidden').value;
    if (!catId) {
        alert("Por favor, selecione uma categoria válida da lista.");
        e.preventDefault();
        return false;
    }
});

function abrirModalLancamento() {
    const m = new bootstrap.Modal(document.getElementById('modalLancamento'));
    m.show();
}
</script>