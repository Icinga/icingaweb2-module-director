<a id="Background-Daemon"></a>Background-Daemon
===============================================

The Icinga Director Background Daemon is available (and mandatory) since v1.7.0.
It is responsible for various background tasks, including fully automated Import,
Sync & Config Deployment Tasks.

Daemon Installation
-------------------

To run the Background Daemon, you need to tell `systemd` about your new service.
First make sure that the system user `icingadirector` exists. In case it doesn't,
please create one:

```sh
useradd -r -g icingaweb2 -d /var/lib/icingadirector -s /bin/false icingadirector
install -d -o icingadirector -g icingaweb2 -m 0750 /var/lib/icingadirector
```

Then copy the provided Unit-File from our [contrib](../contrib/systemd/icinga-director.service)
to `/etc/systemd/system`, enable and start the service:

```sh
MODULE_PATH=/usr/share/icingaweb2/modules/director
cp "${MODULE_PATH}/contrib/systemd/icinga-director.service" /etc/systemd/system/
systemctl daemon-reload
```

Now your system knows about the Icinga Director Daemon. You should make sure that
it starts automatically each time your system boots:

```sh
systemctl enable icinga-director.service
```

Starting the Daemon
-------------------

You now can start the Background daemon like any other service on your Linux system:

```sh
systemctl start icinga-director.service
```

Startup Options
---------------

If you're setting up a new install, container or otherwise, `daemon run`
takes a few flags to fold your usual setup steps into a single command
instead of chaining them by hand:

```sh
icingacli director daemon run --kickstart --deploy
```

- `--kickstart` runs kickstart if it's configured and required
- `--deploy` deploys the generated config

Pass both or just one. Either one applies pending migrations first.

`--kickstart` on its own is safe to use every time you start the daemon. If
kickstart already ran, that step gets skipped and the daemon starts as
normal. If kickstart was never set up at all, the command stops with an
error instead of starting, since that means the install isn't ready yet.
`--deploy` doesn't need kickstart at all, but it does need an Endpoint with
an API user already in the Director DB. Provision that separately or run
kickstart. Without a deployment endpoint, deployment fails with a clear
error.

If the kickstart import fails partway through, its database changes are
rolled back so the next startup can retry from a clean state. If it
succeeds but the daemon gets interrupted before the config is deployed,
that's remembered too: the next time `--deploy` runs, even on its own,
it deploys the pending config even if it looks unchanged, instead of
quietly skipping it.

A kickstart run can delete Endpoint, Zone or Command objects that came from
an earlier kickstart if they're no longer on the Icinga 2 master. To keep
`--kickstart` safe as a startup command, it refuses to run if the Director
DB already has objects like that. Objects you created by hand, such as a
Command template, don't count and won't block it.

If you really do want to kickstart a DB that already has those objects, for
example while restoring a backup that's missing its API user, run
`icingacli director kickstart run` separately. That command has no such
safety check and will remove those objects without asking.

Stopping the Daemon
-------------------

You now can stop the Background daemon like any other service on your Linux system:

```sh
systemctl stop icinga-director.service
```

Getting rid of the old Job Daemon
---------------------------------

Before v1.7.0, Icinga Director shipped an optional Job Daemon. This one is no longer
needed and should be removed from your system as follows:

```sh
systemctl stop director-jobs
systemctl disable director-jobs
rm /etc/systemd/system/director-jobs.service
systemctl daemon-reload
```
