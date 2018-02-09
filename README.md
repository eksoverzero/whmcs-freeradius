# WHMCS Freeradius

[![Gitter](https://badges.gitter.im/Join%20Chat.svg)](https://gitter.im/secondimpression/whmcs-freeradius?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge)

### LOOKING FOR A MAINTAINER

FreeRADIUS experience not required, just PHP, MySQL and WHMCS. I'll be available for questions, I just don't have time to be as active on this project as I'd like to be.

*Willing to pay for assistance on a per bug/feature basis*

### Installing

##### WHMCS

- Create a folder called `freeradius` in `WHMCSROOT/modules/servers/`
- Copy `freeradius.php` and `clientarea.tpl` into the newly created `WHMCSROOT/modules/servers/freeradius` folder
- Copy `freeradiusapi.php` into the `WHMCSROOT/include/api/` folder

##### FreeRADIUS servers

- Create a folder anywhere with whatever name you like. For example, on Linux `mkdir /opt/whmcs-freeradius`
- Copy `cron.php` and `config.php.example` into this folder
- Rename `config.php.example` to `config.php`
- Edit `config.php` as per your needs/requirements
- Create a Cron task for the `cron.php` file. If your `cron.php` file is in `/opt/whmcs-freeradius` then your cron task shold look something like this, if you want it to run every 5 minutes:
  
  ```
  */5 * * * * PATH_TO_PHP/php -q /opt/whmcs-freeradius/cron.php
  ```

- On Linux, you can find the `PATH_TO_PHP` by running `which php`
