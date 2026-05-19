<?php
include_once 'db.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: pdv.php");
    exit;
}

// 1. Busca os dados atuais do produto para preencher o formulário
$produto = obterProdutoPorId($id);

if (!$produto || isset($produto['error'])) {
    die("<div class='alert alert-danger'>Erro ao carregar produto: " . $produto['detalhes'] . "</div>");
}

$mensagem = "";

// NOVO: Lógica para processar a exclusão do produto
if (isset($_GET['action']) && $_GET['action'] === 'excluir') {
    $res = excluirProduto($id);
    if (isset($res['error'])) {
        $mensagem = "<div class='alert alert-danger'><strong>Erro ao excluir:</strong> " . ($res['detalhes'] ?? 'Erro na API') . "</div>";
    } else {
        header("Location: pdv.php?excluido=1");
        exit;
    }
}

// 2. Processa a atualização quando o formulário é enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Processamento da Imagem (Copiado do salvar_produto.php)
    // Mantém a imagem atual como padrão caso não seja enviada uma nova
    $nomeImagem = $produto['imagem'] ?? 'placeholder.jpg';

    if (isset($_FILES['imagemProduto']) && $_FILES['imagemProduto']['error'] === 0) {
        $extensao = strtolower(pathinfo($_FILES['imagemProduto']['name'], PATHINFO_EXTENSION));
        $permitidos = ['jpg', 'jpeg', 'png', 'webp'];
        
        if (!in_array($extensao, $permitidos)) {
            echo "<div class='alert alert-danger text-center'><strong>Erro:</strong> Apenas imagens (JPG, PNG, WEBP) são permitidas.</div>";
            exit;
        }

        $nomeImagem = "produtos/" . time() . "_vestido." . $extensao;
        
        // Envia direto para o Supabase Bucket
        if (!uploadImagemSupabase($_FILES['imagemProduto']['tmp_name'], $nomeImagem)) {
            echo "<div class='alert alert-danger'>Erro ao enviar nova imagem para o Supabase.</div>"; exit;
        }
    }

    // 2. Mapeia os dados seguindo EXATAMENTE o exemplo do salvar_produto.php
    $dadosProduto = [
        "nomeProduto"   => $_POST['nomeProduto'] ?? '',
        "categoria"     => $_POST['categoria'] ?? '',
        "validade"      => (!empty($_POST['validade'])) ? $_POST['validade'] : null,
        "quantidade"    => isset($_POST['quantidade']) ? (int)$_POST['quantidade'] : 0,
        "precoUnitario" => isset($_POST['precoUnitario']) ? (float)$_POST['precoUnitario'] : 0.0,
        "precoPacote"   => isset($_POST['precoPacote']) ? (float)$_POST['precoPacote'] : 0.0,
        "descricao"     => $_POST['descricao'] ?? '',
        "imagem"        => $nomeImagem
    ];

    // 3. Envia para a API (Usando PUT conforme configurado no db.php corrigido)
    $res = editarProduto($id, $dadosProduto);

    if (isset($res['error'])) {
        // Tratamento de erro idêntico ao salvar_produto.php
        echo "<div class='alert alert-danger'><strong>Erro ao salvar:</strong> " . $res['error'] . "<br><strong>Detalhe Técnico:</strong> " . ($res['detalhes'] ?? 'Verifique o servidor') . "</div>";
    } else {
        // Sucesso: Redireciona para o PDV
        header("Location: pdv.php?edit_sucesso=1");
        exit;
    }
}

