<?php
require_once __DIR__ . '/common_fs.php';

$b64 = $_GET['b64'] ?? '';
if ($b64 === '') { http_response_code(404); exit; }
$rel = base64_decode(strtr($b64, '-_', '+/'));
$full = safe_join_under_base($rel);
if (!$full || !is_file($full)) { http_response_code(404); exit; }

$ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
$mime = 'application/octet-stream';
if (in_array($ext, ['png','jpg','jpeg','gif','webp','bmp'])) $mime = 'image/'.($ext==='jpg'?'jpeg':$ext);
elseif ($ext === 'pdf') $mime = 'application/pdf';
elseif (in_array($ext, ['txt','log','md','csv'])) $mime = 'text/plain; charset=utf-8';
elseif ($ext === 'json') $mime = 'application/json';
elseif ($ext === 'xml')  $mime = 'application/xml';

header('Content-Type: '.$mime);
header('Content-Disposition: inline; filename="'.basename($full).'"');
header('Content-Length: '.filesize($full));
readfile($full);
