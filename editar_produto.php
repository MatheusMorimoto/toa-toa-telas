<?php
include_once 'db.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: produtos.php");
    exit;
}

// Verifica se o modo é apenas visualização
$viewOnly = isset($_GET['view']) && $_GET['view'] == '1';

// 1. Busca os dados atuais do produto para preencher o formulário
$produto = obterProdutoPorId($id);

if (!$produto || isset($produto['error'])) {
    $detalhes = is_array($produto) ? ($produto['detalhes'] ?? 'Produto não encontrado.') : 'Produto não encontrado.';
    die("<div class='alert alert-danger'>Erro ao carregar produto: " .
        htmlspecialchars((string)$detalhes, ENT_QUOTES, 'UTF-8') .
        "</div>");
}

$mensagem = "";

// NOVO: Lógica para processar a exclusão do produto
if (!$viewOnly && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'excluir') {
    validate_csrf();
    $res = excluirProduto($id);
    if (isset($res['error'])) {
        $mensagem = "<div class='alert alert-danger'><strong>Erro ao excluir:</strong> " . ($res['detalhes'] ?? 'Erro na API') . "</div>";
    } else {
        header("Location: produtos.php?excluido=1");
        exit;
    }
}

// 2. Processa a atualização quando o formulário é enviado
if (!$viewOnly && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== 'excluir') {
    validate_csrf();
    $upload = toa_validate_product_upload($_FILES['imagemProduto'] ?? null);
    if (isset($upload['error'])) {
        http_response_code(422);
        exit(htmlspecialchars($upload['error'], ENT_QUOTES, 'UTF-8'));
    }
    $dadosProduto = toa_product_form_data($_POST, $upload['file']);

    if ($dadosProduto['nomeProduto'] === '' ||
        $dadosProduto['quantidade'] < 0 ||
        $dadosProduto['precoUnitario'] < 0 ||
        $dadosProduto['precoPacote'] < 0) {
        http_response_code(422);
        exit('Dados do produto inválidos.');
    }

    // 3. Envia para a API (Usando PUT conforme configurado no db.php corrigido)
    $res = editarProduto($id, $dadosProduto);

    if (isset($res['error'])) {
        // Tratamento de erro idêntico ao salvar_produto.php
        echo "<div class='alert alert-danger'><strong>Erro ao salvar:</strong> " .
            htmlspecialchars((string)$res['error'], ENT_QUOTES, 'UTF-8') .
            "<br><strong>Detalhe Técnico:</strong> " .
            htmlspecialchars((string)($res['detalhes'] ?? 'Verifique o servidor'), ENT_QUOTES, 'UTF-8') .
            "</div>";
    } else {
        // Sucesso: Redireciona para Produtos Cadastrados
        header("Location: produtos.php?editado=1");
        exit;
    }
}

