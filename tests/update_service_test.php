<?php

putenv('APP_AUTH_ENABLED=false');
require_once dirname(__DIR__) . '/security.php';
require_once dirname(__DIR__) . '/services/update_service.php';

$passed = 0;
$failed = 0;
$tempRoot = sys_get_temp_dir() . '/toa-update-test-' . bin2hex(random_bytes(5));
mkdir($tempRoot, 0777, true);

function update_check(bool $condition, string $message): void
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

function test_manifest(string $version, ?string $sha = null): array
{
    return [
        'version' => $version,
        'download_url' => 'https://github.com/MatheusMorimoto/toa-toa-telas/raw/main/releases/test.zip',
        'sha256' => $sha ?? str_repeat('a', 64),
        'minimum_app_version' => '1.0.0',
        'published_at' => '2026-07-24T12:00:00Z',
    ];
}

function remove_test_tree(string $root): void
{
    if (!is_dir($root)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($root);
}

try {
    $appRoot = $tempRoot . '/app';
    mkdir($appRoot, 0777, true);
    file_put_contents($appRoot . '/version.json', json_encode(test_manifest('1.0.0')));

    $newManifest = test_manifest('1.1.0');
    $service = new UpdateService([
        'app_root' => $appRoot,
        'work_root' => $tempRoot . '/work-new',
        'http_fetcher' => fn() => json_encode($newManifest),
    ]);
    update_check($service->check(true)['status'] === 'available', 'detecta versão nova');

    file_put_contents($appRoot . '/version.json', json_encode(test_manifest('1.1.0')));
    update_check($service->check(true)['status'] === 'current', 'detecta aplicativo já atualizado');

    $offlineService = new UpdateService([
        'app_root' => $appRoot,
        'work_root' => $tempRoot . '/work-offline',
        'http_fetcher' => fn() => throw new RuntimeException('sem internet'),
    ]);
    update_check($offlineService->check(true)['status'] === 'offline', 'mantém funcionamento sem internet');

    $invalidUrl = test_manifest('1.2.0');
    $invalidUrl['download_url'] = 'https://example.com/update.zip';
    try {
        $service->validateManifest($invalidUrl);
        update_check(false, 'bloqueia URL não autorizada');
    } catch (UpdateException) {
        update_check(true, 'bloqueia URL não autorizada');
    }

    $otherRepository = test_manifest('1.2.0');
    $otherRepository['download_url'] = 'https://github.com/outra-conta/outro-projeto/raw/main/update.zip';
    try {
        $service->validateManifest($otherRepository);
        update_check(false, 'bloqueia pacote de outro repositório');
    } catch (UpdateException) {
        update_check(true, 'bloqueia pacote de outro repositório');
    }

    file_put_contents($appRoot . '/version.json', json_encode(test_manifest('1.0.0')));
    $badHashService = new UpdateService([
        'app_root' => $appRoot,
        'work_root' => $tempRoot . '/work-hash',
        'http_fetcher' => function (string $url) use ($newManifest) {
            return str_ends_with($url, 'version.json') ? json_encode($newManifest) : 'pacote adulterado';
        },
    ]);
    try {
        $badHashService->install($newManifest);
        update_check(false, 'rejeita SHA-256 inválido');
    } catch (UpdateException $error) {
        update_check(str_contains($error->getMessage(), 'SHA-256'), 'rejeita SHA-256 inválido');
    }

    $corruptBytes = 'não é um arquivo zip';
    $corruptManifest = test_manifest('1.2.0', hash('sha256', $corruptBytes));
    $corruptService = new UpdateService([
        'app_root' => $appRoot,
        'work_root' => $tempRoot . '/work-corrupt',
        'http_fetcher' => fn(string $url) => str_ends_with($url, 'version.json')
            ? json_encode($corruptManifest)
            : $corruptBytes,
    ]);
    try {
        $corruptService->install($corruptManifest);
        update_check(false, 'rejeita pacote corrompido');
    } catch (UpdateException $error) {
        update_check(
            str_contains($error->getMessage(), 'ZipArchive') || str_contains($error->getMessage(), 'corrompido'),
            'rejeita pacote corrompido'
        );
    }

    update_check($service->isProtectedPath('.env'), 'preserva credenciais');
    update_check($service->isProtectedPath('imagens/cliente.jpg'), 'preserva imagens do usuário');
    update_check($service->isProtectedPath('dados.sqlite'), 'preserva banco de dados local');
    update_check($service->isProtectedPath('settings.json'), 'preserva preferências locais');

    $rollbackApp = $tempRoot . '/rollback-app';
    $rollbackBackup = $tempRoot . '/rollback-backup';
    mkdir($rollbackApp, 0777, true);
    mkdir($rollbackBackup, 0777, true);
    file_put_contents($rollbackApp . '/tela.php', 'versão anterior');
    file_put_contents($tempRoot . '/nova-tela.php', 'versão nova');
    $rollbackService = new UpdateService(['app_root' => $rollbackApp, 'work_root' => $tempRoot . '/work-rollback']);
    $installMethod = new ReflectionMethod(UpdateService::class, 'backupAndInstall');
    $rollbackMethod = new ReflectionMethod(UpdateService::class, 'rollback');
    $installMethod->invoke($rollbackService, 'tela.php', $tempRoot . '/nova-tela.php', $rollbackBackup);
    update_check(file_get_contents($rollbackApp . '/tela.php') === 'versão nova', 'instala arquivo preparado');
    $rollbackMethod->invoke($rollbackService, ['tela.php'], $rollbackBackup);
    update_check(file_get_contents($rollbackApp . '/tela.php') === 'versão anterior', 'restaura versão após falha');

    if (class_exists('ZipArchive')) {
        $integrationApp = $tempRoot . '/integration-app';
        mkdir($integrationApp . '/imagens', 0777, true);
        file_put_contents($integrationApp . '/version.json', json_encode(test_manifest('1.0.0')));
        file_put_contents($integrationApp . '/tela.php', 'tela anterior');
        file_put_contents($integrationApp . '/.env', 'SEGREDO=preservado');
        file_put_contents($integrationApp . '/imagens/cliente.jpg', 'foto local');

        $zipPath = $tempRoot . '/valid-package.zip';
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('release/tela.php', 'tela atualizada');
        $zip->addFromString('release/z-falha.php', 'arquivo novo');
        $zip->addFromString('release/.env', 'SEGREDO=alterado');
        $zip->addFromString('release/imagens/cliente.jpg', 'foto remota');
        $zip->close();
        $zipBytes = (string)file_get_contents($zipPath);
        $integrationManifest = test_manifest('1.3.0', hash('sha256', $zipBytes));

        $failingService = new UpdateService([
            'app_root' => $integrationApp,
            'work_root' => $tempRoot . '/work-install-failure',
            'http_fetcher' => fn(string $url) => str_ends_with($url, 'version.json')
                ? json_encode($integrationManifest)
                : $zipBytes,
            'before_install_file' => function (string $relativePath): void {
                if ($relativePath === 'z-falha.php') {
                    throw new RuntimeException('falha simulada');
                }
            },
        ]);
        try {
            $failingService->install($integrationManifest);
            update_check(false, 'faz rollback após falha durante instalação');
        } catch (UpdateException) {
            update_check(
                file_get_contents($integrationApp . '/tela.php') === 'tela anterior',
                'faz rollback após falha durante instalação'
            );
        }

        $successfulService = new UpdateService([
            'app_root' => $integrationApp,
            'work_root' => $tempRoot . '/work-install-success',
            'http_fetcher' => fn(string $url) => str_ends_with($url, 'version.json')
                ? json_encode($integrationManifest)
                : $zipBytes,
        ]);
        $installResult = $successfulService->install($integrationManifest);
        update_check($installResult['status'] === 'updated', 'instala pacote íntegro');
        update_check(file_get_contents($integrationApp . '/tela.php') === 'tela atualizada', 'aplica novas telas');
        update_check(file_get_contents($integrationApp . '/.env') === 'SEGREDO=preservado', 'não substitui .env');
        update_check(file_get_contents($integrationApp . '/imagens/cliente.jpg') === 'foto local', 'não substitui imagens locais');
        update_check($successfulService->installedVersion() === '1.3.0', 'registra versão instalada somente após sucesso');
    } else {
        update_check(false, 'extensão ZipArchive disponível para testes de integração');
    }
} finally {
    remove_test_tree($tempRoot);
}

echo "\n$passed testes de atualização passaram; $failed falharam.\n";
exit($failed === 0 ? 0 : 1);
