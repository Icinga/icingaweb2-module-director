Icinga Director
===============

This is going to be the new Icinga 2 config tool. Not for productional use. YET.

Installation
============

Create Icinga Director database
-------------------------------

    mysql -e "CREATE DATABASE director2;
       GRANT SELECT, INSERT, UPDATE, DELETE ON director.* TO director@localhost
       IDENTIFIED BY 'some-password';"

    mysql director < schema/mysql.sql

Configure Icinga Web 2
----------------------

In your web frontend please go to System / Configuration / Resources and create
a new database resource pointing to your newly created database. Last but not
least you have to tell the director module to use this newly created database
resource.

Given that you named your resource `directordb` the `config.ini` for the module
could look as follows:

    @@@ini
    [db]
    resource = directordb

This file is to be found in <ICINGAWEB_CONFIGDIR>/modules/director/, where
ICINGAWEB_CONFIGDIR usually means /etc/icingaweb2.
