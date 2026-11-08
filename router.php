<?php
// Roteador apenas para desenvolvimento local (php -S). Na Vercel valem as rewrites do vercel.json.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($path === '/style.css') {
    header('Content-Type: text/css');
    readfile(__DIR__ . '/public/style.css');
    return true;
}
$map = ['/' => 'index'];
$page = $map[$path] ?? ltrim($path, '/');
if (preg_match('/^(index|login|logout|produtos|produto|categorias|movimentacoes)$/', $page)) {
    require __DIR__ . "/api/$page.php";
    return true;
}
http_response_code(404);
echo 'Não encontrado';
