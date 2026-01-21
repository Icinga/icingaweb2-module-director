<a id="CLI"></a>Director CLI
============================

Large parts of the Director's functionality are also available on your CLI.


Manage Objects
--------------

Use `icingacli director <type> <action>` show, create modify or delete
Icinga objects of a specific type:

| Action       | Description                           |
|--------------|---------------------------------------|
| `create`     | Create a new object                   |
| `delete`     | Delete a specific object              |
| `exists`     | Whether a specific object exists      |
| `set`        | Modify an existing objects properties |
| `show`       | Show a specific object                |


Currently the following object types are available on CLI:

* command
* endpoint
* host
* hostgroup
* notification
* service
* timeperiod
* user
* usergroup
* zone


### Create a new object

Use this command to create a new Icinga object


#### Usage

`icingacli director <type> create [<name>] [options]`


#### Options

| Option            | Description                                           |
|-------------------|-------------------------------------------------------|
| `--<key> <value>` | Provide all properties as single command line options |
| `--json`          | Otherwise provide all options as a JSON string        |


#### Examples

To create a new host you can provide all of its properties as command line
parameters:

```shell
icingacli director host create localhost \
    --imports generic-host \
    --address 127.0.0.1 \
    --vars.location 'My datacenter'
```

It would say:

    Host 'localhost' has been created

Providing structured data could become tricky that way. Therefore you are also
allowed to provide JSON formatted properties:

```shell
icingacli director host create localhost \
    --json '{ "address": "127.0.0.1", "vars": { "test": [ "one", "two" ] } }'
```

Passing JSON via STDIN is also possible:

```shell
icingacli director host create localhost --json < my-host.json
```


### Delete a specific object

Use this command to delete a single Icinga object. Just run

    icingacli director <type> delete <name>

That's it. To delete the host created before, this would read

    icingacli director host delete localhost

It will tell you whether your command succeeded:

    Host 'localhost' has been deleted


### Whether a specific object exists

Use this command to find out whether a single Icinga object exists. Just
run:

    icingacli director <type> exists <name>

So if you run...

    icingacli director host exists localhost

...it will either tell you ...

    Host 'localhost' exists

...or:
 
    Host 'localhost' does not exist

When executed from custom scripts you could also just check the exit code,
`0` means that the object exists, `1` that it doesn't.


### Modify an existing objects properties

Use this command to modify specific properties of an existing Icinga object.


#### Usage

    icingacli director <type> set <name> [options]


#### Options

| Option                     | Description                                           |
|----------------------------|-------------------------------------------------------|
| `--<key> <value>`          | Provide all properties as single command line options |
| `--append-<key> <value>`   | Appends to array values, like `imports`,              |
|                            | `groups` or `vars.system_owners`                      |
| `--remove-<key> [<value>]` | Remove a specific property, potentially only          |
|                            | when matching `value`. In case the property is an     |
|                            | array it will remove just `value` when given          |
| `--json`                   | Otherwise provide all options as a JSON string        |
| `--replace`                | Replace all object properties with the given ones     |
| `--auto-create`            | Create the object in case it does not exist           |
| `--allow-overrides`        | Set variable overrides for virtual Services           |


#### Examples

```shell
icingacli director host set localhost \
    --address 127.0.0.2 \
    --vars.location 'Somewhere else'
```

It will either tell you

    Host 'localhost' has been modified

or, when for example issued immediately a second time:

    Host 'localhost' has not been modified

Like create, this also allows you to provide JSON-formatted properties:

```shell
icingacli director host set localhost --json '{ "address": "127.0.0.2" }'
```

This command will fail in case the specified object does not exist. This is
when the `--auto-create` parameter comes in handy. Command output will tell
you whether an object has either been created or (not) modified.

With `set` you only set the specified properties and do not touch the other
ones. You could also want to completely override an object, purging all other
unspecified parameters that might already exist. Please use `--replace` if this
is the desired behaviour.


