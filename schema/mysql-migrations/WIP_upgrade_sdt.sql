CREATE TABLE icinga_scheduled_downtime (
  id INT(10) UNSIGNED AUTO_INCREMENT NOT NULL,
  object_name VARCHAR(255) NOT NULL,
  display_name VARCHAR(255) DEFAULT NULL,
  update_method VARCHAR(64) DEFAULT NULL,
  zone_id INT(10) UNSIGNED DEFAULT NULL,
  object_type ENUM('object', 'template', 'apply_') NOT NULL,
  disabled ENUM('y', 'n') NOT NULL DEFAULT 'n',
  PRIMARY KEY (id),
  UNIQUE INDEX object_name (object_name),
  CONSTRAINT icinga_scheduled_downtime_zone
  FOREIGN KEY zone (zone_id)
  REFERENCES icinga_zone (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_scheduled_downtime_inheritance (
  scheduled_downtime_id INT(10) UNSIGNED NOT NULL,
  parent_scheduled_downtime_id INT(10) UNSIGNED NOT NULL,
  weight MEDIUMINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (scheduled_downtime_id, parent_scheduled_downtime_id),
  UNIQUE KEY unique_order (scheduled_downtime_id, weight),
  CONSTRAINT icinga_scheduled_downtime_inheritance_downtime
  FOREIGN KEY host (scheduled_downtime_id)
  REFERENCES icinga_scheduled_downtime (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_scheduled_downtime_inheritance_parent_downtime
  FOREIGN KEY host (parent_scheduled_downtime_id)
  REFERENCES icinga_scheduled_downtime (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE icinga_scheduled_downtime_range (
  scheduled_downtime_id INT(10) UNSIGNED AUTO_INCREMENT NOT NULL,
  range_key VARCHAR(255) NOT NULL COMMENT 'monday, ...',
  range_value VARCHAR(255) NOT NULL COMMENT '00:00-24:00, ...',
  range_type ENUM('include', 'exclude') NOT NULL DEFAULT 'include'
  COMMENT 'include -> ranges {}, exclude ranges_ignore {} - not yet',
  merge_behaviour ENUM('set', 'add', 'substract') NOT NULL DEFAULT 'set'
  COMMENT 'set -> = {}, add -> += {}, substract -> -= {}',
  PRIMARY KEY (scheduled_downtime_id, range_type, range_key),
  CONSTRAINT icinga_scheduled_downtime_range_downtime
  FOREIGN KEY scheduled_downtime (scheduled_downtime_id)
  REFERENCES icinga_scheduled_downtime (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- INSERT INTO director_schema_migration
--   (schema_version, migration_time)
--   VALUES (149, NOW());
