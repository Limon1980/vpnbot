# Инструкция по установке системы учета трафика

## Быстрая установка

### 1. Проверка готовности системы

Запустите тестовый скрипт для проверки:
```bash
cd /var/www/html/vpnbot
php test_traffic.php
```

### 2. Настройка cron

Добавьте задачу в crontab:
```bash
crontab -e
```

Добавьте строку (измените путь при необходимости):
```
* * * * * /usr/bin/php /var/www/html/vpnbot/traffic_collector.php >/dev/null 2>&1
```

### 3. Настройка прав доступа

```bash
# Создание лог-файла
sudo touch /var/log/vpnbot_traffic.log
sudo chown www-data:www-data /var/log/vpnbot_traffic.log
sudo chmod 644 /var/log/vpnbot_traffic.log

# Права доступа к логу OpenVPN
sudo chgrp www-data /var/log/openvpn/status.log
sudo chmod 640 /var/log/openvpn/status.log
```

### 4. Проверка работы

```bash
# Тестирование исправлений
php /var/www/html/vpnbot/test_fixes.php

# Исправление времен сессий (если нужно)
php /var/www/html/vpnbot/fix_session_times.php

# Запуск сбора трафика вручную
php /var/www/html/vpnbot/traffic_collector.php

# Проверка логов
tail -f /var/log/vpnbot_traffic.log

# Просмотр статистики
# Откройте в браузере: https://ваш_домен/vpnbot/traffic_stats.php
```

## Что происходит после установки

1. **Автоматический сбор**: Каждую минуту собираются данные о трафике
2. **Отображение в боте**: В личном кабинете показывается статистика
3. **Веб-мониторинг**: Доступна панель администратора
4. **Логирование**: Все операции записываются в лог

## Файлы системы

- `traffic_collector.php` - основной скрипт сбора (запускается по cron)
- `traffic_stats.php` - веб-интерфейс для мониторинга
- `test_traffic.php` - скрипт тестирования системы
- `test_fixes.php` - тестирование исправлений
- `fix_session_times.php` - исправление времен сессий
- `fun.lib.php` - дополнительные функции (обновлен)
- `bot.php` - основной бот (обновлен для отображения трафика)

## Устранение проблем

Если что-то не работает:

1. Проверьте тестовым скриптом: `php test_traffic.php`
2. Проверьте логи: `tail -f /var/log/vpnbot_traffic.log`
3. Убедитесь что cron работает: `grep CRON /var/log/syslog`
4. Проверьте права доступа к файлам

## Мониторинг

- Веб-панель: `https://ваш_домен/vpnbot/traffic_stats.php`
- Логи системы: `/var/log/vpnbot_traffic.log`
- Статистика в боте: команда "Личный кабинет"