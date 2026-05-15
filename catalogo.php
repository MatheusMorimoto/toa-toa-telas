<?php
include_once 'db.php';
$ids_request = isset($_GET['ids']) ? explode(',', $_GET['ids']) : [];
$all_products = listarProdutos();
if (isset($all_products['error'])) { $produtos = []; } 
else {
    if (empty($ids_request)) {
        // Se não houver IDs na URL, mostra todos para permitir seleção
        $produtos = $all_products;
    } else {
        // Filtra apenas os produtos selecionados anteriormente
        $produtos = array_filter($all_products, function($p) use ($ids_request) {
            return in_array($p['id'] ?? '', $ids_request);
        });
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tôa Tôa - Vitrine Exclusiva</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="index.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .public-header { background: #001D3D; color: #FFD700; padding: 30px 0; text-align: center; border-bottom: 5px solid #FFD700; }
        .catalog-card { position: relative; }
        .select-overlay {
            position: absolute;
            top: 10px;
            left: 10px;
            z-index: 10;
            transform: scale(1.5);
            cursor: pointer;
        }
    </style>
</head>
<body>
    <header class="public-header mb-4">
        <img src="toatoa.png" height="80" class="mb-2">
        <h2>Vitrine Tôa Tôa Moda Festa</h2>
        <p class="mb-0">Vestidos selecionados especialmente para você</p>
    </header>

    <!-- Barra de Ações do Administrador -->
    <div id="catalogBar" class="catalog-bar" style="display: flex;">
        <a href="produtos.php" class="btn btn-outline-light btn-sm">
            <i class="bi bi-arrow-left"></i> Voltar para Estoque
        </a>
        <span><strong id="selectedCount">0</strong> selecionados</span>
        <button class="btn btn-warning btn-sm fw-bold" onclick="gerarLink()">
            <i class="bi bi-link-45deg"></i> Gerar Link
        </button>
    </div>

    <div class="container">
        <?php if (empty($produtos)): ?>
            <div class="alert alert-warning text-center">Nenhum vestido disponível nesta vitrine.</div>
        <?php else: ?>
            <div class="catalog-grid">
                <?php foreach ($produtos as $p): ?>
                    <?php 
                        $nome = $p['nomeProduto'] ?? $p['nome'] ?? 'Vestido de Festa';
                        $aluguel = $p['precoPacote'] ?? $p['preco_pacote'] ?? 0;
                        $imgNome = $p['imagem'] ?? '';
                        $caminhoImg = (!empty($imgNome) && file_exists('imagens/' . $imgNome)) ? 'imagens/' . $imgNome : 'toatoa.png';
                        $wa_msg = "Olá, gostei deste vestido: " . $nome . " (ID #" . ($p['id'] ?? '') . ")";
                    ?>
                    <div class="catalog-card">
                        <input type="checkbox" class="form-check-input select-overlay product-check" 
                               value="<?php echo htmlspecialchars($p['id'] ?? ''); ?>" <?php echo in_array($p['id'], $ids_request) ? 'checked' : ''; ?>>
                        <img src="<?php echo htmlspecialchars($caminhoImg); ?>" alt="Vestido">
                        <div class="catalog-info">
                            <span class="badge bg-secondary mb-2"><?php echo htmlspecialchars($p['categoria'] ?? 'Festa'); ?></span>
                            <h5 class="fw-bold"><?php echo htmlspecialchars($nome); ?></h5>
                            <p class="text-muted small mb-1"><?php echo htmlspecialchars($p['descricao'] ?? ''); ?></p>
                            <div class="catalog-price">Aluguel: R$ <?php echo number_format((float)$aluguel, 2, ',', '.'); ?></div>
                            <a href="https://wa.me/SEU_NUMERO_AQUI?text=<?php echo urlencode($wa_msg); ?>" target="_blank" class="btn btn-whatsapp">
                                <i class="bi bi-whatsapp me-2"></i> Tenho Interesse
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <footer class="py-5 text-center text-muted">
            <hr>
            <p><strong>Tôa Tôa Moda Festa</strong><br>Cuiabá - MT</p>
        </footer>
    </div>

    <!-- Modal para Exibir o Link -->
    <div class="modal fade" id="linkModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title">Link do Catálogo Gerado</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 text-dark">
                    <p>Copie o link abaixo para enviar ao cliente:</p>
                    <div class="input-group mb-3">
                        <input type="text" id="generatedLink" class="form-control" readonly>
                        <button class="btn btn-primary" onclick="copiarLink()"><i class="bi bi-clipboard"></i></button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const checks = document.querySelectorAll('.product-check');
        const countSpan = document.getElementById('selectedCount');

        function updateCount() {
            const selected = Array.from(checks).filter(c => c.checked).length;
            countSpan.innerText = selected;
        }

        checks.forEach(c => c.addEventListener('change', updateCount));
        updateCount(); // Inicializa contagem

        function gerarLink() {
            const ids = Array.from(checks).filter(c => c.checked).map(c => c.value).join(',');
            const url = window.location.origin + window.location.pathname + '?ids=' + ids;
            document.getElementById('generatedLink').value = url;
            new bootstrap.Modal(document.getElementById('linkModal')).show();
        }

        function copiarLink() {
            const input = document.getElementById('generatedLink');
            input.select();
            document.execCommand('copy');
            alert('Link copiado!');
        }
    </script>
</body>
</html>