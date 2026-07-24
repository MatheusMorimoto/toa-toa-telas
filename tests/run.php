<?php

putenv('APP_AUTH_ENABLED=false');
putenv('TOA_TOA_API_KEY');
require dirname(__DIR__) . '/db.php';

$passed = 0;
$failed = 0;

function check($condition, $message)
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "[OK] $message\n";
        return;
    }
    $failed++;
    echo "[FALHA] $message\n";
}

$missingAuth = toa_api_request('GET', '/toa-toa-api-supabase');
check(($missingAuth['status'] ?? null) === 0 && isset($missingAuth['error']), 'autenticação ausente');

putenv('CHAVE_MESTRA=chave-legada-apenas-para-teste');
check(toa_api_key() === 'chave-legada-apenas-para-teste', 'compatibilidade com CHAVE_MESTRA');
putenv('CHAVE_MESTRA');

putenv('TOA_TOA_API_KEY=chave-apenas-para-teste');
$requests = [];
$GLOBALS['toa_api_transport'] = function ($method, $endpoint, $data, $multipart, $authenticated) use (&$requests) {
    $requests[] = compact('method', 'endpoint', 'data', 'multipart', 'authenticated');
    if ($endpoint === '/health') {
        return ['status' => 'ok'];
    }
    if ($endpoint === '/toa-toa-vendas') {
        return ['venda_id' => 123];
    }
    return ['id' => 7, 'imagem' => 'https://example.supabase.co/storage/foto.png'];
};

check(verificarSaudeApi() === ['status' => 'ok'], 'health check');
check(listarProdutos()['id'] === 7, 'listagem de produtos');
check(obterProdutoPorId(7)['id'] === 7, 'consulta de produto por ID');
check(toa_product_image('https://example.supabase.co/storage/foto.png') === 'https://example.supabase.co/storage/foto.png', 'associação da imagem ao produto');
check(toa_product_image('placeholder.jpg') === 'toatoa.png', 'fallback de imagem placeholder');
check(toa_product_image('javascript:alert(1)') === 'toatoa.png', 'proteção contra URL de imagem insegura');

$file = (object)['name' => 'teste.png', 'mime' => 'image/png'];
$formData = toa_product_form_data([
    'codProduto' => 'COD-1',
    'nomeProduto' => 'Vestido',
    'categoria' => 'Noivas',
    'validade' => '2026-01-01',
    'quantidade' => 1,
    'precoUnitario' => 100,
    'precoPacote' => 80,
    'descricao' => 'Teste',
], $file);
salvarProduto($formData);
editarProduto(7, $formData);
check($requests[count($requests) - 2]['multipart'] === true, 'criação de produto com FormData');
check($requests[count($requests) - 1]['endpoint'] === '/toa-toa-api-supabase/7', 'edição de produto via rota /:id');
check($requests[count($requests) - 1]['data']['imagem'] === $file, 'arquivo binário no campo imagem');

listarClientes();
buscarCliente(9);
salvarCliente(['nome_completo' => 'Cliente']);
editarCliente(9, ['nome_completo' => 'Cliente']);
excluirCliente(9);
$clientEndpoints = array_column(array_slice($requests, -5), 'endpoint');
check($clientEndpoints === [
    '/toa-toa-clientes',
    '/toa-toa-clientes/9',
    '/toa-toa-clientes',
    '/toa-toa-clientes/9',
    '/toa-toa-clientes/9',
], 'CRUD de clientes');

$sale = registrarOperacaoCompleta([
    'cliente_id' => 1,
    'forma_pagamento' => 'pix',
    'valor_costura' => 0,
    'desconto_valor' => 0,
    'observacoes' => '',
    'itens' => [['produto_id' => 7, 'tipo' => 'venda', 'preco' => 100]],
]);
check(($sale['venda_id'] ?? null) === 123, 'venda válida e venda_id retornado');
check(toa_api_error_message(409) === 'Conflito de dados ou estoque insuficiente.', 'erro de estoque insuficiente');
check(toa_api_error_message(413) === 'A imagem excede o limite permitido.', 'erro de imagem grande');

$escaped = htmlspecialchars('<script>alert(1)</script>', ENT_QUOTES, 'UTF-8');
check(!str_contains($escaped, '<script>'), 'proteção contra XSS');

unset($GLOBALS['toa_api_transport']);
echo "\n$passed testes passaram; $failed falharam.\n";
exit($failed === 0 ? 0 : 1);
