<?php
session_start();
require_once '../../config.php';
$user_id = $_SESSION['usuario_id'];
$acao = $_POST['acao'] ?? '';



if ($acao == 'cadastrar_fazenda') {
    $nome         = $_POST['nome'];
    $proprietario = $_POST['proprietario'];
    $cidade       = $_POST['cidade'];
    $estado       = $_POST['estado'];
    $area_total   = $_POST['area_total'];
    $latitude     = $_POST['latitude'];
    $longitude    = $_POST['longitude'];
    $usuario_id   = $_SESSION['usuario_id'];

    $sql = "INSERT INTO agro_fazendas (nome, proprietario, cidade, estado, area_total, latitude, longitude, usuario_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$nome, $proprietario, $cidade, $estado, $area_total, $latitude, $longitude, $usuario_id]);

    header("Location: fazendas.php?sucesso=1");
    exit;
}


if ($acao == 'lancar_ocorrencia') {
    $talhao_id = $_POST['talhao_id'];
    $tipo = $_POST['tipo_problema'];
    $identificacao = $_POST['identificacao'];
    $gravidade = $_POST['gravidade'];
    $descricao = $_POST['descricao'];

    // LÓGICA DO FELIPINHO (IA)
    // Aqui simulamos o processamento da IA baseada na praga e gravidade
    $orientacao = "Olá! Aqui é o Felipinho. Analisei sua ocorrência de $identificacao. \n\n";

    if ($tipo == 'Praga') {
        $orientacao .= "Para pragas desse tipo, recomendo monitorar a incidência por m². ";
        if ($gravidade == 'Alta' || $gravidade == 'Crítica') {
            $orientacao .= "URGENTE: O nível de dano econômico foi atingido. Recomendo aplicação imediata de inseticida específico e rotação de ativos para evitar resistência.";
        } else {
            $orientacao .= "Nível ainda controlado. Utilize controle biológico se possível e monitore a cada 2 dias.";
        }
    } elseif ($tipo == 'Doença') {
        $orientacao .= "Atenção com a umidade! Fungos se espalham rápido com chuva. Aplique preventivos se a previsão for de tempo fechado.";
    } else {
        $orientacao .= "Verifique a análise de solo deste talhão e o histórico de adubação. Pode ser falta de micronutrientes.";
    }

    $orientacao .= "\n\nNão perca o tempo de manejo para garantir sua produtividade!";

    $sql = "INSERT INTO agro_ocorrencias (talhao_id, tipo_problema, identificacao, gravidade, descricao_produtor, orientacao_ia) 
            VALUES (?, ?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$talhao_id, $tipo, $identificacao, $gravidade, $descricao, $orientacao]);

    header("Location: ocorrencias.php?sucesso=1");
    exit;
}

if ($acao == 'cadastrar_talhao') {
    $stmt = $pdo->prepare("INSERT INTO agro_talhoes (fazenda_id, nome, area_hectares, tipo_solo, coordenadas_json, status) VALUES (?,?,?,?,?, 'Vazio')");
    $stmt->execute([$_POST['fazenda_id'], $_POST['nome'], $_POST['area_hectares'], $_POST['tipo_solo'], $_POST['coordenadas_json']]);
    header("Location: index.php?sucesso=1");
}