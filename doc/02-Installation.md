<a id="Installation"></a>Installation
=====================================

These are the instructions for manual Director installations. You can
learn more about how to automate this in the [automation](03-Automation.md) section
of this documentation. In case you already installed Director and want to upgrade
to the latest version, please [read on here](05-Upgrading.md).

Requirements
------------

* Icinga 2 (&gt;= 2.6.0). All versions sinve 2.4.3 should also work fine, but
  we do no longer test and support them.
* Icinga Web 2 (&gt;= 2.4.1). All versions since 2.2 should also work fine, but
  might show smaller UI bugs and are not actively tested
* A database, MySQL (&gt;= 5.1) or PostgreSQL (&gt;= 9.1). MariaDB and other
  MySQL forks are also fine. Mentioned versions are the required minimum,
  for MySQL we suggest using at least 5.5.3, for PostgreSQL 9.4.
* PHP (>= 5.4). For best performance please consider use 7.x
* php-curl

Database
--------

### Create an empty Icinga Director database

HINT: You should replace `some-password` with a secure custom password.

#### MySQL (or MariaDB)

    mysql -e "CREATE DATABASE director CHARACTER SET 'utf8';
       GRANT ALL ON director.* TO director@localhost IDENTIFIED BY 'some-password';"

In case your MySQL root user is password-protected, please add `-p` to this
command.

#### PostgreSQL


    psql -q -c "CREATE DATABASE director WITH ENCODING 'UTF8';"
    psql director -q -c "CREATE USER director WITH PASSWORD 'some-password';
    GRANT ALL PRIVILEGES ON DATABASE director TO director;
    CREATE EXTENSION pgcrypto;"

Hint: pgcrypto helps to boost performance, but is currently optional. In case you
do not have it available on your platform and/or do not know how to solve this
just leave away the 'CREATE EXTENSION' part.

Web-based Configuration
-----------------------

The following steps should guide you through the web-based Kickstart wizard.
In case you prefer automated configuration, you should check the dedicated
[documentation section](03-Automation.md).

### Create a Database resource

In your web frontend please go to `Configuration / Application / Resources`
and create a new database resource pointing to your newly created database.
Please make sure that you choose `utf8` as an encoding.


### Install the Director module

As with any Icinga Web 2 module, installation is pretty straight-forward. In
case you're installing it from source all you have to do is to drop the director
module in one of your module paths. You can examine (and set) the module path(s)
in `Configuration / Application`. In a typical environment you'll probably drop the
module to `/usr/share/icingaweb2/modules/director`. Please note that the directory
name MUST be `director` and not `icingaweb2-module-director` or anything else.


### Run the graphical kickstart wizard

Now go to your web frontend, Configuration, Modules, director - and enable the
module. Choose either Director directly from the menu or got to the Configuration
tab. Either way you'll reach the kickstart wizards. Follow the instructions and
you're all done!
