<?php

function freeradius_ConfigOptions(){
  $configarray = array(
    "radius_group" => array (
      "FriendlyName" => "Radius Group",
      "Type" => "text",
      "Size" => "25",
      "Description" => "FreeRADIUS group name"
    ),
    "usage_limit" => array (
      "FriendlyName" => "Usage Limit",
      "Type" => "text",
      "Size" => "25",
      "Description" => "In bytes. 0 or blank to disable"
    ),
    "rate_limit" => array (
      "FriendlyName" => "Rate Limit",
      "Type" => "text",
      "Size" => "25",
      "Description" => "Router specific. 0 or blank to disable"
    ),
    "session_limit" => array (
      "FriendlyName" => "Session Limit",
      "Type" => "text",
      "Size" => "5",
      "Description" => "Fixed number. 0 or balnk to disable"
    )
  );
  return $configarray;
}

function freeradius_AdminServicesTabFields($params){
  $username = $params["username"];
  $serviceid = $params["serviceid"];

  $collected = collect_usage($params);

  $fieldsarray = array(
   '# of Logins' => $collected['logins'],
   'Accumalated Hours Online' => secs_to_h( $collected['logintime'] ),
   'Total Usage' => byte_size( $collected['total'] ),
   'Uploaded' => byte_size( $collected['uploads'] ),
   'Downloaded' => byte_size( $collected['downloads'] ),
   'Usage Limit' => byte_size( $collected['usage_limit'] ),
   'Status' => $collected['status']
  );
  return $fieldsarray;
}

function freeradius_ClientArea($params){
  $username = $params["username"];
  $serviceid = $params["serviceid"];

  $collected = collect_usage($params);

  return array(
    'templatefile' => 'clientarea',
    'vars' => array(
      'logins' => $collected['logins'],
      'logintime' => secs_to_h( $collected['logintime'] ),
      'logintime_seconds' => $collected['logintime'],
      'uploads' => byte_size( $collected['uploads'] ),
      'uploads_bytes' => $collected['uploads'],
      'downloads' => byte_size( $collected['downloads'] ),
      'downloads_bytes' => $collected['downloads'],
      'total' => byte_size( $collected['total'] ),
      'total_bytes' => $collected['total'],
      'limit' => byte_size( $collected['usage_limit']),
      'limit_bytes' => $collected['usage_limit'],
      'status' => $collected['status'],
    ),
  );
}

function freeradius_username($email){
  global $CONFIG;
  $emaillen = strlen($email);
  $result = select_query(
    "tblhosting",
    "COUNT(*)",
    array(
      "username" => $email
    )
  );
  $data = mysql_fetch_array($result);
  $username_exists = $data[0];
  $suffix = 0;
  while( $username_exists > 0 ){
    $suffix++;
    $email = substr( $email, 0, $emaillen ) . $suffix;
    $result = select_query(
      "tblhosting",
      "COUNT(*)",
      array(
        "username" => $email
      )
    );
    $data = mysql_fetch_array($result);
    $username_exists = $data[0];
  }
  return $email;
}

