# Dictionary Support & Enhanced Custom Variables — Branch Summary

## Overview

This branch introduces comprehensive **custom variable** support in Icinga Director, with a focus on structured variable types (dictionaries and arrays). It extends the existing data-fields model with a new first-class `Property` concept that supports rich, nested types and brings them into configuration baskets, REST API, and apply-for rules.

---

## New Features

### 1. Custom Variables Types

A new `Custom Variables` section is available under the Icinga Director menu. Custom variables can be configured independently of data fields and support the following types:

| Type                  | Description                                                                                               |
|-----------------------|-----------------------------------------------------------------------------------------------------------|
| `string`              | Plain text value                                                                                          |
| `number`              | Numeric value                                                                                             |
| `boolean`             | True/false value                                                                                          |
| `fixed-array`         | Ordered list with a pre-defined structure; values assigned to preconfigured positions                     |
| `datalist-strict`     | Only values from the chosen datalist can be assigned, it can further be an array or string                |
| `datalist-non-strict` | Values other than the values in the chosen datalist can be assigned, it can further be an array or string |
| `dynamic-dictionary`  | Key-value map where each key maps to a structured sub-dictionary; keys are added by end-users             |
| `dynamic-array`       | Uniform array where end-users can add values freely                                                       |
| `fixed-dictionary`    | Key-value map with a fixed set of preconfigured keys                                                      |
| `dynamic-dictionary`  | Key-value map where each key maps to a structured sub-dictionary; keys are added by end-users             |

> Only one level of nesting is allowed: an inner dictionary may contain non-dictionary values only.
> For Fixed-array all the values must be provided in the object, you cannot leave out any of them.

#### Type Examples (Infrastructure Monitoring Context)

##### `string`
A plain text value. Useful for any single-value configuration parameter.

```
# On a host: the environment tag used to route alerts
vars.environment = "production"

# On a service: the URL path to probe
vars.http_uri = "/api/health"

# On a command: the path to the check plugin binary
vars.plugin_path = "/usr/lib/nagios/plugins/check_http"
```

##### `number`
A numeric value. Ideal for thresholds, timeouts, and retry counts.

```
# On a host: maximum check attempts before a hard state is raised
vars.max_check_attempts = 5

# On a service: SNMP polling interval in seconds
vars.snmp_timeout = 30

# On a notification: rate-limit delay in minutes between repeated alerts
vars.notification_interval = 60
```

##### `boolean`
A true/false flag. Useful for feature toggles and conditional check behaviour.

```
# On a host: whether the host is behind a maintenance window by default
vars.in_maintenance = false

# On a service: enable/disable SSL certificate verification
vars.ssl_verify = true

# On a command: whether to follow HTTP redirects
vars.http_onredirect = true
```

##### `fixed-array`
An ordered list with a predefined structure. Each position has a fixed meaning configured in the property schema. The Icinga 2 config stores this as an array without keys.

```
# On a host: SSH arguments tuple [user, port, identity-file]
vars.ssh_args = ["monitoring", "22", "/etc/icinga2/ssh/id_rsa"]

# On a service: positional thresholds for a custom check [warning, critical]
vars.disk_thresholds = ["20%", "10%"]
```

##### `datalist-strict`
The value must be one of the entries in a pre-configured Director data list. Can be stored as a single string or as an array of list values. Enforces a controlled vocabulary.

```
# On a host: data centre location, chosen from a "dc-locations" datalist
vars.datacenter = "eu-west-1"

# On a notification: escalation tier, chosen from a "severity-levels" datalist
vars.escalation_tier = "critical"

# As an array on a host: the teams that own this host, each value from
# a "teams" datalist
vars.owner_teams = ["networking", "platform"]
```

##### `datalist-non-strict`
Similar to `datalist-strict` but free-text values outside the data list are also accepted. Useful when the list provides common suggestions but operators occasionally need a custom entry.

