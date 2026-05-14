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
$api_base_url = "http://127.0.0.1:3000/toa-toa-api-supabase"; // Use 127.0.0.1 se o Node.js estiver na mesma máquina
$api_key = "sua_chave_de_comunicacao_php_node"; // Sua chave de comunicação PHP <-> Node.js

/**
 * Função genérica para realizar chamadas à API via HTTP (file_get_contents)
 */
function api_request($method, $endpoint, $data = null) {
    global $api_base_url, $api_key;

    // Monta a URL de forma limpa: evita barra no final se o endpoint for "/" ou vazio
    $url = rtrim($api_base_url, '/');
    if ($endpoint !== '/' && !empty($endpoint)) {
        $url .= '/' . ltrim($endpoint, '/');
    }

    $options = [
        'http' => [
            'header'  => "Content-Type: application/json\r\n" .
                         "x-api-key: $api_key\r\n",
            'method'  => $method,
            'content' => $data ? json_encode($data) : null,
            'ignore_errors' => true,
            'timeout' => 15 // Evita que o PHP espere eternamente se a API travar
        ]
    ];

    $context  = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        return ['error' => "Erro de Conexão", 'detalhes' => "Não foi possível estabelecer contato com o serviço na porta 3000."];
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
        return ['error' => "Resposta Inválida", 'detalhes' => "A API não retornou um formato JSON reconhecível."];
    }

    if ($httpCode >= 200 && $httpCode < 300) {
        return $res['dados'] ?? $res;
    }

    // Retorna o erro detalhado vindo do Node.js ou Supabase
    $mensagemErro = $res['mensagem'] ?? ($res['detalhe'] ?? ($res['error'] ?? "Erro interno no servidor Node.js"));
    return ['error' => "Erro API ($httpCode)", 'detalhes' => $mensagemErro];
}

function listarProdutos() {
    return api_request("GET", "/"); // Conforme documentação, GET para produtos é na URL base
}

function buscarProduto($busca) {
    // Ajuste o endpoint conforme sua API Node (ex: /produtos?busca=...)
    // Se a API Node espera a busca na raiz, use "/" e passe a busca como parâmetro
    return api_request("GET", "/?busca=" . urlencode($busca));
}
?>