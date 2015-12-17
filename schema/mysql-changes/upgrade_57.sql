
CREATE TABLE icinga_apiuser (
  id INT(10) UNSIGNED AUTO_INCREMENT NOT NULL,
  object_name VARCHAR(255) NOT NULL,
  object_type ENUM('object', 'template', 'external_object') NOT NULL,
  password VARCHAR(255) DEFAULT NULL,
  client_dn VARCHAR(64) DEFAULT NULL,
  permissions TEXT DEFAULT NULL COMMENT 'JSON-encoded permissions',
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE icinga_endpoint
  ADD COLUMN apiuser_id INT(10) UNSIGNED DEFAULT NULL,
  ADD CONSTRAINT icinga_apiuser
    FOREIGN KEY apiuser (apiuser_id)
    REFERENCES icinga_apiuser (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE;


