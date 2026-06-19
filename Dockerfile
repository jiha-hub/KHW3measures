FROM php:8.2-apache

# PHP 확장 설치
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Apache mod_rewrite 활성화, MPM 충돌 해결
RUN a2enmod rewrite && \
    a2dismod mpm_event && \
    a2enmod mpm_prefork

# Railway PORT 환경변수 적용
RUN sed -i 's/Listen 80/Listen ${PORT:-80}/g' /etc/apache2/ports.conf && \
    sed -i 's/<VirtualHost \*:80>/<VirtualHost *:${PORT:-80}>/g' /etc/apache2/sites-enabled/000-default.conf

# 앱 파일 복사
COPY . /var/www/html/

# 권한 설정
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
