# TNW_Salesforce
Integration between Magneto <> Salesforce.

Development workflow
-----------
- All development is done on the 'develop' branch
- When RC is announced and created, RC branch is created (Ex: 0.01.49.0.0) and merged into 'master'

Managing Module Versions
-----------
A.BB.CC.DD.EEEE (0.01.40.23.1234)

A - can have three values: 0, 1 or 2. current product state is 'Beta' and the value shoudl be 0 (zero). RC for Magento 1 will be 1. All Magento 2 versions with start with 2.

BB - Increased with a major release. (Example: Addition of a new feature)

CC - Increased when a smaller release is announced (Example: Enchancements to an existing feature, refactoring, etc.). Reset back to zero when a large feature is released.

DD - Increased during a release if databased changes are required. (Updates to mappings, configuraction, addition of new tables, table alterations, etc.) Reset back to zero every time we cut RC for public use.

EEEE - bumped every time when code is committed. Reset back to zero if DB change is required.

How to Install
-----------
To install module please copy files to your Magento root folder OR

### Install using modman
Modman allows you to store extension in separate folder and add it to Magento using symlinks.  
For correct installation System -> Configuration -> Developer -> Template Settings -> Allow Symlinks should be enabled.  
Please follow these steps if you haven't used modman before:

* Download modman from [here][modman_link]:
* Move downloaded file to bin folder and make it executable from everywhere: `mv /path/to/modman /usr/local/bin/modman && chmod +x /usr/local/bin/modman`
* In Magento root folter execute `modman init` to initialize modman folder

#### Install using modman from GIT

* To install module use `modman powersync clone <path-to-repo>` command from magento root
* To update module use `modman powersync update`

#### Install using modman without GIT

* To install, copy module files to `.modman/powersync` and execute `modman powersync deploy` in magento root
* To update, upload new version of module to `.modman/powersync` and execute `modman powersync deploy` in magento root

PowerSync
-----------
**PowerSync** http://powersync.biz<br />

[modman_link]: https://raw.githubusercontent.com/hws47a/modman-relative-links/master/modman