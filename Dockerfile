FROM mcr.microsoft.com/devcontainers/base:ubuntu-24.04

ENV DEBIAN_FRONTEND=noninteractive

# Install dependencies and default PHP (8.3 on Ubuntu 24.04)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get update && apt-get install -y \
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
    gh \
    apache2 \
    libapache2-mod-php \
    vim \
    nodejs \
    sudo \
    && a2enmod rewrite \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Configure Xdebug
RUN echo "xdebug.mode=debug" >> /etc/php/8.3/cli/conf.d/20-xdebug.ini \
    && echo "xdebug.start_with_request=trigger" >> /etc/php/8.3/cli/conf.d/20-xdebug.ini \
    && echo "xdebug.client_host=host.docker.internal" >> /etc/php/8.3/cli/conf.d/20-xdebug.ini \
    && echo "xdebug.client_port=9003" >> /etc/php/8.3/cli/conf.d/20-xdebug.ini

# Configure Apache to listen on port 8080
RUN sed -i 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf \
    && sed -i 's/:80/:8080/' /etc/apache2/sites-available/000-default.conf

ENV XDEBUG_MODE=debug

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- \
    --install-dir=/usr/local/bin --filename=composer

# Install WP-CLI
RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod +x wp-cli.phar \
    && mv wp-cli.phar /usr/local/bin/wp

# Copy entrypoint script and healthcheck
COPY .devcontainer/docker-entrypoint.sh /entrypoint.sh
COPY bin/healthcheck.sh /usr/local/bin/healthcheck.sh
RUN chmod +x /entrypoint.sh /usr/local/bin/healthcheck.sh

EXPOSE 8080
EXPOSE 9003

# Create a non-root user that matches the host UID/GID so mounted files keep correct ownership.
ARG USERNAME=vscode
ARG USER_UID=1000
ARG USER_GID=1000
RUN groupadd --gid "$USER_GID" "$USERNAME" || true \
 && useradd -s /bin/bash --uid "$USER_UID" --gid "$USER_GID" -m "$USERNAME" || true \
 && mkdir -p /home/$USERNAME/.local && chown -R $USER_UID:$USER_GID /home/$USERNAME

# Allow the non-root user to run admin commands for starting services during postStart
RUN echo "$USERNAME ALL=(ALL) NOPASSWD:ALL" > /etc/sudoers.d/$USERNAME \
 && chmod 0440 /etc/sudoers.d/$USERNAME

# Copy repository files into workspace
WORKDIR /workspaces/snippen-booking
COPY --chown=$USERNAME:$USERNAME . /workspaces/snippen-booking
RUN composer install --no-interaction --prefer-dist

ENV HOME=/home/$USERNAME
USER $USERNAME

# Healthcheck verifying WordPress and SMS outbox responsiveness
HEALTHCHECK --interval=10s --timeout=5s --start-period=15s --retries=3 \
    CMD /usr/local/bin/healthcheck.sh || exit 1

ENTRYPOINT ["/entrypoint.sh"]
