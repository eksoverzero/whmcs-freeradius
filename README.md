# WHMCS Freeradius

### Important note...

There have been recent untested changes and additions. Log an issue as needed or sponsor a WHMCS testing license / server, if you like.

### I think this would be the legal/disclaimer stuff...

I have worked on this project for a very long time. In that time I have modified many versions of this module, made by others(for clients). In that process, some logic just worked better, so I adopted it. for example:

The date_range and freeradius_username functions are from the freeRADIUS module made by WHMCS, I think. I was previously using MySQL for the date ranges and returning errors on duplicate usernames. These just work better.

##### Please pull and fix or contribute

### Installing

freeradius.php & clientarea.tpl file -> WHMCSROOT/modules/servers/freeradius/
freeradiusapi.php -> WHMCSROOT/include/api/

The reset of the files are for the radius servers.

cron.php is to be added as a cron job.

config.php.example is to be renamed to config.php and edited as per your setup

clientarea.tpl is fully customizable according to your needs