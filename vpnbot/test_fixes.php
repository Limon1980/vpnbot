<?php
/**
 * Тестовый скрипт для проверки исправленных ошибок
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/traffic_stats.php';

echo "=== Тестирование исправлений ===\n\n";

// Тест 1: Проверка топ пользователей
echo "1. Тестирование топ пользователей...\n";
try {
    $stats = new TrafficStats();
    $topUsers = $stats->getTopTrafficUsers(5);
    echo "   ✓ Топ пользователи загружены успешно: " . count($topUsers) . " записей\n";
    
    foreach ($topUsers as $i => $user) {
        echo "   " . ($i + 1) . ". Chat ID: " . $user['chat_id'] . 
             ", Трафик: " . $stats->formatBytes($user['total_traffic']) . "\n";
    }
} catch (Exception $e) {
    echo "   ✗ Ошибка при загрузке топ пользователей: " . $e->getMessage() . "\n";
}

echo "\n";

// Тест 2: Проверка активных сессий
echo "2. Тестирование активных сессий...\n";
try {
    $activeSessions = $stats->getActiveSessions();
    echo "   ✓ Активные сессии загружены успешно: " . count($activeSessions) . " записей\n";
    
    foreach ($activeSessions as $session) {
        echo "   Chat ID: " . $session['chat_id'] . 
             ", Сессия с: " . $session['session_start'] . 
             ", Трафик: " . $stats->formatBytes($session['session_total']) . "\n";
    }
} catch (Exception $e) {
    echo "   ✗ Ошибка при загрузке активных сессий: " . $e->getMessage() . "\n";
}

echo "\n";

// Тест 3: Проверка преобразования времени
echo "3. Тестирование преобразования времени...\n";

class TimeTestCollector {
    public function testTimeConversion($openVpnTime) {
        try {
            $dateTime = DateTime::createFromFormat('Y-m-d H:i:s', $openVpnTime);
            if ($dateTime === false) {
                $dateTime = new DateTime($openVpnTime);
            }
            return $dateTime->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            return "Ошибка: " . $e->getMessage();
        }
    }
}

$timeTest = new TimeTestCollector();
$testTimes = [
    '2025-07-26 14:31:33',
    '2025-01-27 06:28:20',
    'invalid time'
];

foreach ($testTimes as $testTime) {
    $result = $timeTest->testTimeConversion($testTime);
    echo "   '$testTime' -> '$result'\n";
}

echo "\n";

// Тест 4: Проверка подключения к БД
echo "4. Тестирование reconnect в БД...\n";
try {
    $db = new Db();
    $result = $db->query("SELECT 1 as test");
    echo "   ✓ Подключение к БД работает корректно\n";
} catch (Exception $e) {
    echo "   ✗ Ошибка подключения к БД: " . $e->getMessage() . "\n";
}

echo "\n=== Тестирование завершено ===\n";