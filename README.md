Описание:
При публикации нового поста в группе vkontakte, на сервер отправляется Callback API и пост записывается в БД.
Затем по расписанию crone - запись из БД публикуется в telegram канале, странице facebook, группе odnoklassniki.

Установка:
1.Git clone git@github.com:Antoha-94/flat_polotsk_novopolotsk

2.Делаем дубликат файла .env.example из корня. Переименовываем в .env и редактируем под себя.

3.Развертываем энваромент, перед этим редактируем файлы конфига nginx, ДЛЯ ЛОКАЛИ ЭТОТ config/nginx/nginx.conf, ДЛЯ СЕРВЕРА config/nginx.web/nginx.conf. Там по сути server_name только на свой изменить (localhost, или имя домена, если на сервере)

4. docker compose exec php bash
5. В php контейнере выподнить: composer install 

6.Собираемся: 
Локально выполняем команду: docker-compose up -d --build

7.Делаем дубликат файла /app/config.example.ini . Переименовываем в config.ini и редактируем под себя. В конфиге креды подключения к БД, токены от VK,Telegram,Facebook,OK.

8.Размечаем таблицу fromvk в БД wallposts:
CREATE TABLE `fromvk` (
  `id` int(11) NOT NULL,
  `text` text DEFAULT NULL,
  `photo` text DEFAULT NULL,
  `signer_id` text DEFAULT NULL,
  `IsRepostToTelegram` tinyint(1) NOT NULL DEFAULT 0,
  `IsRepostToInstagram` tinyint(1) NOT NULL DEFAULT 0,
  `IsRepostToFacebook` tinyint(1) NOT NULL DEFAULT 0,
  `IsRepostToViber` tinyint(1) DEFAULT 0,
  `IsRepostToOK` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

9.Добавляем индекс: ALTER TABLE `wallposts`.`fromvk` ADD UNIQUE `idIndex` (`id`) USING BTREE;
