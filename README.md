# CourseBank Moodle Module

Moodle CourseBank is an integrated cloud storage solution that allows Moodle admins to push their Moodle Course Backups into the cloud for archiving and backup purposes.

Supported versions of Moodle: 2.5 to 2.9 inclusive

## Getting started with Coursebank:

Before you begin, you'll need to have an active Coursebank account. Sign up for a free account here: https://account.coursebank.biz/

## Installation

The simplest method of installing the plugin is to choose "Download ZIP" on the right hand side of the Github page. Once you've done this, unzip the Coursebank code and copy it to the admin/tool/coursebank directory within your Moodle codebase. On most modern Linux systems, this can be accomplished with:

`unzip ./moodle-tool_coursebank-master.zip
cp -r ./moodle-tool_coursebank-master <your_moodle_directory>/admin/tool/coursebank`

Once you've copied the plugin, you can finish the installation process by logging into your Moodle site as an administrator and visiting the "notifications" page:

`<your.moodle.url>/admin/index.php`

Your site should prompt you to upgrade.

## Configuration

Once the installation process is complete, you'll be prompted to fill in some configuration details. You can also find the Coursebank configuration page again at any time via the Moodle administration block:

`Site Adminstration > Plugins > Admin tools > Course Bank`

To begin with, you'll want to set the Target URL and Authentication token. These will be provided to you as part of the process of setting up your Coursebank account. If your Moodle installation is fairly straightforward, you might be able to stop there. Save your changes and navigate to the Coursebank configuration page.

At the top of the page you'll see the connection check and connection speed test buttons. These will allow you to test if you can successfully connect to the Coursebank service, and get a rough idea of what your connection speed is.

### Additional configuration options

There are a few other configuration options which aren't essential, but which you can use to further tailor your Coursebank installation to your needs.

#### Active

You can use this to enable and disable the plugin without having to uninstall it.

#### Chunk size

This setting can be used to optimise your backup transfer speed. The default of 500kB should work well for most sites. If you'd like to tweak this setting to improve backup transfer times, the connection speed test at the top of the page will provide a suggested chunk size which will work best with your site.

#### Use external cron:

By default, the Coursebank module will check for new backup files to transfer with every run of the Moodle cron. This happens every minute for most modern Moodle sites (You can learn more about the Moodle cron here: https://docs.moodle.org/29/en/Cron). If you'd like more control over when the Coursebank module checks for and transfers backups, you can enable this setting and set up your own external cron process to run the Coursebank sync. This setting is intended for more hands-on Moodle site adminstrators that like to carefully configure their Moodle environment.

#### Delete local backups:

If this setting is enabled, backup files will be removed from your server as soon as they are safely backed up to Coursebank. This setting can be enabled to free up space on your Moodle server.

#### Proxy configuration:

If your Moodle site is hosted within a network environment that requires a proxy for outbound internet access, you can configure Coursebank to work over this proxy. Your network administrator should be able to provide you with the necessary proxy configuration details.
