CREATE TABLE director_job (
  id serial,
  job_name character varying(64) NOT NULL,
  job_class character varying(72) NOT NULL,
  disabled enum_boolean NOT NULL DEFAULT 'n',
  run_interval integer NOT NULL, -- seconds
  last_attempt_succeeded enum_boolean DEFAULT NULL,
  ts_last_attempt timestamp with time zone DEFAULT NULL,
  ts_last_error timestamp with time zone DEFAULT NULL,
  last_error_message text NULL DEFAULT NULL,
  PRIMARY KEY (id)
);

CREATE UNIQUE INDEX director_job_unique_job_name ON director_job (job_name);


CREATE TABLE director_job_setting (
  job_id integer NOT NULL,
  setting_name character varying(64) NOT NULL,
  setting_value text DEFAULT NULL,
  PRIMARY KEY (job_id, setting_name),
  CONSTRAINT director_job_setting_job
    FOREIGN KEY (job_id)
    REFERENCES director_job (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);

CREATE INDEX director_job_setting_job ON director_job_setting (job_id);


INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (94, NOW());
