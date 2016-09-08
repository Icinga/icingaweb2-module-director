<a id="Upgrading"></a>Upgrading
===============================

Icinga Director is very upgrade-friendly. Drop the new version to your Icinga
Web 2 module folder and you're all done. In case you make use of the
[Job Runner](79-Jobs.md), you should restart it's service. Eventually refresh
the page in your browser<sup>[[1]](#footnote1)</sup>, and you are ready to go.
Should there any other actions be required, you will be told so in your frontend.

Read more about:

* [How to work with the latest GIT master](#git-master)
* [Upgrading to 1.1.0](#upgrade-to-1.1.0)
* [Database schema upgrades](#schema-migrations)
* [Job Runner restart](#restart-jobrunner)

And last but not least, having a look at our [Changelog](82-Changelog.md) is
always a good idea before an upgrade.

<a name="git-master"></a>Work with the latest GIT master
--------------------------------------------------------

Icinga Director is still a very young project. Lots of changes are going on,
a lot of improvements, bug fixes and new features are still being added every
month. People living on the bleeding edge might prefer to use all of them as
soon as possible.

So here is the good news: this is no problem at all. It's absolutely legal and
encouraged to run Director as a pure GIT clone, installed as such:

```sh
ICINGAWEB2_MODULES=/usr/share/icingaweb2/modules
DIRECTOR_GIT=https://github.com/Icinga/icingaweb2-module-director.git
git clone $DIRECTOR_GIT $ICINGAWEB_MODULES/director
```

Don't worry about schema upgrades. Once they made it into our GIT master there
will always be a clean upgrade path for you, no manual interaction should ever
be required. Like every human being, we are not infallible. So, while our strict
policy says that the master should never break, this might of course happen.

In that case, please [let us know](https://dev.icinga.org/projects/icingaweb2-module-director/issues)
We'll try to fix your issue as soon as possible. 

<a name="upgrade-to-1.1.0"></a>Upgrading to 1.1.0
-------------------------------------------------

There is nothing special to take care of. In case you are running 1.0.0 or any
GIT master since then, all you need is to replace the Director module folder with
the new one. Or to run `git checkout v1.1.0` in case you installed Director from
GIT.

<a name="schema-migrations"></a>Database schema migrations
----------------------------------------------------------

In case there are any DB schema upgrades (and that's still often the case) this
is no problem at all. They will pop up on your Director Dashboard and can be
applied with a single click. And if your Director is deployed automatically by
and automation tool like Puppet, also schema upgrades can easily be handled
that way. Just follow the [related examples](03-Automation.md) in our documentation.

<a name="restart-jobrunner"></a>Restart the Job Runner service
--------------------------------------------------------------

The Job Runner forks it's jobs, so usually a changed code base will take effect
immediately. However, there might be (schema or code) changes involving the Runner
process itself. To be on the safe side please always restart it after an upgrade,
even when it's just a quick `git pull`:

```sh
systemctl restart director-jobs.service
```

<a name="footnote1">[1]</a>:
Future Icinga Web 2 version will also take care of this step. We want to be
able to have the latest JavaScript and CSS for any active module to be shipped
silently without manual interaction to any connected browser within less then
15 seconds after the latest module has been installed, enabled or upgraded.