// Mapeamento de nomes para facilitar o HTML (API pode retornar nome ou nomeProduto)
$nomeVal = $produto['nomeProduto'] ?? $produto['nome'] ?? '';
$codVal = $produto['codProduto'] ?? $produto['cod'] ?? '';
$precoUVal = $produto['precoUnitario'] ?? $produto['preco_unitario'] ?? 0.00;
$precoPVal = $produto['precoPacote'] ?? $produto['preco_pacote'] ?? 0.00;
$categoriaVal = $produto['categoria'] ?? '';
$estoqueVal = $produto['quantidade'] ?? 0;
$dataVal = isset($produto['validade']) ? date('Y-m-d', strtotime($produto['validade'])) : date('Y-m-d');
$imgAtual = toa_product_image($produto['imagem'] ?? null);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tôa Tôa - Editar Produto</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="index.css" rel="stylesheet">
</head>
<body>
    <?php include_once 'navbar.php'; ?>

    <div class="container-fluid main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="text-dark">
                <i class="bi <?= $viewOnly ? 'bi-eye-fill' : 'bi-pencil-square' ?> me-2"></i>
                <?= $viewOnly ? 'Visualizar Produto' : 'Editar Produto' ?> #<?= htmlspecialchars($id) ?>
            </h2>
            <a href="produtos.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar aos Produtos</a>
        </div>
        
        <?= $mensagem ?>

        <form method="POST" enctype="multipart/form-data">
            <?= csrf_input() ?>
            <div class="row g-0 form-card shadow-sm">
                <!-- Seção de Dados -->
                <div class="col-lg-8 p-4 border-end">
                    <h5 class="section-title">Informações Gerais</h5>
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label class="form-label">Código</label>
                            <input type="text" name="codProduto" class="form-control" value="<?= htmlspecialchars($codVal, ENT_QUOTES, 'UTF-8') ?>" <?= $viewOnly ? 'readonly' : '' ?>>
                        </div>
                        <div class="col-md-9">
                            <label class="form-label">Nome do Vestido</label>
                            <input type="text" name="nomeProduto" class="form-control" value="<?= htmlspecialchars($nomeVal) ?>" required <?= $viewOnly ? 'readonly' : '' ?>>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Categoria</label>
                            <select class="form-select" name="categoria" required <?= $viewOnly ? 'disabled' : '' ?>>
                                <option value="Noivas" <?= $categoriaVal == 'Noivas' ? 'selected' : '' ?>>Noivas</option>
                                <option value="Formandas" <?= $categoriaVal == 'Formandas' ? 'selected' : '' ?>>Formandas</option>
                                <option value="Madrinhas" <?= $categoriaVal == 'Madrinhas' ? 'selected' : '' ?>>Madrinhas</option>
                                <option value="Debutantes" <?= $categoriaVal == 'Debutantes' ? 'selected' : '' ?>>Debutantes</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Estoque</label>
                            <input type="number" name="quantidade" class="form-control" value="<?= $estoqueVal ?>" required <?= $viewOnly ? 'readonly' : '' ?>>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Data Aquisição</label>
                            <input type="date" name="validade" class="form-control" value="<?= $dataVal ?>" <?= $viewOnly ? 'readonly' : '' ?>>
                        </div>
                    </div>

                    <h5 class="section-title mt-4">Financeiro</h5>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Preço de Venda (R$)</label>
                            <input type="number" step="0.01" name="precoUnitario" class="form-control form-control-lg" value="<?= $precoUVal ?>" required <?= $viewOnly ? 'readonly' : '' ?>>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-primary fw-bold">Valor do Aluguel (R$)</label>
                            <input type="number" step="0.01" name="precoPacote" class="form-control form-control-lg border-primary" value="<?= $precoPVal ?>" required <?= $viewOnly ? 'readonly' : '' ?>>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Descrição Detalhada</label>
                        <textarea name="descricao" class="form-control" rows="4" <?= $viewOnly ? 'readonly' : '' ?>><?= htmlspecialchars($produto['descricao'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- Seção de Imagem -->
                <div class="col-lg-4 image-panel">
                    <h5 class="section-title">Foto do Produto</h5>
                    <div class="image-preview" id="imagePreviewContainer">
                        <img src="<?= htmlspecialchars($imgAtual, ENT_QUOTES, 'UTF-8') ?>" alt="Foto do produto" id="previewImg" style="display: block;" onerror="this.onerror=null;this.src='toatoa.png'">
                    </div>
                    <?php if (!$viewOnly): ?>
                    <div class="mt-3">
                        <label for="imagemProduto" class="btn btn-upload w-100">
                            <i class="bi bi-camera me-2"></i> Alterar Imagem
                        </label>
                        <input type="file" class="form-control d-none" id="imagemProduto" name="imagemProduto" accept="image/*">
                        <p class="text-muted small mt-2 text-center">Atual: <?= htmlspecialchars($imgAtual) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!$viewOnly): ?>
            <div class="action-buttons d-flex justify-content-between">
                <button type="button" class="btn btn-outline-danger px-4" onclick="confirmarExclusao()">
                    <i class="bi bi-trash me-2"></i> EXCLUIR PRODUTO
                </button>
                <button type="submit" class="btn btn-save-main">SALVAR ALTERAÇÕES</button>
            </div>
            <?php endif; ?>
        </form>
    </div>

    <script>
        // Preview da nova imagem selecionada
        const imagemProduto = document.getElementById('imagemProduto');
        if (imagemProduto) imagemProduto.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('previewImg').src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        });

        // Função para confirmar a exclusão
        function confirmarExclusao() {
            if (confirm("Tem certeza que deseja excluir permanentemente este produto? Esta ação não pode ser desfeita.")) {
                const form = document.querySelector('form[method="POST"]');
                const action = document.createElement('input');
                action.type = 'hidden';
                action.name = 'action';
                action.value = 'excluir';
                form.appendChild(action);
                form.submit();
            }
        }
    </script>
</body>
</html>
