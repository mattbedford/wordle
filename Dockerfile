FROM php:8.2-cli

# Update and fix broken locales (common cause of apt-get fail on Buddy)
RUN apt-get update || apt-get -o Acquire::ForceIPv4=true update

# Install required packages — keep separate for easier debugging
RUN apt-get install -y apt-transport-https ca-certificates gnupg

# Install system dependencies in smaller chunks (helps pinpoint any failure)
RUN apt-get update && apt-get install -y \
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
    libonig-dev \
    zlib1g-dev \
    libzip-dev \
    --no-install-recommends

# Clean up cache (recommended for Docker layer size)
RUN rm -rf /var/lib/apt/lists/*

# PHP Extensions
RUN docker-php-ext-install \
    intl \
    xml \
    mbstring \
    pdo \
    pdo_sqlite \
    sqlite3 \
    zip \
    gd \
    opcache

# IMAP manually via PECL
RUN pecl install imap && docker-php-ext-enable imap

WORKDIR /app
COPY . .

CMD ["php", "-S", "0.0.0.0:8080", "-t", "."]
