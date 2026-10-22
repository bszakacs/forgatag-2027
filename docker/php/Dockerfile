# Helyi fejlesztokornyezet — a tarhellyel azonos PHP-fo-verzio (8.4).
# A pontos javito-verziot (8.4.x) a szerveren merjuk (public/meres.php), utana ide pinneljuk.
ARG PHP_IMAGE=php:8.4.26-apache
FROM ${PHP_IMAGE}

# gd + exif: a médiatár méretváltozatai (Curator/Glide) — a tárhelyen is kell a gd (Select PHP Version).
RUN apt-get update \
 && apt-get install -y --no-install-recommends libicu-dev libzip-dev unzip git libjpeg62-turbo-dev libpng-dev libwebp-dev libfreetype6-dev \
 && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
 && docker-php-ext-install intl pdo_mysql zip bcmath gd exif \
 && a2enmod rewrite \
 && rm -rf /var/lib/apt/lists/*

# A webgyoker a Laravel public/ mappaja — ugyanugy, mint a tarhelyen az aldomaine.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf

COPY --from=composer:2.8.9 /usr/bin/composer /usr/bin/composer
# Feltöltési korlát: a tárhely beállításával egyezően (a 10 MB-os programfotóhoz).
COPY feltoltes.ini /usr/local/etc/php/conf.d/feltoltes.ini
WORKDIR /var/www/html