### Show a specific object

Use this command to show single objects rendered as Icinga 2 config or
in JSON format.


#### Usage

`icingacli director <type> show <name> [options]`


#### Options

| Option            | Description                                          |
|-------------------|------------------------------------------------------|
| `--resolved`      | Resolve all inherited properties and show a flat     |
|                   | object                                               |
| `--json`          | Use JSON format                                      |
| `--no-pretty`     | JSON is pretty-printed per default (for PHP >= 5.4)  |
|                   | Use this flag to enforce unformatted JSON            |
| `--no-defaults`   | Per default JSON output skips null or default values |
|                   | With this flag you will get all properties           |
| `--with-services` | For hosts only, also shows attached services         |

### Clone an existing object

Use this command to clone a specific object.

#### Usage

`icingacli director <type> clone <name> --from <original> [options]`

#### Options

| Option              | Description                                         |
|---------------------|-----------------------------------------------------|
| `--from <original>` | The name of the object you want to clone            |
| `--<key> <value>`   | Override specific properties while cloning          |
| `--replace`         | In case an object <name> already exists replace it  |
|                     | with the clone                                      |
| `--flat`            | Do no keep inherited properties but create a flat   |
|                     | object with all resolved/inherited properties       |

#### Examples

```shell
icingacli director host clone localhost2 --from localhost
```

```shell
icingacli director host clone localhost3 --from localhost --address 127.0.0.3
```


### Other interesting tasks


#### Rename objects

There is no rename command, but a simple `set` can easily accomplish this task:

    icingacli director host set localhost --object_name localhost2

Please note that it is usually absolutely no problem to rename objects with
the Director. Even renaming something essential as a template like the famous
`generic-host` will not cause any trouble. At least not unless you have other
components outside your Director depending on that template.


#### Disable an object

Objects can be disabled. That way they will still exist in your Director DB,
but they will not be part of your next deployment. Toggling the `disabled`
property is all you need:

    icingacli director host set localhost --disabled

Valid values for booleans are `y`, `n`, `1` and `0`. So to re-enable an object
you could use:

    icingacli director host set localhost --disabled n


#### Working with booleans

As we learned before, `y`, `n`, `1` and `0` are valid values for booleans. But
custom variables have no data type. And even if there is such, you could always
want to change or override this from CLI. So you usually need to provide booleans
in JSON format in case you need them in a custom variable.

There is however one exception from this rule. CLI parameters without a given
value are handled as boolean flags by the Icinga Web 2 CLI. That explains why
the example disabling an object worked without passing `y` or `1`. You could
use this also to set a custom variable to boolean `true`:

    icingacli director host set localhost --vars.some_boolean

Want to change it to false? No chance this way, you need to pass JSON:

    icingacli director host set localhost --json '{ "vars.some_boolean": false }'

This example shows the dot-notation to set a specific custom variable. If we
have had used `{ "vars": { "some_boolean": false } }`, all other custom vars
on this object would have been removed.


#### Change object types

The Icinga Director distincts between the following object types:

| Type              | Description                                                 |
|-------------------|-------------------------------------------------------------|
| `object`          | The default object type. A host, a command and similar      |
| `template`        | An Icinga template                                          |
| `apply`           | An apply rule. This allows for assign rules                 |
| `external_object` | An external object. Can be referenced and used, will not be |
|                   | deployed                                                    |

Example for creating a host template:

```sh
icingacli director host create 'Some template' \
    --object_type template \
    --check_command hostalive
```

Please take a lot of care when modifying object types, you should not do so for
a good reason. The CLI allows you to issue operations that are not allowed in the
web frontend. Do not use this unless you really understand its implications. And
remember, with great power comes great responsibility.


Import/Export Director Objects
------------------------------

Some objects are not directly related to Icinga Objects but used by the Director
to manage them. To make it easier for administrators to for example pre-fill an
empty Director Instance with Import Sources and Sync Rules, related import/export
commands come in handy.

