FROM php:8.3-apache

# ── System packages & PHP extensions ────────────────────────────────────────
# libzip/png/jpeg/webp/freetype: needed to build the gd extension (thumbnails).
# default-mysql-client: lets you `oc rsh` into the pod and run mysql/mysqldump.
# ffmpeg: RoboDoc2 shells out to /usr/bin/ffmpeg for background video compression
#         on upload (see app/controllers/EntryController.php) — required, not optional.
RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev libpng-dev libjpeg62-turbo-dev libwebp-dev libfreetype6-dev libonig-dev \
        default-mysql-client ffmpeg unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mysqli gd exif zip mbstring \
    && a2enmod rewrite headers \
    && apt-get clean && rm -rf /var/lib/apt/lists/*
# NOTE: intentionally NOT purging the -dev packages afterwards — apt's
# --auto-remove pulls the matching runtime shared libraries (libzip5,
# libpng16, libjpeg62-turbo, libwebp7, libfreetype6, libonig5) out with them
# since nothing "apt-visible" depends on them (the PHP extensions that link
# against them were built by docker-php-ext-install, not apt), which breaks
# those extensions at runtime with "cannot open shared object file" errors.

# ── Apache: listen on 8080, serve from public/ ──────────────────────────────
# OpenShift's default "restricted" SCC runs containers as an arbitrary,
# non-root UID (with group 0) and never grants CAP_NET_BIND_SERVICE, so we
# can't bind port 80 — 8080 is the conventional unprivileged substitute.
RUN sed -i 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:8080>/' /etc/apache2/sites-available/000-default.conf \
    && sed -i 's#DocumentRoot /var/www/html#DocumentRoot /var/www/html/public#' /etc/apache2/sites-available/000-default.conf \
    && printf '<Directory /var/www/html/public>\n    AllowOverride All\n    Require all granted\n</Directory>\n' \
       >> /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html
COPY app/ /var/www/html/app/
COPY public/ /var/www/html/public/

# uploads/ is a mount point for a PersistentVolumeClaim in OpenShift — created
# here so the image is also directly runnable (e.g. plain `docker run`) without one.
RUN mkdir -p /var/www/html/uploads/thumbs \
    && printf 'RewriteEngine On\nRewriteCond %%{REQUEST_FILENAME} !-f\nRewriteCond %%{REQUEST_FILENAME} !-d\nRewriteRule ^ index.php [QSA,L]\n' \
       > /var/www/html/public/.htaccess

# ── Arbitrary-UID compatibility ─────────────────────────────────────────────
# The UID OpenShift assigns at runtime is unpredictable, but it is always a
# member of group 0. Make every path the app or Apache itself needs to write
# to group-owned by 0 with group rwX, so it works no matter which UID lands.
RUN mkdir -p /var/run/apache2 /var/lock/apache2 \
    && chgrp -R 0 /var/www/html /var/log/apache2 /var/run/apache2 /var/lock/apache2 \
    && chmod -R g=u /var/www/html /var/log/apache2 /var/run/apache2 /var/lock/apache2 \
    && chmod -R g+rwX /var/www/html/uploads

ENV HOME=/var/www/html
EXPOSE 8080
USER 1001
CMD ["apache2-foreground"]
