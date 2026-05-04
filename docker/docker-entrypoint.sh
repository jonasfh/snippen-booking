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
PLUGIN_DIR="wp-content/plugins/sqlite-database-integration"
if [ ! -d "$PLUGIN_DIR/database" ]; then
  echo "Installing SQLite plugin..."
  rm -rf "$PLUGIN_DIR"
  git clone --depth 1 --branch main https://github.com/WordPress/sqlite-database-integration "$PLUGIN_DIR"
  if [ -d "$PLUGIN_DIR/packages/plugin-sqlite-database-integration" ]; then
    mv "$PLUGIN_DIR/packages/plugin-sqlite-database-integration"/* "$PLUGIN_DIR"/
    rm -rf "$PLUGIN_DIR/packages"
  fi
fi

# Activate plugin manually (drop-in)
if [ ! -f wp-content/db.php ]; then
  if [ -f wp-content/plugins/sqlite-database-integration/db.copy ]; then
    cp wp-content/plugins/sqlite-database-integration/db.copy \
       wp-content/db.php
  elif [ -f wp-content/plugins/sqlite-database-integration/packages/plugin-sqlite-database-integration/db.copy ]; then
    cp wp-content/plugins/sqlite-database-integration/packages/plugin-sqlite-database-integration/db.copy \
       wp-content/db.php
  else
    echo "Could not find db.copy in sqlite-database-integration plugin" >&2
    exit 1
  fi
fi

# wp-config
if [ ! -f wp-config.php ]; then
  echo "Creating wp-config..."
  export DB_ENGINE=${DB_ENGINE:-sqlite}
  export DB_DIR=${DB_DIR:-$WP_DIR/wp-content/database}
  export DB_FILE=${DB_FILE:-dev.db}
  mkdir -p "$DB_DIR"
  wp config create \
    --dbname=dev.db \
    --dbuser="" \
    --dbpass="" \
    --dbhost="localhost" \
    --dbprefix=wp_ \
    --skip-check \
    --allow-root
  cat >> wp-config.php <<EOF

define( 'DB_ENGINE', '${DB_ENGINE}' );
define( 'DB_DIR', '${DB_DIR}' );
define( 'DB_FILE', '${DB_FILE}' );
EOF
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
PLUGIN_SOURCE="$WORKSPACE_DIR/src/wp-content/plugins/booking-plugin"
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
