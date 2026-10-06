FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    postgresql-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    oniguruma-dev \
    icu-dev \
    $PHPIZE_DEPS

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        pgsql \
        gd \
        zip \
        intl \
        mbstring \
        opcache \
        bcmath

RUN pecl install redis && docker-php-ext-enable redis

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Mã nguồn được gắn từ máy chủ vào nên chủ sở hữu thư mục khác với người chạy
# lệnh trong container. Không khai báo ngoại lệ này thì composer và artisan in
# ra cảnh báo "detected dubious ownership in repository at '/var/www'" mỗi lần
# cập nhật, và một số lệnh git bên trong composer sẽ không chạy.
RUN git config --global --add safe.directory /var/www
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-custom.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-custom.conf

# Cấp quyền ghi cho storage mỗi lần container khởi động — xem entrypoint.sh
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]