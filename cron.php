<?php

//Check if script is already running
  $tmpfilename = "/tmp/freeradius_cron.pid";
  if (!($tmpfile = @fopen($tmpfilename,"w")))
  {
    return 0;
  }

  if (!@flock( $tmpfile, LOCK_EX | LOCK_NB, &$wouldblock) || $wouldblock)
  {
    @fclose($tmpfile);
    return 0;
  }
//end

//Include some files
  require(dirname(__FILE__).'/config.php');
//end

//Set some varibles
  if($general_timezone != '') { date_default_timezone_set($general_timezone); }
  $radius_users = array();
//end

//Curl is important
  if (!function_exists('curl_init')){
    echo "cURL is not installed\n";
    die('cURL is not installed');
  }
//

//Make sure the WHMCS API details are present & tested
  if( ( $whmcs_api_url == "" ) || ( $whmcs_api_username == "" ) || ( $whmcs_api_password == "" ) ) {
    die( "WHMCS API details missing\n" );
  }
//end

//Connect to the radius database
  $mysql = ($GLOBALS["___mysqli_ston"] = mysqli_connect($freeradius_db_host,  $freeradius_db_username,  $freeradius_db_password));
  $conn = mysqli_select_db($GLOBALS["___mysqli_ston"], $freeradius_db_database);
  if (!$conn) {
    die( "Could not connect to WHMCS database: " . mysqli_error($GLOBALS["___mysqli_ston"]) . "\n" );
  }
//end

//Get a list of users in the radcheck table 
  $radcheck_q = mysqli_query($GLOBALS["___mysqli_ston"], "SELECT DISTINCT username FROM radcheck");
  while($radcheck = mysqli_fetch_array($radcheck_q)) {
    array_push($radius_users,$radcheck['username']);
  }
//end

//Start the overusage checking
  foreach($radius_users as $user) {
    $postfields = array();
    $postfields["username"] = $whmcs_api_username;
    $postfields["password"] = md5($whmcs_api_password);
    $postfields["api_key"] = $whmcs_api_key;
    $postfields["action"] = "freeradiusapi";
    $postfields["service_username"] = $user;
    $postfields["responsetype"] = "json";

    $query_string = "";
    foreach ($postfields AS $k=>$v) $query_string .= "$k=".urlencode($v)."&";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $whmcs_api_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $query_string);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    $jsondata = curl_exec($ch);
    if( curl_error($ch) ) {
      echo "WHMCS API Connection Error: " . curl_errno($ch) . " - " . curl_error($ch) . "\n";
      curl_close($ch);
      continue;
    }

    curl_close($ch);
    $api_data = json_decode($jsondata, true);

    if( $api_data["result"] == 'error' ) {
      echo $user . ": " . $api_data["message"] . "\n";
      continue;
    }

    $startdate = $api_data["startdate"];
    $enddate = $api_data["enddate"];
    $usagelimit = $api_data["usagelimit"];

    if( $usagelimit == 0 ) {
      echo $user . ": No usage limit set\n";
      continue;
    }

    $query = "SELECT SUM(radacct.AcctOutputOctets) + SUM(radacct.AcctInputOctets) AS total FROM radacct WHERE radacct.Username='$user' AND radacct.AcctStartTime>='".$startdate."'";
    if ($enddate) $query .= " AND radacct.AcctStartTime<='".$startdate."'";
    $result = mysqli_query($GLOBALS["___mysqli_ston"], $query);
    $data = mysqli_fetch_array($result);
    $usagetotal = $data[0];
    if( ( $usagetotal == "" ) || ( $usagetotal == NULL ) ) { $usagetotal = 0; }

    if($usagetotal >= $usagelimit) {
      $result = mysqli_query($GLOBALS["___mysqli_ston"],  "SELECT value FROM radcheck where username = '$user' and attribute = 'Expiration'" );
      $row = mysqli_fetch_array($result);
      if( !$row ) {
        $result = mysqli_query($GLOBALS["___mysqli_ston"],  "INSERT into radcheck (username, attribute, op, value) values ('".$user."','Expiration',':=','".date("Y-d-m G:i:s")."')" );
        echo $user . ": Account has reached its usage limit.\n";
        disconnect($user);
      }
    }
    else {
      $result = mysqli_query($GLOBALS["___mysqli_ston"],  "SELECT value FROM radcheck where username = '$user' and attribute = 'Expiration'" );
      $row = mysqli_fetch_array($result);
      if( $row ) {
        $result = mysqli_query($GLOBALS["___mysqli_ston"], "DELETE from radcheck where username = '".$user."' and attribute = 'Expiration'");
        echo $user . ": Account is within its usage limit. Removing suspension\n";
      }
    }
  }
//end

//Functions
  function disconnect($username) {
    require( dirname(__FILE__) . '/config.php' );

    $result = mysqli_query($GLOBALS["___mysqli_ston"], "SELECT radacctid FROM radacct where username = '".$username."' and acctstoptime is null");
    while( $row = mysqli_fetch_array($result) ) {
      $result = mysqli_query($GLOBALS["___mysqli_ston"], "SELECT * FROM radacct where radacctid = '".$row['radacctid']."'");
      $radacct = mysqli_fetch_array($result);

      $result = mysqli_query($GLOBALS["___mysqli_ston"], "SELECT * FROM nas where nasname = '".$radacct['nasipaddress']."'");
      $nas = mysqli_fetch_array($result);
      if( $nas ) {
        $command = "echo \"User-Name='".$username."',Framed-IP-Address='".$radacct['framedipaddress']."',Acct-Session-Id='".$radacct['acctsessionid']."',NAS-IP-Address='".$radacct['nasipaddress']."'\"| ".$radclient_path." -r 3 -x ".$nas['nasname'].":".$nas['ports']." disconnect ".$nas['secret']."";
        exec($command,$output,$rv);
        $podreply = end($output);
        if( strpos( $podreply, "Disconnect-ACK" ) === true ) {
          $message = $username.": Successfully disconnected session";
        }
        else if( strpos($podreply, "Disconnect-NAK" ) === true ) {
          $message = "Failed to disconnected session. Device rejected the request.";
        }
        else {
          $message = $username.": Failed to disconnected session. Device time out.";
        }
        echo $message . "\n";
      }
    }
  }
//end

//Close radius mysql connection
  ((is_null($___mysqli_res = mysqli_close($mysql))) ? false : $___mysqli_res);
//end

?>