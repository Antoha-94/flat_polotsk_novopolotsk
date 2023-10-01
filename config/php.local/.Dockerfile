FROM php:7.4-fpm

RUN apt-get update && apt-get install -y --no-install-recommends gnupg \
    netcat \
    sudo \
    libicu-dev \
    libfreetype6-dev \
    libjpeg-dev \
    libpng-dev \
    libxml2-dev \
    libsodium-dev \
    libxslt-dev \
    libzip-dev \
    libwebp-dev \
    rsync \
    unzip \
    nano \
    vim \
    less \
    git \
    cron \
    webp \
    zip \
    ;

RUN curl --silent --show-error https://getcomposer.org/installer | php && \
    mv composer.phar /usr/local/bin/composer

RUN docker-php-ext-configure gd --with-freetype=/usr/include/ --with-jpeg=/usr/include/ --with-webp=/usr/include/

RUN docker-php-ext-install bcmath \
    intl \
    gd \
    opcache \
    soap \
    sodium \
    xsl \
    pdo_mysql \
    zip \
    sockets \
    mysqli \
    ;

COPY ./php.ini /usr/local/etc/php/conf.d/php.ini

CMD ["php-fpm"]
