<?php
include('db.php');
$dbh = new Db();

try {
    $dbh->query("
        UPDATE vpnusers 
        SET day = day - 1
        WHERE day > 0
    ");
} catch (Exception $e) {
    // Обработка ошибки
    echo "Ошибка: " . $e->getMessage();
}
