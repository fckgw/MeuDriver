<?php
/**
 * BDSoft Workspace - Minhas Economias
 * View: Categorias (Sincronizada com Modal Global)
 */
$usuario_id = $_SESSION['usuario_id'];
$stmtPais = $pdo->prepare("SELECT * FROM minhaseconomias_categorias WHERE usuario_id = ? AND parent_id IS NULL AND id != 999 ORDER BY nome ASC");
$stmtPais->execute([$usuario_id]);
$categorias_pais = $stmtPais->fetchAll(PDO::FETCH_ASSOC);

function getCorIcone($tipo) {
    if($tipo == 'RECEITA') return 'fa-arrow-up text-success';
    if($tipo == 'DESPESA') return 'fa-arrow-down text-danger';
    return 'fa-exchange-alt text-primary';
}
?>

<div class="card card-finance p-4 bg-white shadow-sm border-0 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h5 class="fw-bold m-0 text-dark"><i class="fas fa-tags me-2 text-primary"></i>Categorias</h5>
            <small class="text-muted">Personalize seus grupos financeiros</small>
        </div>
        <button class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" onclick="window.abrirModalNovaCategoria()">
            <i class="fas fa-plus me-2"></i>NOVA CATEGORIA
        </button>
    </div>

    <div class="row">
        <?php if (empty($categorias_pais)): ?>
            <div class="col-12 text-center py-5 text-muted">
                <i class="fas fa-folder-open fa-3x opacity-25 mb-3"></i>
                <p>Nenhuma categoria principal cadastrada.</p>
            </div>
        <?php else: ?>
            <?php foreach ($categorias_pais as $pai): ?>
                <div class="col-12 mb-3">
                    <div class="border rounded-4 overflow-hidden bg-white shadow-sm">
                        <div class="d-flex align-items-center justify-content-between p-3 bg-light bg-opacity-50 border-bottom">
                            <div class="d-flex align-items-center">
                                <button class="btn btn-sm btn-white border shadow-sm rounded-circle me-3" type="button" data-bs-toggle="collapse" data-bs-target="#sub_<?= $pai['id'] ?>"><i class="fas fa-chevron-down small"></i></button>
                                <div>
                                    <i class="fas <?= getCorIcone($pai['tipo']) ?> me-2"></i>
                                    <span class="fw-bold text-dark fs-6"><?= htmlspecialchars($pai['nome']) ?></span>
                                    <span class="badge bg-white text-muted border ms-2" style="font-size: 9px;"><?= $pai['tipo'] ?></span>
                                </div>
                            </div>
                            <div class="btn-group">
                                <button class="btn btn-link btn-sm text-primary" onclick="window.prepararEdicaoCategoria(<?= $pai['id'] ?>, '<?= addslashes($pai['nome']) ?>', '<?= $pai['tipo'] ?>', '')"><i class="fas fa-edit"></i></button>
                                <button class="btn btn-link btn-sm text-danger" onclick="window.confirmarExclusaoCategoria(<?= $pai['id'] ?>, '<?= addslashes($pai['nome']) ?>')"><i class="fas fa-trash"></i></button>
                            </div>
                        </div>
                        <div class="collapse show" id="sub_<?= $pai['id'] ?>">
                            <div class="p-3 pt-0 ms-5 border-start ps-3">
                                <?php
                                $stmtF = $pdo->prepare("SELECT * FROM minhaseconomias_categorias WHERE parent_id = ? ORDER BY nome ASC");
                                $stmtF->execute([$pai['id']]);
                                while ($filho = $stmtF->fetch(PDO::FETCH_ASSOC)):
                                ?>
                                    <div class="d-flex align-items-center justify-content-between py-2 border-bottom border-light">
                                        <span class="text-dark small">— <?= htmlspecialchars($filho['nome']) ?></span>
                                        <div class="btn-group opacity-75">
                                            <button class="btn btn-link btn-sm text-primary p-0 me-2" onclick="window.prepararEdicaoCategoria(<?= $filho['id'] ?>, '<?= addslashes($filho['nome']) ?>', '<?= $filho['tipo'] ?>', <?= $pai['id'] ?>)"><i class="fas fa-edit"></i></button>
                                            <button class="btn btn-link btn-sm text-danger p-0" onclick="window.confirmarExclusaoCategoria(<?= $filho['id'] ?>, '<?= addslashes($filho['nome']) ?>')"><i class="fas fa-trash"></i></button>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Excluir Categoria (Local para proteção) -->
<div class="modal fade" id="modalExcluirCat" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content border-0 shadow-lg text-center rounded-4"><div class="modal-body p-5"><i class="fas fa-trash text-danger fa-4x mb-4 opacity-25"></i><h5 class="fw-bold">Excluir?</h5><p class="text-muted small" id="txtNomeExcluirCat"></p><form action="index.php?p=categorias" method="POST"><input type="hidden" name="id_categoria_excluir" id="id_cat_excluir"><button type="submit" name="btn_excluir_categoria" class="btn btn-danger w-100 rounded-pill fw-bold shadow">SIM, EXCLUIR</button>
</form></div></div></div></div>