```
# On a host: primary check zone — common zones come from a datalist,
# but a custom satellite zone name is also valid
vars.check_zone = "custom-satellite-eu3"

# On a service: the responsible team; defaults come from a datalist
# but ad-hoc team names are permitted
vars.responsible_team = "database-infra-temp"
```

##### `fixed-dictionary`
A dictionary with a predefined, fixed set of keys. All keys are configured in the property schema; end-users only supply values. Good for structured connection parameters where the key set never changes.

```
# On a host: MySQL connection parameters
vars.mysql = {
    host     = "db-primary.internal"
    port     = "3306"
    user     = "icinga_monitor"
    password = "s3cr3t"
    database = "app_production"
}

# On a service: SNMP v3 credentials (fixed set of keys)
vars.snmp_v3 = {
    username       = "monitoring"
    auth_protocol  = "SHA"
    auth_password  = "authpass123"
    priv_protocol  = "AES"
    priv_password  = "privpass456"
}
```

##### `dynamic-array`
A uniform array where end-users freely add values of the same type. Suitable for lists whose length varies per object.

```
# On a host: contact groups that should receive alerts for this host
vars.contact_groups = ["networking-ops", "on-call-primary", "noc"]

# On a service: expected HTTP response strings (any of which satisfies the check)
vars.http_expect = ["HTTP/1.1 200", "HTTP/1.0 200"]

# On a user: topics this user wants to receive notifications for
vars.notification_topics = ["disk", "cpu", "network"]
```

##### `dynamic-dictionary`
A dictionary where each top-level key is added freely by end-users, and the value for each key is a structured sub-dictionary with a preconfigured set of fields. Ideal for monitoring multiple similar resources on the same host (e.g. multiple disks, multiple virtual hosts).

```
# On a host: one entry per disk partition, each with threshold fields
vars.disk_checks += {
    "/" = {
        disk_partition = "/"
        disk_wfree     = "20%"
        disk_cfree     = "10%"
    }
    "/data" = {
        disk_partition = "/data"
        disk_wfree     = "15%"
        disk_cfree     = "5%"
    }
}

# On a host: one entry per virtual host to probe via HTTP
vars.http_vhosts += {
    "main-site" = {
        http_address = "www.example.com"
        http_uri     = "/"
        http_port    = "443"
        http_expect  = ["HTTP/1.1 200"]
    }
    "api" = {
        http_address = "api.example.com"
        http_uri     = "/health"
        http_port    = "443"
        http_expect  = ["HTTP/1.1 200", "HTTP/1.1 204"]
    }
}
```

---

### 2. Enhanced Custom Variables UI

- A dedicated **Custom Variables** tab is available on objects and templates.
- Users can click **Add Custom Variable** to attach configured custom variables to a template.
- Inherited custom variables from parent templates are shown and editable on objects importing those templates.
- Dynamic dictionary items are **appended** (using `+=`) instead of overwritten, so values from multiple templates are merged on the final object.

---

### 3. Apply-For Rule Support for Dictionaries and Arrays

- Apply-for rules now support **dynamic arrays** and **dynamic dictionaries**, not just flat arrays.
- Service apply rules can reference dictionary items from the host via `$value.<item>$` syntax.
- The `config` keyword in apply-for rules has been renamed to `value` for clarity.
- The IcingaService form shows nested dictionary key suggestions as a list for use in apply-for configuration.

#### Example: Apply-For using a Dynamic Array (`http_vhosts_list`)

**Scenario:** A host has a `dynamic-array` variable `http_vhosts_list` listing the virtual host addresses to probe. A service apply rule creates one HTTP check per entry.

**Host variable (on `web-server-01`):**
```
vars.http_vhosts_list = [
    "www.example.com",
    "api.example.com",
    "status.example.com"
]
```

**Service apply rule (configured in Icinga Director):**

- **Apply for:** `http_vhosts_list` (the array variable on the host)
- **Service name pattern:** `http - $item$`
- **check_command:** `http`

