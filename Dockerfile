FROM php:8.2-apache

# Install dependencies
RUN apt-get update && \
    apt-get install -y \
        libicu-dev \
        libxml2-dev \
        libc-client-dev \
        libkrb5-dev \
        libssl-dev \
        wget \
        unzip \
        sqlite3 \
        php-pear \
        git \
        nano \
        libsqlite3-dev \
        autoconf \
        gcc \
        make \
        libpng-dev \
        libjpeg-dev \
        libonig-dev

# Build and install IMAP extension
RUN docker-php-source extract && \
    pecl install imap && \
    docker-php-ext-enable imap && \
    docker-php-source delete

# Enable SQLite and other PHP extensions
RUN docker-php-ext-install pdo pdo_sqlite sqlite3 mbstring intl

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set working dir and copy app
WORKDIR /var/www/html
COPY . .

# Permissions
RUN chown -R www-data:www-data /var/www/html
