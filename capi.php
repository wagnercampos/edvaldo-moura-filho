<?php
// API de Conversões do Meta: repete pelo servidor os eventos do Pixel do navegador.
// O event_id é o mesmo nos dois lados, então o Meta conta uma vez só.
header('Content-Type: application/json');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('{"ok":false}'); }

$host = parse_url($_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '', PHP_URL_HOST);
if (!in_array($host, ['dredvaldomourafilho.com.br', 'www.dredvaldomourafilho.com.br'], true)) {
  http_response_code(403); exit('{"ok":false}');
}

require __DIR__ . '/capi-config.php';

$in = json_decode(file_get_contents('php://input'), true) ?: [];
$event = $in['event_name'] ?? '';
if (!in_array($event, ['PageView', 'Contact'], true)) { http_response_code(400); exit('{"ok":false}'); }

$eventId = preg_replace('/[^A-Za-z0-9._-]/', '', substr((string)($in['event_id'] ?? ''), 0, 64));
$url = filter_var($in['event_source_url'] ?? '', FILTER_VALIDATE_URL) ?: 'https://dredvaldomourafilho.com.br/';

$userData = [
  'client_ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
  'client_user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
];
if (!empty($_COOKIE['_fbp'])) $userData['fbp'] = $_COOKIE['_fbp'];
if (!empty($_COOKIE['_fbc'])) $userData['fbc'] = $_COOKIE['_fbc'];

$payload = ['data' => [[
  'event_name' => $event,
  'event_time' => time(),
  'event_id' => $eventId,
  'action_source' => 'website',
  'event_source_url' => $url,
  'user_data' => $userData,
]]];
if (!empty($in['test_event_code'])) $payload['test_event_code'] = substr($in['test_event_code'], 0, 32);

$ch = curl_init('https://graph.facebook.com/v23.0/' . META_PIXEL_ID . '/events');
curl_setopt_array($ch, [
  CURLOPT_POST => true,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_TIMEOUT => 5,
  CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . META_CAPI_TOKEN],
  CURLOPT_POSTFIELDS => json_encode($payload),
]);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo json_encode(['ok' => $code === 200, 'meta' => json_decode($res, true)]);
