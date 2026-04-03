<?php
/**
 * BDSoft Workspace - Minhas Economias
 * View: Gestão de Categorias (Pai e Filho)
 * Localização: gestaominhaseconomias/views/categorias.php
 */

$usuario_id = $_SESSION['usuario_id'];

// --- 1. LÓGICA DE PROCESSAMENTO (POST) ---

// Ação: Salvar ou Editar Categoria
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_salvar_categoria'])) {
    $nome = trim($_POST['nome']);
    $tipo = $_POST['tipo'];
    $pai_id = !empty($_POST['pai_id']) ? (int)$_POST['pai_id'] : null;
    $id_cat = $_POST['id_categoria'] ?? null;

    try {
        if (!empty($id_cat)) {
            // EDITAR
            $sql = "UPDATE minhaseconomias_categorias SET nome = ?, tipo = ?, pai_id = ? WHERE id = ? AND usuario_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nome, $tipo, $pai_id, $id_cat, $usuario_id]);
            $res = "cat_atualizada";
        } else {
            // NOVO
            $sql = "INSERT INTO minhaseconomias_categorias (usuario_id, pai_id, nome, tipo, icone) VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$usuario_id, $pai_id, $nome, $tipo, ($tipo == 'Receita' ? 'fa-plus-circle' : 'fa-minus-circle')]);
            $res = "cat_criada";
        }
        echo "<script>window.location.href='index.php?p=categorias&success=$res';</script>";
        exit;
    } catch (PDOException $e) {
        die("Erro ao processar categoria: " . $e->getMessage());
    }
}

// Ação: Excluir Categoria
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_excluir_categoria'])) {
    $id_excluir = (int)$_POST['id_categoria_excluir'];
    try {
        $sql = "DELETE FROM minhaseconomias_categorias WHERE id = ? AND usuario_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_excluir, $usuario_id]);
        echo "<script>window.location.href='index.php?p=categorias&success=cat_excluida';</script>";
        exit;
    } catch (PDOException $e) {
        die("Erro ao excluir: " . $e->getMessage());
    }
}

// --- 2. BUSCAR DADOS ---

// Buscar Categorias Pais
$stmtPais = $pdo->prepare("SELECT * FROM minhaseconomias_categorias WHERE usuario_id = ? AND pai_id IS NULL ORDER BY tipo DESC, nome ASC");
$stmtPais->execute([$usuario_id]);
$categorias_pais = $stmtPais->fetchAll(PDO::FETCH_ASSOC);

/**
 * Função auxiliar para ícones de tipo
 */
function getIconeTipo($tipo) {
    return ($tipo == 'Receita') ? 'fa-arrow-up text-success' : 'fa-arrow-down text-danger';
}
?>

