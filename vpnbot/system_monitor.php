<?php
/**
 * Система мониторинга сервера для админ панели
 */

class SystemMonitor {
    private $cacheFile;
    private $cacheTime = 300; // 5 минут кеша
    
    public function __construct() {
        $this->cacheFile = '/tmp/vpn_system_monitor.json';
    }
    
    /**
     * Получить данные мониторинга (с кешированием)
     */
    public function getSystemData() {
        // Проверяем кеш
        if (file_exists($this->cacheFile)) {
            $cacheData = json_decode(file_get_contents($this->cacheFile), true);
            if ($cacheData && (time() - $cacheData['timestamp']) < $this->cacheTime) {
                return $cacheData['data'];
            }
        }
        
        // Собираем новые данные
        $data = [
            'timestamp' => time(),
            'cpu' => $this->getCpuUsage(),
            'memory' => $this->getMemoryUsage(),
            'disk' => $this->getDiskUsage(),
            'network' => $this->getNetworkStats(),
            'load_average' => $this->getLoadAverage(),
            'uptime' => $this->getUptime(),
            'processes' => $this->getProcessCount()
        ];
        
        // Сохраняем в кеш
        $cacheData = [
            'timestamp' => time(),
            'data' => $data
        ];
        file_put_contents($this->cacheFile, json_encode($cacheData));
        
        return $data;
    }
    
    /**
     * Получить использование CPU
     */
    private function getCpuUsage() {
        // Метод 1: через /proc/stat
        $stat1 = $this->parseCpuStat();
        sleep(1); // Пауза для измерения
        $stat2 = $this->parseCpuStat();
        
        if ($stat1 && $stat2) {
            $total1 = array_sum($stat1);
            $total2 = array_sum($stat2);
            
            $idle1 = $stat1[3];
            $idle2 = $stat2[3];
            
            $totalDiff = $total2 - $total1;
            $idleDiff = $idle2 - $idle1;
            
            if ($totalDiff > 0) {
                $cpuUsage = 100 - (($idleDiff / $totalDiff) * 100);
                return round($cpuUsage, 2);
            }
        }
        
        // Метод 2: через top (резервный)
        $output = shell_exec("top -bn1 | grep 'Cpu(s)' | awk '{print $2}' | cut -d'%' -f1");
        if ($output) {
            return round((float)$output, 2);
        }
        
        return 0;
    }
    
    /**
     * Парсинг /proc/stat для CPU
     */
    private function parseCpuStat() {
        $stat = file_get_contents('/proc/stat');
        if ($stat) {
            $lines = explode("\n", $stat);
            $cpuLine = $lines[0];
            $values = preg_split('/\s+/', $cpuLine);
            
            // Возвращаем значения: user, nice, system, idle, iowait, irq, softirq
            return array_slice(array_map('intval', $values), 1, 7);
        }
        return false;
    }
    
    /**
     * Получить использование памяти
     */
    private function getMemoryUsage() {
        $meminfo = file_get_contents('/proc/meminfo');
        if ($meminfo) {
            preg_match('/MemTotal:\s+(\d+)/', $meminfo, $totalMatch);
            preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $availableMatch);
            
            if ($totalMatch && $availableMatch) {
                $total = (int)$totalMatch[1] * 1024; // KB to bytes
                $available = (int)$availableMatch[1] * 1024;
                $used = $total - $available;
                
                return [
                    'total' => $total,
                    'used' => $used,
                    'free' => $available,
                    'percentage' => round(($used / $total) * 100, 2)
                ];
            }
        }
        
