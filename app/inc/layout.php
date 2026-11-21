<?php
require_once __DIR__ . '/bootstrap.php';

function qs(array $over): string {
    $q = array_filter(array_merge($_GET, $over), fn($v) => $v !== null && $v !== '');
    return '/?' . http_build_query($q);
}

function head(string $title, array $o = []): void {
    $u = user(); $self = basename($_SERVER['SCRIPT_NAME']); $view = g('view');
    $unread = $u ? (int)q('SELECT COUNT(*) FROM messages m JOIN conversations c ON c.id=m.conversation_id
        WHERE (c.buyer_id=? OR c.seller_id=?) AND m.sender_id<>? AND m.read_at IS NULL', [$u['id'], $u['id'], $u['id']])->fetchColumn() : 0;
    $total = (int)q("SELECT COUNT(*) FROM items WHERE status='aktif'")->fetchColumn();
    $nav = [
        ['/', 'store', 'Jelajahi semua', $self === 'index.php' && $view !== 'saved', $total ?: null],
        ['/?view=saved', 'heart', 'Disimpan', $self === 'index.php' && $view === 'saved', null],
        ['/chat.php', 'chat', 'Pesan', $self === 'chat.php', $unread ?: null],
        ['/sell.php', 'tag', 'Jual barang', $self === 'sell.php', null],
        ['/promo.php', 'bolt', 'Promosi', $self === 'promo.php', null],
    ];
    if ($u && $u['role'] === 'admin') $nav[] = ['/admin.php', 'shield', 'Admin', $self === 'admin.php', null];
    $bare = !empty($o['bare']);
    ?><!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title) ?> · Re-Yuse!</title>
<meta name="csrf" content="<?= csrf() ?>">
<script>try{document.documentElement.dataset.theme=localStorage.getItem('theme')||(matchMedia('(prefers-color-scheme:dark)').matches?'dark':'light')}catch(e){}</script>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/style.css?v=1">
</head>
<body>
<header class="top">
  <?php if (!$bare): ?><button class="icon-btn menu-btn" data-drawer aria-label="Buka menu"><?= icon('menu') ?></button><?php endif; ?>
  <a class="brand" href="/"><span class="logo"><?= icon('tag', 22) ?></span><span class="brand-t"><b>Re-Yuse!</b><small>Jual beli antar mahasiswa</small></span></a>
  <form class="search" action="/" method="get" role="search">
    <?= icon('search', 18) ?>
    <input name="q" value="<?= e(g('q')) ?>" placeholder="Cari buku kuliah, kalkulator, alat praktikum…" aria-label="Cari barang" autocomplete="off">
    <kbd>/</kbd>
  </form>
  <div class="actions">
    <button class="icon-btn" data-theme-toggle aria-label="Ganti mode terang atau gelap"><span class="i-moon"><?= icon('moon') ?></span><span class="i-sun"><?= icon('sun') ?></span></button>
    <a class="icon-btn" href="/?view=saved" aria-label="Barang disimpan"><?= icon('heart') ?></a>
    <a class="btn primary" href="/sell.php">+ Jual barang</a>
    <?php if ($u): ?><a class="avatar" href="/profile.php" title="<?= e($u['name']) ?>"><?= e(mb_strtoupper(mb_substr($u['name'], 0, 1))) ?></a>
    <?php else: ?><a class="link" href="/login.php">Masuk</a><?php endif; ?>
  </div>
</header>
<div class="shell<?= $bare ? ' bare' : '' ?>">
<?php if (!$bare): ?>
<aside class="side" id="side">
  <nav class="nav" aria-label="Menu utama">
    <?php foreach ($nav as [$href, $ic, $label, $on, $n]): ?>
      <a href="<?= $href ?>" class="<?= $on ? 'on' : '' ?>"><?= icon($ic) ?> <?= $label ?><?php if ($n): ?><span class="n"><?= $n ?></span><?php endif; ?></a>
    <?php endforeach; ?>
  </nav>
  <?php if (isset($o['browse'])): $b = $o['browse'];
    $cats = q("SELECT c.slug,c.name,c.icon,COUNT(i.id) n FROM categories c LEFT JOIN items i ON i.category_id=c.id AND i.status='aktif' GROUP BY c.id ORDER BY c.sort")->fetchAll(); ?>
    <hr>
    <h4>Kategori</h4>
    <div class="cats">
      <a href="<?= e(qs(['cat' => null, 'page' => null])) ?>" class="<?= $b['cat'] === '' ? 'on' : '' ?>"><span class="ico"><?= icon('grid', 18) ?></span> Semua kategori<span class="n"><?= array_sum(array_column($cats, 'n')) ?></span></a>
      <?php foreach ($cats as $c): ?>
        <a href="<?= e(qs(['cat' => $c['slug'], 'page' => null])) ?>" class="<?= $b['cat'] === $c['slug'] ? 'on' : '' ?>"><span class="ico"><?= icon($c['icon'], 18) ?></span> <?= e($c['name']) ?><span class="n"><?= $c['n'] ?></span></a>
      <?php endforeach; ?>
    </div>
    <hr>
    <form class="filter" method="get" action="/">
      <h4>Filter</h4>
      <?php foreach (['q', 'cat', 'sort', 'view'] as $k) if ($b[$k] ?? '') echo '<input type="hidden" name="' . $k . '" value="' . e($b[$k]) . '">'; ?>
      <label class="t">Kondisi</label>
      <div class="pills">
        <?php foreach (['' => 'Semua'] + CONDITIONS as $k => $v): ?>
          <label><input type="radio" name="kondisi" value="<?= $k ?>" <?= $b['kondisi'] === (string)$k ? 'checked' : '' ?>><span><?= $v ?></span></label>
        <?php endforeach; ?>
      </div>
      <label class="t" for="f-min">Harga (Rp)</label>
      <div class="two"><input id="f-min" type="number" min="0" name="min" placeholder="Min" value="<?= e($b['min']) ?>" aria-label="Harga minimum"><input type="number" min="0" name="max" placeholder="Maks" value="<?= e($b['max']) ?>" aria-label="Harga maksimum"></div>
      <label class="t" for="f-course">Mata kuliah</label>
      <input id="f-course" type="text" name="course" placeholder="mis. Basis Data" value="<?= e($b['course']) ?>">
      <div class="two" style="margin-top:16px"><button class="btn primary sm">Terapkan</button><a class="btn sm" href="/">Reset</a></div>
    </form>
  <?php endif; ?>
  <?php if ($u): ?>
    <hr>
    <form method="post" action="/logout.php" class="logout"><?= csrf_field() ?><span><?= e($u['name']) ?></span><button class="link">Keluar</button></form>
  <?php endif; ?>
</aside>
<div class="scrim" data-drawer></div>
<?php endif; ?>
<main>
<?php foreach ($_SESSION['flash'] ?? [] as [$t, $m]) echo '<div class="flash ' . $t . '" role="status">' . e($m) . '</div>'; unset($_SESSION['flash']); ?>
<?php }

