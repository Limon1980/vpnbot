<?php
// Установить webhook:
// https://api.telegram.org/bot<ТОКЕН>/setWebhook?url=https://твой_домен/vpnbot/bot.php

include('../vendor/autoload.php');
include('db.php');
include('fun.lib.php');
include 'addkey.php';

use Telegram\Bot\Api;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\FileUpload\InputFile;

// Настройки
$token = 'you_token';
$provider_token = 'you_token'; // Тестовый токен ЮKassa
$provider_token = 'you_token'; // Live токен ЮKassa

$telegram = new Api($token);
$dbh = new Db();

// Получаем update
$update = $telegram->getWebhookUpdates();
$chat_id = $update->message->chat->id ?? null;
$text = $update->message->text ?? null;
$callbackQuery = $update->callback_query ?? null;



$referrer_id = null;

// Проверка, пришёл ли пользователь по партнёрской ссылке
if (isset($update['message']['text']) && strpos($update['message']['text'], '/start ') === 0) {
    $parts = explode(' ', $update['message']['text']);
    if (isset($parts[1]) && is_numeric($parts[1])) {
        $referrer_id = $parts[1] != $chat_id ? (int)$parts[1] : null;
		// Показываем главное меню (в любом случае — при старте)
			$reply = "📹 Главное меню";
			$reply_markup = Keyboard::make()
				->setResizeKeyboard(true)
				->setOneTimeKeyboard(false)
				->row(['Инструкция подключения', 'Личный кабинет'])
				->row(['Получить ключ', 'Тарифы'])
				->row(['Партнерская программа']);

			$telegram->sendMessage([
				'chat_id' => $chat_id,
				'text' => $reply,
				'reply_markup' => $reply_markup
			]);
    }
}

if(isset($chat_id)){
// Регистрируем пользователя
// // Проверка, есть ли уже такой пользователь
$exists = $dbh->query("SELECT chat_id FROM vpnusers WHERE chat_id = :chat_id", [':chat_id' => $chat_id]);

if (empty($exists)) {
    try {
        // Выполняем INSERT через query
        $dbh->query("
            INSERT INTO vpnusers (
                chat_id, key_name, ip, tarif, day,
                session_start, recive_byte, sent_byte,
                full_recive_byte, full_sent_byte, reg_date
            ) VALUES (
                :chat_id, :key_name, :ip, :tarif, :day,
                :session_start, :recive_byte, :sent_byte,
                :full_recive_byte, :full_sent_byte, NOW()
            )
        ", [
            ':chat_id' => $chat_id,
            ':key_name' => '', // при необходимости подставить
            ':ip' => '',
            ':tarif' => '',
            ':day' => 0,
            ':session_start' => null,
            ':recive_byte' => 0,
            ':sent_byte' => 0,
            ':full_recive_byte' => 0,
            ':full_sent_byte' => 0
        ]);

        // Если исключение не выброшено, вставка успешна
        $telegram->sendMessage(['chat_id' => $chat_id, 'text' => "✅ Вы зарегистрированы."]);
		
		// Если есть реферер — начисляем ему +10 дней
		if ($referrer_id) {
			$referrerExists = $dbh->query("SELECT chat_id FROM vpnusers WHERE chat_id = :ref_id", [':ref_id' => $referrer_id]);

			if (!empty($referrerExists)) {
				
				// Проверяем текущий тариф и IP пользователя
				$result = $dbh->query("SELECT ip, tarif FROM vpnusers WHERE chat_id = $referrer_id");
				$user = $result[0];
				$ip = $user['ip'];
                $tarif = $user['tarif'];
				
				if ($tarif == 'block' && !empty($ip)) {
                $ip = $user['ip'];
                $tarif = $user['tarif'];
                
                // Обновляем подписку: добавляем дни, обновляем session_start и устанавливаем tarif = 'base'
                $dbh->query("
                    UPDATE vpnusers 
                    SET 
                        day = day + 10,
                        session_start = IF(session_start > NOW(), session_start, NOW()),
                        tarif = 'base'
                    WHERE chat_id = $referrer_id
                ");
                
                // Если тариф был 'block', удаляем правило блокировки IP
                
                    $cmd = "sudo /usr/sbin/iptables -D FORWARD -s " . escapeshellarg($ip) . " -j DROP 2>/dev/null";
                    exec($cmd, $output, $return_var);
                    
                    if ($return_var !== 0) {
                        error_log("Ошибка при удалении блокировки IP $ip: " . implode(", ", $output));
                    } else {
                        error_log("Успешно удалена блокировка IP $ip");
                    }
         
                
                // Отправляем сообщение об успешной активации
                $telegram->sendMessage([
                    'chat_id' => $referrer_id,
                    'text' => "По вашей ссылке зарегистрировался новый пользователь. Подписка на 10 дней активирована." . ($tarif == 'block' ? " Ваш доступ восстановлен." : "")
                ]);
				}else{
					
					$dbh->query("UPDATE vpnusers SET day = day + 10 WHERE chat_id = :ref_id", [':ref_id' => $referrer_id]);
					$telegram->sendMessage([
						'chat_id' => $referrer_id,
						'text' => "🎉 Ура! По вашей ссылке зарегистрировался новый пользователь. Вам начислено +10 дней подписки."
					]);
					}
			}
			
			
			
		}

		
    } catch (PDOException $e) {
        // Обработка ошибки при вставке
        $telegram->sendMessage(['chat_id' => $chat_id, 'text' => "❌ Ошибка при регистрации: " . $e->getMessage()]);
    }
}

 

}