        return null;
    }
    
    /**
     * Получить использование диска
     */
    private function getDiskUsage() {
        $path = '/';
        $total = disk_total_space($path);
        $free = disk_free_space($path);
        
        if ($total && $free) {
            $used = $total - $free;
            return [
                'total' => $total,
                'used' => $used,
                'free' => $free,
                'percentage' => round(($used / $total) * 100, 2)
            ];
        }
        
        return null;
    }
    
    /**
     * Получить статистику сетевых интерфейсов
     */
    private function getNetworkStats() {
        $interfaces = [];
        
        // Читаем /proc/net/dev
        $netDev = file_get_contents('/proc/net/dev');
        if ($netDev) {
            $lines = explode("\n", $netDev);
            
            foreach ($lines as $line) {
                $line = trim($line);
                if (preg_match('/^(\w+):\s*(.+)/', $line, $matches)) {
                    $interface = $matches[1];
                    $stats = preg_split('/\s+/', trim($matches[2]));
                    
                    // Пропускаем loopback
                    if ($interface === 'lo') continue;
                    
                    if (count($stats) >= 16) {
                        $interfaces[$interface] = [
                            'rx_bytes' => (int)$stats[0],
                            'rx_packets' => (int)$stats[1],
                            'rx_errors' => (int)$stats[2],
                            'rx_dropped' => (int)$stats[3],
                            'tx_bytes' => (int)$stats[8],
                            'tx_packets' => (int)$stats[9],
                            'tx_errors' => (int)$stats[10],
                            'tx_dropped' => (int)$stats[11]
                        ];
                    }
                }
            }
        }
        
        // Получаем скорость интерфейсов
        foreach ($interfaces as $iface => &$data) {
            $speed = $this->getInterfaceSpeed($iface);
            $data['speed'] = $speed;
            $data['is_up'] = $this->isInterfaceUp($iface);
        }
        
        return $interfaces;
    }
    
    /**
     * Получить скорость сетевого интерфейса
     */
    private function getInterfaceSpeed($interface) {
        $speedFile = "/sys/class/net/$interface/speed";
        if (file_exists($speedFile)) {
            $speed = (int)file_get_contents($speedFile);
            return $speed > 0 ? $speed : null;
        }
        return null;
    }
    
    /**
     * Проверить, активен ли интерфейс
     */
    private function isInterfaceUp($interface) {
        $operStateFile = "/sys/class/net/$interface/operstate";
        if (file_exists($operStateFile)) {
            $state = trim(file_get_contents($operStateFile));
            return $state === 'up';
        }
        return false;
    }
    
    /**
     * Получить load average
     */
    private function getLoadAverage() {
        $loadavg = file_get_contents('/proc/loadavg');
        if ($loadavg) {
            $values = explode(' ', trim($loadavg));
            return [
                '1min' => (float)$values[0],
                '5min' => (float)$values[1],
                '15min' => (float)$values[2]
            ];
        }
        return null;
    }
    
    /**
     * Получить uptime системы
     */
    private function getUptime() {
        $uptime = file_get_contents('/proc/uptime');
        if ($uptime) {
            $seconds = (float)explode(' ', trim($uptime))[0];
            
            $days = floor($seconds / 86400);
            $hours = floor(($seconds % 86400) / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            
            return [
                'seconds' => $seconds,
                'formatted' => sprintf('%d дней, %d часов, %d минут', $days, $hours, $minutes)
            ];
        }
        return null;
    }
    
    /**
     * Получить количество процессов
     */
    private function getProcessCount() {
        $stat = file_get_contents('/proc/stat');
        if ($stat) {
            if (preg_match('/processes (\d+)/', $stat, $matches)) {
                $total = (int)$matches[1];
            }
        }
        
        // Количество запущенных процессов
        $running = (int)shell_exec("ps aux | wc -l") - 1;
        
        return [
            'total' => $total ?? $running,
            'running' => $running
        ];
    }
    
    /**
     * Форматирование байтов
     */
    public function formatBytes($size, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        
        return round($size, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Получить цвет для процентного значения
     */
    public function getPercentageColor($percentage) {
        if ($percentage < 50) return '#28a745'; // зеленый
        if ($percentage < 80) return '#ffc107'; // желтый
        return '#dc3545'; // красный
    }
}