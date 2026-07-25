<?php

function toa_api_base_url()
{
    return rtrim((string)env_value('API_BASE_URL', 'https://api-toa-a-toa-2.onrender.com'), '/');
}

function toa_api_key()
{
    $key = trim((string)env_value('TOA_TOA_API_KEY', ''));
    if ($key !== '') {
        return $key;
    }

    // Compatibilidade com o nome usado no ambiente do backend no Render.
    return trim((string)env_value('CHAVE_MESTRA', ''));
}

function toa_api_error_message($status)
{
    $messages = [
        400 => 'Requisição inválida.',
        401 => 'Chave da API incorreta ou ausente.',
        404 => 'Registro não encontrado.',
        409 => 'Conflito de dados ou estoque insuficiente.',
        413 => 'A imagem excede o limite permitido.',
        422 => 'Os dados ou a imagem enviada são inválidos.',
        429 => 'Muitas solicitações. Aguarde e tente novamente.',
        500 => 'Falha interna na API.',
    ];
    return $messages[$status] ?? 'Não foi possível concluir a operação.';
}

function toa_api_connection_error_message($curlError)
{
    $error = trim((string)$curlError);
    $normalized = strtolower($error);

    if ($error === '') {
        return 'Não foi possível estabelecer contato com a API. Verifique se allow_url_fopen ou a extensão cURL está habilitada no PHP.';
    }
    if (str_contains($normalized, 'timed out') || str_contains($normalized, 'timeout')) {
        return 'A API demorou mais que o esperado. Verifique a conexão com a internet e o firewall.';
    }
    if (
        str_contains($normalized, 'certificate') ||
        str_contains($normalized, 'ssl') ||
        str_contains($normalized, 'unable to get local issuer')
    ) {
        return 'Falha ao validar o certificado HTTPS da API. Configure curl.cainfo e openssl.cafile no php.ini do PHP Desktop. Detalhe: ' . $error;
    }
    if (
        str_contains($normalized, 'resolve host') ||
        str_contains($normalized, 'could not resolve') ||
        str_contains($normalized, 'getaddrinfo')
    ) {
        return 'O PHP Desktop não conseguiu resolver o endereço da API. Verifique DNS, internet e firewall. Detalhe: ' . $error;
    }
    if (str_contains($normalized, 'connect')) {
        return 'O PHP Desktop não conseguiu abrir conexão com a API. Verifique internet, proxy e firewall. Detalhe: ' . $error;
    }

    return 'Não foi possível estabelecer contato com a API. Detalhe: ' . $error;
}

function toa_api_request($method, $endpoint, $data = null, $multipart = false, $authenticated = true)
{
    $apiKey = toa_api_key();
    if ($authenticated && $apiKey === '') {
        return [
            'error' => 'Configuração ausente',
            'detalhes' => 'A chave de comunicação com a API não está configurada.',
            'status' => 0,
        ];
    }
    if (isset($GLOBALS['toa_api_transport']) && is_callable($GLOBALS['toa_api_transport'])) {
        return $GLOBALS['toa_api_transport']($method, $endpoint, $data, $multipart, $authenticated);
    }
    if (!function_exists('curl_init') && $multipart) {
        return [
            'error' => 'Extensão ausente',
            'detalhes' => 'A extensão cURL precisa estar habilitada no PHP.',
            'status' => 0,
        ];
    }

    $url = toa_api_base_url() . '/' . ltrim($endpoint, '/');
    $headers = ['Accept: application/json'];
    if ($authenticated) {
        $headers[] = 'x-api-key: ' . $apiKey;
    }

    if (!function_exists('curl_init')) {
        $content = null;
        if ($data !== null) {
            $headers[] = 'Content-Type: application/json';
            $content = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }
        $context = stream_context_create([
            'http' => [
                'method' => strtoupper($method),
                'header' => implode("\r\n", $headers),
                'content' => $content,
                'ignore_errors' => true,
                'timeout' => 90,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
        $responseHeaders = $http_response_header ?? [];
        $status = isset($responseHeaders[0]) && preg_match('{HTTP/\S+\s+(\d{3})}', $responseHeaders[0], $match)
            ? (int)$match[1]
            : 0;
        $curlError = '';
    } else {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 90,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        if ($data !== null) {
            if ($multipart) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            } else {
                $headers[] = 'Content-Type: application/json';
                $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
            }
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
    }

    if ($response === false) {
        error_log('Falha de rede ao acessar a API TOA-TOA' . ($curlError !== '' ? ': ' . $curlError : '.'));
        return [
            'error' => 'Erro de conexão',
            'detalhes' => toa_api_connection_error_message($curlError),
            'status' => 0,
        ];
    }

    try {
        $decoded = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        error_log('A API TOA-TOA retornou uma resposta que não é JSON.');
        return [
            'error' => 'Resposta inválida',
            'detalhes' => 'A API não retornou uma resposta JSON válida.',
            'status' => $status,
        ];
    }

    if ($status >= 200 && $status < 300) {
        return is_array($decoded) && array_key_exists('dados', $decoded) ? $decoded['dados'] : $decoded;
    }

    $apiMessage = is_array($decoded)
        ? ($decoded['mensagem'] ?? $decoded['detalhe'] ?? $decoded['error'] ?? null)
        : null;
    return [
        'error' => 'Erro API (' . $status . ')',
        'detalhes' => is_string($apiMessage) && $apiMessage !== '' ? $apiMessage : toa_api_error_message($status),
        'status' => $status,
    ];
}

function toa_product_image($value)
{
    if (!is_string($value) || $value === '' || $value === 'placeholder.jpg') {
        return 'toatoa.png';
    }
    return filter_var($value, FILTER_VALIDATE_URL) ? $value : 'toatoa.png';
}

function toa_validate_product_upload($file)
{
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['file' => null];
    }
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['error' => 'Não foi possível receber a imagem enviada.'];
    }
    if (($file['size'] ?? 0) > 10 * 1024 * 1024) {
        return ['error' => 'A imagem excede o limite de 10 MB.'];
    }

    if (!class_exists('finfo')) {
        return ['error' => 'A extensão fileinfo precisa estar habilitada no PHP para validar imagens.'];
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mime, $allowed, true)) {
        return ['error' => 'Apenas imagens JPEG, PNG ou WebP são permitidas.'];
    }
    if (!class_exists('CURLFile')) {
        return ['error' => 'A extensão cURL precisa estar habilitada no PHP para enviar imagens.'];
    }
    return [
        'file' => new CURLFile($file['tmp_name'], $mime, basename((string)$file['name'])),
    ];
}

function toa_product_form_data($source, $file = null)
{
    $fields = [
        'codProduto' => trim((string)($source['codProduto'] ?? '')),
        'nomeProduto' => trim((string)($source['nomeProduto'] ?? '')),
        'categoria' => trim((string)($source['categoria'] ?? '')),
        'validade' => (string)($source['validade'] ?? ''),
        'quantidade' => (string)(int)($source['quantidade'] ?? 0),
        'precoUnitario' => (string)(float)($source['precoUnitario'] ?? 0),
        'precoPacote' => (string)(float)($source['precoPacote'] ?? 0),
        'descricao' => trim((string)($source['descricao'] ?? '')),
    ];
    if ($file !== null) {
        $fields['imagem'] = $file;
    }
    return $fields;
}
