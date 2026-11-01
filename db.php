<?php
require_once __DIR__ . '/security.php';
require_auth();
require_once __DIR__ . '/services/toa_toa_api.php';

// Versão instalada, mantida pelo manifesto de atualização.
$versionManifest = json_decode((string)@file_get_contents(__DIR__ . '/version.json'), true);
define('SYSTEM_VERSION', is_string($versionManifest['version'] ?? null) ? $versionManifest['version'] : '0.0.0');

// Aumenta o tempo de execução do PHP globalmente para lidar com o "cold start" do Render
set_time_limit(0);
ini_set('max_execution_time', 0);

// Configurações do Supabase (PostgreSQL)
$host = env_value('DB_HOST', '');
$port = env_value('DB_PORT', '5432');
$user = env_value('DB_USER', '');
$pass = env_value('DB_PASSWORD', '');
$dbname = env_value('DB_NAME', '');

// A aplicação usa a API remota nas funções abaixo. Manter uma tentativa de
// conexão MySQL local durante toda abertura atrasava a primeira tela quando
// nenhum servidor MySQL estava ativo, embora essa conexão não fosse utilizada.
$conn = null;

function listarProdutos() {
    return toa_api_request("GET", "/toa-toa-api-supabase");
}

function buscarProduto($busca) {
    return toa_api_request("GET", "/toa-toa-api-supabase?busca=" . rawurlencode($busca));
}

function obterProdutoPorId($id) {
    return toa_api_request("GET", "/toa-toa-api-supabase/" . rawurlencode((string)$id));
}

function salvarProduto($dados) {
    return toa_api_request("POST", "/toa-toa-api-supabase", $dados, true);
}

function editarProduto($id, $dados) {
    return toa_api_request("PUT", "/toa-toa-api-supabase/" . rawurlencode((string)$id), $dados, true);
}

function excluirProduto($id) {
    return toa_api_request("DELETE", "/toa-toa-api-supabase/" . rawurlencode((string)$id));
}

// FUNÇÕES PARA CLIENTES (Novas)
function listarClientes() {
    return toa_api_request("GET", "/toa-toa-clientes");
}

function pesquisarClientes($termo) {
    return toa_api_request("GET", "/toa-toa-clientes?busca=" . rawurlencode($termo));
}

function buscarCliente($id) {
    return toa_api_request("GET", "/toa-toa-clientes/" . rawurlencode((string)$id));
}

function salvarCliente($dados) {
    return toa_api_request("POST", "/toa-toa-clientes", $dados);
}

function editarCliente($id, $dados) {
    return toa_api_request("PUT", "/toa-toa-clientes/" . rawurlencode((string)$id), $dados);
}

function excluirCliente($id) {
    return toa_api_request("DELETE", "/toa-toa-clientes/" . rawurlencode((string)$id));
}

// FUNÇÃO PARA REGISTRO DE VENDAS E ALUGUÉIS
function registrarOperacaoCompleta($dados) {
    return toa_api_request("POST", "/toa-toa-vendas", $dados);
}

function vendasHistoricoArquivo() {
    return __DIR__ . '/storage/vendas_realizadas.json';
}

function listarVendasLocais() {
    $arquivo = vendasHistoricoArquivo();
    if (!is_file($arquivo)) {
        return [];
    }
    $dados = json_decode((string)@file_get_contents($arquivo), true);
    return is_array($dados) ? $dados : [];
}

function registrarVendaLocal($dados) {
    $arquivo = vendasHistoricoArquivo();
    $diretorio = dirname($arquivo);
    if (!is_dir($diretorio) && !mkdir($diretorio, 0775, true) && !is_dir($diretorio)) {
        throw new RuntimeException('Não foi possível criar o histórico local de vendas.');
    }

    $handle = fopen($arquivo, 'c+');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        throw new RuntimeException('Não foi possível abrir o histórico local de vendas.');
    }
    try {
        $conteudo = stream_get_contents($handle);
        $vendas = $conteudo !== false && trim($conteudo) !== '' ? json_decode($conteudo, true) : [];
        if (!is_array($vendas)) {
            $vendas = [];
        }
        $dados['registro_local_id'] = $dados['registro_local_id'] ?? bin2hex(random_bytes(8));
        $dados['realizada_em'] = $dados['realizada_em'] ?? date(DATE_ATOM);
        $vendas[] = $dados;
        rewind($handle);
        ftruncate($handle, 0);
        if (fwrite($handle, json_encode($vendas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) === false) {
            throw new RuntimeException('Não foi possível salvar o histórico local de vendas.');
        }
        fflush($handle);
        return $dados;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function verificarSaudeApi() {
    return toa_api_request("GET", "/health", null, false, false);
}
?>
