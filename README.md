Icinga Director
===============

Icinga Director has been designed to make Icinga 2 configuration handling easy.
It tries to target two main audiences:

* Users with the desire to completely automate their datacenter
* Sysops willing to grant their "point & click" users a lot of flexibility

What makes Icinga Director so special is the fact that it tries to target both
of them at once.


Requirements
============

* Icinga 2 (&gt;= 2.4.2)
* Icinga Web 2 (&gt;= 2.2.0)
* MySQL or PostgreSQL database
* php5-curl
* PostgreSQL: the schema is lacking behind right now, we'll fix this soon. If you want to start testing Director today please use MySQL

Installation
============

Create Icinga Director database
-------------------------------

### MySQL

    mysql -e "CREATE DATABASE director CHARACTER SET 'utf8';
       GRANT ALL ON director.* TO director@localhost IDENTIFIED BY 'some-password';"

### PostgreSQL

    CREATE DATABASE director WITH ENCODING 'UTF8';
    CREATE USER director WITH PASSWORD 'some-password';
    GRANT ALL PRIVILEGES ON DATABASE director TO director;


Configure Icinga Web 2
----------------------

As with any Icinga Web 2 module, installation is pretty straight-forward. In
case you're installing it from source all you have to do is to drop the director
module in one of your module paths. Then go to your web frontend, Configuration,
Modules, director - and enable the module. 

In your web frontend please go to System / Configuration / Resources and create
a new database resource pointing to your newly created database. Last but not
least you have to tell the director module to use this newly created database
resource.

In case you prefer automated or manual installation please learn more about
[automated installations](doc/30-Automation.md) in the related section of our [documentation](doc/30-Automation.md).

