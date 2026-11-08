<?php
require __DIR__ . '/../lib/bootstrap.php';
require_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $acao = $_POST['acao'] ?? '';
    try {
        $pdo->beginTransaction();

        if ($acao === 'criar') {
            $pid = (int)($_POST['produto_id'] ?? 0);
            $tipo = $_POST['tipo'] ?? '';
            $motivo = $_POST['motivo'] ?? '';
            $q = num($_POST['quantidade'] ?? 0);
            if (!isset(MOTIVOS[$tipo][$motivo])) throw new RuntimeException('Tipo ou motivo inválido.');
            if ($q <= 0) throw new RuntimeException('Informe uma quantidade maior que zero.');

            $st = $pdo->prepare('SELECT * FROM produtos WHERE id = ? FOR UPDATE');
            $st->execute([$pid]);
            $prod = $st->fetch();
            if (!$prod) throw new RuntimeException('Produto não encontrado.');
            if ($tipo === 'saida' && $q > (float)$prod['quantidade']) {
                throw new RuntimeException('Saldo insuficiente: disponível ' . qtd($prod['quantidade']) . ' ' . $prod['unidade'] . '.');
            }

            $compra = $tipo === 'entrada' && $motivo === 'compra';
            $custo = $compra && trim($_POST['custo_unitario'] ?? '') !== '' ? num($_POST['custo_unitario']) : null;
            $validade = $compra && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['validade'] ?? '') ? $_POST['validade'] : null;
            $nota = $compra ? (trim($_POST['nota'] ?? '') ?: null) : null;

            $pdo->prepare('INSERT INTO movimentacoes (produto_id, tipo, motivo, quantidade, custo_unitario, nota, validade, observacao) VALUES (?,?,?,?,?,?,?,?)')
                ->execute([$pid, $tipo, $motivo, $q, $custo, $nota, $validade, trim($_POST['observacao'] ?? '') ?: null]);
            $delta = $tipo === 'entrada' ? $q : -$q;
            $pdo->prepare('UPDATE produtos SET quantidade = quantidade + ?' . ($custo !== null ? ', custo = ?' : '') . ' WHERE id = ?')
                ->execute($custo !== null ? [$delta, $custo, $pid] : [$delta, $pid]);
            flash('Movimentação registrada.');

        } elseif ($acao === 'excluir') {
            $st = $pdo->prepare('SELECT * FROM movimentacoes WHERE id = ? FOR UPDATE');
            $st->execute([(int)($_POST['id'] ?? 0)]);
            $m = $st->fetch();
            if (!$m) throw new RuntimeException('Movimentação não encontrada.');

            $st = $pdo->prepare('SELECT quantidade FROM produtos WHERE id = ? FOR UPDATE');
            $st->execute([$m['produto_id']]);
            $saldo = (float)$st->fetchColumn();
            $delta = $m['tipo'] === 'entrada' ? -(float)$m['quantidade'] : (float)$m['quantidade'];
            if ($saldo + $delta < 0) throw new RuntimeException('Não é possível excluir: o saldo ficaria negativo.');

            $pdo->prepare('UPDATE produtos SET quantidade = quantidade + ? WHERE id = ?')->execute([$delta, $m['produto_id']]);
            $pdo->prepare('DELETE FROM movimentacoes WHERE id = ?')->execute([$m['id']]);
            flash('Movimentação excluída e saldo ajustado.');
        }
        $pdo->commit();
    } catch (RuntimeException $ex) {
        $pdo->rollBack();
        flash($ex->getMessage(), 'err');
    } catch (PDOException $ex) {
        $pdo->rollBack();
        flash('Erro ao gravar a movimentação.', 'err');
    }
    redirect('/movimentacoes' . (!empty($_POST['voltar']) ? '?' . ltrim((string)$_POST['voltar'], '?') : ''));
}

/* ---------- Filtros ---------- */
$fp   = (int)($_GET['produto'] ?? 0);
$ftipo = in_array($_GET['tipo'] ?? '', ['entrada', 'saida'], true) ? $_GET['tipo'] : '';
$fmot = $_GET['motivo'] ?? '';
$de   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['de'] ?? '') ? $_GET['de'] : '';
$ate  = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['ate'] ?? '') ? $_GET['ate'] : '';

