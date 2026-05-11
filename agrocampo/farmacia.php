<?php
/**
 * BDSoft Workspace - FARMÁCIA RURAL
 * Localização: agrocampo/farmacia.php
 */
session_start();
require_once '../config.php';

if (!isset($_SESSION['usuario_id'])) { header("Location: ../login.php"); exit; }

$user_id = $_SESSION['usuario_id'];
$hoje = date('Y-m-d');

// Busca estoque atual
$stmt = $pdo->prepare("SELECT * FROM agro_farmacia_estoque WHERE usuario_id = ? ORDER BY data_validade ASC");
$stmt->execute([$user_id]);
$estoque = $stmt->fetchAll(PDO::FETCH_ASSOC);

// KPIs
$stmt_vencendo = $pdo->prepare("SELECT COUNT(*) FROM agro_farmacia_estoque WHERE usuario_id = ? AND data_validade <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
$stmt_vencendo->execute([$user_id]);
$vencendo_30_dias = $stmt_vencendo->fetchColumn();

$stmt_baixo = $pdo->prepare("SELECT COUNT(*) FROM agro_farmacia_estoque WHERE usuario_id = ? AND quantidade_atual <= estoque_minimo");
$stmt_baixo->execute([$user_id]);
$estoque_baixo = $stmt_baixo->fetchColumn();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Farmácia Rural - AgroCampo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f7f4; display: flex; font-family: 'Segoe UI', sans-serif; }
        .main-wrapper { flex: 1; margin-left: 280px; padding: 40px; }
        .card-farmacia { border: none; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); background: #fff; }
        .vencido { background-color: #ffdce0 !important; }
        @media (max-width: 991px) { .main-wrapper { margin-left: 0; width: 100%; padding: 20px; } }
    </style>
</head>
<body>

<?php include 'sidebar_agro.php'; ?>

<div class="main-wrapper">
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h2 class="fw-bold text-dark mb-0"><i class="fas fa-first-aid text-danger me-2"></i>Farmácia Rural</h2>
            <p class="text-muted">Controle de medicamentos, vacinas e validade.</p>
        </div>
        <button class="btn btn-primary rounded-pill px-4 fw-bold shadow" data-bs-toggle="modal" data-bs-target="#modalNovoProduto">
            <i class="fas fa-plus me-2"></i>CADASTRAR PRODUTO
        </button>
    </div>

    <!-- INDICADORES -->
    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="card card-farmacia p-3 border-start border-danger border-5">
                <small class="fw-bold text-muted">VENCENDO EM 30 DIAS</small>
                <h3 class="text-danger fw-bold mb-0"><?php echo $vencendo_30_dias; ?> Itens</h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-farmacia p-3 border-start border-warning border-5">
                <small class="fw-bold text-muted">ESTOQUE BAIXO</small>
                <h3 class="text-warning fw-bold mb-0"><?php echo $estoque_baixo; ?> Itens</h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-farmacia p-3 border-start border-primary border-5">
                <small class="fw-bold text-muted">TOTAL EM ESTOQUE</small>
                <h3 class="text-primary fw-bold mb-0"><?php echo count($estoque); ?> Produtos</h3>
            </div>
        </div>
    </div>

    <!-- GRID DE ESTOQUE -->
    <div class="card card-farmacia overflow-hidden shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-uppercase small fw-bold">
                    <tr>
                        <th class="ps-4">Produto</th>
                        <th>Categoria</th>
                        <th>Lote</th>
                        <th>Validade</th>
                        <th>Estoque</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($estoque as $e): 
                        $is_vencido = ($e['data_validade'] < $hoje);
                        $is_baixo = ($e['quantidade_atual'] <= $e['estoque_minimo']);
                    ?>
                    <tr class="<?php echo $is_vencido ? 'vencido' : ''; ?>">
                        <td class="ps-4">
                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($e['nome_produto']); ?></div>
                            <small class="text-muted"><?php echo $e['unidade_medida']; ?></small>
                        </td>
                        <td><span class="badge bg-light text-dark border"><?php echo $e['categoria']; ?></span></td>
                        <td><code class="text-dark"><?php echo $e['lote']; ?></code></td>
                        <td class="fw-bold <?php echo $is_vencido ? 'text-danger' : ''; ?>">
                            <?php echo date('d/m/Y', strtotime($e['data_validade'])); ?>
                        </td>
                        <td>
                            <div class="fw-bold <?php echo $is_baixo ? 'text-danger' : 'text-success'; ?>">
                                <?php echo number_format($e['quantidade_atual'], 2, ',', ''); ?> <?php echo $e['unidade_medida']; ?>
                            </div>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-primary" onclick="abrirMovimento(<?php echo $e['id']; ?>, '<?php echo $e['nome_produto']; ?>')">
                                <i class="fas fa-exchange-alt"></i> Mover
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL: NOVO PRODUTO -->
<div class="modal fade" id="modalNovoProduto" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form action="acoes_farmacia.php" method="POST" class="modal-content border-0 shadow-lg" style="border-radius:20px;">
            <input type="hidden" name="acao" value="novo_produto">
            <div class="modal-header bg-primary text-white p-4 border-0">
                <h5 class="fw-bold mb-0">Cadastrar Insumo Farmacêutico</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="small fw-bold">NOME DO MEDICAMENTO/VACINA</label>
                    <input type="text" name="nome_produto" class="form-control" required>
                </div>
                <div class="row">
                    <div class="col-6 mb-3">
                        <label class="small fw-bold">CATEGORIA</label>
                        <select name="categoria" class="form-select">
                            <option>Vacina</option>
                            <option>Antibiótico</option>
                            <option>Antiparasitário</option>
                            <option>Vitamina</option>
                            <option>Outros</option>
                        </select>
                    </div>
                    <div class="col-6 mb-3">
                        <label class="small fw-bold">UNIDADE (ML, Dose, Un)</label>
                        <input type="text" name="unidade_medida" class="form-control" placeholder="Ex: ML" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-6 mb-3">
                        <label class="small fw-bold">LOTE</label>
                        <input type="text" name="lote" class="form-control" required>
                    </div>
                    <div class="col-6 mb-3">
                        <label class="small fw-bold">VALIDADE</label>
                        <input type="date" name="data_validade" class="form-control" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-6 mb-3">
                        <label class="small fw-bold">ESTOQUE INICIAL</label>
                        <input type="number" step="0.01" name="quantidade_atual" class="form-control" value="0">
                    </div>
                    <div class="col-6 mb-3">
                        <label class="small fw-bold">ESTOQUE MÍNIMO</label>
                        <input type="number" step="0.01" name="estoque_minimo" class="form-control" value="0">
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="submit" class="btn btn-primary w-100 rounded-pill py-2 fw-bold">SALVAR NA FARMÁCIA</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: MOVIMENTAÇÃO (SAÍDA/USO) -->
<div class="modal fade" id="modalMovimento" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form action="acoes_farmacia.php" method="POST" class="modal-content border-0 shadow-lg" style="border-radius:20px;">
            <input type="hidden" name="acao" value="lancar_movimento">
            <input type="hidden" name="produto_id" id="mov_produto_id">
            <div class="modal-header bg-dark text-white p-4 border-0">
                <h5 class="fw-bold mb-0">Lançar Uso / Saída</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <h4 id="mov_produto_nome" class="fw-bold text-primary"></h4>
                <hr>
                <div class="row text-start">
                    <div class="col-6 mb-3">
                        <label class="small fw-bold">TIPO</label>
                        <select name="tipo_movimento" class="form-select">
                            <option value="Saida">Saída (Uso/Aplicação)</option>
                            <option value="Entrada">Entrada (Ajuste/Compra)</option>
                        </select>
                    </div>
                    <div class="col-6 mb-3">
                        <label class="small fw-bold">QUANTIDADE</label>
                        <input type="number" step="0.01" name="quantidade" class="form-control" required>
                    </div>
                </div>
                <div class="mb-3 text-start">
                    <label class="small fw-bold">MOTIVO / OBSERVAÇÃO</label>
                    <textarea name="motivo" class="form-control" placeholder="Ex: Aplicação no Lote de Garrotes 01"></textarea>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="submit" class="btn btn-dark w-100 rounded-pill py-2 fw-bold">CONFIRMAR MOVIMENTAÇÃO</button>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function abrirMovimento(id, nome) {
        document.getElementById('mov_produto_id').value = id;
        document.getElementById('mov_produto_nome').innerText = nome;
        new bootstrap.Modal(document.getElementById('modalMovimento')).show();
    }
</script>
</body>
</html>