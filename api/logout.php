<?php
require __DIR__ . '/../lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    put_cookie('auth', '', time() - 3600);
}
redirect('/login');
