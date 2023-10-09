CREATE TABLE director_activity_log_remark (
  first_related_activity BIGINT(20) UNSIGNED NOT NULL,
  last_related_activity BIGINT(20) UNSIGNED NOT NULL,
  remark TEXT NOT NULL,
  PRIMARY KEY (first_related_activity, last_related_activity),
  CONSTRAINT activity_log_remark_begin
    FOREIGN KEY first_related_activity (first_related_activity)
      REFERENCES director_activity_log (id)
      ON DELETE CASCADE
      ON UPDATE CASCADE,
  CONSTRAINT activity_log_remark_end
    FOREIGN KEY last_related_activity (last_related_activity)
      REFERENCES director_activity_log (id)
      ON DELETE CASCADE
      ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES ('178', NOW());
