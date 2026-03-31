ALTER TABLE director_activity_log
  MODIFY COLUMN old_properties MEDIUMTEXT DEFAULT NULL COMMENT 'Property hash, JSON',
  MODIFY COLUMN new_properties MEDIUMTEXT DEFAULT NULL COMMENT 'Property hash, JSON';

ALTER TABLE icinga_host_var
  MODIFY COLUMN varvalue MEDIUMTEXT DEFAULT NULL;

INSERT INTO director_schema_migration
(schema_version, migration_time)
VALUES (191, NOW());
