FROM php:8.2-cli

# PHP 확장 설치
RUN docker-php-ext-install pdo pdo_mysql mysqli

# 앱 파일 복사
COPY . /app

WORKDIR /app

EXPOSE 8080

# PHP 내장 서버 사용 (Apache 없이)
CMD php -S 0.0.0.0:${PORT:-8080} -t /app
