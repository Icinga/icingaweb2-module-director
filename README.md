Icinga Director
===============

This is going to be the new Icinga 2 config tool. Not for productional use. YET.

Installation
============

Create Icinga Director database
-------------------------------

    mysql < "CREATE DATABASE director;" \
        " GRANT SELECT, INSERT, UPDATE, DELETE ON director.*" \
        " TO director@localhost IDENTIFIED BY 'some-password';"

    mysql director < schema/mysql.sql


