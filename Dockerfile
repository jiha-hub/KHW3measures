FROM php:8.2-cli

RUN docker-php-ext-install pdo pdo_mysql mysqli

COPY . /app

WORKDIR /app

CMD php -S 0.0.0.0:${PORT:-80} -t /app
