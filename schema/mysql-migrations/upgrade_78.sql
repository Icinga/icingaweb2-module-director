CREATE TABLE icinga_user_field (
  user_id INT(10) UNSIGNED NOT NULL COMMENT 'Makes only sense for templates',
  datafield_id INT(10) UNSIGNED NOT NULL,
  is_required ENUM('y', 'n') NOT NULL,
  PRIMARY KEY (user_id, datafield_id),
  CONSTRAINT icinga_user_field_user
  FOREIGN KEY user(user_id)
    REFERENCES icinga_user (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_user_field_datafield
  FOREIGN KEY datafield(datafield_id)
  REFERENCES director_datafield (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO director_schema_migration
  SET migration_time = NOW(),
      schema_version = 78;
