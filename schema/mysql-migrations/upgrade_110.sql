UPDATE icinga_host_var
  SET varvalue = 'false',
       format = 'json'
  WHERE varvalue = 'n'
    AND varname IN (
      SELECT DISTINCT varname
        FROM director_datafield
       WHERE datatype LIKE '%DataTypeBoolean'
    );

UPDATE icinga_host_var
  SET varvalue = 'true',
       format = 'json'
  WHERE varvalue = 'y'
    AND varname IN (
      SELECT DISTINCT varname
        FROM director_datafield
       WHERE datatype LIKE '%DataTypeBoolean'
    );

UPDATE icinga_service_var
  SET varvalue = 'false',
       format = 'json'
  WHERE varvalue = 'n'
    AND varname IN (
      SELECT DISTINCT varname
        FROM director_datafield
       WHERE datatype LIKE '%DataTypeBoolean'
    );

UPDATE icinga_service_var
  SET varvalue = 'true',
       format = 'json'
  WHERE varvalue = 'y'
    AND varname IN (
      SELECT DISTINCT varname
        FROM director_datafield
       WHERE datatype LIKE '%DataTypeBoolean'
    );


UPDATE icinga_command_var
  SET varvalue = 'false',
       format = 'json'
  WHERE varvalue = 'n'
    AND varname IN (
      SELECT DISTINCT varname
        FROM director_datafield
       WHERE datatype LIKE '%DataTypeBoolean'
    );

UPDATE icinga_command_var
  SET varvalue = 'true',
       format = 'json'
  WHERE varvalue = 'y'
    AND varname IN (
      SELECT DISTINCT varname
        FROM director_datafield
       WHERE datatype LIKE '%DataTypeBoolean'
    );

UPDATE icinga_user_var
  SET varvalue = 'false',
       format = 'json'
  WHERE varvalue = 'n'
    AND varname IN (
      SELECT DISTINCT varname
        FROM director_datafield
       WHERE datatype LIKE '%DataTypeBoolean'
    );

UPDATE icinga_user_var
  SET varvalue = 'true',
       format = 'json'
  WHERE varvalue = 'y'
    AND varname IN (
      SELECT DISTINCT varname
        FROM director_datafield
       WHERE datatype LIKE '%DataTypeBoolean'
    );

UPDATE icinga_notification_var
  SET varvalue = 'false',
       format = 'json'
  WHERE varvalue = 'n'
    AND varname IN (
      SELECT DISTINCT varname
        FROM director_datafield
       WHERE datatype LIKE '%DataTypeBoolean'
    );

UPDATE icinga_notification_var
  SET varvalue = 'true',
       format = 'json'
  WHERE varvalue = 'y'
    AND varname IN (
      SELECT DISTINCT varname
        FROM director_datafield
       WHERE datatype LIKE '%DataTypeBoolean'
    );

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (110, NOW());
