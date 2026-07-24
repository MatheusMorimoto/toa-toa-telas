<?php

require_once __DIR__ . '/../security.php';
require_once __DIR__ . '/toa_toa_api.php';

function catalog_normalize_ids(string $rawIds): array
{
    $ids = [];
    foreach (explode(',', $rawIds) as $rawId) {
        $id = trim($rawId);
        if ($id === '' || !preg_match('/\A[A-Za-z0-9_-]{1,64}\z/', $id)) {
            continue;
        }
        $ids[$id] = $id;
        if (count($ids) >= 100) {
            break;
        }
    }
    return array_values($ids);
}

function catalog_public_base_url(): string
{
    $configured = rtrim(trim((string)env_value('PUBLIC_APP_URL', '')), '/');
    if ($configured !== '') {
        $parts = parse_url($configured);
        if (($parts['scheme'] ?? '') === 'https' && isset($parts['host'])) {
            return $configured;
        }
        return '';
    }

    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $isLocal = $host === '' ||
        str_starts_with($host, 'localhost') ||
        str_starts_with($host, '127.') ||
        str_starts_with($host, '[::1]') ||
        preg_match('/\A(?:10\.|192\.168\.|172\.(?:1[6-9]|2\d|3[01])\.)/', $host);
    if ($isLocal) {
        return '';
    }

    $forwardedProto = strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
    $isHttps = $forwardedProto === 'https' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    if (!$isHttps) {
        return '';
    }
    $scriptDirectory = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/')));
    return 'https://' . $host . ($scriptDirectory === '/' ? '' : rtrim($scriptDirectory, '/'));
}

function catalog_public_url(array $ids): string
{
    $baseUrl = catalog_public_base_url();
    if ($baseUrl === '' || $ids === []) {
        return '';
    }
    return $baseUrl . '/catalogo.php?' . http_build_query(['ids' => implode(',', $ids)], '', '&', PHP_QUERY_RFC3986);
}

function catalog_whatsapp_number(): string
{
    return preg_replace('/\D+/', '', (string)env_value('WHATSAPP_BUSINESS_NUMBER', '')) ?? '';
}

function catalog_whatsapp_url(string $message, ?string $number = null): string
{
    $number = $number ?? catalog_whatsapp_number();
    return 'https://wa.me/' . $number . '?text=' . rawurlencode($message);
}

function catalog_cache_path(): string
{
    return isset($GLOBALS['catalog_cache_path']) && is_string($GLOBALS['catalog_cache_path'])
        ? $GLOBALS['catalog_cache_path']
        : dirname(__DIR__) . '/storage/catalog/products.json';
}

function catalog_write_cache(array $products): void
{
    $path = catalog_cache_path();
    $directory = dirname($path);
    if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
        return;
    }
    $temporary = $path . '.tmp-' . bin2hex(random_bytes(4));
    $payload = json_encode([
        'cached_at' => gmdate(DATE_ATOM),
        'products' => array_values($products),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (is_string($payload) && @file_put_contents($temporary, $payload, LOCK_EX) !== false) {
        if (!@rename($temporary, $path)) {
            @unlink($temporary);
        }
    }
}

function catalog_read_cache(): array
{
    $path = catalog_cache_path();
    if (!is_file($path) || !is_readable($path)) {
        return [];
    }
    try {
        $payload = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        return is_array($payload['products'] ?? null) ? $payload : [];
    } catch (Throwable) {
        return [];
    }
}

function catalog_load_products(): array
{
    $result = toa_api_request('GET', '/toa-toa-api-supabase');
    if (is_array($result) && !isset($result['error'])) {
        catalog_write_cache($result);
        return ['products' => array_values($result), 'cached' => false, 'cached_at' => null, 'error' => null];
    }

    $cache = catalog_read_cache();
    if (is_array($cache['products'] ?? null) && $cache['products'] !== []) {
        return [
            'products' => array_values($cache['products']),
            'cached' => true,
            'cached_at' => $cache['cached_at'] ?? null,
            'error' => $result['detalhes'] ?? 'A conexão com o estoque está temporariamente indisponível.',
        ];
    }
    return [
        'products' => [],
        'cached' => false,
        'cached_at' => null,
        'error' => $result['detalhes'] ?? 'Não foi possível carregar o catálogo.',
    ];
}

function catalog_filter_products(array $products, array $ids): array
{
    if ($ids === []) {
        return [];
    }
    $wanted = array_fill_keys(array_map('strval', $ids), true);
    return array_values(array_filter($products, static function ($product) use ($wanted) {
        return is_array($product) && isset($wanted[(string)($product['id'] ?? '')]);
    }));
}
