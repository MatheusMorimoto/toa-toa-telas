<?php
include 'db.php';

// Inicializa a variável $cliente com campos vazios para evitar erros de "Undefined variable"
$cliente = [
    'id' => '',
    'nome' => '',
    'cpf' => '',
    'telefone' => '',
    'cep' => '',
    'rua' => '',
    'bairro' => '',
    'numero' => '',
    'cidade' => '',
    'complemento' => ''
];

// Alterado para POST por segurança
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["nome"])) {
    $id = isset($_POST["id"]) ? $_POST["id"] : '';
    $nome = $_POST["nome"];
    $cpf = $_POST["cpf"]; 
    $telefone = $_POST["telefone"];
    $cep = $_POST["cep"];
    $rua = $_POST["rua"];
    $bairro = $_POST["bairro"];
    $numerocasa = $_POST["numero"];
    $cidade = $_POST["cidade"];
    $complemento = $_POST["complemento"];

    if (!empty($id)) {
        // Se tem ID, ATUALIZA o cliente existente
        $stmt = $conn->prepare("UPDATE clientes SET nome = ?, cpf = ?, telefone = ?, cep = ?, rua = ?, bairro = ?, numero_casa = ?, cidade = ?, complemento = ? WHERE id = ?");
        $stmt->bind_param("sssssssssi", $nome, $cpf, $telefone, $cep, $rua, $bairro, $numerocasa, $cidade, $complemento, $id);
    } else {
        // Se NÃO tem ID, CADASTRA um novo cliente
        $stmt = $conn->prepare("INSERT INTO clientes (nome, cpf, telefone, cep, rua, bairro, numero_casa, cidade, complemento) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssss", $nome, $cpf, $telefone, $cep, $rua, $bairro, $numerocasa, $cidade, $complemento);
    }

    $stmt->execute();

    header("Location: cadastro_cliente.php?sucesso=1");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET["id"]) && !empty($_GET["id"])) {
    $id = $_GET["id"];
    $stmt = $conn->prepare("SELECT * FROM clientes WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $dados = $stmt->get_result()->fetch_assoc();
    if ($dados) {
        $cliente = $dados;
        // Mapeia o nome da coluna do banco para o nome usado no seu HTML
        $cliente['numero'] = $dados['numero_casa'];
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tôa Tôa - Cadastro de Cliente</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="index.css" rel="stylesheet">
</head>
<body>
    <?php include_once 'navbar.php'; ?>

    <div class="container-fluid main-content">
        <?php if (isset($_GET['sucesso'])): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                <i class="bi bi-check-circle me-2"></i> Cliente salvo com sucesso!
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="text-dark"><i class="bi bi-person-plus me-2"></i> <?= !empty($cliente['id']) ? 'Editar Cliente' : 'Novo Cadastro de Cliente' ?></h2>
        </div>

        <form id="cadastro" method="POST">
            <div class="form-card shadow-sm p-4 bg-white rounded">
                <input type="hidden" name="id" value="<?= htmlspecialchars($cliente['id']) ?>">
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="nome" class="form-label">Nome Completo</label>
                        <input type="text" id="nome" name="nome" class="form-control" value="<?= htmlspecialchars($cliente['nome']) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label for="cpf" class="form-label">CPF</label>
                        <input type="text" id="cpf" name="cpf" class="form-control" value="<?= htmlspecialchars($cliente['cpf']) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label for="telefone" class="form-label">Telefone / WhatsApp</label>
                        <input type="text" id="telefone" name="telefone" class="form-control" value="<?= htmlspecialchars($cliente['telefone']) ?>" required>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-2">
                        <label for="cep" class="form-label">CEP (Somente números)</label>
                        <input type="text" id="cep" name="cep" class="form-control" maxlength="8" value="<?= htmlspecialchars($cliente['cep']) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="rua" class="form-label">Rua / Logradouro</label>
                        <input type="text" id="rua" name="rua" class="form-control" value="<?= htmlspecialchars($cliente['rua']) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label for="bairro" class="form-label">Bairro</label>
                        <input type="text" id="bairro" name="bairro" class="form-control" value="<?= htmlspecialchars($cliente['bairro']) ?>" required>
                    </div>
                    <div class="col-md-1">
                        <label for="numero" class="form-label">Nº</label>
                        <input type="text" id="numero" name="numero" class="form-control" value="<?= htmlspecialchars($cliente['numero']) ?>" required>
                    </div>
                    <div class="col-md-2">
                        <label for="cidade" class="form-label">Cidade</label>
                        <input type="text" id="cidade" name="cidade" class="form-control" value="<?= htmlspecialchars($cliente['cidade']) ?>" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="complemento" class="form-label">Complemento / Referência</label>
                    <input type="text" id="complemento" name="complemento" class="form-control" value="<?= htmlspecialchars($cliente['complemento']) ?>">
                </div>
            </div>

            <div class="action-buttons mt-4">
                <button type="submit" class="btn btn-save-main w-100 py-3 fw-bold">
                    <i class="bi bi-save me-2"></i> <?= !empty($cliente['id']) ? 'SALVAR ALTERAÇÕES' : 'CADASTRAR CLIENTE' ?>
                </button>
            </div>
        </form>
    </div>

<script>
    const cepInput = document.getElementById('cep');
    
    cepInput.addEventListener('blur', () => {
        let cep = cepInput.value.replace(/\D/g, '');
        
        if (cep !== "") {
            let validacep = /^[0-9]{8}$/;

            if(validacep.test(cep)) {
                fetch(`https://viacep.com.br/ws/${cep}/json/`)
                    .then(res => res.json())
                    .then(dados => {
                        if (!("erro" in dados)) {
                            document.getElementById('rua').value = dados.logradouro;
                            document.getElementById('bairro').value = dados.bairro;
                            document.getElementById('cidade').value = dados.localidade;
                        } else {
                            alert("CEP não encontrado.");
                        }
                    });
            }
        }
    });

    // Faz a mensagem de sucesso desaparecer após 3 segundos (3000ms)
    const alertElement = document.querySelector('.alert');
    if (alertElement) {
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alertElement);
            bsAlert.close();
        }, 3000);
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>