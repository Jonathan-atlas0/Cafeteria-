FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    build-essential \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libpq-dev \
    libssl-dev \
    curl \
    unzip \
    git \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install pdo pdo_pgsql pgsql gd zip \
 && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN a2enmod rewrite

RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini" \
 && echo "display_errors = On" >> "$PHP_INI_DIR/php.ini" \
 && echo "error_reporting = E_ALL" >> "$PHP_INI_DIR/php.ini" \
 && echo "upload_max_filesize = 32M" >> "$PHP_INI_DIR/php.ini" \
 && echo "post_max_size = 32M" >> "$PHP_INI_DIR/php.ini" \
 && echo "memory_limit = 256M" >> "$PHP_INI_DIR/php.ini"

WORKDIR /var/www/html

# Copia só o composer.json primeiro para aproveitar cache de layers
COPY composer.json ./

# Instala dependências antes de copiar o resto do código
RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-scripts || true

# Agora copia o restante do projeto com dono correto (evita chown recursivo lento)
COPY --chown=www-data:www-data . .

# Regenera autoload com o código completo
RUN composer dump-autoload --optimize || true

EXPOSE 80

CMD ["apache2-foreground"]
