<a id="Handling-custom-variables"></a>Working with custom variables
===================================================================

Custom variables are extra bits of information you attach to a host,
service or other object, things like a URL to check, a warning
threshold, or login details for a plugin. Icinga Director gives you two
ways to let your users fill these in:

* the original `Data fields` concept, tied to a specific object and now
  **deprecated**
* the newer `Custom Variables` concept, which can also hold lists and
  grouped values, works the same way on every object type, and is what
  you should use when setting things up today

Custom variables with deprecated Data fields
--------------------------------------------

Icinga Director lets you work with custom variables through the
concept of `Data fields`. If you want your users to fill in specific
custom variables, add the corresponding `fields` to your Host,
Service, Command, User or Notification template.

On any object or template, the tab that lets you assign `Data fields` is
now labelled `Fields (Deprecated)`. Existing configuration continues to
work, but new custom variables should be created using the `Custom
Variables` concept described below. See
[Migrating existing Data fields](#Migrating-existing-Data-fields) if you
already have `Data fields` in place.

Examples
--------
* Add fields for existing commands
* Allow to fill an [array of interfaces](14-Fields-example-interfaces-array.md)

Custom Variables
-----------------

The newer Custom variables support is the recommended way to add custom data to
your objects, replacing `Data fields`. Compared to `Data fields`, they can also hold lists and
grouped values instead of just plain text or numbers, they work the
same way on hosts, services, commands, users and notifications, and
they are understood by configuration baskets, the REST API and `Apply
For` rules.

### Custom Variable Types

A new `Custom Variables` menu entry is available under the Icinga
Director main menu (`director/variables`). Custom variables are
configured independently of `Data fields` and support the following
types:

| Type                  | UI label              | Description                                                                                        |
|-----------------------|------------------------|-----------------------------------------------------------------------------------------------------|
| `string`              | String                 | Plain text value                                                                                     |
| `number`              | Number                 | Numeric value                                                                                        |
| `bool`                | Boolean                | True/false value                                                                                     |
| `sensitive`           | Sensitive              | Plain text value that is masked in the value input and in read views, such as a password or token    |
| `fixed-array`         | Fixed Array            | Ordered list with a pre-defined structure; values assigned to preconfigured positions                |
| `datalist-strict`     | Data List Strict       | Only values from the chosen datalist can be assigned; can be stored as a single value or an array    |
| `datalist-non-strict` | Data List Non Strict   | Values outside the chosen datalist are also accepted; can be stored as a single value or an array    |
| `dynamic-array`       | Dynamic Array          | Uniform array where end-users can add values freely                                                  |
| `fixed-dictionary`    | Fixed Dictionary       | Key-value map with a fixed set of preconfigured keys                                                 |
| `dynamic-dictionary`  | Dynamic Dictionary     | Key-value map where each key maps to a structured sub-dictionary; keys are added by end-users        |

> Only one level of nesting is allowed. The fields of a `fixed-array`,
> `fixed-dictionary` or `dynamic-dictionary` may only be scalar (`string`,
> `number`, `bool`, `sensitive`), datalist (`datalist-strict`,
> `datalist-non-strict`), or `dynamic-array` types. A nested field can
> never itself be a `fixed-array`, `fixed-dictionary` or
> `dynamic-dictionary`, that also rules out nesting a `fixed-array`
> inside another `fixed-array`. A `dynamic-array` can be nested this
> way, but not inside another `dynamic-array`. Also, `dynamic-dictionary`
> can only be defined as a top-level property; it cannot be nested
> inside another array or dictionary.
>
> `sensitive` is not offered as the item type of a `dynamic-array` or a
> datalist. Both render their values as a plain visible list, and there
> is no way to mask individual entries in that kind of list.
>
> For `fixed-array`, all positions must be supplied on the object. None
> may be omitted.

#### Examples for each type

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

##### `bool`
A true/false flag. Useful for feature toggles and conditional check behaviour.

```
# On a host: whether the host is behind a maintenance window by default
vars.in_maintenance = false

# On a service: enable/disable SSL certificate verification
vars.ssl_verify = true

# On a command: whether to follow HTTP redirects
vars.http_onredirect = true
```

##### `sensitive`
A plain text value that gets masked wherever it's shown to a user, both in the value
input and in read views. It's stored as plain text, the same way a plain
`visibility = hidden` field was under the old `Data fields` concept, so it's not meant
as a replacement for a secrets manager, just a way to keep a value off the screen.

```
# On a host, the SNMP community string used to poll this device
vars.snmp_community = "s3cr3t-community"

# On a command, an API token needed to reach a paging service
vars.pagerduty_token = "u+abc123def456"
```

A `sensitive` field nested inside a `fixed-array` or `fixed-dictionary` is masked the
same way. Masking is scoped to that field's exact position in its own property
definition, so two unrelated properties can each have a nested field with the same
name (e.g. both defining a `credentials.password`) without one accidentally unmasking
the other.

##### `fixed-array`
An ordered list with a predefined structure. Each position has a fixed meaning configured in the property schema. The Icinga 2 config stores this as an array without keys.

```
# On a host: SSH arguments tuple [user, port, identity-file]
vars.ssh_args = ["monitoring", "22", "/etc/icinga2/ssh/id_rsa"]

# On a service: positional thresholds for a custom check [warning, critical]
vars.disk_thresholds = ["20%", "10%"]
```

##### `datalist-strict`
The value must be one of the entries in a pre-configured Director datalist. Can be stored as a single string or as an array of list values. Enforces a controlled vocabulary.

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
Similar to `datalist-strict` but free-text values outside the datalist are also accepted. Useful when the list provides common suggestions but operators occasionally need a custom entry.

```
# On a host: primary check zone (common zones come from a datalist,
# but a custom satellite zone name is also valid)
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

A `fixed-dictionary` field can itself be a `dynamic-array`:

```
# On a host: MySQL connection parameters, with an array of fallback hosts
vars.mysql = {
    host          = "db-primary.internal"
    fallback_host = ["db-replica-1.internal", "db-replica-2.internal"]
    port          = "3306"
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

### Configuring a custom variable

Go to `Custom Variables` in the Icinga Director menu and choose
`Add Custom Variable`. The form lets you configure:

* `Property Key`: the variable name (e.g. `disk_checks`), used as
  `vars.<key>` in the rendered config
* `Property Label` and `Property Description`: optional, shown in
  object forms and the apply-for hint text
* `Category`: optional, groups related properties in object forms,
  same as with `Data fields`
* `Property Type`: one of the types listed above
* `List name`: only for `datalist-strict` / `datalist-non-strict`,
  selects which Director datalist supplies the allowed values
* `Item Type`: for `dynamic-array` and for the array variant of a
  datalist type, selects whether items are scalar values or, for
  datalists, a `dynamic-array` of values

Once a `fixed-array`, `fixed-dictionary`, `dynamic-array` or
`dynamic-dictionary` property has been created, use its detail page to add
its nested structure: fixed positions for `fixed-array`, fixed keys for
`fixed-dictionary`, the single item type for `dynamic-array`, or, for
`dynamic-dictionary`, the set of sub-dictionary fields every entry holds, no
matter what key an end-user later assigns to that entry.

> Once a property is used on one or more templates, its `Property Type`,
> `Item Type` and `List name` can no longer be changed. Remove it from
> all templates first if it needs to change.

<a id="Attaching-custom-variables-to-objects-and-templates"></a>### Attaching custom variables to objects and templates

Every object type that supports custom variables (host, service, command,
user and notification) exposes a `Custom Variables` tab on its object and
template detail pages, next to the `Fields (Deprecated)` tab. Service sets
do not expose this tab yet.

Only a **template** can attach a configured property with `Add Custom
Variable`, adding it to the schema that concrete objects then fill in. A
concrete object's `Custom Variables` tab only lets you fill in or override
values for properties already attached there or inherited from one of its
imported templates; it has no `Add Custom Variable` action of its own.

* Custom variables inherited from imported templates are shown and can
  be overridden on the object itself.
* `dynamic-dictionary` values are **merged** across the inheritance
  chain rather than overwritten. The rendered config uses `+=`, so a
  child template or the object itself can add further entries without
  losing the ones defined on parent templates.

Removing an imported template can also remove a value that only got there
because of it. If an object had overridden a value it inherited from a
template, and that template is no longer imported (directly or through
another template), the leftover value is removed too, unless some other
still-imported template provides the same property. In the UI, removing a
template that would take a value down with it shows a warning naming the
affected variables and asks for confirmation before saving; the REST API
applies the same cleanup without asking. Restoring a configuration basket
that drops a property attachment applies the same cleanup as well, see
[Restoring Custom Variable Schema Changes](30-Configuration-Baskets.md#Restoring-Custom-Variables)
for that path.

<a id="Required-custom-variables"></a>### Marking a custom variable as required

Besides attaching a property and giving it a value, an attachment can be
marked `Required`. Toggle this on the `Custom Variables` tab of the
**template** where the variable was directly attached; there's no need to
remove and re-add the variable just to change its requiredness.

The flag never blocks saving the template itself, templates describe the
schema, they don't have to fill it in. It takes effect once the variable
reaches an actual host, service, command, user or notification object,
whether attached there directly or inherited.

* Saving that object's `Custom Variables` tab fails if a required
  variable has no value, whether the value comes from the object itself
  or is inherited from one of its imported templates.
* An inherited value already satisfies the check, you don't need to
  repeat it on the object.
* For `fixed-array` and `fixed-dictionary` properties, an attachment
  whose children are all empty/default (e.g. a blank fixed array) is
  treated as having no value, so the required check still triggers
  instead of silently passing.

Apply For rules
---------------

`dynamic-array` and `dynamic-dictionary` custom variables defined on a
**host template** can be used as the source of a service `Apply For`
rule, letting Director create one service per array entry or dictionary
key. See [Working with Apply For rules](15-Service-apply-for-example.md)
for a full walk-through, including the `$value$` / `$key$` syntax used to
reference the iterated value inside the apply rule.

<a id="Migrating-existing-Data-fields"></a>Migrating existing Data fields
-------------------------------------------------------------------------

Existing `Data fields` can be converted to custom variables with:

```bash
icingacli director migrate datafields --dry-run --verbose
icingacli director migrate datafields --verbose
icingacli director migrate datafields --verbose --delete   # also removes the migrated fields
```

Only fields matching **all** of the following are migrated; everything
else is skipped and reported:

* data type is one of `String`, `Number`, `Boolean`, `Array`, `Datalist`
* the field has no category
* there is no other field sharing the same variable name
* no custom variable with the same key already exists
* the field's binding on a template has no `var_filter` set, unless
  `--allow-lossy-filters` is given (see below)

| Data field type | Custom variable type |
|------------------|-----------------------|
| `DataTypeString` | `string`, or `sensitive` if the field's visibility was set to `hidden` |
| `DataTypeNumber` | `number` |
| `DataTypeBoolean` | `bool` |
| `DataTypeArray` | `dynamic-array` (string items) |
| `DataTypeDatalist` (strict / suggest strict) | `datalist-strict` |
| `DataTypeDatalist` (other) | `datalist-non-strict` |

A `DataTypeDatalist` field configured to accept multiple values (its
`data_type` setting is `array`) migrates with `item_type` set to
`dynamic-array`, so the resulting property stores a list of datalist
values instead of a single one. Every other `Datalist` field migrates
with a plain `string` item type.

Existing template assignments are carried over automatically, so
migrated variables show up already attached to the same host, service,
command, user and notification templates that used the original field.

> A field bound to a template with a `var_filter` (only applying the field
> under certain conditions) is left untouched by default, since the new
> property system has no equivalent for conditional bindings; migrating it
> would make an optional field unconditionally required. Pass
> `--allow-lossy-filters` to migrate it anyway and drop the filter, or
> `--verbose` to see which fields were skipped for this reason. With
> `--delete`, a datafield kept back this way is never removed, even without
> `--allow-lossy-filters`.

> Renaming or removing a property, migrated or not, automatically renames or removes its stored
> values by variable name. The one exception is a variable name still claimed by a Data field that
> hasn't been migrated (or was migrated without `--delete`): its values are deliberately left in
> place rather than renamed or deleted out from under that field, so migrate any colliding Data
> field first if you want a clean rename or removal.
>
> Migration stamps existing stored values with the property's UUID, so detaching the
> property from a template later correctly finds and removes them too. The one
> exception is a field left out due to a retained `var_filter` binding (see above):
> its values keep their old, UUID-less shape until you migrate that binding as well.

Configuration Baskets
---------------------

Configuration baskets capture custom variable definitions (and their
nested items) together with the templates that use them, so restoring a
basket snapshot restores both the template and the custom variable
schema it depends on. See [Restoring Custom Variable Schema
Changes](30-Configuration-Baskets.md#Restoring-Custom-Variables) for how
a restore handles values already stored under a renamed, retyped or
detached property.

> For a `datalist-strict` or `datalist-non-strict` property, only the
> datalist's name travels with the basket, not its entries. Restoring
> such a snapshot onto a target that doesn't already have a matching
> datalist creates it empty; populate the datalist there separately.

Custom variable values on an existing object can also be updated
directly through the REST API, without having to submit the whole
object. See [Custom Variables](70-REST-API.md#Custom-Variables) in the
REST API documentation for details and examples.
