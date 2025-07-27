<?php
/**
 * Тестовый скрипт для проверки системы учета трафика
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/fun.lib.php';

class TrafficTest {
    private $dbh;
    
    public function __construct() {
        $this->dbh = new Db();
    }
    
    public function runTests() {
        echo "=== Тестирование системы учета трафика ===\n\n";
        
        // Тест 1: Проверка доступности лог-файла OpenVPN
        $this->testOpenVpnLogFile();
        
        // Тест 2: Проверка подключения к БД
        $this->testDatabaseConnection();
        
        // Тест 3: Проверка функций форматирования
        $this->testFormatFunctions();
        
        // Тест 4: Проверка данных в БД
        $this->testDatabaseData();
        
        // Тест 5: Симуляция парсинга лог-файла
        $this->testLogParsing();
        
        echo "\n=== Тестирование завершено ===\n";
    }
    
    private function testOpenVpnLogFile() {
        echo "1. Проверка лог-файла OpenVPN...\n";
        $logFile = '/var/log/openvpn/status.log';
        
        if (file_exists($logFile)) {
            echo "   ✓ Файл существует: $logFile\n";
            
            if (is_readable($logFile)) {
                echo "   ✓ Файл доступен для чтения\n";
                
                $content = file_get_contents($logFile);
                if ($content !== false) {
                    echo "   ✓ Файл успешно прочитан (" . strlen($content) . " байт)\n";
                    
                    if (strpos($content, 'OpenVPN CLIENT LIST') !== false) {
                        echo "   ✓ Формат файла корректен\n";
                    } else {
                        echo "   ⚠ Предупреждение: не найден заголовок 'OpenVPN CLIENT LIST'\n";
                    }
                } else {
                    echo "   ✗ Ошибка чтения файла\n";
                }
            } else {
                echo "   ✗ Файл недоступен для чтения\n";
            }
        } else {
            echo "   ✗ Файл не найден: $logFile\n";
        }
        echo "\n";
    }
    
    private function testDatabaseConnection() {
        echo "2. Проверка подключения к базе данных...\n";
        
        try {
            $result = $this->dbh->query("SELECT COUNT(*) as count FROM vpnusers");
            if ($result) {
                $count = $result[0]['count'];
                echo "   ✓ Подключение к БД успешно\n";
                echo "   ✓ Найдено $count записей в таблице vpnusers\n";
            } else {
                echo "   ✗ Ошибка выполнения запроса\n";
            }
        } catch (Exception $e) {
            echo "   ✗ Ошибка подключения к БД: " . $e->getMessage() . "\n";
        }
        echo "\n";
    }
    
    private function testFormatFunctions() {
        echo "3. Проверка функций форматирования...\n";
        
        $testValues = [
            512 => '512 B',
            1024 => '1 KB',
            1048576 => '1 MB',
            1073741824 => '1 GB'
        ];
        
        foreach ($testValues as $bytes => $expected) {
            $result = formatBytes($bytes);
            if ($result === $expected) {
                echo "   ✓ $bytes байт = $result\n";
            } else {
                echo "   ⚠ $bytes байт = $result (ожидалось: $expected)\n";
            }
        }
        echo "\n";
    }
    
    private function testDatabaseData() {
        echo "4. Проверка данных в базе...\n";
        
        try {
            // Проверяем пользователей с VPN ключами
            $users = $this->dbh->query("SELECT chat_id, key_name FROM vpnusers WHERE key_name IS NOT NULL AND key_name != ''");
            echo "   ✓ Пользователей с VPN ключами: " . count($users) . "\n";
            
            // Проверяем активные сессии
            $active = $this->dbh->query("SELECT chat_id FROM vpnusers WHERE session_start IS NOT NULL");
            echo "   ✓ Активных сессий в БД: " . count($active) . "\n";
            
            // Проверяем пользователей с трафиком
            $withTraffic = $this->dbh->query("SELECT chat_id FROM vpnusers WHERE (recive_byte > 0 OR sent_byte > 0 OR full_recive_byte > 0 OR full_sent_byte > 0)");
            echo "   ✓ Пользователей с трафиком: " . count($withTraffic) . "\n";
            
        } catch (Exception $e) {
            echo "   ✗ Ошибка: " . $e->getMessage() . "\n";
        }
        echo "\n";
    }
    
    private function testLogParsing() {
        echo "5. Тестирование парсинга лог-файла...\n";
        
        // Создаем тестовые данные
        $testLogContent = "OpenVPN CLIENT LIST
Updated,2025-01-27 04:34:15
Common Name,Real Address,Bytes Received,Bytes Sent,Connected Since
VpnOpenBot_553535640,188.43.18.221:56729,7772618,274318389,2025-01-27 04:10:10
VpnOpenBot_1861525967,176.15.240.22:20189,33155279,690840941,2025-01-26 14:31:33
ROUTING TABLE
Virtual Address,Common Name,Real Address,Last Ref
10.8.0.7,VpnOpenBot_1861525967,176.15.240.22:20189,2025-01-27 04:34:08
10.8.0.10,VpnOpenBot_553535640,188.43.18.221:56729,2025-01-27 04:34:14
GLOBAL STATS
Max bcast/mcast queue length,4
END";
        
        $clients = $this->parseTestLog($testLogContent);
        
        if (count($clients) === 2) {
            echo "   ✓ Парсинг успешен, найдено " . count($clients) . " клиентов\n";
            
            foreach ($clients as $chatId => $client) {
                echo "   ✓ Клиент $chatId: " . formatBytes($client['bytes_received'] + $client['bytes_sent']) . " трафика\n";
            }
        } else {
            echo "   ✗ Ошибка парсинга, найдено " . count($clients) . " клиентов (ожидалось 2)\n";
        }
        echo "\n";
    }
    
    private function parseTestLog($content) {
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
                    $bytesReceived = (int)trim($parts[2]);
                    $bytesSent = (int)trim($parts[3]);
                    
                    if (preg_match('/VpnOpenBot_(\d+)/', $commonName, $matches)) {
                        $chatId = $matches[1];
                        $clients[$chatId] = [
                            'bytes_received' => $bytesReceived,
                            'bytes_sent' => $bytesSent
                        ];
                    }
                }
            }
        }
        
        return $clients;
    }
}

// Запуск тестов
if (php_sapi_name() === 'cli') {
    $test = new TrafficTest();
    $test->runTests();
} else {
    header('Content-Type: text/plain; charset=utf-8');
    $test = new TrafficTest();
    ob_start();
    $test->runTests();
    $output = ob_get_clean();
    echo nl2br(htmlspecialchars($output));
}