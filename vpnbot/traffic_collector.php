<?php
/**
 * Скрипт для сбора трафика из логов OpenVPN
 * Запускается по cron каждую минуту
 * 
 * Добавить в crontab:
 * * * * * * /usr/bin/php /path/to/vpnbot/traffic_collector.php >/dev/null 2>&1
 */

ini_set('display_errors', false);
ini_set('log_errors', true);
ini_set('error_log', '/var/log/vpnbot_traffic.log');

require_once __DIR__ . '/db.php';

class TrafficCollector {
    private $dbh;
    private $logFile;
    
    public function __construct() {
        $this->dbh = new Db();
        $this->logFile = '/var/log/openvpn/status.log';
    }
    
    /**
     * Главный метод для сбора и обновления трафика
     */
    public function collectTraffic() {
        try {
            // Получаем данные из лог-файла OpenVPN
            $clientsData = $this->parseOpenVpnStatus();
            
            if (empty($clientsData)) {
                $this->log("Нет активных клиентов в лог-файле");
                return;
            }
            
            // Получаем текущие данные о трафике из БД
            $currentTraffic = $this->getCurrentTrafficFromDB();
            
            // Обновляем трафик для активных сессий
            $this->updateActiveTraffic($clientsData, $currentTraffic);
            
            // Проверяем завершенные сессии и переносим трафик в полные счетчики
            $this->processCompletedSessions($clientsData, $currentTraffic);
            
            $this->log("Трафик успешно обновлен для " . count($clientsData) . " клиентов");
            
        } catch (Exception $e) {
            $this->log("Ошибка при сборе трафика: " . $e->getMessage());
        }
    }
    
    /**
     * Парсит файл status.log OpenVPN
     */
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
            
            // Начало секции клиентов
            if ($line === 'OpenVPN CLIENT LIST') {
                $inClientSection = true;
                continue;
            }
            
            // Конец секции клиентов
            if ($line === 'ROUTING TABLE' || $line === 'GLOBAL STATS') {
                $inClientSection = false;
                continue;
            }
            
            // Пропускаем заголовки
            if ($line === 'Updated,' || strpos($line, 'Common Name,') === 0) {
                continue;
            }
            
