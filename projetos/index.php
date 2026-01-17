<?php
/**
 * BDSoft Workspace - PROJETOS (LOBBY)
 * Local: projetos/index.php
 */
session_start();
require_once '../config.php'; // Busca o config na raiz

// Verificação de Segurança
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['usuario_id'];

// Buscar Quadros permitidos
$sql = "SELECT DISTINCT q.*, u.nome as criador_nome 
        FROM quadros_projetos q
        LEFT JOIN usuarios u ON q.usuario_id = u.id
        LEFT JOIN quadro_membros qm ON q.id = qm.quadro_id
        WHERE q.tipo = 'Publico' 
        OR q.usuario_id = :uid 
        OR qm.usuario_id = :uid
        ORDER BY q.data_criacao DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([':uid' => $user_id]);
$quadros = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projetos - BDSoft Workspace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f7f9; font-family: 'Segoe UI', sans-serif; }
        .board-card { background: #fff; border: 1px solid #e0e6ed; border-radius: 16px; transition: 0.3s; text-decoration: none; color: #334155; display: block; height: 100%; overflow: hidden; }
        .board-card:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0,0,0,0.08); border-color: #1a73e8; }
        .board-header { height: 100px; display: flex; align-items: center; justify-content: center; background: #f8fafc; position: relative; }
        .board-title { font-weight: 700; font-size: 1.1rem; padding: 20px; text-align: center; }
    </style>
</head>
<body class="p-4">

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h2 class="fw-bold text-dark mb-0">Gestão de Projetos</h2>
            <p class="text-muted">Workspaces e Quadros Scrum</p>
        </div>
        <div>
            <a href="../portal.php" class="btn btn-light border rounded-pill me-2 px-4 fw-bold shadow-sm">VOLTAR AO PORTAL</a>
            <button class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalNovoQuadro">+ NOVO QUADRO</button>
        </div>
    </div>

    <div class="row g-4">
        <?php if (empty($quadros)): ?>
            <div class="col-12 text-center py-5">
                <h5 class="text-muted">Nenhum projeto encontrado.</h5>
            </div>
        <?php else: ?>
            <?php foreach ($quadros as $q): ?>
                <div class="col-md-3">
                    <a href="quadro.php?id=<?php echo $q['id']; ?>" class="board-card shadow-sm">
                        <div class="board-header">
                            <i class="fas <?php echo $q['tipo'] == 'Privado' ? 'fa-lock text-danger' : 'fa-globe text-success'; ?> position-absolute top-0 end-0 m-3"></i>
                            <i class="fas fa-project-diagram fa-3x text-primary opacity-25"></i>
                        </div>
                        <div class="board-title text-truncate border-top"><?php echo htmlspecialchars($q['nome']); ?></div>
                    </a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- MODAL NOVO QUADRO -->
<div class="modal fade" id="modalNovoQuadro" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form action="acoes.php" method="POST" class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 bg-light"><h5>Novo Workspace</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-4">
                <input type="hidden" name="acao" value="criar_quadro">
                <div class="mb-3">
                    <label class="small fw-bold">NOME DO QUADRO</label>
                    <input type="text" name="nome" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="small fw-bold">PRIVACIDADE</label>
                    <select name="privado" class="form-select">
                        <option value="0">Público</option>
                        <option value="1">Privado</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="submit" class="btn btn-primary w-100 py-2 rounded-pill fw-bold">CRIAR QUADRO</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>