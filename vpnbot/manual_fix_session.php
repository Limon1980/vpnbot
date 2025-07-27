<?php
/**
 * Скрипт для ручного исправления времени сессии
 */

require_once __DIR__ . '/db.php';

// Укажите chat_id пользователя и правильное время начала сессии
$chat_id = 1861525967;
$correct_session_start = '2025-07-26 14:31:33';

echo "Исправление времени сессии для пользователя $chat_id\n";
echo "Устанавливаем время начала сессии: $correct_session_start\n\n";

try {
    $dbh = new Db();
    
    // Проверяем текущие данные
    $current = $dbh->query("SELECT chat_id, session_start, recive_byte, sent_byte FROM vpnusers WHERE chat_id = :chat_id", [':chat_id' => $chat_id]);
    
    if (empty($current)) {
        echo "Ошибка: Пользователь с chat_id $chat_id не найден\n";
        exit(1);
    }
    
    $user = $current[0];
    echo "Текущие данные:\n";
    echo "  Chat ID: " . $user['chat_id'] . "\n";
    echo "  Время сессии: " . $user['session_start'] . "\n";
    echo "  Получено: " . $user['recive_byte'] . " байт\n";
    echo "  Отправлено: " . $user['sent_byte'] . " байт\n\n";
    
    // Обновляем время сессии
    $sql = "UPDATE vpnusers SET session_start = :session_start WHERE chat_id = :chat_id";
    $result = $dbh->query($sql, [
        ':session_start' => $correct_session_start,
        ':chat_id' => $chat_id
    ]);
    
    echo "✓ Время сессии обновлено успешно!\n";
    
    // Проверяем результат
    $updated = $dbh->query("SELECT session_start FROM vpnusers WHERE chat_id = :chat_id", [':chat_id' => $chat_id]);
    echo "Новое время сессии: " . $updated[0]['session_start'] . "\n";
    
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}