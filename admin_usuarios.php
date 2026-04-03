<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_nivel'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Lógica de Processamento
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    
    if ($acao === 'editar_usuario') {
        $id = $_POST['usuario_id'];
        $nivel = $_POST['nivel'];
        $status = $_POST['status'];
        $data_i = !empty($_POST['data_inicio']) ? $_POST['data_inicio'] : null;
        $data_f = !empty($_POST['data_fim']) ? $_POST['data_fim'] : null;
        
        $stmt = $pdo->prepare("UPDATE usuarios SET nivel = ?, status = ?, data_inicio = ?, data_fim = ? WHERE id = ?");
        $stmt->execute([$nivel, $status, $data_i, $data_f, $id]);
        echo "Sucesso"; exit;
    }
    
    if ($acao === 'deletar') {
        $pdo->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$_POST['id']]);
        echo "Sucesso"; exit;
    }
}

// Dados para Gráfico
$stmt_g = $pdo->query("SELECT DATE_FORMAT(data_fim, '%m/%Y') as mes, COUNT(*) as total FROM usuarios WHERE data_fim IS NOT NULL GROUP BY mes");
$grafico_dados = $stmt_g->fetchAll(PDO::FETCH_KEY_PAIR);

// Lista de usuários
$stmt = $pdo->query("SELECT * FROM usuarios ORDER BY nome ASC");
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Usuários - Workspace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background-color: #f4f7f6; }
        .card { border-radius: 15px; border: none; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .table-responsive { overflow-x: auto; }
        @media (max-width: 768px) { .btn-text { display: none; } }
    </style>
</head>
<body class="p-3">

<div class="container-fluid">
    <div class="d-flex justify-content-between mb-4">
        <a href="portal.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
        <h4 class="fw-bold">Gestão Administrativa</h4>
    </div>

    <!-- Gráfico -->
    <div class="card p-3 mb-4">
        <canvas id="meuGrafico" style="max-height: 200px;"></canvas>
    </div>

    <!-- Tabela -->
    <div class="card p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr><th>Usuário</th><th>Nível</th><th>Status</th><th>Vencimento</th><th>Ações</th></tr>
                </thead>
                <tbody>
                    <?php foreach($usuarios as $u): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($u['nome']) ?></strong></td>
                        <td><?= ucfirst($u['nivel']) ?></td>
                        <td><span class="badge <?= $u['status']=='ativo'?'bg-success':'bg-danger' ?>"><?= ucfirst($u['status']) ?></span></td>
                        <td><?= $u['data_fim'] ? date('d/m/Y', strtotime($u['data_fim'])) : 'Vitalício' ?></td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="abrirModal(<?= htmlspecialchars(json_encode($u)) ?>)">Editar</button>
                            <button class="btn btn-sm btn-danger" onclick="deletar(<?= $u['id'] ?>)">Excluir</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Edição -->
<div class="modal fade" id="modalEdicao" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content p-4">
            <h5>Configurar Usuário</h5>
            <input type="hidden" id="edit_id">
            <div class="row g-3">
                <div class="col-6"><label>Nível</label><select id="edit_nivel" class="form-select"><option value="membro">Membro</option><option value="admin">Administrador</option></select></div>
                <div class="col-6"><label>Status</label><select id="edit_status" class="form-select"><option value="ativo">Ativo</option><option value="bloqueado">Bloqueado</option></select></div>
                <div class="col-6"><label>Data Início</label><input type="date" id="edit_inicio" class="form-control" onchange="calcularFim()"></div>
                <div class="col-6"><label>Renovação (Meses)</label><select id="edit_periodo" class="form-select" onchange="calcularFim()"><option value="0">Manual/Vitalício</option><option value="3">3 Meses</option><option value="6">6 Meses</option><option value="9">9 Meses</option><option value="12">12 Meses</option></select></div>
                <div class="col-12"><label>Data Fim</label><input type="date" id="edit_fim" class="form-control"></div>
            </div>
            <button class="btn btn-success mt-4 w-100" onclick="salvar()">Salvar Alterações</button>
        </div>
    </div>
</div>

<script>
    const ctx = document.getElementById('meuGrafico');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_keys($grafico_dados)) ?>,
            datasets: [{ label: 'Usuários a vencer por mês', data: <?= json_encode(array_values($grafico_dados)) ?>, backgroundColor: '#0d6efd' }]
        }
    });

    function calcularFim() {
        const inicio = new Date($('#edit_inicio').val());
        const meses = parseInt($('#edit_periodo').val());
        if(meses > 0 && !isNaN(inicio.getTime())) {
            inicio.setMonth(inicio.getMonth() + meses);
            $('#edit_fim').val(inicio.toISOString().split('T')[0]);
        }
    }

    function abrirModal(u) {
        $('#edit_id').val(u.id);
        $('#edit_nivel').val(u.nivel);
        $('#edit_status').val(u.status);
        $('#edit_inicio').val(u.data_inicio);
        $('#edit_fim').val(u.data_fim);
        new bootstrap.Modal('#modalEdicao').show();
    }

    function salvar() {
        $.post('', {
            acao: 'editar_usuario',
            usuario_id: $('#edit_id').val(),
            nivel: $('#edit_nivel').val(),
            status: $('#edit_status').val(),
            data_inicio: $('#edit_inicio').val(),
            data_fim: $('#edit_fim').val()
        }, () => location.reload());
    }

    function deletar(id) {
        Swal.fire({title: 'Confirmar exclusão?', icon: 'warning', showCancelButton: true}).then(r => {
            if(r.isConfirmed) $.post('', {acao: 'deletar', id: id}, () => location.reload());
        });
    }
</script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>