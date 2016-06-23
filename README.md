Icinga Director
===============

Icinga Director has been designed to make Icinga 2 configuration handling easy.
It tries to target two main audiences:

* Users with the desire to completely automate their datacenter
* Sysops willing to grant their "point & click" users a lot of flexibility

What makes Icinga Director so special is the fact that it tries to target both
of them at once.

![Icinga Director](doc/screenshot/director/readme/director_main_screen.png)

Read more about Icinga Director in our [Introduction](doc/01-Introduction.md) section.
Afterwards, you should be ready for [getting started](doc/04-Getting-started.md).

Documentation
-------------

Please have a look at our [Installation instructions](doc/02-Installation.md)
and our hints for how to apply [Upgrades](doc/05-Upgrading.md). We love automation
and in case you also do so, the [Automation chapter](doc/03-Automation.md) could
be worth a read. When upgrading, you should also have a look at our [Changelog](doc/82-Changelog.md).

You could be interested in understanding how the [Director works](doc/10-How-it-works.md)
internally. [Working with agents](24-Working-with-agents.md) is a topic that
affects many Icinga administrators. Other interesting entry points might be
[Import and Synchronization](doc/70-Import-and-Sync.md), our [CLI interface](doc/60-CLI.md),
the [REST API](doc/70-REST-API.md) and last but not least our [FAQ](doc/80-FAQ.md).

A complete list of all our documentation can be found in the [doc](doc/) directory.

Addons
------

The following are to be considered community-supported modules, as they are not
supported by the Icinga Team. At least not yet. But please give them a try if
they fit your needs. They are being used in productive environments:

* [AWS - Amazon Web Services](https://github.com/Thomas-Gelf/icingaweb2-module-aws):
  provides an Import Source for Autoscaling Groups on AWS
* [File-Shipper](https://github.com/Thomas-Gelf/icingaweb2-module-fileshipper):
  allows Director to ship additional config files with manual config with it's
  deployments
* [PuppetDB](https://github.com/Thomas-Gelf/icingaweb2-module-puppetdb): provides
  an Import Source dealing with your PuppetDB
