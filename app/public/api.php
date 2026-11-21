<?php
require __DIR__ . '/../inc/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
function out($d, int $code = 200): never { http_response_code($code); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
$u = user();
if (!$u) out(['error' => 'login'], 401);
$uid = (int)$u['id']; $a = g('a');
function my_conv(int $cid, int $uid): ?array { return q('SELECT * FROM conversations WHERE id=? AND (buyer_id=? OR seller_id=?)', [$cid, $uid, $uid])->fetch() ?: null; }

if ($a === 'fav' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check(); $item = (int)p('item');
    if (!q('SELECT 1 FROM items WHERE id=?', [$item])->fetch()) out(['error' => 'not_found'], 404);
    if (q('DELETE FROM favorites WHERE user_id=? AND item_id=?', [$uid, $item])->rowCount()) out(['on' => false]);
    q('INSERT IGNORE INTO favorites (user_id,item_id) VALUES (?,?)', [$uid, $item]); out(['on' => true]);
}
if ($a === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check(); $cid = (int)p('c'); $body = mb_substr(p('body'), 0, 1000);
    if (!my_conv($cid, $uid) || $body === '') out(['error' => 'invalid'], 422);
    q('INSERT INTO messages (conversation_id,sender_id,body) VALUES (?,?,?)', [$cid, $uid, $body]);
    out(['id' => (int)db()->lastInsertId()]);
}
if ($a === 'poll') {
    $cid = (int)g('c'); $after = (int)g('after');
    if (!my_conv($cid, $uid)) out(['error' => 'invalid'], 404);
    $rows = q('SELECT id,sender_id,body,created_at FROM messages WHERE conversation_id=? AND id>? ORDER BY id LIMIT 100', [$cid, $after])->fetchAll();
    q('UPDATE messages SET read_at=NOW() WHERE conversation_id=? AND sender_id<>? AND read_at IS NULL', [$cid, $uid]);
    out(['messages' => array_map(fn($m) => ['id' => (int)$m['id'], 'me' => (int)$m['sender_id'] === $uid, 'body' => $m['body'], 'time' => date('H:i', strtotime($m['created_at']))], $rows)]);
}
out(['error' => 'unknown'], 400);