// Обработка текста
if ($text && $chat_id) {
    switch ($text) {
		
		case '/start':
			$reply = hex2bin('F09F94B9') . " Главное меню " . hex2bin('F09F94B9');
			$reply_markup = Keyboard::make()
				->setResizeKeyboard(true)
				->setOneTimeKeyboard(false)
				->row(['Инструкция подключения', 'Личный кабинет'])
				->row(['Получить ключ', 'Тарифы'])
				->row(['Партнерская программа']);

			$telegram->sendMessage([
				'chat_id' => $chat_id,
				'text' => $reply,
				'reply_markup' => $reply_markup
			]);
			break;
		
        case 'Инструкция подключения':
            $instructionText = "📌 Инструкция подключения к OpenVPN:\n\n"
                . "1. Скачайте и установите OpenVPN клиент.\n"
                . "2. Получите ключ через меню \"Получить ключ\".\n"
                . "3. Импортируйте ключ в клиент.\n"
                . "4. Подключитесь к VPN.\n"
                . "Подробнее по команде для  /android  /iphone   /windows";
				
            $telegram->sendMessage(['chat_id' => $chat_id, 'text' => $instructionText]);
            break;
			
		case '/iphone':
            $instructionText = "Инструкция для Iphone (ios):
			У нас нет своего приложения, мы используем OpenVPN. Такое решение гарантирует безопасность ваших данных.
			1) Установите приложение <a href='https://apps.apple.com/ru/app/openvpn-connect-openvpn-app/id590379981'>OpenVPN</a> из AppStore
			2) Нажмите кнопку меню - Получить ключ.";
				
            $telegram->sendMessage(['chat_id' => $chat_id, 'text' => $instructionText, 'parse_mode' => 'HTML']);
			// Подготавливаем массив для sendMediaGroup
			$media = [];
			$photoDir = '/var/www/html/vpnbot/iphone/';
			$photoBaseUrl = 'https://ваш_домен/vpnbot/iphone/'; // Замените на ваш домен
			
			for ($i = 1; $i <= 5; $i++) {
				$photoPath = $photoDir . "$i.jpg";
				$photoUrl = $photoBaseUrl . "$i.jpg";
				
				// Проверяем, существует ли файл
				if (file_exists($photoPath)) {
					$mediaItem = [
						'type' => 'photo',
						'media' => $photoUrl
					];
					// Добавляем подпись только к первому изображению
					if ($i === 1) {
						$mediaItem['caption'] = 'Откройте файл следуя инструкции на фото.';
					}
					$media[] = $mediaItem;
				} else {
					error_log("Файл $photoPath не найден");
				}
			}
			
			// Отправляем группу фотографий, если есть хотя бы одна
			if (!empty($media)) {
				try {
					$telegram->sendMediaGroup([
						'chat_id' => $chat_id,
						'media' => json_encode($media)
					]);
				} catch (Exception $e) {
					error_log("Ошибка при отправке sendMediaGroup для chat_id $chat_id: " . $e->getMessage());
					$telegram->sendMessage([
						'chat_id' => $chat_id,
						'text' => "❌ Произошла ошибка при отправке изображений. Обратитесь в поддержку."
					]);
				}
			} else {
				error_log("Не найдено ни одного изображения для команды /iphone");
				$telegram->sendMessage([
					'chat_id' => $chat_id,
					'text' => "❌ Изображения инструкции недоступны. Обратитесь в поддержку."
				]);
			}
			break;
		
		case '/windows':
			$instructionText = "Инструкция для Windows:\n"
							 . "У нас нет своего приложения, мы используем OpenVPN. Такое решение гарантирует безопасность ваших данных. Получите ключ через кнопку меню Получить ключ. Скачайте его на компьютер.";
			
			// Отправляем текстовую инструкцию
			$telegram->sendMessage([
				'chat_id' => $chat_id,
				'text' => $instructionText
			]);
			
			$photoDir = '/var/www/html/vpnbot/windows/';
			$photoBaseUrl = 'https://ваш_домен/vpnbot/windows/';
			
			$captions = [
				1 => '1) Скачайте и установите приложение <a href="https://openvpn.net/community/">OpenVPN GUI</a> если не открывается, используйте <a href="https://www.softportal.com/software-47725-openvpn.html">Ссылку 2:</a>/n Скачать клиент в боте по команде /clientwindows32 или /clientwindows64',
				2 => '2) После установки на рабочем столе появится иконка "OpenVPN GUI". Запустите его.',
				3 => '3) После запуска в трее (меню в нижнем правом углу экрана) появится запущенное приложение OpenVPN GUI.',
				4 => '4) Нажмите правой кнопкой мыши по значку приложения и выберите "Импорт" -> "Импортировать файл конфигурации". Выберите файл конфигурации, скачанный по кнопке Получить ключ.',
				5 => '5) Запустите VPN кнопкой "Подключиться".'
			];
			
			for ($i = 1; $i <= 5; $i++) {
				$photoPath = $photoDir . "$i.jpg";
				$photoUrl = $photoBaseUrl . "$i.jpg";
				
				if (file_exists($photoPath)) {
					try {
						
						$request_params = ['chat_id'   => $chat_id,
									'photo'      => new CURLFile(realpath($photoPath)),
									'caption' => $captions[$i],
									'parse_mode' => 'HTML',
									];
						sendTm($token, 'sendPhoto', $request_params);
						$success = true;
					} catch (Exception $e) {
						error_log("Ошибка при отправке фото $photoPath для chat_id $chat_id: " . $e->getMessage());
					}
				} else {
					error_log("Файл $photoPath не найден");
				}
			}
		
			break;
		
		case '/android':
            $instructionText = 'Инструкция для Android:
			У нас нет своего приложения, мы используем OpenVPN. Такое решение гарантирует безопасность ваших данных.
			1) Установите приложение <a href="https://play.google.com/store/apps/details?id=net.openvpn.openvpn">OpenVPN Connect</a> из Google Play.
			2) Нажмите кнопку меню - Получить ключ.
			3) Запустите установленное приложение и перейдтите на вкладку Upload file.
			4) Выберите файл, скачанный в пункте 2.
			Готово!';
				
            $telegram->sendMessage(['chat_id' => $chat_id, 'text' => $instructionText, 'parse_mode' => 'HTML']);
			
			$media = [];
			$photoDir = '/var/www/html/vpnbot/android/';
			$photoBaseUrl = 'https://ваш_домен/vpnbot/android/'; // Замените на ваш домен
			
			for ($i = 1; $i <= 9; $i++) {
				$photoPath = $photoDir . "$i.jpg";
				$photoUrl = $photoBaseUrl . "$i.jpg";
				
				// Проверяем, существует ли файл
				if (file_exists($photoPath)) {
					$mediaItem = [
						'type' => 'photo',
						'media' => $photoUrl
					];
					// Добавляем подпись только к первому изображению
					if ($i === 1) {
						$mediaItem['caption'] = 'Откройте файл следуя инструкции на фото.';
					}
					$media[] = $mediaItem;
				} else {
					error_log("Файл $photoPath не найден");
				}
			}
			
			// Отправляем группу фотографий, если есть хотя бы одна
			if (!empty($media)) {
				try {
					$telegram->sendMediaGroup([
						'chat_id' => $chat_id,
						'media' => json_encode($media)
					]);
				} catch (Exception $e) {
					error_log("Ошибка при отправке sendMediaGroup для chat_id $chat_id: " . $e->getMessage());
					$telegram->sendMessage([
						'chat_id' => $chat_id,
						'text' => "❌ Произошла ошибка при отправке изображений. Обратитесь в поддержку."
					]);
				}
			} else {
				error_log("Не найдено ни одного изображения для команды /android");
				$telegram->sendMessage([
					'chat_id' => $chat_id,
					'text' => "❌ Изображения инструкции недоступны. Обратитесь в поддержку."
				]);
			}
            break;

		case 'Личный кабинет':
			// Получаем данные из таблицы vpnusers по chat_id
			$userData = $dbh->query("SELECT key_name, day, tarif FROM vpnusers WHERE chat_id = $chat_id");
			
			if ($userData && count($userData) > 0 && $userData[0]['key_name']) {
				$ovpnName = $userData[0]['key_name'] ?? '—';
				$daysLeft = $userData[0]['day'] ?? 0;
				$tarif = $userData[0]['tarif'] ?? 'base';
				
				$textCabinet = "👤 Личный кабинет:\n"
							 . "🔑 Ключ: $ovpnName\n"
							 . "📆 Дней осталось: $daysLeft\n";
				
				// Получаем информацию о трафике
				$trafficInfo = getUserTrafficInfo($chat_id);
				if ($trafficInfo) {
					$textCabinet .= "\n📊 Статистика трафика:\n";
					
					if ($trafficInfo['is_active']) {
						$textCabinet .= "🟢 Текущая сессия:\n";
						$textCabinet .= "  📥 Загружено: " . formatBytes($trafficInfo['current_received']) . "\n";
						$textCabinet .= "  📤 Отправлено: " . formatBytes($trafficInfo['current_sent']) . "\n";
						$textCabinet .= "  📊 Всего за сессию: " . formatBytes($trafficInfo['current_total']) . "\n";
						$textCabinet .= "  🕐 Начало сессии: " . $trafficInfo['session_start'] . "\n";
					} else {
						$textCabinet .= "⚫ Сессия не активна\n";
					}
					
					$textCabinet .= "\n📈 Общая статистика:\n";
					$textCabinet .= "  📥 Всего загружено: " . formatBytes($trafficInfo['total_received']) . "\n";
					$textCabinet .= "  📤 Всего отправлено: " . formatBytes($trafficInfo['total_sent']) . "\n";
					$textCabinet .= "  📊 Общий трафик: " . formatBytes($trafficInfo['total_traffic']);
				}
				
				// Добавляем сообщение о блокировке, если tarif = 'block'
				if ($tarif === 'block') {
					$textCabinet .= "\n\n🚫 Доступ закрыт: Ваш тариф заблокирован. Пожалуйста, оплатите подписку для восстановления доступа.";
				}
			} else {
				$textCabinet = "👤 Личный кабинет:\nВы ещё не получили ключ!";
			}

			$telegram->sendMessage([
				'chat_id' => $chat_id,
				'text' => $textCabinet
			]);
			break;

		case '/clientwindows32':
			
			$filePath = "/var/www/html/vpnbot/windows/OpenVPN-2.6.14-I002-x86.msi";

			if (file_exists($filePath)) {
				$telegram->sendDocument([
					'chat_id' => $chat_id,
					'document' => InputFile::create($filePath, "OpenVPN-2.6.14-I002-x86.msi"),
					'caption' => "Client Windows32"
				]);
			} 
			break;
			
		case '/clientwindows64':
			
			$filePath = "/var/www/html/vpnbot/windows/OpenVPN-2.6.14-I002-amd64.msi";

			if (file_exists($filePath)) {
				$telegram->sendDocument([
					'chat_id' => $chat_id,
					'document' => InputFile::create($filePath, "OpenVPN-2.6.14-I002-amd64.msi"),
					'caption' => "Client Windows64"
				]);
			} 
			break;
		
        case 'Получить ключ':
			$Row = $dbh->query("SELECT ip FROM vpnusers WHERE chat_id = $chat_id");
			$filePath = "/home/www-data/VpnOpenBot_" . $chat_id . ".ovpn";
	
			// nano /etc/openvpn/easy-rsa/pki/index.txt
			// sudo -u www-data sudo /var/www/html/openvpn-install.sh
			// sudo tail -f /var/log/apache2/error.log


				
			if (file_exists($filePath)) {
				$telegram->sendDocument([
					'chat_id' => $chat_id,
					'document' => InputFile::create($filePath, "VpnOpenBot_" . $chat_id . ".ovpn"),
					'caption' => "🔑 Ваш VPN ключ"
				]);
			} else {
				
            $clientName = $chat_id;
			addOpenVpnClient($clientName);
			sleep(1);
			$ip = createClientConfig($clientName);


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
					
			$filePath = "/home/www-data/VpnOpenBot_" . $chat_id . ".ovpn";

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
            
			}
            break;

        case 'Тарифы':
			$tariffsText = "💼 Тарифы OpenVPN:\n\n"
				. "1) 30 дней — 150₽\n"
				. "2) 90 дней — 400₽\n"
				. "3) 180 дней — 700₽\n"
				. "4) 365 дней — 1000₽\n\n"
				. "Выберите нужный тариф для оплаты:";

			$keyboard = [
				[['text' => '30 дней – 150₽', 'callback_data' => 'buy_30']],
				[['text' => '90 дней – 400₽', 'callback_data' => 'buy_90']],
				[['text' => '180 дней – 700₽', 'callback_data' => 'buy_180']],
				[['text' => '365 дней – 1000₽', 'callback_data' => 'buy_365']],
			];

			$reply_markup = json_encode(['inline_keyboard' => $keyboard]);

			$telegram->sendMessage([
				'chat_id' => $chat_id,
				'text' => $tariffsText,
				'reply_markup' => $reply_markup
			]);
			break;

        case 'Партнерская программа':
            $partnerLink = "https://t.me/vpnopenbot?start=$chat_id";
            $partnerText = "🤝 Партнерская программа:\n\n"
                . "Приглашайте друзей по ссылке:\n$partnerLink\n"
                . "За каждого приглашенного вы получаете +10 дней к подписке.";
            $telegram->sendMessage(['chat_id' => $chat_id, 'text' => $partnerText]);
            break;

        default:
			if(!$referrer_id){
            $telegram->sendMessage([
                'chat_id' => $chat_id,
                'text' => "❓ Команда не распознана. Пожалуйста, используйте меню."
            ]);
			}
            break;
    }
}

