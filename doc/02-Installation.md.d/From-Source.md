# Installing Icinga Director from Source

These are the instructions for manual Director installations.

Please see the Icinga Web documentation on
[how to install modules](https://icinga.com/docs/icinga-web-2/latest/doc/08-Modules/#installation) from source.
Make sure you use `director` as the module name. The following requirements must also be met.

## Requirements

* PHP (≥7.3)
    * Director v1.10 is the last version with support for PHP v5.6
* [Icinga 2](https://github.com/Icinga/icinga2) (≥2.8.0)
    * It is recommended to use the latest feature release of Icinga 2
    * All versions since 2.4.3 should also work fine, but
      we do no longer test and support them.
    * Some features require newer Icinga 2 releases
        * Flapping requires 2.8 for the thresholds to work - and at least 2.7 on all
          nodes
* [Icinga Web](https://github.com/Icinga/icingaweb2) (≥2.8.0). All versions since 2.2 should also work fine, but
  might show smaller UI bugs and are not actively tested
* The following Icinga modules must be installed and enabled:
    * [incubator](https://github.com/Icinga/icingaweb2-module-incubator) (≥0.20.0)
    * If you are using Icinga Web <2.9.0, the following modules are also required
        * [ipl](https://github.com/Icinga/icingaweb2-module-ipl) (≥0.5.0)
        * [reactbundle](https://github.com/Icinga/icingaweb2-module-reactbundle) (≥0.9.0)
* A database: MariaDB (≥10.1), MySQL (≥5.7), PostgreSQL (≥9.6). Other
  forks and older versions might work, but are neither tested nor supported
* `php-pdo-mysql` and/or `php-pdo-pgsql`
* `php-curl`
* `php-iconv`
* `php-pcntl` (might already be built into your PHP binary)
* `php-posix` or `php-process` depending on your platform
* `php-sockets` (might already be built into your PHP binary)

## Installing from Release Tarball

Download the [latest version](https://github.com/Icinga/icingaweb2-module-director/releases)
and extract it to a folder named `director` in one of your Icinga Web module path directories.

You might want to use a script as follows for this task:

```shell
MODULE_VERSION="1.11.0"
ICINGAWEB_MODULEPATH="/usr/share/icingaweb2/modules"
REPO_URL="https://github.com/icinga/icingaweb2-module-director"
TARGET_DIR="${ICINGAWEB_MODULEPATH}/director"
URL="${REPO_URL}/archive/v${MODULE_VERSION}.tar.gz"

install -d -m 0755 "${TARGET_DIR}"
wget -q -O - "$URL" | tar xfz - -C "${TARGET_DIR}" --strip-components 1
icingacli module enable director
```

## Installing from Git Repository

Another convenient method is to install directly from our Git repository.
Simply clone the repository in one of your Icinga web module path directories.

You might want to use a script as follows for this task:

```shell
MODULE_VERSION="1.11.0"
ICINGAWEB_MODULEPATH="/usr/share/icingaweb2/modules"
REPO_URL="https://github.com/icinga/icingaweb2-module-director"
TARGET_DIR="${ICINGAWEB_MODULEPATH}/director"

git clone "${REPO_URL}" "${TARGET_DIR}" --branch v${MODULE_VERSION}
icingacli module enable director
```

## Setting up the Director Daemon

For manual installations, the daemon user, its directory, and the systemd service need to be set up:

```shell
useradd -r -g icingaweb2 -d /var/lib/icingadirector -s /sbin/nologin icingadirector
install -d -o icingadirector -g icingaweb2 -m 0750 /var/lib/icingadirector
install -pm 0644 contrib/systemd/icinga-director.service /etc/systemd/system
systemctl daemon-reload
systemctl enable --now icinga-director
```
<!-- {% include "02-Installation.md" %} -->
