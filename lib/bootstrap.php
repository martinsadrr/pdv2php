<?php
declare(strict_types=1);

ob_start();

function env(string $k, ?string $d = null): ?string
{
    $v = getenv($k);
    return ($v === false || $v === '') ? $d : $v;
}

/* ---------- Banco ---------- */

function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;

    $url = env('DATABASE_URL');
    if (!$url) exit('Defina a variável de ambiente DATABASE_URL.');
    $u = parse_url($url);
    $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s;sslmode=%s',
        $u['host'], $u['port'] ?? 5432, ltrim($u['path'] ?? '', '/'), env('DB_SSLMODE', 'require'));

    $pdo = new PDO($dsn, urldecode($u['user'] ?? ''), urldecode($u['pass'] ?? ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    if ($pdo->query("SELECT to_regclass('public.produtos')")->fetchColumn() === null) {
        $pdo->exec(file_get_contents(__DIR__ . '/../schema.sql'));
    }
    return $pdo;
}

/* ---------- Helpers ---------- */

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function redirect(string $url): never { header('Location: ' . $url); exit; }

/** "1.234,56" ou "2,5" ou "2.5" -> float */
function num($s): float
{
    $s = trim((string)$s);
    if (str_contains($s, ',')) $s = str_replace(',', '.', str_replace('.', '', $s));
    return is_numeric($s) ? (float)$s : 0.0;
}
function brl($v): string { return 'R$ ' . number_format((float)$v, 2, ',', '.'); }
function qtd($v): string
{
    $s = number_format((float)$v, 3, ',', '.');
    return rtrim(rtrim($s, '0'), ',');
}
function dt($v): string { return $v ? date('d/m/Y H:i', strtotime((string)$v)) : ''; }
function d($v): string { return $v ? date('d/m/Y', strtotime((string)$v)) : ''; }

const UNIDADES = ['un', 'kg', 'g', 'L', 'ml', 'cx', 'pct'];
const MOTIVOS = [
    'entrada' => ['compra' => 'Compra', 'ajuste' => 'Ajuste', 'estoque_inicial' => 'Estoque inicial'],
    'saida'   => ['consumo' => 'Consumo/Produção', 'perda' => 'Perda/Desperdício', 'vencimento' => 'Vencimento',
                  'ajuste' => 'Ajuste', 'consumo_interno' => 'Consumo interno'],
];
function motivo_label(string $tipo, string $m): string { return MOTIVOS[$tipo][$m] ?? $m; }

/* ---------- Cookies, CSRF, flash ---------- */

function secure(): bool
{
    return !empty($_SERVER['HTTPS']) || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}
function put_cookie(string $n, string $v, int $exp = 0): void
{
    setcookie($n, $v, ['expires' => $exp, 'path' => '/', 'httponly' => true,
        'secure' => secure(), 'samesite' => 'Lax']);
}

function csrf(): string
{
    if (empty($_COOKIE['csrf'])) {
        $t = bin2hex(random_bytes(16));
        put_cookie('csrf', $t, time() + 86400 * 7);
        $_COOKIE['csrf'] = $t;
    }
    return $_COOKIE['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . csrf() . '">'; }
function check_csrf(): void
{
    $c = $_COOKIE['csrf'] ?? '';
    if ($c === '' || !hash_equals($c, (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Token inválido. Volte e tente novamente.');
    }
}

function flash(string $msg, string $type = 'ok'): void
{
    put_cookie('flash', rawurlencode($type . '|' . $msg), time() + 60);
}

/* ---------- Autenticação ---------- */

function app_key(): string
{
    $k = env('APP_KEY');
    if (!$k) exit('Defina a variável de ambiente APP_KEY.');
    return $k;
}
function sign(string $s): string { return hash_hmac('sha256', $s, app_key()); }

function logged_in(): bool
{
    $parts = explode('|', (string)($_COOKIE['auth'] ?? ''));
    if (count($parts) !== 3) return false;
    [$u, $exp, $sig] = $parts;
    return (int)$exp > time() && hash_equals(sign("$u|$exp"), $sig);
}
function login_user(string $u): void
{
    $exp = time() + 86400 * 7;
    put_cookie('auth', "$u|$exp|" . sign("$u|$exp"), $exp);
}
function require_login(): void { if (!logged_in()) redirect('/login'); }

/* ---------- Layout ---------- */

function layout_start(string $title, string $active = ''): void
{
    csrf();
    $nav = ['/' => 'Dashboard', '/produtos' => 'Produtos', '/categorias' => 'Categorias', '/movimentacoes' => 'Movimentações'];
    $flash = null;
    if (!empty($_COOKIE['flash'])) {
        $flash = explode('|', rawurldecode($_COOKIE['flash']), 2);
        put_cookie('flash', '', time() - 3600);
    }
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>' . e($title) . ' · Estoque</title><link rel="stylesheet" href="/style.css"></head><body>'
       . '<header class="top"><div class="wrap bar"><strong class="brand">📦 Estoque</strong><nav>';
    foreach ($nav as $href => $label) {
        echo '<a href="' . $href . '"' . ($href === $active ? ' class="on"' : '') . '>' . $label . '</a>';
    }
    echo '</nav><form method="post" action="/logout" class="logout">' . csrf_field()
       . '<button class="btn sec sm">Sair</button></form></div></header><main class="wrap">';
    if ($flash && count($flash) === 2) {
        echo '<div class="flash ' . e($flash[0]) . '">' . e($flash[1]) . '</div>';
    }
}
function layout_end(): void { echo '</main></body></html>'; }

function situacao(array $p): array
{
    if ((float)$p['quantidade'] <= 0) return ['b-zero', 'Zerado'];
    if ((float)$p['quantidade'] <= (float)$p['estoque_minimo']) return ['b-low', 'Baixo'];
    return ['b-ok', 'OK'];
}
