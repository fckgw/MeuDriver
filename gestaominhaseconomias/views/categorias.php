<?php
/**
 * View: Categorias - Versão BI Premium
 * Funcionalidades: Pesquisa, Filtros de Nível, Filtro de Tipo (Receita, Despesa, Ambos)
 */
$usuario_id = $_SESSION['usuario_id'];

// --- CAPTURA DE FILTROS DA URL ---
$pesquisa = $_GET['search'] ?? '';
$f_tipo    = $_GET['f_tipo'] ?? '';
$f_nivel   = $_GET['f_nivel'] ?? ''; 

// --- CONSTRUÇÃO DA QUERY DINÂMICA ---
$sql = "SELECT c1.*, c2.nome as pai_nome 
        FROM minhaseconomias_categorias c1 
        LEFT JOIN minhaseconomias_categorias c2 ON c1.parent_id = c2.id 
        WHERE c1.usuario_id = ?";

$params = [$usuario_id];

if ($pesquisa) {
    $sql .= " AND c1.nome LIKE ?";
    $params[] = "%$pesquisa%";
}

if ($f_tipo) {
    $sql .= " AND c1.tipo = ?";
    $params[] = $f_tipo;
}

if ($f_nivel) {
    if ($f_nivel == 'categoria') {
        $sql .= " AND c1.parent_id IS NULL";
    } else {
        $sql .= " AND c1.parent_id IS NOT NULL";
    }
}

$stmt = $pdo->prepare($sql . " ORDER BY c1.tipo DESC, c1.nome ASC");
$stmt->execute($params);
$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Busca categorias pai para alimentar o select de "Categoria Pai" no modal
$stmt_pais = $pdo->prepare("SELECT id, nome FROM minhaseconomias_categorias WHERE usuario_id = ? AND parent_id IS NULL ORDER BY nome ASC");
$stmt_pais->execute([$usuario_id]);
$categorias_pai = $stmt_pais->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="card p-4 border-0 shadow-sm bg-white" style="border-radius: 20px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h5 class="fw-bold m-0"><i class="fas fa-tags me-2 text-primary"></i>Gestão de Categorias</h5>
            <small class="text-muted">Organize sua árvore de Receitas e Despesas</small>
        </div>
        <button class="btn btn-primary btn-sm rounded-pill px-4 fw-bold shadow-sm" onclick="novaCategoria()">
            <i class="fas fa-plus me-1"></i> NOVA CATEGORIA
        </button>
    </div>

    <!-- BARRA DE PESQUISA E FILTROS -->
    <form method="GET" class="row g-2 mb-4 bg-light p-3 rounded-4 border">
        <input type="hidden" name="p" value="categorias">
        
        <div class="col-md-4">
            <label class="small fw-bold text-muted text-uppercase">Pesquisar por Nome</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white border-end-0 rounded-start-pill"><i class="fas fa-search text-muted"></i></span>
                <input type="text" name="search" class="form-control border-start-0 rounded-end-pill" placeholder="Ex: Alimentação..." value="<?= htmlspecialchars($pesquisa) ?>">
            </div>
        </div>

        <div class="col-md-3">
            <label class="small fw-bold text-muted text-uppercase">Nível / Hierarquia</label>
            <select name="f_nivel" class="form-select form-select-sm rounded-pill px-3">
                <option value="">Todos os Níveis</option>
                <option value="categoria" <?= $f_nivel == 'categoria' ? 'selected' : '' ?>>Apenas Categorias Principais</option>
                <option value="subcategoria" <?= $f_nivel == 'subcategoria' ? 'selected' : '' ?>>Apenas Subcategorias</option>
            </select>
        </div>

        <div class="col-md-3">
            <label class="small fw-bold text-muted text-uppercase">Tipo de Fluxo</label>
            <select name="f_tipo" class="form-select form-select-sm rounded-pill px-3">
                <option value="">Todos os Tipos</option>
                <option value="Despesa" <?= $f_tipo == 'Despesa' ? 'selected' : '' ?>>Despesa</option>
                <option value="Receita" <?= $f_tipo == 'Receita' ? 'selected' : '' ?>>Receita</option>
                <option value="Ambos" <?= $f_tipo == 'Ambos' ? 'selected' : '' ?>>Ambos</option>
            </select>
        </div>

        <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-dark btn-sm w-100 rounded-pill fw-bold shadow-sm">FILTRAR</button>
        </div>
    </form>

    <!-- TABELA DE CATEGORIAS -->
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr class="small text-muted text-uppercase">
                    <th>NOME / ESTRUTURA</th>
                    <th>TIPO APLICADO</th>
                    <th class="text-end">AÇÕES</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categorias as $c): ?>
                <tr style="font-size: 13px;">
                    <td>
                        <?php if ($c['parent_id']): ?>
                            <span class="text-muted ms-3"><i class="fas fa-level-up-alt fa-rotate-90 me-2"></i></span>
                            <span class="fw-bold text-dark"><?= htmlspecialchars($c['nome']) ?></span>
                            <br><small class="text-muted ms-5">Vínculo: <?= htmlspecialchars($c['pai_nome']) ?></small>
                        <?php else: ?>
                            <span class="text-primary"><i class="fas fa-folder-open me-2"></i></span>
                            <span class="fw-bold text-dark text-uppercase"><?= htmlspecialchars($c['nome']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php 
                            $cor = ['Receita'=>'bg-success', 'Despesa'=>'bg-danger', 'Ambos'=>'bg-primary'];
                            $badge_cor = $cor[$c['tipo']] ?? 'bg-secondary';
                        ?>
                        <span class="badge <?= $badge_cor ?> rounded-pill px-3">
                            <?= $c['tipo'] ?>
                        </span>
                    </td>
                    <td class="text-end">
                        <div class="d-flex justify-content-end gap-1">
                            <button class="btn btn-sm text-primary" onclick='editarCategoria(<?= json_encode($c) ?>)' title="Editar"><i class="fas fa-edit"></i></button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Deseja excluir esta categoria?')">
                                <input type="hidden" name="id_categoria_excluir" value="<?= $c['id'] ?>">
                                <button type="submit" name="btn_excluir_categoria" class="btn btn-sm text-danger"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($categorias)): ?>
                    <tr><td colspan="3" class="text-center py-5 text-muted small">Nenhuma categoria encontrada com estes filtros.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ============================================================
     MODAL DE CATEGORIA (NOVO / EDITAR)
     ============================================================ -->
