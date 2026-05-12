<?php
/**
 * BDSoft Workspace - CONTROLE DE ORDENHA COM BI E EDIÇÃO
 */
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (file_exists('../config.php')) { require_once '../config.php'; } 
elseif (file_exists('../../config.php')) { require_once '../../config.php'; }

if (!isset($_SESSION['usuario_id'])) { header("Location: ../../login.php"); exit; }
$user_id = $_SESSION['usuario_id'];
$hoje = date('Y-m-d');

// --- CÁLCULOS PARA OS QUADRINHOS (KPIs) ---

// 1. Quantidade de Vacas que tiraram leite hoje
$stmt_v_hoje = $pdo->prepare("SELECT COUNT(DISTINCT vaca_id) FROM agro_leite_ordenha o INNER JOIN agro_leite_vacas v ON o.vaca_id = v.id WHERE v.usuario_id = ? AND o.data = ?");
$stmt_v_hoje->execute([$user_id, $hoje]);
$total_vacas_hoje = $stmt_v_hoje->fetchColumn() ?: 0;

// 2. Produção por Turno (Hoje)
$stmt_turnos = $pdo->prepare("SELECT turno, SUM(litros) as total FROM agro_leite_ordenha o INNER JOIN agro_leite_vacas v ON o.vaca_id = v.id WHERE v.usuario_id = ? AND o.data = ? GROUP BY turno");
$stmt_turnos->execute([$user_id, $hoje]);
$producao_turno = $stmt_turnos->fetchAll(PDO::FETCH_KEY_PAIR);

// 3. Produção Total Hoje
$total_litros_hoje = array_sum($producao_turno);

// --- DADOS PARA O BI (GRÁFICO ÚLTIMOS 7 DIAS) ---
$stmt_bi = $pdo->prepare("SELECT data, SUM(litros) as total FROM agro_leite_ordenha o INNER JOIN agro_leite_vacas v ON o.vaca_id = v.id WHERE v.usuario_id = ? GROUP BY data ORDER BY data DESC LIMIT 7");
$stmt_bi->execute([$user_id]);
$dados_bi = array_reverse($stmt_bi->fetchAll(PDO::FETCH_ASSOC));

// --- LISTAGEM E SELECTS ---
$vacas = $pdo->prepare("SELECT id, codigo_brinco, nome FROM agro_leite_vacas WHERE usuario_id = ? AND status = 'Ativa' ORDER BY nome ASC");
$vacas->execute([$user_id]);
$lista_vacas = $vacas->fetchAll(PDO::FETCH_ASSOC);

$ordenhas = $pdo->prepare("SELECT o.*, v.codigo_brinco, v.nome as vaca_nome 
                         FROM agro_leite_ordenha o 
                         INNER JOIN agro_leite_vacas v ON o.vaca_id = v.id 
                         WHERE v.usuario_id = ? AND o.data = ? 
                         ORDER BY o.id DESC");
$ordenhas->execute([$user_id, $hoje]);
$lista_ordenhas = $ordenhas->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Ordenha & BI - AgroCampo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f7f4; display: flex; min-height: 100vh; font-family: 'Segoe UI', sans-serif; }
        .main-wrapper { flex: 1; margin-left: 280px; padding: 40px; width: calc(100% - 280px); transition: 0.3s; }
        .card-agro { border: none; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); background: #fff; }
        .kpi-box { border-radius: 15px; padding: 20px; color: white; height: 100%; }
        @media (max-width: 991px) { .main-wrapper { margin-left: 0; width: 100%; padding: 20px; } }
    </style>
</head>
<body>
<?php include 'sidebar_leite.php'; ?>

