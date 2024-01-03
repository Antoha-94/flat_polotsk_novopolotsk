<?php

require "vendor/autoload.php";
use Abraham\TwitterOAuth\TwitterOAuth;

// Подключение к БД
$config = parse_ini_file('config.ini', true);

$dbServername = $config['Database']['servername'];
$dbUsername = $config['Database']['username'];
$dbPassword = $config['Database']['password'];
$dbName = $config['Database']['dbname'];

// Подключение к БД
$conn = new mysqli($dbServername, $dbUsername, $dbPassword, $dbName);

// Проверка соединения
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Выборка данных из БД
$sql = "SELECT * FROM fromvk WHERE IsRepostToTwitter=0 LIMIT 1";
$result = $conn->query($sql);

if ($result !== false && $result->num_rows > 0) {
    // Получение первой строки результата запроса
    $row = $result->fetch_assoc();

    // Значения из БД
    $photo = $row['photo'];
    $text = $row['text'];
    $signer_id = $row['signer_id'];

// Формирование текста сообщения
    $message_text = "";
    if (!empty($signer_id)) {
        $message_text = "Автор: " . $signer_id . "\n";
    }
    $message_text .= $text;

// Создание картинки с $message_text
    $im = imagecreatetruecolor(950, 600); // Увеличьте ширину до 950
    $white = imagecolorallocate($im, 255, 255, 255);
    $black = imagecolorallocate($im, 0, 0, 0);
    imagefilledrectangle($im, 0, 0, 949, 599, $white); // Увеличьте высоту до 599


    $font = '/var/www/html/front/OpenSans-Italic-VariableFont_wdth,wght.ttf';

// Разбиваем текст на строки с переносом каждые 100 символов или раньше
    $message_text_wrapped = wordwrap($message_text, 100, "\n", true);

// Разбиваем строки на массив
    $lines = explode("\n", $message_text_wrapped);

// Устанавливаем начальные координаты для текста
    $x = 10;
    $y = 30;

// Выводим текст на изображение
    foreach ($lines as $line) {
        imagettftext($im, 20, 0, $x, $y, $black, $font, $line);
        $y += 25; // Увеличиваем y-координату для следующей строки
    }

    imagepng($im, '/var/www/html/temp/message.png');
    imagedestroy($im);

    $uploadedImages = []; // Массив для хранения загруженных картинок

    if (!empty($photo)) {
        $photoUrls = explode(' ', $photo);

        // Загрузка только первых 3 фотографий
        for ($i = 0; $i < min(3, count($photoUrls)); $i++) {
            $url = $photoUrls[$i];

            if (!empty($url)) {
                $pathInfo = pathinfo($url);
                $filename = $pathInfo['filename'] . '.jpg';
                $path = '/var/www/html/temp/' . $filename;

                $imageData = file_get_contents($url);
                if ($imageData !== false) {
                    file_put_contents($path, $imageData);
                    echo "Фотография успешно загружена: " . $filename . "<br>";
                    $uploadedImages[] = $path;
                } else {
                    echo "Не удалось загрузить фотографию: " . $filename . "<br>";
                }
            } else {
                echo "URL-адрес фотографии отсутствует.";
            }
        }
    }
} else {
    echo "Нет данных для обработки из БД.";
}
//загрузка картинки с $message_text
$path = '/var/www/html/temp/message.png';
$imageData = file_get_contents($path);
if ($imageData !== false) {
    file_put_contents($path, $imageData);
    echo "Фотография успешно загружена: " . $filename . "<br>";
    $uploadedImages[] = $path;
} else {
    echo "Не удалось загрузить фотографию: " . $filename . "<br>";
}



// Проверка наличия загруженных изображений
if (!empty($uploadedImages)) {
    // Переменные для подключения к Twitter
    $consumer_key = $config['Twitter']['consumer_key'];
    $consumer_secret = $config['Twitter']['consumer_secret'];
    $access_token = $config['Twitter']['access_token'];
    $access_token_secret = $config['Twitter']['access_token_secret'];

    // Подключение к Twitter
    $connection = new TwitterOAuth($consumer_key, $consumer_secret, $access_token, $access_token_secret);
    $connection->setApiVersion(1.1);

// Загрузка фотографий в Twitter
    $mediaIds = [];
    foreach ($uploadedImages as $image) {
        $media = $connection->upload('media/upload', ['media' => $image]);

        if (isset($media->media_id_string)) {
            array_unshift($mediaIds, $media->media_id_string); // Добавляем media_id в начало массива
        } else {
            echo "Ошибка при загрузке медиафайла: " . print_r($media, true);
        }
    }

    // Ограничение текста до 280 символов
    $message_text = mb_substr($message_text, 0, 280);

// Отправка сообщения в Twitter
    $connection->setApiVersion(2);
    $parameters = [
        'text' => $message_text,
        'media' => ['media_ids' => $mediaIds]
    ];
    $result = $connection->post('tweets', $parameters, true);

    if ($connection->getLastHttpCode() != 200) {
        echo 'Твит не опубликован. Ошибка: ' . print_r($result, true);
    } else {
        echo 'Твит успешно опубликован!';


    }

// Удаление загруженных изображений
    foreach ($uploadedImages as $image) {
        if (file_exists($image)) {
            unlink($image);
            echo "Файл успешно удален: " . $image . "<br>";
        } else {
            echo "Файл не найден: " . $image . "<br>";
        }
    }


}

// Обновление записи в БД после успешной отправки в Twitter
$updateSql = "UPDATE fromvk SET IsRepostToTwitter=1 WHERE id=" . $row['id'];
$updateResult = $conn->query($updateSql);

if ($updateResult === false) {
    echo "Ошибка при обновлении записи в БД: " . $conn->error;
} else {
    echo "Запись в БД успешно обновлена.";
}
// Закрытие соединения с БД
$conn->close();
?>