**Generated Icinga 2 config:**
```
apply Service "http - " for (item in host.vars.http_vhosts_list) {
    check_command = "http"
    vars.http_address = item
    vars.http_port    = 443
    vars.http_uri     = "/"
    assign where host.vars.http_vhosts_list
}
```

**Result:** Three services are created on `web-server-01`:
- `http - www.example.com` → checks `www.example.com`
- `http - api.example.com` → checks `api.example.com`
- `http - status.example.com` → checks `status.example.com`

---

#### Example: Apply-For using a Dynamic Dictionary (`disk_checks`)

**Scenario:** A host has a `dynamic-dictionary` variable `disk_checks` where each key is a disk label and the value is a structured sub-dictionary with threshold fields. A service apply rule creates one disk check per entry.

**Host variable (on `linux-server-01`, merged from templates):**
```
vars.disk_checks += {
    "root" = {
        disk_partition = "/"
        disk_wfree     = "20%"
        disk_cfree     = "10%"
    }
    "data" = {
        disk_partition = "/data"
        disk_wfree     = "15%"
        disk_cfree     = "5%"
    }
    "backup" = {
        disk_partition = "/mnt/backup"
        disk_wfree     = "10%"
        disk_cfree     = "5%"
    }
}
```

**Service apply rule (configured in Icinga Director):**

- **Apply for:** `disk_checks` (the dictionary variable on the host)
- **Service name pattern:** `disk - $key$`
- **check_command:** `disk`
- **Custom variables** referencing the sub-dictionary fields via `$value.<field>$`:

| Variable | Value |
|----------|-------|
| `disk_partitions` | `$value.disk_partition$` |
| `disk_wfree` | `$value.disk_wfree$` |
| `disk_cfree` | `$value.disk_cfree$` |

> The hint text below the Custom Variables section in the apply-rule form shows which `$value.*$` fields are available for the selected dictionary.

**Generated Icinga 2 config:**
```
apply Service "disk - " for (key => value in host.vars.disk_checks) {
    check_command    = "disk"
    vars.disk_partitions = value.disk_partition
    vars.disk_wfree      = value.disk_wfree
    vars.disk_cfree      = value.disk_cfree
    assign where host.vars.disk_checks
}
```

**Result:** Three services are created on `linux-server-01`:
- `disk - root` → checks `/` with warn=20%, crit=10%
- `disk - data` → checks `/data` with warn=15%, crit=5%
- `disk - backup` → checks `/mnt/backup` with warn=10%, crit=5%

Because `disk_checks` uses `+=`, a child template or the host itself can add more partitions without overwriting entries from the parent template. All entries from every level of the template tree are merged into the final host config.

---

### 4. REST API Endpoint for updating Object Custom Variables

A new REST API endpoint allows updating custom variables for an object directly:

```
PUT /director/<objectType>/variables?<params>
```

| Object type | Query parameters |
|-------------|-----------------|
| Host | `name=<hostname>` |
| Individual Service | `host=<hostname>&name=<servicename>` |
| Applied Service or Service Template | `name=<servicename>` |
| User | `name=<username>` |
| Notification | `name=<notificationname>` |
| Command | `name=<commandname>` |

The request body is a JSON object whose keys are variable names and values are the variable values. All standard types are accepted: strings, numbers, booleans, arrays, and nested dictionaries.
> Note: The configuration of the custom variables to be updated should exist in the database in `director_property` table before updating it via the REST API. Otherwise, the update will fail with a 404 Not Found error.

---

#### Host — `linux-server-01`

Updates disk check thresholds (dynamic-dictionary) and contact groups (dynamic-array):

```bash
curl -k -u 'icingaadmin:icinga' \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -X PUT 'http://localhost/icingaweb2/director/host/variables?name=linux-server-01' \
  -d '{
    "disk_checks": {
      "root": {
        "disk_partition": "/",
        "disk_wfree": "20%",
        "disk_cfree": "10%"
      },
      "data": {
        "disk_partition": "/data",
        "disk_wfree": "15%",
        "disk_cfree": "5%"
      }
    },
    "contact_groups": ["noc", "linux-ops"],
    "environment": "production"
  }'
```

