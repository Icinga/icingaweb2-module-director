ALTER TABLE director_activity_log
DROP INDEX checksum,
ADD UNIQUE INDEX checksum (checksum);

ALTER TABLE director_generated_config
DROP FOREIGN KEY director_generated_config_activity;

ALTER TABLE director_generated_config
ADD CONSTRAINT director_generated_config_activity
    FOREIGN KEY (last_activity_checksum)
    REFERENCES director_activity_log (checksum)
    ON DELETE RESTRICT
    ON UPDATE RESTRICT;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (188, NOW());
