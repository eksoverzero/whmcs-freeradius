[![Stories in Ready](https://badge.waffle.io/secondimpression/whmcs-freeradius.png?label=ready&title=Ready)](https://waffle.io/secondimpression/whmcs-freeradius)
# WHMCS Freeradius

[![Gitter](https://badges.gitter.im/Join%20Chat.svg)](https://gitter.im/eksoverzero/whmcs-freeradius?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge)

### Important note...

There have been recent untested changes and additions. Log an issue as needed or sponsor a WHMCS testing license / server, if you like.

### I think this would be the legal/disclaimer stuff...

I have worked on this project for a very long time. In that time I have modified many versions of this module, made by others(for clients). In that process, some logic just worked better, so I adopted it. for example:

The date_range and freeradius_username functions are from the freeRADIUS module made by WHMCS, I think. I was previously using MySQL for the date ranges and returning errors on duplicate usernames. These just work better.

##### Please pull and fix or contribute

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
