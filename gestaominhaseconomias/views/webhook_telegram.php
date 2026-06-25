<?php
// Configurações
define('TELEGRAM_TOKEN', '8501689130:AAHmwSQQr9M4gbiES64fYKj6BcyjNp0rZt0');
define('OPENAI_API_KEY', 'SUA_CHAVE_OPENAI');

// Conexão com o banco de dados (ajuste com seus dados)
$db = new mysqli("host", "usuario", "senha", "workspacebds");

// Recebe o input do Telegram
$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update) exit;

$chat_id = $update["message"]["chat"]["id"];
$text = $update["message"]["text"];
$telegram_user_id = $update["message"]["from"]["id"]; // ID único do Telegram

// 1. Identificar o usuário no seu sistema (via ID do Telegram ou comando de login)
// Aqui você deve buscar qual usuario_id do seu sistema pertence a esse chat_id
$user_id = 11; // Exemplo fixo conforme seu SQL (Usuário 11)

// 2. Tratar Comandos
if ($text == "/start") {
    enviarMensagem($chat_id, "Olá! Sou seu assistente financeiro. Pode digitar algo como: 'Paguei 50 reais de gasolina' ou use /resumo");
    exit;
}

if ($text == "/resumo") {
    // Lógica para buscar resumo no banco
    enviarMensagem($chat_id, "Seu saldo atual é R$ ..."); 
    exit;
}

// 3. Processamento com IA (OpenAI) para extrair dados
$dadosFinanceiros = interpretarComIA($text, $user_id, $db);

if ($dadosFinanceiros) {
    // 4. Salvar no Banco de Dados
    $tipo = $dadosFinanceiros['tipo']; // RECEITA ou DESPESA
    $categoria_id = $dadosFinanceiros['categoria_id'];
    $valor = $dadosFinanceiros['valor'];
    $descricao = $dadosFinanceiros['descricao'];
    $data = date('Y-m-d');

    // Nota: Você precisará criar a tabela 'lancamentos' se ainda não existir
    $stmt = $db->prepare("INSERT INTO minhaseconomias_lancamentos (usuario_id, tipo, categoria_id, valor, descricao, data_lancamento) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isidss", $user_id, $tipo, $categoria_id, $valor, $descricao, $data);
    
    if ($stmt->execute()) {
        enviarMensagem($chat_id, "✅ Lançamento registrado!\n🔹 *$descricao*\n💰 R$ " . number_format($valor, 2, ',', '.') . "\n📂 Categoria: " . $dadosFinanceiros['categoria_nome']);
    } else {
        enviarMensagem($chat_id, "❌ Erro ao salvar no banco.");
    }
}

// --- FUNÇÕES AUXILIARES ---

function enviarMensagem($chat_id, $message) {
    $url = "https://api.telegram.org/bot" . TELEGRAM_TOKEN . "/sendMessage?chat_id=$chat_id&text=" . urlencode($message) . "&parse_mode=Markdown";
    file_get_contents($url);
}

function interpretarComIA($text, $user_id, $db) {
    // Buscar categorias do usuário para a IA escolher a correta
    $res = $db->query("SELECT id, nome, tipo FROM minhaseconomias_categorias WHERE usuario_id = $user_id");
    $categorias = [];
    while($row = $res->fetch_assoc()) { $categorias[] = $row; }
    $cat_list = json_encode($categorias);

    $prompt = "Extraia os dados financeiros da frase: '$text'. 
    Categorias disponíveis (use o ID): $cat_list. 
    Retorne APENAS um JSON com os campos: tipo (RECEITA ou DESPESA), valor (decimal), categoria_id (int), categoria_nome (string), descricao (string).";

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . OPENAI_API_KEY
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        "model" => "gpt-3.5-turbo",
        "messages" => [["role" => "user", "content" => $prompt]],
        "temperature" => 0
    ]));

    $response = curl_exec($ch);
    $data = json_decode($response, true);
    return json_decode($data['choices'][0]['message']['content'], true);
}