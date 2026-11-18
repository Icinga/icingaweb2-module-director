<a id="FAQ"></a>Frequently Asked Questions
==========================================

I got an exception...
---------------------

This section tries to summarize well known pitfalls and their solution.

### Binary data corruption with ZF 1.12.6 and 1.12.17

When deploying your first configuration, you might get this error:

    Refusing to render the configuration, your DB layer corrupts
    binary data. You might be affected by Zend Framework bug #655

Zend Framework 1.12.16 and 1.12.17 silently [corrupt binary data](https://github.com/zendframework/zf1/issues/655).
This has been [fixed](https://github.com/zendframework/zf1/pull/670) with
[1.12.18](https://github.com/zendframework/zf1/releases/tag/release-1.12.18),
please either upgrade or downgrade to an earlier version. Debian Stable currently
ships 1.12.9, but as they backported the involved erraneous security bug their
version has been affected too. In the meantime they also backported the fix for
the fix, so Debian should no longer show this error.

When you work on a RedHat-based distribution please follow
[Bug 1328032](https://bugzilla.redhat.com/show_bug.cgi?id=1328032). The new
release reached Fedora EPEL 6 and EPEL 7, so this should no longer be an issue
on related platforms.

You could also manually fix this issue in `/usr/share/php/Zend/Db/Adapter/Pdo/Abstract.php`.
Search for the `_quote` function and delete the line saying:

```php
$value = addcslashes($value, "\000\032");
```

Please note that doing so would fix all problems, but re-introduce a potential
security issue affecting the MSSQL and Sqlite adapters.

### Connection error when setting up the database

When setting up and validating a database connection for Director in Icinga Web 2,
the following error might occur:

    SQLSTATE[HY000]: General error: 2014 Cannot execute queries while
    other unbuffered queries are active.

This happens with some PHP versions, we have not been able to figure out which ones
and why. However, we found a workaround and and fixed this in Icinga Web 2. Please
upgrade to the latest version, the issue should then be gone.

You probably didn't notice this error before as in most environments the IDO for
historical reasons isn't configured for UTF-8.

Connection lost to DB while....
-------------------------------

In case you are creating large configs or handling huge imports with the Director
it could happen that the default conservative max package size of your MySQL
server bites you. Raise `max packet size` to a reasonable value, this willi
usually fix this issue.

Import succeeded but nothing happened
-------------------------------------

Import and Sync are different tasks, you need to `Run` both of them. This allows
us to combine multiple import sources, even it if some of them are slow or failing
from time to time. It's easy to oversee those links right now, we'll fix this soon.

My Director doesn't look as good as on your screenshots
-------------------------------------------------------

There used to be a bug in older Icinga Web 2 versions that broke automagic cache
invalidation. So when updating a module you might be forced to do SHIFT-Reload or
similar in your browser. Please note that proxies in the way between you and
Icinga Web 2 might currently lead to similar issues.

Config-Redeploy doesn't roll back the configuration in Director
---------------------------------------------------------------

This is just how this option is inteded to work. The redeploy doesn't roll back
any changes you made in the Director database but it will deploy a previous
Icinga 2 configuration that used to work.

This should buy you enough time to fix the errors in your Director configuration
without leaving you with a broken Icinga 2 configuration.

If you want to restore your Director configuration to a previous version use 
a backup (e.g. MySQL Dump) for restoring.

It might be a good idea to make MySQL Dumps more often for the Director
database than for other databases. They are small and fast and you can
use them to restore a specific point in time.
