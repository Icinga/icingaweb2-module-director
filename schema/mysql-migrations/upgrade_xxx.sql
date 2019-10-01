CREATE TABLE director_datafield_category (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  category_name VARCHAR(255) NOT NULL,
  description TEXT DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY category_name (category_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE director_datafield
  ADD COLUMN category_id INT(10) UNSIGNED DEFAULT NULL AFTER id;


-- INSERT INTO director_schema_migration
--  (schema_version, migration_time)
--  VALUES (167, NOW());
