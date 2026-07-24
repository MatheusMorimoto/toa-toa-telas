<?php
include "db.php";
start_secure_session();

// 1. Validação do Cliente
$cliente_id = $_GET['cliente_id'] ?? null;
if (!$cliente_id) {
    header("Location: clientes_cadastrados.php");
    exit;
}

$cliente = buscarCliente($cliente_id);
if (!$cliente || isset($cliente['error'])) {
    die("Erro ao carregar cliente.");
}

// 2. Lógica do Carrinho (em memória/sessão)
if (!isset($_SESSION['venda_cliente_id']) || (string)$_SESSION['venda_cliente_id'] !== (string)$cliente_id) {
    $_SESSION['venda_atual'] = [];
    $_SESSION['venda_cliente_id'] = (string)$cliente_id;
}

// Ação: Adicionar Item
if (isset($_POST['add_item'])) {
    validate_csrf();
    $prod_id = filter_var($_POST['produto_id'] ?? null, FILTER_VALIDATE_INT);
    $tipo = $_POST['tipo'] ?? '';
    if (!$prod_id || !in_array($tipo, ['venda', 'aluguel'], true)) {
        http_response_code(422);
        exit('Produto ou tipo de operação inválido.');
    }
    $prod_data = obterProdutoPorId($prod_id);
    
    if ($prod_data && !isset($prod_data['error'])) {
        $preco = ($tipo === 'venda') ? ($prod_data['preco_unitario'] ?? $prod_data['precoUnitario']) : ($prod_data['preco_pacote'] ?? $prod_data['precoPacote']);
        
        $_SESSION['venda_atual'][] = [
            'id' => $prod_id,
            'nome' => $prod_data['nome'] ?? $prod_data['nomeProduto'],
            'tipo' => $tipo,
            'preco' => $preco,
            'temp_id' => uniqid()
        ];
    }
}

// Ação: Remover Item
if (isset($_POST['remover'])) {
    validate_csrf();
    $temp_id = (string)$_POST['remover'];
    foreach ($_SESSION['venda_atual'] as $key => $item) {
        if ($item['temp_id'] == $temp_id) {
            unset($_SESSION['venda_atual'][$key]);
            break;
        }
    }
    header("Location: venda.php?cliente_id=$cliente_id");
    exit;
}

// 3. Pesquisa de Produtos
$busca = $_GET['busca_prod'] ?? '';
if (!empty($busca)) {
    $produtos_busca = buscarProduto($busca);
} else {
    $produtos_busca = listarProdutos();
}