$where = []; $args = [];
if ($fp)    { $where[] = 'm.produto_id = ?'; $args[] = $fp; }
if ($ftipo) { $where[] = 'm.tipo = ?'; $args[] = $ftipo; }
if ($fmot !== '') { $where[] = 'm.motivo = ?'; $args[] = $fmot; }
if ($de)    { $where[] = 'm.criado_em >= ?::date'; $args[] = $de; }
if ($ate)   { $where[] = 'm.criado_em < (?::date + 1)'; $args[] = $ate; }
$w = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$st = $pdo->prepare("SELECT
    COUNT(*) FILTER (WHERE m.tipo='entrada') AS entradas,
    COUNT(*) FILTER (WHERE m.tipo='saida') AS saidas,
    COALESCE(SUM(m.quantidade * p.custo) FILTER (WHERE m.motivo IN ('perda','vencimento') AND m.tipo='saida'),0) AS perdas,
    COUNT(*) AS total
    FROM movimentacoes m JOIN produtos p ON p.id = m.produto_id $w");
$st->execute($args);
$res = $st->fetch();

$porPag = 20;
$pag = max(1, (int)($_GET['p'] ?? 1));
$paginas = max(1, (int)ceil($res['total'] / $porPag));
$pag = min($pag, $paginas);

$st = $pdo->prepare("SELECT m.*, p.nome, p.unidade FROM movimentacoes m JOIN produtos p ON p.id = m.produto_id $w
    ORDER BY m.criado_em DESC, m.id DESC LIMIT $porPag OFFSET " . (($pag - 1) * $porPag));
$st->execute($args);
$movs = $st->fetchAll();

$produtos = $pdo->query('SELECT id, nome, sku, unidade, quantidade FROM produtos ORDER BY nome')->fetchAll();

$qs = $_GET; unset($qs['p']);
$base = '/movimentacoes?' . http_build_query($qs) . ($qs ? '&' : '');
$voltar = http_build_query($_GET);

layout_start('Movimentações', '/movimentacoes'); ?>
<div class="head"><h1>Movimentações</h1></div>

<form class="card form" method="post" id="nova">
  <?= csrf_field() ?><input type="hidden" name="acao" value="criar">
  <label style="flex:1 1 240px">Produto
    <select name="produto_id" required>
      <option value="">Selecione…</option>
      <?php foreach ($produtos as $pr): ?>
        <option value="<?= $pr['id'] ?>" <?= $fp === (int)$pr['id'] ? 'selected' : '' ?>><?= e($pr['nome']) ?> (<?= e($pr['sku']) ?> · <?= qtd($pr['quantidade']) ?> <?= e($pr['unidade']) ?>)</option>
      <?php endforeach; ?>
    </select></label>
  <label>Tipo
    <select name="tipo" id="tipo"><option value="entrada">Entrada</option><option value="saida">Saída</option></select></label>
  <label>Motivo<select name="motivo" id="motivo"></select></label>
  <label>Quantidade<input name="quantidade" required placeholder="Ex.: 2,5"></label>
  <label class="compra">Custo unit. (R$)<input name="custo_unitario" placeholder="opcional"></label>
  <label class="compra">Nota fiscal<input name="nota"></label>
  <label class="compra">Validade do lote<input type="date" name="validade"></label>
  <label style="flex:1 1 200px">Observação<input name="observacao"></label>
  <button class="btn">Registrar</button>
  <input type="hidden" name="voltar" value="<?= e($voltar) ?>">
</form>

<form class="card filters" method="get">
  <label>Produto
    <select name="produto"><option value="0">Todos</option>
      <?php foreach ($produtos as $pr): ?><option value="<?= $pr['id'] ?>" <?= $fp === (int)$pr['id'] ? 'selected' : '' ?>><?= e($pr['nome']) ?></option><?php endforeach; ?>
    </select></label>
  <label>Tipo
    <select name="tipo"><option value="">Todos</option>
      <option value="entrada" <?= $ftipo === 'entrada' ? 'selected' : '' ?>>Entrada</option>
      <option value="saida" <?= $ftipo === 'saida' ? 'selected' : '' ?>>Saída</option></select></label>
  <label>Motivo
    <select name="motivo"><option value="">Todos</option>
      <?php $todos = []; foreach (MOTIVOS as $t => $ms) foreach ($ms as $k => $lbl) $todos[$k] = $todos[$k] ?? $lbl; ?>
      <?php foreach ($todos as $k => $lbl): ?><option value="<?= $k ?>" <?= $fmot === $k ? 'selected' : '' ?>><?= e($lbl) ?></option><?php endforeach; ?>
    </select></label>
  <label>De<input type="date" name="de" value="<?= e($de) ?>"></label>
  <label>Até<input type="date" name="ate" value="<?= e($ate) ?>"></label>
  <button class="btn">Filtrar</button>
  <a class="btn sec" href="/movimentacoes">Limpar</a>
</form>

<div class="summary">
  <div><span class="muted">Entradas</span><b><?= (int)$res['entradas'] ?></b></div>
  <div><span class="muted">Saídas</span><b><?= (int)$res['saidas'] ?></b></div>
  <div><span class="muted">Perdas (valor)</span><b><?= brl($res['perdas']) ?></b></div>
</div>

<div class="scroll"><table>
  <thead><tr><th>Data</th><th>Produto</th><th>Tipo</th><th>Motivo</th><th class="num">Qtd.</th><th>Detalhes</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($movs as $m):
      $det = [];
      if ($m['custo_unitario'] !== null) $det[] = brl($m['custo_unitario']) . '/' . $m['unidade'];
      if ($m['nota']) $det[] = 'NF ' . $m['nota'];
      if ($m['validade']) $det[] = 'val. ' . d($m['validade']);
      if ($m['observacao']) $det[] = $m['observacao']; ?>
    <tr>
      <td><?= dt($m['criado_em']) ?></td>
      <td><?= e($m['nome']) ?></td>
      <td><span class="badge <?= $m['tipo'] === 'entrada' ? 'b-in' : 'b-out' ?>"><?= $m['tipo'] === 'entrada' ? 'Entrada' : 'Saída' ?></span></td>
      <td><?= e(motivo_label($m['tipo'], $m['motivo'])) ?></td>
      <td class="num"><?= qtd($m['quantidade']) ?> <?= e($m['unidade']) ?></td>
      <td class="muted"><?= e(implode(' · ', $det)) ?></td>
      <td class="act">
        <form method="post" onsubmit="return confirm('Excluir esta movimentação e reverter o saldo?')">
          <?= csrf_field() ?><input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?= $m['id'] ?>">
          <input type="hidden" name="voltar" value="<?= e($voltar) ?>">
          <button class="btn del sm">Excluir</button>
        </form>
      </td>
    </tr>
  <?php endforeach; if (!$movs): ?><tr><td colspan="7" class="empty">Nenhuma movimentação encontrada.</td></tr><?php endif; ?>
  </tbody>
</table></div>

<?php if ($paginas > 1): ?>
<div class="pager">
  <?php if ($pag > 1): ?><a class="btn sec sm" href="<?= e($base) ?>p=<?= $pag - 1 ?>">← Anterior</a><?php endif; ?>
  <span class="muted">Página <?= $pag ?> de <?= $paginas ?></span>
  <?php if ($pag < $paginas): ?><a class="btn sec sm" href="<?= e($base) ?>p=<?= $pag + 1 ?>">Próxima →</a><?php endif; ?>
</div>
<?php endif; ?>

<script>
const MOTIVOS = <?= json_encode(MOTIVOS, JSON_UNESCAPED_UNICODE) ?>;
const tipo = document.getElementById('tipo'), motivo = document.getElementById('motivo');
function atualiza() {
  motivo.innerHTML = Object.entries(MOTIVOS[tipo.value]).map(([k, v]) => `<option value="${k}">${v}</option>`).join('');
  togglCompra();
}
function togglCompra() {
  const on = tipo.value === 'entrada' && motivo.value === 'compra';
  document.querySelectorAll('.compra').forEach(el => el.classList.toggle('hide', !on));
}
tipo.addEventListener('change', atualiza);
motivo.addEventListener('change', togglCompra);
atualiza();
</script>
<?php layout_end();
