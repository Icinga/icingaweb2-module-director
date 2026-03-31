ALTER TABLE director_generated_config
  DROP FOREIGN KEY director_generated_config_activity;

# Delete all entries with duplicate checksum except the first entry
DELETE log1 FROM director_activity_log log1
  INNER JOIN director_activity_log log2 ON log1.checksum = log2.checksum
  WHERE log1.id > log2.id;

ALTER TABLE director_activity_log
DROP INDEX checksum,
ADD UNIQUE INDEX checksum (checksum);

ALTER TABLE director_generated_config
  ADD CONSTRAINT director_generated_config_activity
    FOREIGN KEY (last_activity_checksum)
    REFERENCES director_activity_log (checksum)
    ON DELETE RESTRICT
    ON UPDATE RESTRICT;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (188, NOW());
