<?php

require_once 'vendor/autoload.php'; // Подключение библиотеки VK API PHP
use VK\Client\VKApiClient;
use VK\OAuth\VKOAuth;
$config = parse_ini_file('config.ini', true);

// Параметры подключения к базе данных
$dbHost = $config['Database']['servername'];
$dbUser = $config['Database']['username'];
$dbPassword = $config['Database']['password'];
$dbName = $config['Database']['dbname'];

// Параметры подключения к API ВКонтакте
$groupId = $config['VK']['group_id'];
$accessToken = $config['VK']['assectokentoposttovk'];

// Подключение к базе данных
$conn = new mysqli($dbHost, $dbUser, $dbPassword, $dbName);

// Проверка подключения
if ($conn->connect_error) {
    die("Ошибка подключения к базе данных: " . $conn->connect_error);
}

// Выборка данных из базы данных
$sql = "SELECT * FROM toVk WHERE isPostToVk = 0 LIMIT 1"; // Ограничиваем выборку одной записью
$result = $conn->query($sql);

// Инициализация объекта VK API
$vk = new VKApiClient();

// Обработка результатов выборки и отправка в группу ВКонтакте
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
// Формирование текста сообщения
$postTitle = $conn->real_escape_string($row['post_title']);
$postContent = $conn->real_escape_string($row['post_content']);
$listing_url = urldecode($row['listing_url']); // Декодируем URL

// Вставляем ссылку в текст сообщения
        $message = "Источник: {$listing_url}\n{$postTitle}\n{$postContent}\n{$row['phone_number']}\n";


        // Замена \r\n на \n
        $message = str_replace("\\r\\n", "\n", $message);

        // Получение массива URL изображений
        $imageUrls = explode(',', $row['image_urls']);

        // Загрузка изображений в VK
        $attachments = [];

        foreach ($imageUrls as $imageUrl) {
            // Заменяем символы в строке URL
            $imageUrl = str_replace(['\\', '"'], '', $imageUrl);

            // Проверка наличия значения в $imageUrl
            if (!empty($imageUrl)) {
                try {
                    $uploadUrl = $vk->photos()->getWallUploadServer($accessToken, [
                        'group_id' => $groupId,
                    ]);

                    // Используем Guzzle для выполнения запроса с более гибким управлением
                    $httpClient = new \GuzzleHttp\Client();

                    echo "Запрос изображения по URL: $imageUrl\n";

                    $imageContent = $httpClient->request('GET', $imageUrl)->getBody()->getContents();

                    $uploadResult = $httpClient->request('POST', $uploadUrl['upload_url'], [
                        'multipart' => [
                            [
                                'name' => 'file1', // Проверьте документацию VK API на корректное имя поля
                                'contents' => $imageContent,
                                'filename' => 'photo.jpg', // Подставьте нужное расширение файла
                            ],
                        ],
                    ])->getBody()->getContents();

                    $uploadResult = json_decode($uploadResult, true);

                    $photo = $vk->photos()->saveWallPhoto($accessToken, [
                        'group_id' => $groupId,
                        'server' => $uploadResult['server'],
                        'photo' => $uploadResult['photo'],
                        'hash' => $uploadResult['hash'],
                    ]);

                    $attachments[] = "photo{$photo[0]['owner_id']}_{$photo[0]['id']}";
                } catch (\Exception $e) {
                    echo "Ошибка при загрузке изображения: " . $e->getMessage() . "\n";
                }
            } else {
                echo "Пустое значение в переменной \$imageUrl\n";
            }
        }

        // Отправка данных в группу ВКонтакте
        $postParams = [
            'owner_id' => "-$groupId",
            'from_group' => 1,
            'message' => $message,
            'attachments' => implode(',', $attachments),
            'access_token' => $accessToken,
            'v' => "5.131",
        ];

        $requestUrl = "https://api.vk.com/method/wall.post?" . http_build_query($postParams);

        try {
            // Увеличиваем таймаут для curl
            ini_set('default_socket_timeout', 60);

            // Используем Guzzle для выполнения запроса с более гибким управлением
            $httpClient = new \GuzzleHttp\Client();
            $response = $httpClient->request('GET', $requestUrl)->getBody()->getContents();

            // Обработка ответа (вывод или обработка ошибок)
            $responseData = json_decode($response, true);

            if (isset($responseData['response']['post_id'])) {
                // Обновление isPostToVk на 1 в базе данных
                $updateSql = "UPDATE toVk SET isPostToVk = 1 WHERE id = {$row['id']}";
                $updateResult = $conn->query($updateSql);

                if ($updateResult === false) {
                    echo "Ошибка при обновлении статуса isPostToVk: " . $conn->error;
                }
            } else {
                // Обработка ошибки
                echo "Ошибка при публикации в VK: " . json_encode($responseData);
            }
        } catch (\Exception $e) {
            echo "Ошибка при выполнении HTTP-запроса: " . $e->getMessage();
        }
    }
} else {
    echo "Нет данных для отправки.";
}

// Закрытие соединения с базой данных
$conn->close();
?>
