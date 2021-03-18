# Caldav2ics Plugin

The **Caldav2ics** Plugin is an extension for [Grav CMS](http://github.com/getgrav/grav). It creates ICS Calendar File(s) from remote Caldav Calendar(s)

Ever thought about to use your (remote) CalDav Calendar(s) as an automatic data source for your favourite Grav Calendar Plugin ?
Searched for a Plugin that would provide this functionality in the Grav Plugins Directory and found nothing useful ?
Well, in case of 'Yes' to both questions, this is for You :-) .  
The functionality of this plugin is based on my former Wordpress Plugin [wp-caldav2ics](https://wordpress.org/plugins/wp-caldav2ics/), the Configuration is fully integrated in the [Grav Scheduler](https://learn.getgrav.org/17/advanced/scheduler).  
This way, the Generation of the ICS Calendar(s) is automated to run at predefined Intervals, you can, however, also trigger this by hand in the Admin Backend (see **TODO**).

## Installation

Installing the Caldav2ics plugin can be done in one of three ways: The GPM (Grav Package Manager) installation method lets you quickly install the plugin with a simple terminal command, the manual method lets you do so via a zip file, and the admin method lets you do so via the Admin Plugin.

### GPM Installation (Preferred)

To install the plugin via the [GPM](http://learn.getgrav.org/advanced/grav-gpm), through your system's terminal (also called the command line), navigate to the root of your Grav-installation, and enter:

    bin/gpm install caldav2ics

This will install the Caldav2ics plugin into your `/user/plugins`-directory within Grav. Its files can be found under `/your/site/grav/user/plugins/caldav2ics`.

### Manual Installation

To install the plugin manually, download the zip-version of this repository and unzip it under `/your/site/grav/user/plugins`. Then rename the folder to `caldav2ics`. You can find these files on [GitHub](https://github.com/wernerjoss/grav-plugin-caldav2ics) or via [GetGrav.org](http://getgrav.org/downloads/plugins#extras).

You should now have all the plugin files under

    /your/site/grav/user/plugins/caldav2ics
	
> NOTE: This plugin is a modular component for Grav which may require other plugins to operate, please see its [blueprints.yaml-file on GitHub](https://github.com/wernerjoss/grav-plugin-caldav2ics/blob/master/blueprints.yaml).

### Admin Plugin

If you use the Admin Plugin, you can install the plugin directly by browsing the `Plugins`-menu and clicking on the `Add` button.

## Configuration

Before configuring this plugin, you should copy the `user/plugins/caldav2ics/caldav2ics.yaml` to `user/config/plugins/caldav2ics.yaml` and only edit that copy.

Here is a sample configuration for a single remote Calendar:

```yaml
enabled: true
scheduled_jobs:
  enabled: true
  at: '15 * * * *'
  logs: logs/caldav2ics.log
calendars:
  -
    Name: 'Test Calendar'
    Url: 'https://yourcaldavserver.net/remote.php/dav/calendars/myself/testcalendar'
    User: 'myself'
    Pass: 'my#secret$password'
```

Note that if you use the Admin Plugin, a file with your configuration named caldav2ics.yaml will be saved in the `user/config/plugins/`-folder once the configuration is saved in the Admin.  
In Addition, Configuration via the Admin is much easier than by editing the config file, as this has a nice, (hopefully) self-explaining UI, so you should really use this, at least for a Start :smile:

## Usage
Once installed, you should at first enable the Grav Scheduler (if not already done) - see [the official docs](https://learn.getgrav.org/17/advanced/scheduler).  
From this point, all you need to do is correctly fill out the Form in the Admin for the caldav2ics Plugin.  
This should be rather self-explanatory, as already stated.  
It is, however, not always that easy to correctly determine the URL of remote CalDav Calendars, although most commonly used Incarnations (such as OwnCloud, NextCloud...) provide extra Links for that.

## TODO
Trigger ICS Generation via Button in the Admin