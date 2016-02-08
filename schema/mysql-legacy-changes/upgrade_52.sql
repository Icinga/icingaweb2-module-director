ALTER TABLE icinga_endpoint
  MODIFY object_type ENUM('object', 'template', 'external_object') NOT NULL,
  ADD COLUMN host VARCHAR(255) DEFAULT NULL COMMENT 'IP address / hostname of remote node' AFTER object_name,
  DROP column address;

