#!/bin/sh
# Migrations own the schema (Article V, ADR-001): run them before serving (interpretation I-08).
set -e
php artisan migrate --force
# T-17 adds here: php artisan library:bootstrap-admin
exec php artisan serve --host=0.0.0.0 --port=8000
