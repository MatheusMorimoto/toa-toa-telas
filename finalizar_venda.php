<?php
include 'db.php';
start_secure_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Metodo nao permitido.');
}

validate_csrf();

$clienteId = filter_var($_POST['cliente_id'] ?? null, FILTER_VALIDATE_INT);
$clienteSessao = $_SESSION['venda_cliente_id'] ?? null;
if (!$clienteId || (string)$clienteId !== (string)$clienteSessao || empty($_SESSION['venda_atual'])) {
    http_response_code(422);
    exit('Venda ou cliente invalido. Retorne ao cadastro do cliente e tente novamente.');
}

$formasPagamento = ['dinheiro', 'pix', 'cartao_credito', 'cartao_debito'];
$formaPagamento = $_POST['forma_pagamento'] ?? '';
$valorCostura = filter_var($_POST['valor_costura'] ?? 0, FILTER_VALIDATE_FLOAT);
$desconto = filter_var($_POST['desconto_valor'] ?? 0, FILTER_VALIDATE_FLOAT);

if (!in_array($formaPagamento, $formasPagamento, true) ||
    $valorCostura === false || $valorCostura < 0 ||
    $desconto === false || $desconto < 0) {
    http_response_code(422);
    exit('Dados financeiros invalidos.');
}

try {
    $dadosVenda = [
        'cliente_id' => $clienteId,
        'forma_pagamento' => $formaPagamento,
        'valor_costura' => (float)$valorCostura,
        'desconto_valor' => (float)$desconto,
        'observacoes' => trim((string)($_POST['observacoes'] ?? '')),
        'itens' => [],
    ];

    $subtotal = 0.0;
    foreach ($_SESSION['venda_atual'] as $item) {
        $produtoId = filter_var($item['id'] ?? null, FILTER_VALIDATE_INT);
        $tipo = $item['tipo'] ?? '';
        if (!$produtoId || !in_array($tipo, ['venda', 'aluguel'], true)) {
            throw new RuntimeException('Existe um item invalido na operacao.');
        }

        $produto = obterProdutoPorId($produtoId);
        if (!$produto || isset($produto['error'])) {
            throw new RuntimeException("Nao foi possivel validar o produto #$produtoId.");
        }

        $quantidade = (int)($produto['quantidade'] ?? 0);
        if ($tipo === 'venda' && $quantidade < 1) {
            throw new RuntimeException("O produto #$produtoId nao possui estoque disponivel.");
        }

        $preco = $tipo === 'venda'
            ? ($produto['precoUnitario'] ?? $produto['preco_unitario'] ?? null)
            : ($produto['precoPacote'] ?? $produto['preco_pacote'] ?? null);
        if (!is_numeric($preco) || (float)$preco < 0) {
            throw new RuntimeException("O produto #$produtoId possui preco invalido.");
        }

        $preco = (float)$preco;
        $subtotal += $preco;
        $dadosVenda['itens'][] = [
            'produto_id' => $produtoId,
            'tipo' => $tipo,
            'preco' => $preco,
        ];
    }

    if ($desconto > $subtotal + $valorCostura) {
        throw new RuntimeException('O desconto nao pode ser maior que o total da operacao.');
    }

    $res = registrarOperacaoCompleta($dadosVenda);
    if (isset($res['error'])) {
        throw new RuntimeException((string)($res['detalhes'] ?? $res['error']));
    }

    $vendaId = filter_var($res['venda_id'] ?? $res['id'] ?? null, FILTER_VALIDATE_INT);
    unset($_SESSION['venda_atual'], $_SESSION['venda_cliente_id']);
    $redirect = 'clientes_cadastrados.php?venda_sucesso=1';
    if ($vendaId) {
        $redirect .= '&venda_id=' . rawurlencode((string)$vendaId);
    }
    header('Location: ' . $redirect);
    exit;
} catch (Throwable $e) {
    error_log('Erro ao finalizar venda: ' . $e->getMessage());
    http_response_code(422);
    echo "<div style='color:red; padding:20px;'>Erro ao finalizar venda: " .
        htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') .
        '</div>';
}
