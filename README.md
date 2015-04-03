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
There are there common ways to install module: modman, composer or just copy files.

### Install using modman
Modman allows you to store extension in separate folder and add it to Magento using symlinks.  
For correct installation `System -> Configuration -> Developer -> Template Settings -> Allow Symlinks` should be enabled.  
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

### Install using composer (best for deployment)
It's the best way for deployment with access to repository.

Download composer [here][composer_link].  
Create `composer.json` in project root with these content:  

    {
        "require": {
            "magento-hackathon/magento-composer-installer": "*",
            "technweb/salesforce":"dev-master"
        },
        "repositories":[
            {
                "type":"composer",
                "url":"http://packages.firegento.com"
            },
            {
                "type":"vcs",
                "url":"git@github.com:technweb/TNW_Salesforce.git"
            }
        ],
        "extra":
        {
            "magento-root-dir":"."
        }
    }

If you want to copy files instead of make symlinks add `"magento-deploystrategy":"copy"` to "extra" part.  
If you are not using copy strategy, please enable `System -> Configuration -> Developer -> Template Settings -> Allow Symlinks` config.  
To use any of development branch use `dev-<branch_name>` instead of `dev-master` or just tag name.  
  
Execute `php /path/to/composer.phar install` to deploy extension.  
Use `php /path/to/composer.phar update` to update extension from repository.

Tests
-----------
To use unit tests [EcomDev_PHPUnit][ecomdev_phpunit_link] must be installed.

PowerSync
-----------
**PowerSync** http://powersync.biz<br />

[modman_link]: https://raw.githubusercontent.com/hws47a/modman-relative-links/master/modman
[composer_link]: https://getcomposer.org/download/
[ecomdev_phpunit]: https://github.com/EcomDev/EcomDev_PHPUnit
