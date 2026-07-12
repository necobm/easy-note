# EasyNote tests

Two layers of tests ship with the plugin.

## 1. WP-CLI smoke test (runs today)

An assertion-based smoke test that exercises the plugin's logic and REST surface
against the live WordPress install. It needs no extra tooling and is the
verification used in this environment.

```bash
docker compose exec wordpress wp eval-file \
  wp-content/plugins/easynote/tests/wpcli-smoke.php --allow-root
```

It prints `PASS`/`FAIL` per check, creates and removes its own fixtures, and
exits non-zero if anything fails. It covers post-type/taxonomy registration,
activation + cap granting, the content-meta sanitizer, the tree builder,
ownership scoping, and the REST tree/move/toggle-public endpoints plus the
public-note 404-for-private / 200-for-shared behaviour. Current result:
**18 passing**.

The front end is verified separately with a headless-browser smoke run
(login gating, sidebar tree render, note open, and the type→autosave→reload
round-trip).

## 2. PHPUnit suite (ready, not yet runnable here)

`tests/phpunit/` contains a `WP_UnitTestCase` suite covering post-type/taxonomy
registration, activation + capability granting, the content-meta sanitizer, the
tree builder, ownership scoping, and the REST endpoints (public visibility,
author-only share, publish-on-share, and note move). Configuration is in
`phpunit.xml.dist`.

**Why it is not run in this environment:** the WordPress Docker image
(`wordpress:php8.3-apache`) does not include `phpunit`, `composer`, `svn`, or
`mysqladmin`, and the standard WordPress PHPUnit library is not installed. This
is the documented fallback trigger from the plan — delivery is verified via the
WP-CLI smoke test above instead.

**To enable the PHPUnit suite** (adds tooling to the container / image):

1. Install PHPUnit + the WP test library, e.g. add to the `Dockerfile`:
   `apt-get install -y subversion default-mysql-client` and Composer, then
   `composer require --dev phpunit/phpunit yoast/phpunit-polyfills`.
2. Install the test suite against the existing MySQL service (root creds create
   a separate `wordpress_test` DB — no second service required):
   ```bash
   bin/install-wp-tests.sh wordpress_test root "$MYSQL_ROOT_PASSWORD" db latest
   ```
   (Obtain `install-wp-tests.sh` via `wp scaffold plugin-tests easynote`.)
3. Run:
   ```bash
   WP_TESTS_DIR=/tmp/wordpress-tests-lib vendor/bin/phpunit \
     -c wp-content/plugins/easynote/phpunit.xml.dist
   ```