            // Обрабатываем строки с данными клиентов
            if ($inClientSection && !empty($line)) {
                $parts = explode(',', $line);
                
                if (count($parts) >= 5) {
                    $commonName = trim($parts[0]);
                    $realAddress = trim($parts[1]);
                    $bytesReceived = (int)trim($parts[2]);
                    $bytesSent = (int)trim($parts[3]);
                    $connectedSince = trim($parts[4]);
                    
                    // Извлекаем chat_id из имени клиента (формат: VpnOpenBot_chat_id)
                    if (preg_match('/VpnOpenBot_(\d+)/', $commonName, $matches)) {
                        $chatId = $matches[1];
                        
                        // Преобразуем время из формата OpenVPN в формат MySQL
                        $mysqlDateTime = $this->convertOpenVpnTimeToMysql($connectedSince);
                        
                        $clients[$chatId] = [
                            'chat_id' => $chatId,
                            'common_name' => $commonName,
                            'real_address' => $realAddress,
                            'bytes_received' => $bytesReceived,
                            'bytes_sent' => $bytesSent,
                            'connected_since' => $mysqlDateTime
                        ];
                    }
                }
            }
        }
        
        return $clients;
    }
    
    /**
     * Получает текущие данные о трафике из базы данных
     */
    private function getCurrentTrafficFromDB() {
        $sql = "SELECT chat_id, recive_byte, sent_byte, full_recive_byte, full_sent_byte, session_start, key_name 
                FROM vpnusers 
                WHERE key_name IS NOT NULL AND key_name != ''";
        
        $result = $this->dbh->query($sql);
        $traffic = [];
        
        foreach ($result as $row) {
            $traffic[$row['chat_id']] = $row;
        }
        
        return $traffic;
    }
    
    /**
     * Обновляет трафик для активных сессий
     */
    private function updateActiveTraffic($clientsData, $currentTraffic) {
        foreach ($clientsData as $chatId => $clientData) {
            // Проверяем, есть ли уже активная сессия в БД
            $isNewSession = !isset($currentTraffic[$chatId]) || 
                           empty($currentTraffic[$chatId]['session_start']) ||
                           ($currentTraffic[$chatId]['recive_byte'] == 0 && $currentTraffic[$chatId]['sent_byte'] == 0) ||
                           // Также проверяем, если время подключения в логе OpenVPN новее, чем в БД (переподключение)
                           (!empty($currentTraffic[$chatId]['session_start']) && 
                            strtotime($clientData['connected_since']) > strtotime($currentTraffic[$chatId]['session_start']));
            
            if ($isNewSession) {
                // Новая сессия - устанавливаем время из лога OpenVPN
                $sql = "UPDATE vpnusers 
                        SET recive_byte = :recive_byte,
                            sent_byte = :sent_byte,
                            session_start = :session_start
                        WHERE chat_id = :chat_id";
                
                $params = [
                    ':recive_byte' => $clientData['bytes_received'],
                    ':sent_byte' => $clientData['bytes_sent'],
                    ':session_start' => $clientData['connected_since'],
                    ':chat_id' => $chatId
                ];
                
                                 // Проверяем, это совсем новая сессия или переподключение
                 $isReconnection = isset($currentTraffic[$chatId]) && 
                                 !empty($currentTraffic[$chatId]['session_start']) &&
                                 strtotime($clientData['connected_since']) > strtotime($currentTraffic[$chatId]['session_start']);
                 
                 if ($isReconnection) {
                     // При переподключении сначала сохраняем трафик предыдущей сессии
                     $this->log("Переподключение для chat_id: $chatId. Старая сессия: " . $currentTraffic[$chatId]['session_start'] . ", новая: " . $clientData['connected_since']);
                     
                     // Добавляем трафик предыдущей сессии к общему счетчику
                     $addTrafficSql = "UPDATE vpnusers 
                                      SET full_recive_byte = full_recive_byte + :old_recive,
                                          full_sent_byte = full_sent_byte + :old_sent
                                      WHERE chat_id = :chat_id";
                     
                     $this->dbh->query($addTrafficSql, [
                         ':old_recive' => $currentTraffic[$chatId]['recive_byte'],
                         ':old_sent' => $currentTraffic[$chatId]['sent_byte'],
                         ':chat_id' => $chatId
                     ]);
                 } else {
                     $this->log("Новая сессия для chat_id: $chatId, начало: " . $clientData['connected_since']);
                 }
            } else {
                // Продолжающаяся сессия - обновляем только трафик
                $sql = "UPDATE vpnusers 
                        SET recive_byte = :recive_byte,
                            sent_byte = :sent_byte
                        WHERE chat_id = :chat_id";
                
                $params = [
                    ':recive_byte' => $clientData['bytes_received'],
                    ':sent_byte' => $clientData['bytes_sent'],
                    ':chat_id' => $chatId
                ];
            }
            
            $this->dbh->query($sql, $params);
        }
    }
    
    /**
     * Обрабатывает завершенные сессии и переносит трафик в полные счетчики
     */
    private function processCompletedSessions($activeClients, $currentTraffic) {
        foreach ($currentTraffic as $chatId => $dbData) {
            // Если клиент был в БД с активной сессией, но сейчас не активен
            if (!isset($activeClients[$chatId]) && 
                !empty($dbData['key_name']) && 
                ($dbData['recive_byte'] > 0 || $dbData['sent_byte'] > 0)) {
                
                // Сессия завершена, добавляем трафик к полным счетчикам
                $sql = "UPDATE vpnusers 
                        SET full_recive_byte = full_recive_byte + recive_byte,
                            full_sent_byte = full_sent_byte + sent_byte,
                            recive_byte = 0,
                            sent_byte = 0,
                            session_start = NULL
                        WHERE chat_id = :chat_id";
                
                $this->dbh->query($sql, [':chat_id' => $chatId]);
                
                $this->log("Сессия завершена для chat_id: $chatId, трафик перенесен в полные счетчики");
            }
        }
    }
    
    /**
     * Преобразует время из формата OpenVPN в формат MySQL
     */
    private function convertOpenVpnTimeToMysql($openVpnTime) {
        // OpenVPN формат: 2025-07-26 14:31:33
        // MySQL формат: 2025-07-26 14:31:33 (тот же, но проверим)
        
        try {
            $dateTime = DateTime::createFromFormat('Y-m-d H:i:s', $openVpnTime);
            if ($dateTime === false) {
                // Пробуем другой формат, если первый не сработал
                $dateTime = new DateTime($openVpnTime);
            }
            return $dateTime->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            $this->log("Ошибка преобразования времени '$openVpnTime': " . $e->getMessage());
            // Возвращаем текущее время как fallback
            return date('Y-m-d H:i:s');
        }
    }
    
    /**
     * Логирование
     */
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        error_log("[$timestamp] TrafficCollector: $message");
    }
}

// Запуск сбора трафика
if (php_sapi_name() === 'cli') {
    $collector = new TrafficCollector();
    $collector->collectTraffic();
} else {
    // Если скрипт вызывается через веб, показываем статус
    header('Content-Type: text/plain');
    echo "Traffic Collector Script\n";
    echo "Этот скрипт должен запускаться через cron.\n";
    echo "Добавьте в crontab: * * * * * /usr/bin/php " . __FILE__ . " >/dev/null 2>&1\n";
}