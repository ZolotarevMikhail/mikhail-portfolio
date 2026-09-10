<?php
// Отправка сообщений с сайта в Telegram и ВКонтакте.
// Принимает POST: type (tg/vk/call/mail), message/phone/email, task
// Возвращает JSON: {ok: true} или {ok: false, error: '...'}

// === ЗАГРУЗКА СЕКРЕТОВ ИЗ .env ===
// Файл .env лежит рядом с send.php, в .gitignore. Содержит:
//   TG_BOT_TOKEN, TG_CHAT_ID, VK_TOKEN, VK_USER_ID, VK_API_V
$env_path = __DIR__ . '/.env';
if (!file_exists($env_path)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Сервер не настроен (.env отсутствует)']);
    exit;
}
foreach (file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with($line, '#') || !str_contains($line, '=')) continue;
    [$k, $v] = explode('=', $line, 2);
    $_ENV[trim($k)] = trim($v);
}

$TG_BOT_TOKEN = $_ENV['TG_BOT_TOKEN'] ?? '';
$TG_CHAT_ID   = $_ENV['TG_CHAT_ID']   ?? '';
$VK_TOKEN     = $_ENV['VK_TOKEN']     ?? '';
$VK_USER_ID   = $_ENV['VK_USER_ID']   ?? '';
$VK_API_V     = $_ENV['VK_API_V']     ?? '5.199';

if ($TG_BOT_TOKEN === '' || $TG_CHAT_ID === '' || $VK_TOKEN === '' || $VK_USER_ID === '') {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Не все секреты заданы в .env']);
    exit;
}
// ===================================

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Только POST']);
    exit;
}

// Валидация type (whitelist)
$type = isset($_POST['type']) ? trim($_POST['type']) : '';
if (!in_array($type, ['tg', 'vk', 'call', 'mail'], true)) {
    echo json_encode(['ok' => false, 'error' => 'Неверный тип']);
    exit;
}

$message = isset($_POST['message']) ? trim($_POST['message']) : '';
$phone   = isset($_POST['phone'])   ? trim($_POST['phone'])   : '';
$email   = isset($_POST['email'])   ? trim($_POST['email'])   : '';
$task    = isset($_POST['task'])    ? trim($_POST['task'])    : '';

// Лимиты длины
if (mb_strlen($message) > 4000 || mb_strlen($phone) > 32 || mb_strlen($email) > 254 || mb_strlen($task) > 4000) {
    echo json_encode(['ok' => false, 'error' => 'Слишком длинное значение']);
    exit;
}

// Honeypot — скрытое поле, человек его не заполняет, бот заполнит
if (!empty($_POST['hp'])) {
    // Молча отвечаем "ок", но ничего не отправляем
    echo json_encode(['ok' => true]);
    exit;
}

if (($message === '' && $phone === '' && $email === '')) {
    echo json_encode(['ok' => false, 'error' => 'Пустое сообщение']);
    exit;
}

// Собираем текст сообщения
$icon  = '';
$title = '';
switch ($type) {
    case 'tg':   $icon = '✈️'; $title = 'Сообщение через Telegram'; break;
    case 'vk':   $icon = '🔵'; $title = 'Сообщение через ВКонтакте'; break;
    case 'call': $icon = '📞'; $title = 'Заказ обратного звонка'; break;
    case 'mail': $icon = '�️'; $title = 'Запрос по email'; break;
}

$text = "$icon $title\n\n";

if ($type === 'call') {
    $text .= "� Телефон: $phone\n";
} elseif ($type === 'mail') {
    $text .= "✉️ Email: $email\n";
    if ($task !== '') {
        $text .= "\n📋 Задача:\n$task\n";
    }
} else {
    if ($message !== '') {
        $text .= "$message\n";
    }
}

$text .= "\n—\n";
$text .= "🕐 " . date('Y-m-d H:i:s') . " (MSK)\n";
$text .= "🌐 IP: " . htmlspecialchars($_SERVER['REMOTE_ADDR']);

// Экранирование для Telegram HTML
$tg_text = "$icon <b>$title</b>\n\n";
if ($type === 'call') {
    $tg_text .= "📞 Телефон: <b>" . htmlspecialchars($phone) . "</b>\n";
} elseif ($type === 'mail') {
    $tg_text .= "✉️ Email: <b>" . htmlspecialchars($email) . "</b>\n";
    if ($task !== '') {
        $tg_text .= "\n📋 Задача:\n" . htmlspecialchars($task) . "\n";
    }
} else {
    if ($message !== '') {
        $tg_text .= htmlspecialchars($message) . "\n";
    }
}
$tg_text .= "\n—\n";
$tg_text .= "🕐 " . date('Y-m-d H:i:s') . " (MSK)\n";
$tg_text .= "🌐 IP: " . htmlspecialchars($_SERVER['REMOTE_ADDR']);

$results = ['tg' => false, 'vk' => false];
$errors  = [];

// === Telegram ===
$tg_url = "https://api.telegram.org/bot{$TG_BOT_TOKEN}/sendMessage";
$ch = curl_init($tg_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'chat_id'    => $TG_CHAT_ID,
    'text'       => $tg_text,
    'parse_mode' => 'HTML',
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$tg_result = curl_exec($ch);
$tg_code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
if ($tg_code === 200) {
    $decoded = json_decode($tg_result, true);
    if (isset($decoded['ok']) && $decoded['ok'] === true) {
        $results['tg'] = true;
    } else {
        $errors[] = 'TG: ' . ($decoded['description'] ?? 'unknown');
    }
} else {
    $errors[] = "TG: HTTP $tg_code";
}

// === ВКонтакте ===
$vk_url = 'https://api.vk.com/method/messages.send';
$ch = curl_init($vk_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'user_id'      => $VK_USER_ID,
    'message'      => $text,
    'random_id'    => rand(1, 999999999),
    'access_token' => $VK_TOKEN,
    'v'            => $VK_API_V,
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$vk_result = curl_exec($ch);
$vk_code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
if ($vk_code === 200) {
    $decoded = json_decode($vk_result, true);
    if (!isset($decoded['error']) && isset($decoded['response'])) {
        $results['vk'] = true;
    } else {
        $errors[] = 'VK: ' . ($decoded['error']['error_msg'] ?? 'unknown');
    }
} else {
    $errors[] = "VK: HTTP $vk_code";
}

// Логируем неудачи (в error_log на хостинге)
if (!$results['tg'] && !$results['vk']) {
    error_log('[send.php] delivery failed: ' . implode('; ', $errors));
}

if ($results['tg'] || $results['vk']) {
    echo json_encode(['ok' => true, 'tg' => $results['tg'], 'vk' => $results['vk']]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Не удалось отправить']);