Use `icingacli director export <type> [options]` to export objects of a specific
type:

| Type                  | Description                                     |
|-----------------------|-------------------------------------------------|
| `datafields`          | Export all DataField definitions                |
| `datalists`           | Export all DataList definitions                 |
| `hosttemplatechoices` | Export all IcingaTemplateChoiceHost definitions |
| `importsources`       | Export all ImportSource definitions             |
| `jobs`                | Export all Job definitions                      |
| `syncrules`           | Export all SyncRule definitions                 |

#### Options

| Option        | Description                                          |
|---------------|------------------------------------------------------|
| `--no-pretty` | JSON is pretty-printed per default. Use this flag to |
|               | enforce unformatted JSON                             |

Use `icingacli director import <type> < exported.json` to import objects of a
specific type:

| Type                  | Description                                     |
|-----------------------|-------------------------------------------------|
| `importsources`       | Import ImportSource definitions from STDIN      |
| `syncrules`           | Import SyncRule definitions from STDIN          |


This feature is available since v1.5.0.


Director Configuration Basket
-----------------------------

A basket contains a set of Director Configuration objects (like Templates,
Commands, Import/Sync definitions - but not single Hosts or Services). This
CLI command allows you to integrate them into your very own workflows

## Available Actions

| Action     | Description                                       |
|------------|---------------------------------------------------|
| `dump`     | JSON-dump for objects related to the given Basket |
| `list`     | List configured Baskets                           |
| `restore`  | Restore a Basket from JSON dump provided on STDIN |
| `snapshot` | Take a snapshot for the given Basket              |

### Options

| Option   | Description                                          |
|----------|------------------------------------------------------|
| `--name` | `dump` and `snapshot` require a specific object name |

Use `icingacli director basket restore < exported-basket.json` to restore objects
from a specific basket. Take a snapshot or a backup first to be on the safe side.

This feature is available since v1.6.0.


Health Check Plugin
-------------------

You can use the Director CLI as an Icinga CheckPlugin and monitor your Director
Health. This will run all or just one of the following test suites:

| Name         | Description                                                       |
|--------------|-------------------------------------------------------------------|
| `config`     | Configuration, Schema, Migrations                                 |
| `sync`       | All configured Sync Rules (pending changes are not a problem)     |
| `import`     | All configured Import Sources (pending changes are not a problem) |
| `jobs`       | All configured Jobs (ignores disabled ones)                       |
| `deployment` | Deployment Endpoint, last deployment outcome                      |

#### Usage

`icingacli director health check [options]`

#### Options

| Option               | Description                           |
|---------------------------------|------------------------------------------------------------------------|
| `--check <name>`                | Run only a specific test suite                                         |
| `--<db> <name>`                 | Use a specific Icinga Web DB resource                                  |
| `--critical_undeploy <integer>` | Use a specific value as critical for pending deploymemts. Default is 3 |
| `--critical_undeploy <integer>` | Use a specific value as warning for pending deploymemts. Default is 2  |

#### Examples

```shell
icingacli director health check
```

Example for running a check only for the configuration:

```shell
icingacli director health check --check config
```

Sample output:

```
Director configuration: 5 tests OK
[OK] Database resource 'Director DB' has been specified'
[OK] Make sure the DB schema exists
[OK] There are no pending schema migrations
[OK] Deployment endpoint is 'icinga.example.com'
[OK] There is a single un-deployed change
```

Example for running a check only for the deployments:
```shell
icingacli director health --check deployment --critical_undeploy 2 --warning_undeploy 1
```

Sample output:
```
Director Deployments: 2 tests OK, 1x CRITICAL
[OK] Deployment endpoint is 'prod-mon1.com'
[CRITICAL] There are a 2 un-deployed change
[OK] The last Deployment was successful at 23:57
```

Kickstart and schema handling
-----------------------------

The `kickstart` and the `migration` command are handled in the [automation section](03-Automation.md),
so they are skipped here.


