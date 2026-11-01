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
$bandeiraCartao = trim((string)($_POST['bandeira_cartao'] ?? ''));
$dataRetirada = trim((string)($_POST['data_retirada'] ?? ''));
$dataDevolucao = trim((string)($_POST['data_devolucao'] ?? ''));
$valorCostura = filter_var($_POST['valor_costura'] ?? 0, FILTER_VALIDATE_FLOAT);
$desconto = filter_var($_POST['desconto_valor'] ?? 0, FILTER_VALIDATE_FLOAT);

if (!in_array($formaPagamento, $formasPagamento, true) ||
    $valorCostura === false || $valorCostura < 0 ||
    $desconto === false || $desconto < 0) {
    http_response_code(422);
    exit('Dados financeiros invalidos.');
}
$formasCartao = ['cartao_credito', 'cartao_debito'];
$bandeiras = ['visa', 'mastercard', 'elo', 'american_express', 'hipercard', 'outra'];
if (in_array($formaPagamento, $formasCartao, true) && !in_array($bandeiraCartao, $bandeiras, true)) {
    http_response_code(422);
    exit('Selecione a bandeira do cartão.');
}

try {
    $dadosVenda = [
        'cliente_id' => $clienteId,
        'forma_pagamento' => $formaPagamento,
        'bandeira_cartao' => $bandeiraCartao !== '' ? $bandeiraCartao : null,
        'data_retirada' => $dataRetirada !== '' ? $dataRetirada : null,
        'data_devolucao' => $dataDevolucao !== '' ? $dataDevolucao : null,
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
            'produto_nome' => (string)($produto['nomeProduto'] ?? $produto['nome'] ?? ('Produto #' . $produtoId)),
            'tipo' => $tipo,
            'preco' => $preco,
        ];
    }

    $possuiAluguel = count(array_filter($dadosVenda['itens'], static fn($item) => $item['tipo'] === 'aluguel')) > 0;
    if ($possuiAluguel) {
        $retirada = DateTimeImmutable::createFromFormat('!Y-m-d', $dataRetirada);
        $devolucao = DateTimeImmutable::createFromFormat('!Y-m-d', $dataDevolucao);
        if (!$retirada || !$devolucao || $devolucao < $retirada) {
            throw new RuntimeException('Informe datas válidas de retirada e devolução.');
        }
    } else {
        $dadosVenda['data_retirada'] = null;
        $dadosVenda['data_devolucao'] = null;
    }

    if ($desconto > $subtotal + $valorCostura) {
        throw new RuntimeException('O desconto nao pode ser maior que o total da operacao.');
    }

    $res = registrarOperacaoCompleta($dadosVenda);
    if (isset($res['error'])) {
        throw new RuntimeException((string)($res['detalhes'] ?? $res['error']));
    }

    $vendaId = filter_var($res['venda_id'] ?? $res['id'] ?? null, FILTER_VALIDATE_INT);
    $cliente = buscarCliente($clienteId);
    $dadosVenda['venda_id'] = $vendaId ?: null;
    $dadosVenda['cliente_nome'] = (string)($cliente['nome_completo'] ?? ('Cliente #' . $clienteId));
    $dadosVenda['cliente_cpf'] = (string)($cliente['cpf'] ?? '');
    $dadosVenda['cliente_detalhes'] = [
        'nome_completo' => (string)($cliente['nome_completo'] ?? ''),
        'cpf' => (string)($cliente['cpf'] ?? ''),
        'rg' => (string)($cliente['rg'] ?? ''),
        'whatsapp' => (string)($cliente['whatsapp'] ?? ''),
        'telefone_secundario' => (string)($cliente['telefone_secundario'] ?? ''),
        'email' => (string)($cliente['email'] ?? ''),
        'data_evento' => (string)($cliente['data_evento'] ?? ''),
        'cep' => (string)($cliente['cep'] ?? ''),
        'rua' => (string)($cliente['rua'] ?? ''),
        'numero' => (string)($cliente['numero'] ?? ''),
        'bairro' => (string)($cliente['bairro'] ?? ''),
        'cidade' => (string)($cliente['cidade'] ?? ''),
        'complemento' => (string)($cliente['complemento'] ?? ''),
        'preferencias' => (string)($cliente['preferencias'] ?? ''),
    ];
    $dadosVenda['subtotal'] = $subtotal;
    $dadosVenda['valor_final'] = max(0, $subtotal + (float)$valorCostura - (float)$desconto);
    registrarVendaLocal($dadosVenda);
    unset($_SESSION['venda_atual'], $_SESSION['venda_cliente_id']);
    $redirect = 'vendas_realizadas.php?venda_sucesso=1';
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
