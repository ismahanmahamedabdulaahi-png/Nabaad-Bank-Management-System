#!/bin/sh
set -e
cd /var/www/html

# Railway provides $PORT — point Apache at it
PORT="${PORT:-8080}"
# mod_php needs prefork — make sure no other MPM is enabled
rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.*
a2enmod -q mpm_prefork >/dev/null 2>&1 || true
sed -ri "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Persistent volume (uploads) may be mounted empty — recreate the dirs
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs storage/app/private storage/app/public
chown -R www-data:www-data storage bootstrap/cache

php artisan migrate --force

# Seed roles/admin/settings only on a fresh (empty) database
USERS=$(php artisan tinker --execute='echo \App\Models\User::count();' 2>/dev/null | tail -n1 || echo "x")
if [ "$USERS" = "0" ]; then
    php artisan db:seed --force
fi

[ -e public/storage ] || php artisan storage:link
php artisan optimize

# Scheduler (EOD, alerts, service-code expiry, ...)
su -s /bin/sh www-data -c "php artisan schedule:work" > /dev/stderr 2>&1 &

exec apache2-foreground
