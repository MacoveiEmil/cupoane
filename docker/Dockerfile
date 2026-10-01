# Imaginea locală: PHP 8.3 + nginx, plus extensiile cerute de Filament (intl) și de logo-uri WebP (gd).
# Pe hostingul cPanel aceleași extensii se activează din „Select PHP Version”.
FROM serversideup/php:8.3-fpm-nginx

USER root
RUN install-php-extensions intl gd exif bcmath
USER www-data
