<?php
include 'db.php';

// Inicializa a variável $cliente com campos vazios para evitar erros de "Undefined variable"
$cliente = [
    'id' => '',
    'nome_completo' => '',
    'cpf' => '',
    'rg' => '',
    'whatsapp' => '',
    'tipo_contato_1' => '',
    'telefone_secundario' => '',
    'tipo_contato_2' => '',
    'email' => '',
    'cep' => '',
    'rua' => '',
    'bairro' => '',
    'numero' => '',
    'cidade' => '',
    'complemento' => '',
    'preferencias' => '',
    'data_evento' => '',
    'imagem' => ''
];

// Alterado para POST por segurança
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["nome_completo"])) {
    $id = isset($_POST["id"]) ? $_POST["id"] : '';
    $nome_completo = $_POST["nome_completo"];
    $cpf = $_POST["cpf"]; 
    $rg = $_POST["rg"];
    $whatsapp = $_POST["whatsapp"];
    $tipo_contato_1 = $_POST["tipo_contato_1"];
    $telefone_secundario = $_POST["telefone_secundario"];
    $tipo_contato_2 = $_POST["tipo_contato_2"];
    $email = $_POST["email"];
    $cep = $_POST["cep"];
    $preferencias = $_POST["preferencias"];
    $data_evento = $_POST["data_evento"];

    // Processamento da Imagem (Nomenclatura: timestamp_vestido.ext / Pasta: produtos/)
    $nomeImagem = $cliente['imagem'] ?? '';
    if (isset($_FILES['imagemCliente']) && $_FILES['imagemCliente']['error'] === 0) {
        $extensao = strtolower(pathinfo($_FILES['imagemCliente']['name'], PATHINFO_EXTENSION));
        $permitidos = ['jpg', 'jpeg', 'png', 'webp'];
        
        if (in_array($extensao, $permitidos)) {
            // Seguindo a regra: Pasta produtos/ + timestamp + _vestido.ext
            $nomeImagem = "produtos/" . time() . "_vestido." . $extensao;
            
            if (!uploadImagemSupabase($_FILES['imagemCliente']['tmp_name'], $nomeImagem)) {
                echo "<div class='alert alert-danger'>Erro ao enviar imagem para o Supabase.</div>";
                exit;
            }
        }
    }
    
    // Monta o endereço completo para o campo 'endereco' do banco
    $endereco_completo = $_POST["rua"] . ", " . $_POST["numero"] . " - " . $_POST["bairro"] . ", " . $_POST["cidade"] . " (" . $_POST["complemento"] . ")";

    $dadosCliente = [
        "nome_completo" => $nome_completo,
        "cpf" => $cpf,
        "rg" => $rg,
        "whatsapp" => $whatsapp,
        "tipo_contato_1" => $tipo_contato_1,
        "telefone_secundario" => $telefone_secundario,
        "tipo_contato_2" => $tipo_contato_2,
        "email" => $email,
        "cep" => $cep,
        "endereco" => $endereco_completo,
        "preferencias" => $preferencias,
        "data_evento" => $data_evento,
        "imagem" => $nomeImagem
    ];

    if (!empty($id)) {
        $res = editarCliente($id, $dadosCliente);
    } else {
        $res = salvarCliente($dadosCliente);
    }

    if (!isset($res['error'])) {
        header("Location: cadastro_cliente.php?sucesso=1");
        exit();
    }
}

