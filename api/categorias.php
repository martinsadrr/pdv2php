<?php
require __DIR__ . '/../lib/bootstrap.php';
require_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $acao = $_POST['acao'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $nome = trim($_POST['nome'] ?? '');
    try {
        if ($acao === 'salvar') {
            if ($nome === '') throw new RuntimeException('Informe o nome da categoria.');
            if ($id) {
                $pdo->prepare('UPDATE categorias SET nome = ? WHERE id = ?')->execute([$nome, $id]);
                flash('Categoria atualizada.');
            } else {
                $pdo->prepare('INSERT INTO categorias (nome) VALUES (?)')->execute([$nome]);
                flash('Categoria criada.');
            }
        } elseif ($acao === 'excluir') {
            $st = $pdo->prepare('SELECT COUNT(*) FROM produtos WHERE categoria_id = ?');
            $st->execute([$id]);
            if ($st->fetchColumn() > 0) {
                throw new RuntimeException('Não é possível excluir: existem produtos nesta categoria.');
            }
            $pdo->prepare('DELETE FROM categorias WHERE id = ?')->execute([$id]);
            flash('Categoria excluída.');
        }
    } catch (RuntimeException $ex) {
        flash($ex->getMessage(), 'err');
    } catch (PDOException $ex) {
        flash($ex->getCode() === '23505' ? 'Já existe uma categoria com esse nome.' : 'Erro ao salvar.', 'err');
    }
    redirect('/categorias');
}

$editId = (int)($_GET['editar'] ?? 0);
$edit = null;
if ($editId) {
    $st = $pdo->prepare('SELECT * FROM categorias WHERE id = ?');
    $st->execute([$editId]);
    $edit = $st->fetch() ?: null;
}
$cats = $pdo->query('SELECT c.*, COUNT(p.id) AS total FROM categorias c LEFT JOIN produtos p ON p.categoria_id = c.id GROUP BY c.id ORDER BY c.nome')->fetchAll();

layout_start('Categorias', '/categorias'); ?>
<div class="head"><h1>Categorias</h1></div>

<form class="card form" method="post">
  <?= csrf_field() ?><input type="hidden" name="acao" value="salvar"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
  <label style="flex:1 1 260px"><?= $edit ? 'Editar categoria' : 'Nova categoria' ?>
    <input name="nome" value="<?= e($edit['nome'] ?? '') ?>" placeholder="Ex.: Carnes, Hortifrúti, Bebidas" required></label>
  <button class="btn"><?= $edit ? 'Salvar' : 'Adicionar' ?></button>
  <?php if ($edit): ?><a class="btn sec" href="/categorias">Cancelar</a><?php endif; ?>
</form>

<div class="scroll"><table>
  <thead><tr><th>Categoria</th><th class="num">Produtos</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($cats as $c): ?>
    <tr><td><?= e($c['nome']) ?></td><td class="num"><?= (int)$c['total'] ?></td>
      <td class="act">
        <a class="btn sec sm" href="/categorias?editar=<?= $c['id'] ?>">Editar</a>
        <form method="post" onsubmit="return confirm('Excluir esta categoria?')">
          <?= csrf_field() ?><input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?= $c['id'] ?>">
          <button class="btn del sm">Excluir</button>
        </form>
      </td></tr>
  <?php endforeach; if (!$cats): ?><tr><td colspan="3" class="empty">Nenhuma categoria cadastrada.</td></tr><?php endif; ?>
  </tbody>
</table></div>
<?php layout_end();