function freeradius_CreateAccount($params){
  $username = $params["username"];
  $password = $params["password"];
  $groupname = $params["configoption1"];
  $firstname = $params["clientsdetails"]["firstname"];
  $lastname = $params["clientsdetails"]["lastname"];
  $email = $params["clientsdetails"]["email"];
  $phonenumber = $params["clientsdetails"]["phonenumber"];

  if( !$username ){
    $username = freeradius_username( $email );
    update_query(
      "tblhosting",
      array(
        "username" => $username
        ),
      array(
        "id" => $params["serviceid"]
      )
    );
  }

  $sqlhost = $params["serverip"];
  $sqlusername = $params["serverusername"];
  $sqlpassword = $params["serverpassword"];
  $sqldbname = $params["serveraccesshash"];
  $freeradiussql = mysql_connect($sqlhost,$sqlusername,$sqlpassword);
  mysql_select_db($sqldbname);

  $query = "SELECT COUNT(*) FROM radcheck WHERE username='$username'";
  $result = mysql_query($query,$freeradiussql);
  if( !$result ){
    $radiussqlerror = mysql_error();
    freeradius_WHMCSReconnect();
    return "FreeRadius Database Query Error: ".$radiussqlerror;
  }
  $data = mysql_fetch_array($result);
  if( $data[0] ){
    freeradius_WHMCSReconnect();
    return "Username Already Exists";
  }
  $query = "INSERT INTO radcheck (username, attribute, value, op) VALUES ('$username', 'User-Password', '$password', ':=')";
  $result = mysql_query($query,$freeradiussql);
  if( !$result ){
    $radiussqlerror = mysql_error();
    freeradius_WHMCSReconnect();
    return "FreeRadius Database Query Error: " . $radiussqlerror;
  }
  $query = "INSERT INTO radusergroup(username, groupname) VALUES ('$username', '$groupname')";
  $result = mysql_query( $query, $freeradiussql );
  if( !$result ){
    $radiussqlerror = mysql_error();
    freeradius_WHMCSReconnect();
    return "FreeRadius Database Query Error: " . $radiussqlerror;
  }

  $rate_limit = $params["configoption3"];
  $session_limit = $params["configoption4"];

  foreach( $params["configoptions"] as $key => $value ){
    if( $key == 'Rate Limit' ){
      $rate_limit = $value;
    }
    if( $key == 'Session Limit' ){
      $session_limit = $value;
    }
  }

  if( $rate_limit ){
    $query = "INSERT INTO radreply (username,attribute,value,op) VALUES ('$username','Mikrotik-Rate-Limit','$rate_limit',':=')";
    $result = mysql_query($query,$freeradiussql);
    if (!$result) {
      $radiussqlerror = mysql_error();
      freeradius_WHMCSReconnect();
      return "FreeRadius Database Query Error: ".$radiussqlerror;
    }
  }

  if( $session_limit ){
    $query = "INSERT INTO radcheck (username,attribute,value,op) VALUES ('$username','Simultaneous-Use','$session_limit',':=')";
    $result = mysql_query($query,$freeradiussql);
    if (!$result) {
      $radiussqlerror = mysql_error();
      freeradius_WHMCSReconnect();
      return "FreeRadius Database Query Error: ".$radiussqlerror;
    }
  }

  freeradius_WHMCSReconnect();
  return "success";
}

function freeradius_SuspendAccount($params){
  $username = $params["username"];
  $password = $params["password"];

  $sqlhost = $params["serverip"];
  $sqlusername = $params["serverusername"];
  $sqlpassword = $params["serverpassword"];
  $sqldbname = $params["serveraccesshash"];
  $freeradiussql = mysql_connect($sqlhost,$sqlusername,$sqlpassword);
  mysql_select_db($sqldbname);

  $query = "SELECT COUNT(*) FROM radcheck WHERE username='$username'";
  $result = mysql_query($query,$freeradiussql);
  if (!$result) {
    freeradius_WHMCSReconnect();
    return "FreeRadius Database Query Error: ".mysql_error();
  }
  $data = mysql_fetch_array($result);
  $count = $data[0];
  if (!$count) {
    freeradius_WHMCSReconnect();
    return "User Not Found";
  }
  $query = "SELECT COUNT(*) FROM radcheck WHERE username='$username' AND attribute='Expiration'";
  $result = mysql_query($query,$freeradiussql);
  if (!$result) {
    freeradius_WHMCSReconnect();
    return "FreeRadius Database Query Error: ".mysql_error();
  }
  $data = mysql_fetch_array($result);
  $count = $data[0];
  if (!$count) {
    $query = "INSERT INTO radcheck (username,attribute,value,op) VALUES ('$username','Expiration','".date("d F Y")."',':=')";
  } else {
    $query = "UPDATE radcheck SET value='".date("d F Y")."' WHERE username='$username' AND attribute='Expiration'";
  }
  $result = mysql_query($query,$freeradiussql);
  if (!$result) {
    freeradius_WHMCSReconnect();
    return "FreeRadius Database Query Error: ".mysql_error();
  }
  freeradius_WHMCSReconnect();

  return "success";
}

