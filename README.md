# snippen-booking
Booking modul for snippen grendehus

## Container-based development

This repository uses a VS Code devcontainer to run WordPress with SQLite locally.

### Start the devcontainer

1. Open the repository in VS Code.
2. Use `Remote Containers: Reopen in Container` (or `Dev Containers: Reopen in Container`).
3. Wait for the devcontainer build and `postCreateCommand` to finish.

### Access WordPress

- Site URL: `http://localhost:8080`
- Admin URL: `http://localhost:8080/wp-admin/`
- Admin credentials:
  - Username: `admin`
  - Password: `admin`

### Plugin development

- Plugin source lives in `src/wp-content/plugins/booking-plugin`
- The devcontainer symlinks this folder into WordPress at `wp-content/plugins/snippen-booking`
- Use the container terminal to run commands like `wp plugin list`, `wp plugin activate snippen-booking`, and other WP-CLI commands
- The devcontainer setup automates WordPress installation so you should not need to complete the web install wizard manually
- Database file location: `/wordpress/wp-content/database/dev.db`
- To reset the entire WordPress installation (including database), run: `bash /entrypoint.sh reset`

### Notes

- Port `8080` is forwarded from the container to your host.
- Xdebug warnings about `host.docker.internal:9003` are normal if you don’t have a debugger attached.
- If you change the plugin or config and the site stops working, rebuild/reopen the devcontainer and/or delete `/wordpress/wp-config.php` to force reconfiguration.
