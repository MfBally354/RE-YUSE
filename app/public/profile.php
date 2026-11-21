<?php
require __DIR__ . '/../inc/layout.php';
$me = require_login();
$id = (int)(g('id') ?: $me['id']);
$pu = q('SELECT id,name,jurusan,angkatan,whatsapp,created_at FROM users WHERE id=?', [$id])->fetch();
if (!$pu) { http_response_code(404); exit('Pengguna tidak ditemukan.'); }
$own = (int)$pu['id'] === (int)$me['id'];
$err = [];
if ($own && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $n = p('name'); $j = p('jurusan'); $a = p('angkatan'); $w = p('whatsapp');
    if (mb_strlen($n) < 2 || mb_strlen($n) > 80) $err[] = 'Nama harus 2–80 karakter.';
    if ($j === '' || mb_strlen($j) > 80) $err[] = 'Isi jurusan.';
    if (!ctype_digit($a) || $a < 2015 || $a > (int)date('Y')) $err[] = 'Angkatan tidak valid.';
    if ($w !== '' && !preg_match('/^[0-9+\- ]{8,20}$/', $w)) $err[] = 'Nomor WhatsApp tidak valid.';
    if (!$err) { q('UPDATE users SET name=?,jurusan=?,angkatan=?,whatsapp=? WHERE id=?', [$n, $j, (int)$a, $w ?: null, $id]); flash('Profil diperbarui.'); redirect('/profile.php'); }
}
$st = $own ? '' : "AND i.status='aktif'";
$rows = q(CARD_SQL . " WHERE i.user_id=? $st ORDER BY i.created_at DESC", [$id])->fetchAll();
$favs = fav_set($me);
head($own ? 'Profil saya' : $pu['name']);
?>
<div class="head"><div>
  <h1><?= e($pu['name']) ?></h1>
  <p><?= e($pu['jurusan']) ?> · Angkatan <?= (int)$pu['angkatan'] ?> <span class="ok"><?= icon('check', 12) ?> Terverifikasi</span></p>
</div></div>
<?php if ($own): ?>
<details class="panel" style="margin-bottom:28px" <?= $err ? 'open' : '' ?>>
  <summary style="cursor:pointer;font-weight:700">Ubah profil</summary>
  <?php if ($err): ?><ul class="errs" style="margin-top:14px"><?php foreach ($err as $x) echo '<li>' . e($x) . '</li>'; ?></ul><?php endif; ?>
  <form method="post" class="form" style="margin-top:16px"><?= csrf_field() ?>
    <div class="row"><label>Nama<input type="text" name="name" required value="<?= e($pu['name']) ?>"></label>
    <label>Jurusan<input type="text" name="jurusan" required value="<?= e($pu['jurusan']) ?>"></label></div>
    <div class="row"><label>Angkatan<input type="text" name="angkatan" required inputmode="numeric" value="<?= (int)$pu['angkatan'] ?>"></label>
    <label>WhatsApp<input type="tel" name="whatsapp" value="<?= e($pu['whatsapp']) ?>"></label></div>
    <button class="btn primary">Simpan</button>
  </form>
</details>
<?php endif; ?>
<h2 style="margin-bottom:18px;font-size:20px"><?= $own ? 'Barang jualanku' : 'Barang dijual' ?> (<?= count($rows) ?>)</h2>
<?php if (!$rows): ?><div class="empty"><p>Belum ada barang.</p><?php if ($own) echo '<a class="btn primary" href="/sell.php">+ Jual barang</a>'; ?></div>
<?php else: ?><div class="grid"><?php foreach ($rows as $r) card($r, $favs); ?></div><?php endif; ?>
<?php foot();
