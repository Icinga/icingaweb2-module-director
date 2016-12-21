CREATE TABLE icinga_service_set_inheritance (
  service_set_id INT(10) UNSIGNED NOT NULL,
  parent_service_set_id INT(10) UNSIGNED NOT NULL,
  weight MEDIUMINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (service_set_id, parent_service_set_id),
  UNIQUE KEY unique_order (service_set_id, weight),
  CONSTRAINT icinga_service_set_inheritance_set
  FOREIGN KEY host (service_set_id)
  REFERENCES icinga_service_set (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_service_set_inheritance_parent
  FOREIGN KEY host (parent_service_set_id)
  REFERENCES icinga_service_set (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE icinga_service_set MODIFY description TEXT DEFAULT NULL;

INSERT INTO director_schema_migration
  (schema_version, migration_time)
  VALUES (115, NOW());
