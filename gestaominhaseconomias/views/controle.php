<?php
/**
 * View: Controle de Frota e Consumo
 * Localização: gestaominhaseconomias/views/controle.php
 */
$usuario_id = $_SESSION['usuario_id'];

// 1. Busca os Veículos para os Seletores
$query_veiculos = $pdo->prepare("SELECT * FROM minhaseconomias_veiculos WHERE usuario_id = ? ORDER BY modelo ASC");
$query_veiculos->execute([$usuario_id]);
$meus_veiculos = $query_veiculos->fetchAll(PDO::FETCH_ASSOC);

// 2. Busca o Histórico de Abastecimentos
$query_historico = $pdo->prepare("
    SELECT abast.*, veic.modelo, veic.placa 
    FROM minhaseconomias_combustivel as abast 
    JOIN minhaseconomias_veiculos as veic ON abast.veiculo_id = veic.id 
    WHERE abast.usuario_id = ? 
    ORDER BY abast.data_abastecimento DESC, abast.km_atual DESC
");
$query_historico->execute([$usuario_id]);
$lista_abastecimentos = $query_historico->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-md-6">
            <h4 class="fw-bold"><i class="fas fa-gas-pump text-primary me-2"></i>Controle de Abastecimentos</h4>
        </div>
        <div class="col-md-6 text-end">
            <button class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" onclick="abrirModalNovoAbastecimento()">
                <i class="fas fa-plus me-1"></i> NOVO ABASTECIMENTO
            </button>
        </div>
    </div>

    <!-- TABELA DE HISTÓRICO -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white border-0 pt-3 pb-2">
            <h6 class="fw-bold mb-0 text-muted text-uppercase" style="font-size: 12px;">Últimos Abastecimentos</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr class="text-muted small" style="font-size: 10px;">
                        <th class="ps-4">DATA</th>
                        <th>VEÍCULO</th>
                        <th>KM ATUAL</th>
                        <th>KM RODADO</th>
                        <th>LITROS</th>
                        <th>MÉDIA</th>
                        <th>VALOR TOTAL</th>
                        <th class="text-center">AÇÕES</th>
                    </tr>
                </thead>
                <tbody style="font-size: 13px;">
                    <?php if (empty($lista_abastecimentos)): ?>
                        <tr><td colspan="8" class="text-center py-5 text-muted">Nenhum abastecimento registrado.</td></tr>
                    <?php endif; ?>
                    
                    <?php foreach ($lista_abastecimentos as $item): ?>
                    <tr>
                        <td class="ps-4"><?= date('d/m/Y', strtotime($item['data_abastecimento'])) ?></td>
                        <td class="fw-bold text-dark"><?= htmlspecialchars($item['modelo']) ?> <small class='text-muted'>(<?= htmlspecialchars($item['placa']) ?>)</small></td>
                        <td><?= number_format($item['km_atual'], 0, ',', '.') ?> km</td>
                        <td class="text-primary fw-bold">+ <?= number_format($item['km_rodado'], 1, ',', '.') ?> km</td>
                        <td><?= number_format($item['litros'], 2, ',', '.') ?> L</td>
                        <td>
                            <span class="badge bg-success-soft text-success border border-success fw-bold">
                                <?= number_format($item['media_kml'], 2, ',', '.') ?> km/L
                            </span>
                        </td>
                        <td class="fw-bold">R$ <?= number_format($item['valor_total'], 2, ',', '.') ?></td>
                        <td class="text-center">
                            <button class="btn btn-link btn-sm text-primary p-1" title="Editar" onclick='abrirEdicaoAbastecimento(<?= json_encode($item) ?>)'>
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-link btn-sm text-danger p-1" title="Excluir" onclick="confirmarExclusaoAbastecimento(<?= $item['id'] ?>, '<?= date('d/m/Y', strtotime($item['data_abastecimento'])) ?>')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL: CADASTRAR / EDITAR ABASTECIMENTO -->
<div class="modal fade" id="modalAbastecimento" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form action="index.php?p=controle" method="POST" class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="fw-bold" id="titulo_modal_abastecimento">Novo Registro</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 pt-0">
                <input type="hidden" name="id_abastecimento" id="input_id_abastecimento">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="small fw-bold">VEÍCULO</label>
                        <select name="veiculo_id" id="select_veiculo" class="form-select" required>
                            <option value="">Selecione um carro...</option>
                            <?php foreach ($meus_veiculos as $veic): ?>
                                <option value="<?= $veic['id'] ?>"><?= $veic['modelo'] ?> (<?= $veic['placa'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="small fw-bold">DATA</label>
                        <input type="date" name="data_abastecimento" id="input_data" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="small fw-bold">KM ATUAL</label>
                        <input type="number" name="km_atual" id="input_km" class="form-control" placeholder="0" required>
                    </div>
                    <div class="col-md-6">
                        <label class="small fw-bold">LITROS</label>
                        <input type="text" name="litros" id="input_litros" class="form-control" placeholder="0,00" oninput="mascaraDecimal(this)" required>
                    </div>
                    <div class="col-md-6">
                        <label class="small fw-bold">VALOR TOTAL (R$)</label>
                        <input type="text" name="valor_total" id="input_valor" class="form-control fw-bold text-primary" placeholder="0,00" oninput="mascaraDecimal(this)" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="submit" name="btn_salvar_abastecimento" class="btn btn-primary w-100 rounded-pill py-3 fw-bold">SALVAR REGISTRO</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: CONFIRMAR EXCLUSÃO -->
<div class="modal fade" id="modalExcluirAbastecimento" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <form action="index.php?p=controle" method="POST" class="modal-content border-0 shadow text-center rounded-4">
            <div class="modal-body p-5">
                <input type="hidden" name="id_abastecimento_excluir" id="input_id_excluir">
                <i class="fas fa-trash text-danger fa-3x mb-3 opacity-25"></i>
                <h6 class="fw-bold">Excluir este registro?</h6>
                <p class="small text-muted" id="texto_data_exclusao"></p>
                <div class="d-grid gap-2 mt-4">
                    <button type="submit" name="btn_excluir_abastecimento" class="btn btn-danger rounded-pill fw-bold">SIM, EXCLUIR</button>
                    <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">CANCELAR</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
// Máscara para campos de dinheiro e decimais
function mascaraDecimal(i) {
    let v = i.value.replace(/\D/g,'');
    v = (v/100).toFixed(2) + '';
    v = v.replace(".", ",");
    v = v.replace(/(\d)(\d{3})(\d{3}),/g, "$1.$2.$3,");
    v = v.replace(/(\d)(\d{3}),/g, "$1.$2,");
    i.value = v;
}

function abrirModalNovoAbastecimento() {
    document.getElementById('input_id_abastecimento').value = '';
    document.getElementById('select_veiculo').value = '';
    document.getElementById('input_km').value = '';
    document.getElementById('input_litros').value = '';
    document.getElementById('input_valor').value = '';
    document.getElementById('input_data').value = '<?= date('Y-m-d') ?>';
    document.getElementById('titulo_modal_abastecimento').innerText = 'Novo Registro';
    new bootstrap.Modal(document.getElementById('modalAbastecimento')).show();
}

function abrirEdicaoAbastecimento(dados) {
    document.getElementById('input_id_abastecimento').value = dados.id;
    document.getElementById('select_veiculo').value = dados.veiculo_id;
    document.getElementById('input_data').value = dados.data_abastecimento;
    document.getElementById('input_km').value = dados.km_atual;
    // Formata os valores para exibição no modal com vírgula
    document.getElementById('input_litros').value = parseFloat(dados.litros).toLocaleString('pt-BR', {minimumFractionDigits: 2});
    document.getElementById('input_valor').value = parseFloat(dados.valor_total).toLocaleString('pt-BR', {minimumFractionDigits: 2});
    
    document.getElementById('titulo_modal_abastecimento').innerText = 'Editar Registro';
    new bootstrap.Modal(document.getElementById('modalAbastecimento')).show();
}

function confirmarExclusaoAbastecimento(id, data) {
    document.getElementById('input_id_excluir').value = id;
    document.getElementById('texto_data_exclusao').innerText = 'Abastecimento do dia ' + data;
    new bootstrap.Modal(document.getElementById('modalExcluirAbastecimento')).show();
}
</script>