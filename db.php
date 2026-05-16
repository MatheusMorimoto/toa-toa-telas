<?php
// Configurações do Supabase (PostgreSQL)
// NOTA: Estas configurações são para conexão direta via PDO.
// Se você está usando a API Node.js, elas não são usadas diretamente pelo PHP.
// Mantenho-as aqui caso você decida voltar à conexão direta.
$host = "db.idxyfkeodaettqbjuiak.supabase.co";
$port = "5432";
$user = "postgres";
$pass = "M@v!21019425"; 
$dbname = "postgres";

// URL Base da sua API Node.js
/** 
 * NOTA: Adicionamos /toa-toa-api-supabase pois o servidor Node.js 
 * responde as rotas de produtos dentro deste prefixo.
 */
$api_base_url = "https://api-toa-a-toa-2.onrender.com/toa-toa-api-supabase";

// IMPORTANTE: O valor desta variável deve ser EXATAMENTE o mesmo que 
// você definiu como CHAVE_MESTRA no painel de Environment Variables do Render.
$api_key = "sua_chave_de_comunicacao_php_node"; 

/**
 * Função genérica para realizar chamadas à API via HTTP (file_get_contents)
 */
function api_request($method, $endpoint, $data = null) {
    global $api_base_url, $api_key;

    // Garante que a URL base termine com barra apenas se necessário
    $url = rtrim($api_base_url, '/');
    
    // Se o endpoint for apenas uma query string (ex: ?id=1), não adicionamos a barra extra
    // Isso evita o erro "Cannot PUT /endpoint/"
    if (strpos($endpoint, '?') === 0) {
        $url .= $endpoint;
    } else {
        $url .= '/' . ltrim($endpoint, '/');
    }

    $options = [
        'http' => [
            'header'  => "Content-Type: application/json\r\n" .
                         "x-api-key: $api_key\r\n",
            'method'  => $method,
            'content' => $data ? json_encode($data) : null,
            'ignore_errors' => true,
            'timeout' => 30 // Aumentado para 30s para suportar o "cold start" do Render
        ]
    ];

    $context  = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        return ['error' => "Erro de Conexão", 'detalhes' => "Não foi possível estabelecer contato com a API em: " . $url];
    }

    $httpCode = 500;
    $headers = function_exists('http_get_last_response_headers') 
        ? http_get_last_response_headers() 
        : ($http_response_header ?? null);

    if ($headers !== null && isset($headers[0])) {
        if (preg_match('{HTTP\/\S*\s(\d{3})}', $headers[0], $match)) {
            $httpCode = (int)$match[1];
        }
    }

    $res = json_decode($response, true);

    // Valida se a resposta é um JSON válido
    if (json_last_error() !== JSON_ERROR_NONE) {
        $trechoResposta = substr(strip_tags($response), 0, 100);
        return ['error' => "Resposta Inválida", 'detalhes' => "A API não retornou JSON. Resposta do servidor: " . $trechoResposta];
    }

    if ($httpCode >= 200 && $httpCode < 300) {
        return $res['dados'] ?? $res;
    }

    // Retorna o erro detalhado vindo do Node.js ou Supabase
    $mensagemErro = $res['mensagem'] ?? ($res['detalhe'] ?? ($res['error'] ?? "Erro interno no servidor Node.js"));
    return ['error' => "Erro API ($httpCode)", 'detalhes' => $mensagemErro];
}

function listarProdutos() {
    return api_request("GET", "/"); 
}

function buscarProduto($busca) {
    return api_request("GET", "/?busca=" . urlencode($busca));
}

function obterProdutoPorId($id) {
    $resultado = api_request("GET", "/?id=" . urlencode($id));
    // Se a API retornar uma lista, pega o primeiro item
    if (is_array($resultado) && isset($resultado[0])) return $resultado[0];
    return $resultado;
}

function editarProduto($id, $dados) {
    return api_request("PUT", "/?id=" . urlencode($id), $dados);
}

function excluirProduto($id) {
    return api_request("DELETE", "/?id=" . urlencode($id));
}
?>