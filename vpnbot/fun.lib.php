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