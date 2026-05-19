<?php
include_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Processamento da Imagem (Salva o arquivo físico na pasta imagens/)
    $nomeImagem = 'placeholder.jpg';
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
            echo "<div class='alert alert-danger'>Erro ao enviar imagem para o Supabase.</div>"; exit;
        }
    }

    // 2. Mapeia os dados seguindo EXATAMENTE o exemplo JSON da sua documentação de API
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