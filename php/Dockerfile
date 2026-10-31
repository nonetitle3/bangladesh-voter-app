FROM php:8.3-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
    unzip git poppler-utils tesseract-ocr tesseract-ocr-ben tesseract-ocr-eng \
    libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring zip gd \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY composer.json composer.lock* ./
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader || composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
COPY public/ ./public/
COPY src/ ./src/
COPY database/ ./database/
COPY .htaccess ./public/.htaccess

RUN mkdir -p /var/www/html/storage/pdfs /var/www/html/storage/previews \
    && chown -R www-data:www-data /var/www/html/storage \
    && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
EXPOSE 80
CMD ["apache2-foreground"]