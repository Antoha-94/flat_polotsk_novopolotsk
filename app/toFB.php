<?php

require_once './vendor/autoload.php';
use Facebook\Facebook;
use Facebook\Exceptions\FacebookResponseException;
use Facebook\Exceptions\FacebookSDKException;

$config = parse_ini_file('config.ini', true);

// Для доступа к настройкам Facebook:
$facebookAppId = $config['Facebook']['app_id'];
$facebookAppSecret = $config['Facebook']['app_secret'];
$facebookPageId = $config['Facebook']['page_id'];
$facebookUserAccessToken = $config['Facebook']['user_access_token'];

// Для доступа к настройкам базы данных:
$dbServername = $config['Database']['servername'];
$dbUsername = $config['Database']['username'];
$dbPassword = $config['Database']['password'];
$dbName = $config['Database']['dbname'];

// Подключение к базе данных
$conn = new mysqli($dbServername, $dbUsername, $dbPassword, $dbName);

// Проверка соединения с базой данных
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Получение одной записи из таблицы fromvk, где IsRepostToFacebook=0
$sql = "SELECT * FROM fromvk WHERE IsRepostToFacebook=0 LIMIT 1";
$result = $conn->query($sql);

if ($result !== false && $result->num_rows > 0) {
    // Инициализация Facebook SDK
    $fb = new Facebook([
        'app_id' => $facebookAppId,
        'app_secret' => $facebookAppSecret,
        'default_graph_version' => 'v16.0',
    ]);

    $row = $result->fetch_assoc();

    $id = $row['id'];
    $photo = $row['photo'];
    $text = $row['text'];
    $signer_id = $row['signer_id'];

    // Если signer_id не пустой, добавляем его в текст сообщения
    if (!empty($signer_id)) {
        $text .= "\nавтор: " . $signer_id;
    }

    // Создание массива параметров для публикации на странице Facebook
    $post_data = [
        'message' => $text,
    ];

    // Проверка на наличие фото
    if (!empty($photo)) {
        // Разделение строки с фотографиями на отдельные URL
        $photo_urls = explode(' ', $photo);

        $attached_media = [];
        foreach ($photo_urls as $photo_url) {
            // Загрузка фотографии на Facebook
            $photo_params = [
                'source' => $fb->fileToUpload($photo_url),
                'published' => false,
            ];
            $response = $fb->post('/' . $facebookPageId . '/photos', $photo_params, $facebookUserAccessToken);
            $graphNode = $response->getGraphNode();
            $media_id = $graphNode['id'];

            $attached_media[] = [
                'media_fbid' => $media_id,
            ];
        }

        $post_data['attached_media'] = $attached_media;
    }

    // Проверка на наличие фото и текста перед публикацией
    if (!empty($attached_media) || !empty($text)) {
        // Публикация на странице Facebook
        try {
            $response = $fb->post('/' . $facebookPageId . '/feed', $post_data, $facebookUserAccessToken);
            $graphNode = $response->getGraphNode();

            // Если сообщение успешно опубликовано на Facebook, обновляем флаг IsRepostToFacebook в базе данных
            if ($graphNode && $graphNode['id']) {
                $sql_update = "UPDATE fromvk SET IsRepostToFacebook=1 WHERE id=$id";
                $conn->query($sql_update);
            }
        } catch (FacebookResponseException $e) {
            echo 'Graph returned an error: ' . $e->getMessage();
            echo 'Graph error code: ' . $e->getCode();
        } catch (FacebookSDKException $e) {
            echo 'Facebook SDK Error: ' . $e->getMessage();
        }
    } else {
        echo 'No photo or text found.';
    }
}

$conn->close();
?>
