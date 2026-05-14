<?php
include_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Processamento da Imagem (Salva o arquivo físico na pasta imagens/)
    $nomeImagem = 'placeholder.jpg';
    if (isset($_FILES['imagemProduto']) && $_FILES['imagemProduto']['error'] === 0) {
        $extensao = pathinfo($_FILES['imagemProduto']['name'], PATHINFO_EXTENSION);
        $nomeImagem = "produto_" . uniqid() . "." . $extensao;
        
        if (!is_dir('imagens')) mkdir('imagens', 0777, true);
        move_uploaded_file($_FILES['imagemProduto']['tmp_name'], 'imagens/' . $nomeImagem);
    }

    // 2. Mapeia os dados conforme o Node.js espera (Case Sensitive)
    $dadosProduto = [
        "codProduto"    => $_POST['codProduto'] ?? '',
        "nomeProduto"   => $_POST['nomeProduto'] ?? '',
        "categoria"     => $_POST['categoria'] ?? '',
        "validade"      => !empty($_POST['validade']) ? $_POST['validade'] : null,
        "quantidade"    => (int)($_POST['quantidade'] ?? 0),
        "precoUnitario" => (float)($_POST['precoUnitario'] ?? 0),
        "precoPacote"   => (float)($_POST['precoPacote'] ?? 0),
        "descricao"     => $_POST['descricao'] ?? '',
        "imagem"        => $nomeImagem
    ];

    // 3. Envia para a API centralizada no db.php (POST na URL base conforme documentação)
    $res = api_request("POST", "/", $dadosProduto);

    if (isset($res['error'])) {
        echo "<div class='alert alert-danger'><strong>Erro ao salvar:</strong> " . $res['error'] . "<br><strong>Detalhe Técnico:</strong> " . ($res['detalhes'] ?? 'Verifique o console do Node.js') . "</div>";
    } else {
        header("Location: index.php?sucesso=1"); // Redireciona em caso de sucesso
        exit;
    }
} else {
    header("Location: index.php");
}
?>