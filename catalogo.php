<?php

require_once __DIR__ . '/services/catalog_service.php';

$selectedIds = catalog_normalize_ids((string)($_GET['ids'] ?? ''));
$catalogResult = catalog_load_products();
$products = catalog_filter_products($catalogResult['products'], $selectedIds);
$publicUrl = catalog_public_url($selectedIds);
$whatsappNumber = catalog_whatsapp_number();
$publicBaseUrl = catalog_public_base_url();
$shareImageUrl = $publicBaseUrl !== '' ? $publicBaseUrl . '/toatoa.png' : '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#001d3d">
    <meta name="description" content="Catálogo de vestidos selecionados da Tôa Tôa Moda Festa">
    <meta property="og:type" content="website">
    <meta property="og:title" content="Catálogo Tôa Tôa Moda Festa">
    <meta property="og:description" content="Confira os vestidos selecionados especialmente para você.">
    <?php if ($publicUrl !== ''): ?><meta property="og:url" content="<?= htmlspecialchars($publicUrl, ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
    <?php if ($shareImageUrl !== ''): ?><meta property="og:image" content="<?= htmlspecialchars($shareImageUrl, ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
    <title>Catálogo Tôa Tôa Moda Festa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="index.css" rel="stylesheet">
    <style>
        body { background: #f4f6f9; color: #001d3d; }
        .public-header { background: #001d3d; color: #ffd700; padding: 2rem 1rem; text-align: center; border-bottom: 5px solid #ffd700; }
        .public-header img { width: 88px; height: 88px; object-fit: contain; border-radius: 50%; background: #fff; }
        .catalog-grid { grid-template-columns: repeat(auto-fit, minmax(min(100%, 260px), 1fr)); }
        .catalog-card { display: flex; flex-direction: column; }
        .catalog-card img { height: 360px; }
        .catalog-info { display: flex; flex: 1; flex-direction: column; }
        .catalog-description { min-height: 2.8rem; }
        .btn-whatsapp { margin-top: auto; text-decoration: none; }
        .connection-note { max-width: 760px; margin: 1rem auto 0; }
    </style>
</head>
<body>
<header class="public-header">
    <img src="toatoa.png" alt="Logotipo Tôa Tôa Moda Festa" onerror="this.style.display='none'">
    <h1 class="h3 mt-3 mb-1">Tôa Tôa Moda Festa</h1>
    <p class="mb-0">Vestidos selecionados especialmente para você</p>
</header>

<main class="container py-4">
    <?php if ($catalogResult['cached']): ?>
        <div class="alert alert-warning connection-note" role="status">
            <i class="bi bi-wifi-off me-2"></i>
            Estamos exibindo a última versão disponível do catálogo enquanto a conexão é restabelecida.
        </div>
    <?php endif; ?>

    <?php if ($selectedIds === []): ?>
        <div class="alert alert-info text-center">
            Este link não contém produtos selecionados. Solicite um novo catálogo à loja.
        </div>
    <?php elseif ($products === []): ?>
        <div class="alert alert-warning text-center">
            Não foi possível encontrar os vestidos deste catálogo no momento. Tente novamente em alguns instantes.
        </div>
    <?php else: ?>
        <div class="catalog-grid">
            <?php foreach ($products as $product): ?>
                <?php
                $id = (string)($product['id'] ?? '');
                $name = (string)($product['nomeProduto'] ?? $product['nome'] ?? 'Vestido de Festa');
                $category = (string)($product['categoria'] ?? 'Festa');
                $description = (string)($product['descricao'] ?? '');
                $rentalPrice = (float)($product['precoPacote'] ?? $product['preco_pacote'] ?? 0);
                $salePrice = (float)($product['precoUnitario'] ?? $product['preco_unitario'] ?? 0);
                $image = toa_product_image($product['imagem'] ?? '');
                $interestMessage = "Olá! Tenho interesse no vestido {$name} (código #{$id}).";
                if ($publicUrl !== '') {
                    $interestMessage .= "\n\nCatálogo: " . $publicUrl;
                }
                ?>
                <article class="catalog-card">
                    <img src="<?= htmlspecialchars($image, ENT_QUOTES, 'UTF-8') ?>"
                         alt="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"
                         loading="lazy"
                         onerror="this.onerror=null;this.src='toatoa.png'">
                    <div class="catalog-info">
                        <span class="badge bg-secondary align-self-center mb-2"><?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?></span>
                        <h2 class="h5 fw-bold"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></h2>
                        <p class="catalog-description text-muted small"><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></p>
                        <?php if ($rentalPrice > 0): ?>
                            <div class="catalog-price">Aluguel: R$ <?= number_format($rentalPrice, 2, ',', '.') ?></div>
                        <?php endif; ?>
                        <?php if ($salePrice > 0): ?>
                            <div class="small mt-1">Venda: R$ <?= number_format($salePrice, 2, ',', '.') ?></div>
                        <?php endif; ?>
                        <a href="<?= htmlspecialchars(catalog_whatsapp_url($interestMessage, $whatsappNumber), ENT_QUOTES, 'UTF-8') ?>"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="btn btn-whatsapp">
                            <i class="bi bi-whatsapp me-2"></i>Tenho interesse
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <footer class="py-5 text-center text-muted">
        <hr>
        <strong>Tôa Tôa Moda Festa</strong><br>Cuiabá — MT
    </footer>
</main>
</body>
</html>
