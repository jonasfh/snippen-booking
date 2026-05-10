#!/usr/bin/env bash
set -euo pipefail

WP_DIR="/wordpress"
PORT="8080"

mkdir -p "$WP_DIR"
cd "$WP_DIR"

echo "Starting MariaDB..."

# Start MariaDB service
service mariadb start

# Wait for MariaDB to be ready
until mysqladmin ping --silent; do
  echo "Waiting for MariaDB..."
  sleep 1
done

# Create database and user if they don't exist
mysql -u root -e "CREATE DATABASE IF NOT EXISTS wordpress;" || true
mysql -u root -e "CREATE USER IF NOT EXISTS 'wpuser'@'localhost' IDENTIFIED BY 'wppass';" || true
mysql -u root -e "GRANT ALL PRIVILEGES ON wordpress.* TO 'wpuser'@'localhost';" || true
mysql -u root -e "FLUSH PRIVILEGES;" || true

echo "Downloading WordPress..."

if [ ! -f wp-load.php ]; then
  wp core download \
    --locale=en_US \
    --allow-root
fi

echo "Creating wp-config.php..."

if [ ! -f wp-config.php ]; then
  wp config create \
    --dbname=wordpress \
    --dbuser=wpuser \
    --dbpass=wppass \
    --dbhost=localhost \
    --skip-check \
    --allow-root
fi

echo "Installing WordPress..."

if ! wp core is-installed --allow-root; then
  wp core install \
    --url="http://localhost:${PORT}" \
    --title="Dev Site" \
    --admin_user=admin \
    --admin_password=admin \
    --admin_email=admin@example.com \
    --skip-email \
    --allow-root
fi

# Symlink the plugin
PLUGIN_SOURCE="/workspaces/snippen-booking/src/wp-content/plugins/booking-plugin"
PLUGIN_SLUG="snippen-booking"
if [ -d "$PLUGIN_SOURCE" ]; then
  if [ ! -L "wp-content/plugins/$PLUGIN_SLUG" ]; then
    echo "Symlinking plugin..."
    rm -rf "wp-content/plugins/$PLUGIN_SLUG"
    ln -s "$PLUGIN_SOURCE" "wp-content/plugins/$PLUGIN_SLUG"
  fi
fi

# Activate the plugin
if [ -d "wp-content/plugins/$PLUGIN_SLUG" ]; then
  wp plugin activate "$PLUGIN_SLUG" --allow-root || true
fi

# if has argument, and if argument is "setup", exit here
if [ $# -gt 0 ] && [ "$1" == "setup" ]; then
  echo "Setup complete. Exiting."
  exit 0
fi

# If argument is "reset", clean everything and exit
if [ $# -gt 0 ] && [ "$1" == "reset" ]; then
  echo "Resetting WordPress installation..."
  rm -f wp-config.php
  mysql -u root -e "DROP DATABASE IF EXISTS wordpress;" || true
  mysql -u root -e "DROP USER IF EXISTS 'wpuser'@'localhost';" || true
  echo "Reset complete. Run 'setup' to reinstall."
  exit 0
fi

echo "Starting PHP server on port ${PORT}..."

cat > router.php <<'EOF'
<?php

if (php_sapi_name() === 'cli-server') {
    $path = parse_url(
        $_SERVER['REQUEST_URI'],
        PHP_URL_PATH
    );

    $file = __DIR__ . $path;

    if (is_file($file)) {
        return false;
    }
}

require __DIR__ . '/index.php';
EOF

exec php -S 0.0.0.0:${PORT} router.php