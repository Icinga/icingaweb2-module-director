<a id="baskets"></a> Importing Director Configurations with Baskets
===================================================================

Director already takes care of importing configurations for monitored objects.  This same concept
is also useful for Director's internal configuration.  *Configuration Baskets* allow you to
export, import, share and restore all or parts of your Icinga Director configuration, as many
times as you like.

Configuration baskets can save or restore the configurations for almost all internal Director
objects, such as host groups, host templates, service sets, commands, notifications, sync
rules, and much more.  Because configuration baskets are supported directly in Director, all
customizations included in your Director configuration are imported and exported properly.
Each snapshot is a persistent, serialized (JSON) representation of all involved objects at that
moment in time.

Configuration baskets allow you to:
- Back up (take a snapshot) and restore a Director configuration...
  - To be able to restore in case of a misconfiguration you have deployed
  - Copy specific objects as a static JSON file to migrate them from testing to production
- Understand problems stemming from your changes with a diff between two configurations
- Share configurations with others, either your entire environment or just specific parts such as commands
- Choose only some elements to snapshot (using a *custom selection*) in a given category such as
  a subset of Host Templates

In addition, you can script some changes with the following command:
```
# icingacli director basket [options]
```



Using Configuration Baskets
---------------------------

To create or use a configuration basket, select **Icinga Director > Configuration Baskets**.  At
the top of the new panel are options to:
- Make a completely new configuration basket with the *Create* action
- Make a new basket by importing a previously saved JSON file with the *Upload* action

At the bottom you will find the list of existing baskets and the number of snapshots in each.
Selecting a basket will take you to the tabs for editing baskets and for taking snapshots.



### Create a New Configuration Basket

To create or edit a configuration basket, give it a name, and then select whether each of the
configuration elements should appear in snapshots for that basket.  The following choices
are available for each element type:
- **Ignore:**  Do not put this element in snapshots (for instance, do not include sync rules).
- **All of them:**  Put all items of this element type in snapshots (for example, all host templates).
- **Custom Selection:**  Put only specified items of this element type in a snapshot.  You will
  have to manually mark each element on the element itself.  For instance, if you have marked host 
  templates for custom selection, then you will have to go to each of the desired host templates 
  and select the action *Add to Basket*.  This will cause those particular host templates to be 
  included in the next snapshot.



### Uploading and Editing Saved Baskets

If you or someone else has created a serialized JSON snapshot (see below), you can upload that
basket from disk.  Select the *Upload* action, give it a new name, use the file chooser to select
the JSON file, and click on the *Upload* button.  The new basket will appear in the list of
configuration baskets.

Editing a basket is simple:  Click on its name in the list of configuration baskets to edit either
the basket name or else whether and how each configuration type will appear in snapshots.



### Managing Snapshots

From the *Snapshots* panel you can create a new snapshot by clicking on the *Create Snapshot*
button.  The new snapshot should immediately appear in the table below, along with a short
summary of the included types (e.g., *2x HostTemplate*) and the time.  If no configuration types
were selected for inclusion, the summary for that row will only show a dash instead of types.

Clicking on a row summary will take you to the *Snapshot* panel for that snapshot, with the
actions
- **Show Basket:**  Edit the basket that the snapshot was created from
- **Restore:**  Requests the target Director database; clicking on the *Restore* button will begin
  the process of restoring from the snapshot.  Configuration types that are not in the snapshot
  will not be replaced.
- **Download:**  Saves the snapshot as a local JSON file.

followed by its creation date, checksum, and a list of all configured types (or custom
selections).

For each item in that list, the keywords *unchanged* or *new* will appear to the right.
Clicking on *new* will show the differences between the version in the snapshot and the
current configuration.
