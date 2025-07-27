<?php
/**
 * Скрипт для просмотра статистики трафика
 * Административная панель для мониторинга трафика
 */

require_once __DIR__ . '/db.php';

class TrafficStats {
    private $dbh;
    
    public function __construct() {
        $this->dbh = new Db();
    }
    
    /**
     * Получить статистику трафика всех пользователей
     */
    public function getAllUsersTraffic() {
        $sql = "SELECT 
                    chat_id,
                    key_name,
                    ip,
                    tarif,
                    day,
                    recive_byte,
                    sent_byte,
                    full_recive_byte,
                    full_sent_byte,
                    session_start,
                    reg_date,
                    (recive_byte + sent_byte) as current_session_total,
                    (full_recive_byte + full_sent_byte) as total_traffic_used
                FROM vpnusers 
                ORDER BY reg_date DESC";
        
        return $this->dbh->query($sql);
    }
    
    /**
     * Получить статистику для конкретного пользователя
     */
    public function getUserTraffic($chatId) {
        $sql = "SELECT * FROM vpnusers WHERE chat_id = :chat_id";
        $result = $this->dbh->query($sql, [':chat_id' => $chatId]);
        
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Получить активные сессии
     */
    public function getActiveSessions() {
        $sql = "SELECT 
                    chat_id,
                    key_name,
                    ip,
                    recive_byte,
                    sent_byte,
                    session_start,
                    (recive_byte + sent_byte) as session_total
                FROM vpnusers 
                WHERE session_start IS NOT NULL 
                AND (recive_byte > 0 OR sent_byte > 0)
                ORDER BY session_start DESC";
        
        return $this->dbh->query($sql);
    }
    
    /**
     * Получить топ пользователей по трафику
     */
    public function getTopTrafficUsers($limit = 10) {
        $sql = "SELECT 
                    chat_id,
                    key_name,
                    (full_recive_byte + full_sent_byte + recive_byte + sent_byte) as total_traffic
                FROM vpnusers 
                WHERE key_name IS NOT NULL AND key_name != ''
                ORDER BY total_traffic DESC 
                LIMIT " . (int)$limit;
        
        return $this->dbh->query($sql);
    }
    
    /**
     * Форматирование байтов в читаемый вид
     */
    public function formatBytes($size, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        
        return round($size, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Сброс трафика текущей сессии для пользователя
     */
    public function resetCurrentSessionTraffic($chatId) {
        $sql = "UPDATE vpnusers 
                SET recive_byte = 0, 
                    sent_byte = 0,
                    session_start = NULL
                WHERE chat_id = :chat_id";
        
        return $this->dbh->query($sql, [':chat_id' => $chatId]);
    }
    
    /**
     * Полный сброс трафика пользователя
     */
    public function resetAllUserTraffic($chatId) {
        $sql = "UPDATE vpnusers 
                SET recive_byte = 0, 
                    sent_byte = 0,
                    full_recive_byte = 0,
                    full_sent_byte = 0,
                    session_start = NULL
                WHERE chat_id = :chat_id";
        
        return $this->dbh->query($sql, [':chat_id' => $chatId]);
    }
}

// Веб-интерфейс для просмотра статистики
if (php_sapi_name() !== 'cli') {
    $stats = new TrafficStats();
    $action = $_GET['action'] ?? 'overview';
    $chatId = $_GET['chat_id'] ?? null;
    
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Статистика трафика VPN Bot</title>
        <meta charset="utf-8">
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; background-color: #f8f9fa; }
            table { border-collapse: collapse; width: 100%; background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; }
            .active { background-color: #e8f5e8; }
            .nav { margin-bottom: 20px; }
            .nav a { margin-right: 15px; text-decoration: none; padding: 5px 10px; background: #007cba; color: white; border-radius: 3px; }
            .nav a:hover { background: #005a87; }
            
            /* Стили для мониторинга */
            .monitor-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 20px; }
            .monitor-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
            .monitor-card h3 { margin-top: 0; color: #333; border-bottom: 2px solid #007cba; padding-bottom: 10px; }
            .progress-bar { width: 100%; height: 20px; background-color: #e9ecef; border-radius: 10px; overflow: hidden; margin: 10px 0; }
            .progress-fill { height: 100%; transition: width 0.3s ease, background-color 0.3s ease; }
            .metric { display: flex; justify-content: space-between; align-items: center; margin: 10px 0; }
            .metric-label { font-weight: bold; }
            .metric-value { color: #666; }
            .status-indicator { width: 12px; height: 12px; border-radius: 50%; display: inline-block; margin-right: 5px; }
            .status-up { background-color: #28a745; }
            .status-down { background-color: #dc3545; }
            .last-update { text-align: center; color: #6c757d; margin-top: 20px; font-size: 14px; }
            .refresh-btn { background: #28a745; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; }
            .refresh-btn:hover { background: #218838; }
            .loading { opacity: 0.6; }
        </style>
    </head>
    <body>
        <h1>Статистика трафика VPN Bot</h1>
        
        <div class="nav">
            <a href="?action=overview">Обзор</a>
            <a href="?action=active">Активные сессии</a>
            <a href="?action=top">Топ пользователи</a>
            <a href="?action=all">Все пользователи</a>
            <a href="?action=monitor">Мониторинг системы</a>
        </div>
        
        <?php
        switch ($action) {
            case 'active':
                $sessions = $stats->getActiveSessions();
                echo "<h2>Активные сессии (" . count($sessions) . ")</h2>";
                if (!empty($sessions)) {
                    echo "<table>";
                    echo "<tr><th>Chat ID</th><th>Key Name</th><th>IP</th><th>Получено</th><th>Отправлено</th><th>Всего за сессию</th><th>Начало сессии</th></tr>";
                    foreach ($sessions as $session) {
                        echo "<tr class='active'>";
                        echo "<td>" . htmlspecialchars($session['chat_id']) . "</td>";
                        echo "<td>" . htmlspecialchars($session['key_name']) . "</td>";
                        echo "<td>" . htmlspecialchars($session['ip']) . "</td>";
                        echo "<td>" . $stats->formatBytes($session['recive_byte']) . "</td>";
                        echo "<td>" . $stats->formatBytes($session['sent_byte']) . "</td>";
                        echo "<td>" . $stats->formatBytes($session['session_total']) . "</td>";
                        echo "<td>" . htmlspecialchars($session['session_start']) . "</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                } else {
                    echo "<p>Нет активных сессий</p>";
                }
                break;
                
            case 'top':
                $topUsers = $stats->getTopTrafficUsers(20);
                echo "<h2>Топ 20 пользователей по трафику</h2>";
                if (!empty($topUsers)) {
                    echo "<table>";
                    echo "<tr><th>Место</th><th>Chat ID</th><th>Key Name</th><th>Общий трафик</th></tr>";
                    $position = 1;
                    foreach ($topUsers as $user) {
                        echo "<tr>";
                        echo "<td>" . $position++ . "</td>";
                        echo "<td>" . htmlspecialchars($user['chat_id']) . "</td>";
                        echo "<td>" . htmlspecialchars($user['key_name']) . "</td>";
                        echo "<td>" . $stats->formatBytes($user['total_traffic']) . "</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                }
                break;
                
            case 'all':
                $allUsers = $stats->getAllUsersTraffic();
                echo "<h2>Все пользователи (" . count($allUsers) . ")</h2>";
                if (!empty($allUsers)) {
                    echo "<table>";
                    echo "<tr><th>Chat ID</th><th>Key Name</th><th>IP</th><th>Тариф</th><th>Дни</th><th>Текущая сессия</th><th>Общий трафик</th><th>Регистрация</th></tr>";
                    foreach ($allUsers as $user) {
                        $isActive = !empty($user['session_start']);
                        echo "<tr" . ($isActive ? " class='active'" : "") . ">";
                        echo "<td>" . htmlspecialchars($user['chat_id']) . "</td>";
                        echo "<td>" . htmlspecialchars($user['key_name']) . "</td>";
                        echo "<td>" . htmlspecialchars($user['ip']) . "</td>";
                        echo "<td>" . htmlspecialchars($user['tarif']) . "</td>";
                        echo "<td>" . htmlspecialchars($user['day']) . "</td>";
                        echo "<td>" . $stats->formatBytes($user['current_session_total']) . "</td>";
                        echo "<td>" . $stats->formatBytes($user['total_traffic_used']) . "</td>";
                        echo "<td>" . htmlspecialchars($user['reg_date']) . "</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                }
                break;
                
            case 'monitor':
                require_once __DIR__ . '/system_monitor.php';
                echo "<h2>Мониторинг системы</h2>";
                echo "<button class='refresh-btn' onclick='refreshMonitorData()'>Обновить данные</button>";
                
                echo "<div id='monitor-container' class='monitor-grid'>";
                echo "<div class='monitor-card'>";
                echo "<h3>🖥️ Процессор</h3>";
                echo "<div id='cpu-usage'>Загрузка...</div>";
                echo "</div>";
                
                echo "<div class='monitor-card'>";
                echo "<h3>💾 Память</h3>";
                echo "<div id='memory-usage'>Загрузка...</div>";
                echo "</div>";
                
                echo "<div class='monitor-card'>";
                echo "<h3>💿 Диск</h3>";
                echo "<div id='disk-usage'>Загрузка...</div>";
                echo "</div>";
                
                echo "<div class='monitor-card'>";
                echo "<h3>📊 Нагрузка системы</h3>";
                echo "<div id='load-average'>Загрузка...</div>";
                echo "</div>";
                
                echo "<div class='monitor-card'>";
                echo "<h3>⏱️ Время работы</h3>";
                echo "<div id='uptime'>Загрузка...</div>";
                echo "</div>";
                
                echo "<div class='monitor-card'>";
                echo "<h3>🔄 Процессы</h3>";
                echo "<div id='processes'>Загрузка...</div>";
                echo "</div>";
                echo "</div>";
                
                echo "<div class='monitor-card'>";
                echo "<h3>🌐 Сетевые интерфейсы</h3>";
                echo "<div id='network-interfaces'>Загрузка...</div>";
                echo "</div>";
                
                echo "<div class='last-update' id='last-update'>Последнее обновление: загрузка...</div>";
                
                // JavaScript для автообновления
                echo "<script>
                let monitorInterval;
                
                function loadMonitorData() {
                    document.getElementById('monitor-container').classList.add('loading');
                    
                    fetch('monitor_api.php')
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                updateMonitorDisplay(data.data);
                                document.getElementById('last-update').textContent = 'Последнее обновление: ' + data.last_update;
                            } else {
                                console.error('Error:', data.error);
                            }
                        })
                        .catch(error => {
                            console.error('Fetch error:', error);
                        })
                        .finally(() => {
                            document.getElementById('monitor-container').classList.remove('loading');
                        });
                }
                
                function updateMonitorDisplay(data) {
                    // CPU
                    document.getElementById('cpu-usage').innerHTML = `
                        <div class='metric'>
                            <span class='metric-label'>Использование:</span>
                            <span class='metric-value'>\${data.cpu}%</span>
                        </div>
                        <div class='progress-bar'>
                            <div class='progress-fill' style='width: \${data.cpu}%; background-color: \${data.colors.cpu};'></div>
                        </div>
                    `;
                    
                    // Memory
                    if (data.memory) {
                        document.getElementById('memory-usage').innerHTML = `
                            <div class='metric'>
                                <span class='metric-label'>Использовано:</span>
                                <span class='metric-value'>\${data.memory.used_formatted} / \${data.memory.total_formatted}</span>
                            </div>
                            <div class='progress-bar'>
                                <div class='progress-fill' style='width: \${data.memory.percentage}%; background-color: \${data.colors.memory};'></div>
                            </div>
                            <div class='metric'>
                                <span class='metric-label'>Процент:</span>
                                <span class='metric-value'>\${data.memory.percentage}%</span>
                            </div>
                        `;
                    }
                    
                    // Disk
                    if (data.disk) {
                        document.getElementById('disk-usage').innerHTML = `
                            <div class='metric'>
                                <span class='metric-label'>Использовано:</span>
                                <span class='metric-value'>\${data.disk.used_formatted} / \${data.disk.total_formatted}</span>
                            </div>
                            <div class='progress-bar'>
                                <div class='progress-fill' style='width: \${data.disk.percentage}%; background-color: \${data.colors.disk};'></div>
                            </div>
                            <div class='metric'>
                                <span class='metric-label'>Процент:</span>
                                <span class='metric-value'>\${data.disk.percentage}%</span>
                            </div>
                        `;
                    }
                    
                    // Load Average
                    if (data.load_average) {
                        document.getElementById('load-average').innerHTML = `
                            <div class='metric'>
                                <span class='metric-label'>1 минута:</span>
                                <span class='metric-value'>\${data.load_average['1min']}</span>
                            </div>
                            <div class='metric'>
                                <span class='metric-label'>5 минут:</span>
                                <span class='metric-value'>\${data.load_average['5min']}</span>
                            </div>
                            <div class='metric'>
                                <span class='metric-label'>15 минут:</span>
                                <span class='metric-value'>\${data.load_average['15min']}</span>
                            </div>
                        `;
                    }
                    
                    // Uptime
                    if (data.uptime) {
                        document.getElementById('uptime').innerHTML = `
                            <div class='metric-value'>\${data.uptime.formatted}</div>
                        `;
                    }
                    
                    // Processes
                    if (data.processes) {
                        document.getElementById('processes').innerHTML = `
                            <div class='metric'>
                                <span class='metric-label'>Запущено:</span>
                                <span class='metric-value'>\${data.processes.running}</span>
                            </div>
                            <div class='metric'>
                                <span class='metric-label'>Всего:</span>
                                <span class='metric-value'>\${data.processes.total}</span>
                            </div>
                        `;
                    }
                    
                    // Network Interfaces
                    if (data.network) {
                        let networkHtml = '<table><tr><th>Интерфейс</th><th>Статус</th><th>Скорость</th><th>RX</th><th>TX</th><th>Ошибки</th></tr>';
                        for (const [iface, stats] of Object.entries(data.network)) {
                            const statusClass = stats.is_up ? 'status-up' : 'status-down';
                            const statusText = stats.is_up ? 'UP' : 'DOWN';
                            networkHtml += `
                                <tr>
                                    <td><strong>\${iface}</strong></td>
                                    <td><span class='status-indicator \${statusClass}'></span>\${statusText}</td>
                                    <td>\${stats.speed_formatted}</td>
                                    <td>\${stats.rx_bytes_formatted}</td>
                                    <td>\${stats.tx_bytes_formatted}</td>
                                    <td>RX: \${stats.rx_errors}, TX: \${stats.tx_errors}</td>
                                </tr>
                            `;
                        }
                        networkHtml += '</table>';
                        document.getElementById('network-interfaces').innerHTML = networkHtml;
                    }
                }
                
                function refreshMonitorData() {
                    loadMonitorData();
                }
                
                function startMonitorInterval() {
                    monitorInterval = setInterval(loadMonitorData, 60000); // Обновление каждые 60 секунд
                }
                
                function stopMonitorInterval() {
                    if (monitorInterval) {
                        clearInterval(monitorInterval);
                    }
                }
                
                // Загружаем данные при загрузке страницы
                document.addEventListener('DOMContentLoaded', function() {
                    loadMonitorData();
                    startMonitorInterval();
                });
                
                // Останавливаем интервал при уходе со страницы
                window.addEventListener('beforeunload', stopMonitorInterval);
                </script>";
                break;
                
            default: // overview
                $allUsers = $stats->getAllUsersTraffic();
                $activeSessions = $stats->getActiveSessions();
                $totalUsers = count($allUsers);
                $activeUsers = count($activeSessions);
                
                $totalTraffic = 0;
                $currentSessionTraffic = 0;
                foreach ($allUsers as $user) {
                    $totalTraffic += $user['total_traffic_used'];
                    $currentSessionTraffic += $user['current_session_total'];
                }
                
                echo "<h2>Общая статистика</h2>";
                echo "<table>";
                echo "<tr><th>Метрика</th><th>Значение</th></tr>";
                echo "<tr><td>Всего пользователей</td><td>$totalUsers</td></tr>";
                echo "<tr><td>Активных сессий</td><td>$activeUsers</td></tr>";
                echo "<tr><td>Общий трафик всех пользователей</td><td>" . $stats->formatBytes($totalTraffic) . "</td></tr>";
                echo "<tr><td>Трафик текущих сессий</td><td>" . $stats->formatBytes($currentSessionTraffic) . "</td></tr>";
                echo "</table>";
                
                if (!empty($activeSessions)) {
                    echo "<h3>Последние активные сессии</h3>";
                    echo "<table>";
                    echo "<tr><th>Chat ID</th><th>Key Name</th><th>IP</th><th>Трафик за сессию</th><th>Начало сессии</th></tr>";
                    $recentSessions = array_slice($activeSessions, 0, 10);
                    foreach ($recentSessions as $session) {
                        echo "<tr class='active'>";
                        echo "<td>" . htmlspecialchars($session['chat_id']) . "</td>";
                        echo "<td>" . htmlspecialchars($session['key_name']) . "</td>";
                        echo "<td>" . htmlspecialchars($session['ip']) . "</td>";
                        echo "<td>" . $stats->formatBytes($session['session_total']) . "</td>";
                        echo "<td>" . htmlspecialchars($session['session_start']) . "</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                }
                break;
        }
        ?>
        
        <hr>
        <p><small>Последнее обновление: <?= date('Y-m-d H:i:s') ?></small></p>
    </body>
    </html>
    <?php
} else {
    // CLI режим - возможность запуска из командной строки
    echo "Traffic Stats CLI\n";
    echo "Используйте веб-интерфейс для просмотра статистики\n";
}