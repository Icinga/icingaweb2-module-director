ALTER TABLE icinga_zone
  DROP FOREIGN KEY icinga_zone_parent_zone,
  CHANGE parent_zone_id parent_id INT(10) UNSIGNED DEFAULT NULL,
  ADD CONSTRAINT icinga_zone_parent
    FOREIGN KEY parent_zone (parent_id)
    REFERENCES icinga_zone (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE;
