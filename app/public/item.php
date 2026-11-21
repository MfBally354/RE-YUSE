<?php
require __DIR__ . '/../inc/layout.php';
$u = user();
$id = (int)g('id');
$it = q("SELECT i.*, s.name seller, s.jurusan, s.angkatan, s.whatsapp, c.name cat_name, COALESCE(i.promo_until>NOW(),0) promo
         FROM items i JOIN users s ON s.id=i.user_id JOIN categories c ON c.id=i.category_id WHERE i.id=?", [$id])->fetch();
if (!$it) { http_response_code(404); head('Tidak ditemukan'); echo '<div class="empty"><p><b>Barang tidak ditemukan.</b></p><a class="btn" href="/">Kembali ke beranda</a></div>'; foot(); exit; }
$imgs = q('SELECT * FROM item_images WHERE item_id=? ORDER BY sort,id', [$id])->fetchAll();
$mine = $u && ((int)$u['id'] === (int)$it['user_id'] || $u['role'] === 'admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $mine) {
    csrf_check();
    $a = p('do');
    if ($a === 'sold' || $a === 'reopen') { q('UPDATE items SET status=? WHERE id=?', [$a === 'sold' ? 'terjual' : 'aktif', $id]); flash('Status barang diperbarui.'); }
    if ($a === 'delete') {
        foreach ($imgs as $im) @unlink(__DIR__ . '/' . $im['path']);
        q('DELETE FROM items WHERE id=?', [$id]); flash('Barang dihapus.'); redirect('/');
    }
    redirect('/item.php?id=' . $id);
}
if (empty($_SESSION['seen'][$id])) { q('UPDATE items SET views=views+1 WHERE id=?', [$id]); $_SESSION['seen'][$id] = 1; $it['views']++; }
$fav = $u && q('SELECT 1 FROM favorites WHERE user_id=? AND item_id=?', [$u['id'], $id])->fetch();

head($it['title']);
?>
<div class="detail">
  <div>
    <div class="gal-main"><?php if ($imgs): ?><img id="gal" src="/<?= e($imgs[0]['path']) ?>" alt="<?= e($it['title']) ?>"><?php else: ?><span class="noimg"><?= icon('image', 48) ?></span><?php endif; ?></div>
    <?php if (count($imgs) > 1): ?><div class="thumbs"><?php foreach ($imgs as $i => $im): ?>
      <button type="button" data-src="/<?= e($im['path']) ?>" aria-current="<?= $i ? 'false' : 'true' ?>" aria-label="Foto <?= $i + 1 ?>"><img src="/<?= e($im['path']) ?>" alt=""></button>
    <?php endforeach; ?></div><?php endif; ?>
  </div>
  <div class="info">
    <div>
      <span class="badge"><?= e(CONDITIONS[$it['kondisi']]) ?></span>
      <?php if ($it['promo']): ?><span class="badge"><?= icon('bolt', 12) ?> Unggulan</span><?php endif; ?>
      <?php if ($it['status'] === 'terjual'): ?><span class="badge sold">Terjual</span><?php endif; ?>
    </div>
    <h1><?= e($it['title']) ?></h1>
    <div class="price"><?= money($it['price']) ?></div>
    <dl class="facts">
      <dt>Kategori</dt><dd><?= e($it['cat_name']) ?></dd>
      <?php if ($it['course']): ?><dt>Mata kuliah</dt><dd><?= e($it['course']) ?></dd><?php endif; ?>
      <dt>Diunggah</dt><dd><?= ago($it['created_at']) ?></dd>
      <dt>Dilihat</dt><dd><?= (int)$it['views'] ?> kali</dd>
    </dl>
    <?php if ($it['description']): ?><p class="desc"><?= e($it['description']) ?></p><?php endif; ?>
    <a class="who" href="/profile.php?id=<?= $it['user_id'] ?>">
      <span class="avatar"><?= e(mb_strtoupper(mb_substr($it['seller'], 0, 1))) ?></span>
      <span><b><?= e($it['seller']) ?></b> <span class="ok"><?= icon('check', 12) ?> Terverifikasi</span><br><span class="meta"><?= e($it['jurusan']) ?> · Angkatan <?= (int)$it['angkatan'] ?></span></span>
    </a>
    <div class="cta">
      <?php if ($mine): ?>
        <a class="btn primary" href="/sell.php?id=<?= $id ?>">Edit barang</a>
        <a class="btn" href="/promo.php?item=<?= $id ?>"><?= icon('bolt', 16) ?> Promosikan</a>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="<?= $it['status'] === 'aktif' ? 'sold' : 'reopen' ?>"><button class="btn"><?= $it['status'] === 'aktif' ? 'Tandai terjual' : 'Aktifkan lagi' ?></button></form>
        <form method="post" onsubmit="return confirm('Hapus barang ini secara permanen?')"><?= csrf_field() ?><input type="hidden" name="do" value="delete"><button class="btn danger">Hapus</button></form>
      <?php else: ?>
        <a class="btn primary" href="/chat.php?item=<?= $id ?>"><?= icon('chat', 16) ?> Chat penjual</a>
        <?php if ($u && $it['whatsapp']): ?><a class="btn" target="_blank" rel="noopener" href="<?= e(wa_link($it['whatsapp'], 'Halo, aku tertarik dengan "' . $it['title'] . '" di Preloved UNNES.')) ?>">WhatsApp</a><?php endif; ?>
        <button type="button" class="btn fav-btn<?= $fav ? ' on' : '' ?>" data-fav="<?= $id ?>"><?= icon('heart', 16) ?> Simpan</button>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php foot();
