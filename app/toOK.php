<?php

$config = parse_ini_file('config.ini', true);

// Для доступа к настройкам Одноклассники:
$okAccessToken = $config['OK']['access_token'];
$okPrivateKey = $config['OK']['private_key'];
$okPublicKey = $config['OK']['public_key'];
$okSessionKey = $config['OK']['session_key'];
$okGroupId = $config['OK']['group_id'];

// Для доступа к настройкам базы данных:
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
$sql = "SELECT * FROM fromvk WHERE IsRepostToOK=0 LIMIT 1";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    // Получение первой строки результата запроса
    $row = $result->fetch_assoc();

    // Значения из БД
    $photo = $row['photo'];
    $text = $row['text'];
    $signer_id = $row['signer_id'];

    // Формирование текста сообщения
    $message_text = $text . "\nАвтор: " . $signer_id;

    $uploadedImages = []; // Массив для хранения загруженных картинок

    if (!empty($photo)) {
        $photoUrls = explode(' ', $photo);

        foreach ($photoUrls as $url) {
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

    // Проверка наличия текста сообщения или загруженных фотографий
    if (!empty($message_text) || !empty($uploadedImages)) {
        // Вызов функции ok_wall_post для отправки сообщия
        ok_wall_post($okAccessToken, $message_text, $uploadedImages, $okPublicKey, $okPrivateKey, $okSessionKey, $okGroupId);

        // Обновление значения IsRepostToOK в БД
        $updateSql = "UPDATE fromvk SET IsRepostToOK = 1 WHERE id = " . $row['id'];
        $conn->query($updateSql);

        // Удаление загруженных фотографий
        foreach ($uploadedImages as $image) {
            unlink($image);
        }
    } else {
        echo "Отсутствуют текст сообщения и изображения для загрузки.";
    }
}

$conn->close();


function ok_wall_post($ok_access_token, $message_text, $uploadedImages, $ok_public_key, $ok_private_key, $ok_session_key, $group_id_param)
{
    function getUrl($url, $type = "GET", $params = array(), $image = false, $decode = true)
    {

        if ($ch = curl_init()) {
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HEADER, false);

            if ($type == "POST") {
                curl_setopt($ch, CURLOPT_POST, true);

                // Картинка
                if ($image) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
                }
                // Обычный запрос
                elseif ($decode) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, urldecode(http_build_query($params)));
                }
                // Текст
                else {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
                }
            }

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'PHP Bot');
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

            $data = curl_exec($ch);

            curl_close($ch);

            // Еще разок, если API завис
            if (isset($data['error_code']) && $data['error_code'] == 5000) {
                $data = getUrl($url, $type, $params);
            }
            return $data;
        } else {
            return "{}";
        }
    }

    $arrPhotos = []; // Используем пустой массив для складывания массивов с парами Array('id' => $photo_token)
    $album_id = "970018043831";
    $group_id_photo = $group_id_param; // Используем переданный параметр $group_id_param

    foreach ($uploadedImages as $image) {
        $params = [
            'application_key' => $ok_public_key,
            'session_secret_key' => $ok_session_key,
            'access_token' => $ok_access_token,
            'method' => 'photosV2.getUploadUrl',
            'gid' => $group_id_photo, // Используем $group_id_photo вместо жестко закодированного значения
            'format' => 'json'
        ];

        ksort($params);
        $paramsS = '';
        foreach ($params as $k => $v) {
            $paramsS .= $k . "=" . $v;
        }

        $sigg = md5($paramsS . $ok_private_key);

        $params['sig'] = strtolower($sigg);

        $photo_get_id = json_decode(getUrl("https://api.ok.ru/fb.do", "POST", $params), true);
        $photo_send_id = $photo_get_id['photo_ids'][0];
        $upload_url = $photo_get_id['upload_url'];
        $paramsP = array('pic1' => curl_file_create($image));
        $photo_send = json_decode(getUrl($upload_url, "POST", $paramsP, true), true);
        $photo_token = $photo_send['photos'][$photo_send_id]['token'];

        $arrPhotos[] = ['id' => $photo_token];
    }

    $media = [
        'media' => [
            ['type' => 'text', 'text' => $message_text],
            ['type' => 'photo', 'list' => $arrPhotos],
        ],
    ];

    $paramsArray = [
        'application_key' => $ok_public_key,
        'access_token' => $ok_access_token,
        'type' => 'GROUP_THEME',
        'gid' => $group_id_photo,
        'attachment' => json_encode($media),
        'format' => 'json',
        'method' => 'mediatopic.post',
    ];

    ksort($paramsArray);
    $paramsStr = '';
    foreach ($paramsArray as $k => $v) {
        $paramsStr .= $k . "=" . $v;
    }
    $sig = md5($paramsStr . $ok_private_key);

    $paramsArray['sig'] = strtolower($sig);

    $status = json_decode(getUrl("https://api.ok.ru/fb.do", "POST", $paramsArray, false, false), true);
    echo '<pre>' . json_encode($status, JSON_PRETTY_PRINT) . '</pre>';

}
