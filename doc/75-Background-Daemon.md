<a id="Background-Daemon"></a>Background-Daemon
===============================================

The Icinga Director Background Daemon is available (and mandatory) since v1.7.0.
It is responsible for various background tasks, including fully automated Import,
Sync & Config Deployment Tasks.

Daemon Installation
-------------------

In case you installed Icinga Director as a package, the daemon should already
have been installed. In case you're running directly from a GIT working copy or
from a manual installation, you need to tell `systemd` about your new service.

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
