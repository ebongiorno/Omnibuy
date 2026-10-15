FROM php:8.3-apache

# Install PHP extensions and tools needed by OmniBuy and PHPUnit
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        unzip \
        libonig-dev \
#combines the previous instructures to install pdo, pdo_mysql, and mbstring at the same time
    && docker-php-ext-install \ 
        pdo \
        pdo_mysql \
        mbstring \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN a2enmod rewrite

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html

COPY . /var/www/html

EXPOSE 80
