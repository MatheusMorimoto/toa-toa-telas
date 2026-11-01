<?php
include_once 'db.php';

$mes = (string)($_GET['mes'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $mes)) $mes = date('Y-m');
$vendas = array_values(array_filter(listarVendasLocais(), static function ($venda) use ($mes) {
    return str_starts_with((string)($venda['realizada_em'] ?? ''), $mes);
}));
usort($vendas, static fn($a, $b) => strcmp((string)($b['realizada_em'] ?? ''), (string)($a['realizada_em'] ?? '')));
$totalMes = array_sum(array_map(static fn($v) => (float)($v['valor_final'] ?? 0), $vendas));
$rotulosPagamento = ['dinheiro' => 'Dinheiro', 'pix' => 'PIX', 'cartao_credito' => 'Cartão de crédito', 'cartao_debito' => 'Cartão de débito'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tôa Tôa - Vendas Realizadas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="index.css" rel="stylesheet">
</head>
<body>
<?php include_once 'navbar.php'; ?>
<main class="container-fluid main-content mt-4 px-4">
    <?php if (isset($_GET['venda_sucesso'])): ?><div class="alert alert-success">Venda ou aluguel registrado com sucesso.</div><?php endif; ?>
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
        <div><h2 class="mb-1">Vendas realizadas</h2><p class="text-muted mb-0">Histórico completo do mês selecionado.</p></div>
        <form method="GET"><label class="form-label fw-bold">Mês</label><input type="month" name="mes" value="<?= htmlspecialchars($mes) ?>" class="form-control" onchange="this.form.submit()"></form>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="card shadow-sm"><div class="card-body"><div class="text-muted">Operações no mês</div><div class="display-6 fw-bold"><?= count($vendas) ?></div></div></div></div>
        <div class="col-md-4"><div class="card shadow-sm border-success"><div class="card-body"><div class="text-muted">Total líquido</div><div class="display-6 fw-bold text-success">R$ <?= number_format($totalMes, 2, ',', '.') ?></div></div></div></div>
    </div>
    <div class="card shadow-sm"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead class="table-dark"><tr><th>Data</th><th>Cliente</th><th>Itens</th><th>Retirada / devolução</th><th>Pagamento</th><th>Desconto</th><th class="text-end">Valor final</th></tr></thead>
        <tbody>
        <?php if (empty($vendas)): ?><tr><td colspan="7" class="text-center text-muted py-5">Nenhuma venda registrada neste mês.</td></tr><?php endif; ?>
        <?php foreach ($vendas as $venda): ?>
            <tr>
                <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$venda['realizada_em']))) ?></td>
                <td><strong><?= htmlspecialchars($venda['cliente_nome'] ?? 'Cliente') ?></strong><br><small><?= htmlspecialchars($venda['cliente_cpf'] ?? '') ?></small></td>
                <td><?php foreach (($venda['itens'] ?? []) as $item): ?><div><span class="badge <?= ($item['tipo'] ?? '') === 'aluguel' ? 'bg-warning text-dark' : 'bg-success' ?>"><?= htmlspecialchars(strtoupper((string)($item['tipo'] ?? ''))) ?></span> <?= htmlspecialchars($item['produto_nome'] ?? ('Produto #' . ($item['produto_id'] ?? ''))) ?> — R$ <?= number_format((float)($item['preco'] ?? 0), 2, ',', '.') ?></div><?php endforeach; ?></td>
                <td><?= !empty($venda['data_retirada']) ? htmlspecialchars(date('d/m/Y', strtotime($venda['data_retirada']))) . '<br>' . htmlspecialchars(date('d/m/Y', strtotime($venda['data_devolucao']))) : '—' ?></td>
                <td><?= htmlspecialchars($rotulosPagamento[$venda['forma_pagamento'] ?? ''] ?? ($venda['forma_pagamento'] ?? '')) ?><?php if (!empty($venda['bandeira_cartao'])): ?><br><small><?= htmlspecialchars(strtoupper($venda['bandeira_cartao'])) ?></small><?php endif; ?></td>
                <td>R$ <?= number_format((float)($venda['desconto_valor'] ?? 0), 2, ',', '.') ?></td>
                <td class="text-end fw-bold text-success">R$ <?= number_format((float)($venda['valor_final'] ?? 0), 2, ',', '.') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div></div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
