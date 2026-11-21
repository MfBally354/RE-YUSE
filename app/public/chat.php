<?php
require __DIR__ . '/../inc/layout.php';
$u = require_login(); $uid = (int)$u['id'];

if (g('item') !== '') {   // mulai / buka percakapan dari halaman barang
    $it = q('SELECT id,user_id FROM items WHERE id=?', [(int)g('item')])->fetch();
    if (!$it) redirect('/');
    if ((int)$it['user_id'] === $uid) { flash('Ini barangmu sendiri.', 'err'); redirect('/item.php?id=' . $it['id']); }
    q('INSERT IGNORE INTO conversations (item_id,buyer_id,seller_id) VALUES (?,?,?)', [$it['id'], $uid, $it['user_id']]);
    $cid = q('SELECT id FROM conversations WHERE item_id=? AND buyer_id=?', [$it['id'], $uid])->fetchColumn();
    redirect('/chat.php?c=' . $cid);
}
$convs = q("SELECT cv.id, i.title, IF(cv.buyer_id=?, sl.name, bu.name) other,
    (SELECT body FROM messages WHERE conversation_id=cv.id ORDER BY id DESC LIMIT 1) last_body,
    COALESCE((SELECT MAX(id) FROM messages WHERE conversation_id=cv.id),0) last_id,
    (SELECT COUNT(*) FROM messages m WHERE m.conversation_id=cv.id AND m.sender_id<>? AND m.read_at IS NULL) unread
    FROM conversations cv JOIN items i ON i.id=cv.item_id JOIN users bu ON bu.id=cv.buyer_id JOIN users sl ON sl.id=cv.seller_id
    WHERE cv.buyer_id=? OR cv.seller_id=? ORDER BY last_id DESC, cv.id DESC", [$uid, $uid, $uid, $uid])->fetchAll();

$cid = (int)g('c'); $cur = null; $msgs = [];
if ($cid) {
    $cur = q("SELECT cv.*, i.title, i.price, (SELECT path FROM item_images WHERE item_id=i.id ORDER BY sort,id LIMIT 1) cover,
        IF(cv.buyer_id=?, sl.name, bu.name) other FROM conversations cv JOIN items i ON i.id=cv.item_id
        JOIN users bu ON bu.id=cv.buyer_id JOIN users sl ON sl.id=cv.seller_id WHERE cv.id=? AND (cv.buyer_id=? OR cv.seller_id=?)", [$uid, $cid, $uid, $uid])->fetch();
    if (!$cur) { http_response_code(404); exit('Percakapan tidak ditemukan.'); }
    q('UPDATE messages SET read_at=NOW() WHERE conversation_id=? AND sender_id<>? AND read_at IS NULL', [$cid, $uid]);
    $msgs = q('SELECT * FROM (SELECT * FROM messages WHERE conversation_id=? ORDER BY id DESC LIMIT 200) t ORDER BY id', [$cid])->fetchAll();
}
head('Pesan');
?>
<div class="head"><div><h1>Pesan</h1><p>Negosiasi harga dan atur janji temu di area kampus</p></div></div>
<div class="chat<?= $cur ? ' has-thread' : '' ?>">
  <div class="clist">
    <?php if (!$convs) echo '<p class="help" style="padding:18px">Belum ada percakapan. Buka sebuah barang lalu pilih “Chat penjual”.</p>'; ?>
    <?php foreach ($convs as $c): ?>
      <a href="/chat.php?c=<?= $c['id'] ?>" class="<?= $c['id'] == $cid ? 'on' : '' ?>">
        <b><span><?= e($c['other']) ?></span><?php if ($c['unread']): ?><span class="unread"><?= $c['unread'] ?></span><?php endif; ?></b>
        <small><?= e($c['title']) ?></small><small><?= e($c['last_body'] ?? 'Belum ada pesan') ?></small>
      </a>
    <?php endforeach; ?>
  </div>
  <div class="thread">
  <?php if ($cur): ?>
    <div class="thead">
      <a class="back" href="/chat.php">←</a>
      <?php if ($cur['cover']): ?><img src="/<?= e($cur['cover']) ?>" alt=""><?php endif; ?>
      <div><b><?= e($cur['other']) ?></b><br><a class="meta" href="/item.php?id=<?= $cur['item_id'] ?>"><?= e($cur['title']) ?> · <?= money($cur['price']) ?></a></div>
    </div>
    <div class="msgs" id="msgs" data-conv="<?= $cid ?>" data-last="<?= $msgs ? end($msgs)['id'] : 0 ?>">
      <?php foreach ($msgs as $m): ?><div class="msg<?= (int)$m['sender_id'] === $uid ? ' me' : '' ?>"><?= e($m['body']) ?><time><?= date('H:i', strtotime($m['created_at'])) ?></time></div><?php endforeach; ?>
    </div>
    <form class="compose" id="chat-form"><textarea name="body" maxlength="1000" placeholder="Tulis pesan… (Enter untuk kirim)" aria-label="Pesan" required></textarea><button class="btn primary">Kirim</button></form>
  <?php else: ?>
    <div class="empty" style="margin:auto;border:0"><p>Pilih percakapan di sebelah kiri.</p></div>
  <?php endif; ?>
  </div>
</div>
<?php foot();
