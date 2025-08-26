<a id="Jobs"></a>Jobs
=====================

The [background daemon](75-Background-Daemon.md) is responsible for running
Jobs accoring our schedule. Director allows you to schedule possibly long-
running tasks, so they can run in the background.

Currently this includes:

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

Time periods
------------

Icinga time periods can get pretty complex. You configure them with Director,
but until now it didn't have the necessity to "understand" them. This of course
changed with Time Period support in our Job Runner. Director will try to fully
"understand" periods in future, but right now it is only capable to interpret
a limited subset of timeperiod range definitions.
