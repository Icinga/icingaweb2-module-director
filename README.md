Icinga Director
===============

Icinga Director has been designed to make Icinga 2 configuration handling easy.
It tries to target two main audiences:

* Users with the desire to completely automate their datacenter
* Sysops willing to grant their "point & click" users a lot of flexibility

What makes Icinga Director so special is the fact that it tries to target both
of them at once.

![Icinga Director](https://www.icinga.org/wp-content/uploads/2016/02/director_main_screen.png)

Requirements
------------

* Icinga 2 (&gt;= 2.4.3)
* Icinga Web 2 (&gt;= 2.2.0)
* A database, MySQL (&gt;= 5.1) or PostgreSQL (&gt;= 9.1) database (MariaDB and other forks are also fine)
* php5-curl

Installation
------------

### Create Icinga Director database

#### MySQL

    mysql -e "CREATE DATABASE director CHARACTER SET 'utf8';
       GRANT ALL ON director.* TO director@localhost IDENTIFIED BY 'some-password';"

#### PostgreSQL

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
[automated installations](doc/30-Automation.md) in the related [section](doc/30-Automation.md) of our documentation.

