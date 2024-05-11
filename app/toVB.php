<?php

require_once './vendor/autoload.php';

$config = parse_ini_file('config.ini', true);

// Настройки Viber
$authToken = $config['Viber']['api_key'];
$userID = $config['Viber']['user_id'];

// Настройки базы данных
$dbServername = $config['Database']['servername'];
$dbUsername = $config['Database']['username'];
$dbPassword = $config['Database']['password'];
$dbName = $config['Database']['dbname'];

// Подключение к базе данных
$conn = new mysqli($dbServername, $dbUsername, $dbPassword, $dbName);

// Проверка соединения
if ($conn->connect_error) {
    die("Ошибка подключения: " . $conn->connect_error);
}

// Запрос для получения первого непересланного сообщения
$sql = "SELECT * FROM fromvk WHERE IsRepostToViber=0 LIMIT 1";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    // Получаем данные сообщения
    $row = $result->fetch_assoc();

    // Информация о сообщении
    $messageText = $row["text"];
    $messageID = $row["id"];
    $signerID = $row["signer_id"];
    $photoURLs = explode(' ', $row["photo"]);

    // Добавляем имя автора и ссылку VK, если есть имя автора
    if (!empty($signerID)) {
        $messageText = "Ссылка на пост VK: https://vk.com/flat_polotsk_novopolotsk?w=wall-71599563_" . $messageID . PHP_EOL . "Автор: " . $signerID . PHP_EOL . $messageText;
    }

    // URL для отправки сообщения
    $url = 'https://chatapi.viber.com/pa/post';

    // Заголовки запроса
    $headers = [
        'Content-Type: application/json',
        'X-Viber-Auth-Token: ' . $authToken,
    ];

    // Формируем сообщение
    $messageData = [
        'from' => $userID,
    ];

    // Добавляем текст и/или изображения к сообщению
    if (!empty($photoURLs[0])) {
        // Если есть фото, отправляем первое фото и текст
        $messageData['type'] = 'picture';
        $messageData['text'] = mb_substr($messageText, 0, 768); // Ограничение на 768 символов
        $messageData['media'] = $photoURLs[0];
    } else {
        // Если нет фото, отправляем только текст
        $messageData['type'] = 'text';
        $messageData['text'] = mb_substr($messageText, 0, 7000); // Ограничение на 700 символов
    }

    // Отправка запроса
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($messageData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    // Обновляем флаг пересылки сообщения
    if ($response) {
        $updateSql = "UPDATE fromvk SET IsRepostToViber=1 WHERE id=$messageID";
        $updateResult = $conn->query($updateSql);
        if (!$updateResult) {
            echo "Ошибка обновления флага пересылки: " . $conn->error;
        }
    } else {
        echo "Ошибка отправки сообщения в Viber: " . curl_error($ch);
    }
} else {
    echo "Нет непересланных сообщений в базе данных.";
}

$conn->close();

?>
