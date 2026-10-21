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

# Install cloudflared (Cloudflare Tunnel for webhook and local testing)
RUN ARCH=$(dpkg --print-architecture) \
    && curl -fsSL "https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-${ARCH}.deb" -o /tmp/cloudflared.deb \
    && dpkg -i /tmp/cloudflared.deb \
    && rm -f /tmp/cloudflared.deb

# Pre-install Chromium and system dependencies for Playwright visual regression tests
ENV PLAYWRIGHT_BROWSERS_PATH=/ms-playwright
RUN npx --yes playwright install chromium --with-deps \
    && chmod -R 777 /ms-playwright

# Configure Xdebug
RUN echo "xdebug.mode=debug" >> /etc/php/8.3/cli/conf.d/20-xdebug.ini \
    && echo "xdebug.start_with_request=trigger" >> /etc/php/8.3/cli/conf.d/20-xdebug.ini \
    && echo "xdebug.client_host=host.docker.internal" >> /etc/php/8.3/cli/conf.d/20-xdebug.ini \
    && echo "xdebug.client_port=9003" >> /etc/php/8.3/cli/conf.d/20-xdebug.ini

# Configure Apache to listen on port 8080 and set global ServerName
RUN sed -i 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf \
    && sed -i 's/:80/:8080/' /etc/apache2/sites-available/000-default.conf \
    && echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername

# Install dummy sendmail for local mail catching in devcontainer
RUN printf '#!/bin/sh\nLOG_FILE="/var/log/mail.log"\nif ! touch "$LOG_FILE" 2>/dev/null; then\n    LOG_FILE="/tmp/mail.log"\n    touch "$LOG_FILE" 2>/dev/null || true\nfi\nchmod 666 "$LOG_FILE" 2>/dev/null || true\necho "=== EMAIL DISPATCH: $(date -Iseconds) ===" >> "$LOG_FILE"\necho "Arguments: $*" >> "$LOG_FILE"\ncat >> "$LOG_FILE"\necho "" >> "$LOG_FILE"\nexit 0\n' > /usr/local/bin/sendmail-dummy \
    && chmod 755 /usr/local/bin/sendmail-dummy \
    && ln -sf /usr/local/bin/sendmail-dummy /usr/sbin/sendmail \
    && ln -sf /usr/local/bin/sendmail-dummy /usr/lib/sendmail \
    && touch /var/log/mail.log && chmod 666 /var/log/mail.log

ENV XDEBUG_MODE=debug

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- \
    --install-dir=/usr/local/bin --filename=composer

# Install WP-CLI
RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod +x wp-cli.phar \
    && mv wp-cli.phar /usr/local/bin/wp

# Install PHP_CodeSniffer and PHPCBF
RUN curl -OL https://squizlabs.github.io/PHP_CodeSniffer/phpcs.phar \
    && chmod +x phpcs.phar \
    && mv phpcs.phar /usr/local/bin/phpcs \
    && curl -OL https://squizlabs.github.io/PHP_CodeSniffer/phpcbf.phar \
    && chmod +x phpcbf.phar \
    && mv phpcbf.phar /usr/local/bin/phpcbf

# Copy entrypoint script
COPY docker-entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

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

# Ensure workspace and entrypoint are writable by the non-root user
RUN chown -R "$USER_UID":"$USER_GID" /workspaces /entrypoint.sh || true

ENV HOME=/home/$USERNAME
USER $USERNAME

# Healthcheck verifying WordPress and SMS outbox responsiveness
HEALTHCHECK --interval=10s --timeout=5s --start-period=15s --retries=3 \
    CMD bash /workspaces/snippen-booking/bin/healthcheck.sh || exit 1

ENTRYPOINT ["/entrypoint.sh"]
