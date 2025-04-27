FROM richarvey/nginx-php-fpm:3.1.6

# Copy project files
COPY . .

# Image config
ENV SKIP_COMPOSER 1
ENV WEBROOT /var/www/html/public
ENV PHP_ERRORS_STDERR 1
ENV RUN_SCRIPTS 1
ENV REAL_IP_HEADER 1

# Laravel config
ENV APP_ENV production
ENV APP_DEBUG false
ENV LOG_CHANNEL stderr

# Allow composer to run as root
ENV COMPOSER_ALLOW_SUPERUSER 1

# Install Google Cloud SDK for additional tools (optional)
RUN apk add --no-cache python3 py3-pip bash \
    && pip3 install --no-cache-dir google-cloud-sdk

CMD ["/start.sh"]
