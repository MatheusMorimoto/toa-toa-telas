<?php
include_once 'db.php';

// Busca a lista de clientes cadastrados na API
$result = listarClientes();

// Verifica se houve erro na API
$api_error = isset($result['error']) ? $result : null;
$clientes = $api_error ? [] : $result;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tôa Tôa - Clientes Cadastrados</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="index.css" rel="stylesheet">
    <style>
        :root {
            --brand-navy: #001D3D;
            --brand-gold: #FFD700;
        }
        body { background-color: #f4f6f9; }
        .table-container { background: white; border-radius: 12px; overflow: hidden; border: 1px solid #e0e0e0; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .thead-brand { background-color: var(--brand-navy); color: var(--brand-gold); border-bottom: 4px solid var(--brand-gold); }
        .thead-brand th { font-weight: 700; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 0.5px; padding: 15px; border: none; }
        .table tbody tr:hover { background-color: #fcf9e8; }
        .cod-badge { font-family: monospace; font-size: 0.9rem; color: var(--brand-navy); background: #fff9c4; padding: 3px 8px; border-radius: 4px; }
    </style>
</head>
<body>
    <?php include_once 'navbar.php'; ?>

    <div class="container-fluid main-content mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="section-title mb-0"><i class="bi bi-people me-2"></i>Clientes Cadastrados</h4>
            <a href="cadastro_cliente.php" class="btn btn-warning-custom btn-sm shadow-sm">
                <i class="bi bi-person-plus me-1"></i> Novo Cliente
            </a>
        </div>

        <?php if ($api_error): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-octagon me-2"></i>
                <strong>Erro ao carregar clientes:</strong> <?php echo htmlspecialchars($api_error['detalhes']); ?>
            </div>
        <?php elseif (empty($clientes)): ?>
            <div class="text-center p-5 bg-white border rounded shadow-sm">
                <i class="bi bi-person-x display-1 text-muted"></i>
                <p class="mt-3 fs-5">Nenhum cliente encontrado.</p>
            </div>
        <?php else: ?>
            <div class="table-container shadow-sm">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="thead-brand">
                            <tr>
                                <th class="ps-4">ID</th>
                                <th>Nome Completo</th>
                                <th>WhatsApp</th>
                                <th>CPF</th>
                                <th>Cidade</th>
                                <th class="pe-4 text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clientes as $c): ?>
                                <tr>
                                    <td class="ps-4">
                                        <span class="cod-badge">#<?php echo htmlspecialchars($c['id'] ?? '---'); ?></span>
                                    </td>
                                    <td class="fw-bold text-dark">
                                        <?php echo htmlspecialchars($c['nome_completo'] ?? $c['nome'] ?? 'N/A'); ?>
                                    </td>
                                    <td>
                                        <a href="https://wa.me/<?php echo preg_replace('/\D/', '', $c['whatsapp'] ?? $c['telefone'] ?? ''); ?>" target="_blank" class="text-decoration-none">
                                            <i class="bi bi-whatsapp text-success me-1"></i>
                                            <?php echo htmlspecialchars($c['whatsapp'] ?? $c['telefone'] ?? '---'); ?>
                                        </a>
                                    </td>
                                    <td class="text-muted small">
                                        <?php echo htmlspecialchars($c['cpf'] ?? '---'); ?>
                                    </td>
                                    <td>
                                        <?php 
                                            // Tenta extrair a cidade do campo endereço se não houver campo cidade direto
                                            echo htmlspecialchars($c['cidade'] ?? '---'); 
                                        ?>
                                    </td>
                                    <td class="pe-4 text-end">
                                        <a href="cadastro_cliente.php?id=<?php echo urlencode($c['id']); ?>" class="btn btn-sm btn-outline-secondary" title="Editar">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>