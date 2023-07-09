<?php

require_once './vendor/autoload.php';

use Telegram\Bot\Api;

$config = parse_ini_file('config.ini', true);

// Для доступа к настройкам Telegram:
$telegramApiKey = $config['Telegram']['api_key'];
$telegramChatId = $config['Telegram']['chat_id'];

// Для доступа к настройкам базы данных:
$dbServername = $config['Database']['servername'];
$dbUsername = $config['Database']['username'];
$dbPassword = $config['Database']['password'];
$dbName = $config['Database']['dbname'];

$conn = new mysqli($dbServername, $dbUsername, $dbPassword, $dbName);

// Проверка соединения с базой данных
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Получение одной записи из таблицы fromvk, где IsRepostToTelegram=0
$sql = "SELECT * FROM fromvk WHERE IsRepostToTelegram=0 LIMIT 1";
$result = $conn->query($sql);

// Инициализация Telegram API
$telegram = new Api($telegramApiKey);

if ($result->num_rows > 0) {
    // Получение данных из базы данных
    $row = $result->fetch_assoc();
    $id = $row['id'];
    $photo = $row['photo'];
    $text = $row['text'];
    $signer_id = $row['signer_id'];

    // Если signer_id не пустой, добавляем его в текст сообщения
    if (!empty($signer_id)) {
        $text .= PHP_EOL . "автор: " . $signer_id;
    }

    // Разделение строки с фотографиями на отдельные url
    $photo_urls = explode(" ", $photo);

    // Формирование массива с объектами фотографий
    $media = array();
    foreach ($photo_urls as $key => $photo_url) {
        // Проверяем, что ссылка на медиафайл не пуста
        if (!empty($photo_url)) {
            $media[] = [
                'type' => 'photo',
                'media' => $photo_url,
                'caption' => ($key == 0) ? $text : "" // caption только у первой фотографии
            ];
        }
    }

    // Отправка сообщения с медиа-группой, если есть фотографии
    if (!empty($media)) {
        try {
            $telegram->sendMediaGroup([
                'chat_id' => $telegramChatId,
                'media' => json_encode($media),
            ]);

            // Обновление записи в базе данных
            $sql = "UPDATE fromvk SET IsRepostToTelegram=1 WHERE id=$id";
            $conn->query($sql);
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }
    } else {
        // Отправка сообщения без медиа-группы, если нет фотографий
        try {
            $telegram->sendMessage([
                'chat_id' => $telegramChatId,
                'text' => $text,
            ]);

            // Обновление записи в базе данных
            $sql = "UPDATE fromvk SET IsRepostToTelegram=1 WHERE id=$id";
            $conn->query($sql);
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }
    }
}
// Закрытие соединения с базой данных
$conn->close();

// Логирум запуск по крону
printf("Date: %s | Rows: %s\n", (new \DateTime('now'))->format('Y-m-d H:i:s'), $result->num_rows);
