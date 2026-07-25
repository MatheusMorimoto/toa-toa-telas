<?php

final class UpdateException extends RuntimeException
{
}

final class UpdateService
{
    private string $appRoot;
    private string $workRoot;
    private string $manifestUrl;
    private string $minimumAppVersion;
    private $httpFetcher;
    private $beforeInstallFile;

    private const ALLOWED_HOSTS = [
        'github.com',
        'raw.githubusercontent.com',
        'objects.githubusercontent.com',
    ];

    private const BLOCKED_TOP_LEVEL = [
        '.git',
        '.github',
        '.venv',
        'venv',
        'storage',
        'imagens',
        'banco de dados',
        'backups',
        'temp_update',
        'update_package.zip',
    ];

    private const BLOCKED_FILES = [
        '.env',
        'settings.json',
        'config.py',
    ];

    public function __construct(array $options = [])
    {
        $this->appRoot = rtrim($options['app_root'] ?? dirname(__DIR__), '/\\');
        $this->workRoot = rtrim($options['work_root'] ?? $this->appRoot . '/storage/update', '/\\');
        $this->manifestUrl = $options['manifest_url'] ??
            'https://raw.githubusercontent.com/MatheusMorimoto/toa-toa-telas/main/version.json';
        $this->minimumAppVersion = $options['app_runtime_version'] ?? '1.0.0';
        $this->httpFetcher = $options['http_fetcher'] ?? null;
        $this->beforeInstallFile = $options['before_install_file'] ?? null;
    }

    public function installedVersion(): string
    {
        $manifest = $this->readJsonFile($this->appRoot . '/version.json');
        return is_string($manifest['version'] ?? null) ? $manifest['version'] : '0.0.0';
    }

    public function check(bool $force = false): array
    {
        $this->ensureWorkDirectories();
        $cachePath = $this->workRoot . '/check-cache.json';
        $cacheSeconds = max(60, (int)env_value('UPDATER_CHECK_INTERVAL', '300'));

        if (!$force && is_file($cachePath) && filemtime($cachePath) >= time() - $cacheSeconds) {
            $cached = $this->readJsonFile($cachePath);
            if ($cached !== []) {
                return $cached;
            }
        }

        $installed = $this->installedVersion();
        $this->log('Verificando atualizações', ['installed_version' => $installed]);

        try {
            $manifestContents = $this->fetch($this->manifestUrl);
            $manifest = json_decode($manifestContents, true, 16, JSON_THROW_ON_ERROR);
            $this->validateManifest($manifest);

            $result = [
                'ok' => true,
                'status' => version_compare($manifest['version'], $installed, '>') ? 'available' : 'current',
                'installed_version' => $installed,
                'remote_version' => $manifest['version'],
                'published_at' => $manifest['published_at'],
                'message' => version_compare($manifest['version'], $installed, '>')
                    ? 'Nova atualização disponível.'
                    : 'O aplicativo já está atualizado.',
                'manifest' => $manifest,
            ];
            $this->writeJsonAtomic($cachePath, $result);
            return $result;
        } catch (Throwable $error) {
            $this->log('Não foi possível verificar atualizações', ['error' => $error->getMessage()]);
            return [
                'ok' => false,
                'status' => 'offline',
                'installed_version' => $installed,
                'message' => 'Não foi possível verificar atualizações agora. O aplicativo continuará funcionando e tentará novamente mais tarde.',
            ];
        }
    }