Configuration handling
----------------------

### Render your configuration

The Director distincts between rendering and deploying your configuration.
Rendering means that Icinga 2 config will be pre-rendered and stored to the
Director DB. Nothing bad happens if you decide to render the current config
thousands of times in a loop. In case a config with the same checksum already
exists, it will store - nothing.

You can trigger config rendering by running

```shell
icingacli director config render
```

In case a new config has been created, it will tell you so:
```
New config with checksum b330febd0820493fb12921ad8f5ea42102a5c871 has been generated
```

Run it once again, and you'll see that the output changes:
```
Config with checksum b330febd0820493fb12921ad8f5ea42102a5c871 already exists
```


### Config deployment

#### Usage

`icingacli director config deploy [options]`

#### Options

| Option                     | Description                                                      |
|----------------------------|------------------------------------------------------------------|
| `--checksum <checksum>`    | Optionally deploy a specific configuration                       |
| `--force`                  | Force a deployment, even when the configuration hasn't changed   |
| `--wait <seconds>`         | Optionally wait until Icinga completed it's restart              |
| `--grace-period <seconds>` | Do not deploy if a deployment took place less than <seconds> ago |

#### Examples

You do not need to explicitly render your config before deploying it to your
Icinga 2 master node. Just trigger a deployment, it will re-render the current
config:

```shell
icingacli director config deploy 
```

The output tells you which config has been shipped:

```
Config 'b330febd0820493fb12921ad8f5ea42102a5c871' has been deployed
```

Director tries to avoid needless deployments, so in case you immediately deploy
again, the output changes:
```
Config matches active stage, nothing to do
```

You can override this by adding the `--force` parameter. It will then tell you:

```
Config matches active stage, deploying anyway
```

In case you do not want `deploy` to waste time re-rendering your config
or in case you decide to re-deploy a specific, possibly older, config
version the `deploy` command allows you to provide a specific checksum:

```shell
icingacli director config deploy --checksum b330febd0820493fb12921ad8f5ea42102a5c871
```

When using `icingacli` deployments in an automated way, and want to avoid fast
consecutive deployments, you can provide a grace period:

```shell
icingacli director config deploy --grace-period 300
```

### Deployments status
In case you want to fetch the information about the deployments status, 
you can call the following CLI command:
```shell
icingacli director config deploymentstatus
```
```json
{
    "active_configuration": {
        "stage_name": "5c65cae0-4f1b-47b4-a890-766c82681622",
        "config": "617b9cbad9e141cfc3f4cb636ec684bd60073be1",
        "activity": "4f7bc6600dd50a989f22f82d3513e561ef333363"
    }
}
```
In case there is no active stage name related to the Director, active_configuration 
is set to null.

Another possibility is to pass a list of checksums to fetch the status of 
specific deployments and (activity log) activities.
Following, you can see an example of how to do it:
```shell
icingacli director config deploymentstatus \
    --configs 617b9cbad9e141cfc3f4cb636ec684bd60073be1 \
    --activities 4f7bc6600dd50a989f22f82d3513e561ef333363
```
```json
{
    "active_configuration": {
        "stage_name": "5c65cae0-4f1b-47b4-a890-766c82681622",
        "config": "617b9cbad9e141cfc3f4cb636ec684bd60073be1",
        "activity": "4f7bc6600dd50a989f22f82d3513e561ef333363"
    },
    "configs": {
        "617b9cbad9e141cfc3f4cb636ec684bd60073be1": "active"
    },
    "activities": {
        "4f7bc6600dd50a989f22f82d3513e561ef333363": "active"
    }
}
```

You can also decide to access directly to a value inside the result JSON by 
using the `--key` param:
```shell
icingacli director config deploymentstatus \
    --configs 617b9cbad9e141cfc3f4cb636ec684bd60073be1 \
    --activities 4f7bc6600dd50a989f22f82d3513e561ef333363 \
    --key active_configuration.config
```
```
617b9cbad9e141cfc3f4cb636ec684bd60073be1
```



