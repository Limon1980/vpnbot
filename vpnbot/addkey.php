<?php
// $clientName = 'test6123';
function addOpenVpnClient($clientName) {
	
	
    $expectScript = '/var/www/html/add_vpn_user.expect';
    $cmd = "expect $expectScript $clientName";
    exec($cmd, $output, $return_var);

    if ($return_var !== 0) {
        throw new Exception("Ошибка при запуске expect-скрипта");
    }
	
	

    return [
        'client_name' => $clientName,
        'output' => $output
    ];
}





function createClientConfig($clientName, $initialIp = null) {
    $directory = '/etc/openvpn/ccd';
    $lastIpFile = $directory . '/last_ip.txt';
    $maxIp = '10.8.3.254';
    $filePath = $directory . '/' . 'VpnOpenBot_'.$clientName;

    // Проверка существования директории
    if (!is_dir($directory)) {
        if (!mkdir($directory, 0755, true)) {
            return "Ошибка: не удалось создать директорию $directory";
        }
    }

    // Инициализация последнего IP, если файла нет
    if (!file_exists($lastIpFile)) {
        $startIp = $initialIp ?: '10.8.0.1';
        file_put_contents($lastIpFile, $startIp);
    }

    // Открытие файла last_ip.txt с блокировкой
    $fp = fopen($lastIpFile, 'r+');
    if ($fp === false) {
        return "Ошибка: не удалось открыть last_ip.txt";
    }
    if (flock($fp, LOCK_EX)) {
        $lastIp = trim(fgets($fp));
        $lastIpLong = ip2long($lastIp);
        if ($lastIpLong === false) {
            flock($fp, LOCK_UN);
            fclose($fp);
            return "Ошибка: неверный IP в last_ip.txt";
        }
        $nextIpLong = $lastIpLong + 1;
        $nextIp = long2ip($nextIpLong);
        $maxIpLong = ip2long($maxIp);
        if ($nextIpLong > $maxIpLong) {
            flock($fp, LOCK_UN);
            fclose($fp);
            return "Ошибка: пул IP-адресов исчерпан";
        }
        // Запись нового последнего IP
        fseek($fp, 0);
        ftruncate($fp, 0);
        fwrite($fp, $nextIp);
        fflush($fp);
        flock($fp, LOCK_UN);
    } else {
        fclose($fp);
        return "Ошибка: не удалось заблокировать last_ip.txt";
    }
    fclose($fp);

    // Проверка, существует ли файл клиента
    if (file_exists($filePath)) {
        return "Ошибка: файл конфигурации клиента уже существует";
    }

    // Запись конфигурации клиента
    $content = "ifconfig-push $nextIp 255.255.252.0\n";
    if (file_put_contents($filePath, $content) === false) {
        return "Ошибка: не удалось записать в файл $filePath";
    }

    return $nextIp;
}



/* $ip = createClientConfig($clientName);


if ($ip){
	$dbh->query("
    UPDATE vpnusers 
    SET 
        ip = :ip,
        day = :day,
        key_name = :key_name,
        tarif = :tarif,
        session_start = NOW()
    WHERE chat_id = :chat_id
", [
    ':ip' => $ip,
    ':day' => 30,
    ':key_name' => 'VpnOpenBot_'.$clientName,
    ':tarif' => 'base',
    ':chat_id' => $chat_id
]);
		
		$filePath = "/home/www-data/VpnOpenBot__" . $chat_id . ".ovpn";

				if (file_exists($filePath)) {
					$telegram->sendDocument([
						'chat_id' => $chat_id,
						'document' => InputFile::create($filePath, "VpnOpenBot_" . $chat_id . ".ovpn"),
						'caption' => "🔑 Ваш VPN ключ"
					]);
				} else {
					$telegram->sendMessage([
						'chat_id' => $chat_id,
						'text' => "⚠️ Файл ключа не найден. Обратитесь в поддержку."
					]);
				}
		
}
 */

			

// Вернет и запишет 10.8.0.3

?>
