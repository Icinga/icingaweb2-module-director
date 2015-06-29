CREATE TABLE icinga_zone_inheritance (
  zone_id integer NOT NULL,
  parent_zone_id integer NOT NULL,
  weight integer DEFAULT NULL,
  PRIMARY KEY (zone_id, parent_zone_id),
  CONSTRAINT icinga_zone_inheritance_zone
  FOREIGN KEY (zone_id)
  REFERENCES icinga_zone (id)
  ON DELETE CASCADE
  ON UPDATE CASCADE,
  CONSTRAINT icinga_zone_inheritance_parent_zone
  FOREIGN KEY (parent_zone_id)
  REFERENCES icinga_zone (id)
  ON DELETE RESTRICT
  ON UPDATE CASCADE
);

CREATE UNIQUE INDEX zone_inheritance_unique_order ON icinga_zone_inheritance (zone_id, weight);
CREATE INDEX zone_inheritance_zone ON icinga_zone_inheritance (zone_id);
CREATE INDEX zone_inheritance_zone_parent ON icinga_zone_inheritance (parent_zone_id);
