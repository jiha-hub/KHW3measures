FROM php:8.2-apache

# PHP 확장 설치
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Apache mod_rewrite 활성화
RUN a2enmod rewrite

# Apache 포트를 Railway PORT 환경변수에 맞게 설정
RUN sed -i 's/Listen 80/Listen ${PORT}/g' /etc/apache2/ports.conf
RUN sed -i 's/:80>/:${PORT}>/g' /etc/apache2/sites-enabled/000-default.conf

# 앱 파일 복사
COPY . /var/www/html/

# 권한 설정
RUN chown -R www-data:www-data /var/www/html

# Railway가 제공하는 PORT 사용
EXPOSE ${PORT:-80}

CMD ["apache2-foreground"]