// Обработка inline-кнопки (callback_query)
if ($callbackQuery) {
    $data = $callbackQuery['data'] ?? '';
    $chat_id = $callbackQuery['message']['chat']['id'];

    $telegram->answerCallbackQuery([
        'callback_query_id' => $callbackQuery['id'],
        'text' => 'Открываю форму оплаты...',
        'show_alert' => false
    ]);

    $tariff_map = [
        'buy_30' => ['label' => 'Подписка на 30 дней', 'amount' => 15000, 'days' => 30],
        'buy_90' => ['label' => 'Подписка на 90 дней', 'amount' => 40000, 'days' => 90],
        'buy_180' => ['label' => 'Подписка на 180 дней', 'amount' => 70000, 'days' => 180],
        'buy_365' => ['label' => 'Подписка на 365 дней', 'amount' => 100000, 'days' => 365],
    ];

    if (isset($tariff_map[$data])) {
        $tariff = $tariff_map[$data];
        $label = $tariff['label'];
        $amount = $tariff['amount'];
        $payload = $data;

        $telegram->sendInvoice([
            'chat_id' => $chat_id,
            'title' => $label,
            'description' => "Доступ к сервису на {$tariff['days']} дней",
            'payload' => $payload,
            'provider_token' => $provider_token,
            'start_parameter' => $data,
            'currency' => 'RUB',
            'prices' => [
                ['label' => $label, 'amount' => $amount]
            ],
            'need_email' => false,
            'provider_data' => json_encode([
                "receipt" => [
                    "customer" => ["email" => "tarkalmob@yandex.ru"],
                    "items" => [[
                        "description" => $label,
                        "quantity" => "1.00",
                        "amount" => [
                            "value" => number_format($amount / 100, 2, '.', ''),
                            "currency" => "RUB"
                        ],
                        "vat_code" => 1,
                        "payment_mode" => "full_prepayment",
                        "payment_subject" => "service"
                    ]]
                ]
            ])
        ]);
    }
}


