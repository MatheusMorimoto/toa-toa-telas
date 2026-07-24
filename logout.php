<?php
require_once 'security.php';
require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Metodo nao permitido.');
}
validate_csrf();
start_secure_session();
$_SESSION = [];
session_destroy();
header('Location: login.php');
exit;
