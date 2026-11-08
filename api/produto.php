<?php
require __DIR__ . '/../lib/bootstrap.php';
require_login();
$pdo = db();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$p = ['sku' => '', 'nome' => '', 'categoria_id' => 0, 'unidade' => 'un', 'custo' => 0, 'estoque_minimo' => 0,
      'quantidade' => 0, 'fornecedor' => '', 'observacoes' => ''];
if ($id) {
    $st = $pdo->prepare('SELECT * FROM produtos WHERE id = ?');
    $st->execute([$id]);
    $p = $st->fetch();
    if (!$p) redirect('/produtos');
}
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $p['sku'] = strtoupper(trim($_POST['sku'] ?? ''));
    $p['nome'] = trim($_POST['nome'] ?? '');
    $p['categoria_id'] = (int)($_POST['categoria_id'] ?? 0) ?: null;
    $p['unidade'] = in_array($_POST['unidade'] ?? '', UNIDADES, true) ? $_POST['unidade'] : 'un';
    $p['custo'] = num($_POST['custo'] ?? 0);
    $p['estoque_minimo'] = num($_POST['estoque_minimo'] ?? 0);
    $p['fornecedor'] = trim($_POST['fornecedor'] ?? '');
    $p['observacoes'] = trim($_POST['observacoes'] ?? '');
    $inicial = num($_POST['quantidade'] ?? 0);

    if ($p['sku'] === '' || $p['nome'] === '') $erro = 'Informe SKU e nome.';
    elseif ($p['custo'] < 0 || $p['estoque_minimo'] < 0 || $inicial < 0) $erro = 'Valores não podem ser negativos.';
    else {
        try {
            $campos = [$p['sku'], $p['nome'], $p['categoria_id'], $p['unidade'], $p['custo'],
                       $p['estoque_minimo'], $p['fornecedor'] ?: null, $p['observacoes'] ?: null];
            if ($id) {
                $pdo->prepare('UPDATE produtos SET sku=?, nome=?, categoria_id=?, unidade=?, custo=?, estoque_minimo=?, fornecedor=?, observacoes=? WHERE id=?')
                    ->execute([...$campos, $id]);
                flash('Produto atualizado.');
            } else {
                $pdo->beginTransaction();
                $st = $pdo->prepare('INSERT INTO produtos (sku, nome, categoria_id, unidade, custo, estoque_minimo, fornecedor, observacoes, quantidade) VALUES (?,?,?,?,?,?,?,?,?) RETURNING id');
                $st->execute([...$campos, $inicial]);
                $novo = (int)$st->fetchColumn();
                if ($inicial > 0) {
                    $pdo->prepare("INSERT INTO movimentacoes (produto_id, tipo, motivo, quantidade, custo_unitario) VALUES (?, 'entrada', 'estoque_inicial', ?, ?)")
                        ->execute([$novo, $inicial, $p['custo']]);
                }
                $pdo->commit();
                flash('Produto cadastrado.');
            }
            redirect('/produtos');
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $erro = $ex->getCode() === '23505' ? 'Já existe um produto com esse SKU.' : 'Erro ao salvar o produto.';
        }
    }
}
$cats = $pdo->query('SELECT * FROM categorias ORDER BY nome')->fetchAll();

layout_start($id ? 'Editar produto' : 'Novo produto', '/produtos'); ?>
<h1><?= $id ? 'Editar produto' : 'Novo produto' ?></h1>
<?php if ($erro): ?><div class="flash err"><?= e($erro) ?></div><?php endif; ?>
<form class="card form" method="post">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
  <label>SKU / Código<input name="sku" value="<?= e($p['sku']) ?>" required></label>
  <label style="flex:1 1 260px">Nome<input name="nome" value="<?= e($p['nome']) ?>" required></label>
  <label>Categoria
    <select name="categoria_id"><option value="0">— sem categoria —</option>
      <?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>" <?= (int)$p['categoria_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['nome']) ?></option><?php endforeach; ?>
    </select></label>
  <label>Unidade
    <select name="unidade"><?php foreach (UNIDADES as $u): ?><option <?= $p['unidade'] === $u ? 'selected' : '' ?>><?= $u ?></option><?php endforeach; ?></select></label>
  <label>Custo unitário (R$)<input name="custo" value="<?= e(number_format((float)$p['custo'], 2, ',', '')) ?>"></label>
  <label>Estoque mínimo<input name="estoque_minimo" value="<?= e(qtd($p['estoque_minimo'])) ?>"></label>
  <?php if ($id): ?>
    <label>Quantidade atual<input value="<?= e(qtd($p['quantidade'])) ?>" disabled title="Altere pela tela de Movimentações"></label>
  <?php else: ?>
    <label>Quantidade inicial<input name="quantidade" value="0"></label>
  <?php endif; ?>
  <label>Fornecedor<input name="fornecedor" value="<?= e($p['fornecedor']) ?>"></label>
  <label style="flex:1 1 100%">Observações<textarea name="observacoes" rows="2"><?= e($p['observacoes']) ?></textarea></label>
  <div style="width:100%"><button class="btn">Salvar</button> <a class="btn sec" href="/produtos">Cancelar</a></div>
</form>
<?php layout_end();
