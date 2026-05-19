<?php
// Aumenta o tempo de execução do PHP globalmente para lidar com o "cold start" do Render
set_time_limit(0);
ini_set('max_execution_time', 0);

// Configurações do Supabase (PostgreSQL)
$host = "db.idxyfkeodaettqbjuiak.supabase.co";
$port = "5432";
$user = "postgres";
$pass = "M@v!21019425"; 
$dbname = "postgres";

// Inicialização da conexão MySQLi (Necessária para cadastro_cliente.php e finalizar_venda.php)
// O padrão para XAMPP/WAMP é host: localhost, user: root, senha: "", banco: produtos_cadastrados
@mysqli_report(MYSQLI_REPORT_OFF);
try {
    $conn = @new mysqli("localhost", "root", "", "produtos_cadastrados");
    if ($conn->connect_error) { 
        // Note: mysqli não conectará ao host do Supabase (PostgreSQL), mas mantemos para evitar erro de variável nula
        $conn = @new mysqli($host, $user, $pass, $dbname); 
    }
} catch (Exception $e) {
    $conn = null;
}

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
 * Função para fazer upload de imagem diretamente para o Bucket do Supabase
 */
function uploadImagemSupabase($file_tmp_path, $file_name) {
    $project_id = "idxyfkeodaettqbjuiak";
    $bucket_name = "toa-toa-moda-festa";
    
    /** 
     * IMPORTANTE: Você DEVE substituir o valor abaixo pela sua 'service_role key' 
     * ou 'anon key' (caso as políticas de storage permitam) encontrada no painel do Supabase.
     */
    $supabase_key = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImlkeHlma2VvZGFldHRxYmp1aWFrIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc3ODYyNTE5MCwiZXhwIjoyMDk0MjAxMTkwfQ.NHpC4HgxdbxUMYUJSu10rPKdss4jHZZa3IK_ojjFBIM"; 

    $url = "https://{$project_id}.supabase.co/storage/v1/object/{$bucket_name}/{$file_name}";

    $file_data = file_get_contents($file_tmp_path);
    $file_size = strlen($file_data);

    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime_type = $finfo->file($file_tmp_path);
    } else {
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $mimes = [
            'jpg'  => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'png'  => 'image/png',  'webp' => 'image/webp'
        ];
        $mime_type = $mimes[$ext] ?? 'application/octet-stream';
    }

    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $file_data);
        // Ignora verificação SSL para funcionar em XAMPP/Windows local
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $supabase_key",
            "apikey: $supabase_key",
            "Content-Type: $mime_type",
            "Content-Length: $file_size",
            "Cache-Control: max-age=3600"
        ]);

        curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    } else {
        $options = [
            'http' => [
                'header'  => "Authorization: Bearer $supabase_key\r\n" .
                             "apikey: $supabase_key\r\n" .
                             "Content-Type: $mime_type\r\n" .
                             "Content-Length: $file_size\r\n" .
                             "Cache-Control: max-age=3600\r\n",
                'method'  => 'POST',
                'content' => $file_data,
                'ignore_errors' => true
            ],
            // Fallback para SSL no file_get_contents
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ];
        $context  = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);
        
        $http_code = 0;
        $headers = function_exists('http_get_last_response_headers') ? http_get_last_response_headers() : ($http_response_header ?? null);
        
        if (isset($headers[0])) {
            preg_match('{HTTP\/\S*\s(\d{3})}', $headers[0], $match);
            $http_code = (int)$match[1];
        }
    }

    return ($http_code == 200 || $http_code == 201);
}

/**
 * Função genérica para realizar chamadas à API via HTTP (file_get_contents)
 */
function api_request($method, $endpoint, $data = null) {
    global $api_base_url, $api_key;
    
    if (strpos($endpoint, '/toa-toa-clientes') === 0) {
        // Se for endpoint de clientes, a URL já é completa após o domínio
        $url = "https://api-toa-a-toa-2.onrender.com" . $endpoint;
    } else {
        // Lógica para produtos (mantendo o que já funcionava)
        $url = rtrim($api_base_url, '/');
        if (strpos($endpoint, '?') === 0) {
            $url .= $endpoint;
        } else {
            $url .= '/' . ltrim($endpoint, '/');
        }
    }

    $options = [
        'http' => [
            'header'  => "Content-Type: application/json\r\n" .
                         "x-api-key: $api_key\r\n",
            'method'  => $method,
            'content' => $data ? json_encode($data) : null,
            'ignore_errors' => true,
            'timeout' => 90 // Aumentado para 90 segundos para o "cold start" do Render
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

// FUNÇÕES PARA CLIENTES (Novas)
function listarClientes() {
    return api_request("GET", "/toa-toa-clientes");
}

function buscarCliente($id) {
    return api_request("GET", "/toa-toa-clientes/" . $id);
}

function salvarCliente($dados) {
    return api_request("POST", "/toa-toa-clientes", $dados);
}

function editarCliente($id, $dados) {
    return api_request("PUT", "/toa-toa-clientes/" . $id, $dados);
}
?>