---

#### Individual Service — `linux-server-01` / `http - www.example.com`

Updates the HTTP check parameters (string, number, boolean, dynamic-array):

```bash
curl -k -u 'icingaadmin:icinga' \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -X PUT 'http://localhost/icingaweb2/director/service/variables?host=linux-server-01&name=http%20-%20www.example.com' \
  -d '{
    "http_address": "www.example.com",
    "http_port": 443,
    "http_uri": "/health",
    "ssl_verify": true,
    "http_expect": ["HTTP/1.1 200", "HTTP/1.0 200"]
  }'
```

---

#### Service Template — `generic-http-service`

Updates default SNMP v3 credentials on a service template (fixed-dictionary):

```bash
curl -k -u 'icingaadmin:icinga' \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -X PUT 'http://localhost/icingaweb2/director/service/variables?name=generic-http-service' \
  -d '{
    "snmp_v3": {
      "username": "monitoring",
      "auth_protocol": "SHA",
      "auth_password": "newAuthPass!",
      "priv_protocol": "AES",
      "priv_password": "newPrivPass!"
    },
    "snmp_timeout": 30
  }'
```

---

#### User — `on-call-engineer`

Updates notification preferences (string, boolean, dynamic-array, fixed-dictionary):

```bash
curl -k -u 'icingaadmin:icinga' \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -X PUT 'http://localhost/icingaweb2/director/user/variables?name=on-call-engineer' \
  -d '{
    "pagerduty_key": "abc123def456",
    "phone": "+1-555-0199",
    "notify_on_recovery": true,
    "notify_on_flapping": false,
    "subscribed_services": ["disk", "http", "cpu", "memory"],
    "working_hours": {
      "timezone": "Europe/Berlin",
      "start_time": "08:00",
      "end_time": "18:00"
    }
  }'
```

---

#### Notification — `slack-host-notification`

Updates Slack webhook details and escalation recipients (string, dynamic-array, fixed-dictionary):

```bash
curl -k -u 'icingaadmin:icinga' \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -X PUT 'http://localhost/icingaweb2/director/notification/variables?name=slack-host-notification' \
  -d '{
    "slack_webhook_url": "https://hooks.slack.com/services/T.../B.../newtoken",
    "slack_channel": "#alerts-production",
    "include_graphs": true,
    "escalation_emails": ["noc@example.com", "oncall@example.com"],
    "pagerduty": {
      "integration_key": "xyz789",
      "severity": "critical",
      "component": "infrastructure",
      "group": "platform"
    }
  }'
```

---

#### Command — `check_by_ssh`

Updates default SSH connection parameters and plugin flags (string, number, boolean, fixed-array):

```bash
curl -k -u 'icingaadmin:icinga' \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -X PUT 'http://localhost/icingaweb2/director/command/variables?name=check_by_ssh' \
  -d '{
    "by_ssh_logname": "monitoring",
    "by_ssh_port": 22,
    "by_ssh_quiet": false,
    "by_ssh_arguments": ["-w", "20", "-c", "10"]
  }'
```

---

### 5. Configuration Basket Support

- Configuration baskets now include custom properties when snapshotting **templates**.
- The snapshot captures both the `<Objecttype>Template` (with `properties` UUIDs) and a new `Property` section containing the full property definitions and their nested items.
- Restoring a basket snapshot will restore the associated custom property schemas alongside the template.

---

### 6. New `DirectorProperty` Object

A new database-backed object `DirectorProperty` (`library/Director/Objects/DirectorProperty.php`) stores the custom property schema:

- UUID-based identity
- Hierarchical structure via `parent_uuid`
- Supports all value types listed above
- Linked to templates via a join table

---

## Database Changes

A new migration (`schema/mysql-migrations/upgrade_192.sql`) and updated `schema/mysql.sql` introduce:

