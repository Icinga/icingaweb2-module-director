Frequently Asked Questions
==========================

I got an exception...
---------------------

* "Refusing to render the configuration, your DB layer corrupts binary data. You might be affected by Zend Framework bug #655"

Sad but true. Zend Framework 1.12.16 and 1.12.17 silently corrupt binary data. You can either wait for 1.12.18 or downgrade to an earlier version. Debian Stable currently ships 1.12.9, but as they backported the involved erraneous security bug their version is affected too.

You could also manually fix this issue in `/usr/share/php/Zend/Db/Adapter/Pdo/Abstract.php`. Search for the `_quote` function and delete the line saying `$value = addcslashes($value, "\000\032");`. Please note that doing so would fix all problems, but re-introduce a potential security issue affecting the MSSQL and Sqlite adapters.

My Director doesn't look as good as on your screenshots
-------------------------------------------------------

Currently there is a bug in Icinga Web 2 that broke automagic cache invalidation. So when updating a module you might be forced to do SHIFT-Reload or similar in your browser. Please note that proxies in the way between you and Icinga Web 2 might currently lead to similar issues.

I want to set ... on a host object
----------------------------------

You might have realized that a couple of options are not available on concrete object implementations or apply rules. It is one of our main goal to keep daily work with Director fast and simple. Want exceptions, special configurations? No problem, but please provide dedicated templates. Still not convinced? Let us know your opinions, tell us what your are missing and what we could do better.
