ALTER TABLE icinga_user ADD COLUMN object_type ENUM('object', 'template') NOT NULL AFTER zone_id;

