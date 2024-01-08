<a id="Automation"></a>Automation - Configuration management
============================================================

Director has been designed to work in distributed environments. In case
you're using tools like Puppet, Ansible, Salt (R)?ex or similar, this
chapter is what you're looking for!

Generic hints
-------------

Director keeps all of its configuration in a relational database. So,
all you need to tell them is how it can reach and access that db. In case
you already rolled out Icinga Web 2 you should already be used to handle
resource definitions.

The Director needs a `database resource`, and your RDBMS must either by
MySQL, MariaDB or PostgreSQL. This is how such a resource could look like
in your `/etc/icingaweb2/resources.ini`:

```ini
[Director DB]
type = "db"
db = "mysql"
host = "localhost"
dbname = "director"
username = "director"
password = "***"
charset = "utf8"
```

Please note that the charset is required and MUST be `utf8`.

Next you need to tell the Director to use this database resource. Create
its `config.ini` with the only required setting:

```ini
[db]
resource = "Director DB"
```

Hint: `/etc/icingaweb2/modules/director/config.ini` is usually the full
path to this config file.

#### Schema creation and migrations

You do not need to manually care about creating the schema and to migrate
it for newer versions. Just `grant` the configured user all permissions on
his database.

On CLI then please run:

    icingacli director migration run --verbose

You should run this command after each upgrade, or you could also run it
at a regular interval. Please have a look at...

    icingacli director migration pending --verbose

...in case you are looking for an idempotent way of managing the schema.
Use `--help` to learn more about those commands.

If you have any good reason for doing so and feel experienced enough you
can of course also manage the schema on your own. All required files are
to be found in our `schema` directories.


Deploy Icinga Director with Puppet
----------------------------------

Drop the director source repository to a directory named `director` in
one of your `module_path`'s and enable the module as you did with all the
others.

Deploy the mentioned database resource and `config.ini`. Director could
now be configured and kick-started via the web frontend. But you are here
for automation, so please read on.

### Handle schema migrations

It doesn't matter whether you already have a schema, did a fresh install
or just an upgrade. Migrations are as easy as defining:

    exec { 'Icinga Director DB migration':
        path    => '/usr/local/bin:/usr/bin:/bin',
        command => 'icingacli director migration run',
        onlyif  => 'icingacli director migration pending',
    }

Hint: please do not travel back in time, schema downgrades are not
supported.

### Kickstart an empty Director database

The Director kickstart wizard helps you with setting up a connection to
Icinga2 master node, import its endpoint and zone definition and it also
syncs already configured command definitions. But this wizard is not only
available through the web frontend, you can perfectly trigger it in an
idempotent way with Puppet:

    exec { 'Icinga Director Kickstart':
        path    => '/usr/local/bin:/usr/bin:/bin',
        command => 'icingacli director kickstart run',
        onlyif  => 'icingacli director kickstart required',
        require => Exec['Icinga Director DB migration'],
     }

Nothing will happen so far. Kickstart will not do anything unless you
drop a `kickstart.ini` allowing the CLI kickstart helper to do so:

```ini
[config]
endpoint = icinga-master
; host = 127.0.0.1
; port = 5665
username = director
password = ***
```

Usually `/etc/icingaweb2/modules/director/kickstart.ini` should be the
full path to this file. `endpoint` (master certificate name), `username`
and `password` (fitting an already configured `ApiUser`) are required.
`host` can be a resolvable hostname or an IP address. `port` is 5665 per
default in case none is given. And of course your Icinga2 installation
needs to have a corresponding `ApiListener` (look at your enabled
features) listening at the given port.

You can run the `kickstart` from the CLI if you don't use a tool for
automation.

    icingacli director kickstart run

You can rerun the kickstart if you have to reimport changed local
config, even when the beforementioned check tells you you don't need to.
Or you could use the import/synchronisation features of Director.