// Обработка pre_checkout_query (Telegram ждёт этот ответ!)
if (isset($update['pre_checkout_query'])) {
    $telegram->answerPreCheckoutQuery([
        'pre_checkout_query_id' => $update['pre_checkout_query']['id'],
        'ok' => true
    ]);
}

// Подтверждение успешной оплаты
if (isset($update['message']['successful_payment'])) {
    $chat_id = $update['message']['chat']['id'];
    $payload = $update['message']['successful_payment']['invoice_payload'];

    $day_map = [
        'buy_30' => 30,
        'buy_90' => 90,
        'buy_180' => 180,
        'buy_365' => 365,
    ];

    if (isset($day_map[$payload])) {
        $days = $day_map[$payload];
        
        try {
            // Проверяем текущий тариф и IP пользователя
            $result = $dbh->query("SELECT ip, tarif FROM vpnusers WHERE chat_id = $chat_id");
            $user = $result[0];
            
            if ($user) {
                $ip = $user['ip'];
                $tarif = $user['tarif'];
                
                // Обновляем подписку: добавляем дни, обновляем session_start и устанавливаем tarif = 'base'
                $dbh->query("
                    UPDATE vpnusers 
                    SET 
                        day = day + $days,
                        session_start = IF(session_start > NOW(), session_start, NOW()),
                        tarif = 'base'
                    WHERE chat_id = $chat_id
                ");
                
                // Если тариф был 'block', удаляем правило блокировки IP
                if ($tarif == 'block' && !empty($ip)) {
                    $cmd = "sudo /usr/sbin/iptables -D FORWARD -s " . escapeshellarg($ip) . " -j DROP 2>/dev/null";
                    exec($cmd, $output, $return_var);
                    
                    if ($return_var !== 0) {
                        error_log("Ошибка при удалении блокировки IP $ip: " . implode(", ", $output));
                    } else {
                        error_log("Успешно удалена блокировка IP $ip");
                    }
                }
                
                // Отправляем сообщение об успешной активации
                $telegram->sendMessage([
                    'chat_id' => $chat_id,
                    'text' => "✅ Спасибо! Подписка на $days дней активирована." . ($tarif == 'block' ? " Ваш доступ восстановлен." : "")
                ]);
            } else {
                error_log("Пользователь с chat_id $chat_id не найден");
                $telegram->sendMessage([
                    'chat_id' => $chat_id,
                    'text' => "❌ Ошибка: Пользователь не найден. Обратитесь в поддержку."
                ]);
            }
        } catch (Exception $e) {
            error_log("Ошибка при обработке оплаты для chat_id $chat_id: " . $e->getMessage());
            $telegram->sendMessage([
                'chat_id' => $chat_id,
                'text' => "❌ Произошла ошибка при активации подписки. Обратитесь в поддержку."
            ]);
        }
    }
}

/* if (isset($update['message']['successful_payment'])) {
    $chat_id = $update['message']['chat']['id'];
    $payload = $update['message']['successful_payment']['invoice_payload'];

    $day_map = [
        'buy_30' => 30,
        'buy_90' => 90,
        'buy_180' => 180,
        'buy_365' => 365,
    ];

    if (isset($day_map[$payload])) {
        $days = $day_map[$payload];
        $dbh->query("
			UPDATE vpnusers 
			SET 
				day = day + $days,
				session_start = IF(session_start > NOW(), session_start, NOW())
			WHERE chat_id = $chat_id
		");

        $telegram->sendMessage([
            'chat_id' => $chat_id,
            'text' => "✅ Спасибо! Подписка на $days дней активирована."
        ]);
    }
} */