- `director_property` — stores custom variable definitions (uuid, key_name, value_type, label, description, parent_uuid)
- `icinga_<object type>_var` — extended to support property-based custom variables , where object type is `host`, `service`, `user`, `notification` or `command`
- Foreign key and index updates to support the hierarchical property model

---

## UI & Frontend Changes

- New **Custom Variables** tab on object/template detail views (`Web/Tabs/ObjectTabs.php`)
- New form classes:
  - `CustomVariableForm` — create/edit a single custom variable on an object
  - `CustomVariablesForm` — manage all custom variables for an object
  - `DeleteCustomVariableForm` — remove a custom variable
  - `DictionaryElements/Dictionary`, `DictionaryItem`, `NestedDictionary`, `NestedDictionaryItem` — composable form elements for rendering nested dictionary structures
  - `ObjectCustomvarForm` — property selection and value assignment
- New CSS for custom variable forms, item lists, and collapsible dictionary entries (`public/css/custom-variables-form.less`, `item-list.less`, etc.)
- Icinga Web's native collapsible component is used for nested dictionary items
- `module.js` updated to suppress item count display on fieldset elements added by `CustomVariablesForm`

---

## Backend / Library Changes

| File                                                              | Change                                                                                                 |
|-------------------------------------------------------------------|--------------------------------------------------------------------------------------------------------|
| `CustomVariables.php`                                             | Extended to handle property-uuid-based lookup, `+=` operator for dictionaries, and inheritance merging |
| `CustomVariable.php`                                              | New logic for serialising/deserialising typed variables                                                |
| `CustomVariableString.php`                                        | Explicit rendering of `$variable$`-style macros                                                        |
| `IcingaObject.php`                                                | Updated to support new custom variables support                                                        |
| `IcingaService.php`                                               | Apply-for rule support for dictionaries and arrays                                                     |
| `IcingaConfig / IcingaConfigHelper`                               | Expanded config macro matching (`$value.<key>$` pattern)                                               |
| `IcingaObjectHandler.php`                                         | REST API create/update flow: object is created first, then custom variables are set                    |
| `BasketSnapshot.php` / `BasketSnapshotCustomVariableResolver.php` | Snapshot serialisation and restore for custom variables                                                |
| `ObjectController.php`                                            | Custom variables tab and CRUD actions                                                                  |
| `TemplateTree.php`                                                | Considers custom variable inheritance across template hierarchies                                      |
| `CustomVariableReferenceLoader.php`                               | New helper to load custom variable references for basket/export flows                                  |

---

## IcingaDB Custom Variable Renderer

`ProvidedHook/Icingadb/CustomVarRenderer.php` has been significantly extended to render the new structured variable types (nested dictionaries, arrays) in the IcingaDB web views.

---

## CLI Commands

| Command | Description                                                                                                                            |
|---------|----------------------------------------------------------------------------------------------------------------------------------------|
| `MigrateCommand` | Migration script to help transition existing string/number/bool data fields without data categories to the new custom properties model |
| `ExportCommand` | Updated to include custom variable export                                                                                              |

### `director migrate datafields`

Migrates existing Director data fields to the new custom properties model. Only fields that meet **all** of the following criteria are migrated:

- Data type is one of: `String`, `Number`, `Boolean`, `Array`, `Datalist`
- The field has **no category** (`category_id IS NULL`)
- The field has **no duplicate variable name** (only one field per `varname`)
- The field is **not protected** (no `visibility = hidden` setting)
- No custom property with the same `key_name` already exists

Fields that do not meet these criteria are skipped and reported when `--verbose` is used.

#### Data type mapping

| Data field type | Custom property `value_type` |
|-----------------|------------------------------|
| `DataTypeString` | `string` |
| `DataTypeNumber` | `number` |
| `DataTypeBoolean` | `boolean` |
| `DataTypeArray` | `dynamic-array` (with a `string` child item) |
| `DataTypeDatalist` (strict/suggest\_strict) | `datalist-strict` |
| `DataTypeDatalist` (other) | `datalist-non-strict` |

