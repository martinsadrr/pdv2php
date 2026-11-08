<?php
require __DIR__ . '/../lib/bootstrap.php';

if (logged_in()) redirect('/');
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $user = env('ADMIN_USER', 'admin');
    $pass = env('ADMIN_PASS');
    if (!$pass) {
        $erro = 'ADMIN_PASS não configurada no servidor.';
    } elseif (hash_equals($user, (string)($_POST['usuario'] ?? '')) && hash_equals($pass, (string)($_POST['senha'] ?? ''))) {
        login_user($user);
        redirect('/');
    } else {
        $erro = 'Usuário ou senha inválidos.';
    }
}
csrf();
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Entrar · Estoque</title><link rel="stylesheet" href="/style.css"></head><body>
<div class="login card">
  <h1>📦 Estoque</h1>
  <?php if ($erro): ?><div class="flash err"><?= e($erro) ?></div><?php endif; ?>
  <form class="form" method="post">
    <?= csrf_field() ?>
    <label>Usuário<input name="usuario" required autofocus></label>
    <label>Senha<input type="password" name="senha" required></label>
    <button class="btn">Entrar</button>
  </form>
</div></body></html>