function foot(): void { ?>
</main>
</div>
<script src="/assets/app.js?v=1" defer></script>
</body>
</html>
<?php }

function card(array $r, array $favs = []): void { ?>
<article class="card">
  <a class="thumb" href="/item.php?id=<?= $r['id'] ?>">
    <?php if ($r['cover']): ?><img src="/<?= e($r['cover']) ?>" alt="" loading="lazy"><?php else: ?><span class="noimg"><?= icon('image', 32) ?></span><?php endif; ?>
    <span class="tag"><?= e(CONDITIONS[$r['kondisi']]) ?></span>
    <?php if ($r['promo']): ?><span class="tag promo"><?= icon('bolt', 12) ?> Unggulan</span><?php endif; ?>
  </a>
  <button type="button" class="fav<?= isset($favs[$r['id']]) ? ' on' : '' ?>" data-fav="<?= $r['id'] ?>" aria-label="Simpan barang"><?= icon('heart', 18) ?></button>
  <div class="cbody">
    <div class="price"><?= money($r['price']) ?></div>
    <h3><a href="/item.php?id=<?= $r['id'] ?>"><?= e($r['title']) ?></a></h3>
    <div class="meta"><?= ago($r['created_at']) ?> · <?= icon('eye', 14) ?> <?= (int)$r['views'] ?></div>
    <div class="seller"><span class="sn"><?= e($r['seller']) ?></span><span class="ok"><?= icon('check', 12) ?><span class="t"> Terverifikasi</span></span></div>
    <div class="meta"><?= e($r['jurusan']) ?> '<?= substr((string)$r['angkatan'], 2) ?></div>
  </div>
</article>
<?php }

const CARD_SQL = "SELECT i.*, s.name seller, s.jurusan, s.angkatan,
    (SELECT path FROM item_images WHERE item_id=i.id ORDER BY sort,id LIMIT 1) cover,
    COALESCE(i.promo_until>NOW(),0) promo
    FROM items i JOIN users s ON s.id=i.user_id JOIN categories c ON c.id=i.category_id";

function fav_set(?array $u): array { return $u ? array_flip(q('SELECT item_id FROM favorites WHERE user_id=?', [$u['id']])->fetchAll(PDO::FETCH_COLUMN)) : []; }
