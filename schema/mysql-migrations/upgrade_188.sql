ALTER TABLE director_activity_log
ADD UNIQUE INDEX idx_checksum (checksum);

ALTER TABLE director_generated_config
ADD CONSTRAINT fk_director_generated_config_activity
    FOREIGN KEY (last_activity_checksum)
    REFERENCES director_activity_log(checksum)
    ON DELETE RESTRICT
    ON UPDATE RESTRICT;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (188, NOW());
