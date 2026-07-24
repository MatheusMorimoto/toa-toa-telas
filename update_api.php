<?php

require_once __DIR__ . '/security.php';
require_auth();
require_once __DIR__ . '/services/update_service.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

function update_json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$service = new UpdateService([
    'manifest_url' => env_value(
        'UPDATER_MANIFEST_URL',
        'https://raw.githubusercontent.com/MatheusMorimoto/toa-toa-telas/main/version.json'
    ),
    'app_runtime_version' => env_value('APP_RUNTIME_VERSION', '1.0.0'),
]);
$action = $_GET['action'] ?? 'check';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'check') {
        update_json_response($service->check());
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'install') {
        validate_csrf();
        update_json_response($service->install());
    }
    update_json_response(['ok' => false, 'message' => 'Operação não permitida.'], 405);
} catch (UpdateException $error) {
    update_json_response(['ok' => false, 'status' => 'error', 'message' => $error->getMessage()], 422);
} catch (Throwable $error) {
    update_json_response([
        'ok' => false,
        'status' => 'error',
        'message' => 'Não foi possível concluir a atualização. A versão atual continua disponível.',
    ], 500);
}

