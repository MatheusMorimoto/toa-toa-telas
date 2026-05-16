<?php
session_start();
include 'db.php';

if (empty($_SESSION['carrinho'])) {
    header("Location: pdv.php");
    exit();
}

// 2. Iniciar Transação
$conn->begin_transaction();

try {
    // 1. Calcular o total geral e validar se todos os produtos existem
    $totalGeral = 0;
    $stmtCalc = $conn->prepare("SELECT preco_unitario FROM produtos WHERE id = ?");
    foreach ($_SESSION['carrinho'] as $id => $quantidade) {
        // Pula se o ID for vazio ou inválido
        if (empty($id)) {
            unset($_SESSION['carrinho'][$id]);
            continue;
        }

        $stmtCalc->bind_param("i", $id);
        $stmtCalc->execute();
        $res = $stmtCalc->get_result()->fetch_assoc();
        if (!$res) {
            throw new Exception("Produto ID $id não encontrado no sistema.");
        }
        $totalGeral += $res['preco_unitario'] * $quantidade;
    }
    $stmtCalc->close();

    // 3. Inserir a venda principal
    $stmtVenda = $conn->prepare("INSERT INTO vendas (total) VALUES (?)");
    $stmtVenda->bind_param("d", $totalGeral);
    $stmtVenda->execute();
    $venda_id = $conn->insert_id;
    $stmtVenda->close();

    // 4. Preparar statements para os itens e estoque (fora do loop por performance)
    $stmtPreco = $conn->prepare("SELECT preco_unitario FROM produtos WHERE id = ?");
    $stmtItem = $conn->prepare("INSERT INTO itens_venda (venda_id, produto_id, quantidade, preco_unitario) VALUES (?, ?, ?, ?)");
    $stmtEstoque = $conn->prepare("UPDATE produtos SET quantidade = quantidade - ? WHERE id = ?");

    foreach ($_SESSION['carrinho'] as $id => $quantidade) {
        // Pula se o ID for vazio (segunda verificação por segurança)
        if (empty($id)) {
            continue;
        }

        // 4.1 Buscar preço atual para registro histórico (evita problemas se o preço mudar no futuro)
        $stmtPreco->bind_param("i", $id);
        $stmtPreco->execute();
        $p = $stmtPreco->get_result()->fetch_assoc();
        
        if (!$p) {
            throw new Exception("Erro de integridade: Produto ID $id sumiu durante o processo.");
        }

        $preco_historico = $p['preco_unitario'];

        // 4.2 Gravar item da venda
        $stmtItem->bind_param("iiid", $venda_id, $id, $quantidade, $preco_historico);
        $stmtItem->execute();

        // 4.3 Baixar estoque
        $stmtEstoque->bind_param("ii", $quantidade, $id);
        $stmtEstoque->execute();
    }

    $stmtPreco->close();
    $stmtItem->close();
    $stmtEstoque->close();

    $conn->commit();
    unset($_SESSION['carrinho']); // Limpa o carrinho
    header("Location: pdv.php?venda_sucesso=1");
} catch (Exception $e) {
    $conn->rollback();
    echo "Erro ao finalizar venda: " . $e->getMessage();
}