function freeradius_UnsuspendAccount($params){
  $username = $params["username"];
  $password = $params["password"];

  $sqlhost = $params["serverip"];
  $sqlusername = $params["serverusername"];
  $sqlpassword = $params["serverpassword"];
  $sqldbname = $params["serveraccesshash"];
  $freeradiussql = mysql_connect($sqlhost,$sqlusername,$sqlpassword);
  mysql_select_db($sqldbname);

  $query = "SELECT COUNT(*) FROM radcheck WHERE username='$username' AND attribute='Expiration'";
  $result = mysql_query($query,$freeradiussql);
  if (!$result) {
    freeradius_WHMCSReconnect();
    return "FreeRadius Database Query Error: ".mysql_error();
  }
  $data = mysql_fetch_array($result);
  $count = $data[0];
  if (!$count) {
    freeradius_WHMCSReconnect();
    return "User Not Currently Suspended";
  }
  $query = "DELETE FROM radcheck WHERE username='$username' AND attribute='Expiration'";
  $result = mysql_query($query,$freeradiussql);
  if (!$result) {
    freeradius_WHMCSReconnect();
    return "FreeRadius Database Query Error: ".mysql_error();
  }
  freeradius_WHMCSReconnect();

  return "success";
}

function freeradius_TerminateAccount($params){
  $username = $params["username"];
  $password = $params["password"];

  $sqlhost = $params["serverip"];
  $sqlusername = $params["serverusername"];
  $sqlpassword = $params["serverpassword"];
  $sqldbname = $params["serveraccesshash"];
  $freeradiussql = mysql_connect($sqlhost,$sqlusername,$sqlpassword);
  mysql_select_db($sqldbname);

  $query = "DELETE FROM radreply WHERE username='$username'";
  $result = mysql_query($query,$freeradiussql);
  if (!$result) {
    $radiussqlerror = mysql_error();
    freeradius_WHMCSReconnect();
    return "FreeRadius Database Query Error: ".$radiussqlerror;
  }
  $query = "DELETE FROM radusergroup WHERE username='$username'";
  $result = mysql_query($query,$freeradiussql);
  if (!$result) {
    $radiussqlerror = mysql_error();
    freeradius_WHMCSReconnect();
    return "FreeRadius Database Query Error: ".$radiussqlerror;
  }
  $query = "DELETE FROM radcheck WHERE username='$username'";
  $result = mysql_query($query,$freeradiussql);
  if (!$result) {
    $radiussqlerror = mysql_error();
    freeradius_WHMCSReconnect();
    return "FreeRadius Database Query Error: ".$radiussqlerror;
  }
  freeradius_WHMCSReconnect();

  return "success";
}

function freeradius_ChangePassword($params){
  $username = $params["username"];
  $password = $params["password"];

  $sqlhost = $params["serverip"];
  $sqlusername = $params["serverusername"];
  $sqlpassword = $params["serverpassword"];
  $sqldbname = $params["serveraccesshash"];
  $freeradiussql = mysql_connect($sqlhost,$sqlusername,$sqlpassword);
  mysql_select_db($sqldbname);

  $query = "SELECT COUNT(*) FROM radcheck WHERE username='$username'";
  $result = mysql_query($query,$freeradiussql);
  if (!$result) {
    $sqlerror = mysql_error();
    freeradius_WHMCSReconnect();
    return "FreeRadius Database Query Error: $sqlerror";
  }
  $data = mysql_fetch_array($result);
  $count = $data[0];
  if (!$count) {
    freeradius_WHMCSReconnect();
    return "User Not Found";
  }
  $query = "UPDATE radcheck SET value='$password' WHERE username='$username' AND attribute='User-Password'";
  $result = mysql_query($query,$freeradiussql);
  if (!$result) {
    freeradius_WHMCSReconnect();
    return "FreeRadius Database Query Error: ".mysql_error();
  }
  freeradius_WHMCSReconnect();

  return "success";
}

