FROM php:8.2-fpm

# Install required OS dependencies and immediately clean apt cache to reduce layer size
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    git \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Configure and compile PHP extensions
# - gd: configured for image manipulation
# - pdo/pdo_mysql: native driver compiled without requiring OS mysql-client libs
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo pdo_mysql

# Note: 'mbstring' and 'session' are omitted as they are compiled into the base image natively.