### Cronjob usage

You could decide to pre-render your config in the background quite often. As of
this writing this has one nice advantage. It allows the GUI to find out whether
a bunch of changes still results into the very same config. 
only one 


Run sync and import jobs
------------------------

### Import Sources

#### List available Import Sources

This shows a table with your defined Import Sources, their IDs and
current state. As triggering Imports requires an ID, this is where you
can look up the desired ID.

`icingacli director importsource list`

#### Check a given Import Source for changes

This command fetches data from the given Import Source and compares it
to the most recently imported data.

`icingacli director importsource check --id <id>`

##### Options

| Option        | Description                                             |
|---------------|---------------------------------------------------------|
| `--id <id>`   | An Import Source ID. Use the list command to figure out |
| `--benchmark` | Show timing and memory usage details                    |

#### Fetch data from a given Import Source

This command fetches data from the given Import Source and outputs them
as plain JSON

`icingacli director importsource fetch --id <id>`

##### Options

| Option        | Description                                             |
|---------------|---------------------------------------------------------|
| `--id <id>`   | An Import Source ID. Use the list command to figure out |
| `--benchmark` | Show timing and memory usage details                    |

#### Trigger an Import Run for a given Import Source

This command fetches data from the given Import Source and stores it to
the Director DB, so that the next related Sync Rule run can work with
fresh data. In case data didn't change, nothing is going to be stored.

`icingacli director importsource run --id <id>`

##### Options

| Option        | Description                                             |
|---------------|---------------------------------------------------------|
| `--id <id>`   | An Import Source ID. Use the list command to figure out |
| `--benchmark` | Show timing and memory usage details                    |

### Sync Rules

#### List defined Sync Rules

This shows a table with your defined Sync Rules, their IDs and current
state. As triggering a Sync requires an ID, this is where you can look
up the desired ID.

`icingacli director syncrule list`

#### Check a given Sync Rule for changes

This command runs a complete Sync in memory but doesn't persist eventual
changes.

`icingacli director syncrule check --id <id>`

##### Options

| Option        | Description                                        |
|---------------|----------------------------------------------------|
| `--id <id>`   | A Sync Rule ID. Use the list command to figure out |
| `--benchmark` | Show timing and memory usage details               |

#### Trigger a Sync Run for a given Sync Rule

This command builds new objects according your Sync Rule, compares them
with existing ones and persists eventual changes.

`icingacli director syncrule run --id <id>`

##### Options

| Option        | Description                                        |
|---------------|----------------------------------------------------|
| `--id <id>`   | A Sync Rule ID. Use the list command to figure out |
| `--benchmark` | Show timing and memory usage details               |


Database housekeeping
---------------------

Your database may grow over time and ask for various housekeeping tasks. You
can usually store a lot of data in your Director DB before you would even
notice a performance impact. 

Still, we started to prepare some tasks that assist with removing useless
garbage from your DB. You can show available tasks with:

    icingacli director housekeeping tasks

The output might look as follows:

```
 Housekeeping task (name)                                  | Count
-----------------------------------------------------------|-------
 Undeployed configurations (oldUndeployedConfigs)          |     3
 Unused rendered files (unusedFiles)                       |     0
 Unlinked imported row sets (unlinkedImportedRowSets)      |     0
 Unlinked imported rows (unlinkedImportedRows)             |     0
 Unlinked imported properties (unlinkedImportedProperties) |     0
```

You could run a specific task with

    icingacli director housekeeping run <taskName>

...like in:

    icingacli director housekeeping run unlinkedImportedRows

Or you could also run all of them, that's the preferred way of doing this:

    icingacli director housekeeping run ALL

Please note that some tasks once issued create work for other tasks, as
lost imported rows might appear once you remove lost row sets. So `ALL`
is usually the best choice as it runs all of them in the best order.
