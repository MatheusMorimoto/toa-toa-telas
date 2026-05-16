<?php
include_once 'db.php';

// Busca os produtos na API Node.js/Supabase
$result = listarProdutos();

// Verifica se houve erro na API
$api_error = isset($result['error']) ? $result : null;
$produtos = $api_error ? [] : $result;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tôa Tôa - Catálogo de Produtos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <!-- Refresh automático removido: o catálogo agora é carregado apenas sob demanda do usuário -->
    <link href="index.css" rel="stylesheet">
    <!-- 
         Refatoração de Engenharia: 
         - Removido qualquer script de refresh (setInterval/setTimeout).
         - Sistema opera estritamente sob demanda (Manual).
    -->
    <style>
        :root {
            --brand-navy: #001D3D;
            --brand-gold: #FFD700;
            --brand-green: #2D8A4E;
        }
        body { background-color: #f4f6f9; }
        .table-container { background: white; border-radius: 12px; overflow: hidden; border: 1px solid #e0e0e0; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .thead-brand { background-color: var(--brand-navy); color: var(--brand-gold); border-bottom: 4px solid var(--brand-gold); }
        .thead-brand th { font-weight: 700; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 0.5px; padding: 15px; border: none; }
        .table tbody tr { transition: background-color 0.2s; border-bottom: 1px solid #f0f0f0; }
        .table tbody tr:hover { background-color: #fcf9e8; transform: translateY(-1px); }
        .img-thumb { width: 55px; height: 55px; object-fit: cover; border-radius: 8px; border: 1px solid #ddd; transition: transform 0.2s; }
        .img-thumb:hover { transform: scale(1.1); }
        .price-tag { color: var(--brand-green); font-weight: 700; }
        .rental-tag { color: var(--brand-navy); font-weight: 700; }
        .badge-cat { background-color: var(--brand-green); padding: 5px 10px; font-size: 0.75rem; }
        .desc-text { font-size: 0.85rem; color: #666; max-width: 200px; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .stock-badge { font-size: 0.85rem; padding: 5px 12px; font-weight: 600; }
        .cod-badge { font-family: monospace; font-size: 0.9rem; color: var(--brand-navy); background: #fff9c4; padding: 3px 8px; border-radius: 4px; }
    </style>
</head>
<body>
    <?php include_once 'navbar.php'; ?>

    <div class="container-fluid main-content mt-4">
        <?php if (isset($_GET['editado'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i>Produto atualizado com sucesso!
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['excluido'])): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="bi bi-trash me-2"></i>Produto removido com sucesso.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="section-title mb-0"><i class="bi bi-box-seam me-2"></i>Produtos em Estoque</h4>
            <a href="catalogo.php" class="btn btn-warning-custom btn-sm shadow-sm">
                <i class="bi bi-journal-richtext me-1"></i> Criar Catálogo
            </a>
        </div>

        <!-- Barra de Ações para Gerar Catálogo -->
        <div id="catalogBar" class="catalog-bar">
            <span><strong id="selectedCount">0</strong> vestidos selecionados</span>
            <button class="btn btn-warning btn-sm fw-bold" onclick="gerarLink()">
                <i class="bi bi-link-45deg"></i> Gerar Link do Catálogo
            </button>
        </div>

        <?php if ($api_error): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-octagon me-2"></i>
                <strong>Erro ao carregar:</strong> <?php echo htmlspecialchars($api_error['detalhes']); ?>
            </div>
        <?php elseif (empty($produtos)): ?>
            <div class="text-center p-5 bg-white border rounded shadow-sm">
                <i class="bi bi-search display-1 text-muted"></i>
                <p class="mt-3 fs-5">Nenhum produto cadastrado no momento.</p>
            </div>
        <?php else: ?>
            <div class="table-container shadow-sm">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="thead-brand">
                            <tr>
                                <th class="ps-3 text-center"><input type="checkbox" id="selectAll" class="form-check-input"></th>
                                <th class="ps-3">Foto</th>
                                <th>Cód.</th>
                                <th>Nome do Vestido</th>
                                <th>Categoria</th>
                                <th>Aquisição</th>
                                <th class="text-center">Qtd</th>
                                <th class="text-end">Venda (R$)</th>
                                <th class="text-end">Aluguel (R$)</th>
                                <th class="pe-3 text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($produtos as $p): ?>
                                <tr>
                                    <td class="ps-3 text-center"><input type="checkbox" class="form-check-input product-check" value="<?php echo htmlspecialchars($p['id'] ?? ''); ?>"></td>
                                    <td class="ps-3">
                                        <?php 
                                            // Mapeamento robusto: aceita snake_case (DB) e camelCase (API Docs)
                                            $id       = $p['id']            ?? '---';
                                            $nome     = $p['nomeProduto']   ?? $p['nome']          ?? '';
                                            $cat      = $p['categoria']     ?? '';
                                            $qtd      = $p['quantidade']    ?? 0;
                                            $venda    = $p['precoUnitario'] ?? $p['preco_unitario'] ?? 0;
                                            $aluguel  = $p['precoPacote']   ?? $p['preco_pacote']   ?? 0;
                                            $imgNome  = $p['imagem']        ?? '';
                                            $dataAq   = $p['validade']      ?? '';

                                            $caminhoImg = (!empty($imgNome) && file_exists('imagens/' . $imgNome)) 
                                                          ? 'imagens/' . $imgNome 
                                                          : 'toatoa.png';
                                        ?>
                                        <img src="<?php echo htmlspecialchars($caminhoImg); ?>" 
                                             class="img-thumb" alt="Foto" onerror="this.src='https://via.placeholder.com/60x60?text=Vestido'">
                                    </td>
                                    <td><span class="cod-badge">#<?php echo htmlspecialchars($id); ?></span></td>
                                    <td class="fw-bold text-dark" style="font-size: 0.95rem;"><?php echo htmlspecialchars($nome); ?></td>
                                    <td><span class="badge badge-cat rounded-pill"><?php echo htmlspecialchars($cat); ?></span></td>
                                    <td class="text-muted small">
                                        <?php echo (!empty($dataAq) && $dataAq !== '0000-00-00') ? date('d/m/Y', strtotime($dataAq)) : '--/--/--'; ?>
                                    </td>
                                    <td class="text-center"><span class="badge stock-badge rounded-pill bg-light text-dark border"><?php echo (int)$qtd; ?></span></td>
                                    <td class="text-end price-tag">R$ <?php echo number_format((float)$venda, 2, ',', '.'); ?></td>
                                    <td class="text-end rental-tag">R$ <?php echo number_format((float)$aluguel, 2, ',', '.'); ?></td>
                                    <td class="text-end pe-3">
                                        <a href="editar_produto.php?id=<?php echo urlencode($id); ?>" class="btn btn-sm btn-outline-secondary" title="Ver detalhes"><i class="bi bi-eye"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal para Exibir o Link -->
    <div class="modal fade" id="linkModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title">Link do Catálogo Gerado</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <p>Envie este link para o cliente pelo WhatsApp:</p>
                    <div class="input-group mb-3">
                        <input type="text" id="generatedLink" class="form-control" readonly>
                        <button class="btn btn-primary" onclick="copiarLink()"><i class="bi bi-clipboard"></i></button>
                    </div>
                    <div class="alert alert-info small">Este link abrirá uma vitrine apenas com os vestidos selecionados.</div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const checks = document.querySelectorAll('.product-check');
        const catalogBar = document.getElementById('catalogBar');
        const countSpan = document.getElementById('selectedCount');

        function updateBar() {
            const selected = Array.from(checks).filter(c => c.checked).length;
            catalogBar.style.display = selected > 0 ? 'flex' : 'none';
            countSpan.innerText = selected;
        }

        checks.forEach(c => c.addEventListener('change', updateBar));
        document.getElementById('selectAll').addEventListener('change', function() {
            checks.forEach(c => c.checked = this.checked);
            updateBar();
        });

        function gerarLink() {
            const ids = Array.from(checks).filter(c => c.checked).map(c => c.value).join(',');
            const url = window.location.origin + window.location.pathname.replace('produtos.php', 'catalogo.php') + '?ids=' + ids;
            document.getElementById('generatedLink').value = url;
            new bootstrap.Modal(document.getElementById('linkModal')).show();
        }

        function copiarLink() {
            const input = document.getElementById('generatedLink');
            input.select();
            document.execCommand('copy');
            alert('Link copiado com sucesso!');
        }

        // Fecha os alertas automaticamente após 3 segundos
        document.querySelectorAll('.alert').forEach(function(alert) {
            setTimeout(function() {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 3000);
        });
    </script>
</body>
</html>