#!/bin/bash
PHP_VERSION="$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')"
phpcs --standard=PHPCompatibility -q -n --colors --runtime-set testVersion "$PHP_VERSION" ispapi_modules.php || exit 1;