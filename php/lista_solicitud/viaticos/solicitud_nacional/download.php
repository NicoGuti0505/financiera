<?php
require_once __DIR__ . '/common_fs.php';

$b64 = $_GET['b64'] ?? '';
if ($b64 === '') { http_response_code(404); exit; }
$rel = base64_decode(strtr($b64, '-_', '+/'));
$full = safe_join_under_base($rel);
if (!$full || !is_file($full)) { http_response_code(404); exit; }

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="'.basename($full).'"');
header('Content-Length: '.filesize($full));
readfile($full);
