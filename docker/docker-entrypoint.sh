#!/bin/bash
set -e

# Define workspace and wordpress directories
WORKSPACE_DIR="/workspaces/snippen-booking"
WP_DIR="/wordpress"

# Ensure WordPress directory exists
mkdir -p "$WP_DIR"
cd "$WP_DIR"

# Download WordPress if not exists
if [ ! -f wp-load.php ]; then
  echo "Installing WordPress..."
  wp core download --allow-root
fi

# SQLite plugin (required for WP with SQLite)
if [ ! -d wp-content/plugins/sqlite-database-integration ]; then
  echo "Installing SQLite plugin..."
  git clone https://github.com/WordPress/sqlite-database-integration \
    wp-content/plugins/sqlite-database-integration
fi

# Activate plugin manually (drop-in)
if [ ! -f wp-content/db.php ]; then
  cp wp-content/plugins/sqlite-database-integration/db.copy \
     wp-content/db.php
fi

# wp-config
if [ ! -f wp-config.php ]; then
  echo "Creating wp-config..."
  wp config create \
    --dbname=dev.db \
    --dbtype=sqlite \
    --dbprefix=wp_ \
    --skip-check \
    --allow-root
fi

# Install WP if not done
if ! wp core is-installed --allow-root; then
  echo "Installing site..."
  wp core install \
    --url=http://localhost:8080 \
    --title="Dev Site" \
    --admin_user=admin \
    --admin_password=admin \
    --admin_email=admin@example.com \
    --skip-email \
    --allow-root
fi

# Symlink the plugin
if [ ! -L "wp-content/plugins/snippen-booking" ]; then
  echo "Symlinking plugin..."
  ln -s "$WORKSPACE_DIR" "wp-content/plugins/snippen-booking"
fi

# Activate the plugin
wp plugin activate snippen-booking --allow-root || true

# If argument is "setup", exit here
if [ "$1" == "setup" ]; then
  echo "Setup complete. Exiting."
  exit 0
fi

echo "Starting PHP server..."

# Router for pretty URLs
cat > router.php <<'EOF'
<?php
if (php_sapi_name() === 'cli-server') {
    $url  = parse_url($_SERVER['REQUEST_URI']);
    $file = __DIR__ . $url['path'];

    if (is_file($file)) {
        return false;
    }
}
require_once __DIR__ . '/index.php';
EOF

exec php -S 0.0.0.0:8080 router.php
