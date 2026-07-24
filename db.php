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

// Inicialização da conexão MySQLi (Necessária para cadastro_cliente.php e finalizar_venda.php)
// O padrão para XAMPP/WAMP é host: localhost, user: root, senha: "", banco: produtos_cadastrados
if (function_exists('mysqli_report')) {
    @mysqli_report(MYSQLI_REPORT_OFF);
}

$conn = null;
if (class_exists('mysqli')) {
    try {
        $conn = @new mysqli(
            env_value('MYSQL_HOST', 'localhost'),
            env_value('MYSQL_USER', 'root'),
            env_value('MYSQL_PASSWORD', ''),
            env_value('MYSQL_DATABASE', 'produtos_cadastrados')
        );
        if ($conn->connect_error) {
            $conn = null;
        }
    } catch (Exception $e) {
        $conn = null;
    }
}

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

function verificarSaudeApi() {
    return toa_api_request("GET", "/health", null, false, false);
}
?>
