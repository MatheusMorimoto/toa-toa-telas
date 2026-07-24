<?php

putenv('APP_AUTH_ENABLED=false');
putenv('TOA_TOA_API_KEY=teste-catalogo');
putenv('PUBLIC_APP_URL=https://catalogo.exemplo.com/app');
putenv('WHATSAPP_BUSINESS_NUMBER=+55 (65) 99999-9999');
require_once dirname(__DIR__) . '/services/catalog_service.php';

$passed = 0;
$failed = 0;
$tempRoot = sys_get_temp_dir() . '/toa-catalog-test-' . bin2hex(random_bytes(5));
mkdir($tempRoot, 0777, true);
$GLOBALS['catalog_cache_path'] = $tempRoot . '/products.json';

function catalog_check(bool $condition, string $message): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "[OK] $message\n";
    } else {
        $failed++;
        echo "[FALHA] $message\n";
    }
}

try {
    $ids = catalog_normalize_ids('7,8,7,<script>,abc-1,,9');
    catalog_check($ids === ['7', '8', 'abc-1', '9'], 'normaliza e limita IDs públicos');
    catalog_check(
        catalog_public_url(['7', '8']) === 'https://catalogo.exemplo.com/app/catalogo.php?ids=7%2C8',
        'gera URL HTTPS pública e estável'
    );
    catalog_check(catalog_whatsapp_number() === '5565999999999', 'normaliza número do WhatsApp');
    catalog_check(
        str_starts_with(catalog_whatsapp_url('Olá catálogo'), 'https://wa.me/5565999999999?text='),
        'gera link oficial do WhatsApp'
    );

    $products = [
        ['id' => 7, 'nome' => 'Vestido A'],
        ['id' => 8, 'nome' => 'Vestido B'],
        ['id' => 9, 'nome' => 'Vestido C'],
    ];
    catalog_check(
        array_column(catalog_filter_products($products, ['8', '9']), 'id') === [8, 9],
        'filtra somente produtos selecionados'
    );

    $GLOBALS['toa_api_transport'] = fn() => $products;
    $live = catalog_load_products();
    catalog_check(!$live['cached'] && count($live['products']) === 3, 'carrega produtos da API');
    catalog_check(is_file($GLOBALS['catalog_cache_path']), 'persiste cache para indisponibilidade temporária');

    $GLOBALS['toa_api_transport'] = fn() => [
        'error' => 'Erro de conexão',
        'detalhes' => 'API temporariamente indisponível',
        'status' => 0,
    ];
    $fallback = catalog_load_products();
    catalog_check($fallback['cached'] && count($fallback['products']) === 3, 'usa cache quando a API fica indisponível');

    putenv('PUBLIC_APP_URL=');
    $_SERVER['HTTP_HOST'] = 'localhost:8000';
    $_SERVER['HTTPS'] = 'off';
    catalog_check(catalog_public_base_url() === '', 'não compartilha endereço localhost');
} finally {
    unset($GLOBALS['toa_api_transport'], $GLOBALS['catalog_cache_path']);
    if (is_file($tempRoot . '/products.json')) {
        unlink($tempRoot . '/products.json');
    }
    if (is_dir($tempRoot)) {
        rmdir($tempRoot);
    }
}

echo "\n$passed testes de catálogo passaram; $failed falharam.\n";
exit($failed === 0 ? 0 : 1);

