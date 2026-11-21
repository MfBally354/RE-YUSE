<?php
require __DIR__ . '/../inc/layout.php';
$u = require_login();
$mine = q("SELECT id,title FROM items WHERE user_id=? AND status='aktif' ORDER BY created_at DESC", [$u['id']])->fetchAll();
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check(); $item = (int)p('item_id'); $days = (int)p('days');
    if (!in_array($item, array_column($mine, 'id')) || !isset(PROMO_PACKAGES[$days])) $err = 'Pilih barang dan paket promosi.';
    elseif (q("SELECT 1 FROM promo_requests WHERE item_id=? AND status='menunggu'", [$item])->fetch()) $err = 'Barang ini masih punya pengajuan yang menunggu konfirmasi.';
    else {
        q('INSERT INTO promo_requests (item_id,user_id,days,price) VALUES (?,?,?,?)', [$item, $u['id'], $days, PROMO_PACKAGES[$days]]);
        flash('Pengajuan promosi dikirim. Lakukan pembayaran, lalu admin akan mengaktifkannya.'); redirect('/promo.php');
    }
}
$reqs = q('SELECT r.*, i.title, i.promo_until FROM promo_requests r JOIN items i ON i.id=r.item_id WHERE r.user_id=? ORDER BY r.id DESC LIMIT 20', [$u['id']])->fetchAll();
$sel = (int)g('item');
head('Promosi', ['bare' => true]);
?>
<div class="panel auth">
  <h1>Promosikan barang</h1>
  <p class="help">Barang berpromo tampil paling atas di katalog dan diberi tanda “Unggulan”. Listing biasa tetap gratis.</p>
  <?php if ($err): ?><div class="flash err"><?= e($err) ?></div><?php endif; ?>
  <?php if (!$mine): ?><p>Kamu belum punya barang aktif. <a href="/sell.php" style="color:var(--accent);font-weight:700">Jual barang dulu</a>.</p>
  <?php else: ?>
  <form method="post" class="form"><?= csrf_field() ?>
    <label>Barang<select name="item_id" required><?php foreach ($mine as $m) echo '<option value="' . $m['id'] . '"' . ($sel === (int)$m['id'] ? ' selected' : '') . '>' . e($m['title']) . '</option>'; ?></select></label>
    <div><b style="font-size:14px">Paket</b>
      <div class="pills" style="margin-top:8px"><?php foreach (PROMO_PACKAGES as $d => $pr): ?><label><input type="radio" name="days" value="<?= $d ?>" <?= $d === 7 ? 'checked' : '' ?>><span><?= $d ?> hari · <?= money($pr) ?></span></label><?php endforeach; ?></div></div>
    <p class="help"><?= e(env('PROMO_PAYMENT_INFO', 'Pembayaran dikonfirmasi manual oleh admin.')) ?></p>
    <button class="btn primary block"><?= icon('bolt', 16) ?> Ajukan promosi</button>
  </form>
  <?php endif; ?>
  <?php if ($reqs): ?>
    <h2 style="margin:28px 0 8px;font-size:18px">Riwayat pengajuan</h2>
    <div class="scroll"><table class="tbl"><tr><th>Barang</th><th>Paket</th><th>Status</th></tr>
    <?php foreach ($reqs as $r): ?><tr><td><a href="/item.php?id=<?= $r['item_id'] ?>"><?= e($r['title']) ?></a></td><td><?= $r['days'] ?> hari<br><span class="meta"><?= money($r['price']) ?></span></td>
      <td><span class="badge <?= $r['status'] === 'menunggu' ? 'wait' : ($r['status'] === 'ditolak' ? 'sold' : '') ?>"><?= ucfirst($r['status']) ?></span><?php if ($r['status'] === 'aktif' && $r['promo_until']): ?><br><span class="meta">s.d. <?= date('d M Y H:i', strtotime($r['promo_until'])) ?></span><?php endif; ?></td></tr><?php endforeach; ?>
    </table></div>
  <?php endif; ?>
</div>
<?php foot();
