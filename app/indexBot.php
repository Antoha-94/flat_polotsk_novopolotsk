<?php

$config = parse_ini_file('config.ini', true);

$confirmation_token = $config['VK']['confirmation_token'];
$token = $config['VK']['token'];
$post_type = $config['VK']['post_type'];

// Для доступа к настройкам базы данных:
$dbServername = $config['Database']['servername'];
$dbUsername = $config['Database']['username'];
$dbPassword = $config['Database']['password'];
$dbName = $config['Database']['dbname'];

// подключение к БД
function connection()
{
    global $dbServername, $dbUsername, $dbPassword, $dbName;
    $DB = mysqli_connect($dbServername, $dbUsername, $dbPassword, $dbName);
    if (!$DB) {
        echo 'не удалось подключиться к БД <br>';
        echo mysqli_connect_error();
    }
    return $DB;
}

// проверка в БД на уникальность по id Записи
function CheckIdWallinDB($connection, $data)
{
    $id = $data->object->id;
    $checkIdQuery = "SELECT id FROM fromvk WHERE id=$id";
    $checkIdResult = mysqli_query($connection, $checkIdQuery);
    if ($checkIdResult->num_rows === 1) {
        echo "ok"; //когда присылают id, который уже есть в бд, чтобы vk больше не долбил ретрай
    } else {
        echo AddWallPostinDB($connection, $data);
    }
}

// если id Записи уникальна, то добавляем ее в БД
function AddWallPostinDB($connection, $data)
{
    $id = $data->object->id ?? null;
    $text = $data->object->text ?? null;
    $signer_id = $data->object->signer_id ?? null;
    if ($signer_id !== null) {
        $signer_id = "https://vk.com/id{$signer_id}";
    }

    $addNewWallpostQuery = "INSERT INTO fromvk (id, text, signer_id) VALUES ('$id', '$text', '$signer_id')";
    if (mysqli_query($connection, $addNewWallpostQuery)) {
        echo "ok"; //запись добавлена в бд
    } else {
        echo "ошибка добавления записи";
    }
}

// выбираем URL фото, пришедших в Записи
function GetImageUrlFromPost($data): array
{
    $photoArray = [];
    foreach ($data as $key => $datum) {
        if (!empty($datum->attachments)) {
            foreach ($datum->attachments as $attachment) {
                if ($attachment->type === 'photo') {
                    $sizeArray = $attachment->photo->sizes;
                    $maxWidth = 0;
                    $maxSizeUrl = '';
                    foreach ($sizeArray as $size) {
                        if ($size->width > $maxWidth) {
                            $maxWidth = $size->width;
                            $maxSizeUrl = $size->url;
                        }
                    }
                    if (!in_array($maxSizeUrl, $photoArray)) {
                        $photoArray[] = $maxSizeUrl;
                    }
                }
            }
        }
    }
    return $photoArray;
}


// Записываем фото из Записи в БД
function AddWallPhotoDB($connection, $parsedArray, $postIdFromVk)
{
    if (!is_array($parsedArray)) {
        $parsedArray = explode(',', $parsedArray);
    }
    $addNewWallPhotoQuery = "UPDATE fromvk SET photo='" . implode(',', $parsedArray) . "' WHERE id=$postIdFromVk";
    if (mysqli_query($connection, $addNewWallPhotoQuery)) {
        echo ""; //Фото добавлены в БД
    } else {
        echo "Ошибка добавления фото";
    }
    mysqli_close($connection);
}

if (!isset($_REQUEST)) {
    return;
}

// декодируем пришедший json
$data = json_decode(file_get_contents('php://input'));

// проверка токена группы вк, если не совпадает "умираем"
if ($token != $data->secret) {
    die();
}

// Если в типе confirmation, отправляем confirmation_token для подтверждения сервера
switch ($data->type) {
    case 'confirmation':
        echo $confirmation_token;
        die();
}

// проверка на тип записи, чтобы не было предложенных, только опубликованные.
if ($post_type != $data->object->post_type) {
    echo "ok";
    die();
}

// Если тип записи wall_post_new - записываем его в БД
switch ($data->type) {
    case 'wall_post_new':
        echo CheckIdWallinDB(connection(), $data);
        break;
}

// для записи в БД фото по id Записи, выводим переменную $PostIdFromVk
$PostIdFromVk = $data->object->id;
// Преобразовываем array с фото в строку, для записи в БД.
$getData = GetImageUrlFromPost($data);
$ParsedArray = implode(" ", $getData);
// Конектимся к БД
$connection = connection();
// Вызываем функцию записи фото в БД
AddWallPhotoDB($connection, $ParsedArray, $PostIdFromVk);

?>
