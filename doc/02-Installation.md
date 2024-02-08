<!-- {% if index %} -->
# Installing Icinga Director

The recommended way to install Icinga Director and its dependencies is to use prebuilt packages for
all supported platforms from our official release repository.
Please note that [Icinga Web](https://icinga.com/docs/icinga-web) is required to run Icinga Director
and if it is not already set up, it is best to do this first.

The following steps will guide you through installing and setting up Icinga Director.

To upgrade an existing Icinga Director installation to a newer version,
see the [upgrading](05-Upgrading.md) documentation for the necessary steps.

If you want to automate the installation, configuration and upgrade,
you can learn more about it in the [automation](03-Automation.md) section of this documentation.

## Optional Requirements

The following requirements are not necessary for installation,
but may be needed later if you want to import from one of the listed sources:

* For **IBM Db2** imports: `php-pdo-ibm`
* For **Microsoft SQL Server** imports: `php-mssql`, `php-pdo-dblib` or `php-sybase` depending on your platform
* For **Oracle Database** imports: `php-oci8` or `php-pdo-oci` depending on your platform
* For **SQLite** imports: `php-pdo-sqlite`
<!-- {% else %} -->
<!-- {% if not icingaDocs %} -->

## Installing Icinga Director Package

If the [repository](https://packages.icinga.com) is not configured yet, please add it first.
Then use your distribution's package manager to install the `icinga-director` package
or install [from source](02-Installation.md.d/From-Source.md).
<!-- {% endif %} -->

## Setting up the Database

A MySQL (≥5.7), MariaDB (≥10.1), or PostgreSQL (≥9.6) database is required to run Icinga Director.
Please follow the steps listed for your target database, to set up the database and the user.
The schema will be imported later via the web interface.

### Setting up a MySQL or MariaDB Database

> **Warning**
> Make sure to replace `CHANGEME` with a secure password.

```
mysql -e "CREATE DATABASE director CHARACTER SET 'utf8';
  CREATE USER director@localhost IDENTIFIED BY 'CHANGEME';
  GRANT ALL ON director.* TO director@localhost;"
```

### Setting up a PostgreSQL Database

> **Warning**
> Make sure to replace `CHANGEME` with a secure password.

```
psql -q -c "CREATE DATABASE director WITH ENCODING 'UTF8';"
psql director -q -c "CREATE USER director WITH PASSWORD 'CHANGEME';
GRANT ALL PRIVILEGES ON DATABASE director TO director;
CREATE EXTENSION pgcrypto;"
```

## Configuring Icinga Director

Log in to your running Icinga Web setup with a privileged user
and follow the steps below to configure Icinga Director:

1. Create a new resource for the Icinga Director [database](#setting-up-the-database) via the
   `Configuration → Application → Resources` menu.
   Please make sure that you configure `utf8` as encoding.
2. Select  `Icinga Director` directly from the main menu
   and you will be taken to the kickstart wizard. Follow the instructions and you are done!
<!-- {% endif %} --><!-- {# end else if index #} -->
