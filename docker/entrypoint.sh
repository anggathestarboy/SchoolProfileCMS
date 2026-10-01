#!/bin/sh
set -e

cd /app

# Only wait on the database and migrate when actually starting the server
# (the real "docker compose up" case) — a one-off command run via
# `docker compose run --rm --no-deps app php artisan ...` (e.g.
# key:generate --show, before .env even has DB credentials filled in)
# has no "db" container to wait for and nothing to migrate.
if [ "$1" = "supervisord" ]; then
    echo "Waiting for the database..."
    # db:show only needs a working connection, unlike migrate:status — which
    # errors ("Migration table not found") on a brand-new database with no
    # migrations table yet, so it would never succeed on first boot.
    until php artisan db:show > /dev/null 2>&1; do
        sleep 2
    done

    php artisan migrate --force
    php artisan storage:link || true

    # Bootstraps the admin user/site/collection on the very first boot only.
    # draft:setup is NOT safe to call unconditionally on every boot — one of
    # its steps (make:media) prompts interactively for a new container title
    # if "assets" already exists, which would hang forever with no TTY
    # attached. So this checks the users table directly first — with raw
    # PDO, not artisan/tinker (tinker's exit() doesn't reliably propagate as
    # the process's own exit code) — and only calls draft:setup when nobody
    # exists yet. Override DRAFT_ADMIN_*/DRAFT_SITE_* in .env.docker before
    # the first boot to change these from their defaults.
    if ! php -r '
        $pdo = new PDO(
            "mysql:host=".getenv("DB_HOST").";port=".getenv("DB_PORT").";dbname=".getenv("DB_DATABASE"),
            getenv("DB_USERNAME"),
            getenv("DB_PASSWORD")
        );
        exit($pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() > 0 ? 0 : 1);
    ' > /dev/null 2>&1; then
        php artisan draft:setup \
            --name="${DRAFT_ADMIN_NAME:-Admin}" \
            --email="${DRAFT_ADMIN_EMAIL:-admin@example.com}" \
            --password="${DRAFT_ADMIN_PASSWORD:-change-me-1234}" \
            --site-name="${DRAFT_SITE_NAME:-Staging}" \
            --site-handle="${DRAFT_SITE_HANDLE:-default}"
    fi

    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

exec "$@"
