#!/bin/bash

set -ex

MODULE_HOME=${MODULE_HOME:="$(dirname "$(readlink -f $(dirname "$0"))")"}
PHP_VERSION="$(php -r 'echo phpversion();')"

ICINGAWEB_VERSION=${ICINGAWEB_VERSION:=2.4.1}
ICINGAWEB_GITREF=${ICINGAWEB_GITREF:=}

PHPCS_VERSION=${PHPCS_VERSION:=2.9.1}

if [ "$PHP_VERSION" '<' 5.6.0 ]; then
  PHPUNIT_VERSION=${PHPUNIT_VERSION:=4.8}
else
  PHPUNIT_VERSION=${PHPUNIT_VERSION:=5.7}
fi

cd ${MODULE_HOME}

test -d vendor || mkdir vendor
cd vendor/

# icingaweb2
if [ -n "$ICINGAWEB_GITREF" ]; then
  icingaweb_path="icingaweb2"
  test ! -L "$icingaweb_path" || rm "$icingaweb_path"

  if [ ! -d "$icingaweb_path" ]; then
    git clone https://github.com/Icinga/icingaweb2.git "$icingaweb_path"
  fi

  (
    set -e
    cd "$icingaweb_path"
    git fetch -p
    git checkout -f "$ICINGAWEB_GITREF"
  )
else
  icingaweb_path="icingaweb2-${ICINGAWEB_VERSION}"
  if [ ! -e "${icingaweb_path}".tar.gz ]; then
    wget -O "${icingaweb_path}".tar.gz https://github.com/Icinga/icingaweb2/archive/v"${ICINGAWEB_VERSION}".tar.gz
  fi
  if [ ! -d "${icingaweb_path}" ]; then
    tar xf "${icingaweb_path}".tar.gz
  fi

  ln -svf "${icingaweb_path}" icingaweb2
fi
ln -svf "${icingaweb_path}"/library/Icinga
ln -svf "${icingaweb_path}"/library/vendor/Zend

# phpunit
phpunit_path="phpunit-${PHPUNIT_VERSION}"
if [ ! -e "${phpunit_path}".phar ]; then
  wget -O "${phpunit_path}".phar https://phar.phpunit.de/phpunit-${PHPUNIT_VERSION}.phar
fi
ln -svf "${phpunit_path}".phar phpunit.phar

# phpcs
phpcs_path="phpcs-${PHPCS_VERSION}"
if [ ! -e "${phpcs_path}".phar ]; then
  wget -O "${phpcs_path}".phar \
    https://github.com/squizlabs/PHP_CodeSniffer/releases/download/${PHPCS_VERSION}/phpcs.phar
fi
ln -svf "${phpcs_path}".phar phpcs.phar
