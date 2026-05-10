FROM mcr.microsoft.com/devcontainers/base:ubuntu-24.04

ENV DEBIAN_FRONTEND=noninteractive

# Install dependencies and default PHP (8.3 on Ubuntu 24.04)
RUN apt-get update && apt-get install -y \
    curl \
    git \
    unzip \
    mariadb-server \
    mariadb-client \
    php \
    php-cli \
    php-curl \
    php-json \
    php-mbstring \
    php-mysql \
    php-xml \
    php-zip \
    php-xdebug \
    php-intl \
    php-gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Configure Xdebug
RUN echo "xdebug.mode=debug" >> /etc/php/8.3/cli/conf.d/20-xdebug.ini \
    && echo "xdebug.start_with_request=yes" >> /etc/php/8.3/cli/conf.d/20-xdebug.ini \
    && echo "xdebug.client_host=host.docker.internal" >> /etc/php/8.3/cli/conf.d/20-xdebug.ini \
    && echo "xdebug.client_port=9003" >> /etc/php/8.3/cli/conf.d/20-xdebug.ini

ENV XDEBUG_MODE=debug

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- \
    --install-dir=/usr/local/bin --filename=composer

# Install WP-CLI
RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod +x wp-cli.phar \
    && mv wp-cli.phar /usr/local/bin/wp

# Copy entrypoint script
COPY docker/docker-entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 8080
EXPOSE 9003