<div class="modal fade" id="modalCat" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" id="formCat" class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-dark text-white border-0">
                <h5 class="modal-title fw-bold" id="modalCatTitulo">Nova Categoria</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="id_categoria" id="cat_id">
                
                <div class="mb-3">
                    <label class="small fw-bold text-muted">NOME DA CATEGORIA</label>
                    <input type="text" name="nome" id="cat_nome" class="form-control rounded-3" required placeholder="Ex: Alimentação, Freelance...">
                </div>

                <div class="mb-3">
                    <label class="small fw-bold text-muted">TIPO DE CATEGORIA</label>
                    <select name="tipo" id="cat_tipo" class="form-select rounded-3">
                        <option value="Despesa">Despesa (Saída)</option>
                        <option value="Receita">Receita (Entrada)</option>
                        <option value="Ambos">Ambos (Receita e Despesa)</option>
                    </select>
                    <small class="text-muted">Categorias "Ambos" aparecem em qualquer tipo de lançamento.</small>
                </div>

                <div class="mb-0 border-top pt-3">
                    <label class="small fw-bold text-muted">VINCULAR A UMA CATEGORIA PAI (SUB-NÍVEL)</label>
                    <select name="parent_id" id="cat_parent" class="form-select rounded-3">
                        <option value="">-- Esta é uma categoria principal --</option>
                        <?php foreach ($categorias_pai as $pai): ?>
                            <option value="<?= $pai['id'] ?>"><?= htmlspecialchars($pai['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="submit" name="btn_salvar_categoria" class="btn btn-primary w-100 rounded-pill py-3 fw-bold shadow">
                    SALVAR ALTERAÇÕES
                </button>
            </div>
        </form>
    </div>
</div>

<script>
/**
 * Limpa o formulário e abre o modal para um novo registro
 */
function novaCategoria() {
    document.getElementById('modalCatTitulo').innerText = 'Nova Categoria';
    document.getElementById('formCat').reset();
    document.getElementById('cat_id').value = '';
    new bootstrap.Modal(document.getElementById('modalCat')).show();
}

/**
 * Preenche o formulário com dados existentes para edição
 */
function editarCategoria(dados) {
    document.getElementById('modalCatTitulo').innerText = 'Editar Categoria';
    document.getElementById('cat_id').value = dados.id;
    document.getElementById('cat_nome').value = dados.nome;
    document.getElementById('cat_tipo').value = dados.tipo;
    document.getElementById('cat_parent').value = dados.parent_id ? dados.parent_id : "";
    
    // Abre o modal
    const m = new bootstrap.Modal(document.getElementById('modalCat'));
    m.show();
}
</script>