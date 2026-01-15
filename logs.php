<?php
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_nivel'] !== 'admin') {
    header("Location: login.php");
    exit;
}
require_once 'config.php';

// Filtros
$f_user = isset($_GET['f_user']) ? $_GET['f_user'] : '';
$f_acao = isset($_GET['f_acao']) ? $_GET['f_acao'] : '';
$d_inicio = isset($_GET['d_inicio']) ? $_GET['d_inicio'] : '';
$d_fim = isset($_GET['d_fim']) ? $_GET['d_fim'] : '';

$sql = "SELECT l.*, u.nome as nome_usuario 
        FROM logs l 
        LEFT JOIN usuarios u ON l.usuario_id = u.id 
        WHERE 1=1";
$params = [];

if ($f_user) { $sql .= " AND u.nome LIKE ?"; $params[] = "%$f_user%"; }
if ($f_acao) { $sql .= " AND l.acao = ?"; $params[] = $f_acao; }
if ($d_inicio) { $sql .= " AND l.data_hora >= ?"; $params[] = $d_inicio . " 00:00:00"; }
if ($d_fim) { $sql .= " AND l.data_hora <= ?"; $params[] = $d_fim . " 23:59:59"; }

$sql .= " ORDER BY l.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Logs - DriveBDS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f8f9fa; }
        .card { border-radius: 15px; border: none; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        @media print {
            .no-print { display: none !important; }
            body { background: #fff; padding: 0; }
            .card { box-shadow: none; border: 1px solid #eee; }
        }
    </style>
</head>
<body class="p-4">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4 no-print">
            <h3 class="fw-bold"><i class="fas fa-list-ul text-primary me-2"></i>Logs do Sistema</h3>
            <div>
                <button onclick="window.print()" class="btn btn-danger rounded-pill px-4 me-2"><i class="fas fa-file-pdf me-2"></i>PDF</button>
                <a href="dashboard.php" class="btn btn-outline-secondary rounded-pill px-4">Voltar</a>
            </div>
        </div>

        <div class="card p-4 mb-4 no-print">
            <form method="GET" class="row g-3">
                <div class="col-md-3"><label class="small fw-bold">USUÁRIO</label><input type="text" name="f_user" class="form-control" value="<?php echo htmlspecialchars($f_user); ?>"></div>
                <div class="col-md-2"><label class="small fw-bold">AÇÃO</label>
                    <select name="f_acao" class="form-select">
                        <option value="">Todas</option>
                        <option value="Login">Login</option>
                        <option value="Upload">Upload</option>
                        <option value="Excluir">Excluir</option>
                        <option value="Criar Pasta">Criar Pasta</option>
                    </select>
                </div>
                <div class="col-md-2"><label class="small fw-bold">DE:</label><input type="date" name="d_inicio" class="form-control" value="<?php echo $d_inicio; ?>"></div>
                <div class="col-md-2"><label class="small fw-bold">ATÉ:</label><input type="date" name="d_fim" class="form-control" value="<?php echo $d_fim; ?>"></div>
                <div class="col-md-3 d-flex align-items-end"><button type="submit" class="btn btn-primary w-100 fw-bold">FILTRAR</button></div>
            </form>
        </div>

        <div class="card overflow-hidden">
            <table class="table table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Data/Hora</th>
                        <th>Usuário</th>
                        <th>Ação</th>
                        <th>Detalhes</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($logs as $l): ?>
                    <tr>
                        <td class="small fw-bold"><?php echo date('d/m/Y H:i', strtotime($l['data_hora'])); ?></td>
                        <td><?php echo htmlspecialchars($l['nome_usuario'] ?: 'Sistema'); ?></td>
                        <td><span class="badge bg-secondary"><?php echo $l['acao']; ?></span></td>
                        <td class="small"><?php echo htmlspecialchars($l['detalhes']); ?></td>
                        <td class="small text-muted"><?php echo $l['ip']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>