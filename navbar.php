<?php start_secure_session(); ?>
<!-- 1. Cabeçalho de Identidade (Top Bar) -->
<style>
    /* Efeito de hover para os dropdowns aparecerem ao passar o mouse */
    .dropdown:hover .dropdown-menu {
        display: block;
        margin-top: 0;
    }
    #update-status {
        position: fixed;
        right: 1rem;
        bottom: 1rem;
        z-index: 1080;
        max-width: min(420px, calc(100vw - 2rem));
        display: none;
        padding: .8rem 1rem;
        border-radius: .6rem;
        color: #fff;
        background: #001d3d;
        border: 1px solid #ffd700;
        box-shadow: 0 .4rem 1.4rem rgba(0, 0, 0, .3);
        font: 500 .9rem/1.35 system-ui, sans-serif;
    }
</style>
<header class="top-bar">
    <div class="brand-area">
        <img src="toatoa.png" alt="Tôa Tôa" onerror="this.src='https://via.placeholder.com/40x40?text=TT'">
        <div class="brand-text">
            <span class="brand-main">Tôa Tôa Moda Festa</span>
            <span class="brand-sub">Patrocinadora oficial do Miss Mato Grosso</span>
            <span class="brand-desc">Formandas, Madrinhas, Noivas e Balada</span>
        </div>
    </div>
    
    <div class="title-area">
        <!-- Título Central do Sistema -->
        <h1>SISTEMA TOA TOA</h1>
    </div>

    <div class="header-right-content">
        <div class="nav-links-top">
            <!-- Dropdown Clientes -->
            <div class="dropdown d-inline-block me-1">
                <button class="btn btn-outline-warning btn-sm dropdown-toggle" type="button" id="dropdownClientes" data-bs-toggle="dropdown" aria-expanded="false">
                    Clientes
                </button>
                <ul class="dropdown-menu shadow" aria-labelledby="dropdownClientes">
                    <li><a class="dropdown-item" href="cadastro_cliente.php">Cadastro de Cliente</a></li>
                    <li><a class="dropdown-item" href="clientes_cadastrados.php">Clientes Cadastrados</a></li>
                </ul>
            </div>

            <div class="dropdown d-inline-block">
                <button class="btn btn-outline-warning btn-sm me-1 dropdown-toggle" type="button" id="dropdownProdutos" aria-expanded="false">
                    Produtos
                </button>
                <ul class="dropdown-menu shadow" aria-labelledby="dropdownProdutos">
                    <li><a class="dropdown-item" href="index.php">Cadastro de Produtos</a></li>
                    <li><a class="dropdown-item" href="produtos.php">Produtos Cadastrados</a></li>
                </ul>
            </div>
        </div>
        <div class="search-container ms-3"> 
            <form action="produtos.php" method="GET">
                <input type="text" name="busca" class="form-control" placeholder="Busca rápida...">
            </form>
        </div>
        <?php if (env_bool('APP_AUTH_ENABLED', false)): ?>
            <form action="logout.php" method="POST" class="ms-2">
                <?= csrf_input() ?>
                <button type="submit" class="btn btn-outline-light btn-sm" title="Sair">
                    <i class="bi bi-box-arrow-right"></i>
                </button>
            </form>
        <?php endif; ?>
    </div>
</header>
<div id="update-status" role="status" aria-live="polite"></div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form[method="POST"]').forEach((form) => {
        form.addEventListener('submit', () => {
            form.setAttribute('aria-busy', 'true');
            setTimeout(() => {
                form.querySelectorAll('button[type="submit"]').forEach((button) => {
                    button.disabled = true;
                });
            }, 0);
        });
    });

    const updateStatus = document.getElementById('update-status');
    const csrfToken = <?= json_encode(csrf_token(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    let checkingUpdate = false;

    const showUpdateStatus = (message, kind = 'info', hideAfter = 0) => {
        if (!updateStatus) return;
        updateStatus.textContent = message;
        updateStatus.style.display = 'block';
        updateStatus.style.borderColor = kind === 'error' ? '#dc3545' : '#ffd700';
        if (hideAfter > 0) {
            window.setTimeout(() => {
                updateStatus.style.display = 'none';
            }, hideAfter);
        }
    };

    const installUpdate = async () => {
        showUpdateStatus('Baixando e instalando atualização…');
        const body = new URLSearchParams({ csrf_token: csrfToken });
        const response = await fetch('update_api.php?action=install', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
            body,
        });
        const result = await response.json();
        if (!response.ok || !result.ok) {
            throw new Error(result.message || 'Não foi possível instalar a atualização.');
        }
        if (result.status === 'updated') {
            showUpdateStatus('Aplicativo atualizado. Recarregando as telas…');
            window.setTimeout(() => window.location.reload(), 1200);
        } else {
            showUpdateStatus('O aplicativo já está atualizado.', 'info', 2500);
        }
    };

    const checkForUpdates = async () => {
        if (checkingUpdate || document.visibilityState === 'hidden') return;
        checkingUpdate = true;
        showUpdateStatus('Verificando atualizações…');
        try {
            const response = await fetch('update_api.php?action=check', {
                credentials: 'same-origin',
                cache: 'no-store',
            });
            const result = await response.json();
            if (!response.ok) throw new Error(result.message || 'Falha na verificação.');
            if (result.status === 'available') {
                await installUpdate();
            } else if (result.status === 'offline') {
                showUpdateStatus(result.message, 'error', 5000);
            } else {
                showUpdateStatus('Aplicativo atualizado.', 'info', 1800);
            }
        } catch (error) {
            showUpdateStatus(
                error instanceof Error ? error.message : 'Não foi possível verificar atualizações agora.',
                'error',
                5000
            );
        } finally {
            checkingUpdate = false;
        }
    };

    checkForUpdates();
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') checkForUpdates();
    });
});
</script>
