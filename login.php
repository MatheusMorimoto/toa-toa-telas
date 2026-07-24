<?php
require_once 'security.php';
start_secure_session();

if (is_authenticated()) {
    header('Location: index.php');
    exit;
}

$error = '';
$redirect = safe_redirect_target($_GET['redirect'] ?? $_POST['redirect'] ?? 'index.php');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $expectedUser = (string)env_value('APP_AUTH_USER', '');
    $passwordHash = (string)env_value('APP_AUTH_PASSWORD_HASH', '');
    $validUser = $expectedUser !== '' && hash_equals($expectedUser, (string)($_POST['usuario'] ?? ''));
    $validPassword = $passwordHash !== '' && password_verify((string)($_POST['senha'] ?? ''), $passwordHash);
    if ($validUser && $validPassword) {
        session_regenerate_id(true);
        $_SESSION['authenticated_at'] = time();
        header('Location: ' . $redirect);
        exit;
    }
    $error = $expectedUser === '' || $passwordHash === ''
        ? 'Autenticacao nao configurada. Defina APP_AUTH_USER e APP_AUTH_PASSWORD_HASH.'
        : 'Usuario ou senha invalidos.';
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tôa Tôa - Acesso</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="index.css" rel="stylesheet">
</head>
<body>
    <div class="container" style="max-width: 420px; padding-top: 10vh;">
        <h2 class="text-dark mb-4">Tôa Tôa Moda Festa</h2>
        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <form method="POST">
            <?= csrf_input() ?>
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8') ?>">
            <div class="mb-3">
                <label for="usuario" class="form-label">Usuario</label>
                <input id="usuario" name="usuario" class="form-control" required autocomplete="username">
            </div>
            <div class="mb-3">
                <label for="senha" class="form-label">Senha</label>
                <input id="senha" name="senha" type="password" class="form-control" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-save-main w-100">ENTRAR</button>
        </form>
    </div>
</body>
</html>
