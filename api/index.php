<?php
require __DIR__ . '/../lib/bootstrap.php';
require_login();
$pdo = db();

$baixos = $pdo->query("
    SELECT p.*, c.nome AS categoria FROM produtos p
    LEFT JOIN categorias c ON c.id = p.categoria_id
    WHERE p.quantidade <= p.estoque_minimo
    ORDER BY p.quantidade = 0 DESC, p.nome LIMIT 50")->fetchAll();

$lotes = $pdo->query("
    SELECT m.validade, m.quantidade, p.nome, p.unidade, (m.validade < CURRENT_DATE) AS vencido
    FROM movimentacoes m JOIN produtos p ON p.id = m.produto_id
    WHERE m.tipo = 'entrada' AND m.validade IS NOT NULL
      AND m.validade BETWEEN CURRENT_DATE - 30 AND CURRENT_DATE + 7
    ORDER BY m.validade LIMIT 50")->fetchAll();

$ultimas = $pdo->query("
    SELECT m.*, p.nome, p.unidade FROM movimentacoes m
    JOIN produtos p ON p.id = m.produto_id
    ORDER BY m.criado_em DESC, m.id DESC LIMIT 10")->fetchAll();

$tot = $pdo->query("SELECT COUNT(*) AS n, COALESCE(SUM(quantidade*custo),0) AS valor FROM produtos")->fetch();

layout_start('Dashboard', '/'); ?>
<div class="head"><h1>Dashboard</h1><a class="btn" href="/movimentacoes">+ Nova movimentação</a></div>

<div class="summary">
  <div><span class="muted">Produtos</span><b><?= (int)$tot['n'] ?></b></div>
  <div><span class="muted">Abaixo do mínimo</span><b><?= count($baixos) ?></b></div>
  <div><span class="muted">Valor em estoque</span><b><?= brl($tot['valor']) ?></b></div>
</div>

<div class="grid2">
  <div>
    <h2>⚠️ Estoque baixo</h2>
    <div class="scroll"><table>
      <thead><tr><th>Produto</th><th class="num">Qtd.</th><th class="num">Mín.</th></tr></thead>
      <tbody>
      <?php foreach ($baixos as $p): [$cls, $lbl] = situacao($p); ?>
        <tr><td><?= e($p['nome']) ?></td>
            <td class="num"><span class="badge <?= $cls ?>"><?= qtd($p['quantidade']) ?> <?= e($p['unidade']) ?></span></td>
            <td class="num"><?= qtd($p['estoque_minimo']) ?></td></tr>
      <?php endforeach; if (!$baixos): ?><tr><td colspan="3" class="empty">Tudo certo por aqui.</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </div>
  <div>
    <h2>⏳ Validade (vencidos e próximos 7 dias)</h2>
    <div class="scroll"><table>
      <thead><tr><th>Produto</th><th class="num">Lote</th><th>Validade</th></tr></thead>
      <tbody>
      <?php foreach ($lotes as $l): ?>
        <tr><td><?= e($l['nome']) ?></td>
            <td class="num"><?= qtd($l['quantidade']) ?> <?= e($l['unidade']) ?></td>
            <td><span class="badge <?= $l['vencido'] ? 'b-zero' : 'b-low' ?>"><?= d($l['validade']) ?><?= $l['vencido'] ? ' · vencido' : '' ?></span></td></tr>
      <?php endforeach; if (!$lotes): ?><tr><td colspan="3" class="empty">Nenhum lote próximo do vencimento.</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </div>
</div>

<h2>Últimas movimentações</h2>
<div class="scroll"><table>
  <thead><tr><th>Data</th><th>Produto</th><th>Tipo</th><th>Motivo</th><th class="num">Qtd.</th></tr></thead>
  <tbody>
  <?php foreach ($ultimas as $m): ?>
    <tr><td><?= dt($m['criado_em']) ?></td><td><?= e($m['nome']) ?></td>
        <td><span class="badge <?= $m['tipo'] === 'entrada' ? 'b-in' : 'b-out' ?>"><?= $m['tipo'] === 'entrada' ? 'Entrada' : 'Saída' ?></span></td>
        <td><?= e(motivo_label($m['tipo'], $m['motivo'])) ?></td>
        <td class="num"><?= qtd($m['quantidade']) ?> <?= e($m['unidade']) ?></td></tr>
  <?php endforeach; if (!$ultimas): ?><tr><td colspan="5" class="empty">Sem movimentações ainda.</td></tr><?php endif; ?>
  </tbody>
</table></div>
<?php layout_end();
