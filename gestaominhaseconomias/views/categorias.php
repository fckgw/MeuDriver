<?php
$usuario_id = $_SESSION['usuario_id'];
$lista = $pdo->prepare("SELECT * FROM minhaseconomias_categorias WHERE usuario_id = ? ORDER BY IFNULL(parent_id, id), parent_id IS NOT NULL, nome ASC");
$lista->execute([$usuario_id]);
$cats = $lista->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="fw-bold m-0"><i class="fas fa-tags text-primary me-2"></i>Categorias</h5>
        <button class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" onclick="novaCat()">+ NOVA</button>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light small text-muted">
                <tr><th class="ps-4">NOME</th><th>TIPO</th><th class="text-center">AÇÕES</th></tr>
            </thead>
            <tbody>
                <?php foreach($cats as $c): ?>
                <tr style="font-size: 13px;">
                    <td class="ps-4 fw-bold"><?= ($c['parent_id'] ? " — " : " ● ") . $c['nome'] ?></td>
                    <td><span class="badge bg-light text-dark border"><?= $c['tipo'] ?></span></td>
                    <td class="text-center">
                        <button class="btn btn-link btn-sm p-1" onclick="editarCat(<?= $c['id'] ?>, '<?= $c['nome'] ?>', '<?= $c['tipo'] ?>', '<?= $c['parent_id'] ?>')"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-link btn-sm p-1 text-danger" onclick="excluirCat(<?= $c['id'] ?>, '<?= addslashes($c['nome']) ?>')"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL CATEGORIA -->
<div class="modal fade" id="modalCat" tabindex="-1">
    <form method="POST" class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-0 pt-4 px-4 pb-0"><h5 class="fw-bold">Configurar Categoria</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-4">
                <input type="hidden" name="id_categoria" id="c_id">
                <div class="mb-3"><label class="small fw-bold">NOME</label><input type="text" name="nome" id="c_nome" class="form-control" required></div>
                <div class="mb-3"><label class="small fw-bold">CATEGORIA PAI</label><select name="parent_id" id="c_parent" class="form-select"><option value="">Nenhuma (Será PAI)</option><?php foreach($cats as $p) if(!$p['parent_id']) echo "<option value='{$p['id']}'>{$p['nome']}</option>"; ?></select></div>
                <div class="mb-3"><label class="small fw-bold">TIPO</label><select name="tipo" id="c_tipo" class="form-select"><option value="AMBOS">Ambos</option><option value="RECEITA">Receita</option><option value="DESPESA">Despesa</option></select></div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0"><button type="submit" name="btn_salvar_categoria" class="btn btn-primary w-100 rounded-pill py-2 fw-bold">GRAVAR</button></div>
        </div>
    </form>
</div>

<!-- MODAL EXCLUIR CATEGORIA -->
<div class="modal fade" id="modalDelCat" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <form method="POST" class="modal-content border-0 shadow text-center rounded-4">
            <div class="modal-body p-4">
                <input type="hidden" name="id_categoria_excluir" id="dc_id">
                <h6 class="fw-bold">Excluir categoria?</h6>
                <p class="small text-muted" id="dc_nome"></p>
                <div class="d-grid gap-2">
                    <button type="submit" name="btn_excluir_categoria" class="btn btn-danger rounded-pill">Sim, Excluir</button>
                    <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Não</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function novaCat(){
    document.getElementById('c_id').value = '';
    document.getElementById('c_nome').value = '';
    new bootstrap.Modal(document.getElementById('modalCat')).show();
}
function editarCat(id, n, t, p){
    document.getElementById('c_id').value = id;
    document.getElementById('c_nome').value = n;
    document.getElementById('c_tipo').value = t;
    document.getElementById('c_parent').value = p;
    new bootstrap.Modal(document.getElementById('modalCat')).show();
}
function excluirCat(id, n){
    document.getElementById('dc_id').value = id;
    document.getElementById('dc_nome').innerText = n;
    new bootstrap.Modal(document.getElementById('modalDelCat')).show();
}
</script>