After creating the custom property configurations, existing template bindings (host, service, notification, command, user) are carried over to the new `icinga_<type>_property` join table.

#### Usage

```bash
# Preview what would be migrated — no DB changes are made
icingacli director migrate datafields --dry-run

# Preview with per-field detail
icingacli director migrate datafields --dry-run --verbose

# Run the migration
icingacli director migrate datafields

# Run with per-field progress output
icingacli director migrate datafields --verbose
```

#### Example dry-run output

Suppose the Director instance has eight data fields. Two are duplicates, one belongs to a category, one is a hidden/protected string, one has a type that cannot be mapped (`DataTypeSqlQuery`), and the remaining three are plain `String`, `Number`, and `Array` fields ready to migrate.

```
$ icingacli director migrate datafields --dry-run --verbose

The following datafield types and the corresponding number of datafields can be migrated:
Data type: String | count: 1
Data type: Number | count: 1
Data type: Array  | count: 1
Total datafields that can be migrated: 3

The following datafields can not be migrated as there are duplicates:
Var name: environment | count: 2
Total datafields that can not be migrated because of having duplicates: 2

The following number of datafields belong to a category and can not be migrated: 1

The following number of datafields are protected and can not be migrated: 1

The following datafield types and the corresponding number of datafields can not be migrated:
Data type: SqlQuery | count: 1
Total datafields that can not be migrated because of incompatible datatypes with new custom property support: 1

Number of datafields that can not be migrated as the custom properties with the same name already exists: 0
Migrating Data fields
Migration completed
Summary:
Total datafields migrated: 0
Total datafields skipped: 5
```

> `--dry-run` prints the summary but does **not** write anything to the database.

#### Example live migration output

Running without `--dry-run` performs the migration inside a single transaction:

```
$ icingacli director migrate datafields --verbose

Migrating Data fields
[-] Skipping migrating datafield 'environment' as there are '2' datafields with same name
[-] Skipping migrating datafield 'category_field' as it belongs to a category
[-] Skipping migrating datafield 'secret_password' as it is protected
[-] Skipping migration of datafield 'custom_sql_query' as it has an unsupported datatype 'SqlQuery'
[+] Datafield 'max_check_attempts' successfully migrated
[+] Datafield 'agent_enabled' successfully migrated
[+] Datafield 'contact_groups' successfully migrated
Migration completed
Summary:
Total datafields migrated: 3
Total datafields skipped: 5
```

After a successful run, the three fields appear as custom properties in `director_property` and their template assignments are reflected in the `icinga_<type>_property` tables.

---

## Custom Variables by Object Type

The following examples show how the new custom variable types can be applied across each supported Icinga 2 object type.

### Host

Hosts are the primary target for the new system. All variable types are fully supported, and the dynamic-dictionary merge behaviour is most relevant here (values from multiple imported templates are combined).

```
# generic-linux-host template
vars.environment       = "production"          # string
vars.max_check_retries = 3                     # number
vars.agent_enabled     = true                  # boolean
vars.ssh_args          = ["monitoring", "22"]  # fixed-array
vars.contact_groups    = ["noc", "linux-ops"]  # dynamic-array

# Per-disk monitoring (dynamic-dictionary, merged across templates)
vars.disk_checks += {
    "/" = {
        disk_partition = "/"
        disk_wfree     = "20%"
        disk_cfree     = "10%"
    }
}

# MySQL credentials for the DB host template (fixed-dictionary)
vars.mysql_conn = {
    host     = "localhost"
    port     = "3306"
    user     = "icinga"
    password = "secret"
    database = "prod"
}
```

---

### Service

Service custom variables drive check plugin arguments. Fixed types work well for connection parameters; dynamic arrays capture lists of expected strings; `string` and `number` types cover thresholds.

