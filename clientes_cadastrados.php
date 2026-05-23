<?php
include 'db.php';

// Verifica se há um termo de busca vindo da barra de navegação
$busca = (isset($_GET['busca']) && trim($_GET['busca']) !== '') ? trim($_GET['busca']) : null;

if (!empty($busca)) {
    $clientes = pesquisarClientes($busca);
} else {
    // Busca a lista completa de clientes através da API definida no db.php
    $clientes = listarClientes();
}
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
</head>
<body>
    <?php include_once 'navbar.php'; ?>

    <div class="container-fluid main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="text-dark">
                <i class="bi bi-people-fill me-2" style="color: #001D3D;"></i> 
                Clientes Cadastrados
            </h2>
            <a href="cadastro_cliente.php" class="btn btn-save-main">
                <i class="bi bi-person-plus me-2"></i> NOVO CLIENTE
            </a>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead style="background-color: #001D3D; color: white;">
                            <tr>
                                <th class="ps-4">Nome Completo</th>
                                <th>WhatsApp</th>
                                <th>CPF</th>
                                <th>Nascimento</th>
                                <th class="text-center">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (isset($clientes['error'])): ?>
                                <tr>
                                    <td colspan="5" class="text-center p-5">
                                        <div class="alert alert-warning d-inline-block">
                                            <i class="bi bi-exclamation-triangle me-2"></i>
                                            <?= htmlspecialchars($clientes['detalhes']) ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php elseif (empty($clientes)): ?>
                                <tr>
                                    <td colspan="5" class="text-center p-5 text-muted">
                                        <i class="bi bi-inbox me-2"></i> Nenhum cliente encontrado.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($clientes as $c): ?>
                                    <tr>
                                        <td class="ps-4 fw-bold text-dark"><?= htmlspecialchars($c['nome_completo'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($c['whatsapp'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($c['cpf'] ?? 'N/A') ?></td>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                <?= !empty($c['data_evento']) ? date('d/m/Y', strtotime($c['data_evento'])) : 'Não definida' ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <!-- Botão solicitado: Ver todo o cadastro -->
                                            <a href="cadastro_cliente.php?id=<?= $c['id'] ?>&view=1" class="btn btn-sm btn-outline-primary shadow-sm" title="Ver cadastro completo">
                                                <i class="bi bi-eye-fill me-1"></i> Ver Todo Cadastro
                                            </a>
                                            <a href="cadastro_cliente.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-secondary shadow-sm ms-1" title="Editar">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Lógica de Busca Automática (Filtro em tempo real) para Clientes
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.querySelector('input[name="busca"]');
            if (searchInput) {
                // Re-foca o campo se for um retorno de busca do servidor
                if (new URLSearchParams(window.location.search).has('busca')) {
                    searchInput.focus();
                    const val = searchInput.value;
                    searchInput.value = '';
                    searchInput.value = val;
                }

                searchInput.addEventListener('input', function() {
                    const filter = this.value.toLowerCase().trim();
                    const rows = document.querySelectorAll('tbody tr');
                    rows.forEach(row => {
                        // Busca em todos os campos da linha (Nome, WhatsApp, CPF, etc)
                        const text = row.textContent.toLowerCase();
                        row.style.display = text.includes(filter) ? '' : 'none';
                    });
                });
            }
        });
    </script>
</body>
</html>