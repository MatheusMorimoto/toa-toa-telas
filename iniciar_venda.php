<?php
include_once 'db.php';

$busca = trim((string)($_GET['busca_cliente'] ?? ''));
$clientes = $busca !== '' ? pesquisarClientes($busca) : [];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tôa Tôa - Iniciar Venda</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="index.css" rel="stylesheet">
</head>
<body>
<?php include_once 'navbar.php'; ?>
<main class="container main-content mt-4">
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white"><h4 class="mb-0">Iniciar venda ou aluguel</h4></div>
        <div class="card-body">
            <p class="text-muted">Digite o CPF ou o nome do cliente para carregar o cadastro completo.</p>
            <form method="GET" class="row g-2 mb-4">
                <div class="col-md-9"><input class="form-control form-control-lg" name="busca_cliente" value="<?= htmlspecialchars($busca) ?>" placeholder="CPF ou nome do cliente" required autofocus></div>
                <div class="col-md-3"><button class="btn btn-warning btn-lg w-100"><i class="bi bi-search me-2"></i>Buscar cliente</button></div>
            </form>
            <?php if ($busca !== ''): ?>
                <?php if (isset($clientes['error'])): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($clientes['detalhes'] ?? 'Não foi possível pesquisar clientes.') ?></div>
                <?php elseif (empty($clientes)): ?>
                    <div class="alert alert-info">Nenhum cliente encontrado. <a href="cadastro_cliente.php">Cadastrar novo cliente</a>.</div>
                <?php else: ?>
                    <div class="row g-3">
                    <?php foreach ($clientes as $cliente): ?>
                        <div class="col-lg-6">
                            <div class="card h-100 border-warning">
                                <div class="card-body">
                                    <h5><?= htmlspecialchars($cliente['nome_completo'] ?? 'Cliente') ?></h5>
                                    <div><strong>CPF:</strong> <?= htmlspecialchars($cliente['cpf'] ?? 'Não informado') ?></div>
                                    <div><strong>WhatsApp:</strong> <?= htmlspecialchars($cliente['whatsapp'] ?? 'Não informado') ?></div>
                                    <div><strong>Endereço:</strong> <?= htmlspecialchars(implode(', ', array_filter([$cliente['rua'] ?? '', $cliente['numero'] ?? '', $cliente['bairro'] ?? '', $cliente['cidade'] ?? '']))) ?></div>
                                </div>
                                <div class="card-footer bg-white"><a class="btn btn-success w-100" href="venda.php?cliente_id=<?= rawurlencode((string)$cliente['id']) ?>">Selecionar cliente e adicionar vestido</a></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
