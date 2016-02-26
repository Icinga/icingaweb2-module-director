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

Clone this repository in your icingaweb2 `modules` directory and name it `director`, eg.:

```
git clone https://github.com/Icinga/icingaweb2-module-director.git /usr/share/icingaweb2/modules/director
```

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

As with any Icinga Web 2 module, installation is pretty straight-forward. 

Create a new database resource pointing to your newly created database. Usually add an entry
for the database in your [resources.ini](https://github.com/Icinga/icingaweb2/blob/master/doc/resources.md#resources):

```
[director]
type = "db"
db = "mysql"
host = "localhost"
port = ""
dbname = "director"
username = "director"
password = "some-password"
charset = ""
persistent = "0"
```

In your web front end, enable the director module in System / Modules / Director.

Then you have to tell the director module to use this newly created database
resource in System / Modules / Director / Configuration. Save the configuration or manually add
an entry to `/etc/icingaweb2/modules/director/config.ini`.

As a last step you need to add an API endpoint to your icinga2 installation.

In case you prefer automated or manual installation please learn more about
[automated installations](doc/30-Automation.md) in the related section of our [documentation](doc/30-Automation.md).

