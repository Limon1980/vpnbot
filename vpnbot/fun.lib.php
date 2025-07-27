<?php

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
	return $result;

    // Обработка ответа
    if ($result['ok']) {
        // Успешная отправка сообщения
        echo 'Сообщение отправлено успешно.' . PHP_EOL;
    } else {
        // Ошибка отправки сообщения
        echo 'Ошибка отправки сообщения: ' . $result['description'] . PHP_EOL;
    }
}

function findFreeTarifs($chat_id) {

		$base = new Db();
		$data = $base->query("SELECT * FROM vpnusers WHERE chat_id=$chat_id");
		$freeTarif = $data[0]['day'];
	
		return $freeTarif;

}

function formatBytes($size, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    
    return round($size, $precision) . ' ' . $units[$i];
}

function getUserTrafficInfo($chat_id) {
    $base = new Db();
    $data = $base->query("SELECT recive_byte, sent_byte, full_recive_byte, full_sent_byte, session_start FROM vpnusers WHERE chat_id = :chat_id", [':chat_id' => $chat_id]);
    
    if (empty($data)) {
        return null;
    }
    
    $traffic = $data[0];
    
    // Текущая сессия
    $currentReceived = (int)$traffic['recive_byte'];
    $currentSent = (int)$traffic['sent_byte'];
    $currentTotal = $currentReceived + $currentSent;
    
    // Общий трафик
    $totalReceived = (int)$traffic['full_recive_byte'] + $currentReceived;
    $totalSent = (int)$traffic['full_sent_byte'] + $currentSent;
    $totalTraffic = $totalReceived + $totalSent;
    
    return [
        'current_received' => $currentReceived,
        'current_sent' => $currentSent,
        'current_total' => $currentTotal,
        'total_received' => $totalReceived,
        'total_sent' => $totalSent,
        'total_traffic' => $totalTraffic,
        'session_start' => $traffic['session_start'],
        'is_active' => !empty($traffic['session_start']) && $currentTotal > 0
    ];
}

?>