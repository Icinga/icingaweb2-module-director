UPDATE icinga_user u
SET period_id = NULL
WHERE NOT EXISTS (
  SELECT id FROM icinga_timeperiod
  WHERE id = u.period_id
) AND u.period_id IS NOT NULL;

ALTER TABLE icinga_user
  ADD CONSTRAINT icinga_user_period
  FOREIGN KEY period (period_id)
  REFERENCES icinga_timeperiod (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (150, NOW());