<div class="card card-finance p-4 bg-white border-0 shadow-sm mb-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h5 class="fw-bold m-0 text-dark"><i class="fas fa-tags me-2 text-primary"></i>Categorias e Subcategorias</h5>
            <p class="text-muted small mb-0">Organize seus lançamentos por grupos de afinidade.</p>
        </div>
        <button class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" onclick="abrirModalNovaCategoria()">
            <i class="fas fa-plus me-2"></i>NOVA CATEGORIA
        </button>
    </div>

    <!-- LISTA DE CATEGORIAS -->
    <div class="row">
        <?php if (empty($categorias_pais)): ?>
            <div class="col-12 text-center py-5">
                <i class="fas fa-layer-group fa-3x text-muted opacity-25 mb-3"></i>
                <p class="text-muted">Você ainda não possui categorias cadastradas.</p>
            </div>
        <?php else: ?>
            <?php foreach ($categorias_pais as $pai): ?>
                <div class="col-12 mb-3">
                    <div class="border rounded-4 overflow-hidden shadow-sm bg-white">
                        <!-- Linha da Categoria Pai -->
                        <div class="d-flex align-items-center justify-content-between p-3 bg-light bg-opacity-50">
                            <div class="d-flex align-items-center">
                                <!-- Botão de Expandir -->
                                <button class="btn btn-sm btn-white border shadow-sm rounded-circle me-3" 
                                        type="button" 
                                        data-bs-toggle="collapse" 
                                        data-bs-target="#sub_<?= $pai['id'] ?>" 
                                        aria-expanded="false"
                                        title="Ver Subcategorias">
                                    <i class="fas fa-chevron-down small"></i>
                                </button>
                                
                                <div>
                                    <i class="fas <?= getIconeTipo($pai['tipo']) ?> me-2"></i>
                                    <span class="fw-bold text-dark fs-5"><?= htmlspecialchars($pai['nome']) ?></span>
                                    <span class="badge bg-white text-muted border ms-2" style="font-size: 10px;"><?= strtoupper($pai['tipo']) ?></span>
                                </div>
                            </div>

                            <div class="btn-group">
                                <button class="btn btn-outline-primary btn-sm rounded-pill px-3 me-2" 
                                        onclick="prepararEdicaoCategoria(<?= $pai['id'] ?>, '<?= addslashes($pai['nome']) ?>', '<?= $pai['tipo'] ?>', '')">
                                    <i class="fas fa-edit me-1"></i> Editar
                                </button>
                                <button class="btn btn-outline-danger btn-sm rounded-pill px-3" 
                                        onclick="confirmarExclusaoCategoria(<?= $pai['id'] ?>, '<?= addslashes($pai['nome']) ?>')">
                                    <i class="fas fa-trash me-1"></i> Excluir
                                </button>
                            </div>
                        </div>

                        <!-- Sub-Lista (Collapse) -->
                        <div class="collapse show" id="sub_<?= $pai['id'] ?>">
                            <div class="p-3 pt-0">
                                <div class="list-group list-group-flush ms-5 border-start ps-3">
                                    <?php
                                    $stmtF = $pdo->prepare("SELECT * FROM minhaseconomias_categorias WHERE pai_id = ? ORDER BY nome ASC");
                                    $stmtF->execute([$pai['id']]);
                                    $filhos = $stmtF->fetchAll(PDO::FETCH_ASSOC);

                                    if (empty($filhos)):
                                    ?>
                                        <div class="py-2 text-muted small fst-italic">Nenhuma subcategoria vinculada.</div>
                                    <?php else: ?>
                                        <?php foreach ($filhos as $filho): ?>
                                            <div class="list-group-item d-flex align-items-center justify-content-between border-0 py-2 px-0">
                                                <div class="d-flex align-items-center">
                                                    <i class="fas fa-level-up-alt fa-rotate-90 text-muted me-3"></i>
                                                    <span class="text-dark"><?= htmlspecialchars($filho['nome']) ?></span>
                                                </div>
                                                <div class="btn-group opacity-75">
                                                    <button class="btn btn-link btn-sm text-primary p-1" 
                                                            onclick="prepararEdicaoCategoria(<?= $filho['id'] ?>, '<?= addslashes($filho['nome']) ?>', '<?= $filho['tipo'] ?>', <?= $pai['id'] ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-link btn-sm text-danger p-1" 
                                                            onclick="confirmarExclusaoCategoria(<?= $filho['id'] ?>, '<?= addslashes($filho['nome']) ?>')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- ========================================================
     MODAL: GERENCIAR CATEGORIA (CADASTRAR / EDITAR)
     ======================================================== -->
<div class="modal fade" id="modalCategoria" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold" id="labelModalCat">Nova Categoria</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="index.php?p=categorias" method="POST">
                <input type="hidden" name="id_categoria" id="id_categoria">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">NOME DA CATEGORIA</label>
                        <input type="text" name="nome" id="nome_cat" class="form-control bg-light border-0 py-2 rounded-3" placeholder="Ex: Combustível, Lazer..." required>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">TIPO</label>
                            <select name="tipo" id="tipo_cat" class="form-select bg-light border-0 rounded-3">
                                <option value="Despesa">Despesa (Saída)</option>
                                <option value="Receita">Receita (Entrada)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">CATEGORIA PAI</label>
                            <select name="pai_id" id="pai_id_cat" class="form-select bg-light border-0 rounded-3">
                                <option value="">Nenhuma (Principal)</option>
                                <?php foreach ($categorias_pais as $p) echo "<option value='{$p['id']}'>{$p['nome']}</option>"; ?>
                            </select>
                        </div>
                    </div>
                    <small class="text-muted" style="font-size: 11px;">
                        <i class="fas fa-info-circle me-1"></i> Se selecionar uma <b>Categoria Pai</b>, este item será uma subcategoria.
                    </small>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4 fw-bold text-muted" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="btn_salvar_categoria" class="btn btn-primary rounded-pill px-5 fw-bold shadow">Salvar Dados</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================
     MODAL: CONFIRMAR EXCLUSÃO DE CATEGORIA
     ======================================================== -->
<div class="modal fade" id="modalExcluirCat" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg text-center rounded-4">
            <div class="modal-body p-5">
                <i class="fas fa-exclamation-circle text-danger fa-4x mb-4 opacity-25"></i>
                <h4 class="fw-bold mb-2">Excluir Categoria?</h4>
                <p class="text-muted">Deseja apagar a categoria <br><strong id="nome_cat_excluir" class="text-dark"></strong>?</p>
                
                <div class="alert alert-danger small border-0 py-2">
                    <i class="fas fa-info-circle me-1"></i> Se for uma categoria principal, todas as subcategorias serão excluídas.
                </div>

                <form action="index.php?p=categorias" method="POST" class="mt-4">
                    <input type="hidden" name="id_categoria_excluir" id="id_categoria_excluir">
                    <div class="d-grid gap-2">
                        <button type="submit" name="btn_excluir_categoria" class="btn btn-danger rounded-pill py-2 fw-bold shadow">Sim, excluir agora</button>
                        <button type="button" class="btn btn-light rounded-pill py-2 text-muted" data-bs-dismiss="modal">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>