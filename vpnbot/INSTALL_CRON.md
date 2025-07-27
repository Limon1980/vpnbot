# Установка cron задачи для обновления сессий

## 1. Откройте cron редактор
```bash
sudo crontab -e
```

## 2. Добавьте следующую строку в конец файла
```bash
# Обновление данных VPN сессий каждые 5 минут
*/5 * * * * /usr/bin/php /var/www/html/vpnbot/update_sessions.php >> /var/log/vpn_sessions_update.log 2>&1
```

## 3. Создайте файл лога (опционально)
```bash
sudo touch /var/log/vpn_sessions_update.log
sudo chown www-data:www-data /var/log/vpn_sessions_update.log
```

## 4. Проверьте, что cron работает
```bash
sudo systemctl status cron
```

## 5. Проверьте логи через несколько минут
```bash
tail -f /var/log/vpn_sessions_update.log
```

## Альтернативный вариант - запуск через пользователя www-data
```bash
sudo -u www-data crontab -e
```

И добавьте:
```bash
*/5 * * * * /usr/bin/php /var/www/html/vpnbot/update_sessions.php >> /var/log/vpn_sessions_update.log 2>&1
```

## Проверка путей
Убедитесь, что пути корректны:
- `/var/www/html/vpnbot/update_sessions.php` - путь к скрипту
- `/var/log/openvpn/status.log` - путь к файлу статуса OpenVPN
- `/usr/bin/php` - путь к PHP (проверьте командой `which php`)