function freeradius_ChangePackage($params){
  $username = $params["username"];
  $password = $params["password"];
  $groupname = $params["configoption1"];

  $sqlhost = $params["serverip"];
  $sqlusername = $params["serverusername"];
  $sqlpassword = $params["serverpassword"];
  $sqldbname = $params["serveraccesshash"];
  $freeradiussql = mysql_connect($sqlhost,$sqlusername,$sqlpassword);
  mysql_select_db($sqldbname);

  $query = "SELECT COUNT(*) FROM radusergroup WHERE username='$username'";
  $result = mysql_query($query,$freeradiussql);
  if (!$result) {
    freeradius_WHMCSReconnect();
    return "FreeRadius Database Query Error: ".mysql_error();
  }
  $data = mysql_fetch_array($result);
  $count = $data[0];
  if ( !$count ) {
    freeradius_WHMCSReconnect();
    return "User Not Found";
  }
  $query = "UPDATE radusergroup SET groupname='$groupname' WHERE username='$username'";
  $result = mysql_query($query,$freeradiussql);
  if ( !$result ) {
    freeradius_WHMCSReconnect();
    return "FreeRadius Database Query Error: ".mysql_error();
  }

  $rate_limit = $params["configoption3"];
  $session_limit = $params["configoption4"];

  foreach ($params["configoptions"] as $key => $value) {
    if ($key == 'Rate Limit') {
      $rate_limit = $value;
    }
    if ($key == 'Session Limit') {
      $session_limit = $value;
    }
  }

  if( $rate_limit ) {
    $query = "UPDATE radreply SET value='$rate_limit' WHERE username='$username' AND attribute='Mikrotik-Rate-Limit'";
    $result = mysql_query($query,$freeradiussql);
    if (!$result) {
      $radiussqlerror = mysql_error();
      freeradius_WHMCSReconnect();
      return "FreeRadius Database Query Error: ".$radiussqlerror;
    }
  }

  if( $session_limit ) {
    $query = "UPDATE radcheck SET value='$session_limit' WHERE username='$username' AND attribute='Mikrotik-Rate-Limit'";
    $result = mysql_query($query,$freeradiussql);
    if (!$result) {
      $radiussqlerror = mysql_error();
      freeradius_WHMCSReconnect();
      return "FreeRadius Database Query Error: ".$radiussqlerror;
    }
  }

  freeradius_WHMCSReconnect();
  return "success";
}

function freeradius_update_ip_address($params){

  $username = $params["username"];

  $result = select_query(
    'tblhosting',
    "id,dedicatedip",
    array(
      "id"=>$params["serviceid"]
    )
  );
  $data = mysql_fetch_array($result);
  $id = $data['id'];
  $dedicatedip = $data['dedicatedip'];

  $sqlhost = $params["serverip"];
  $sqlusername = $params["serverusername"];
  $sqlpassword = $params["serverpassword"];
  $sqldbname = $params["serveraccesshash"];
  $freeradiussql = mysql_connect($sqlhost,$sqlusername,$sqlpassword);
  mysql_select_db($sqldbname);

  $query = "DELETE FROM radreply WHERE username='$username' AND attribute='Framed-IP-Address'";
  $result = mysql_query($query,$freeradiussql);
  if (!$result) {
    $radiussqlerror = mysql_error();
    freeradius_WHMCSReconnect();
    return "FreeRadius Database Query Error: ".$radiussqlerror;
  }

  if( $dedicatedip ){
    $query = "INSERT INTO radreply (username,attribute,value,op) VALUES ('$username','Framed-IP-Address','$dedicatedip',':=')";
    $result = mysql_query($query,$freeradiussql);
    if (!$result) {
      $radiussqlerror = mysql_error();
      freeradius_WHMCSReconnect();
      return "FreeRadius Database Query Error: ".$radiussqlerror;
    }
  }
  freeradius_WHMCSReconnect();
  return "success";
}

