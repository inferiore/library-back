#!/bin/bash
set -e

echo "Starting Laravel application..."

# Wait for database connection
echo "Waiting for database connection..."
until php -r "new PDO('mysql:host=mysql;dbname=library', 'library_user', 'library_password');" 2>/dev/null; do
  echo "Database not ready, waiting..."
  sleep 2
done

echo "Database connected!"

# Run migrations
echo "Running migrations..."
php artisan migrate --force

# Optimize application
echo "Optimizing application..."
php artisan config:cache
php artisan route:cache

# Seed database if needed
if [ "$(php artisan tinker --execute='echo App\Models\User::count();' 2>/dev/null | tail -1)" = "0" ]; then
  echo "Seeding database..."
  php artisan db:seed --force
fi

# Start server
echo "Starting server on http://0.0.0.0:8000"
php artisan serve --host=0.0.0.0 --port=8000