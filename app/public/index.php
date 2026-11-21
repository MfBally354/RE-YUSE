<?php
require __DIR__ . '/../inc/layout.php';
$u = user();
$view = g('view') === 'saved' ? 'saved' : '';
if ($view && !$u) redirect('/login.php?next=' . urlencode('/?view=saved'));

$f = ['q' => g('q'), 'cat' => g('cat'), 'kondisi' => g('kondisi'), 'min' => g('min'), 'max' => g('max'),
      'course' => g('course'), 'sort' => g('sort') ?: 'terbaru', 'view' => $view];
$where = ["i.status='aktif'"]; $par = [];
$like = fn(string $s) => '%' . addcslashes($s, '%_\\') . '%';
if ($view) { $where[] = 'i.id IN (SELECT item_id FROM favorites WHERE user_id=?)'; $par[] = $u['id']; }
if ($f['q'] !== '') { $where[] = '(i.title LIKE ? OR i.description LIKE ? OR i.course LIKE ?)'; array_push($par, $like($f['q']), $like($f['q']), $like($f['q'])); }
if ($f['cat'] !== '') { $where[] = 'c.slug=?'; $par[] = $f['cat']; }
if (isset(CONDITIONS[$f['kondisi']])) { $where[] = 'i.kondisi=?'; $par[] = $f['kondisi']; }
if ($f['min'] !== '' && ctype_digit($f['min'])) { $where[] = 'i.price>=?'; $par[] = (int)$f['min']; }
if ($f['max'] !== '' && ctype_digit($f['max'])) { $where[] = 'i.price<=?'; $par[] = (int)$f['max']; }
if ($f['course'] !== '') { $where[] = 'i.course LIKE ?'; $par[] = $like($f['course']); }

$sorts = ['terbaru' => 'Terbaru', 'termurah' => 'Harga terendah', 'termahal' => 'Harga tertinggi', 'populer' => 'Terpopuler'];
$order = ['terbaru' => 'i.created_at DESC', 'termurah' => 'i.price ASC', 'termahal' => 'i.price DESC', 'populer' => 'i.views DESC'][$f['sort']] ?? 'i.created_at DESC';
$W = implode(' AND ', $where);
$total = (int)q("SELECT COUNT(*) FROM items i JOIN categories c ON c.id=i.category_id WHERE $W", $par)->fetchColumn();
$per = 12; $pages = max(1, (int)ceil($total / $per));
$page = min($pages, max(1, (int)g('page'))); $off = ($page - 1) * $per;
// Barang berpromo selalu tampil paling atas
$rows = q(CARD_SQL . " WHERE $W ORDER BY promo DESC, $order, i.id DESC LIMIT $per OFFSET $off", $par)->fetchAll();
$favs = fav_set($u);

$h1 = $view ? 'Barang disimpan' : ($f['q'] !== '' ? 'Hasil untuk “' . $f['q'] . '”' : 'Jelajahi barang preloved');
head($view ? 'Disimpan' : 'Beranda', ['browse' => $f]);
?>
<div class="head">
  <div>
    <h1><?= e($h1) ?></h1>
    <p><?= $total ?> barang dari mahasiswa UNNES, deal lewat chat atau WhatsApp</p>
  </div>
  <form method="get" class="sort">
    <?php foreach ($_GET as $k => $v) if (!in_array($k, ['sort', 'page'], true) && is_string($v)) echo '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">'; ?>
    <label for="sort">Urutkan:</label>
    <select id="sort" name="sort" data-autosubmit>
      <?php foreach ($sorts as $k => $v) echo '<option value="' . $k . '"' . ($f['sort'] === $k ? ' selected' : '') . '>' . $v . '</option>'; ?>
    </select>
  </form>
</div>

<?php if (!$rows): ?>
  <div class="empty">
    <p><b><?= $view ? 'Belum ada barang yang kamu simpan.' : 'Belum ada barang yang cocok.' ?></b><br>Coba ubah kata kunci atau filter, atau jual barangmu sendiri.</p>
    <a class="btn primary" href="/sell.php">+ Jual barang</a>
  </div>
<?php else: ?>
  <div class="grid"><?php foreach ($rows as $r) card($r, $favs); ?></div>
  <?php if ($pages > 1): ?>
    <nav class="pager" aria-label="Halaman">
      <?php if ($page > 1) echo '<a class="btn sm" href="' . e(qs(['page' => $page - 1])) . '">Sebelumnya</a>'; ?>
      <span class="btn sm" style="pointer-events:none">Halaman <?= $page ?> / <?= $pages ?></span>
      <?php if ($page < $pages) echo '<a class="btn sm" href="' . e(qs(['page' => $page + 1])) . '">Berikutnya</a>'; ?>
    </nav>
  <?php endif; ?>
<?php endif; ?>
<?php foot();