    public function install(?array $manifest = null): array
    {
        $this->ensureWorkDirectories();
        $lockHandle = fopen($this->workRoot . '/install.lock', 'c+');
        if ($lockHandle === false || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
            throw new UpdateException('Já existe uma atualização em andamento.');
        }

        $transactionId = gmdate('YmdHis') . '-' . bin2hex(random_bytes(4));
        $transactionRoot = $this->workRoot . '/transactions/' . $transactionId;
        $packagePath = $transactionRoot . '/package.zip';
        $stageRoot = $transactionRoot . '/stage';
        $backupRoot = $transactionRoot . '/backup';
        $installedFiles = [];

        try {
            $manifest ??= $this->check(true)['manifest'] ?? null;
            if (!is_array($manifest)) {
                throw new UpdateException('O manifesto remoto não está disponível.');
            }
            $this->validateManifest($manifest);

            $installedVersion = $this->installedVersion();
            if (!version_compare($manifest['version'], $installedVersion, '>')) {
                return [
                    'ok' => true,
                    'status' => 'current',
                    'version' => $installedVersion,
                    'message' => 'O aplicativo já está atualizado.',
                ];
            }
            if (version_compare($this->minimumAppVersion, $manifest['minimum_app_version'], '<')) {
                throw new UpdateException('Esta atualização exige uma versão mais nova do aplicativo base.');
            }

            $this->mkdir($transactionRoot);
            $this->mkdir($stageRoot);
            $this->mkdir($backupRoot);

            $this->log('Baixando atualização', ['version' => $manifest['version']]);
            $this->download($manifest['download_url'], $packagePath);
            $actualHash = hash_file('sha256', $packagePath);
            if (!is_string($actualHash) || !hash_equals(strtolower($manifest['sha256']), strtolower($actualHash))) {
                throw new UpdateException('A assinatura SHA-256 do pacote é inválida.');
            }

            $this->extractAndValidate($packagePath, $stageRoot);
            $sourceRoot = $this->locatePackageRoot($stageRoot);
            $files = $this->collectInstallableFiles($sourceRoot);
            if ($files === []) {
                throw new UpdateException('O pacote não contém arquivos permitidos para atualização.');
            }

            $this->log('Instalando atualização', ['version' => $manifest['version'], 'files' => count($files)]);
            foreach ($files as $relativePath => $sourcePath) {
                if ($relativePath === 'version.json') {
                    continue;
                }
                $this->backupAndInstall($relativePath, $sourcePath, $backupRoot);
                $installedFiles[] = $relativePath;
            }

            $installedManifest = [
                'version' => $manifest['version'],
                'download_url' => $manifest['download_url'],
                'sha256' => $manifest['sha256'],
                'minimum_app_version' => $manifest['minimum_app_version'],
                'published_at' => $manifest['published_at'],
            ];
            $versionSource = $transactionRoot . '/installed-version.json';
            $this->writeJsonAtomic($versionSource, $installedManifest);
            $this->backupAndInstall('version.json', $versionSource, $backupRoot);
            $installedFiles[] = 'version.json';

            $this->writeJsonAtomic($transactionRoot . '/transaction.json', [
                'status' => 'completed',
                'version' => $manifest['version'],
                'installed_at' => gmdate(DATE_ATOM),
                'files' => $installedFiles,
            ]);
            @unlink($this->workRoot . '/check-cache.json');
            $this->log('Aplicativo atualizado', ['version' => $manifest['version']]);

            return [
                'ok' => true,
                'status' => 'updated',
                'version' => $manifest['version'],
                'message' => 'Aplicativo atualizado.',
            ];
        } catch (Throwable $error) {
            $rollbackError = null;
            try {
                $this->rollback($installedFiles, $backupRoot);
            } catch (Throwable $rollbackFailure) {
                $rollbackError = $rollbackFailure->getMessage();
            }
            $this->log('Falha ao instalar atualização', [
                'error' => $error->getMessage(),
                'rollback_error' => $rollbackError,
            ]);
            throw new UpdateException(
                $rollbackError === null
                    ? 'A atualização falhou e a versão anterior foi restaurada: ' . $error->getMessage()
                    : 'A atualização falhou e o rollback precisa de atenção: ' . $error->getMessage(),
                0,
                $error
            );
        } finally {
            if (is_resource($lockHandle)) {
                flock($lockHandle, LOCK_UN);
                fclose($lockHandle);
            }
        }
    }