<div class="main-wrapper">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-dark">Monitoramento de Ordenha</h2>
        <button class="btn btn-primary rounded-pill px-4 fw-bold shadow" data-bs-toggle="modal" data-bs-target="#modalOrdenha">
            <i class="fas fa-plus me-2"></i>NOVA RETIRADA
        </button>
    </div>

    <!-- QUADRINHOS DE ANÁLISE (KPIs) -->
    <div class="row g-3 mb-5">
        <div class="col-md-3">
            <div class="kpi-box shadow-sm" style="background: linear-gradient(45deg, #1a73e8, #0d47a1);">
                <small class="fw-bold opacity-75">VACAS ORDENHADAS</small>
                <h3 class="fw-bold mb-0"><?php echo $total_vacas_hoje; ?> <small style="font-size: 14px;">Cabeças</small></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-box shadow-sm" style="background: linear-gradient(45deg, #34a853, #1b5e20);">
                <small class="fw-bold opacity-75">MANHÃ</small>
                <h3 class="fw-bold mb-0"><?php echo number_format($producao_turno['Manhã'] ?? 0, 1, ',', '.'); ?> <small style="font-size: 14px;">Litros</small></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-box shadow-sm" style="background: linear-gradient(45deg, #fbbc05, #e65100);">
                <small class="fw-bold opacity-75">TARDE</small>
                <h3 class="fw-bold mb-0"><?php echo number_format($producao_turno['Tarde'] ?? 0, 1, ',', '.'); ?> <small style="font-size: 14px;">Litros</small></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-box shadow-sm" style="background: linear-gradient(45deg, #673ab7, #311b92);">
                <small class="fw-bold opacity-75">PRODUÇÃO TOTAL</small>
                <h3 class="fw-bold mb-0"><?php echo number_format($total_litros_hoje, 1, ',', '.'); ?> <small style="font-size: 14px;">L</small></h3>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- GRÁFICO BI -->
        <div class="col-lg-12 mb-4">
            <div class="card card-agro p-4">
                <h5 class="fw-bold mb-4"><i class="fas fa-chart-line text-primary me-2"></i>Tendência de Produção (Últimos 7 dias)</h5>
                <canvas id="chartLeite" style="width: 100%; height: 250px;"></canvas>
            </div>
        </div>

        <!-- GRID DE LANÇAMENTOS -->
        <div class="col-lg-12">
            <div class="card card-agro overflow-hidden">
                <div class="card-header bg-white py-3 fw-bold">Retiradas de Hoje</div>
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small">
                        <tr><th>Vaca (Brinco)</th><th>Turno</th><th>Litros</th><th>Qualidade</th><th>CCS</th><th class="text-center">Ações</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($lista_ordenhas as $o): ?>
                        <tr>
                            <td><strong><?php echo $o['codigo_brinco']; ?></strong> - <?php echo $o['vaca_nome']; ?></td>
                            <td><?php echo $o['turno']; ?></td>
                            <td class="fw-bold text-primary"><?php echo number_format($o['litros'], 1, ',', '.'); ?> L</td>
                            <td><span class="badge bg-info"><?php echo $o['qualidade']; ?></span></td>
                            <td><?php echo $o['ccs'] ?: '-'; ?></td>
                            <td class="text-center">
                                <button class="btn btn-sm text-primary border-0" onclick='abrirEditar(<?php echo json_encode($o); ?>)'>
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a href="acoes_leite.php?del_ordenha=<?php echo $o['id']; ?>" class="btn btn-sm text-danger border-0" onclick="return confirm('Excluir?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODAL ORDENHA (NOVO) -->
<div class="modal fade" id="modalOrdenha" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form action="acoes_leite.php" method="POST" class="modal-content border-0 shadow-lg" style="border-radius:20px;">
            <input type="hidden" name="acao" value="lancar_ordenha">
            <div class="modal-header bg-primary text-white border-0 p-4">
                <h5 class="fw-bold mb-0">Novo Lançamento</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="small fw-bold">VACA</label>
                    <select name="vaca_id" class="form-select" required>
                        <?php foreach($lista_vacas as $v): ?>
                        <option value="<?php echo $v['id']; ?>"><?php echo $v['codigo_brinco'] . " - " . $v['nome']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row">
                    <div class="col-6 mb-3"><label class="small fw-bold">DATA</label><input type="date" name="data" class="form-control" value="<?php echo $hoje; ?>" required></div>
                    <div class="col-6 mb-3"><label class="small fw-bold">TURNO</label>
                        <select name="turno" class="form-select"><option>Manhã</option><option>Tarde</option><option>Noite</option></select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-6 mb-3"><label class="small fw-bold">LITROS</label><input type="text" name="litros" class="form-control" placeholder="0.0" required></div>
                    <div class="col-6 mb-3"><label class="small fw-bold">QUALIDADE</label>
                        <select name="qualidade" class="form-select"><option>Excelente</option><option value="Boa" selected>Boa</option><option>Regular</option><option>Ruim</option></select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-6 mb-3"><label class="small fw-bold">CCS</label><input type="number" name="ccs" class="form-control"></div>
                    <div class="col-6 mb-3"><label class="small fw-bold">RESPONSÁVEL</label><input type="text" name="responsavel" class="form-control"></div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0"><button type="submit" class="btn btn-primary w-100 rounded-pill py-2 fw-bold">SALVAR PRODUÇÃO</button></div>
        </form>
    </div>
</div>

<!-- MODAL EDITAR (NOVO) -->
<div class="modal fade" id="modalEditarOrdenha" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form action="acoes_leite.php" method="POST" class="modal-content border-0 shadow-lg" style="border-radius:20px;">
            <input type="hidden" name="acao" value="editar_ordenha">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-header bg-dark text-white border-0 p-4">
                <h5 class="fw-bold mb-0">Editar Retirada</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row">
                    <div class="col-6 mb-3"><label class="small fw-bold">LITROS</label><input type="text" name="litros" id="edit_litros" class="form-control" required></div>
                    <div class="col-6 mb-3"><label class="small fw-bold">TURNO</label>
                        <select name="turno" id="edit_turno" class="form-select"><option>Manhã</option><option>Tarde</option><option>Noite</option></select>
                    </div>
                </div>
                <div class="mb-3"><label class="small fw-bold">QUALIDADE</label>
                    <select name="qualidade" id="edit_qualidade" class="form-select"><option>Excelente</option><option>Boa</option><option>Regular</option><option>Ruim</option></select>
                </div>
                <div class="row">
                    <div class="col-6 mb-3"><label class="small fw-bold">CCS</label><input type="number" name="ccs" id="edit_ccs" class="form-control"></div>
                    <div class="col-6 mb-3"><label class="small fw-bold">TEMP.</label><input type="text" name="temperatura" id="edit_temp" class="form-control"></div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0"><button type="submit" class="btn btn-dark w-100 rounded-pill py-2 fw-bold">ATUALIZAR DADOS</button></div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    // --- LÓGICA DO GRÁFICO BI ---
    const ctx = document.getElementById('chartLeite').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: [<?php foreach($dados_bi as $d) echo "'".date('d/m', strtotime($d['data']))."',"; ?>],
            datasets: [{
                label: 'Litros Produzidos',
                data: [<?php foreach($dados_bi as $d) echo $d['total'].","; ?>],
                borderColor: '#1a73e8',
                backgroundColor: 'rgba(26, 115, 232, 0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: { responsive: true, plugins: { legend: { display: false } } }
    });

    // --- FUNÇÃO PARA EDITAR ---
    function abrirEditar(dados) {
        document.getElementById('edit_id').value = dados.id;
        document.getElementById('edit_litros').value = dados.litros;
        document.getElementById('edit_turno').value = dados.turno;
        document.getElementById('edit_qualidade').value = dados.qualidade;
        document.getElementById('edit_ccs').value = dados.ccs;
        document.getElementById('edit_temp').value = dados.temperatura;
        new bootstrap.Modal(document.getElementById('modalEditarOrdenha')).show();
    }
</script>
</body>
</html>