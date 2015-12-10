ALTER TABLE icinga_service
  ADD COLUMN host_id INT(10) UNSIGNED DEFAULT NULL AFTER display_name,
  ADD UNIQUE KEY object_key (object_name, host_id),
  ADD CONSTRAINT icinga_host
    FOREIGN KEY host (host_id)
    REFERENCES icinga_host (id)
    ON DELETE SET NULL
    ON UPDATE CASCADE;

