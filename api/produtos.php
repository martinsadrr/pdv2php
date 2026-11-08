<?php
require __DIR__ . '/../lib/bootstrap.php';
require_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'excluir') {
    check_csrf();
    $pdo->prepare('DELETE FROM produtos WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
    flash('Produto excluído junto com o histórico de movimentações.');
    redirect('/produtos');
}

$q    = trim($_GET['q'] ?? '');
$cat  = (int)($_GET['categoria'] ?? 0);
$forn = trim($_GET['fornecedor'] ?? '');
$sit  = $_GET['situacao'] ?? '';

$where = []; $args = [];
if ($q !== '')    { $where[] = '(p.nome ILIKE ? OR p.sku ILIKE ?)'; $args[] = "%$q%"; $args[] = "%$q%"; }
if ($cat > 0)     { $where[] = 'p.categoria_id = ?'; $args[] = $cat; }
if ($forn !== '') { $where[] = 'p.fornecedor ILIKE ?'; $args[] = "%$forn%"; }
if ($sit === 'zerado') $where[] = 'p.quantidade <= 0';
if ($sit === 'baixo')  $where[] = 'p.quantidade > 0 AND p.quantidade <= p.estoque_minimo';
if ($sit === 'ok')     $where[] = 'p.quantidade > p.estoque_minimo';

$sql = 'SELECT p.*, c.nome AS categoria FROM produtos p LEFT JOIN categorias c ON c.id = p.categoria_id'
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY p.nome';
$st = $pdo->prepare($sql); $st->execute($args);
$produtos = $st->fetchAll();
$cats = $pdo->query('SELECT * FROM categorias ORDER BY nome')->fetchAll();

$nBaixo = 0; $valor = 0.0;
foreach ($produtos as $p) {
    $valor += $p['quantidade'] * $p['custo'];
    if ($p['quantidade'] <= $p['estoque_minimo']) $nBaixo++;
}

layout_start('Produtos', '/produtos'); ?>
<div class="head"><h1>Produtos</h1><a class="btn" href="/produto">+ Novo produto</a></div>

<form class="card filters" method="get">
  <label>Busca<input name="q" value="<?= e($q) ?>" placeholder="Nome ou SKU"></label>
  <label>Categoria
    <select name="categoria"><option value="0">Todas</option>
      <?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>" <?= $cat === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['nome']) ?></option><?php endforeach; ?>
    </select></label>
  <label>Fornecedor<input name="fornecedor" value="<?= e($forn) ?>"></label>
  <label>Situação
    <select name="situacao">
      <option value="">Todas</option>
      <option value="ok" <?= $sit === 'ok' ? 'selected' : '' ?>>Em estoque</option>
      <option value="baixo" <?= $sit === 'baixo' ? 'selected' : '' ?>>Estoque baixo</option>
      <option value="zerado" <?= $sit === 'zerado' ? 'selected' : '' ?>>Zerado</option>
    </select></label>
  <button class="btn">Filtrar</button>
  <a class="btn sec" href="/produtos">Limpar</a>
</form>

<div class="summary">
  <div><span class="muted">Itens</span><b><?= count($produtos) ?></b></div>
  <div><span class="muted">Abaixo do mínimo</span><b><?= $nBaixo ?></b></div>
  <div><span class="muted">Valor em estoque</span><b><?= brl($valor) ?></b></div>
</div>

<div class="scroll"><table>
  <thead><tr><th>SKU</th><th>Produto</th><th>Categoria</th><th>Fornecedor</th><th class="num">Custo</th><th class="num">Qtd.</th><th>Situação</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($produtos as $p): [$cls, $lbl] = situacao($p); ?>
    <tr>
      <td><?= e($p['sku']) ?></td>
      <td><?= e($p['nome']) ?></td>
      <td><?= e($p['categoria'] ?? '—') ?></td>
      <td><?= e($p['fornecedor'] ?: '—') ?></td>
      <td class="num"><?= brl($p['custo']) ?>/<?= e($p['unidade']) ?></td>
      <td class="num"><?= qtd($p['quantidade']) ?> <?= e($p['unidade']) ?> <span class="muted">/ mín. <?= qtd($p['estoque_minimo']) ?></span></td>
      <td><span class="badge <?= $cls ?>"><?= $lbl ?></span></td>
      <td class="act">
        <a class="btn sec sm" href="/movimentacoes?produto=<?= $p['id'] ?>">Mov.</a>
        <a class="btn sec sm" href="/produto?id=<?= $p['id'] ?>">Editar</a>
        <form method="post" onsubmit="return confirm('Excluir este produto e todo o histórico de movimentações?')">
          <?= csrf_field() ?><input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?= $p['id'] ?>">
          <button class="btn del sm">Excluir</button>
        </form>
      </td>
    </tr>
  <?php endforeach; if (!$produtos): ?><tr><td colspan="8" class="empty">Nenhum produto encontrado.</td></tr><?php endif; ?>
  </tbody>
</table></div>
<?php layout_end();
