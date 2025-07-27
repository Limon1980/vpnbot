<?php
/**
 * API для получения данных мониторинга системы
 */

require_once __DIR__ . '/system_monitor.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

try {
    $monitor = new SystemMonitor();
    $data = $monitor->getSystemData();
    
    // Добавляем форматированные значения для удобства
    if ($data['memory']) {
        $data['memory']['total_formatted'] = $monitor->formatBytes($data['memory']['total']);
        $data['memory']['used_formatted'] = $monitor->formatBytes($data['memory']['used']);
        $data['memory']['free_formatted'] = $monitor->formatBytes($data['memory']['free']);
    }
    
    if ($data['disk']) {
        $data['disk']['total_formatted'] = $monitor->formatBytes($data['disk']['total']);
        $data['disk']['used_formatted'] = $monitor->formatBytes($data['disk']['used']);
        $data['disk']['free_formatted'] = $monitor->formatBytes($data['disk']['free']);
    }
    
    // Форматируем сетевые интерфейсы
    if ($data['network']) {
        foreach ($data['network'] as $iface => &$stats) {
            $stats['rx_bytes_formatted'] = $monitor->formatBytes($stats['rx_bytes']);
            $stats['tx_bytes_formatted'] = $monitor->formatBytes($stats['tx_bytes']);
            $stats['speed_formatted'] = $stats['speed'] ? $stats['speed'] . ' Mbps' : 'Unknown';
        }
    }
    
    // Добавляем цвета для процентных значений
    $data['colors'] = [
        'cpu' => $monitor->getPercentageColor($data['cpu']),
        'memory' => $data['memory'] ? $monitor->getPercentageColor($data['memory']['percentage']) : '#6c757d',
        'disk' => $data['disk'] ? $monitor->getPercentageColor($data['disk']['percentage']) : '#6c757d'
    ];
    
    echo json_encode([
        'success' => true,
        'data' => $data,
        'last_update' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}