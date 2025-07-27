<?php
/**
 * Скрипт для обновления данных сессий из status.log
 * Запускается по cron каждые 5 минут
 */

include('traffic_stats.php');

$stats = new TrafficStats();
$updated = $stats->updateSessionData();

if ($updated !== false) {
    echo date('Y-m-d H:i:s') . " - Updated $updated sessions\n";
} else {
    echo date('Y-m-d H:i:s') . " - Error updating sessions\n";
}
?>