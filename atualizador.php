<?php
require_once __DIR__ . '/security.php';
require_auth();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tôa Tôa — Atualizações</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #001d3d; color: #fff; font-family: system-ui, sans-serif; }
        main { width: min(560px, calc(100% - 2rem)); padding: 2rem; box-sizing: border-box; border: 1px solid #ffd700; border-radius: 1rem; background: #082a4d; }
        h1 { margin-top: 0; color: #ffd700; }
        button { border: 0; border-radius: .5rem; padding: .75rem 1rem; background: #ffd700; color: #001d3d; font-weight: 700; cursor: pointer; }
        button:disabled { opacity: .6; cursor: wait; }
        #status { min-height: 3rem; margin: 1rem 0; line-height: 1.5; }
        a { color: #ffd700; }
    </style>
</head>
<body>
<main>
    <h1>Atualizações do sistema</h1>
    <p id="status" role="status" aria-live="polite">Pronto para verificar o GitHub.</p>
    <button id="update-button" type="button">Verificar agora</button>
    <p><a href="index.php">Voltar ao sistema</a></p>
</main>
<script>
const statusElement = document.getElementById('status');
const button = document.getElementById('update-button');
const csrfToken = <?= json_encode(csrf_token(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

async function update() {
    button.disabled = true;
    statusElement.textContent = 'Verificando atualizações…';
    try {
        const checkResponse = await fetch('update_api.php?action=check', { cache: 'no-store' });
        const check = await checkResponse.json();
        if (!checkResponse.ok || !check.ok) throw new Error(check.message);
        if (check.status !== 'available') {
            statusElement.textContent = check.message;
            return;
        }

        statusElement.textContent = 'Baixando e instalando atualização…';
        const installResponse = await fetch('update_api.php?action=install', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
            body: new URLSearchParams({ csrf_token: csrfToken }),
        });
        const install = await installResponse.json();
        if (!installResponse.ok || !install.ok) throw new Error(install.message);
        statusElement.textContent = 'Aplicativo atualizado. Recarregando as telas…';
        setTimeout(() => window.location.href = 'index.php', 1200);
    } catch (error) {
        statusElement.textContent = error instanceof Error
            ? error.message
            : 'Não foi possível atualizar agora. A versão atual continua funcionando.';
    } finally {
        button.disabled = false;
    }
}

button.addEventListener('click', update);
update();
</script>
</body>
</html>
