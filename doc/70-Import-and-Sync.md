Import and Synchronization
==========================

Icinga Director offers very powerful mechanisms when it comes to fetching data
from external data sources.

The following examples should give you a quick idea of what you might want to
use this feature for. Please note that Import Data Sources are implemented as
hooks in Director. This means that it is absolutely possible and probably very
easy to create custom data sources for whatever kind of data you have. And you
do not need to modify the Director source code for this, you can ship your very
own importer in your very own Icinga Web 2 module.

Examples
--------

### Import Servers from MS Active Directory

#### Create a new import source

Importing data from LDAP sources is pretty easy. We use MS Active Directory
as an example source:

![Import source](screenshot/director/08_import-and-sync/081_director_import_source.png)

You must formerly have configured a corresponding LDAP resource in your Icinga Web.
Then you choose your preferred object class, you might add custom filters, a search
base should always be set.

The only tricky part here are the chosen Properties. You must know them and you
are required to fill them in, no way around this right now. Also please choose one
column as your key column.

In case you want to avoid trouble please make this the column that corresponds to
your desired object name for the objects you are going to import. Rows duplicating
this property will be considered erroneous, the Import would fail.

#### Property modifiers

Data sources like SQL databases provide very powerful modifiers themselves. With a
handcrafted query you can solve lots of data conversion problems. Sometimes this is
not possible, and some sources (like LDAP) do not even have such features.

This is where property modifiers jump in to the rescue. Your computer names are
uppercase and you hate this? Use the lowercase modifier:

![Lowercase modifier](screenshot/director/08_import-and-sync/082_director_import_modifier_lowercase.png)

You want to have the object SID as a custom variable, but the data is stored
binary in your AD? There is a dedicated modifier:

![SID modifier](screenshot/director/08_import-and-sync/083_director_import_modifier_sid.png)

You do not agree with the way Microsoft represents it's version numbers? Regular
expressions are able to fix everything:

![Regular expression modifier](screenshot/director/08_import-and-sync/084_director_import_modifier_regex.png)

#### Preview

A quick look at the preview confirms that we reached a good point, that's the data
we want:

![Import preview](screenshot/director/08_import-and-sync/085_director_import_preview.png)

#### Synchronization

The Import itself just fetches raw data, it does not yet try to modify any of your
Icinga objects. That's what the Sync rules have been designed for. This distinction
has a lot of advantages when it goes to automatic scheduling for various import and
sync jobs.

When creating a Synchronization rule, you must decide which Icinga objects you want
to work with. You could decide to use the same import source in various rules with
different filters and properties.

![Synchronization rule](screenshot/director/08_import-and-sync/086_director_sync_rule_ad_hosts.png)

For every property you must decide whether and how it should be synchronized. You
can also define custom expressions, combine multiple source fields, set custom
properties based on custom conditions and so on.

![Synchronization properties](screenshot/director/08_import-and-sync/087_director_sync_properties_ad_host.png)

Now you are all done and ready to a) launch the Import and b) trigger your synchronization
run.


