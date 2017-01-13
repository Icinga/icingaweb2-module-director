<a id="Changelog"></a>Changelog
===============================

Please make sure to always read our [Upgrading](05-Upgrading.md) documentation
before switching to a new version.

1.3.0
-----

### Fixed issues and related features
* You can find issues and feature requests related to this release on our
  [roadmap](https://dev.icinga.com/versions/331)

### Service Sets
* You are now allowed to create sets of services and assign all of them at
  once with an apply rule
* Sets can be assigned to host templates or directly to single hosts

### Service Variable Overrides
* When switching to a host view's services tab, you'll now not only see its
  very own services, but also ones that result from an apply rule
* You can override those services custom field values for every single host
* Same goes for services belonging to Service Sets

### Apply rules
* A new "contains" operator gives more possibilities when working with arrays
* Service vars are now also offered in the apply rule form wizard

### Custom Variables and Fields
* Issues with special characters in custom variables have been fixed
* In case mandatory fields should not have been enforced, this should work
  fine right now
* Fields can now be shown based on filter rules. Example use case: show a
  `Community String` field in case `SNMPv2` has been selected, but show
  five other fields for `SNMPv3`. This allows one to build powerful little
  wizard-like forms

### Agents and Satellites
* It is now possible to set Agent and Zone settings on every single host. This
  means that you no longer need to provide dedicated Templates for Satellite
  nodes
* The proposed Agent Deployment script has been improved for Windows and Linux

### Commands
* Command arguments are now always appended when inheriting a template. This
  slightly changes the former behavior, but should mostly be what one would
  expect anyways.

### Testing
* [Testing instructions](Testing.md) have been improved
* Running the test suite has been simplified
* While we keep running our own [tests](Testing.md) on software platforms, tests
  are now also visible on Travis-CI and triggered for all pull requests

### Compatibility
* We worked around a bug in very old PHP 5.3 versions on CentOS 6

### Activity log
* You can now search and filter in the Activity log
* In case you have hundreds of thousands of changes you'll notice that pagination
  performance improve a lot
* A quick-filter allows you to see just your very own changes with a single click

### Deployment
* More performance tweaking took place. 1.2.0 was already very fast, 1.3.0 should
  beat it
* Deployment log got better at detecting files and linking them directly from the
  log output, in case any error occured

### Work related to Icinga 1.x
* Deploying to Icinga 1.x is completely unsupported. However, it works and a
  lot of effort has been put into this feature, so it should be mentioned here
* Please note that the Icinga Director has not been designed to deploy legacy
  1.x configuration. This is a sponsored feature for a larger migration project
  and has therefore been built in a very opinionated way. You shouldn't even
  try to use it. And if so, you're on your own. Nobody will help you when
  running into trouble

### Translation
* German translation is now again at 100%

### REST API
* Issues related to fetching object lists have been fixed

### Integrations
* We now hook into the [Cube](https://github.com/icinga/icingaweb2-module-cube)
  module, this gives one more possibility to benefit from our multi-edit feature
* Icinga Web 2.4 caused some minor issues for 1.2.0. It works, but an upgrade to
  Director 1.3.0 is strongly suggested

1.2.0
-----

### Fixed a lot of issues and related features
* You can find issues and feature requests related to this release on our
  [roadmap](https://dev.icinga.com/versions/310)

### Permissions and restrictions
* Permissions are now enforced. Please check your role definitions, permission
  names have changed and are now enforced everywhere
* Configuration preview, Inspect action, Deployment and others can be granted
  independently

### Auditing
* Director provides a nice activity log. Now it is also possible to additionally
  log to Syslog or File in case you want to archive all actions elsewhere. Access
  to the audit log in the Director can be controlled with a new permission

### Configuration kickstart
* Now imports also existing notification commands
* Kickstart can be re-triggered on demand at any time

### Performance
* Config rendering got a huge performance boost. In large environments we
  managed it to deploy a real-world configuration 5 times as fast as before

### Import / Sync
* Various improvements have been applied, mostly hidden small features that should
  make work easier. Better form field descriptions, more possibilities when it
  goes to syncing special fields like "imports"
* Property modifiers can now generate new modified columns at import time
* New property modifiers are available. There is a pretty flexible DNS lookup, you
  can cast to Integer or Boolean, JSON decoding and more is offered
* Datalist entries can now be imported and synchronized, this was broken in 1.1

### Configuration possibilities
* You can now define assign rules nested as deep as you want, based on all host
  and/or service properties
* It is now possible to define "assign for" constructs, looping over hashes or
  dictionaries
* Improved Icinga 2 DSL support in commands, implicit support for skip\_key
* More and more developers are contributing code. We therefore simplified the
  way to launch our unit tests and provided related documentation
* Other objects can be referred as a dropdown or similar in custom variables

### GUI and usability
* Form error handling got a lot of tweaking, eventual exceptions are caught in
  various places and presented in a readable way
* The deployment button is now easier to find
* Configuration preview has been improved and allows a full config diff even
  before deploying the configuration
* Inheritance loops are now shown in a nice way and can be resolved in the GUI
* A new hidden gem is the multiedit functionality. Press SHIFT/CTRL while
  selecting multiple hosts and modify imports, custom vars and other properties
  for all of them at once
* Errors or warnings in all historic startup logs now link directly to the
  related config file at the time being, pointing to the referred line

### Agent setup
* The Windows kickstart script got some small improvements and now enables all
  related ITL commands per default

### CLI
* You can find a few new commands, with the ability to list or fetch all hosts
  at once in various ways being the most prominent one

### Related modules
* There are now more additional modules implementing Director Hooks. AWS import
  for EC2 instances, ELBs and Autoscaling Groups. File import for CSV, JSON,
  YAML and XML. We heard from various successful Import source implementations
  in custom projects and would love to see more of those being publicly available!

1.1.0
-----

### Fixed a lot of issues and related features
* You can find issues and feature requests related to this release on our
  [roadmap](https://dev.icinga.com/versions/301)

### Icinga Agent handling
* A lot of effort has been put into making config deployment easier for
  environments with lots of Icinga Agents
* Related bugs have been fixed, the generated configuration should now work fine
  in distributed environments
* A customized Powershell Script for automatic Windows Agent setup is provided

### Apply Rules
* It's now possible to work with apply rules in various places

### Notifications
* All components required to deploy notifications are now available. ENV for
  commands is still missing, however it's pretty easy to work around this

### Automation
* Job Scheduler and Job Runner have been introduced. Import, Sync, Deploy and
  run Housekeeping in the background with full control and feedback in the GUI
* There is a new intelligent `purge` option allowing one to purge only those
  objects that vanished at involved Import Sources between multiple Import and
  Sync Runs.

### Data Types
* Booleans, Integers and Arrays are now first-class citizens when dealing with
  custom variables
