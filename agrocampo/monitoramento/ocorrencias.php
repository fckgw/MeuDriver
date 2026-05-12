<?php
/**
 * BDSoft Workspace - OCORRÊNCIAS E IA AGRÍCOLA
 * Localização: agrocampo/monitoramento/ocorrencias.php
 */
session_start();
require_once '../../config.php';

if (!isset($_SESSION['usuario_id'])) { header("Location: ../../login.php"); exit; }

$user_id = $_SESSION['usuario_id'];

// Busca talhões para o select
$stmt_t = $pdo->prepare("SELECT t.id, t.nome, f.nome as fazenda FROM agro_talhoes t INNER JOIN agro_fazendas f ON t.fazenda_id = f.id WHERE f.usuario_id = ?");
$stmt_t->execute([$user_id]);
$talhoes = $stmt_t->fetchAll(PDO::FETCH_ASSOC);

// Busca histórico de ocorrências
$stmt_h = $pdo->prepare("SELECT o.*, t.nome as talhao_nome FROM agro_ocorrencias o INNER JOIN agro_talhoes t ON o.talhao_id = t.id INNER JOIN agro_fazendas f ON t.fazenda_id = f.id WHERE f.usuario_id = ? ORDER BY o.data_registro DESC");
$stmt_h->execute([$user_id]);
$historico = $stmt_h->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Ocorrências e IA - AgroCampo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f7f4; display: flex; }
        .main-wrapper { flex: 1; margin-left: 280px; padding: 40px; width: calc(100% - 280px); }
        .card-agro { border: none; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); background: #fff; }
        .felipinho-avatar { width: 50px; height: 50px; border-radius: 50%; background: #2ecc71; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem; }
        .chat-ia { background: #e8f5e9; border-radius: 15px; padding: 15px; border-left: 5px solid #2ecc71; }
        @media (max-width: 991px) { .main-wrapper { margin-left: 0; width: 100%; } }
    </style>
</head>
<body>

<?php include 'sidebar_monitoramento.php'; ?>

<div class="main-wrapper">
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h2 class="fw-bold text-dark mb-0">Alertas e Manejo</h2>
            <p class="text-muted small">Identifique pragas e receba orientações do Felipinho IA.</p>
        </div>
        <button class="btn btn-danger rounded-pill px-4 fw-bold shadow" data-bs-toggle="modal" data-bs-target="#modalOcorrencia">
            <i class="fas fa-exclamation-triangle me-2"></i>LANÇAR OCORRÊNCIA
        </button>
    </div>

    <div class="row">
        <!-- LISTA DE OCORRÊNCIAS ATIVAS -->
        <div class="col-lg-12">
            <h5 class="fw-bold mb-4">Linha do Tempo de Manejo</h5>
            <?php foreach($historico as $h): ?>
            <div class="card card-agro p-4 mb-4">
                <div class="d-flex justify-content-between">
                    <div>
                        <span class="badge bg-<?php echo ($h['gravidade'] == 'Alta' || $h['gravidade'] == 'Crítica') ? 'danger' : 'warning'; ?> mb-2">
                            GRAVIDADE <?php echo strtoupper($h['gravidade']); ?>
                        </span>
                        <h5 class="fw-bold mb-1"><?php echo $h['identificacao']; ?> no Talhão <?php echo $h['talhao_nome']; ?></h5>
                        <small class="text-muted">Detectado em: <?php echo date('d/m/Y H:i', strtotime($h['data_registro'])); ?></small>
                    </div>
                    <div class="text-end">
                        <span class="badge outline-primary border text-primary"><?php echo $h['status_resolucao']; ?></span>
                    </div>
                </div>
                
                <p class="mt-3 text-dark"><?php echo $h['descricao_produtor']; ?></p>

                <div class="chat-ia mt-3">
                    <div class="d-flex align-items-center mb-2">
                        <div class="felipinho-avatar me-2"><i class="fas fa-robot"></i></div>
                        <strong class="text-success">Felipinho (Consultor IA):</strong>
                    </div>
                    <div class="small fst-italic">
                        <?php echo nl2br($h['orientacao_ia']); ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- MODAL: LANÇAR OCORRÊNCIA -->
<div class="modal fade" id="modalOcorrencia" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form action="acoes_monitoramento.php" method="POST" class="modal-content border-0 shadow-lg" style="border-radius:25px;">
            <input type="hidden" name="acao" value="lancar_ocorrencia">
            <div class="modal-header bg-danger text-white p-4 border-0">
                <h5 class="fw-bold mb-0"><i class="fas fa-bug me-2"></i>Registrar Problema na Lavoura</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="small fw-bold">QUAL O TALHÃO?</label>
                        <select name="talhao_id" class="form-select" required>
                            <option value="">Selecione o talhão...</option>
                            <?php foreach($talhoes as $t): ?>
                                <option value="<?php echo $t['id']; ?>"><?php echo $t['fazenda']; ?> - <?php echo $t['nome']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="small fw-bold">TIPO DE OCORRÊNCIA</label>
                        <select name="tipo_problema" class="form-select" required>
                            <option value="Praga">Praga (Insetos)</option>
                            <option value="Doença">Doença (Fungos/Bactérias)</option>
                            <option value="Nutricional">Deficiência Nutricional</option>
                            <option value="Climático">Dano Climático (Geada/Granizo)</option>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label class="small fw-bold">IDENTIFICAÇÃO (O QUE VOCÊ VIU?)</label>
                        <input type="text" name="identificacao" class="form-control" placeholder="Ex: Lagarta do Cartucho, Ferrugem Asiática..." required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="small fw-bold">GRAVIDADE ATUAL</label>
                        <select name="gravidade" class="form-select">
                            <option value="Baixa">Baixa (Início)</option>
                            <option value="Média">Média (Espalhando)</option>
                            <option value="Alta">Alta (Dano visível)</option>
                            <option value="Crítica">Crítica (Risco de Perda)</option>
                        </select>
                    </div>
                </div>

                <div class="mb-0">
                    <label class="small fw-bold">DESCRIÇÃO DOS SINTOMAS</label>
                    <textarea name="descricao" class="form-control" rows="3" placeholder="Descreva como estão as plantas..."></textarea>
                </div>
                
                <div class="mt-3 p-3 bg-light rounded-3 text-center border">
                    <i class="fas fa-robot text-success me-2"></i> O Felipinho analisará sua ocorrência e dará as orientações após o envio.
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="submit" class="btn btn-danger w-100 rounded-pill py-3 fw-bold">ENVIAR E CONSULTAR IA</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>