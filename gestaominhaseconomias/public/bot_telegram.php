<?php
/**
 * PROJETO: Gestão Minhas Economias - Versão Totalmente Integrada
 * TABELA ALVO: minhaseconomias_movimentacoes
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

define('TELEGRAM_TOKEN', '8501689130:AAHmwSQQr9M4gbiES64fYKj6BcyjNp0rZt0');

// CONEXÃO COM O BANCO
$conexao = new mysqli("workspacebds.mysql.dbaas.com.br", "workspacebds", "BDSoft@1020", "workspacebds");
if ($conexao->connect_error) die("Erro de conexão");
$conexao->set_charset("utf8mb4");

$conteudo_recebido = file_get_contents("php://input");
$atualizacao = json_decode($conteudo_recebido, true);

if (!$atualizacao) {
    echo "<h1>✅ Bot Integrado ao Sistema Web</h1>";
    echo "Status: <b>Operacional</b>";
    exit;
}

$id_do_chat = isset($atualizacao["message"]) ? $atualizacao["message"]["chat"]["id"] : $atualizacao["callback_query"]["message"]["chat"]["id"];
$id_do_usuario_sistema = 11; 

// --- LÓGICA 1: RECEBIMENTO DE TEXTO ---
if (isset($atualizacao["message"]["text"])) {
    $texto_recebido = $atualizacao["message"]["text"];

    if ($texto_recebido == "/start") {
        enviarMensagemTelegram($id_do_chat, "💰 *Bem-vindo ao Sistema Integrado!*\nEnvie o gasto (Ex: `Pizza 80`) e eu registrarei diretamente no seu Dashboard.");
        exit;
    }

    if (preg_match('/(\d+[\.,]\d{2})|(\d+)/', $texto_recebido, $ocorrencias)) {
        $valor_extraido = str_replace(',', '.', $ocorrencias[0]);
        $descricao_limpa = trim(str_replace($ocorrencias[0], '', $texto_recebido));
        if (empty($descricao_limpa)) $descricao_limpa = "Gasto via Telegram";

        // 1. BUSCAR CATEGORIAS
        $resultado_categorias = $conexao->query("SELECT id, nome FROM minhaseconomias_categorias WHERE usuario_id = $id_do_usuario_sistema ORDER BY nome ASC LIMIT 10");
        
        if ($resultado_categorias->num_rows > 0) {
            $botoes_categorias = [];
            while ($categoria = $resultado_categorias->fetch_assoc()) {
                // Reduzimos a descrição para caber no limite de 64 bytes do callback_data
                $desc_curta = mb_substr($descricao_limpa, 0, 10, 'UTF-8');
                $botoes_categorias[] = [[
                    "text" => "📂 " . $categoria['nome'],
                    "callback_data" => "escolher_conta|$valor_extraido|$desc_curta|{$categoria['id']}"
                ]];
            }

            $teclado = ["inline_keyboard" => $botoes_categorias];
            enviarMensagemTelegram($id_chat = $id_do_chat, "💵 *Valor:* R$ " . number_format($valor_extraido, 2, ',', '.') . "\n📝 *Desc:* $descricao_limpa\n\n*1º Passo: Escolha a Categoria:*", $teclado);
        }
    }
}

// --- LÓGICA 2: PROCESSAMENTO DOS CLIQUES ---
if (isset($atualizacao["callback_query"])) {
    $id_do_callback = $atualizacao["callback_query"]["id"];
    $dados_do_botao = $atualizacao["callback_query"]["data"];
    $partes = explode('|', $dados_do_botao);
    $acao = $partes[0];

    // PASSO 2: ESCOLHER A CONTA BANCÁRIA
    if ($acao == 'escolher_conta') {
        $valor = $partes[1];
        $desc = $partes[2];
        $id_cat = $partes[3];

        $resultado_contas = $conexao->query("SELECT id, nome FROM minhaseconomias_contas WHERE usuario_id = $id_do_usuario_sistema AND status = 1");
        
        $botoes_contas = [];
        while ($conta = $resultado_contas->fetch_assoc()) {
            $botoes_contas[] = [[
                "text" => "💳 " . $conta['nome'],
                "callback_data" => "finalizar|$valor|$desc|$id_cat|{$conta['id']}"
            ]];
        }

        removerCarregamentoBotao($id_do_callback, "Categoria selecionada!");
        enviarMensagemTelegram($id_do_chat, "🏦 *2º Passo: Em qual conta/cartão?*", ["inline_keyboard" => $botoes_contas]);
    }

    // PASSO 3: SALVAR NA TABELA REAL (minhaseconomias_movimentacoes)
    if ($acao == 'finalizar') {
        $valor = $partes[1];
        $desc = $partes[2];
        $id_cat = $partes[3];
        $id_conta = $partes[4];
        $hoje = date('Y-m-d');

        // Seguindo exatamente os campos da sua index.php
        $sql = "INSERT INTO minhaseconomias_movimentacoes 
                (usuario_id, conta_id, categoria_id, descricao, valor, data_vencimento, data_pagamento, status, tipo) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'Pago', 'DESPESA')";
        
        $comando = $conexao->prepare($sql);
        $comando->bind_param("iiissss", $id_do_usuario_sistema, $id_conta, $id_cat, $desc, $valor, $hoje, $hoje);

        if ($comando->execute()) {
            removerCarregamentoBotao($id_do_callback, "Sucesso!");
            enviarMensagemTelegram($id_do_chat, "✅ *Lançamento Confirmado!*\n\n💰 R$ " . number_format($valor, 2, ',', '.') . "\n📝 $desc\n📅 " . date('d/m/Y') . "\n\nO registro já aparece no seu Dashboard Web!");
        } else {
            enviarMensagemTelegram($id_do_chat, "❌ Erro ao integrar com o sistema web.");
        }
    }
}

// --- FUNÇÕES ---

function enviarMensagemTelegram($id_chat, $texto, $teclado = null) {
    $url = "https://api.telegram.org/bot" . TELEGRAM_TOKEN . "/sendMessage";
    $dados = ['chat_id' => $id_chat, 'text' => $texto, 'parse_mode' => 'Markdown'];
    if ($teclado) $dados['reply_markup'] = json_encode($teclado);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $dados);
    curl_exec($ch);
    curl_close($ch);
}

function removerCarregamentoBotao($id_callback, $texto) {
    $url = "https://api.telegram.org/bot" . TELEGRAM_TOKEN . "/answerCallbackQuery";
    $dados = ['callback_query_id' => $id_callback, 'text' => $texto];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $dados);
    curl_exec($ch);
    curl_close($ch);
}