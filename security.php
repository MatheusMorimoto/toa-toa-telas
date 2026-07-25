<?php

function load_env_file($path)
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$name, $value] = array_map('trim', explode('=', $line, 2));
        if ($name === '' || getenv($name) !== false) {
            continue;
        }
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }
        putenv("$name=$value");
        $_ENV[$name] = $value;
    }
}

function env_value($name, $default = null)
{
    $value = getenv($name);
    return $value === false ? $default : $value;
}

function env_bool($name, $default = false)
{
    $value = env_value($name);
    return $value === null ? $default : filter_var($value, FILTER_VALIDATE_BOOLEAN);
}

function start_secure_session()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params(['httponly' => true, 'secure' => $isHttps, 'samesite' => 'Lax']);
    session_start();
}

function is_authenticated()
{
    if (!env_bool('APP_AUTH_ENABLED', false)) {
        return true;
    }
    start_secure_session();
    return isset($_SESSION['authenticated_at']);
}

function require_auth()
{
    if (PHP_SAPI === 'cli') {
        return;
    }

    // Abra a sessão enquanto ainda estamos no início da requisição. Mesmo com a
    // autenticação desativada, a navbar usa a sessão para o token CSRF.
    start_secure_session();

    if (is_authenticated()) {
        return;
    }
    $requestUri = $_SERVER['REQUEST_URI'] ?? 'index.php';
    header('Location: login.php?redirect=' . rawurlencode($requestUri));
    exit;
}

function csrf_token()
{
    start_secure_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_input()
{
    return '<input type="hidden" name="csrf_token" value="' .
        htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function validate_csrf()
{
    start_secure_session();
    $provided = $_POST['csrf_token'] ?? '';
    $stored = $_SESSION['csrf_token'] ?? '';
    if (!is_string($provided) || $stored === '' || !hash_equals($stored, $provided)) {
        http_response_code(403);
        exit('Solicitacao invalida. Atualize a pagina e tente novamente.');
    }
}

function safe_redirect_target($target)
{
    if (!is_string($target) || $target === '' || str_contains($target, "\r") || str_contains($target, "\n")) {
        return 'index.php';
    }
    $parts = parse_url($target);
    if ($parts === false || isset($parts['scheme']) || isset($parts['host']) || str_starts_with($target, '//')) {
        return 'index.php';
    }
    return $target;
}

load_env_file(__DIR__ . '/.env');
