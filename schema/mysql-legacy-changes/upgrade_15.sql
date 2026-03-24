-- SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

CREATE TABLE icinga_hostgroup_inheritance (
  hostgroup_id INT(10) UNSIGNED NOT NULL,
  parent_hostgroup_id INT(10) UNSIGNED NOT NULL,
  weight MEDIUMINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (hostgroup_id, parent_hostgroup_id),
  UNIQUE KEY unique_order (hostgroup_id, weight),
  CONSTRAINT icinga_hostgroup_inheritance_hostgroup
  FOREIGN KEY host (hostgroup_id)
  REFERENCES icinga_hostgroup (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_hostgroup_inheritance_parent_hostgroup
  FOREIGN KEY host (parent_hostgroup_id)
  REFERENCES icinga_hostgroup (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
