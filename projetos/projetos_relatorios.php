<?php
/**
 * BDSoft Workspace - RELATÓRIOS E BUSINESS INTELLIGENCE (BI)
 * Local: projetos_relatorios.php
 */

// 1. Configurações de Erro e Sessão
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config.php';

$user_id_sessao = $_SESSION['usuario_id'];
$id_quadro = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// 2. Validar Existência do Quadro e Acesso
$stmt_quadro = $pdo->prepare("SELECT nome FROM quadros_projetos WHERE id = ?");
$stmt_quadro->execute([$id_quadro]);
$dados_quadro = $stmt_quadro->fetch();

if (!$dados_quadro) {
    die("<div style='text-align:center; padding:50px; font-family:sans-serif;'><h2>❌ Quadro não encontrado</h2><a href='projetos_home.php'>Voltar</a></div>");
}

$nome_projeto = $dados_quadro['nome'];

// 3. Buscar Todas as Tarefas do Quadro (Query Consolidada)
$sql_tarefas = "SELECT 
                    t.id, 
                    t.titulo, 
                    t.prioridade, 
                    t.data_fim,
                    IFNULL(s.label, 'Sem Status') as status_nome, 
                    IFNULL(s.cor, '#bdc3c7') as status_cor, 
                    IFNULL(g.nome, 'Geral') as grupo_nome 
                FROM tarefas_projetos t
                LEFT JOIN quadros_status s ON t.status_id = s.id
                LEFT JOIN projetos_grupos g ON t.grupo_id = g.id
                WHERE t.quadro_id = :qid
                ORDER BY g.ordem ASC, t.id DESC";

$stmt_tarefas = $pdo->prepare($sql_tarefas);
$stmt_tarefas->execute([':qid' => $id_quadro]);
$lista_tarefas = $stmt_tarefas->fetchAll(PDO::FETCH_ASSOC);

// 4. Agrupar Dados para o Gráfico (Chart.js)
$agrupamento_status = [];
foreach ($lista_tarefas as $tarefa) {
    $label = $tarefa['status_nome'];
    if (!isset($agrupamento_status[$label])) {
        $agrupamento_status[$label] = [
            'quantidade' => 0,
            'cor' => $tarefa['status_cor']
        ];
    }
    $agrupamento_status[$label]['quantidade']++;
}

