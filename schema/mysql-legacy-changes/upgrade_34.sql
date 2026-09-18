-- SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

ALTER TABLE icinga_host_field MODIFY is_required ENUM('y', 'n') NOT NULL;
ALTER TABLE icinga_service_field MODIFY is_required ENUM('y', 'n') NOT NULL;
