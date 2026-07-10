FROM wordpress:php8.3-apache

# WP-CLI for scaffolding, DB resets, plugin/theme installs from the shell.
# Run via: docker compose exec wordpress wp <command>
RUN curl -fSL -o /usr/local/bin/wp \
      https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
 && chmod +x /usr/local/bin/wp

# Use PHP's development ini for a dev-friendly configuration (verbose errors, etc.).
RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
