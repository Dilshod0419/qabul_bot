FROM php:8.2-fpm-alpine

# Install extensions
RUN docker-php-ext-install pdo pdo_mysql

# Install curl extension
RUN apk add --no-cache curl-dev && docker-php-ext-install curl

WORKDIR /var/www/telegram-bot

COPY . .

RUN chown -R www-data:www-data /var/www/telegram-bot

EXPOSE 9000
