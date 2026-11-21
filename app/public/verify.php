<?php
require __DIR__ . '/../inc/bootstrap.php';
$t = g('t');
if (strlen($t) === 40 && q('UPDATE users SET verified=1, verify_token=NULL WHERE verify_token=?', [$t])->rowCount()) flash('Akun terverifikasi. Silakan masuk.');
else flash('Tautan verifikasi tidak valid atau sudah dipakai.', 'err');
redirect('/login.php');