if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET["id"]) && !empty($_GET["id"])) {
    $res = buscarCliente($_GET["id"]);
    if ($res && !isset($res['error'])) {
        // Mescla os dados recebidos com os valores padrão para evitar avisos de chaves inexistentes
        $cliente = array_merge($cliente, $res);
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

        <form id="cadastro" method="POST" enctype="multipart/form-data">
            <div class="row g-0 form-card shadow-sm">
                <!-- Seção de Dados -->
                <div class="col-lg-8 p-4 border-end">
                <input type="hidden" name="id" value="<?= htmlspecialchars($cliente['id'] ?? '') ?>">
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="nome_completo" class="form-label">Nome Completo</label>
                        <input type="text" id="nome_completo" name="nome_completo" class="form-control" value="<?= htmlspecialchars($cliente['nome_completo'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label for="cpf" class="form-label">CPF</label>
                        <input type="text" id="cpf" name="cpf" class="form-control" value="<?= htmlspecialchars($cliente['cpf'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label for="rg" class="form-label">RG</label>
                        <input type="text" id="rg" name="rg" class="form-control" value="<?= htmlspecialchars($cliente['rg'] ?? '') ?>">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <label for="whatsapp" class="form-label">WhatsApp (Principal)</label>
                        <input type="text" id="whatsapp" name="whatsapp" class="form-control" value="<?= htmlspecialchars($cliente['whatsapp'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label for="tipo_contato_1" class="form-label">Tipo</label>
                        <input type="text" name="tipo_contato_1" class="form-control" placeholder="Ex: Pessoal, Noiva" value="<?= htmlspecialchars($cliente['tipo_contato_1'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="telefone_secundario" class="form-label">Telefone Secundário</label>
                        <input type="text" name="telefone_secundario" class="form-control" value="<?= htmlspecialchars($cliente['telefone_secundario'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="tipo_contato_2" class="form-label">Parentesco/Tipo</label>
                        <input type="text" name="tipo_contato_2" class="form-control" placeholder="Ex: Mãe, Noivo" value="<?= htmlspecialchars($cliente['tipo_contato_2'] ?? '') ?>">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="email" class="form-label">E-mail</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($cliente['email'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="data_evento" class="form-label">Data do Evento</label>
                        <input type="date" name="data_evento" class="form-control" value="<?= htmlspecialchars($cliente['data_evento'] ?? '') ?>">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-2">
                        <label for="cep" class="form-label">CEP (Somente números)</label>
                        <input type="text" id="cep" name="cep" class="form-control" maxlength="8" value="<?= htmlspecialchars($cliente['cep'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="rua" class="form-label">Rua / Logradouro</label>
                        <input type="text" id="rua" name="rua" class="form-control" value="<?= htmlspecialchars($cliente['rua'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label for="bairro" class="form-label">Bairro</label>
                        <input type="text" id="bairro" name="bairro" class="form-control" value="<?= htmlspecialchars($cliente['bairro'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-1">
                        <label for="numero" class="form-label">Nº</label>
                        <input type="text" id="numero" name="numero" class="form-control" value="<?= htmlspecialchars($cliente['numero'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-2">
                        <label for="cidade" class="form-label">Cidade</label>
                        <input type="text" id="cidade" name="cidade" class="form-control" value="<?= htmlspecialchars($cliente['cidade'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="complemento" class="form-label">Complemento / Referência</label>
                    <input type="text" id="complemento" name="complemento" class="form-control" value="<?= htmlspecialchars($cliente['complemento'] ?? '') ?>">
                </div>

                <div class="mb-3">
                    <label for="preferencias" class="form-label">Preferências / Observações</label>
                    <textarea name="preferencias" class="form-control" rows="3"><?= htmlspecialchars($cliente['preferencias'] ?? '') ?></textarea>
                </div>
                </div>

                <!-- Seção de Imagem (Painel Lateral Direto conforme padrão do sistema) -->
                <div class="col-lg-4 image-panel">
                    <h5 class="section-title">Foto de Referência</h5>
                    <div class="image-preview" id="imagePreviewContainer">
                        <?php $urlImg = !empty($cliente['imagem']) ? "https://idxyfkeodaettqbjuiak.supabase.co/storage/v1/object/public/toa-toa-moda-festa/" . $cliente['imagem'] : ""; ?>
                        <img src="<?= htmlspecialchars($urlImg) ?>" alt="Preview" id="previewImg" style="<?= !empty($urlImg) ? 'display: block;' : 'display: none;' ?>">
                        <div id="previewPlaceholder" style="<?= !empty($urlImg) ? 'display: none;' : 'display: block;' ?>">
                            <i class="bi bi-camera" style="font-size: 3rem; color: #ccc;"></i>
                            <p class="text-muted">Anexar foto da cliente ou vestido</p>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label for="imagemCliente" class="btn btn-upload w-100">
                            <i class="bi bi-plus-circle me-2"></i> Selecionar Imagem
                        </label>
                        <input type="file" class="form-control d-none" id="imagemCliente" name="imagemCliente" accept="image/*">
                        <p class="text-muted small mt-2">Pasta: produtos/ | Upsert: Off</p>
                    </div>
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
    // Lógica de Preview da Imagem
    const imgInput = document.getElementById('imagemCliente');
    const previewImg = document.getElementById('previewImg');
    const placeholder = document.getElementById('previewPlaceholder');

    imgInput.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                previewImg.src = e.target.result;
                previewImg.style.display = 'block';
                placeholder.style.display = 'none';
            }
            reader.readAsDataURL(file);
        }
    });

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