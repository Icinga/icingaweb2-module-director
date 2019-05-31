<a id="How-it-works"></a>How it works
=====================================

This chapter wants to give you some basic understanding of how the
Director works with your Icinga installation. At least once you start
to work with satellite zones it might be worth to give this a read.


How your configuration is going to be rendered
----------------------------------------------

First of all, the Director doesn't write to `/etc/icinga2`. That's where
you keep to store your manual configuration and that's where you are
required to do the basic config tasks required to get Icinga 2 ready for
the Director.

The Director uses the Icinga 2 API to ship the configuration. It does
so by shipping full config packages, it does not deal with single
objects. This makes deployments much faster. It also makes it easier to
eventually use Director in parallel with manual configuration or
configuration shipped by other tools.

Internally, Icinga 2 manages part of its configuration in its `var/lib`
directory. This is usually to be found in `/var/lib/icinga2`. Config
packages are stored to `/var/lib/icinga2/api/packages` once shipped
through the API. So as soon as you deployed your first configuration
with the Director, there will be a new timestamped subdirectory
containing the new configuration.

Those subdirectories are called stages. You'll often see more than one
of them. When a new config is deployed, Icinga 2 tries to restart with
that new stage. In case it fails, Icinga 2 will keep running with the
former configuration. When it succeeds, it will terminate the old process
and keep running with the new configuration.

In either scenario, it writes an exit code and its startup log to the
corresponding stage directory. This allows the Director to check back
later on to fetch this information. That's why you see all those nice
startup log outputs along with your deployment history in your frontend.

The configuration in such a stage directory is structured like your
Icinga 2 config directory in `/etc`: there is a `conf.d` and a `zones.d`
subdirectory. In `zones.d` Director will create a subdirectory for each
Zone it wants to deploy config to.

Please note that those `zones.d` subdirectories are subject to config
sync. To get them syncronized to other nodes, the following must be
true for them:

* they must have a zone definition for that zone in their local config
* they must make part of your deployment endpoints zone or be a direct
  or indirect subzone of that one
* the `accept_config` setting must be `true` in their `ApiListener`
  object definition

The director does not try to create additional zones your nodes do not
know about. In a distributed environment it is essential that the
Director can ship parts of the configuration to specific zones and
other parts to a global zone. The name of its preferred global zone
is currently hardcoded to `director-global`. Please make sure that such
a zone exists on all involved nodes that should get config from the
Director in a direct or indirect way:

```icinga2
object Zone "director-global" {
  global = true
}
```

Please do not use this zone for your own configuration files.
There is a zone called `global-templates` available in default Icinga
setups that's meant for configuration files. `director-global` is reserved
for use by Icinga Director.

Zone membership handling
------------------------

Mostly you do not need to care much about Zones when working with the
Director. In case you have no Satellite node, you wouldn't even notice
their existence.

You are not required to deal with Agent Zones, as the Director does
this for you. Please refer to [Working with agents](24-Working-with-agents.md)
for related examples.

Currently the GUI does not allow you to set the zone property on single
objects. You can circumvent this through the Director's [REST API](70-REST-API.md),
with Sync rules and through the CLI. However, that shouldn't be part
of your normal workflow. So if this restriction causes trouble with what
you want to build please let us know. Explain your scenario, make us
understand what you want to achieve.

We think of this restriction being a good idea, as it makes things
easier for most people. That doesn't mean that we would refuse to change
our mind on this. At least not if you come up with a very good
reasonable use case.


Object rendering
----------------

This chapter explains where the Director renders which config object to.

* Most objects are rendered to the master zone per default
* Templates, commands and apply rules are rendered to the global zone
* Objects with a zone property are rendered to that zone, even if they
  inherited that property
* Host objects configured as an Agent are rendered to the master zone,
  as Director configures them as a Command Execution Bridge
* Agents with a zone property respect that setting
* Every command is rendered to the global zone per default

