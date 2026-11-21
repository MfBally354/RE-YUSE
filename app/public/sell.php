<?php
require __DIR__ . '/../inc/layout.php';
$u = require_login();
$id = (int)g('id'); $it = null; $imgs = [];
if ($id) {
    $it = q('SELECT * FROM items WHERE id=?', [$id])->fetch();
    if (!$it || ((int)$it['user_id'] !== (int)$u['id'] && $u['role'] !== 'admin')) { http_response_code(403); exit('Barang tidak ditemukan atau bukan milikmu.'); }
    $imgs = q('SELECT * FROM item_images WHERE item_id=? ORDER BY sort,id', [$id])->fetchAll();
}
$cats = q('SELECT id,name FROM categories ORDER BY sort')->fetchAll();
$v = ['title' => $it['title'] ?? '', 'category_id' => (string)($it['category_id'] ?? ''), 'kondisi' => $it['kondisi'] ?? 'bekas',
      'price' => (string)($it['price'] ?? ''), 'course' => $it['course'] ?? '', 'description' => $it['description'] ?? ''];
$err = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($v as $k => $_) $v[$k] = p($k);
    $price = (int)preg_replace('/\D/', '', $v['price']);
    $del = array_filter(array_map('intval', (array)($_POST['del'] ?? [])), fn($d) => in_array($d, array_column($imgs, 'id')));
    $keep = count($imgs) - count($del);
    $new = files_of('photos');
    if (mb_strlen($v['title']) < 3 || mb_strlen($v['title']) > 120) $err[] = 'Judul harus 3–120 karakter.';
    if (!in_array((int)$v['category_id'], array_column($cats, 'id'))) $err[] = 'Pilih kategori.';
    if (!isset(CONDITIONS[$v['kondisi']])) $err[] = 'Pilih kondisi barang.';
    if ($v['price'] === '' || $price > 99999999) $err[] = 'Isi harga dengan benar (angka, maksimal 99.999.999).';
    if (mb_strlen($v['description']) > 2000) $err[] = 'Deskripsi maksimal 2000 karakter.';
    if (mb_strlen($v['course']) > 80) $err[] = 'Nama mata kuliah maksimal 80 karakter.';
    if ($keep + count($new) > 4) $err[] = 'Maksimal 4 foto per barang.';
    if ($keep + count($new) < 1) $err[] = 'Tambahkan minimal 1 foto.';
    $paths = [];
    if (!$err) foreach ($new as $fl) { $pth = save_upload($fl); if ($pth) $paths[] = $pth; else $err[] = 'Foto harus berformat JPG, PNG, atau WebP dengan ukuran maksimal 4 MB.'; }
    if ($err) { foreach ($paths as $pth) @unlink(__DIR__ . '/' . $pth); }
    else {
        $args = [(int)$v['category_id'], $v['title'], $v['description'] ?: null, $price, $v['kondisi'], $v['course'] ?: null];
        if ($it) q('UPDATE items SET category_id=?,title=?,description=?,price=?,kondisi=?,course=? WHERE id=?', [...$args, $id]);
        else { q('INSERT INTO items (category_id,title,description,price,kondisi,course,user_id) VALUES (?,?,?,?,?,?,?)', [...$args, $u['id']]); $id = (int)db()->lastInsertId(); }
        foreach ($imgs as $im) if (in_array((int)$im['id'], $del, true)) { @unlink(__DIR__ . '/' . $im['path']); q('DELETE FROM item_images WHERE id=?', [$im['id']]); }
        foreach ($paths as $i => $pth) q('INSERT INTO item_images (item_id,path,sort) VALUES (?,?,?)', [$id, $pth, count($imgs) + $i]);
        flash($it ? 'Perubahan disimpan.' : 'Barang berhasil diunggah.'); redirect('/item.php?id=' . $id);
    }
}
head($it ? 'Edit barang' : 'Jual barang', ['bare' => true]);
?>
<div class="panel auth">
  <h1><?= $it ? 'Edit barang' : 'Jual barang' ?></h1>
  <p class="help">Foto yang jelas dan harga yang wajar membuat barangmu lebih cepat laku.</p>
  <?php if ($err): ?><ul class="errs"><?php foreach (array_unique($err) as $x) echo '<li>' . e($x) . '</li>'; ?></ul><?php endif; ?>
  <form method="post" enctype="multipart/form-data" class="form">
    <?= csrf_field() ?>
    <label>Judul barang<input type="text" name="title" maxlength="120" required value="<?= e($v['title']) ?>" placeholder="mis. Kalkulator Casio fx-991EX"></label>
    <div class="row">
      <label>Kategori<select name="category_id" required><option value="">Pilih…</option>
        <?php foreach ($cats as $c) echo '<option value="' . $c['id'] . '"' . ((int)$v['category_id'] === (int)$c['id'] ? ' selected' : '') . '>' . e($c['name']) . '</option>'; ?></select></label>
      <label>Kondisi<select name="kondisi"><?php foreach (CONDITIONS as $k => $n) echo '<option value="' . $k . '"' . ($v['kondisi'] === $k ? ' selected' : '') . '>' . $n . '</option>'; ?></select></label>
    </div>
    <div class="row">
      <label>Harga (Rp)<input type="text" inputmode="numeric" name="price" required value="<?= e($v['price']) ?>" placeholder="75000"></label>
      <label>Mata kuliah <small>(opsional)</small><input type="text" name="course" maxlength="80" value="<?= e($v['course']) ?>" placeholder="mis. Basis Data"></label>
    </div>
    <label>Deskripsi <small>(kondisi, alasan dijual, lokasi COD di kampus)</small><textarea name="description" maxlength="2000"><?= e($v['description']) ?></textarea></label>
    <?php if ($imgs): ?>
      <div><b style="font-size:14px">Foto saat ini</b> <span class="help">(centang untuk menghapus)</span>
        <div class="thumbs"><?php foreach ($imgs as $im): ?><label class="delimg"><img src="/<?= e($im['path']) ?>" alt=""><input type="checkbox" name="del[]" value="<?= $im['id'] ?>"> Hapus</label><?php endforeach; ?></div></div>
    <?php endif; ?>
    <label>Foto <small>(maks. 4, JPG/PNG/WebP, 4 MB per foto)</small><input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple data-preview="<?= 4 - count($imgs) ?>"></label>
    <div class="thumbs" id="previews"></div>
    <button class="btn primary block"><?= $it ? 'Simpan perubahan' : 'Unggah barang' ?></button>
  </form>
</div>
<?php foot();
