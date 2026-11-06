# Manager Library API - PHP 8.3 CLI with pdo_pgsql and Composer.
FROM php:8.3-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libzip-dev unzip git \
    && docker-php-ext-install pdo_pgsql zip \
    && rm -rf /var/lib/apt/lists/*

# Never print function arguments (passwords) in stack traces (RNF-3).
# Upload limits above the 2 MB cover rule, so the API itself answers 422 for bigger files.
RUN printf "zend.exception_ignore_args=On\nupload_max_filesize=8M\npost_max_size=10M\n" > /usr/local/etc/php/conf.d/library.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

EXPOSE 8000
# Startup (T-17): migrations, then the initial admin from the environment, then the server.
CMD ["sh", "-c", "php artisan migrate --force && php artisan library:ensure-admin && php artisan serve --host=0.0.0.0 --port=8000"]
