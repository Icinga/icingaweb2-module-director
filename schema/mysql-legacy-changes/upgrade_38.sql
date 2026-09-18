-- SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

ALTER TABLE icinga_host
  ADD COLUMN display_name VARCHAR(255) DEFAULT NULL,
  ADD INDEX search_idx (display_name);

