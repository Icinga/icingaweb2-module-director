-- SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
-- SPDX-License-Identifier: GPL-3.0-or-later

ALTER TABLE director_deployment_log MODIFY startup_log MEDIUMTEXT DEFAULT NULL;
