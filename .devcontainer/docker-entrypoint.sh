#!/usr/bin/env bash
set -euo pipefail

# Elevate privileges if run as non-root (e.g. USER vscode)
if [ "$(id -u)" -ne 0 ]; then
  exec sudo -E bash "$0" "$@"
fi

WP_DIR="/wordpress"
PORT="${PORT:-8080}"
DB_HOST="${MYSQL_HOST:-localhost}"
DB_USER="${MYSQL_USER:-wpuser}"
DB_PASS="${MYSQL_PWD:-wppass}"
DB_NAME="${MYSQL_DATABASE:-wordpress}"
WP_URL="${WP_URL:-http://${HTTP_HOST:-localhost}:${PORT}}"

mkdir -p "$WP_DIR"
cd "$WP_DIR"

# Ensure phpcbf and phpcs are accessible in /usr/local/bin
if [ -f "/workspaces/snippen-booking/vendor/bin/phpcbf" ] && [ ! -f "/usr/local/bin/phpcbf" ]; then
  ln -sf /workspaces/snippen-booking/vendor/bin/phpcbf /usr/local/bin/phpcbf || true
fi
if [ -f "/workspaces/snippen-booking/vendor/bin/phpcs" ] && [ ! -f "/usr/local/bin/phpcs" ]; then
  ln -sf /workspaces/snippen-booking/vendor/bin/phpcs /usr/local/bin/phpcs || true
fi

# 1. Database Initialization
if [ "$DB_HOST" = "localhost" ] || [ "$DB_HOST" = "127.0.0.1" ]; then
  if [ -d "/etc/mysql/mariadb.conf.d" ]; then
    cat << 'EOF' > /etc/mysql/mariadb.conf.d/99-test-performance.cnf
[mysqld]
innodb_flush_log_at_trx_commit = 2
EOF
  fi

  echo "Starting MariaDB..."
  service mariadb start

  until mysqladmin ping --silent; do
    echo "Waiting for MariaDB..."
    sleep 1
  done

  mysql -u root -e "SET GLOBAL innodb_flush_log_at_trx_commit = 2;" || true

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

# Inject reverse proxy HTTPS support and dynamic WP_HOME / WP_SITEURL before wp-settings.php
if ! grep -q "HTTP_X_FORWARDED_PROTO" wp-config.php; then
  python3 -c '
path = "wp-config.php"
with open(path, "r") as f:
    content = f.read()

proxy_code = """
// Support reverse proxy HTTPS (Cloudflare Tunnel, ngrok, Codespaces)
if ( isset( $_SERVER[\x27HTTP_X_FORWARDED_PROTO\x27] ) && 0 === strpos( $_SERVER[\x27HTTP_X_FORWARDED_PROTO\x27], \x27https\x27 ) ) {
    $_SERVER[\x27HTTPS\x27] = \x27on\x27;
}
if ( ! empty( $_SERVER[\x27HTTP_X_FORWARDED_HOST\x27] ) ) {
    $_SERVER[\x27HTTP_HOST\x27] = $_SERVER[\x27HTTP_X_FORWARDED_HOST\x27];
}

// Dynamic WP_HOME and WP_SITEURL for multi-host container and tunnel support (Issue #265)
if ( ! defined( \x27WP_HOME\x27 ) ) {
    $scheme = ( isset( $_SERVER[\x27HTTPS\x27] ) && $_SERVER[\x27HTTPS\x27] === \x27on\x27 ) ? \x27https://\x27 : \x27http://\x27;
    $host   = $_SERVER[\x27HTTP_HOST\x27] ?? \x27localhost:8080\x27;
    define( \x27WP_HOME\x27, $scheme . $host );
}
if ( ! defined( \x27WP_SITEURL\x27 ) ) {
    $scheme = ( isset( $_SERVER[\x27HTTPS\x27] ) && $_SERVER[\x27HTTPS\x27] === \x27on\x27 ) ? \x27https://\x27 : \x27http://\x27;
    $host   = $_SERVER[\x27HTTP_HOST\x27] ?? \x27localhost:8080\x27;
    define( \x27WP_SITEURL\x27, $scheme . $host );
}
"""

target = "/* That\x27s all, stop editing! Happy publishing. */"
if target in content:
    content = content.replace(target, proxy_code + "\n" + target)
elif "require_once ABSPATH . \x27wp-settings.php\x27;" in content:
    content = content.replace("require_once ABSPATH . \x27wp-settings.php\x27;", proxy_code + "\nrequire_once ABSPATH . \x27wp-settings.php\x27;")

with open(path, "w") as f:
    f.write(content)
'
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
  wp eval 'if ( class_exists( "\SnippenBooking\Database\Install" ) ) { \SnippenBooking\Database\Install::activate(); } if ( class_exists( "\SnippenBooking\Database\MigrationManager" ) ) { \SnippenBooking\Database\MigrationManager::run(); }' --allow-root || true
fi

# Ensure wp-content and uploads are writable by both web server (www-data) and dev/test user (vscode)
mkdir -p "$WP_DIR/wp-content/uploads"
chmod 777 "$WP_DIR/wp-content" || true
chmod -R 777 "$WP_DIR/wp-content/uploads" || true

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

if [ $# -gt 0 ] && ( [ "$1" == "start" ] || [ "$1" == "background" ] || [ "$1" == "bg" ] ); then
  echo "Ensuring MariaDB and Apache are running in the background..."
  service mariadb start
  service apache2 start
  echo "Services started. WordPress is running on http://localhost:${PORT}"
  exit 0
fi

if [ $# -gt 0 ] && [ "$1" == "stop" ]; then
  echo "Stopping services..."
  service apache2 stop || true
  service mariadb stop || true
  echo "Services stopped."
  exit 0
fi

if [ $# -gt 0 ] && [ "$1" == "status" ]; then
  service mariadb status || true
  service apache2 status || true
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