// Preparar variáveis JSON para o JavaScript
$js_labels = array_keys($agrupamento_status);
$js_valores = array_column($agrupamento_status, 'quantidade');
$js_cores = array_column($agrupamento_status, 'cor');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BI - <?php echo htmlspecialchars($nome_projeto); ?> - BDSoft</title>
    
    <!-- Bootstrap 5, FontAwesome e Chart.js -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        body { background-color: #f4f7f6; font-family: 'Segoe UI', system-ui, sans-serif; }
        .card-bi { background: #ffffff; border: none; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); height: 100%; }
        .nav-report { background: #fff; border-bottom: 1px solid #dee2e6; padding: 15px 30px; position: sticky; top: 0; z-index: 1000; }
        .status-badge { padding: 5px 12px; border-radius: 20px; color: #fff; font-size: 10px; font-weight: bold; text-transform: uppercase; }
        
        /* Interatividade da Tabela */
        .task-row { transition: 0.2s; }
        .row-highlight { background-color: #e8f0fe !important; font-weight: bold; }
        .row-hidden { display: none; }

        @media print {
            .no-print { display: none !important; }
            body { background: #fff; padding: 0; }
            .card-bi { box-shadow: none; border: 1px solid #ddd; }
        }
    </style>
</head>
<body class="p-0">

<!-- NAVBAR DE RELATÓRIO -->
<nav class="nav-report d-flex justify-content-between align-items-center mb-4 no-print shadow-sm">
    <div class="d-flex align-items-center">
        <a href="projetos_quadro.php?id=<?php echo $id_quadro; ?>" class="btn btn-sm btn-light border rounded-circle me-3"><i class="fas fa-arrow-left"></i></a>
        <div>
            <h4 class="fw-bold mb-0">Analytics & BI</h4>
            <small class="text-primary fw-bold"><?php echo htmlspecialchars($nome_projeto); ?></small>
        </div>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-danger btn-sm rounded-pill px-4 fw-bold shadow-sm">
            <i class="fas fa-file-pdf me-2"></i>PDF
        </button>
        <button data-bs-toggle="modal" data-bs-target="#modalEmail" class="btn btn-primary btn-sm rounded-pill px-4 fw-bold shadow-sm">
            <i class="fas fa-envelope me-2"></i>E-MAIL
        </button>
    </div>
</nav>

<div class="container-fluid px-4">
    <div class="row g-4">
        <!-- COLUNA 1: GRÁFICO DE DISTRIBUIÇÃO -->
        <div class="col-md-5">
            <div class="card-bi p-5 text-center">
                <h6 class="fw-bold text-muted mb-5 text-uppercase small">Distribuição de Tarefas por Status</h6>
                
                <?php if (empty($lista_tarefas)): ?>
                    <div class="py-5 text-muted">
                        <i class="fas fa-chart-pie fa-3x mb-3 opacity-25"></i>
                        <p>Sem dados para gerar o gráfico.</p>
                    </div>
                <?php else: ?>
                    <div style="max-width: 320px; margin: auto;">
                        <canvas id="graficoStatus"></canvas>
                    </div>
                    <div class="mt-5 no-print">
                        <p class="small text-muted"><i class="fas fa-mouse-pointer me-2"></i>Clique nas fatias para filtrar o detalhamento.</p>
                        <button class="btn btn-sm btn-link text-primary text-decoration-none fw-bold" onclick="resetarFiltroTabela()">
                            <i class="fas fa-sync-alt me-1"></i> MOSTRAR TODAS AS ATIVIDADES
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- COLUNA 2: LISTAGEM DETALHADA -->
        <div class="col-md-7">
            <div class="card-bi p-4">
                <h6 class="fw-bold text-muted mb-4 text-uppercase small">Detalhamento das Atividades</h6>
                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="tabelaDadosBI">
                        <thead class="table-light">
                            <tr class="small text-muted">
                                <th>NOME DA TAREFA</th>
                                <th>GRUPO / SPRINT</th>
                                <th class="text-center">STATUS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($lista_tarefas as $t): ?>
                            <tr class="task-row" data-status-label="<?php echo htmlspecialchars($t['status_nome']); ?>">
                                <td class="fw-bold"><?php echo htmlspecialchars($t['titulo']); ?></td>
                                <td class="small text-muted"><?php echo htmlspecialchars($t['grupo_nome']); ?></td>
                                <td class="text-center">
                                    <span class="status-badge" style="background-color: <?php echo $t['status_cor']; ?>">
                                        <?php echo htmlspecialchars($t['status_nome']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: ENVIAR RELATÓRIO POR E-MAIL -->
<div class="modal fade" id="modalEmail" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-0">
                <h5 class="modal-title fw-bold"><i class="fas fa-paper-plane me-2"></i>Enviar Relatório</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="small fw-bold mb-1">DESTINATÁRIO PRINCIPAL</label>
                    <input type="email" id="email_para" class="form-control" placeholder="cliente@empresa.com" required>
                </div>
                <div class="mb-3">
                    <label class="small fw-bold mb-1">COM CÓPIA (CC)</label>
                    <input type="email" id="email_copia" class="form-control" placeholder="gestor@empresa.com">
                </div>
                <div class="alert alert-light border small text-muted">
                    O sistema enviará um resumo executivo HTML com o status atual do projeto e o link de acesso.
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" onclick="dispararEmailRelatorio()" id="btnDisparar" class="btn btn-primary rounded-pill px-5 fw-bold shadow">
                    ENVIAR RELATÓRIO
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // 1. Iniciar Gráfico Chart.js
    const labelsBI = <?php echo json_encode($js_labels); ?>;
    const valoresBI = <?php echo json_encode($js_valores); ?>;
    const coresBI = <?php echo json_encode($js_cores); ?>;

    const canvas = document.getElementById('graficoStatus');
    if (canvas) {
        const ctx = canvas.getContext('2d');
        const biChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labelsBI,
                datasets: [{
                    data: valoresBI,
                    backgroundColor: coresBI,
                    borderWidth: 0,
                    hoverOffset: 20
                }]
            },
            options: {
                cutout: '75%',
                plugins: {
                    legend: { position: 'bottom', labels: { usePointStyle: true, padding: 25, font: { size: 12, weight: 'bold' } } }
                },
                // Lógica de Drill-down
                onClick: (event, elements) => {
                    if (elements.length > 0) {
                        const index = elements[0].index;
                        const statusNome = labelsBI[index];
                        filtrarTabelaPorStatus(statusNome);
                    }
                }
            }
        });
    }

    // 2. Filtro Visual de Linhas
    function filtrarTabelaPorStatus(nome) {
        const rows = document.querySelectorAll('.task-row');
        rows.forEach(row => {
            if (row.getAttribute('data-status-label') === nome) {
                row.classList.remove('row-hidden');
                row.classList.add('row-highlight');
            } else {
                row.classList.add('row-hidden');
                row.classList.remove('row-highlight');
            }
        });
    }

    function resetarFiltroTabela() {
        document.querySelectorAll('.task-row').forEach(r => r.classList.remove('row-hidden', 'row-highlight'));
    }

    // 3. Disparo de E-mail via AJAX
    function dispararEmailRelatorio() {
        const para = document.getElementById('email_para').value;
        const copia = document.getElementById('email_copia').value;
        const btn = document.getElementById('btnDisparar');

        if (!para) { alert("Informe o e-mail de destino."); return; }

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>ENVIANDO...';

        const fd = new FormData();
        fd.append('acao', 'email_relatorio');
        fd.append('id', '<?php echo $id_quadro; ?>');
        fd.append('para', para);
        fd.append('copia', copia);

        fetch('projetos_acoes.php', { method: 'POST', body: fd })
        .then(res => res.text())
        .then(data => {
            alert("E-mail enviado com sucesso!");
            btn.disabled = false;
            btn.innerText = "ENVIAR RELATÓRIO";
            bootstrap.Modal.getInstance(document.getElementById('modalEmail')).hide();
        })
        .catch(err => {
            alert("Erro ao processar envio.");
            btn.disabled = false;
        });
    }
</script>
</body>
</html>