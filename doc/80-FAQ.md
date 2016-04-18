Frequently Asked Questions
==========================

I got an exception...
---------------------

This section tries to summarize well known pitfalls and their solution.

### Binary data corruption with ZF 1.12.6 and 1.12.17

When deploying your first configuration, you might get this error:

    Refusing to render the configuration, your DB layer corrupts
    binary data. You might be affected by Zend Framework bug #655

Zend Framework 1.12.16 and 1.12.17 silently [corrupt binary data](https://github.com/zendframework/zf1/issues/655). This has been [fixed](https://github.com/zendframework/zf1/pull/670) with [1.12.18](https://github.com/zendframework/zf1/releases/tag/release-1.12.18), please either upgrade or downgrade to an earlier version. Debian Stable currently ships 1.12.9, but as they backported the involved erraneous security bug their version is affected too. When you work on a RedHat-based distribution please follow [Bug 1328032](https://bugzilla.redhat.com/show_bug.cgi?id=1328032).

You could also manually fix this issue in `/usr/share/php/Zend/Db/Adapter/Pdo/Abstract.php`. Search for the `_quote` function and delete the line saying `$value = addcslashes($value, "\000\032");`. Please note that doing so would fix all problems, but re-introduce a potential security issue affecting the MSSQL and Sqlite adapters.


### Connection error when setting up the database

When setting up and validating a database connection for Director in Icinga Web 2, the following error might occur:

    SQLSTATE[HY000]: General error: 2014 Cannot execute queries while
    other unbuffered queries are active.

This happens with some PHP versions, we have not been able to figure out which ones and why. However, we found a workaround and and fixed this in Icinga Web 2. Please pull from the current Icinga Web 2 master or at least apply this fix to your installation:

https://git.icinga.org/?p=icingaweb2.git;a=commitdiff;h=ea871ea032c78f58fa43bd672b96c5a66339fcf3;js=1

You probably didn't notice this error before as in most environments the IDO for historical reasons isn't configured for UTF-8.


Connection lost to DB while....
-------------------------------

In case you are creating large configs or handling huge imports with the Director it could happen that the default conservative max package size of your MySQL server bites you. Raise `max packet size` to a reasonable value, this will usually fix this issue.


Import succeeded but nothing happened
-------------------------------------

Import and Sync are different tasks, you need to `Run` both of them. This allows us to combine multiple import sources, even it if some of them are slow or failing from time to time. It's easy to oversee those links right now, we'll fix this soon.


My Director doesn't look as good as on your screenshots
-------------------------------------------------------

Currently there is a bug in Icinga Web 2 that broke automagic cache invalidation. So when updating a module you might be forced to do SHIFT-Reload or similar in your browser. Please note that proxies in the way between you and Icinga Web 2 might currently lead to similar issues.


I want to set ... on a host object
----------------------------------

You might have realized that a couple of options are not available on concrete object implementations or apply rules. It is one of our main goals to keep daily work with Director fast and simple. Want exceptions, special configurations? No problem, but please provide dedicated templates. Still not convinced? Let us know your opinions, tell us what your are missing and what we could do better.
