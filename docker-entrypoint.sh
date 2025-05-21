#!/bin/bash

# Decode Google Cloud credentials if provided
if [ ! -z "$GOOGLE_CREDENTIALS_B64" ]; then
  echo "$GOOGLE_CREDENTIALS_B64" | base64 -d > /var/www/storage/credentials/laravel.json
  echo "✅ Google credentials decoded successfully"
else
  echo "⚠️ GOOGLE_CREDENTIALS_B64 is not set. Skipping credentials decode."
fi

# Start Laravel dev server
php artisan serve --host=0.0.0.0 --port=8000
