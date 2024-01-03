<?php
$config = parse_ini_file('config.ini', true);

// Для доступа к настройкам базы данных:
$dbServername = $config['Database']['servername'];
$dbUsername = $config['Database']['username'];
$dbPassword = $config['Database']['password'];
$dbName = $config['Database']['dbname'];

// подключение к БД
function connection()
{
    global $dbServername, $dbUsername, $dbPassword, $dbName;

    try {
        // Подключение к базе данных с использованием PDO
        $pdo = new PDO("mysql:host=$dbServername;dbname=$dbName", $dbUsername, $dbPassword);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        // В случае ошибки базы данных выводим сообщение об ошибке
        echo 'Не удалось подключиться к БД: ' . $e->getMessage();
        exit();
    }
}

// Получение данных из POST-запроса
$data = json_decode(file_get_contents('php://input'), true);

// Проверка наличия ключа 'key' в JSON-данных и сравнение его значения
if (!isset($data['key']) || $data['key'] !== "F67ftfuaw37FUf6d74gfwFF") {
    echo "Ошибка: Значение ключа 'key' недопустимо или отсутствует в JSON-данных.";
    exit; // Прекращаем выполнение скрипта
}

$post_id = $data['id'];
$post_title = $data['title'];
$post_content = $data['text'];
$phone_number = $data['phone'];
$image_urls = $data['images'];
$listing_url = $data['listing_url']; // Получаем ссылку на объявление

try {
    // Подключение к базе данных
    $pdo = connection();

    // Подготовка SQL-запроса для вставки данных в таблицу toVk
    $stmt = $pdo->prepare("INSERT INTO toVk (id, post_title, post_content, phone_number, image_urls, listing_url, isPostToVk) VALUES (?, ?, ?, ?, ?, ?, 0)");

    // Выполнение SQL-запроса
    $stmt->execute([$post_id, $post_title, $post_content, $phone_number, json_encode($image_urls), $listing_url]);

    // Ответ на запрос
    http_response_code(200);
    echo "Данные успешно сохранены в базе данных.";
} catch (PDOException $e) {
    // В случае ошибки базы данных выводим сообщение об ошибке
    http_response_code(500);
    echo "Ошибка базы данных: " . $e->getMessage();
}
?>
