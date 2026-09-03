#!/usr/bin/env bash
set -euo pipefail

WP_DIR="/wordpress"
PORT="${PORT:-8080}"
DB_HOST="${MYSQL_HOST:-localhost}"
DB_USER="${MYSQL_USER:-wpuser}"
DB_PASS="${MYSQL_PWD:-wppass}"
DB_NAME="${MYSQL_DATABASE:-wordpress}"
WP_URL="${WP_URL:-http://${HTTP_HOST:-localhost}:${PORT}}"

mkdir -p "$WP_DIR"
cd "$WP_DIR"

# 1. Database Initialization
if [ "$DB_HOST" = "localhost" ] || [ "$DB_HOST" = "127.0.0.1" ]; then
  echo "Starting MariaDB..."
  service mariadb start

  until mysqladmin ping --silent; do
    echo "Waiting for MariaDB..."
    sleep 1
  done

  mysql -u root -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\`;" || true
  mysql -u root -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';" || true
  mysql -u root -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';" || true
  mysql -u root -e "FLUSH PRIVILEGES;" || true
fi

# 2. Download WordPress Core
echo "Downloading WordPress..."
if [ ! -f wp-load.php ]; then
  wp core download \
    --version=7.0 \
    --locale=en_US \
    --allow-root
fi

# 3. Create wp-config.php
echo "Creating wp-config.php..."
if [ ! -f wp-config.php ]; then
  wp config create \
    --dbname="${DB_NAME}" \
    --dbuser="${DB_USER}" \
    --dbpass="${DB_PASS}" \
    --dbhost="${DB_HOST}" \
    --skip-check \
    --allow-root
fi

# Inject dynamic WP_HOME and WP_SITEURL to allow access from multiple hostnames (localhost vs container name)
if ! grep -q "WP_HOME" wp-config.php; then
  cat <<'WPCONF' >> wp-config.php

// Dynamic WP_HOME and WP_SITEURL for multi-host container support (Issue #265)
if ( ! defined( 'WP_HOME' ) ) {
    $scheme = ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] === 'on' ) ? 'https://' : 'http://';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
    define( 'WP_HOME', $scheme . $host );
}
if ( ! defined( 'WP_SITEURL' ) ) {
    $scheme = ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] === 'on' ) ? 'https://' : 'http://';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
    define( 'WP_SITEURL', $scheme . $host );
}
WPCONF
fi

# 4. Install WordPress Core
echo "Installing WordPress..."
if ! wp core is-installed --allow-root; then
  wp core install \
    --url="${WP_URL}" \
    --title="Snippen Booking Dev" \
    --admin_user=admin \
    --admin_password=admin \
    --admin_email=admin@example.com \
    --skip-email \
    --allow-root
  wp rewrite structure '/%postname%/' --allow-root

  # Ensure standard .htaccess is created with HTTP_AUTHORIZATION pass-through
  cat > .htaccess <<'HTACCESS_EOF'
# BEGIN WordPress
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
RewriteBase /
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
</IfModule>
# END WordPress
HTACCESS_EOF
  chown www-data:www-data .htaccess || true
fi

# 5. Symlink and activate plugin
PLUGIN_SOURCE="${PLUGIN_SOURCE:-/workspaces/snippen-booking/src/wp-content/plugins/booking-plugin}"
if [ ! -d "$PLUGIN_SOURCE" ] && [ -d "/app/src/wp-content/plugins/booking-plugin" ]; then
  PLUGIN_SOURCE="/app/src/wp-content/plugins/booking-plugin"
fi

PLUGIN_SLUG="snippen-booking"
if [ -d "$PLUGIN_SOURCE" ]; then
  if [ ! -L "wp-content/plugins/$PLUGIN_SLUG" ]; then
    echo "Symlinking plugin from $PLUGIN_SOURCE..."
    rm -rf "wp-content/plugins/$PLUGIN_SLUG"
    ln -s "$PLUGIN_SOURCE" "wp-content/plugins/$PLUGIN_SLUG"
  fi
fi

if [ -d "wp-content/plugins/$PLUGIN_SLUG" ]; then
  wp plugin activate "$PLUGIN_SLUG" --allow-root || true
fi

# 6. Configure Apache VirtualHost (Always configured before any exit)
echo "Configuring Apache..."
cat > /etc/apache2/sites-available/000-default.conf <<EOF_APACHE
<VirtualHost *:${PORT}>
    ServerAdmin webmaster@localhost
    DocumentRoot /wordpress

    <Directory /wordpress>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/error.log
    CustomLog \${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF_APACHE

# 7. Non-interactive demo execution support
PROJECT_ROOT="/workspaces/snippen-booking"
if [ ! -d "$PROJECT_ROOT" ] && [ -d "/app" ]; then
  PROJECT_ROOT="/app"
fi

if [ "${INIT_GATEWAY:-false}" = "true" ] || [ "${1:-}" = "demo:gateway" ] || [ "${1:-}" = "gateway" ]; then
  echo "Running composer demo:gateway headless..."
  if [ -d "$PROJECT_ROOT" ]; then
    (cd "$PROJECT_ROOT" && composer demo:gateway)
  fi
fi

if [ "${INIT_DEMO:-false}" = "true" ] || [ "${AUTO_DEMO:-false}" = "true" ] || [ "${1:-}" = "demo" ]; then
  echo "Running composer demo headless..."
  if [ -d "$PROJECT_ROOT" ]; then
    (cd "$PROJECT_ROOT" && composer demo)
  fi
fi

# 8. Arguments Handling
if [ $# -gt 0 ] && [ "$1" == "setup" ]; then
  echo "Setup complete. Exiting."
  exit 0
fi

if [ $# -gt 0 ] && [ "$1" == "reset" ]; then
  echo "Resetting WordPress installation..."
  rm -f wp-config.php
  mysql -u root -e "DROP DATABASE IF EXISTS \`${DB_NAME}\`;" || true
  mysql -u root -e "DROP USER IF EXISTS '${DB_USER}'@'localhost';" || true
  echo "Reset complete. Run 'setup' to reinstall."
  exit 0
fi

if [ $# -gt 0 ] && [ "$1" != "demo" ] && [ "$1" != "demo:gateway" ] && [ "$1" != "gateway" ]; then
  exec "$@"
fi

echo "Starting Apache..."
exec apachectl -D FOREGROUND
