Installation
============

These are the instructions for manual Director installations. You can
learn moree about how to automate this in the [automation](03-Automation.md) section
of this documentation.

Requirements
------------

* Icinga 2 (&gt;= 2.4.3)
* Icinga Web 2 (&gt;= 2.2.0)
* A database, MySQL (&gt;= 5.1) or PostgreSQL (&gt;= 9.1) database (MariaDB and
  other forks are also fine)
* php5-curl

Database
--------

### Create an empty Icinga Director database

#### MySQL

    mysql -e "CREATE DATABASE director CHARACTER SET 'utf8';
       GRANT ALL ON director.* TO director@localhost IDENTIFIED BY 'some-password';"

#### PostgreSQL


    psql -q -c "CREATE DATABASE director WITH ENCODING 'UTF8';"
    psql director -q -c "CREATE USER director WITH PASSWORD 'some-password';
    GRANT ALL PRIVILEGES ON DATABASE director TO director;"


Configure Icinga Web 2
----------------------

In your web frontend please go to System / Configuration / Resources and create
a new database resource pointing to your newly created database. Please make
sure that you choose `utf8` as an encoding.

As with any Icinga Web 2 module, installation is pretty straight-forward. In
case you're installing it from source all you have to do is to drop the director
module in one of your module paths. You can examine (and set) the module path(s)
in `Configuration / General`. In a typical environment you'll probably drop the
module to `/usr/share/icingaweb2/modules/director`. Please note that the directory
name MUST be `director` and not `icingaweb2-module-director` or anything else.

Now go to your web frontend, Configuration, Modules, director - and enable the
module. Choose either Director directly from the menu or got to the Configuration
tab. Either way you'll reach the kickstart wizards. Follow the instructions and
you're all done!
