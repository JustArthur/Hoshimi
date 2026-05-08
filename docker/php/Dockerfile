# ============================================================
#  PHP-FPM — Image personnalisée pour la médiathèque
# ============================================================

FROM php:8.3-fpm-alpine

# --- Dépendances système + FFmpeg ---
RUN apk add --no-cache \
    # Build tools
    autoconf \
    g++ \
    make \
    # Extensions PHP
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    freetype-dev \
    libzip-dev \
    oniguruma-dev \
    curl-dev \
    icu-dev \
    # FFmpeg pour lire les métadonnées vidéo
    ffmpeg \
    # Git (utile en dev)
    git

# --- Extensions PHP ---
RUN docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        mysqli \
        gd \
        zip \
        mbstring \
        curl \
        intl \
        opcache \
        exif

# --- Composer ---
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# --- Répertoire de travail ---
WORKDIR /var/www/html

# --- Permissions ---
RUN chown -R www-data:www-data /var/www/html

USER www-data
