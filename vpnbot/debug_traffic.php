<?php
/**
 * Отладочный скрипт для диагностики проблем с трафиком
 */

require_once __DIR__ . '/db.php';

echo "=== Диагностика системы трафика ===\n\n";

try {
    $dbh = new Db();
    
    // 1. Проверяем топ пользователей (проблемная функция)
    echo "1. Тестирование запроса топ пользователей...\n";
    $sql = "SELECT 
                chat_id,
                key_name,
                (full_recive_byte + full_sent_byte + recive_byte + sent_byte) as total_traffic
            FROM vpnusers 
            WHERE key_name IS NOT NULL AND key_name != ''
            ORDER BY total_traffic DESC 
            LIMIT 5";
    
    $topUsers = $dbh->query($sql);
    echo "   ✓ Запрос выполнен успешно, найдено пользователей: " . count($topUsers) . "\n";
    
    // 2. Проверяем активные сессии
    echo "\n2. Проверка активных сессий...\n";
    $activeSql = "SELECT 
                    chat_id,
                    key_name,
                    session_start,
                    recive_byte,
                    sent_byte
                  FROM vpnusers 
                  WHERE session_start IS NOT NULL";
    
    $activeSessions = $dbh->query($activeSql);
    echo "   ✓ Активных сессий в БД: " . count($activeSessions) . "\n";
    
    foreach ($activeSessions as $session) {
        echo "   - Chat ID: " . $session['chat_id'] . 
             ", Сессия с: " . $session['session_start'] . 
             ", Трафик: " . formatBytes($session['recive_byte'] + $session['sent_byte']) . "\n";
    }
    
    // 3. Проверяем лог OpenVPN
    echo "\n3. Проверка лога OpenVPN...\n";
    $logFile = '/var/log/openvpn/status.log';
    
    if (file_exists($logFile)) {
        $content = file_get_contents($logFile);
        echo "   ✓ Лог-файл найден, размер: " . strlen($content) . " байт\n";
        
        // Парсим активных клиентов
        $lines = explode("\n", $content);
        $clients = [];
        $inClientSection = false;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if ($line === 'OpenVPN CLIENT LIST') {
                $inClientSection = true;
                continue;
            }
            
            if ($line === 'ROUTING TABLE' || $line === 'GLOBAL STATS') {
                break;
            }
            
            if ($inClientSection && !empty($line) && strpos($line, 'Common Name,') !== 0 && $line !== 'Updated,') {
                $parts = explode(',', $line);
                if (count($parts) >= 5 && preg_match('/VpnOpenBot_(\d+)/', trim($parts[0]), $matches)) {
                    $chatId = $matches[1];
                    $clients[$chatId] = [
                        'name' => trim($parts[0]),
                        'received' => (int)trim($parts[2]),
                        'sent' => (int)trim($parts[3]),
                        'connected_since' => trim($parts[4])
                    ];
                }
            }
        }
        
        echo "   ✓ Активных клиентов в OpenVPN: " . count($clients) . "\n";
        
        foreach ($clients as $chatId => $client) {
            echo "   - Chat ID: $chatId, Время: " . $client['connected_since'] . 
                 ", Трафик: " . formatBytes($client['received'] + $client['sent']) . "\n";
        }
        
        // 4. Сравниваем времена
        echo "\n4. Сравнение времен сессий...\n";
        foreach ($activeSessions as $dbSession) {
            $chatId = $dbSession['chat_id'];
            if (isset($clients[$chatId])) {
                $logTime = $clients[$chatId]['connected_since'];
                $dbTime = $dbSession['session_start'];
                
                echo "   Chat ID: $chatId\n";
                echo "     OpenVPN: $logTime\n";
                echo "     БД:      $dbTime\n";
                
                if ($logTime !== $dbTime) {
                    echo "     ⚠ ВРЕМЕНА ОТЛИЧАЮТСЯ!\n";
                } else {
                    echo "     ✓ Времена совпадают\n";
                }
                echo "\n";
            }
        }
        
    } else {
        echo "   ✗ Лог-файл не найден: $logFile\n";
    }
    
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
    echo "Стек: " . $e->getTraceAsString() . "\n";
}

function formatBytes($size, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    
    return round($size, $precision) . ' ' . $units[$i];
}

echo "\n=== Диагностика завершена ===\n";