    public function validateManifest(array $manifest): void
    {
        foreach (['version', 'download_url', 'sha256', 'minimum_app_version', 'published_at'] as $field) {
            if (!isset($manifest[$field]) || !is_string($manifest[$field]) || trim($manifest[$field]) === '') {
                throw new UpdateException("O campo obrigatório '$field' está ausente no manifesto.");
            }
        }
        if (!preg_match('/\A\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?\z/', $manifest['version'])) {
            throw new UpdateException('A versão remota é inválida.');
        }
        if (!preg_match('/\A[0-9a-f]{64}\z/i', $manifest['sha256'])) {
            throw new UpdateException('O SHA-256 informado é inválido.');
        }
        if (strtotime($manifest['published_at']) === false) {
            throw new UpdateException('A data de publicação é inválida.');
        }
        $this->assertAllowedPackageUrl($manifest['download_url']);
    }

    public function assertAllowedUrl(string $url): void
    {
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        if (($parts['scheme'] ?? '') !== 'https' || !in_array($host, self::ALLOWED_HOSTS, true)) {
            throw new UpdateException('A URL de atualização não pertence a um domínio autorizado do GitHub.');
        }
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            throw new UpdateException('A URL de atualização contém componentes não autorizados.');
        }
    }

    public function assertAllowedPackageUrl(string $url): void
    {
        $this->assertAllowedUrl($url);
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        $path = '/' . ltrim($parts['path'] ?? '', '/');
        $repositoryPrefix = '/MatheusMorimoto/toa-toa-telas/';
        if (!str_starts_with(strtolower($path), strtolower($repositoryPrefix))) {
            throw new UpdateException('O pacote não pertence ao repositório oficial autorizado.');
        }
        if ($host === 'objects.githubusercontent.com') {
            throw new UpdateException('Use um endereço verificável do repositório oficial para o pacote.');
        }
    }

    public function isProtectedPath(string $relativePath): bool
    {
        $normalized = trim(str_replace('\\', '/', $relativePath), '/');
        if (strtolower($normalized) === 'certificates/cacert.pem') {
            return false;
        }
        $segments = explode('/', strtolower($normalized));
        if ($normalized === '' || in_array($segments[0], array_map('strtolower', self::BLOCKED_TOP_LEVEL), true)) {
            return true;
        }
        $basename = strtolower(basename($normalized));
        if (in_array($basename, array_map('strtolower', self::BLOCKED_FILES), true)) {
            return true;
        }
        return str_starts_with($basename, '.env') ||
            preg_match('/\.(?:sqlite|sqlite3|db|sql|pem|key|p12|pfx)\z/i', $basename) === 1;
    }

    private function fetch(string $url): string
    {
        $this->assertAllowedUrl($url);
        if (is_callable($this->httpFetcher)) {
            $contents = ($this->httpFetcher)($url);
            if (!is_string($contents)) {
                throw new UpdateException('A resposta remota é inválida.');
            }
            return $contents;
        }

        for ($redirects = 0; $redirects <= 3; $redirects++) {
            $location = null;
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_USERAGENT => 'ToaToaUpdater/1.0',
                CURLOPT_HEADERFUNCTION => function ($curl, string $header) use (&$location): int {
                    if (stripos($header, 'Location:') === 0) {
                        $location = trim(substr($header, 9));
                    }
                    return strlen($header);
                },
            ]);
            $this->configureCertificateBundle($ch);
            $contents = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($status >= 300 && $status < 400 && is_string($location) && $location !== '') {
                $url = $this->resolveRedirect($url, $location);
                $this->assertAllowedUrl($url);
                continue;
            }
            if ($contents === false || $status !== 200) {
                throw new UpdateException('Falha de rede ao consultar o GitHub' . ($error !== '' ? ': ' . $error : '.'));
            }
            return $contents;
        }
        throw new UpdateException('O GitHub excedeu o limite seguro de redirecionamentos.');
    }

    private function download(string $url, string $destination): void
    {
        $this->assertAllowedPackageUrl($url);
        if (is_callable($this->httpFetcher)) {
            $contents = ($this->httpFetcher)($url);
            if (!is_string($contents) || file_put_contents($destination, $contents, LOCK_EX) === false) {
                throw new UpdateException('Não foi possível salvar o pacote de atualização.');
            }
            return;
        }

        for ($redirects = 0; $redirects <= 3; $redirects++) {
            $file = fopen($destination, 'wb');
            if ($file === false) {
                throw new UpdateException('Não foi possível criar o pacote temporário.');
            }
            $location = null;
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_FILE => $file,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TIMEOUT => 120,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_USERAGENT => 'ToaToaUpdater/1.0',
                CURLOPT_HEADERFUNCTION => function ($curl, string $header) use (&$location): int {
                    if (stripos($header, 'Location:') === 0) {
                        $location = trim(substr($header, 9));
                    }
                    return strlen($header);
                },
            ]);
            $this->configureCertificateBundle($ch);
            $ok = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            fclose($file);

            if ($status >= 300 && $status < 400 && is_string($location) && $location !== '') {
                $url = $this->resolveRedirect($url, $location);
                $this->assertAllowedPackageUrl($url);
                continue;
            }
            if (!$ok || $status !== 200) {
                throw new UpdateException('Falha ao baixar a atualização' . ($error !== '' ? ': ' . $error : '.'));
            }
            return;
        }
        throw new UpdateException('O GitHub excedeu o limite seguro de redirecionamentos.');
    }

    private function configureCertificateBundle($curlHandle): void
    {
        $certificateBundle = $this->appRoot . '/certificates/cacert.pem';
        if (is_file($certificateBundle) && is_readable($certificateBundle)) {
            curl_setopt($curlHandle, CURLOPT_CAINFO, $certificateBundle);
        }
    }

    private function resolveRedirect(string $currentUrl, string $location): string
    {
        if (parse_url($location, PHP_URL_SCHEME) !== null) {
            return $location;
        }
        $parts = parse_url($currentUrl);
        $origin = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '');
        if (str_starts_with($location, '//')) {
            return 'https:' . $location;
        }
        if (str_starts_with($location, '/')) {
            return $origin . $location;
        }
        $directory = preg_replace('#/[^/]*\z#', '/', $parts['path'] ?? '/');
        return $origin . $directory . $location;
    }

    private function extractAndValidate(string $packagePath, string $stageRoot): void
    {
        if (!class_exists('ZipArchive')) {
            throw new UpdateException('A extensão PHP ZipArchive é necessária para instalar atualizações.');
        }
        $zip = new ZipArchive();
        if ($zip->open($packagePath) !== true) {
            throw new UpdateException('O pacote baixado está corrompido ou não é um ZIP válido.');
        }
        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entry = str_replace('\\', '/', (string)$zip->getNameIndex($index));
                if (!$this->isSafeArchivePath($entry)) {
                    throw new UpdateException('O pacote contém um caminho de arquivo não autorizado.');
                }
                $attributes = $zip->getExternalAttributesIndex($index, $operations, $externalAttributes)
                    ? (($externalAttributes >> 16) & 0170000)
                    : 0;
                if ($attributes === 0120000) {
                    throw new UpdateException('Links simbólicos não são permitidos no pacote.');
                }
            }
            if (!$zip->extractTo($stageRoot)) {
                throw new UpdateException('Não foi possível extrair o pacote de atualização.');
            }
        } finally {
            $zip->close();
        }
    }

    private function isSafeArchivePath(string $entry): bool
    {
        if ($entry === '' || str_starts_with($entry, '/') || preg_match('/\A[A-Za-z]:\//', $entry)) {
            return false;
        }
        foreach (explode('/', trim($entry, '/')) as $segment) {
            if ($segment === '..' || $segment === '') {
                return false;
            }
        }
        return !str_contains($entry, "\0");
    }

    private function locatePackageRoot(string $stageRoot): string
    {
        $items = array_values(array_filter(scandir($stageRoot) ?: [], fn($item) => $item !== '.' && $item !== '..'));
        if (count($items) === 1 && is_dir($stageRoot . '/' . $items[0])) {
            return $stageRoot . '/' . $items[0];
        }
        return $stageRoot;
    }

    private function collectInstallableFiles(string $sourceRoot): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceRoot, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile() || $fileInfo->isLink()) {
                continue;
            }
            $relative = str_replace('\\', '/', substr($fileInfo->getPathname(), strlen($sourceRoot) + 1));
            if ($this->isProtectedPath($relative)) {
                continue;
            }
            $files[$relative] = $fileInfo->getPathname();
        }
        ksort($files);
        return $files;
    }

    private function backupAndInstall(string $relativePath, string $sourcePath, string $backupRoot): void
    {
        if ($this->isProtectedPath($relativePath)) {
            throw new UpdateException("Tentativa de atualizar arquivo protegido: $relativePath");
        }
        if (is_callable($this->beforeInstallFile)) {
            ($this->beforeInstallFile)($relativePath);
        }

        $targetPath = $this->appRoot . '/' . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $backupPath = $backupRoot . '/' . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $this->mkdir(dirname($targetPath));
        $this->mkdir(dirname($backupPath));

        if (is_file($targetPath)) {
            if (!copy($targetPath, $backupPath)) {
                throw new UpdateException("Não foi possível criar backup de $relativePath.");
            }
        } else {
            if (file_put_contents($backupPath . '.missing', '') === false) {
                throw new UpdateException("Não foi possível registrar o backup de $relativePath.");
            }
        }

        $temporaryTarget = $targetPath . '.update-' . bin2hex(random_bytes(4));
        if (!copy($sourcePath, $temporaryTarget)) {
            throw new UpdateException("Não foi possível preparar $relativePath.");
        }
        if (!rename($temporaryTarget, $targetPath)) {
            @unlink($temporaryTarget);
            throw new UpdateException("Não foi possível instalar $relativePath.");
        }
    }

    private function rollback(array $installedFiles, string $backupRoot): void
    {
        foreach (array_reverse($installedFiles) as $relativePath) {
            $targetPath = $this->appRoot . '/' . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            $backupPath = $backupRoot . '/' . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            if (is_file($backupPath)) {
                $temporaryTarget = $targetPath . '.rollback-' . bin2hex(random_bytes(4));
                if (!copy($backupPath, $temporaryTarget) || !rename($temporaryTarget, $targetPath)) {
                    @unlink($temporaryTarget);
                    throw new UpdateException("Falha ao restaurar $relativePath.");
                }
            } elseif (is_file($backupPath . '.missing') && is_file($targetPath)) {
                if (!unlink($targetPath)) {
                    throw new UpdateException("Falha ao remover $relativePath durante o rollback.");
                }
            }
        }
    }

    private function ensureWorkDirectories(): void
    {
        $this->mkdir($this->workRoot);
        $this->mkdir($this->workRoot . '/transactions');
    }

    private function mkdir(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
            throw new UpdateException("Não foi possível criar o diretório de trabalho.");
        }
    }

    private function readJsonFile(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }
        try {
            $decoded = json_decode((string)file_get_contents($path), true, 16, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : [];
        } catch (Throwable) {
            return [];
        }
    }

    private function writeJsonAtomic(string $path, array $data): void
    {
        $this->mkdir(dirname($path));
        $temporary = $path . '.tmp-' . bin2hex(random_bytes(4));
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false || file_put_contents($temporary, $json . PHP_EOL, LOCK_EX) === false ||
            !rename($temporary, $path)) {
            @unlink($temporary);
            throw new UpdateException('Não foi possível salvar o estado da atualização.');
        }
    }

    private function log(string $message, array $context = []): void
    {
        try {
            $this->ensureWorkDirectories();
            $safeContext = array_intersect_key($context, array_flip([
                'installed_version',
                'version',
                'files',
                'error',
                'rollback_error',
            ]));
            $line = json_encode([
                'time' => gmdate(DATE_ATOM),
                'message' => $message,
                'context' => $safeContext,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (is_string($line)) {
                file_put_contents($this->workRoot . '/updater.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
            }
        } catch (Throwable) {
            // O log nunca deve interromper o aplicativo ou esconder o erro original.
        }
    }
}
