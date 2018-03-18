<?php

require(ROOTDIR."/configuration.php");

#Return defaults
$return["result"] = "error";
$return["message"] = "Unknow WHMCS API error";

#Check if username has been posted
if( isset( $_POST['service_username'] ) ) {
  $username = $_POST['service_username'];
}
else {
  $return["message"] = "No username supplied";
  echo json_encode( $return );
  return;
}

#Collect service details based on posted username
$result = select_query(
  'tblhosting',
  "*",
  array(
    "username"=>$username
  )
);
$tblhosting = mysqli_fetch_array($result);
if( !$tblhosting ) {
  $return["message"] = "Username not found in WHMCS";
  echo json_encode( $return );
  return;
}

#Don't bother going on if account is not active
if( $tblhosting["domainstatus"] != "Active" ) {
  $return["message"] = "Account not active in WHMCS";
  echo json_encode( $return );
  return;
}

#Collect service details based on posted username
$result = select_query(
  'tblproducts',
  "*",
  array(
    "id" => $tblhosting["packageid"]
  )
);
$tblproducts = mysqli_fetch_array($result);
if( !$tblproducts ){
  $return["message"] = "Package not found in WHMCS";
  echo json_encode( $return );
  return;
}

#Check account usage limit
$Megabytes = 0;
$Gigabytes = 0;
$usage_limit = 0;
$result = select_query(
  "tblhostingconfigoptions",
  "tblhostingconfigoptions.relid,tblhostingconfigoptions.configid,tblhostingconfigoptions.optionid,tblhostingconfigoptions.optionid,tblhostingconfigoptions.qty,tblproductconfigoptionssub.id,tblproductconfigoptionssub.optionname",
  array(
    "relid"=>$tblhosting["id"],
  ),
  "relid",
  "ASC",
  false,
  "tblproductconfigoptionssub ON tblproductconfigoptionssub.id=tblhostingconfigoptions.optionid"
);

#Billing period dates for usage check
$nextduedate = $tblhosting["nextduedate"];
$billingcycle = $tblhosting["billingcycle"];
$year = substr($nextduedate,0,4);
$month = substr($nextduedate,5,2);
$day = substr($nextduedate,8,2);

if ($billingcycle=="Monthly") {
    $new_time=mktime(0,0,0,$month-1,$day,$year);
} elseif ($billingcycle=="Quarterly") {
    $new_time=mktime(0,0,0,$month-3,$day,$year);
} elseif ($billingcycle=="Semi-Annually") {
    $new_time=mktime(0,0,0,$month-6,$day,$year);
} elseif ($billingcycle=="Annually") {
    $new_time=mktime(0,0,0,$month,$day,$year-1);
} elseif ($billingcycle=="Biennially") {
    $new_time=mktime(0,0,0,$month,$day,$year-2);
}
$startdate = date("Y-m-d",$new_time);

$enddate = "";

if (date("Ymd",$new_time)>=date("Ymd")) {
  if ($billingcycle=="Monthly") {
      $new_time=mktime(0,0,0,$month-2,$day,$year);
  } elseif ($billingcycle=="Quarterly") {
      $new_time=mktime(0,0,0,$month-6,$day,$year);
  } elseif ($billingcycle=="Semi-Annually") {
      $new_time=mktime(0,0,0,$month-12,$day,$year);
  } elseif ($billingcycle=="Annually") {
      $new_time=mktime(0,0,0,$month,$day,$year-2);
  } elseif ($billingcycle=="Biennially") {
      $new_time=mktime(0,0,0,$month,$day,$year-4);
  }
  $startdate = date("Y-m-d",$new_time);
  if ($billingcycle=="Monthly") {
      $new_time=mktime(0,0,0,$month-1,$day,$year);
  } elseif ($billingcycle=="Quarterly") {
      $new_time=mktime(0,0,0,$month-3,$day,$year);
  } elseif ($billingcycle=="Semi-Annually") {
      $new_time=mktime(0,0,0,$month-6,$day,$year);
  } elseif ($billingcycle=="Annually") {
      $new_time=mktime(0,0,0,$month,$day,$year-1);
  } elseif ($billingcycle=="Biennially") {
      $new_time=mktime(0,0,0,$month,$day,$year-2);
  }
  $enddate = date("Y-m-d",$new_time);
}

$return["startdate"] = $startdate;
$return["enddate"] = $enddate;

if( !$tblproducts["configoption2"] ){
  $usage_limit =0;
}
else {
  if( is_numeric($tblproducts["configoption2"]) ) { $usage_limit = $tblproducts["configoption2"]; }
}

while ($data = mysqli_fetch_array($result)) {
  $qty = $data['qty'];
  $optionname = $data['optionname'];
  if ($optionname == 'Megabytes') {
    if (is_numeric($qty)) {
      $Gigabytes = $qty * 1024 * 1024;
    }
  }
  if ($optionname == 'Gigabytes') {
    if (is_numeric($qty)) {
      $Gigabytes = $qty * 1024 * 1024 * 1024;
    }
  }
  if ( ( $Megabytes > 0 ) || ( $Gigabytes > 0 ) ) {
    $usage_limit = $Megabytes + $Gigabytes;
  }
}

$return["usagelimit"] = $usage_limit;

$return["result"] = "success";
$return["message"] = "Success";
echo json_encode( $return );
return;

?>