function freeradius_AdminCustomButtonArray(){
    $buttonarray = array(
   "Update IP Address" => "update_ip_address"
  );
  return $buttonarray;
}

function date_range($nextduedate, $billingcycle) {
  $year = substr( $nextduedate, 0, 4 );
  $month = substr( $nextduedate, 5, 2 );
  $day = substr( $nextduedate, 8, 2 );

  if( $billingcycle == "Monthly" ){
    $new_time = mktime( 0, 0, 0, $month - 1, $day, $year );
  } elseif( $billingcycle == "Quarterly" ){
    $new_time = mktime( 0, 0, 0, $month - 3, $day, $year );
  } elseif( $billingcycle == "Semi-Annually" ){
    $new_time = mktime( 0, 0, 0, $month - 6, $day, $year );
  } elseif( $billingcycle == "Annually" ){
    $new_time = mktime( 0, 0, 0, $month, $day, $year - 1 );
  } elseif( $billingcycle == "Biennially" ){
    $new_time = mktime( 0, 0, 0, $month, $day, $year - 2 );
  }
  $startdate = date( "Y-m-d", $new_time );
  $enddate = "";

  if( date( "Ymd", $new_time ) >= date( "Ymd" ) ){
    if( $billingcycle == "Monthly" ){
      $new_time = mktime( 0, 0, 0, $month - 2, $day, $year );
    } elseif( $billingcycle == "Quarterly" ){
      $new_time = mktime( 0, 0, 0, $month - 6, $day, $year );
    } elseif( $billingcycle == "Semi-Annually" ){
      $new_time = mktime( 0, 0, 0, $month - 12, $day, $year );
    } elseif( $billingcycle == "Annually" ){
      $new_time = mktime( 0, 0, 0, $month, $day, $year - 2 );
    } elseif( $billingcycle == "Biennially" ){
      $new_time = mktime( 0, 0, 0, $month, $day, $year - 4 );
    }
    $startdate = date( "Y-m-d", $new_time );
    if( $billingcycle == "Monthly" ){
      $new_time = mktime( 0, 0, 0, $month - 1, $day, $year );
    } elseif( $billingcycle == "Quarterly" ){
      $new_time = mktime( 0, 0, 0, $month - 3, $day, $year );
    } elseif( $billingcycle == "Semi-Annually" ){
      $new_time = mktime( 0, 0, 0, $month - 6, $day, $year );
    } elseif( $billingcycle == "Annually" ){
      $new_time = mktime( 0, 0, 0, $month, $day, $year - 1 );
    } elseif( $billingcycle == "Biennially" ){
      $new_time = mktime( 0, 0, 0, $month, $day, $year - 2 );
    }
    $enddate = date( "Y-m-d", $new_time );
  }
  return array(
    "startdate" => $startdate,
    "enddate" => $enddate
  );
}

