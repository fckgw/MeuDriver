<?php
/**
 * BDSoft Workspace - AGRO CAMPO (ORDENHA PRÁTICA)
 * Local: agrocampo/ordenha.php
 */
session_start();
require_once '../config.php';


// No topo de cada página sub-módulo (ex: ordenha.php)
$arquivo_atual = basename(__FILE__); // Pega 'ordenha.php'
if ($_SESSION['usuario_nivel'] !== 'admin') {
    $stmt_check = $pdo->prepare("SELECT 1 FROM usuarios_agro_permissões up 
                                 INNER JOIN agro_submodulos s ON up.submodulo_id = s.id 
                                 WHERE up.usuario_id = ? AND s.slug = ?");
    $stmt_check->execute([$user_id, $arquivo_atual]);
    if (!$stmt_check->fetch()) {
        die("<div style='text-align:center; padding:50px;'><h2>🔒 Acesso Restrito</h2><p>Você não tem permissão para este recurso.</p><a href='index.php'>Voltar</a></div>");
    }
}



if (!isset($_SESSION['usuario_id'])) { header("Location: ../login.php"); exit; }
$user_id = $_SESSION['usuario_id'];
$hoje = date('Y-m-d');

// 1. Estatísticas Rápidas de Hoje
$stmt_hoje = $pdo->prepare("SELECT SUM(litros) FROM agro_ordenhas WHERE usuario_id = ? AND data_registro = ?");
$stmt_hoje->execute([$user_id, $hoje]);
$total_hoje = $stmt_hoje->fetchColumn() ?: 0;

// 2. Buscar Animais em Lactação para o Registro Rápido
$stmt_animais = $pdo->prepare("SELECT id, nome, brinco FROM agro_animais WHERE usuario_id = ? AND status = 'Lactação' ORDER BY nome ASC");
$stmt_animais->execute([$user_id]);
$animais = $stmt_animais->fetchAll(PDO::FETCH_ASSOC);

// 3. Últimos 10 registros de hoje
$stmt_recentes = $pdo->prepare("SELECT o.*, a.nome as vaca FROM agro_ordenhas o INNER JOIN agro_animais a ON o.animal_id = a.id WHERE o.usuario_id = ? ORDER BY o.id DESC LIMIT 10");
$stmt_recentes->execute([$user_id]);
$recentes = $stmt_recentes->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ordenha Prática - AgroCampo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --agro-primary: #2d5a27; --agro-accent: #8bc34a; --monday-blue: #1a73e8; }
        body { background-color: #f0f4f0; font-family: 'Segoe UI', sans-serif; }
        
        /* Layout Mobile-First */
        .app-container { max-width: 500px; margin: auto; background: #fff; min-height: 100vh; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
        .app-header { background: var(--agro-primary); color: white; padding: 20px; border-radius: 0 0 25px 25px; }
        
        .action-card { background: #fff; border: 1px solid #eee; border-radius: 20px; padding: 20px; transition: 0.3s; cursor: pointer; text-decoration: none; color: #333; display: block; }
        .action-card:hover { background: #f8faf8; transform: scale(1.02); }
        .icon-circle { width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 10px; font-size: 1.2rem; }

        .btn-record { background: var(--agro-primary); color: white; border-radius: 15px; padding: 15px; font-weight: bold; border: none; width: 100%; }
        .vaca-row { border-bottom: 1px solid #f1f1f1; padding: 12px 0; }
    </style>
</head>
<body>

<div class="app-container">
    <!-- HEADER ESTILO APP -->
    <div class="app-header shadow">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <a href="index.php" class="text-white"><i class="fas fa-chevron-left fa-lg"></i></a>
            <h5 class="fw-bold mb-0">ORDENHA PRÁTICA</h5>
            <a href="../portal.php" class="text-white"><i class="fas fa-th"></i></a>
        </div>
        <div class="text-center py-2">
            <small class="opacity-75 text-uppercase fw-bold">Produção Hoje</small>
            <h1 class="fw-bold"><?php echo number_format($total_hoje, 1, ',', '.'); ?> <small class="h6">litros</small></h1>
        </div>
    </div>

    <div class="p-4">
        <!-- BOTÕES DE AÇÃO RÁPIDA -->
        <div class="row g-3 mb-4 text-center">
            <div class="col-6">
                <a href="#" data-bs-toggle="modal" data-bs-target="#modalLançar" class="action-card shadow-sm">
                    <div class="icon-circle bg-success bg-opacity-10 text-success mx-auto"><i class="fas fa-plus"></i></div>
                    <span class="small fw-bold">Lançar Leite</span>
                </a>
            </div>
            <div class="col-6">
                <a href="#" data-bs-toggle="modal" data-bs-target="#modalVacas" class="action-card shadow-sm">
                    <div class="icon-circle bg-primary bg-opacity-10 text-primary mx-auto"><i class="fas fa-cow"></i></div>
                    <span class="small fw-bold">Ver Rebanho</span>
                </a>
            </div>
        </div>

        <!-- LISTAGEM RECENTE -->
        <h6 class="fw-bold text-muted mb-3">ÚLTIMOS LANÇAMENTOS</h6>
        <div class="bg-light rounded-4 p-3 border">
            <?php if(empty($recentes)): ?>
                <p class="text-center text-muted small py-3">Nenhum registro hoje.</p>
            <?php else: ?>
                <?php foreach($recentes as $r): ?>
                <div class="vaca-row d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-bold small"><?php echo htmlspecialchars($r['vaca']); ?></div>
                        <small class="text-muted"><?php echo $r['periodo']; ?> • <?php echo date('H:i', strtotime($r['data_cadastro'])); ?></small>
                    </div>
                    <div class="text-success fw-bold"><?php echo $r['litros']; ?> L</div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- MODAL: REGISTRAR LEITE -->
<div class="modal fade" id="modalLançar" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form action="acoes.php" method="POST" class="modal-content border-0 shadow-lg" style="border-radius: 25px;">
            <div class="modal-header border-0">
                <h5 class="fw-bold">Registrar Ordenha</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="acao" value="lancar_ordenha">
                
                <div class="mb-3">
                    <label class="small fw-bold">SELECIONE A VACA</label>
                    <select name="animal_id" class="form-select form-select-lg" required>
                        <option value="">Escolher...</option>
                        <?php foreach($animais as $a) echo "<option value='{$a['id']}'>{$a['brinco']} - {$a['nome']}</option>"; ?>
                    </select>
                </div>

                <div class="row">
                    <div class="col-6 mb-3">
                        <label class="small fw-bold">LITROS</label>
                        <input type="number" name="litros" step="0.1" class="form-control form-control-lg" placeholder="0.0" required>
                    </div>
                    <div class="col-6 mb-3">
                        <label class="small fw-bold">PERÍODO</label>
                        <select name="periodo" class="form-select form-select-lg">
                            <option value="Manhã">Manhã</option>
                            <option value="Tarde">Tarde</option>
                            <option value="Noite">Noite</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="small fw-bold">DATA</label>
                    <input type="date" name="data_registro" class="form-control" value="<?php echo $hoje; ?>">
                </div>

                <button type="submit" class="btn-record shadow-sm mt-3">SALVAR REGISTRO</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: GERENCIAR REBANHO -->
<div class="modal fade" id="modalVacas" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 25px;">
            <div class="modal-header border-0">
                <h5 class="fw-bold">Meu Rebanho</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <!-- FORMULÁRIO RÁPIDO PARA NOVA VACA -->
                <form action="acoes.php" method="POST" class="row g-2 mb-4 p-3 bg-light rounded-4">
                    <input type="hidden" name="acao" value="novo_animal">
                    <div class="col-4"><input type="text" name="brinco" class="form-control" placeholder="Brinco" required></div>
                    <div class="col-5"><input type="text" name="nome" class="form-control" placeholder="Nome da Vaca"></div>
                    <div class="col-3"><button class="btn btn-success w-100"><i class="fas fa-plus"></i></button></div>
                </form>

                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead><tr class="small"><th>Brinco</th><th>Nome</th><th>Status</th><th>Ação</th></tr></thead>
                        <tbody>
                            <?php
                            $res_vaca = $pdo->prepare("SELECT * FROM agro_animais WHERE usuario_id = ?");
                            $res_vaca->execute([$user_id]);
                            while($v = $res_vaca->fetch()):
                            ?>
                            <tr>
                                <td><b>#<?php echo $v['brinco']; ?></b></td>
                                <td><?php echo $v['nome']; ?></td>
                                <td><small class="badge bg-light text-dark border"><?php echo $v['status']; ?></small></td>
                                <td><a href="acoes.php?del_animal=<?php echo $v['id']; ?>" class="text-danger"><i class="fas fa-times"></i></a></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>