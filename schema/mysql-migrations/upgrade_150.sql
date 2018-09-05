UPDATE icinga_user
SET period_id = NULL
WHERE id IN (
  SELECT DISTINCT u.id
  FROM icinga_user u
  LEFT JOIN icinga_timeperiod tp ON tp.id = u.period_id
  WHERE u.period_id IS NOT NULL AND tp.id IS NULL
);

ALTER TABLE icinga_user
  ADD CONSTRAINT icinga_user_period
    FOREIGN KEY period (period_id)
    REFERENCES icinga_timeperiod (id)
      ON DELETE RESTRICT
      ON UPDATE CASCADE;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (150, NOW());
