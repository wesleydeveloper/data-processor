FROM php:8.3-cli

# Install system dependencies
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
        zlib1g-dev \
        libxml2-dev \
        libicu-dev \
        libonig-dev \
    && docker-php-ext-install zip xml intl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy composer manifests and install dependencies
COPY composer.json phpunit.xml ./
RUN composer install --prefer-dist --no-interaction --no-progress

# Copy application source
COPY . .

# Default command to run tests
CMD ["vendor/bin/phpunit", "--colors=always"]