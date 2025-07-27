<?php
/**
 * Тестирование системы мониторинга
 */

require_once __DIR__ . '/system_monitor.php';

echo "=== Тестирование системы мониторинга ===\n\n";

try {
    $monitor = new SystemMonitor();
    
    echo "1. Тестирование сбора данных...\n";
    $data = $monitor->getSystemData();
    
    echo "   ✓ Данные получены успешно\n";
    echo "   ✓ Временная метка: " . date('Y-m-d H:i:s', $data['timestamp']) . "\n\n";
    
    // CPU
    echo "2. CPU информация:\n";
    echo "   Использование: " . $data['cpu'] . "%\n";
    echo "   Цвет индикатора: " . $monitor->getPercentageColor($data['cpu']) . "\n\n";
    
    // Memory
    echo "3. Память:\n";
    if ($data['memory']) {
        echo "   Всего: " . $monitor->formatBytes($data['memory']['total']) . "\n";
        echo "   Использовано: " . $monitor->formatBytes($data['memory']['used']) . " (" . $data['memory']['percentage'] . "%)\n";
        echo "   Свободно: " . $monitor->formatBytes($data['memory']['free']) . "\n\n";
    } else {
        echo "   ⚠ Данные о памяти недоступны\n\n";
    }
    
    // Disk
    echo "4. Диск:\n";
    if ($data['disk']) {
        echo "   Всего: " . $monitor->formatBytes($data['disk']['total']) . "\n";
        echo "   Использовано: " . $monitor->formatBytes($data['disk']['used']) . " (" . $data['disk']['percentage'] . "%)\n";
        echo "   Свободно: " . $monitor->formatBytes($data['disk']['free']) . "\n\n";
    } else {
        echo "   ⚠ Данные о диске недоступны\n\n";
    }
    
    // Load Average
    echo "5. Нагрузка системы:\n";
    if ($data['load_average']) {
        echo "   1 минута: " . $data['load_average']['1min'] . "\n";
        echo "   5 минут: " . $data['load_average']['5min'] . "\n";
        echo "   15 минут: " . $data['load_average']['15min'] . "\n\n";
    } else {
        echo "   ⚠ Данные о нагрузке недоступны\n\n";
    }
    
    // Uptime
    echo "6. Время работы:\n";
    if ($data['uptime']) {
        echo "   Время работы: " . $data['uptime']['formatted'] . "\n";
        echo "   Секунд: " . $data['uptime']['seconds'] . "\n\n";
    } else {
        echo "   ⚠ Данные о времени работы недоступны\n\n";
    }
    
    // Processes
    echo "7. Процессы:\n";
    if ($data['processes']) {
        echo "   Запущено: " . $data['processes']['running'] . "\n";
        echo "   Всего: " . $data['processes']['total'] . "\n\n";
    } else {
        echo "   ⚠ Данные о процессах недоступны\n\n";
    }
    
    // Network
    echo "8. Сетевые интерфейсы:\n";
    if ($data['network'] && !empty($data['network'])) {
        foreach ($data['network'] as $iface => $stats) {
            echo "   Интерфейс: $iface\n";
            echo "     Статус: " . ($stats['is_up'] ? 'UP' : 'DOWN') . "\n";
            echo "     Скорость: " . ($stats['speed'] ? $stats['speed'] . ' Mbps' : 'Unknown') . "\n";
            echo "     RX: " . $monitor->formatBytes($stats['rx_bytes']) . " (пакеты: " . $stats['rx_packets'] . ", ошибки: " . $stats['rx_errors'] . ")\n";
            echo "     TX: " . $monitor->formatBytes($stats['tx_bytes']) . " (пакеты: " . $stats['tx_packets'] . ", ошибки: " . $stats['tx_errors'] . ")\n";
            echo "\n";
        }
    } else {
        echo "   ⚠ Сетевые интерфейсы не найдены\n\n";
    }
    
    // Тестирование кеша
    echo "9. Тестирование кеша:\n";
    $cacheFile = '/tmp/vpn_system_monitor.json';
    if (file_exists($cacheFile)) {
        $cacheData = json_decode(file_get_contents($cacheFile), true);
        $cacheAge = time() - $cacheData['timestamp'];
        echo "   ✓ Кеш-файл существует\n";
        echo "   ✓ Возраст кеша: $cacheAge секунд\n";
        echo "   ✓ Размер кеш-файла: " . filesize($cacheFile) . " байт\n\n";
    } else {
        echo "   ⚠ Кеш-файл не найден\n\n";
    }
    
    echo "=== Тестирование завершено успешно ===\n";
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
    echo "Стек: " . $e->getTraceAsString() . "\n";
}