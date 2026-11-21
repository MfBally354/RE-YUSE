<?php
require __DIR__ . '/../inc/layout.php';
if (user()) redirect('/');
$v = ['name' => '', 'email' => '', 'jurusan' => '', 'angkatan' => '', 'whatsapp' => '']; $err = [];
$allowed = array_filter(array_map('trim', explode(',', strtolower(env('ALLOWED_DOMAINS', 'students.unnes.ac.id')))));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($v as $k => $_) $v[$k] = p($k);
    $v['email'] = strtolower($v['email']); $pw = (string)($_POST['password'] ?? '');
    $dom = substr(strrchr($v['email'], '@') ?: '', 1);
    if (mb_strlen($v['name']) < 2 || mb_strlen($v['name']) > 80) $err[] = 'Nama harus 2–80 karakter.';
    if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL) || !in_array($dom, $allowed, true)) $err[] = 'Gunakan email mahasiswa UNNES (@' . implode(', @', $allowed) . ').';
    elseif (q('SELECT 1 FROM users WHERE email=?', [$v['email']])->fetch()) $err[] = 'Email ini sudah terdaftar.';
    if ($v['jurusan'] === '' || mb_strlen($v['jurusan']) > 80) $err[] = 'Isi jurusan (maksimal 80 karakter).';
    if (!ctype_digit($v['angkatan']) || $v['angkatan'] < 2015 || $v['angkatan'] > (int)date('Y')) $err[] = 'Pilih angkatan.';
    if ($v['whatsapp'] !== '' && !preg_match('/^[0-9+\- ]{8,20}$/', $v['whatsapp'])) $err[] = 'Nomor WhatsApp tidak valid.';
    if (strlen($pw) < 8) $err[] = 'Kata sandi minimal 8 karakter.';
    if (!$err) {
        $token = bin2hex(random_bytes(20));
        q('INSERT INTO users (name,email,password_hash,jurusan,angkatan,whatsapp,verify_token) VALUES (?,?,?,?,?,?,?)',
          [$v['name'], $v['email'], password_hash($pw, PASSWORD_DEFAULT), $v['jurusan'], (int)$v['angkatan'], $v['whatsapp'] ?: null, $token]);
        $link = app_url() . '/verify.php?t=' . $token;
        if (env('VERIFY_MODE', 'link') === 'mail') {
            @mail($v['email'], 'Verifikasi akun Preloved UNNES', "Halo {$v['name']},\n\nKlik tautan ini untuk memverifikasi akunmu:\n$link\n", 'From: ' . env('MAIL_FROM', 'no-reply@localhost'));
            flash('Pendaftaran berhasil. Cek email kampusmu untuk tautan verifikasi.');
        } else { $_SESSION['verify_link'] = $link; flash('Pendaftaran berhasil. Verifikasi akunmu dulu sebelum masuk.'); }
        redirect('/login.php');
    }
}
head('Daftar', ['bare' => true]);
?>
<div class="panel auth">
  <h1>Daftar</h1>
  <p class="help">Khusus mahasiswa UNNES. Akun diverifikasi lewat email kampus supaya transaksi lebih tepercaya.</p>
  <?php if ($err): ?><ul class="errs"><?php foreach ($err as $x) echo '<li>' . e($x) . '</li>'; ?></ul><?php endif; ?>
  <form method="post" class="form">
    <?= csrf_field() ?>
    <label>Nama lengkap<input type="text" name="name" required maxlength="80" value="<?= e($v['name']) ?>"></label>
    <label>Email kampus<input type="email" name="email" required value="<?= e($v['email']) ?>" placeholder="nama@students.unnes.ac.id"></label>
    <div class="row">
      <label>Jurusan<input type="text" name="jurusan" required maxlength="80" list="jur" value="<?= e($v['jurusan']) ?>" placeholder="mis. PTIK">
        <datalist id="jur"><option>PTIK</option><option>Teknik Elektro</option><option>Teknik Informatika</option><option>Teknik Mesin</option><option>Teknik Sipil</option><option>Pendidikan Matematika</option><option>Manajemen</option><option>Akuntansi</option><option>Hukum</option></datalist></label>
      <label>Angkatan<select name="angkatan" required><option value="">Pilih…</option>
        <?php for ($y = (int)date('Y'); $y >= (int)date('Y') - 8; $y--) echo '<option' . ($v['angkatan'] == $y ? ' selected' : '') . '>' . $y . '</option>'; ?></select></label>
    </div>
    <label>WhatsApp <small>(opsional, hanya tampil untuk pengguna yang login)</small><input type="tel" name="whatsapp" value="<?= e($v['whatsapp']) ?>" placeholder="08xxxxxxxxxx"></label>
    <label>Kata sandi <small>(minimal 8 karakter)</small><input type="password" name="password" required minlength="8"></label>
    <button class="btn primary block">Buat akun</button>
  </form>
  <p class="help" style="margin-top:18px">Sudah punya akun? <a href="/login.php" style="color:var(--accent);font-weight:700">Masuk</a></p>
</div>
<?php foot();
