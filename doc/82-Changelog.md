<a id="Changelog"></a>Changelog
===============================

Please make sure to always read our [Upgrading](05-Upgrading.md) documentation
before switching to a new version.

1.7.0 (unreleased)
------------------
### Fixed issues
* You can find issues and feature requests related to this release on our
  [roadmap](https://github.com/Icinga/icingaweb2-module-director/milestone/18?closed=1)

### Import and Sync
* FEATURE: When fetching invalid data, Import refers erroneous rows (#1741)
* FEATURE: Sync now offers a preview, showing what would happen (#1754)
* FEATURE: ParseURL property modifier has been added (#1746)

### CLI
* FEATURE: Service Groups are now available on CLI (#1745)

### Icinga Configuration
* FIX: Allow to render single configuration files larger than 16MB (#1787)

1.6.2
-----
### Fixed issues
* You can find issues and feature requests related to this release on our
  [roadmap](https://github.com/Icinga/icingaweb2-module-director/milestone/20?closed=1)

### Icinga Configuration
* FIX: rendering for Service Sets on single Hosts has been fixed (#1788, #1789)

1.6.1
-----
### Fixed issues
* You can find issues and feature requests related to this release on our
  [roadmap](https://github.com/Icinga/icingaweb2-module-director/milestone/19?closed=1)

### User Interface
* FIX: restoring a basket fails when there is only one configured DB (#1716)
* FIX: creating a new Basket with a "Custom Selection" failed with an error (#1733)
* FIX: some new reserved keywords are now escaped correctly (#1765)
* FIX: correctly render NOT used in apply rules (fixes #1777)
* FIX: Activity Log used to ignore Host filters (#1613)
* FIX: Basket failed to restore depending on PHP version (#1782)
* FIX: Loop detection works again (#1631)
* FIX: Snapshots for Baskets with Dependencies are now possible (#1739)
* FIX: Commands snapshots now carry fields in your Basket (#1747)
* FIX: Cloning services from one Set to another one no longer fails (#1758)
* FIX: Blacklisting a Service from a Set on a Host Template is now possible (#1707)
* FIX: Services from a Set assigned to a single Host can be blacklisted (#1616)
* FEATURE: Add TimePeriod support to Configuration Baskets (#1735)
* FEATURE: RO users could want to see where a configured service originated (#1785)
* FEATURE: introduce director/serviceapplyrules REST API endpoint (#1755)

### REST API
* FIX: Self Service API now ships optional Service User parameter (#1297)

### DB Schema
* FIX: it wasn't possible to use the same custom var in multiple notification
  definitions on PostgreSQL (#1762)

### Icinga Configuration
* FIX: escape newly introduced Icinga 2 keywords (#1765)

1.6.0
-----
### Fixed issues
* You can find issues and feature requests related to this release on our
  [roadmap](https://github.com/Icinga/icingaweb2-module-director/milestone/15?closed=1)

### User Interface
* FIX: link startup log warning even for non-standard package names (#1633)
* FIX: searching for fields assigned to a template was broken (#1670)
* FIX: changing an argument type from String to DSL didn't work (#1640)
* FIX: incorrect links from template-tree to non-template commands (#1544)
* FIX: drop useless object-type field for Time Periods (#788)
* FIX: clean up naming for some tabs (#1312)
* FIX: "remove" now removes the correct Service Set on a Host (#1619)
* FIX: do not fail when "inspecting" a pending service (#1641)
* FIX: a problem when selecting multiple host has been fixed (#1647)
* FIX: allow to remove renamed Service Sets (#1664)
* FIX: resolved a side-effect triggered by hooked Custom Fields (#1667)
* FIX: config diff URL behavior has been corrected (#1704)
* FEATURE: allow to filter templates by usage (#1339)
* FEATURE: allow to show SQL used for template tables
* FEATURE: allow to defined Service Groups for Set members and for Services
  assigned to Host Templates (#619)
* FEATURE: it's now possible to choose another target Service Set when cloning
  a member service (#886)
* FEATURE: Configuration Baskets with snapshot/import/export capabilities (#1630)
* FEATURE: Allow to clone a Service from one Set to another one (#886)
* FEATURE: form descriptions are now shown directly below the field, reverting
  a change from v1.4.0 (#1510)
* FEATURE: show sub-sets in Config Preview (#1623)
* FEATURE: show live Health-Check in the frontend (#1669)

### Import and Sync
* FIX: Core Api imports flapping only for 2.8+ (#1652) 
* FEATURE: new Property Modifier allows to extract specific Array values (#473)

### CLI
* FIX: Director Health Check no longer warns about no Imports/Syncs/Jobs (#1607)
* FEATURE: It's now possible to dump data as fetched by an Import Source (#1626)
* FEATURE: CLI implementation for Configuration Basket features (#1630)
* FEATURE: allow to append to or remove from array properties (#1666)

### Icinga Configuration
* FIX: rendering of disabled objects containing `*/` has been fixed (#1263)
* FEATURE: support for Timeperiod include/exclude (#1639)
* FEATURE: improve legacy v1.x configuration rendering (#1624)

### Icinga API
* FIX: ship workarounds for issues with specific Icinga 2 versions
* FIX: clean up deployed incomplete stages lost by Icinga (#1696)
* FEATURE: allow to behave differently based on Icinga 2 version (#1695)

### Icinga Agent handling
* FEATURE: ship latest PowerShell module (#1632)
* FIX: PowerShell module runs in FIPS enforced mode (#1274)

### DB Schema
* FIX: enforce strict object_name uniqueness on commands (#1496)

### Documentation
* FEATURE: improve installation docs, fix URLs (#1656, #1655)


1.5.2
-----
### Fixed issues
* You can find issues and feature requests related to this release on our
  [roadmap](https://github.com/Icinga/icingaweb2-module-director/milestone/17?closed=1)

### Configuration rendering
* FIX: Fix compatibility with Icinga v2.6, got broken with v1.5.0 (#1614)

### REST API
* FIX: No more invalid JSON in some special circumstances (#1314)

### User Interface
* FIX: Hostgroup assignment cache has been fixed (#1574, #1618)

### DB Schema
* FIX: missing user/timeperiod constraint. We usually do not touch the schema
  in minor versions, this has been cherry-picked by accident. However, don't
  worry - the migration has been tested intensively.

1.5.1
-----
### Fixed issues
* You can find issues and feature requests related to this release on our
  [roadmap](https://github.com/Icinga/icingaweb2-module-director/milestone/16?closed=1)

### Icinga Configuration
* FIX: Switched Variable-Override related constant names broke the feature (#1601)

### User Interface
* FIX: Custom Fields attached to a Service Template have not been shown for Apply
  Rules whose name matched the Template Name (#1602)

### Import and Sync
* FIX: There was an issue with specific binary checksums on MySQL (#1556)

1.5.0
-----
### Fixed issues
* You can find issues and feature requests related to this release on our
  [roadmap](https://github.com/Icinga/icingaweb2-module-director/milestone/11?closed=1)

### Security Fixes
* FIX: users with `director/audit` permission had the possibility to inject SQL.
  Thanks to Boyd Ansems for reporting this.

### Permissions and Restrictions
* FEATURE: Showing the executed SQL query now requires the `showsql` permission
* FEATURE: Grant access to Service Set in a controlled way
* FIX: do not allow a user to create hosts he wouldn't be allowed to see #1451
* FIX: Hostgroup-based restrictions worked fine when applied, bug was buggy in
  combination with directly assigned or inherited groups (#1464)

### Icinga Configuration
* FEATURE: Add 'is false (or not set)' condition for apply rules (#1436)
* FEATURE: support flapping settings for Icinga &gt;= 2.8.0 (#330)
* FEATURE: include all itl packages in Linux Agent sample config (#1450)
* FEATURE: it's now possible to blacklist inherited or applied Services on
  single hosts (#907)
* FEATURE: timestamped startup log rendering for upcoming Icinga v2.9.0 (#1478)
* FEATURE: allow to switch between multiple Director databases (#1498)
* FEATURE: it's now possible to specify Zones for UserGroups (#1163)
* FEATURE: dependencies are no longer considered experimental

### User Interface
* FEATURE: Admins have now access to JSON download links in many places
* FEATURE: Users equipped with related permissions can toggle "Show SQL" in the GUI
* FEATURE: A Service Set can now be assigned to multiple hosts at once #1281
* FEATURE: Commands can now be filtered by usage (#1480)
* FEATURE: Show usage of Commands over templates and objects (#335)
* FEATURE: Allow horizontal size increase of Import Source DB Query field (#299)
* FEATURE: Small UI improvements like #1308
* FEATURE: Data Lists can be chosen by name in Sync rules (#1048)
* FEATURE: Inspect feature got refactored, also for Services (#264, #689, #1396, #1397)
* FEATURE: The "Modify" hook is now available for Services (#689), regardless
  of whether they have been directly assigned, inherited or applied
* FEATURE: Config preview links imports, hosts and commands to related objects (#1521)
* FEATURE: German translation has been refreshed (#1599)
* FEATURE: Apply Rule editor shows suggestions for Data-List vars (#1588)
* FIX: Don't suggest Command templates where Commands are required (#1414)
* FIX: Do not allow to delete Commands being used by other objects (#1443)
* FIX: Show 'Inspect' tab only for Endpoints with an ApiUser (#1293)
* FIX: It's now possible to specify TimePeriods for single Users #944
* FIX: Redirect after not modifying a Command Argument failed on some RHEL 7
  setups (#1512)
* FIX: click on Service Set titles no longer removes them from their host (#1560)
* FIX: Restoring objects based on compound keys has been fixed (#1597)
* FIX: Linux Agent kickstart script improved and tweaked for Icinga 2.9 (#1596)

### CLI
* FEATURE: Director Health Check Plugin (#1278)
* FEATURE: Show and trigger Import Sources (#1474)
* FEATURE: Show and trigger Sync Rules ( #1476)

### Import and Sync
* FIX: Sync is very powerful and allows for actions not available in the GUI. It
  however allowed to store invalid single Service Objects with no Host. This is
  now illegal, as it never makes any sense
* FIX: Performance boost for "purge" on older MySQL/MariaDB systems (#1475)
* FEATURE: new Property Modifier for IPs formatted as number in Excel files (#1296)
* FEATURE: new Property Modifier to url-encode values
* FEATURE: new Property Modifier: uppercase the first character of each word
* FEATURE: Kickstart Helper now also imports Event Commands (#1389)
* FEATURE: Preserve _override_servicevars on sync, even when replacing vars (#1307)

### Internals
* FIX: problems related to users working from different time zones have been
  fixed (#1270, #1332)
* FEATURE: Html/Attribute now allows boolean properties
* FEATURE: Html/Attribute allows colons in attribute names (required for SVGs)
* FEATURE: Html/Attributes can be prefixed (helps with data-*)
* FEATURE: Html/Img data:-urls are now supported
* FEATURE: ipl has been aligned with the upcoming ipl-html library
* FEATURE: Director now supports multiple Databases, allows to switch between
  them and to deploy different Config Packages. Other features based on this
  combined with related documentation will follow.

1.4.3
-----
### Fixed issues
* You can find issues and feature requests related to this release on our
  [roadmap](https://github.com/Icinga/icingaweb2-module-director/milestone/13?closed=1)

### User Interface
* FIX: Pagination used to be broken for some tables (#1273)

### Automation
* FIX: API calls changing only object relations and no "real" property resulted
  in no change at all (#1315)

1.4.2
-----
### Fixed issues
* You can find issues and feature requests related to this release on our
  [roadmap](https://github.com/Icinga/icingaweb2-module-director/milestone/13?closed=1)

### Configuration rendering
* FIX: Caching had an influence on context-specific Custom Variable rendering
  when those variables contained macros (#1257)

### Sync
* FIX: The fix for #1223 caused a regression and broke Sync for objects without
  a 'disabled' property (Sets, List members) (#1279)

1.4.1
-----
### Fixed issues
* You can find issues and feature requests related to this release on our
  [roadmap](https://github.com/Icinga/icingaweb2-module-director/milestone/12?closed=1)

### Automation
* FIX: A Sync Rule with `merge` policy used to re-enable manually disabled objects,
  even when no Sync Property `disabled` has been defined (#1223)
* FIX: Fix SQL error on PostgreSQL when inspecting Template-Choice (#1242)

### Large environments
* FIX: Director tries to raise it's memory limit for certain memory-intensive
  tasks. When granted more (but not infinite) memory however this had the effect
  that he self-restricted himself to a lower limit (#1222)

### User Interface
* FIX: Assignment filters suggested only Host properties, you have been required
  to manually type Service property names (#1207)
* FIX: Hostgroups Dashlet has been shown to users with restricted permissions,
  clicking it used to throw an error (#1237)

1.4.0
-----
### New requirements
* Icinga Director now requires PHP 5.4, support for 5.3 has been dropped
* For best performance we strongly suggest PHP 7
* When using MySQL, please consider slowly moving to at least version 5.5. One
  of our next versions will introduce official Emoji support 😱😱😱! That's not
  possible with older MySQL versions. However, 1.4.x still supports 5.1.x

### Fixed issues and related features
* You can find issues and feature requests related to this release on our
  [roadmap](https://github.com/Icinga/icingaweb2-module-director/milestone/6?closed=1)

### Dashboard and Dashlets
* Multiple new Dashboards have been introduced, their layout has been optimized
* Dashboards are made aware of newly introduced permissions and try to provide
  useful hints

### GUI, UX and Responsiveness
* Many little improvements related to mobile devices have been applied to
  Dashboards, Forms and Tables
* Search has been both improved and simplified. On most tables search spawns
  multiple columns, visible and invisible ones. Multiple search terms are
  combined in an intuitive way.
* Pagination (and search) has been added to those tables where it was still
  missing
* Some form fields referencing related objects are no longer static drop-down
  selection elements but offer suggestions as you type. This makes forms faster,
  especially in larger environments
* Navigation has been simplified, redirects after form submissions have been
  improved, more possibilities to jump to related objects have been added
* Form field description has been moved to the bottom of the screen. Might be
  easier to overlook this way, but while the former implementation was great
  for people navigating forms with their Keyboard, it was annoying for Mouse
  lovers
* Double-Click a Tab to enlarge it to full width
* Action Link bar has been unified, all links should now respect permissions
* All tables showing historic data are now grouped by day
* Property Modifiers, Sync Rules, Import Sources and more objects now offer
  description fields. This allows you to explain your colleagues all the magic
  going on behind the scenes

### Object Types
* Service Sets got quite some tweaking and bug fixing
* Groups of all kinds are now able to list their members, even when being
  applied based on filters
* Command Argument handling has been improved
* It is now possible to configure Dependencies through the Icinga Director
* Cloning Hosts now allows to also optionally clone their Services and Service
  Sets

### Templates
* The template resolver has been rewritten, is now easier to test, strict and
  faster
* Template Tree has been re-written and now also immediately shows whether a
  template is in use
* When navigating to a Template you'll notice a new usage summary page showing
  you where and how that specific template is being used. Therefor, many tables
  are now internally able to filter by inheritance

### Template Choices
* While Host- and Service-Templates are powerful building blocks, having to choose
  from a single long list might become unintuitive as this list starts growing.
  That's where Template Choices jump in. They allow you to bundle related Templates
  together and offer your users to choose amongst them in a meaningful way.

### Apply rules
* Various related issues have been addressed
* A new virtual "is true / is set" operator is now available

### Permissions and Restrictions
* It is now possible to limit access to Hosts belonging to a a list of Hostgroups.
  This works also for Hostgroups assigned through Apply Rules.
* Data List entries can be made available based on Roles

### Data Types
* SQL Query and Data List based Data Fields can now both be offered as Array fields,
  so that you can choose among specific options when filling such
* New overview tables give admins a deep look into used Custom Variables, their
  distinct values and usage
* Various issues related to Boolean values have been fixed

### Import and Synchronization
* Many issues have been addressed. Merge behavior, handling of special fields and
  data types
* Problems with Import Source deletion on PostgreSQL have been addressed
* New Property Modifiers are available. When importing single Services you might
  love the "Combine" modifier
* It is now possible to re-arrange execution order of Property Modifiers and
  Sync Properties
* Preview rendering got some improvements
* "Replace" policy on Custom Vars is now always respected
* Using VMware/vSphere/ESX? There is now a new powerful module providing a
  dedicated Import Source

### REST API
* A new Self Service API now allows to completely automate your Icinga Agent
  roll-out, especially (but not only) for Microsoft Windows
* List views are now officially available. They are very fast and stream the
  result in a memory-efficient way
* Documentation better explains how to deal with various objects, especially
  with different types of Services (!!!!!)

### Internal architecture
* Many base components have been completely replaced and re-written, based on
  and early prototype of our upcoming Icinga PHP Library (ipl)

1.3.2
-----

### Fixed issues and related features
* You can find issues and feature requests related to this release on our
  [roadmap](https://github.com/Icinga/icingaweb2-module-director/milestone/10?closed=1)

### Apply Rules
* Slashes in Apply Rules have not been correctly escaped
* Services applied based on Arrays (contains) did not show up in the Hosts
  Services list, and therefor it was not possible to override their vars
* Some magic has been introduced to detect numbers in apply rules - not perfect
  yet

### Host Groups
* It has not been possible to modify Host Groups without defining an apply rule
* Hostgroups have not been sorted
* It is now legal to have `external` HostGroup objects

### Rendered Config
* Custom Endpoint objects are now rendered to their parent zone
* (Rendering) issues with the `in` operator have been fixed
* You are now allowed to put Notifications into specific Zones

### Usability and UI
* Selecting multiple hosts at once and deleting them had no effect
* Documentation got some little improvements
* German translation has been refreshed
* Header alignment has been improved
* Escaping issues with the Inspect feature have been addressed

### Kickstart

* Kickstart is more robust and now able to deal with renamed Icinga Masters and
  more

### CLI
* It is not possible to list and show Service Sets on the CLI

### Import and Sync
* Synchronizing Data List entries caused problems
* A new Import Modifier has been added to deal with LConf specialities
* Issues with special characters like spaces used in column names shipped by
  Import Sources have been addressed
* A new Property Modifier allows to filter Arrays based on wildcards or regular
  expressions
* A new Property Modifier allowing to "Combine multiple properties" has been
  introduced. It's main purpose is to provide reliable unique keys when importing
  single service objects.
* A new warning hint informs you in case you created a Sync Rule without related
  properties
* Synchronization filters failed when built with columns not used in any property
  mapping

### Auditing
* The audit log now also carries IP address and username

### Generic bug fixes
* Fixed erraneous loop detection under certain (rare) conditions
* Various issues with PHP 5.3 have been fixed
* Combination of multiple table filters might have failed (in very rare conditions)

1.3.1
-----

### Fixed issues and related features
* You can find issues and feature requests related to this release on our
  [roadmap](https://github.com/Icinga/icingaweb2-module-director/milestone/8?closed=1)

### Service Sets
* Various little issues have been fixed. You can now remove Sets from hosts,
  even when being empty. Services from Sets assigned to parents or via apply
  rule are now shown for every single host, and their custom vars can be
  overridden at a single host level
* Sets assigned to single hosts have been shown, variable overrides have been
  offered - but rendering did not include the Director-generated template
  necessary to really put them into place. This has been fixed

### Usability
* A nasty bug hindered fields inherited from Commands from being shown ad a
  Service level - works fine right now
* There is now a pagination for Zones
* Multiedit no longer showed custom fields, now it works again as it should

### Rendering
* Disabling a host now also disables rendering of related objects (Endpoint,
  Zone) for hosts using the Icinga Agent

### REST API
* Ticket creation through the REST API has been broken, is now fixed

### Performance, Internals
* A data encoding inconsistency slowed down apply rule editing where a lot of
  host custom vars exists
* Some internal changes have been made to make parts of the code easier to be
  used by other modules

1.3.0
-----

### Fixed issues and related features
* You can find issues and feature requests related to this release on our
  [roadmap](https://github.com/Icinga/icingaweb2-module-director/milestone/7?closed=1)

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
  wizard-like forms like shown [here](16-Fields-example-SNMP.md)

### Agents and Satellites
* It is now possible to set Agent and Zone settings on every single host. This
  means that you no longer need to provide dedicated Templates for Satellite
  nodes
* The proposed Agent Deployment script has been improved for Windows and Linux
* Infrastructure management got a dedicated dashboard
* Kickstart Wizard helps when working with Satellites. This has formerly been
  a hidden, now it can be accessed through the Infrastructure dashboard

### Commands
* Command arguments are now always appended when inheriting a template. This
  slightly changes the former behavior, but should mostly be what one would
  expect anyways.

### Testing
* [Testing instructions](93-Testing.md) have been improved
* Running the test suite has been simplified
* While we keep running our own [tests](93-Testing.md) on software platforms, tests
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
  [roadmap](https://github.com/Icinga/icingaweb2-module-director/milestone/5?closed=1)

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
  [roadmap](https://github.com/Icinga/icingaweb2-module-director/milestone/4?closed=1)

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