// Mapeamento de nomes para facilitar o HTML (API pode retornar nome ou nomeProduto)
$nomeVal = $produto['nomeProduto'] ?? $produto['nome'] ?? '';
$precoUVal = $produto['precoUnitario'] ?? $produto['preco_unitario'] ?? 0.00;
$precoPVal = $produto['precoPacote'] ?? $produto['preco_pacote'] ?? 0.00;
$categoriaVal = $produto['categoria'] ?? '';
$estoqueVal = $produto['quantidade'] ?? 0;
$dataVal = isset($produto['validade']) ? date('Y-m-d', strtotime($produto['validade'])) : date('Y-m-d');
$imgAtual = !empty($produto['imagem']) ? $produto['imagem'] : 'placeholder.jpg';
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
            <h2 class="text-dark"><i class="bi bi-pencil-square me-2"></i>Editar Produto #<?= htmlspecialchars($id) ?></h2>
            <a href="pdv.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar ao PDV</a>
        </div>
        
        <?= $mensagem ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="row g-0 form-card shadow-sm">
                <!-- Seção de Dados -->
                <div class="col-lg-8 p-4 border-end">
                    <h5 class="section-title">Informações Gerais</h5>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label class="form-label">Nome do Vestido</label>
                            <input type="text" name="nomeProduto" class="form-control" value="<?= htmlspecialchars($nomeVal) ?>" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Categoria</label>
                            <select class="form-select" name="categoria" required>
                                <option value="Noivas" <?= $categoriaVal == 'Noivas' ? 'selected' : '' ?>>Noivas</option>
                                <option value="Formandas" <?= $categoriaVal == 'Formandas' ? 'selected' : '' ?>>Formandas</option>
                                <option value="Madrinhas" <?= $categoriaVal == 'Madrinhas' ? 'selected' : '' ?>>Madrinhas</option>
                                <option value="Debutantes" <?= $categoriaVal == 'Debutantes' ? 'selected' : '' ?>>Debutantes</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Estoque</label>
                            <input type="number" name="quantidade" class="form-control" value="<?= $estoqueVal ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Data Aquisição</label>
                            <input type="date" name="validade" class="form-control" value="<?= $dataVal ?>">
                        </div>
                    </div>

                    <h5 class="section-title mt-4">Financeiro</h5>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Preço de Venda (R$)</label>
                            <input type="number" step="0.01" name="precoUnitario" class="form-control form-control-lg" value="<?= $precoUVal ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-primary fw-bold">Valor do Aluguel (R$)</label>
                            <input type="number" step="0.01" name="precoPacote" class="form-control form-control-lg border-primary" value="<?= $precoPVal ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Descrição Detalhada</label>
                        <textarea name="descricao" class="form-control" rows="4"><?= htmlspecialchars($produto['descricao'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- Seção de Imagem -->
                <div class="col-lg-4 image-panel">
                    <h5 class="section-title">Foto do Produto</h5>
                    <div class="image-preview" id="imagePreviewContainer">
                        <?php $urlImg = "https://idxyfkeodaettqbjuiak.supabase.co/storage/v1/object/public/toa-toa-moda-festa/" . $imgAtual; ?>
                        <img src="<?= htmlspecialchars($urlImg) ?>" alt="Preview" id="previewImg" style="display: block;" onerror="this.src='toatoa.png'">
                    </div>
                    <div class="mt-3">
                        <label for="imagemProduto" class="btn btn-upload w-100">
                            <i class="bi bi-camera me-2"></i> Alterar Imagem
                        </label>
                        <input type="file" class="form-control d-none" id="imagemProduto" name="imagemProduto" accept="image/*">
                        <p class="text-muted small mt-2 text-center">Atual: <?= htmlspecialchars($imgAtual) ?></p>
                    </div>
                </div>
            </div>

            <div class="action-buttons d-flex justify-content-between">
                <button type="button" class="btn btn-outline-danger px-4" onclick="confirmarExclusao()">
                    <i class="bi bi-trash me-2"></i> EXCLUIR PRODUTO
                </button>
                <button type="submit" class="btn btn-save-main">SALVAR ALTERAÇÕES</button>
            </div>
        </form>
    </div>

    <script>
        // Preview da nova imagem selecionada
        document.getElementById('imagemProduto').addEventListener('change', function() {
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
                window.location.href = "editar_produto.php?id=<?= $id ?>&action=excluir";
            }
        }
    </script>
</body>
</html>