<?php
/**
 * SISTEMA DE ATUALIZAÇÃO AUTOMÁTICA - TÔA TÔA MODA FESTA
 * Este arquivo é isolado e não altera a lógica do banco de dados ou das APIs.
 */

// 1. Definição da Versão Local (Mude este número sempre que subir uma nova versão)
define('SYSTEM_VERSION', '1.0.0');

// 2. Configurações do GitHub
$repoOwner = "MatheusMorimoto";
$repoName  = "toa-toa-telas";
$branch    = "main";
$versionUrl = "https://raw.githubusercontent.com/$repoOwner/$repoName/$branch/version.json";

function logUpdate($message) {
    echo "<div style='font-family: sans-serif; padding: 10px; border-bottom: 1px solid #eee;'>";
    echo "<strong>[Update Log]</strong> " . htmlspecialchars($message);
    echo "</div>";
}

try {
    // 3. Busca informações da versão remota via cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $versionUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        throw new Exception("Não foi possível conectar ao GitHub para verificar atualizações.");
    }

    $remoteData = json_decode($response, true);
    if (!$remoteData || !isset($remoteData['version'])) {
        throw new Exception("Arquivo version.json inválido no repositório.");
    }

    // 4. Compara versões
    if (version_compare($remoteData['version'], SYSTEM_VERSION, '>')) {
        logUpdate("Nova versão detectada: " . $remoteData['version'] . " (Atual: " . SYSTEM_VERSION . ")");
        
        $zipFile = __DIR__ . '/update_package.zip';
        $tempDir = __DIR__ . '/temp_update/';

        // 5. Download do pacote ZIP
        logUpdate("Baixando pacote de atualização...");
        $fp = fopen($zipFile, 'w+');
        $ch = curl_init($remoteData['download_url']);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($ch);
        curl_close($ch);
        fclose($fp);

        // 6. Extração dos arquivos
        $zip = new ZipArchive;
        if ($zip->open($zipFile) === TRUE) {
            if (!is_dir($tempDir)) mkdir($tempDir, 0777, true);
            $zip->extractTo($tempDir);
            $zip->close();
            unlink($zipFile); // Remove o ZIP após extrair

            // GitHub ZIPs criam uma subpasta (ex: toa-toa-telas-main)
            $subFolders = glob($tempDir . '*', GLOB_ONLYDIR);
            if (empty($subFolders)) throw new Exception("Erro na estrutura do pacote baixado.");
            
            $source = $subFolders[0] . '/';
            $destination = __DIR__ . '/';

            // 7. Sobrescrever arquivos existentes
            logUpdate("Aplicando alterações nos arquivos...");
            $directoryIterator = new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS);
            $iterator = new RecursiveIteratorIterator($directoryIterator, RecursiveIteratorIterator::SELF_FIRST);

            foreach ($iterator as $item) {
                $target = $destination . $iterator->getSubPathName();
                if ($item->isDir()) {
                    if (!is_dir($target)) mkdir($target, 0777, true);
                } else {
                    copy($item->getPathname(), $target);
                }
            }

            // 8. Limpeza do diretório temporário
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($files as $fileinfo) {
                $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
                $todo($fileinfo->getRealPath());
            }
            rmdir($tempDir);

            logUpdate("<span style='color: green;'>Sistema atualizado com sucesso para v" . $remoteData['version'] . "!</span>");
            echo "<script>setTimeout(() => { window.location.href = 'index.php'; }, 3000);</script>";
        } else {
            throw new Exception("Falha ao abrir o arquivo ZIP. Verifique permissões de pasta.");
        }
    } else {
        logUpdate("O sistema já está em sua versão mais recente.");
    }

} catch (Exception $e) {
    logUpdate("<span style='color: red;'>Aviso: " . $e->getMessage() . "</span>");
    // O sistema continua funcionando normalmente mesmo se houver erro aqui
}
?>