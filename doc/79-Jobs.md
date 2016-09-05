<a id="Jobs"></a>Jobs
=====================

Director allows you to schedule eventually long-running tasks so that they
can run in the background. Currently this includes:

* Import runs
* Sync runs
* Housekeeping tasks
* Config rendering and deployment

This component is internally provided as a Hook. This allows other Icinga
Web 2 modules to benefit from the Job Runner by providing their very own Job
implementations.

Theory of operation
-------------------

Jobs are configured via the Web frontend. You can create multiple definitions
for the very same Job. Every single job will run with a configurable interval.
Please do not expect this to behave like a scheduler or a cron daemon. Jobs
are currently not executed in parallel. Therefore if one job takes longer, it
might have an influence on the scheduling of other jobs.

Some of you might want actions like automated config deployment not to be
executed all around the clock. That's why you have the possibility to assign
time periods to your jobs. Choose an Icinga timeperiod, the job will only be
executed within that period.

Execution methods
-----------------

Jobs are executed on CLI, basically with the `jobs` CLI command and its
available actions and options. A call to...

```sh
icingacli director jobs run
```

...would run all currently pending jobs just once. As cron jobs should not run
forever, the command terminates after this. In case you want it to keep on
running, just add `forever` (or `--forever`) to the command:

```sh
icingacli director jobs run forever
```

This could be used to run the Job Runner as a daemon, preferrably with a
systemd unit file looking as follows:

```ini
[Unit]
Description=Director Job runner

[Service]
Type=simple
ExecStart=/usr/bin/icingacli director jobs run forever
Restart=on-success
```

However, `forever` is not forever. In case Director detects that too much
memory has been used (and not freed), it terminates itself with exit code 0.
So, like the above init script, please expect the Job Runner to terminate at
any time.

Want so see more details? Add `--verbose` to get colorful log lines on STDERR.
In case the Job Runner is running with Systemd, those log lines will find its
way to your system log.

An example script is included as [contrib](../contrib/systemd/director-jobs.service) and can simply stored in `/etc/systemd/system/director-jobs.service`. To enable and start the job afterwards run:

```
systemctl enable director-jobs.service
systemctl start director-jobs.service
```

Time periods
------------

Icinga time periods can get pretty complex. You configure them with Director,
but until now it didn't have the necessity to "understand" them. This of course
changed with Time Period support in our Job Runner. Director will try to fully
"understand" periods in future, but right now it is only capable to interpret
a limited subset of timeperiod range definitions.