```
# generic-http-service template
vars.http_address = "www.example.com"       # string
vars.http_port    = 443                     # number
vars.ssl_verify   = true                    # boolean
vars.http_expect  = ["HTTP/1.1 200"]        # dynamic-array

# HTTP virtual-host probes assigned per service (dynamic-dictionary)
vars.http_vhosts += {
    "main" = {
        http_address = "www.example.com"
        http_uri     = "/"
        http_port    = "443"
    }
}

# SNMP v3 credentials for an SNMP service (fixed-dictionary)
vars.snmp_v3 = {
    username      = "monitoring"
    auth_protocol = "SHA"
    auth_password = "authpass"
    priv_protocol = "AES"
    priv_password = "privpass"
}

# SSH-based check arguments (fixed-array: [user, port, identity-file])
vars.ssh_args = ["monitoring", "22", "/etc/icinga2/ssh/id_rsa"]
```

**Apply-for rule:** A service apply rule iterates over `host.vars.disk_checks` and maps each disk entry to a service. Dictionary fields are referenced as `$value.disk_partition$`, `$value.disk_wfree$`, etc.

---

### User

User objects benefit from `string` (contact details), `boolean` (opt-in flags), `dynamic-array` (subscribed topics), and `datalist-strict` (controlled notification preferences).

```
# on-call-engineer user
vars.pagerduty_key      = "abc123def456"       # string  — PagerDuty integration key
vars.phone              = "+1-555-0100"        # string  — SMS fallback number
vars.notify_on_recovery = true                 # boolean — send recovery notifications
vars.notify_on_flapping = false                # boolean — suppress flapping alerts

# Services this user wants to receive notifications for (dynamic-array)
vars.subscribed_services = ["disk", "http", "cpu", "memory"]

# Preferred notification channels, from a "channels" datalist (datalist-strict)
vars.preferred_channel = "slack"

# Working hours window (fixed-dictionary)
vars.working_hours = {
    timezone   = "Europe/Berlin"
    start_time = "08:00"
    end_time   = "18:00"
}
```

---

### Command

Command objects use custom variables to parameterise plugin invocations. `string` and `number` types set default argument values; `boolean` toggles flags; `fixed-array` passes positional arguments; `fixed-dictionary` groups related plugin options.

```
# check_by_ssh command
vars.by_ssh_logname = "monitoring"    # string  — SSH user
vars.by_ssh_port    = 22              # number  — SSH port
vars.by_ssh_quiet   = false           # boolean — suppress SSH banner

# Positional arguments passed to the remote plugin (fixed-array)
vars.by_ssh_arguments = ["-w", "20", "-c", "10"]

# SSL/TLS options for check_http (fixed-dictionary)
vars.http_ssl_opts = {
    ssl_cert   = "/etc/ssl/certs/ca-bundle.crt"
    ssl_verify = "yes"
    min_tls    = "1.2"
}

# Environments this command is valid in (datalist-strict, dynamic-array)
vars.valid_environments = ["production", "staging"]
```

---

### Notification

Notification objects use custom variables to drive alert routing, templating, and channel selection. All scalar types apply; `fixed-dictionary` is useful for channel-specific config blocks; `dynamic-array` lists escalation recipients.

```
# slack-notification notification object
vars.slack_webhook_url  = "https://hooks.slack.com/services/T.../B.../xxx"  # string
vars.slack_channel      = "#alerts-production"                               # string
vars.notification_icon  = ":fire:"                                           # string
vars.include_graphs     = true                                               # boolean
vars.retry_count        = 3                                                  # number

# Escalation recipients (dynamic-array)
vars.escalation_emails = ["noc@example.com", "oncall@example.com"]

# PagerDuty routing block (fixed-dictionary)
vars.pagerduty = {
    integration_key = "abc123"
    severity        = "critical"
    component       = "infrastructure"
    group           = "platform"
}

# Escalation tier, from a "severity-levels" datalist (datalist-strict)
vars.escalation_tier = "P1"
```

---

## Known Limitations / Not Yet Implemented

- **No visibility control** — custom variable values (e.g. passwords) are always visible; no masking support.
- **Apply-for rules** only work with dynamic arrays and dynamic dictionaries, not fixed types.
