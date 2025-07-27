<?php
include('db.php');

class TrafficStats {
    private $dbh;
    
    public function __construct() {
        $this->dbh = new Db();
    }
    
    /**
     * Получение топ пользователей по трафику
     */
    public function getTopTrafficUsers($limit = 10) {
        try {
            $sql = "SELECT 
                        chat_id,
                        key_name, 
                        ip,
                        recive_byte,
                        sent_byte,
                        (recive_byte + sent_byte) as total_traffic,
                        full_recive_byte,
                        full_sent_byte,
                        (full_recive_byte + full_sent_byte) as total_full_traffic,
                        session_start,
                        reg_date
                    FROM vpnusers 
                    WHERE tarif != 'block' AND key_name != ''
                    ORDER BY total_full_traffic DESC 
                    LIMIT :limit";
            
            return $this->dbh->query($sql, [':limit' => $limit]);
        } catch (Exception $e) {
            error_log("Error in getTopTrafficUsers: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Получение активных сессий
     */
    public function getActiveSessions() {
        try {
            $sql = "SELECT 
                        chat_id,
                        key_name, 
                        ip,
                        recive_byte,
                        sent_byte,
                        (recive_byte + sent_byte) as session_traffic,
                        session_start
                    FROM vpnusers 
                    WHERE tarif != 'block' AND key_name != '' AND session_start IS NOT NULL
                    ORDER BY session_start DESC";
            
            return $this->dbh->query($sql);
        } catch (Exception $e) {
            error_log("Error in getActiveSessions: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Обновление данных сессии из status.log
     */
    public function updateSessionData() {
        $statusFile = '/var/log/openvpn/status.log';
        
        if (!file_exists($statusFile)) {
            error_log("Status file not found: $statusFile");
            return false;
        }
        
        try {
            $lines = file($statusFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $inClientList = false;
            $updated = 0;
            
            foreach ($lines as $line) {
                if (strpos($line, 'OpenVPN CLIENT LIST') !== false) {
                    $inClientList = true;
                    continue;
                }
                
                if (strpos($line, 'ROUTING TABLE') !== false) {
                    $inClientList = false;
                    break;
                }
                
                if ($inClientList && strpos($line, 'Common Name,Real Address') !== false) {
                    continue; // Пропускаем заголовок
                }
                
                if ($inClientList && strpos($line, 'Updated,') !== false) {
                    continue; // Пропускаем строку обновления
                }
                
                if ($inClientList && !empty(trim($line))) {
                    $parts = explode(',', $line);
                    if (count($parts) >= 5) {
                        $keyName = trim($parts[0]);
                        $realAddress = trim($parts[1]);
                        $bytesReceived = (int)trim($parts[2]);
                        $bytesSent = (int)trim($parts[3]);
                        $connectedSince = trim($parts[4]);
                        
                        // Извлекаем chat_id из key_name (формат: VpnOpenBot_1861525967)
                        if (preg_match('/VpnOpenBot_(\d+)/', $keyName, $matches)) {
                            $chatId = $matches[1];
                            
                            // Получаем текущие данные пользователя
                            $currentData = $this->dbh->query(
                                "SELECT full_recive_byte, full_sent_byte FROM vpnusers WHERE chat_id = :chat_id",
                                [':chat_id' => $chatId]
                            );
                            
                            $fullReceived = isset($currentData[0]) ? $currentData[0]['full_recive_byte'] : 0;
                            $fullSent = isset($currentData[0]) ? $currentData[0]['full_sent_byte'] : 0;
                            
                            // Если это новая сессия (данные трафика больше текущих), обновляем полный трафик
                            if ($bytesReceived > 0 && $bytesSent > 0) {
                                $fullReceived = max($fullReceived, $bytesReceived);
                                $fullSent = max($fullSent, $bytesSent);
                            }
                            
                            // Обновляем данные в базе
                            $sql = "UPDATE vpnusers SET 
                                        recive_byte = :recive_byte,
                                        sent_byte = :sent_byte,
                                        full_recive_byte = :full_recive_byte,
                                        full_sent_byte = :full_sent_byte,
                                        session_start = :session_start
                                    WHERE chat_id = :chat_id AND key_name = :key_name";
                            
                            $this->dbh->query($sql, [
                                ':recive_byte' => $bytesReceived,
                                ':sent_byte' => $bytesSent,
                                ':full_recive_byte' => $fullReceived,
                                ':full_sent_byte' => $fullSent,
                                ':session_start' => $connectedSince,
                                ':chat_id' => $chatId,
                                ':key_name' => $keyName
                            ]);
                            
                            $updated++;
                        }
                    }
                }
            }
            
            return $updated;
        } catch (Exception $e) {
            error_log("Error updating session data: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Форматирование байт в читаемый вид
     */
    public function formatBytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}

// Пример использования для отображения топ пользователей
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    $stats = new TrafficStats();
    
    echo "<h2>Топ пользователей по трафику</h2>";
    $topUsers = $stats->getTopTrafficUsers(10);
    
    echo "<table border='1'>";
    echo "<tr><th>Chat ID</th><th>Key Name</th><th>IP</th><th>Получено</th><th>Отправлено</th><th>Всего за сессию</th><th>Всего за все время</th><th>Начало сессии</th></tr>";
    
    foreach ($topUsers as $user) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($user['chat_id']) . "</td>";
        echo "<td>" . htmlspecialchars($user['key_name']) . "</td>";
        echo "<td>" . htmlspecialchars($user['ip']) . "</td>";
        echo "<td>" . $stats->formatBytes($user['recive_byte']) . "</td>";
        echo "<td>" . $stats->formatBytes($user['sent_byte']) . "</td>";
        echo "<td>" . $stats->formatBytes($user['total_traffic']) . "</td>";
        echo "<td>" . $stats->formatBytes($user['total_full_traffic']) . "</td>";
        echo "<td>" . htmlspecialchars($user['session_start']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h2>Активные сессии</h2>";
    $activeSessions = $stats->getActiveSessions();
    
    echo "<table border='1'>";
    echo "<tr><th>Chat ID</th><th>Key Name</th><th>IP</th><th>Получено</th><th>Отправлено</th><th>Всего за сессию</th><th>Начало сессии</th></tr>";
    
    foreach ($activeSessions as $session) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($session['chat_id']) . "</td>";
        echo "<td>" . htmlspecialchars($session['key_name']) . "</td>";
        echo "<td>" . htmlspecialchars($session['ip']) . "</td>";
        echo "<td>" . $stats->formatBytes($session['recive_byte']) . "</td>";
        echo "<td>" . $stats->formatBytes($session['sent_byte']) . "</td>";
        echo "<td>" . $stats->formatBytes($session['session_traffic']) . "</td>";
        echo "<td>" . htmlspecialchars($session['session_start']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}
?>