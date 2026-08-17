FROM php:8.3-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends git libzip-dev zip unzip \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install zip

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY . /src
WORKDIR /src

# The GitHub API allows only 60 anonymous requests per hour, which the six VCS
# repositories in composer.json exhaust immediately. Cloning over plain git
# instead avoids the API — and the token it would otherwise demand — entirely.
RUN composer config --global use-github-api false \
    && composer install -o --prefer-source --no-dev --no-interaction \
    && chmod a+x expose-server builds/expose-server

# The SQLite database lives under $HOME/.expose; mount a volume here to keep
# users and auth tokens across redeploys.
RUN mkdir -p /root/.expose

ENV port=8080
ENV domain=localhost
ENV username=username
ENV password=password
ENV exposeConfigPath=/src/config/expose-server.php

COPY docker-entrypoint.sh /usr/bin/
RUN chmod 755 /usr/bin/docker-entrypoint.sh
ENTRYPOINT ["docker-entrypoint.sh"]
