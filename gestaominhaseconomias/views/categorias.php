<?php
/**
 * BDSoft Workspace - Minhas Economias
 * View: Categorias (Sincronizada com Modal Global)
 * Localização: gestaominhaseconomias/views/categorias.php
 */

$usuario_id = $_SESSION['usuario_id'];

// Buscar Categorias Principais (Pai) - Ignora a 999 da lista principal por estética
$stmtPais = $pdo->prepare("SELECT * FROM minhaseconomias_categorias WHERE usuario_id = ? AND parent_id IS NULL AND id != 999 ORDER BY nome ASC");
$stmtPais->execute([$usuario_id]);
$categorias_pais = $stmtPais->fetchAll(PDO::FETCH_ASSOC);

function getCorTipo($tipo) {
    if($tipo == 'RECEITA') return 'fa-arrow-up text-success';
    if($tipo == 'DESPESA') return 'fa-arrow-down text-danger';
    return 'fa-exchange-alt text-primary';
}
?>

<div class="card card-finance p-4 bg-white shadow-sm border-0 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h5 class="fw-bold m-0 text-dark"><i class="fas fa-tags me-2 text-primary"></i>Minhas Categorias</h5>
            <small class="text-muted">Organize seus lançamentos por grupos e subgrupos</small>
        </div>
        <!-- Chamada da função global definida no footer -->
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
                        
                        <!-- LINHA DA CATEGORIA PAI -->
                        <div class="d-flex align-items-center justify-content-between p-3 bg-light bg-opacity-50 border-bottom">
                            <div class="d-flex align-items-center">
                                <button class="btn btn-sm btn-white border shadow-sm rounded-circle me-3" 
                                        type="button" data-bs-toggle="collapse" data-bs-target="#sub_<?= $pai['id'] ?>">
                                    <i class="fas fa-chevron-down small"></i>
                                </button>
                                <div>
                                    <i class="fas <?= getCorTipo($pai['tipo']) ?> me-2"></i>
                                    <span class="fw-bold text-dark fs-6"><?= htmlspecialchars($pai['nome']) ?></span>
                                    <span class="badge bg-white text-muted border ms-2" style="font-size: 9px;"><?= $pai['tipo'] ?></span>
                                </div>
                            </div>
                            
                            <div class="btn-group">
                                <button class="btn btn-link btn-sm text-primary" 
                                        onclick="window.prepararEdicaoCategoria(<?= $pai['id'] ?>, '<?= addslashes($pai['nome']) ?>', '<?= $pai['tipo'] ?>', '')">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-link btn-sm text-danger" 
                                        onclick="window.confirmarExclusaoCategoria(<?= $pai['id'] ?>, '<?= addslashes($pai['nome']) ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>

                        <!-- LISTA DE FILHOS (SUB-LISTA) -->
                        <div class="collapse show" id="sub_<?= $pai['id'] ?>">
                            <div class="p-3 pt-0 ms-5 border-start ps-3">
                                <?php
                                $stmtF = $pdo->prepare("SELECT * FROM minhaseconomias_categorias WHERE parent_id = ? ORDER BY nome ASC");
                                $stmtF->execute([$pai['id']]);
                                $tem_filho = false;
                                while ($filho = $stmtF->fetch(PDO::FETCH_ASSOC)):
                                    $tem_filho = true;
                                ?>
                                    <div class="d-flex align-items-center justify-content-between py-2 border-bottom border-light">
                                        <span class="text-dark small">— <?= htmlspecialchars($filho['nome']) ?></span>
                                        <div class="btn-group opacity-75">
                                            <button class="btn btn-link btn-sm text-primary p-0 me-2" 
                                                    onclick="window.prepararEdicaoCategoria(<?= $filho['id'] ?>, '<?= addslashes($filho['nome']) ?>', '<?= $filho['tipo'] ?>', <?= $pai['id'] ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-link btn-sm text-danger p-0" 
                                                    onclick="window.confirmarExclusaoCategoria(<?= $filho['id'] ?>, '<?= addslashes($filho['nome']) ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                                <?php if(!$tem_filho): ?>
                                    <div class="py-2 text-muted small fst-italic">Nenhuma subcategoria vinculada.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

        <?php endif; ?>
    </div>
</div>