// Cálculo dos subtotais para exibição no rodapé
$subtotal_venda = 0;
$subtotal_aluguel = 0;
foreach ($_SESSION['venda_atual'] as $item) {
    if ($item['tipo'] === 'venda') {
        $subtotal_venda += $item['preco'];
    } else {
        $subtotal_aluguel += $item['preco'];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Tôa Tôa - Realizar Venda/Aluguel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="index.css" rel="stylesheet">
    <style>
        .client-info-card { background: #e9ecef; border-left: 5px solid #001D3D; }
        .product-res-scroll { max-height: 400px; overflow-y: auto; }
        .badge-venda { background-color: #28a745; }
        .badge-aluguel { background-color: #fd7e14; }
    </style>
</head>
<body>
    <?php include_once 'navbar.php'; ?>

    <div class="container-fluid main-content mt-4">
        <div class="row">
            <!-- Coluna da Esquerda: Cliente e Carrinho -->
            <div class="col-lg-7">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0"><i class="bi bi-person-check me-2"></i>Cliente Selecionado</h5>
                    </div>
                    <div class="card-body client-info-card">
                        <div class="row g-2 small">
                            <div class="col-md-6"><strong>Nome Completo:</strong> <?= htmlspecialchars($cliente['nome_completo'] ?? 'N/A') ?></div>
                            <div class="col-md-3"><strong>CPF:</strong> <?= htmlspecialchars($cliente['cpf'] ?? 'N/A') ?></div>
                            <div class="col-md-3"><strong>RG:</strong> <?= htmlspecialchars($cliente['rg'] ?? 'N/A') ?></div>
                            
                            <div class="col-md-3"><strong>WhatsApp (Principal):</strong> <?= htmlspecialchars($cliente['whatsapp'] ?? 'N/A') ?></div>
                            <div class="col-md-3"><strong>Tipo:</strong> <?= htmlspecialchars($cliente['tipo_contato_1'] ?? 'N/A') ?></div>
                            <div class="col-md-3"><strong>Telefone Secundário:</strong> <?= htmlspecialchars($cliente['telefone_secundario'] ?? 'N/A') ?></div>
                            <div class="col-md-3"><strong>Parentesco/Tipo:</strong> <?= htmlspecialchars($cliente['tipo_contato_2'] ?? 'N/A') ?></div>
                            
                            <div class="col-md-6"><strong>E-mail:</strong> <?= htmlspecialchars($cliente['email'] ?? 'N/A') ?></div>
                            <div class="col-md-3"><strong>Nascimento:</strong> <?= !empty($cliente['data_evento']) ? date('d/m/Y', strtotime($cliente['data_evento'])) : 'N/A' ?></div>
                            <div class="col-md-3"><strong>CEP:</strong> <?= htmlspecialchars($cliente['cep'] ?? 'N/A') ?></div>

                            <div class="col-md-5"><strong>Rua / Logradouro:</strong> <?= htmlspecialchars($cliente['rua'] ?? 'N/A') ?></div>
                            <div class="col-md-1"><strong>Nº:</strong> <?= htmlspecialchars($cliente['numero'] ?? 'N/A') ?></div>
                            <div class="col-md-3"><strong>Bairro:</strong> <?= htmlspecialchars($cliente['bairro'] ?? 'N/A') ?></div>
                            <div class="col-md-3"><strong>Cidade:</strong> <?= htmlspecialchars($cliente['cidade'] ?? 'N/A') ?></div>
                            
                            <div class="col-12"><strong>Complemento / Referência:</strong> <?= htmlspecialchars($cliente['complemento'] ?? 'N/A') ?></div>
                            
                            <div class="col-12 mt-2 pt-2 border-top">
                                <strong>Preferências / Observações:</strong> 
                                <span class="text-muted"><?= htmlspecialchars($cliente['preferencias'] ?? 'Nenhuma observação registrada.') ?></span>
                            </div>
                        </div>
                        <div class="mt-3 d-flex justify-content-end gap-2">
                            <a href="clientes_cadastrados.php" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-people me-1"></i> Trocar Cliente
                            </a>
                            <a href="cadastro_cliente.php?id=<?= $cliente['id'] ?>&view=1" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye me-1"></i> Ver Cadastro Completo
                            </a>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-cart3 me-2"></i>Itens da Venda / Aluguel</h5>
                        <span class="badge bg-primary fs-6">Total: R$ <?= number_format(array_sum(array_column($_SESSION['venda_atual'], 'preco')), 2, ',', '.') ?></span>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Produto</th>
                                    <th>Tipo</th>
                                    <th class="text-end">Valor</th>
                                    <th class="text-center">Ação</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($_SESSION['venda_atual'])): ?>
                                    <tr><td colspan="4" class="text-center p-4 text-muted">Nenhum item adicionado</td></tr>
                                <?php else: ?>
                                    <?php foreach ($_SESSION['venda_atual'] as $item): ?>
                                        <tr>
                                            <td class="ps-3"><?= htmlspecialchars($item['nome']) ?></td>
                                            <td>
                                                <span class="badge <?= $item['tipo'] == 'venda' ? 'badge-venda' : 'badge-aluguel' ?>">
                                                    <?= strtoupper($item['tipo']) ?>
                                                </span>
                                            </td>
                                            <td class="text-end">R$ <?= number_format($item['preco'], 2, ',', '.') ?></td>
                                            <td class="text-center">
                                                <form method="POST">
                                                    <?= csrf_input() ?>
                                                    <input type="hidden" name="remover" value="<?= htmlspecialchars($item['temp_id'], ENT_QUOTES, 'UTF-8') ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer p-4">
                        <form action="finalizar_venda.php" method="POST">
                            <?= csrf_input() ?>
                            <input type="hidden" name="cliente_id" value="<?= $cliente_id ?>">
                            <div class="mb-4">
                                <label class="form-label fw-bold"><i class="bi bi-chat-left-text me-2"></i>Observações:</label>
                                <textarea name="observacoes" class="form-control" rows="2" placeholder="Descreva aqui detalhes dos ajustes, detalhes realizados ou observações gerais..."></textarea>
                            </div>
                            <div class="row align-items-start">
                                <div class="col-md-6 border-end">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold"><i class="bi bi-cash-stack me-2"></i>Forma de Pagamento:</label>
                                        <select name="forma_pagamento" class="form-select shadow-sm" required>
                                            <option value="">Selecione...</option>
                                            <option value="dinheiro">Dinheiro</option>
                                            <option value="pix">PIX</option>
                                            <option value="cartao_credito">Cartão de Crédito</option>
                                            <option value="cartao_debito">Cartão de Debito</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold"><i class="bi bi-scissors me-2"></i>Valor da Costura (R$):</label>
                                        <input type="number" step="0.01" name="valor_costura" id="valor_costura" class="form-control" value="0.00">
                                    </div>
                                </div>
                                <div class="col-md-6 text-end">
                                    <div class="mb-2">
                                        <span class="text-muted">Subtotal Vendas:</span>
                                        <strong class="ms-2">R$ <?= number_format($subtotal_venda, 2, ',', '.') ?></strong>
                                    </div>
                                    <div class="mb-2">
                                        <span class="text-muted">Subtotal Alugueis:</span>
                                        <strong class="ms-2">R$ <?= number_format($subtotal_aluguel, 2, ',', '.') ?></strong>
                                    </div>

                                    <!-- Campos de Desconto Solicitados -->
                                    <div class="d-flex justify-content-end align-items-center mb-2 gap-2">
                                        <span class="text-muted">Desconto:</span>
                                        <div class="input-group input-group-sm" style="width: 130px;">
                                            <span class="input-group-text">R$</span>
                                            <input type="number" step="0.01" name="desconto_valor" id="desconto_valor" class="form-control" value="0.00">
                                        </div>
                                        <span class="text-muted">ou</span>
                                        <div class="input-group input-group-sm" style="width: 100px;">
                                            <input type="number" step="0.1" name="desconto_porcentagem" id="desconto_porcentagem" class="form-control" value="0.0">
                                            <span class="input-group-text">%</span>
                                        </div>
                                    </div>

                                    <div class="mb-3 border-top pt-2">
                                        <span class="fs-5 fw-bold">TOTAL GERAL:</span>
                                        <span class="fs-5 fw-bold text-primary ms-2" id="display_total_geral">R$ <?= number_format($subtotal_venda + $subtotal_aluguel, 2, ',', '.') ?></span>
                                    </div>
                                    <button type="submit" class="btn btn-success btn-lg px-5 fw-bold shadow-sm" <?= empty($_SESSION['venda_atual']) ? 'disabled' : '' ?>>
                                        <i class="bi bi-check2-circle me-2"></i> FINALIZAR OPERAÇÃO
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Coluna da Direita: Busca de Produtos -->
            <div class="col-lg-5">
                <div class="card shadow-sm">
                    <div class="card-header bg-navy text-white" style="background-color: #001D3D;">
                        <h5 class="mb-0 text-white"><i class="bi bi-search me-2"></i>Pesquisar Produtos</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="mb-3">
                            <input type="hidden" name="cliente_id" value="<?= $cliente_id ?>">
                            <div class="input-group">
                                <input type="text" name="busca_prod" class="form-control" placeholder="Nome ou código do vestido..." value="<?= htmlspecialchars($busca) ?>">
                                <button class="btn btn-primary" type="submit">Buscar</button>
                            </div>
                        </form>

                        <div class="product-res-scroll">
                            <?php if (isset($produtos_busca['error'])): ?>
                                <div class="alert alert-danger">Erro ao carregar: <?= htmlspecialchars($produtos_busca['detalhes']) ?></div>
                            <?php elseif (empty($produtos_busca)): ?>
                                <div class="alert alert-info">Nenhum produto encontrado.</div>
                            <?php else: ?>
                                <?php foreach ($produtos_busca as $p): 
                                    $pid = $p['id'];
                                    $pnome = $p['nomeProduto'] ?? $p['nome'];
                                    $pVenda = $p['precoUnitario'] ?? $p['preco_unitario'];
                                    $pAluguel = $p['precoPacote'] ?? $p['preco_pacote'];
                                ?>
                                    <div class="border rounded p-3 mb-2 bg-light">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1"><?= htmlspecialchars($pnome) ?></h6>
                                                <small class="text-muted">Cód: #<?= $pid ?> | Estoque: <?= $p['quantidade'] ?></small>
                                            </div>
                                            <span class="badge bg-dark">R$ <?= number_format($pVenda, 2, ',', '.') ?></span>
                                        </div>
                                        <div class="mt-3 d-flex gap-2">
                                            <form method="POST" class="flex-grow-1 text-center">
                                                <?= csrf_input() ?>
                                                <div class="small fw-bold mb-1 text-success">R$ <?= number_format($pVenda, 2, ',', '.') ?></div>
                                                <input type="hidden" name="produto_id" value="<?= $pid ?>">
                                                <input type="hidden" name="tipo" value="venda">
                                                <button type="submit" name="add_item" class="btn btn-sm btn-success w-100">
                                                    <i class="bi bi-bag-check me-1"></i> + Venda
                                                </button>
                                            </form>
                                            <form method="POST" class="flex-grow-1 text-center">
                                                <?= csrf_input() ?>
                                                <div class="small fw-bold mb-1 text-warning-emphasis">R$ <?= number_format($pAluguel, 2, ',', '.') ?></div>
                                                <input type="hidden" name="produto_id" value="<?= $pid ?>">
                                                <input type="hidden" name="tipo" value="aluguel">
                                                <button type="submit" name="add_item" class="btn btn-sm btn-warning w-100">
                                                    <i class="bi bi-calendar-event me-1"></i> + Aluguel
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const totalBase = <?= ($subtotal_venda + $subtotal_aluguel) ?>;
        const inputDescValor = document.getElementById('desconto_valor');
        const inputDescPorc = document.getElementById('desconto_porcentagem');
        const inputCostura = document.getElementById('valor_costura');
        const displayTotal = document.getElementById('display_total_geral');

        function atualizarTotal() {
            const descValor = parseFloat(inputDescValor.value) || 0;
            const valorCostura = parseFloat(inputCostura.value) || 0;
            
            const totalFinal = totalBase - descValor + valorCostura;
            
            displayTotal.innerText = totalFinal.toLocaleString('pt-BR', {
                style: 'currency',
                currency: 'BRL'
            });
        }

        // Ao alterar o Valor em R$, calcula a %
        inputDescValor.addEventListener('input', () => {
            if (totalBase > 0) {
                const valor = parseFloat(inputDescValor.value) || 0;
                inputDescPorc.value = ((valor / totalBase) * 100).toFixed(1);
            }
            atualizarTotal();
        });

        // Ao alterar a %, calcula o Valor em R$
        inputDescPorc.addEventListener('input', () => {
            if (totalBase > 0) {
                const porc = parseFloat(inputDescPorc.value) || 0;
                inputDescValor.value = ((porc / 100) * totalBase).toFixed(2);
            }
            atualizarTotal();
        });

        // Ao alterar a costura, apenas atualiza o total
        inputCostura.addEventListener('input', atualizarTotal);
    </script>
</body>
</html>
