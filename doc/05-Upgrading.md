<a id="Upgrading"></a>Upgrading
===============================

Icinga Director is very upgrade-friendly. We never had any complaint referring
data loss on upgrade. But to be on the safe side, please always [backup](#backup-first)
your database before running an upgrade.

Then drop the new version to your Icinga Web 2 module folder and you're all done.
In case you make use of the [Job Runner](79-Jobs.md), you should restart it's
service. Eventually refresh the page in your browser<sup>[[1]](#footnote1)</sup>,
and you are ready to go.

Should there any other actions be required (like [schema migrations](#schema-migrations)),
you will be told so in your frontend.

Please read more about:

* [Database Backup](#backup-first)
* [Upgrading to 1.7.x](#upgrade-to-1.7.x)
* [Upgrading to 1.6.x](#upgrade-to-1.6.x)
* [Upgrading to 1.5.x](#upgrade-to-1.5.x)
* [Upgrading to 1.4.x](#upgrade-to-1.4.x)
* [Upgrading to 1.3.0](#upgrade-to-1.3.0)
* [Upgrading to 1.2.0](#upgrade-to-1.2.0)
* [Upgrading to 1.1.0](#upgrade-to-1.1.0)
* [How to work with the latest GIT master](#git-master)
* [Database schema upgrades](#schema-migrations)
* [Job Runner restart](#restart-jobrunner)
* [Downgrading](#downgrade)

And last but not least, having a look at our [Changelog](82-Changelog.md) is
usually a good idea before applying an upgrade.

<a name="backup-first"></a>Always take a backup first
-----------------------------------------------------

All you need for backing up your Director is a snapshot of your database. Please
use the tools provided by your database backend, like `mysqldump` or `pg_dump`.
Restoring from a backup is trivial, and Director will always be able to apply
pending database migrations to an imported old database snapshot.

<a name="upgrade-to-1.7.x"></a>Upgrading to 1.7.x
-------------------------------------------------

Since v1.7.0 Icinga Director requires at least PHP 5.6. Also, this version
introduces new dependencies. Please make sure that the following Icinga Web 2
modules have been installed and enabled:

* [ipl](https://github.com/Icinga/icingaweb2-module-ipl) (>=0.3.0)
* [incubator](https://github.com/Icinga/icingaweb2-module-incubator) (>=0.4.0)
* [reactbundle](https://github.com/Icinga/icingaweb2-module-reactbundle) (>=0.7.0)

Apart from this, in case you are running 1.6.x or any GIT master since then,
all you need is to replace the Director module folder with the new one. Or to
run `git checkout v1.7.x` in case you installed Director from GIT.

As always, you'll then be prompted to apply pending Database Migrations. There
is now a new, modern (and mandatory) Background Daemon, the old (optional) Jobs
Daemon must be removed. Please check our [documentation](75-Background-Daemon.md)
for related instructions.

<a name="upgrade-to-1.6.x"></a>Upgrading to 1.6.x
-------------------------------------------------

There is nothing special to take care of. In case you are running 1.5.x or any
GIT master since then, all you need is to replace the Director module folder
with the new one. Or to run git checkout v1.6.0 in case you installed Director
from GIT.

As always, you'll then be prompted to apply pending Database Migrations.

<a name="upgrade-to-1.5.x"></a>Upgrading to 1.5.x
-------------------------------------------------

There is nothing special to take care of. In case you are running 1.4.x or any
GIT master since then, all you need is to replace the Director module folder
with the new one. Or to run git checkout v1.5.0 in case you installed Director
from GIT.

As always, you'll then be prompted to apply pending Database Migrations.

<a name="upgrade-to-1.4.x"></a>Upgrading to 1.4.x
-------------------------------------------------

Since v1.4.0 Icinga Director requires at least PHP 5.4. Apart from this, there
is nothing special to take care of. In case you are running 1.3.x or any GIT
master since then, all you need is to replace the Director module folder with
the new one. Or to run `git checkout v1.4.x` in case you installed Director
from GIT.

<a name="upgrade-to-1.3.x"></a>Upgrading to 1.3.x
-------------------------------------------------

In case you are running 1.2.0 or any GIT master since then, all you need is to
replace the Director module folder with the new one. Or to run `git checkout v1.3.x`
in case you installed Director from GIT.

When running Director since 1.1.0 or earlier on PostgreSQL, you might not yet
have the PostgreSQL crypto extension installed (Package: `postgresql-contrib`) and
enabled:

     psql -q -c "CREATE EXTENSION pgcrypto;"


<a name="upgrade-to-1.2.0"></a>Upgrading to 1.2.0
-------------------------------------------------

There is nothing special to take care of. In case you are running 1.1.0 or any
GIT master since then, all you need is to replace the Director module folder with
the new one. Or to run `git checkout v1.2.0` in case you installed Director from
GIT.

<a name="upgrade-to-1.1.0"></a>Upgrading to 1.1.0
-------------------------------------------------

There is nothing special to take care of. In case you are running 1.0.0 or any
GIT master since then, all you need is to replace the Director module folder with
the new one. Or to run `git checkout v1.1.0` in case you installed Director from
GIT.

<a name="git-master"></a>Work with the latest GIT master
--------------------------------------------------------

Icinga Director is still a very young project. Lots of changes are going on,
a lot of improvements, bug fixes and new features are still being added every
month. People living on the bleeding edge might prefer to use all of them as
soon as possible.

So here is the good news: this is no problem at all. It's absolutely legal and
encouraged to run Director as a pure GIT clone, installed as such:

```sh
ICINGAWEB_MODULES=/usr/share/icingaweb2/modules
DIRECTOR_GIT=https://github.com/Icinga/icingaweb2-module-director.git
git clone $DIRECTOR_GIT $ICINGAWEB_MODULES/director
```

Don't worry about schema upgrades. Once they made it into our GIT master there
will always be a clean upgrade path for you, no manual interaction should ever
be required. Like every human being, we are not infallible. So, while our strict
policy says that the master should never break, this might of course happen.

In that case, please [let us know](https://github.com/Icinga/icingaweb2-module-director/issues).
We'll try to fix your issue as soon as possible.

<a name="schema-migrations"></a>Database schema migrations
----------------------------------------------------------

In case there are any DB schema upgrades (and that's still often the case) this
is no problem at all. They will pop up on your Director Dashboard and can be
applied with a single click. And if your Director is deployed automatically by
and automation tool like Puppet, also schema upgrades can easily be handled
that way. Just follow the [related examples](03-Automation.md) in our documentation.

<a name="schema-migrations"></a>Manual schema migrations
----------------------------------------------------------

Please *do not* manually apply our schema migration files. We are very strict
about our connection settings, encodings and SQL modes. Client encoding MUST be
UTF-8, for MySQL and MariaDB we are using the following SQL Mode:

```sql
SET SESSION SQL_MODE='STRICT_ALL_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,
ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,ANSI_QUOTES,PIPES_AS_CONCAT,
NO_ENGINE_SUBSTITUTION';
```

Our migration files are written based on the assumption that those rules are
strictly followed, and there may be other ones in future. So please use one
of the supported migration methods either on the web or on command line and
stay away from directly interfering with the schema.

<a name="restart-jobrunner"></a>Restart the Job Runner service
--------------------------------------------------------------

The Job Runner forks it's jobs, so usually a changed code base will take effect
immediately. However, there might be (schema or code) changes involving the Runner
process itself. To be on the safe side please always restart it after an upgrade,
even when it's just a quick `git pull`:

```sh
systemctl restart director-jobs.service
```

<a name="downgrade"></a>Downgrading
-----------------------------------

Downgrading is **not supported**. Most parts of the code will even refuse to
work in case there are new fields in their database tables. Migrations are
intentionally provided for upgrades only. In case you want to travel back in
time please restore a matching former [Database Backup](#backup-first).

<a name="footnote1">[1]</a>:
Future Icinga Web 2 version will also take care of this step. We want to be
able to have the latest JavaScript and CSS for any active module to be shipped
silently without manual interaction to any connected browser within less then
15 seconds after the latest module has been installed, enabled or upgraded.
