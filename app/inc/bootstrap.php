<?php
date_default_timezone_set('Asia/Jakarta');
session_start();
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

const CONDITIONS = ['baru' => 'Baru', 'seperti_baru' => 'Seperti baru', 'bekas' => 'Bekas'];
const PROMO_PACKAGES = [3 => 5000, 7 => 10000, 14 => 15000]; // hari => harga (Rp)

function env(string $k, string $d = ''): string { $v = getenv($k); return ($v === false || $v === '') ? $d : $v; }
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function g(string $k): string { $v = $_GET[$k] ?? ''; return is_string($v) ? trim($v) : ''; }
function p(string $k): string { $v = $_POST[$k] ?? ''; return is_string($v) ? trim($v) : ''; }

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $dsn = 'mysql:host=' . env('DB_HOST', 'db') . ';dbname=' . env('DB_NAME', 'preloved') . ';charset=utf8mb4';
    for ($i = 0; ; $i++) {
        try {
            $pdo = new PDO($dsn, env('DB_USER', 'preloved'), env('DB_PASS'), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+07:00'",
            ]);
            break;
        } catch (PDOException $ex) { if ($i >= 9) throw $ex; sleep(1); }
    }
    seed_admin($pdo);
    return $pdo;
}

// Admin pertama dibuat otomatis dari ADMIN_EMAIL / ADMIN_PASSWORD di .env
function seed_admin(PDO $pdo): void {
    $mail = strtolower(env('ADMIN_EMAIL')); $pass = env('ADMIN_PASSWORD');
    if (!$mail || !$pass) return;
    if ($pdo->query("SELECT 1 FROM users WHERE role='admin' LIMIT 1")->fetch()) return;
    $pdo->prepare("INSERT INTO users (name,email,password_hash,jurusan,angkatan,role,verified) VALUES ('Admin',?,?,'Admin',YEAR(NOW()),'admin',1)
                   ON DUPLICATE KEY UPDATE role='admin', verified=1")->execute([$mail, password_hash($pass, PASSWORD_DEFAULT)]);
}

function q(string $sql, array $p = []): PDOStatement { $s = db()->prepare($sql); $s->execute($p); return $s; }
function redirect(string $to): never { header('Location: ' . $to); exit; }
function safe_next(?string $n): string { return ($n && $n[0] === '/' && !in_array($n[1] ?? '', ['/', '\\'], true)) ? $n : '/'; }

function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(16)); }
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . csrf() . '">'; }
function csrf_check(): void {
    $t = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? '';
    if (!is_string($t) || !hash_equals(csrf(), $t)) { http_response_code(419); exit('Sesi kedaluwarsa. Muat ulang halaman lalu coba lagi.'); }
}
function flash(string $m, string $t = 'ok'): void { $_SESSION['flash'][] = [$t, $m]; }

function user(): ?array {
    static $u = false;
    if ($u === false) $u = empty($_SESSION['uid']) ? null : (q('SELECT * FROM users WHERE id=?', [$_SESSION['uid']])->fetch() ?: null);
    return $u;
}
function require_login(): array { $u = user(); if (!$u) redirect('/login.php?next=' . urlencode($_SERVER['REQUEST_URI'])); return $u; }
function require_admin(): array { $u = require_login(); if ($u['role'] !== 'admin') { http_response_code(403); exit('Halaman ini khusus admin.'); } return $u; }

function money($n): string { return 'Rp ' . number_format((int)$n, 0, ',', '.'); }
function ago(string $dt): string {
    $d = time() - strtotime($dt);
    if ($d < 60) return 'baru saja';
    foreach ([[2592000, 'bulan'], [86400, 'hari'], [3600, 'jam'], [60, 'menit']] as [$s, $l]) if ($d >= $s) return intdiv($d, $s) . " $l lalu";
    return '';
}
function wa_link(string $num, string $text): string {
    $n = preg_replace('/\D/', '', $num);
    if (str_starts_with($n, '0')) $n = '62' . substr($n, 1);
    return 'https://wa.me/' . $n . '?text=' . rawurlencode($text);
}
function app_url(): string { return rtrim(env('APP_URL') ?: ((!empty($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST']), '/'); }

function icon(string $n, int $s = 20): string {
    static $d = [
        'store' => '<path d="M4 9l1-5h14l1 5M4 9v11h16V9M4 9a2.7 2.7 0 0 0 5.3 0 2.7 2.7 0 0 0 5.4 0A2.7 2.7 0 0 0 20 9M10 20v-5h4v5"/>',
        'heart' => '<path d="M12 20s-7.5-4.6-7.5-10.3A4.2 4.2 0 0 1 12 7.2a4.2 4.2 0 0 1 7.5 2.5C19.5 15.4 12 20 12 20z"/>',
        'chat' => '<path d="M4 5h16v11H9l-5 4z"/>',
        'tag' => '<path d="M3 12V4h8l10 10-8 8z"/><circle cx="7.5" cy="8.5" r="1"/>',
        'bolt' => '<path d="M13 2L4 14h7l-1 8 9-12h-7z"/>',
        'shield' => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/>',
        'book' => '<path d="M5 4h10a3 3 0 0 1 3 3v13H8a3 3 0 0 1-3-3z"/><path d="M5 17a3 3 0 0 1 3-3h10"/>',
        'calc' => '<rect x="5" y="3" width="14" height="18" rx="2"/><path d="M8 7h8M8 12h.01M12 12h.01M16 12h.01M8 16h.01M12 16h.01M16 16h.01"/>',
        'flask' => '<path d="M9 3h6M10 3v6l-5 10a1 1 0 0 0 1 1.5h12a1 1 0 0 0 1-1.5L14 9V3"/>',
        'bed' => '<path d="M3 18V7M3 14h18v4M21 14v-3a3 3 0 0 0-3-3h-7v6"/>',
        'laptop' => '<rect x="4" y="5" width="16" height="11" rx="1.5"/><path d="M2 20h20"/>',
        'grid' => '<rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><rect x="14" y="14" width="6" height="6" rx="1"/>',
        'moon' => '<path d="M20 14.5A8 8 0 0 1 9.5 4 8 8 0 1 0 20 14.5z"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/>',
        'eye' => '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'check' => '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
        'image' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="1.5"/><path d="M21 16l-5-5-9 9"/>',
    ];
    return '<svg class="ic" width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($d[$n] ?? '') . '</svg>';
}

function save_upload(array $f): ?string {
    if (($f['error'] ?? 4) !== UPLOAD_ERR_OK || $f['size'] > 4 * 1024 * 1024) return null;
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
    if (!$ext) return null;
    $name = bin2hex(random_bytes(10)) . '.' . $ext;
    return move_uploaded_file($f['tmp_name'], __DIR__ . '/../public/uploads/' . $name) ? 'uploads/' . $name : null;
}
function files_of(string $key): array {
    $out = []; $f = $_FILES[$key] ?? null;
    if (!$f || !is_array($f['name'])) return $out;
    foreach ($f['name'] as $i => $_) if ($f['error'][$i] !== UPLOAD_ERR_NO_FILE) $out[] = ['tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
    return $out;
}
