FROM wordpress:6.4-php8.2-apache

# System tools
RUN apt-get update && apt-get install -y \
    git \
    curl \
    unzip \
    less \
    vim \
    gnupg \
    ca-certificates \
    lsb-release \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions (ofte nødvendig)
RUN docker-php-ext-install \
    mysqli \
    pdo \
    pdo_mysql

# Install Xdebug
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

# Xdebug config
RUN echo "xdebug.mode=debug" >> /usr/local/etc/php/conf.d/xdebug.ini \
    && echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/xdebug.ini \
    && echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/xdebug.ini

# Install Composer (fra offisiell image)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Install WP-CLI
RUN curl -o /usr/local/bin/wp \
    https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod +x /usr/local/bin/wp

# Install GitHub CLI
RUN mkdir -p /etc/apt/keyrings \
    && curl -fsSL https://cli.github.com/packages/githubcli-archive-keyring.gpg \
    | tee /etc/apt/keyrings/githubcli-archive-keyring.gpg > /dev/null \
    && chmod go+r /etc/apt/keyrings/githubcli-archive-keyring.gpg \
    && echo "deb [arch=$(dpkg --print-architecture) \
    signed-by=/etc/apt/keyrings/githubcli-archive-keyring.gpg] \
    https://cli.github.com/packages stable main" \
    > /etc/apt/sources.list.d/github-cli.list \
    && apt-get update \
    && apt-get install -y gh \
    && rm -rf /var/lib/apt/lists/*

# (valgfritt) global PHPUnit
RUN composer global require phpunit/phpunit:^10 \
    && ln -s /root/.composer/vendor/bin/phpunit /usr/local/bin/phpunit
