-- SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

ALTER TABLE import_source ADD COLUMN key_column VARCHAR(64) NOT NULL AFTER source_name;
ALTER TABLE import_source ADD INDEX search_idx (key_column);
