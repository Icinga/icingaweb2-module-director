ALTER TABLE icinga_user_states_set 
  DROP CONSTRAINT icinga_user_states_set_pkey,
  ADD PRIMARY KEY (user_id, property, merge_behaviour);

ALTER TABLE icinga_user_types_set
  DROP CONSTRAINT icinga_user_types_set_pkey,
  ADD PRIMARY KEY (user_id, property, merge_behaviour);

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (79, NOW());
