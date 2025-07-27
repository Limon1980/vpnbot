<?php
include('db.php');
$dbh = new Db();

$token = '7633588710:AAH4vYAT4fSfew_tuElIyzSBNKGJujkRL9M';
function sendTm($token, $method, $request_params)
{
    // Формирование URL-адреса запроса
    $url = 'https://api.telegram.org/bot' . $token . '/' . $method;

    // Инициализация cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $request_params);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Отправка запроса и получение ответа
    $result = curl_exec($ch);
    curl_close($ch);

    // Декодирование ответа JSON
    $result = json_decode($result, true);

    // Обработка ответа
    if ($result['ok']) {
        // Успешная отправка сообщения
        echo 'Сообщение отправлено успешно.' . PHP_EOL;
    } else {
        // Ошибка отправки сообщения
        echo 'Ошибка отправки сообщения: ' . $result['description'] . PHP_EOL;
    }
}

try {
    // Получаем строки, где day = 0 и tarif = 'base'
    $result = $dbh->query("SELECT chat_id, ip, tarif FROM vpnusers WHERE day = 0 AND tarif = 'base'");
    
    // Обновляем поле tarif на 'block' для строк с day = 0 и tarif = 'base'
    $dbh->query("UPDATE vpnusers SET tarif = 'block' WHERE day = 0 AND tarif = 'base'");
    
    // Обрабатываем каждую строку
    foreach ($result as $row) {
        $ip = $row['ip'];
		$chat_id = $row['chat_id'];
        // Проверяем, что IP-адрес не пустой
        if (!empty($ip)) {
            // Формируем команду для проверки и добавления правила iptables
            $cmd = "sudo /usr/sbin/iptables -C FORWARD -s " . escapeshellarg($ip) . " -j DROP 2>/dev/null || sudo /usr/sbin/iptables -I FORWARD -s " . escapeshellarg($ip) . " -j DROP";
            exec($cmd, $output, $return_var);
            
            // Проверяем результат выполнения команды
            if ($return_var !== 0) {
                error_log("Ошибка при блокировке IP $ip: " . implode(", ", $output));
            } else {
                error_log("Успешно заблокирован IP $ip");
            }
			
			$request_params = [
					'chat_id'   => $chat_id,
					'text' => "\n🚫 Доступ закрыт: Ваш тариф заблокирован. Пожалуйста, оплатите подписку для восстановления доступа.",
					// 'disable_notification' => 1,
					];

	
			sendTm($token, 'sendMessage', $request_params);	
        }
    }
    
    // Логируем количество обработанных записей
    error_log("Обработано записей: " . count($result));
    return $result;
} catch (Exception $e) {
    // Обработка ошибки
    error_log("Ошибка: " . $e->getMessage());
}
?>