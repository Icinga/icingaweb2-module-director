<a id="Changelog"></a>Changelog
===============================

1.1.0
-----

### Fixed a lot of issues and related features
* You can find issues and feature requests related to this release on our
  [roadmap](https://dev.icinga.org/versions/301)

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

