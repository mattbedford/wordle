FROM php:8.2-cli

# Install system dependencies
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
        libonig-dev \
        zlib1g-dev \
        libzip-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install \
        intl \
        xml \
        pdo \
        pdo_sqlite \
        sqlite3 \
        mbstring \
        zip \
        gd \
        opcache

# Install IMAP manually
RUN pecl install imap && docker-php-ext-enable imap

# Set working dir
WORKDIR /app

# Copy your project files
COPY . .

# Expose port (if running built-in PHP server)
EXPOSE 8080

CMD ["php", "-S", "0.0.0.0:8080", "-t", "."]
