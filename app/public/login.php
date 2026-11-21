<?php
require __DIR__ . '/../inc/layout.php';
if (user()) redirect('/');
$err = ''; $next = safe_next($_GET['next'] ?? $_POST['next'] ?? '/'); $email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = strtolower(p('email'));
    $row = q('SELECT * FROM users WHERE email=?', [$email])->fetch();
    if (!$row || !password_verify((string)($_POST['password'] ?? ''), $row['password_hash'])) $err = 'Email atau kata sandi salah.';
    elseif (!$row['verified']) {
        $err = 'Akun belum diverifikasi. Buka tautan verifikasi yang dikirim saat mendaftar.';
        if (env('VERIFY_MODE', 'link') === 'link' && $row['verify_token']) $_SESSION['verify_link'] = app_url() . '/verify.php?t=' . $row['verify_token'];
    } else { session_regenerate_id(true); $_SESSION['uid'] = $row['id']; redirect($next); }
}
$link = $_SESSION['verify_link'] ?? null; unset($_SESSION['verify_link']);
head('Masuk', ['bare' => true]);
?>
<div class="panel auth">
  <h1>Masuk</h1>
  <p class="help">Gunakan akun yang terdaftar dengan email mahasiswa UNNES.</p>
  <?php if ($err): ?><div class="flash err"><?= e($err) ?></div><?php endif; ?>
  <?php if ($link): ?><div class="flash ok">Mode demo: <a href="<?= e($link) ?>" style="text-decoration:underline"><b>klik di sini untuk verifikasi akun</b></a>.</div><?php endif; ?>
  <form method="post" class="form">
    <?= csrf_field() ?><input type="hidden" name="next" value="<?= e($next) ?>">
    <label>Email<input type="email" name="email" required autofocus value="<?= e($email) ?>" placeholder="nama@students.unnes.ac.id"></label>
    <label>Kata sandi<input type="password" name="password" required></label>
    <button class="btn primary block">Masuk</button>
  </form>
  <p class="help" style="margin-top:18px">Belum punya akun? <a href="/register.php" style="color:var(--accent);font-weight:700">Daftar</a></p>
</div>
<?php foot();
