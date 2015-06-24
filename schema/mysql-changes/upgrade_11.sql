ALTER TABLE icinga_zone ADD is_global ENUM('y', 'n') NOT NULL DEFAULT 'n' AFTER object_type;
