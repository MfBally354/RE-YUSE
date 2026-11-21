<?php
require __DIR__ . '/../inc/layout.php';
require_admin();
$tab = in_array(g('tab'), ['items', 'users'], true) ? g('tab') : 'promo';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check(); $do = p('do'); $id = (int)p('id');
    if ($do === 'promo_ok' || $do === 'promo_no') {
        $r = q("SELECT * FROM promo_requests WHERE id=? AND status='menunggu'", [$id])->fetch();
        if ($r && $do === 'promo_ok') {
            q('UPDATE items SET promo_until = DATE_ADD(GREATEST(NOW(), COALESCE(promo_until, NOW())), INTERVAL ? DAY) WHERE id=?', [$r['days'], $r['item_id']]);
            q("UPDATE promo_requests SET status='aktif' WHERE id=?", [$id]); flash('Promosi diaktifkan.');
        } elseif ($r) { q("UPDATE promo_requests SET status='ditolak' WHERE id=?", [$id]); flash('Pengajuan ditolak.'); }
    }
    if ($do === 'item_del') {
        foreach (q('SELECT path FROM item_images WHERE item_id=?', [$id])->fetchAll(PDO::FETCH_COLUMN) as $pth) @unlink(__DIR__ . '/' . $pth);
        q('DELETE FROM items WHERE id=?', [$id]); flash('Barang dihapus.');
    }
    if ($do === 'user_verify') { q('UPDATE users SET verified=1, verify_token=NULL WHERE id=?', [$id]); flash('Pengguna diverifikasi manual.'); }
    redirect('/admin.php?tab=' . $tab);
}
$stat = q("SELECT (SELECT COUNT(*) FROM users) u, (SELECT COUNT(*) FROM items WHERE status='aktif') i, (SELECT COUNT(*) FROM promo_requests WHERE status='menunggu') p")->fetch();
head('Admin');
$btn = fn($do, $id, $label, $cls = '') => '<form method="post" style="display:inline">' . csrf_field() . '<input type="hidden" name="do" value="' . $do . '"><input type="hidden" name="id" value="' . $id . '"><button class="btn sm ' . $cls . '">' . $label . '</button></form>';
?>
<div class="head"><div><h1>Admin</h1><p><?= $stat['u'] ?> pengguna · <?= $stat['i'] ?> barang aktif · <?= $stat['p'] ?> promosi menunggu</p></div></div>
<div class="tabs"><?php foreach (['promo' => 'Promosi', 'items' => 'Barang', 'users' => 'Pengguna'] as $k => $l) echo '<a class="btn sm' . ($tab === $k ? ' primary' : '') . '" href="?tab=' . $k . '">' . $l . '</a>'; ?></div>
<div class="panel scroll">
<?php if ($tab === 'promo'): $rs = q('SELECT r.*, i.title, u.name FROM promo_requests r JOIN items i ON i.id=r.item_id JOIN users u ON u.id=r.user_id ORDER BY FIELD(r.status,"menunggu","aktif","ditolak"), r.id DESC LIMIT 50')->fetchAll(); ?>
  <table class="tbl"><tr><th>Barang</th><th>Penjual</th><th>Paket</th><th>Status</th><th></th></tr>
  <?php foreach ($rs as $r): ?><tr><td><a href="/item.php?id=<?= $r['item_id'] ?>"><?= e($r['title']) ?></a></td><td><?= e($r['name']) ?></td><td><?= $r['days'] ?> hari · <?= money($r['price']) ?></td><td><?= e($r['status']) ?></td>
    <td><?= $r['status'] === 'menunggu' ? $btn('promo_ok', $r['id'], 'Aktifkan', 'primary') . ' ' . $btn('promo_no', $r['id'], 'Tolak') : '' ?></td></tr><?php endforeach; ?>
  </table><?php if (!$rs) echo '<p class="help">Belum ada pengajuan.</p>'; ?>
<?php elseif ($tab === 'items'): $rs = q('SELECT i.id,i.title,i.price,i.status,i.created_at,u.name FROM items i JOIN users u ON u.id=i.user_id ORDER BY i.id DESC LIMIT 50')->fetchAll(); ?>
  <table class="tbl"><tr><th>Barang</th><th>Penjual</th><th>Harga</th><th>Status</th><th></th></tr>
  <?php foreach ($rs as $r): ?><tr><td><a href="/item.php?id=<?= $r['id'] ?>"><?= e($r['title']) ?></a></td><td><?= e($r['name']) ?></td><td><?= money($r['price']) ?></td><td><?= e($r['status']) ?></td>
    <td><form method="post" style="display:inline" onsubmit="return confirm('Hapus barang ini?')"><?= csrf_field() ?><input type="hidden" name="do" value="item_del"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn sm danger">Hapus</button></form></td></tr><?php endforeach; ?>
  </table>
<?php else: $rs = q('SELECT id,name,email,jurusan,angkatan,verified,role FROM users ORDER BY id DESC LIMIT 50')->fetchAll(); ?>
  <table class="tbl"><tr><th>Nama</th><th>Email</th><th>Jurusan</th><th>Status</th><th></th></tr>
  <?php foreach ($rs as $r): ?><tr><td><?= e($r['name']) ?></td><td><?= e($r['email']) ?></td><td><?= e($r['jurusan']) ?> '<?= substr((string)$r['angkatan'], 2) ?></td><td><?= $r['verified'] ? 'Terverifikasi' : 'Belum' ?><?= $r['role'] === 'admin' ? ' · admin' : '' ?></td>
    <td><?= $r['verified'] ? '' : $btn('user_verify', $r['id'], 'Verifikasi') ?></td></tr><?php endforeach; ?>
  </table>
<?php endif; ?>
</div>
<?php foot();
