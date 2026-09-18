-- SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

CREATE TABLE icinga_service_inheritance (
  service_id INT(10) UNSIGNED NOT NULL,
  parent_service_id INT(10) UNSIGNED NOT NULL,
  weight MEDIUMINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (service_id, parent_service_id),
  UNIQUE KEY unique_order (service_id, weight),
  CONSTRAINT icinga_service_inheritance_service
  FOREIGN KEY host (service_id)
  REFERENCES icinga_service (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT icinga_service_inheritance_parent_service
  FOREIGN KEY host (parent_service_id)
  REFERENCES icinga_service (id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
