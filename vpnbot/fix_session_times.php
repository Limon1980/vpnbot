<?php
/**
 * Скрипт для исправления времени сессий в базе данных
 * Запускать вручную для корректировки неправильных времен начала сессий
 */

require_once __DIR__ . '/db.php';

class SessionTimeFixer {
    private $dbh;
    private $logFile;
    
    public function __construct() {
        $this->dbh = new Db();
        $this->logFile = '/var/log/openvpn/status.log';
    }
    
    public function fixSessionTimes() {
        echo "=== Исправление времен сессий ===\n\n";
        
        // Получаем текущие данные из лога OpenVPN
        $currentClients = $this->parseOpenVpnStatus();
        
        if (empty($currentClients)) {
            echo "Нет активных клиентов в лог-файле OpenVPN\n";
            return;
        }
        
        echo "Найдено активных клиентов в OpenVPN: " . count($currentClients) . "\n\n";
        
        // Получаем активные сессии из БД
        $dbSessions = $this->getActiveDbSessions();
        
        $fixed = 0;
        
        foreach ($currentClients as $chatId => $client) {
            if (isset($dbSessions[$chatId])) {
                $dbSession = $dbSessions[$chatId];
                $logTime = $client['connected_since'];
                $dbTime = $dbSession['session_start'];
                
                echo "Chat ID: $chatId\n";
                echo "  Время в логе OpenVPN: $logTime\n";
                echo "  Время в БД:          $dbTime\n";
                
                // Если времена отличаются, обновляем
                if ($logTime !== $dbTime) {
                    echo "  ⚠ Времена отличаются! Обновляем...\n";
                    
                    $sql = "UPDATE vpnusers 
                            SET session_start = :session_start 
                            WHERE chat_id = :chat_id";
                    
                    $this->dbh->query($sql, [
                        ':session_start' => $logTime,
                        ':chat_id' => $chatId
                    ]);
                    
                    echo "  ✓ Время обновлено\n";
                    $fixed++;
                } else {
                    echo "  ✓ Время корректно\n";
                }
                echo "\n";
            } else {
                echo "Chat ID: $chatId - найден в логе OpenVPN, но нет в БД\n\n";
            }
        }
        
        echo "=== Исправление завершено ===\n";
        echo "Исправлено сессий: $fixed\n";
    }
    
    private function parseOpenVpnStatus() {
        if (!file_exists($this->logFile)) {
            throw new Exception("Лог-файл OpenVPN не найден: " . $this->logFile);
        }
        
        $content = file_get_contents($this->logFile);
        if ($content === false) {
            throw new Exception("Не удалось прочитать лог-файл OpenVPN");
        }
        
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
                $inClientSection = false;
                continue;
            }
            
            if ($line === 'Updated,' || strpos($line, 'Common Name,') === 0) {
                continue;
            }
            
            if ($inClientSection && !empty($line)) {
                $parts = explode(',', $line);
                
                if (count($parts) >= 5) {
                    $commonName = trim($parts[0]);
                    $connectedSince = trim($parts[4]);
                    
                    if (preg_match('/VpnOpenBot_(\d+)/', $commonName, $matches)) {
                        $chatId = $matches[1];
                        
                        $clients[$chatId] = [
                            'chat_id' => $chatId,
                            'common_name' => $commonName,
                            'connected_since' => $connectedSince
                        ];
                    }
                }
            }
        }
        
        return $clients;
    }
    
    private function getActiveDbSessions() {
        $sql = "SELECT chat_id, session_start 
                FROM vpnusers 
                WHERE session_start IS NOT NULL 
                AND key_name IS NOT NULL 
                AND key_name != ''";
        
        $result = $this->dbh->query($sql);
        $sessions = [];
        
        foreach ($result as $row) {
            $sessions[$row['chat_id']] = $row;
        }
        
        return $sessions;
    }
}

// Запуск исправления
if (php_sapi_name() === 'cli') {
    $fixer = new SessionTimeFixer();
    $fixer->fixSessionTimes();
} else {
    header('Content-Type: text/plain; charset=utf-8');
    echo "Этот скрипт должен запускаться из командной строки:\n";
    echo "php fix_session_times.php\n";
}