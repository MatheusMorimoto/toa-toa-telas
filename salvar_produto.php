<?php
include_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

    $res = salvarProduto($dadosProduto);

    if (isset($res['error'])) {
        echo "<div class='alert alert-danger'><strong>Erro ao salvar:</strong> " .
            htmlspecialchars((string)$res['error'], ENT_QUOTES, 'UTF-8') .
            "<br><strong>Detalhe Técnico:</strong> " .
            htmlspecialchars((string)($res['detalhes'] ?? 'Verifique o console do Node.js'), ENT_QUOTES, 'UTF-8') .
            "</div>";
    } else {
        header("Location: index.php?sucesso=1"); // Redireciona em caso de sucesso
        exit;
    }
} else {
    http_response_code(405);
    header('Allow: POST');
    header("Location: index.php");
}
?>
