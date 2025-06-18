ALTER TABLE icinga_dependency ADD COLUMN redundancy_group character varying(255);
ALTER TABLE branched_icinga_dependency ADD COLUMN redundancy_group character varying(255);

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (190, NOW());