function collect_usage($params){
  $username = $params["username"];
  $serviceid = $params["serviceid"];

  $sqlhost = $params["serverip"];
  $sqlusername = $params["serverusername"];
  $sqlpassword = $params["serverpassword"];
  $sqldbname = $params["serveraccesshash"];

  $result = select_query("tblhosting","nextduedate,billingcycle",array("id"=>$serviceid));
  $data = mysql_fetch_array($result);

  $date_range = date_range( $data["nextduedate"], $data["billingcycle"] );

  $startdate = $date_range["startdate"];
  $enddate = $date_range["enddate"];

  $freeradiussql = mysql_connect($sqlhost,$sqlusername,$sqlpassword);
  mysql_select_db($sqldbname);

  $query = "SELECT COUNT(*) AS logins,SUM(radacct.AcctSessionTime) AS logintime,SUM(radacct.AcctInputOctets) AS uploads,SUM(radacct.AcctOutputOctets) AS downloads,SUM(radacct.AcctOutputOctets) + SUM(radacct.AcctInputOctets) AS total FROM radacct WHERE radacct.Username='$username' AND radacct.AcctStartTime>='".$startdate."'";
  if ($enddate) $query .= " AND radacct.AcctStartTime<='".$startdate."'";
  $query .= " ORDER BY AcctStartTime DESC";
  $result = mysql_query($query,$freeradiussql);
  $data = mysql_fetch_array($result);
  $logins = $data[0];
  $logintime = $data[1];
  $uploads = $data[2];
  $downloads = $data[3];
  $total = $data[4];

  $query = "SELECT radacct.AcctStartTime as start, radacct.AcctStopTime as stop FROM radacct WHERE radacct.Username='$username' ORDER BY AcctStartTime DESC LIMIT 0,1";
  $result = mysql_query($query,$freeradiussql);
  $data = mysql_fetch_array($result);
  $sessions = mysql_num_rows($result);
  $start = $data[0];
  $end = $data[1];

  $status = "Offline";
  if( $end ) {
    $status = "Logged in at ".$start;
  }
  if( $sessions < 1 ){
    $status = "No logins";
  }

  freeradius_WHMCSReconnect();

  $usage_limit = 0;
  if( !empty( $params["configoption2"] ) ){
    if( is_numeric($params["configoption2"]) ) { $usage_limit = $params["configoption2"]; }
  }

  foreach( $params["configoptions"] as $key => $value ){
    $Megabytes = 0;
    $Gigabytes = 0;
    if( $key == 'Megabytes' ){
      if( is_numeric($value) ){
        $Gigabytes = $value * 1024 * 1024;
      }
    }
    if($key == 'Gigabytes'){
      if( is_numeric($value) ){
        $Gigabytes = $value * 1024 * 1024 * 1024;
      }
    }
    if( ( $Megabytes > 0 ) || ( $Gigabytes > 0 ) ){
      $usage_limit = $Megabytes + $Gigabytes;
    }
  }

  return array(
   'logins' => $logins,
   'logintime' => $logintime,
   'total' => $total,
   'uploads' => $uploads,
   'downloads' => $downloads,
   'usage_limit' => $usage_limit,
   'status' => $status,
  );
}

function secs_to_h($secs){
  $units = array(
    "week"   => 7*24*3600,
    "day"    => 24*3600,
    "hour"   => 3600,
    "minute" => 60
  );
  if ( $secs == 0 ) return "0 seconds";
  if ( $secs < 60 ) return "{$secs} seconds";
  $s = "";

  foreach ( $units as $name => $divisor ) {
    if ( $quot = intval($secs / $divisor) ) {
      $s .= $quot." ".$name;
      $s .= (abs($quot) > 1 ? "s" : "") . ", ";
      $secs -= $quot * $divisor;
    }
  }
  return substr($s, 0, -2);
}

function byte_size($bytes){
  $size = $bytes / 1024;
  if( $size < 1024 ) {
    $size = number_format( $size, 2 );
    $size .= ' KB';
  } 
  else {
    if( $size / 1024 < 1024 ) {
      $size = number_format($size / 1024, 2);
      $size .= ' MB';
    } 
    else if ( $size / 1024 / 1024 < 1024 ) {
      $size = number_format($size / 1024 / 1024, 2);
      $size .= ' GB';
    }
  }
  return $size;
}

function freeradius_WHMCSReconnect(){
  require( ROOTDIR . "/configuration.php" );
  $whmcsmysql = mysql_connect($db_host,$db_username,$db_password);
  mysql_select_db($db_name);
}

?>