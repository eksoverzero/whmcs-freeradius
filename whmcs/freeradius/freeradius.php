<?php
/**
 * WHMCS SDK Sample Provisioning Module
 *
 * Provisioning Modules, also referred to as Product or Server Modules, allow
 * you to create modules that allow for the provisioning and management of
 * products and services in WHMCS.
 *
 * This sample file demonstrates how a provisioning module for WHMCS should be
 * structured and exercises all supported functionality.
 *
 * Provisioning Modules are stored in the /modules/servers/ directory. The
 * module name you choose must be unique, and should be all lowercase,
 * containing only letters & numbers, always starting with a letter.
 *
 * Within the module itself, all functions must be prefixed with the module
 * filename, followed by an underscore, and then the function name. For this
 * example file, the filename is "provisioningmodule" and therefore all
 * functions begin "provisioningmodule_".
 *
 * If your module or third party API does not support a given function, you
 * should not define that function within your module. Only the _ConfigOptions
 * function is required.
 *
 * For more information, please refer to the online documentation.
 *
 * @see https://developers.whmcs.com/provisioning-modules/
 *
 * @copyright Copyright (c) WHMCS Limited 2017
 * @license https://www.whmcs.com/license/ WHMCS Eula
 */
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}
// Require any libraries needed for the module to function.
// require_once __DIR__ . '/path/to/library/loader.php';
//
// Also, perform any initialization required by the service's library.
/**
 * Define module related meta data.
 *
 * Values returned here are used to determine module related abilities and
 * settings.
 *
 * @see https://developers.whmcs.com/provisioning-modules/meta-data-params/
 *
 * @return array
 */
function provisioningmodule_MetaData(){
    return array(
        'DisplayName' => 'WHMCS FreeRADIUS',
        'APIVersion' => '1.1', // Use API Version 1.1
        'RequiresServer' => true, // Set true if module requires a server to work
        'DefaultNonSSLPort' => '1111', // Default Non-SSL Connection Port
        'DefaultSSLPort' => '1112', // Default SSL Connection Port
        'ServiceSingleSignOnLabel' => 'Login to Panel as User',
        'AdminSingleSignOnLabel' => 'Login to Panel as Admin',
    );
}
/**
 * Define product configuration options.
 *
 * The values you return here define the configuration options that are
 * presented to a user when configuring a product for use with the module. These
 * values are then made available in all module function calls with the key name
 * configoptionX - with X being the index number of the field from 1 to 24.
 *
 * You can specify up to 24 parameters, with field types:
 * * text
 * * password
 * * yesno
 * * dropdown
 * * radio
 * * textarea
 *
 * Examples of each and their possible configuration parameters are provided in
 * this sample function.
 *
 * @see https://developers.whmcs.com/provisioning-modules/config-options/
 *
 * @return array
 */
function freeradius_ConfigOptions(){
    return array(
        'Radius Group' => array(
            'Type' => 'text',
            'Size' => '25',
            'Default' => '',
            'Description' => 'FreeRADIUS group name',
        ),
        'Usage Limit' => array(
            'Type' => 'text',
            'Size' => '25',
            'Default' => '0',
            'Description' => 'Usage limit in bytes. Use 0 or leave blank to disable',
        ),
        'Rate Limit' => array(
            'Type' => 'text',
            'Size' => '25',
            'Default' => '0',
            'Description' => 'Rate limit. Use 0 or leave blank to disable',
        ),
        'Session Limit' => array(
            'Type' => 'text',
            'Size' => '5',
            'Default' => '0',
            'Description' => 'Session limit as a number. Use 0 or leave blank to disable',
        )
    );
}
/**
 * Provision a new instance of a product/service.
 *
 * Attempt to provision a new instance of a given product/service. This is
 * called any time provisioning is requested inside of WHMCS. Depending upon the
 * configuration, this can be any of:
 * * When a new order is placed
 * * When an invoice for a new order is paid
 * * Upon manual request by an admin user
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/provisioning-modules/module-parameters/
 *
 * @return string "success" or an error message
 */
function freeradius_CreateAccount(array $params){
    try {
        $email = $params['clientsdetails']['email'];
        $firstname = $params['clientsdetails']['firstname'];
        $lastname = $params['clientsdetails']['lastname'];

        $username = $params['username'];
        $password = $params['password'];

        $groupname = $params['configoption1'];
        $rate_limit = $params['configoption3'];
        $session_limit = $params['configoption4'];

        $sqlhost = $params['serverip'];
        $sqldbname = $params['serveraccesshash'];
        $sqlusername = $params['serverusername'];
        $sqlpassword = $params['serverpassword'];

        if (!$username) {
            $username = freeradius_username($email);

            update_query(
                'tblhosting',
                array(
                    'username' => $username
                ),
                array(
                    'id' => $params['serviceid']
                )
            );
        }

        $freeradiussql = ($GLOBALS["___mysqli_ston"] = mysqli_connect($sqlhost,  $sqlusername,  $sqlpassword));
        mysqli_select_db($GLOBALS["___mysqli_ston"], $sqldbname);

        $query = "SELECT COUNT(*) FROM radcheck WHERE username='$username'";
        $result = mysqli_query($freeradiussql, $query);

        if (!$result) {
            $radiussqlerror = mysqli_error($GLOBALS["___mysqli_ston"]);
            freeradius_WHMCSReconnect();
            return 'FreeRADIUS Database Query Error: ' . $radiussqlerror;
        }

        $data = mysqli_fetch_array($result);

        if ($data[0]) {
            freeradius_WHMCSReconnect();
            return 'Username Already Exists';
        }

        $query = "INSERT INTO radcheck (username, attribute, value, op) VALUES ('$username', 'User-Password', '$password', ':=')";
        $result = mysqli_query($freeradiussql, $query);

        if (!$result) {
            $radiussqlerror = mysqli_error($GLOBALS["___mysqli_ston"]);
            freeradius_WHMCSReconnect();
            return 'FreeRADIUS Database Query Error: ' . $radiussqlerror;
        }

        $query = "INSERT INTO radusergroup(username, groupname) VALUES ('$username', '$groupname')";
        $result = mysqli_query( $freeradiussql ,  $query);

        if (!$result) {
            $radiussqlerror = mysqli_error($GLOBALS["___mysqli_ston"]);
            freeradius_WHMCSReconnect();
            return 'FreeRADIUS Database Query Error: ' . $radiussqlerror;
        }

        foreach ($params["configoptions"] as $key => $value ) {
            if ($key == 'Rate Limit') {
                $rate_limit = $value;
            }

            if ($key == 'Session Limit') {
                $session_limit = $value;
            }
        }

        if ($rate_limit) {
            $query = "INSERT INTO radreply (username,attribute,value,op) VALUES ('$username','Mikrotik-Rate-Limit','$rate_limit',':=')";
            $result = mysqli_query($freeradiussql, $query);

            if (!$result) {
                $radiussqlerror = mysqli_error($GLOBALS["___mysqli_ston"]);
                freeradius_WHMCSReconnect();
                return 'FreeRADIUS Database Query Error: ' . $radiussqlerror;
            }
        }

        if ($session_limit) {
            $query = "INSERT INTO radcheck (username,attribute,value,op) VALUES ('$username','Simultaneous-Use','$session_limit',':=')";
            $result = mysqli_query($freeradiussql, $query);

            if (!$result) {
                $radiussqlerror = mysqli_error($GLOBALS["___mysqli_ston"]);
                freeradius_WHMCSReconnect();
                return 'FreeRadius Database Query Error: ' . $radiussqlerror;
            }
        }

        freeradius_WHMCSReconnect();
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'freeradius',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
        return $e->getMessage();
    }

    return 'success';
}
/**
 * Suspend an instance of a product/service.
 *
 * Called when a suspension is requested. This is invoked automatically by WHMCS
 * when a product becomes overdue on payment or can be called manually by admin
 * user.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/provisioning-modules/module-parameters/
 *
 * @return string "success" or an error message
 */
function freeradius_SuspendAccount(array $params){
    try {
        $sqlhost = $params['serverip'];
        $sqldbname = $params['serveraccesshash'];
        $sqlusername = $params['serverusername'];
        $sqlpassword = $params['serverpassword'];

        $freeradiussql = ($GLOBALS["___mysqli_ston"] = mysqli_connect($sqlhost,  $sqlusername,  $sqlpassword));
        mysqli_select_db($GLOBALS["___mysqli_ston"], $sqldbname);

        $query = "SELECT COUNT(*) FROM radcheck WHERE username='$username'";
        $result = mysqli_query( $freeradiussql, $query);

        if (!$result) {
            $radiussqlerror = mysqli_error($GLOBALS["___mysqli_ston"]);
            freeradius_WHMCSReconnect();
            return 'FreeRADIUS Database Query Error: ' . $radiussqlerror;
        }

        $data = mysqli_fetch_array($result);
        $count = $data[0];

        if (!$count) {
            freeradius_WHMCSReconnect();
            return 'User Not Found';
        }

        $query = "SELECT COUNT(*) FROM radcheck WHERE username='$username' AND attribute='Expiration'";
        $result = mysqli_query($freeradiussql, $query);

        if (!$result) {
            $radiussqlerror = mysqli_error($GLOBALS["___mysqli_ston"]);
            freeradius_WHMCSReconnect();
            return 'FreeRADIUS Database Query Error: ' . $radiussqlerror;
        }

        $data = mysqli_fetch_array($result);
        $count = $data[0];

        if (!$count) {
            $query = "INSERT INTO radcheck (username,attribute,value,op) VALUES ('$username','Expiration','".date("d F Y")."',':=')";
        } else {
            $query = "UPDATE radcheck SET value='".date("d F Y")."' WHERE username='$username' AND attribute='Expiration'";
        }

        $result = mysqli_query( $freeradiussql, $query);

        if (!$result) {
            $radiussqlerror = mysqli_error($GLOBALS["___mysqli_ston"]);
            freeradius_WHMCSReconnect();
            return 'FreeRADIUS Database Query Error: ' . $radiussqlerror;
        }

        freeradius_WHMCSReconnect();
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'freeradius',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
        return $e->getMessage();
    }

    return 'success';
}
/**
 * Un-suspend instance of a product/service.
 *
 * Called when an un-suspension is requested. This is invoked
 * automatically upon payment of an overdue invoice for a product, or
 * can be called manually by admin user.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/provisioning-modules/module-parameters/
 *
 * @return string "success" or an error message
 */
function freeradius_UnsuspendAccount(array $params){
    try {
        $sqlhost = $params['serverip'];
        $sqldbname = $params['serveraccesshash'];
        $sqlusername = $params['serverusername'];
        $sqlpassword = $params['serverpassword'];

        $freeradiussql = ($GLOBALS["___mysqli_ston"] = mysqli_connect($sqlhost,  $sqlusername,  $sqlpassword));
        mysqli_select_db($GLOBALS["___mysqli_ston"], $sqldbname);

        $query = "SELECT COUNT(*) FROM radcheck WHERE username='$username' AND attribute='Expiration'";
        $result = mysqli_query($freeradiussql, $query);

        if (!$result) {
            $radiussqlerror = mysqli_error($GLOBALS["___mysqli_ston"]);
            freeradius_WHMCSReconnect();
            return 'FreeRADIUS Database Query Error: ' . $radiussqlerror;
        }

        $data = mysqli_fetch_array($result);
        $count = $data[0];

        if (!$count) {
            freeradius_WHMCSReconnect();
            return 'User Not Currently Suspended';
        }

        $query = "DELETE FROM radcheck WHERE username='$username' AND attribute='Expiration'";
        $result = mysqli_query($freeradiussql, $query);

        if (!$result) {
            $radiussqlerror = mysqli_error($GLOBALS["___mysqli_ston"]);
            freeradius_WHMCSReconnect();
            return 'FreeRADIUS Database Query Error: ' . $radiussqlerror;
        }

        freeradius_WHMCSReconnect();
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'freeradius',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
        return $e->getMessage();
    }

    return 'success';
}
/**
 * Terminate instance of a product/service.
 *
 * Called when a termination is requested. This can be invoked automatically for
 * overdue products if enabled, or requested manually by an admin user.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/provisioning-modules/module-parameters/
 *
 * @return string "success" or an error message
 */
function freeradius_TerminateAccount(array $params){
    try {
        $sqlhost = $params['serverip'];
        $sqldbname = $params['serveraccesshash'];
        $sqlusername = $params['serverusername'];
        $sqlpassword = $params['serverpassword'];

        $freeradiussql = ($GLOBALS["___mysqli_ston"] = mysqli_connect($sqlhost,  $sqlusername,  $sqlpassword));
        mysqli_select_db($GLOBALS["___mysqli_ston"], $sqldbname);

        $query = "DELETE FROM radreply WHERE username='$username'";
        $result = mysqli_query($freeradiussql, $query);

        if (!$result) {
            $radiussqlerror = mysqli_error($GLOBALS["___mysqli_ston"]);
            freeradius_WHMCSReconnect();
            return 'FreeRadius Database Query Error: ' . $radiussqlerror;
        }

        $query = "DELETE FROM radusergroup WHERE username='$username'";
        $result = mysqli_query($freeradiussql, $query);

        if (!$result) {
            $radiussqlerror = mysqli_error($GLOBALS["___mysqli_ston"]);
            freeradius_WHMCSReconnect();
            return 'FreeRADIUS Database Query Error: ' . $radiussqlerror;
        }

        $query = "DELETE FROM radcheck WHERE username='$username'";
        $result = mysqli_query($freeradiussql, $query);

        if (!$result) {
            $radiussqlerror = mysqli_error($GLOBALS["___mysqli_ston"]);
            freeradius_WHMCSReconnect();
            return 'FreeRADIUS Database Query Error: ' . $radiussqlerror;
        }

        freeradius_WHMCSReconnect();
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'freeradius',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
        return $e->getMessage();
    }
    return 'success';
}
/**
 * Change the password for an instance of a product/service.
 *
 * Called when a password change is requested. This can occur either due to a
 * client requesting it via the client area or an admin requesting it from the
 * admin side.
 *
 * This option is only available to client end users when the product is in an
 * active status.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/provisioning-modules/module-parameters/
 *
 * @return string "success" or an error message
 */
function freeradius_ChangePassword(array $params){
    try {
        $sqlhost = $params['serverip'];
        $sqldbname = $params['serveraccesshash'];
        $sqlusername = $params['serverusername'];
        $sqlpassword = $params['serverpassword'];

        $freeradiussql = ($GLOBALS["___mysqli_ston"] = mysqli_connect($sqlhost,  $sqlusername,  $sqlpassword));
        mysqli_select_db($GLOBALS["___mysqli_ston"], $sqldbname);

        $query = "SELECT COUNT(*) FROM radcheck WHERE username='$username'";
        $result = mysqli_query($freeradiussql, $query);

        if (!$result) {
            $radiussqlerror = mysqli_error($GLOBALS["___mysqli_ston"]);
            freeradius_WHMCSReconnect();
            return 'FreeRADIUS Database Query Error: ' . $radiussqlerror;
        }

        $data = mysqli_fetch_array($result);
        $count = $data[0];

        if (!$count) {
            freeradius_WHMCSReconnect();
            return 'User Not Found';
        }

        $query = "UPDATE radcheck SET value='$password' WHERE username='$username' AND attribute='User-Password'";
        $result = mysqli_query($freeradiussql, $query);

        if (!$result) {
            $radiussqlerror = mysqli_error($GLOBALS["___mysqli_ston"]);
            freeradius_WHMCSReconnect();
            return 'FreeRADIUS Database Query Error: ' . $radiussqlerror;
        }

        freeradius_WHMCSReconnect();
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'freeradius',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
        return $e->getMessage();
    }
    return 'success';
}
/**
 * Upgrade or downgrade an instance of a product/service.
 *
 * Called to apply any change in product assignment or parameters. It
 * is called to provision upgrade or downgrade orders, as well as being
 * able to be invoked manually by an admin user.
 *
 * This same function is called for upgrades and downgrades of both
 * products and configurable options.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/provisioning-modules/module-parameters/
 *
 * @return string "success" or an error message
 */
function freeradius_ChangePackage(array $params)
{
    try {
        $rate_limit = $params['configoption3'];
        $session_limit = $params['configoption4'];

        $sqlhost = $params['serverip'];
        $sqldbname = $params['serveraccesshash'];
        $sqlusername = $params['serverusername'];
        $sqlpassword = $params['serverpassword'];

        $freeradiussql = ($GLOBALS["___mysqli_ston"] = mysqli_connect($sqlhost,  $sqlusername,  $sqlpassword));
        mysqli_select_db($GLOBALS["___mysqli_ston"], $sqldbname);

        $query = "SELECT COUNT(*) FROM radusergroup WHERE username='$username'";
        $result = mysqli_query($freeradiussql, $query);

        if (!$result) {
            $radiussqlerror = mysqli_error($GLOBALS["___mysqli_ston"]);
            freeradius_WHMCSReconnect();
            return 'FreeRADIUS Database Query Error: ' . $radiussqlerror;
        }

        $data = mysqli_fetch_array($result);
        $count = $data[0];

        if (!$count) {
            freeradius_WHMCSReconnect();
            return 'User Not Found';
        }

        $query = "UPDATE radusergroup SET groupname='$groupname' WHERE username='$username'";
        $result = mysqli_query($freeradiussql, $query);

        if (!$result) {
            $radiussqlerror = mysqli_error($GLOBALS["___mysqli_ston"]);
            freeradius_WHMCSReconnect();
            return 'FreeRADIUS Database Query Error: ' . $radiussqlerror;
        }

        foreach ($params["configoptions"] as $key => $value) {
            if ($key == 'Rate Limit') {
                $rate_limit = $value;
            }

            if ($key == 'Session Limit') {
                $session_limit = $value;
            }
        }

        if ($rate_limit) {
            $query = "UPDATE radreply SET value='$rate_limit' WHERE username='$username' AND attribute='Mikrotik-Rate-Limit'";
            $result = mysqli_query($freeradiussql, $query);

            if (!$result) {
                $radiussqlerror = mysqli_error($GLOBALS["___mysqli_ston"]);
                freeradius_WHMCSReconnect();
                return 'FreeRADIUS Database Query Error: ' . $radiussqlerror;
            }
        }

        if ($session_limit) {
            $query = "UPDATE radcheck SET value='$session_limit' WHERE username='$username' AND attribute='Simultaneous-Use'";
            $result = mysqli_query($freeradiussql, $query);

            if (!$result) {
                $radiussqlerror = mysqli_error($GLOBALS["___mysqli_ston"]);
                freeradius_WHMCSReconnect();
                return 'FreeRADIUS Database Query Error: ' . $radiussqlerror;
            }
        }

        freeradius_WHMCSReconnect();
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'freeradius',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
        return $e->getMessage();
    }

    return 'success';
}
/**
 * Test connection with the given server parameters.
 *
 * Allows an admin user to verify that an API connection can be
 * successfully made with the given configuration parameters for a
 * server.
 *
 * When defined in a module, a Test Connection button will appear
 * alongside the Server Type dropdown when adding or editing an
 * existing server.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/provisioning-modules/module-parameters/
 *
 * @return array
 */
function provisioningmodule_TestConnection(array $params)
{
    try {
        // Call the service's connection test function.
        $success = true;
        $errorMsg = '';
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'provisioningmodule',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
        $success = false;
        $errorMsg = $e->getMessage();
    }
    return array(
        'success' => $success,
        'error' => $errorMsg,
    );
}
/**
 * Additional actions an admin user can invoke.
 *
 * Define additional actions that an admin user can perform for an
 * instance of a product/service.
 *
 * @see provisioningmodule_buttonOneFunction()
 *
 * @return array
 */
function provisioningmodule_AdminCustomButtonArray()
{
    return array(
        "Button 1 Display Value" => "buttonOneFunction",
        "Button 2 Display Value" => "buttonTwoFunction",
    );
}
/**
 * Additional actions a client user can invoke.
 *
 * Define additional actions a client user can perform for an instance of a
 * product/service.
 *
 * Any actions you define here will be automatically displayed in the available
 * list of actions within the client area.
 *
 * @return array
 */
function provisioningmodule_ClientAreaCustomButtonArray()
{
    return array(
        "Action 1 Display Value" => "actionOneFunction",
        "Action 2 Display Value" => "actionTwoFunction",
    );
}
/**
 * Custom function for performing an additional action.
 *
 * You can define an unlimited number of custom functions in this way.
 *
 * Similar to all other module call functions, they should either return
 * 'success' or an error message to be displayed.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/provisioning-modules/module-parameters/
 * @see provisioningmodule_AdminCustomButtonArray()
 *
 * @return string "success" or an error message
 */
function provisioningmodule_buttonOneFunction(array $params)
{
    try {
        // Call the service's function, using the values provided by WHMCS in
        // `$params`.
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'provisioningmodule',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
        return $e->getMessage();
    }
    return 'success';
}
/**
 * Custom function for performing an additional action.
 *
 * You can define an unlimited number of custom functions in this way.
 *
 * Similar to all other module call functions, they should either return
 * 'success' or an error message to be displayed.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/provisioning-modules/module-parameters/
 * @see provisioningmodule_ClientAreaCustomButtonArray()
 *
 * @return string "success" or an error message
 */
function provisioningmodule_actionOneFunction(array $params)
{
    try {
        // Call the service's function, using the values provided by WHMCS in
        // `$params`.
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'provisioningmodule',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
        return $e->getMessage();
    }
    return 'success';
}
/**
 * Admin services tab additional fields.
 *
 * Define additional rows and fields to be displayed in the admin area service
 * information and management page within the clients profile.
 *
 * Supports an unlimited number of additional field labels and content of any
 * type to output.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/provisioning-modules/module-parameters/
 * @see provisioningmodule_AdminServicesTabFieldsSave()
 *
 * @return array
 */
function provisioningmodule_AdminServicesTabFields(array $params)
{
    try {
        // Call the service's function, using the values provided by WHMCS in
        // `$params`.
        $response = array();
        // Return an array based on the function's response.
        return array(
            'Number of Apples' => (int) $response['numApples'],
            'Number of Oranges' => (int) $response['numOranges'],
            'Last Access Date' => date("Y-m-d H:i:s", $response['lastLoginTimestamp']),
            'Something Editable' => '<input type="hidden" name="provisioningmodule_original_uniquefieldname" '
                . 'value="' . htmlspecialchars($response['textvalue']) . '" />'
                . '<input type="text" name="provisioningmodule_uniquefieldname"'
                . 'value="' . htmlspecialchars($response['textvalue']) . '" />',
        );
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'provisioningmodule',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
        // In an error condition, simply return no additional fields to display.
    }
    return array();
}
/**
 * Execute actions upon save of an instance of a product/service.
 *
 * Use to perform any required actions upon the submission of the admin area
 * product management form.
 *
 * It can also be used in conjunction with the AdminServicesTabFields function
 * to handle values submitted in any custom fields which is demonstrated here.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/provisioning-modules/module-parameters/
 * @see provisioningmodule_AdminServicesTabFields()
 */
function provisioningmodule_AdminServicesTabFieldsSave(array $params)
{
    // Fetch form submission variables.
    $originalFieldValue = isset($_REQUEST['provisioningmodule_original_uniquefieldname'])
        ? $_REQUEST['provisioningmodule_original_uniquefieldname']
        : '';
    $newFieldValue = isset($_REQUEST['provisioningmodule_uniquefieldname'])
        ? $_REQUEST['provisioningmodule_uniquefieldname']
        : '';
    // Look for a change in value to avoid making unnecessary service calls.
    if ($originalFieldValue != $newFieldValue) {
        try {
            // Call the service's function, using the values provided by WHMCS
            // in `$params`.
        } catch (Exception $e) {
            // Record the error in WHMCS's module log.
            logModuleCall(
                'provisioningmodule',
                __FUNCTION__,
                $params,
                $e->getMessage(),
                $e->getTraceAsString()
            );
            // Otherwise, error conditions are not supported in this operation.
        }
    }
}
/**
 * Perform single sign-on for a given instance of a product/service.
 *
 * Called when single sign-on is requested for an instance of a product/service.
 *
 * When successful, returns a URL to which the user should be redirected.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/provisioning-modules/module-parameters/
 *
 * @return array
 */
function provisioningmodule_ServiceSingleSignOn(array $params)
{
    try {
        // Call the service's single sign-on token retrieval function, using the
        // values provided by WHMCS in `$params`.
        $response = array();
        return array(
            'success' => true,
            'redirectTo' => $response['redirectUrl'],
        );
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'provisioningmodule',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
        return array(
            'success' => false,
            'errorMsg' => $e->getMessage(),
        );
    }
}
/**
 * Perform single sign-on for a server.
 *
 * Called when single sign-on is requested for a server assigned to the module.
 *
 * This differs from ServiceSingleSignOn in that it relates to a server
 * instance within the admin area, as opposed to a single client instance of a
 * product/service.
 *
 * When successful, returns a URL to which the user should be redirected to.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/provisioning-modules/module-parameters/
 *
 * @return array
 */
function provisioningmodule_AdminSingleSignOn(array $params)
{
    try {
        // Call the service's single sign-on admin token retrieval function,
        // using the values provided by WHMCS in `$params`.
        $response = array();
        return array(
            'success' => true,
            'redirectTo' => $response['redirectUrl'],
        );
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'provisioningmodule',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
        return array(
            'success' => false,
            'errorMsg' => $e->getMessage(),
        );
    }
}
/**
 * Client area output logic handling.
 *
 * This function is used to define module specific client area output. It should
 * return an array consisting of a template file and optional additional
 * template variables to make available to that template.
 *
 * The template file you return can be one of two types:
 *
 * * tabOverviewModuleOutputTemplate - The output of the template provided here
 *   will be displayed as part of the default product/service client area
 *   product overview page.
 *
 * * tabOverviewReplacementTemplate - Alternatively using this option allows you
 *   to entirely take control of the product/service overview page within the
 *   client area.
 *
 * Whichever option you choose, extra template variables are defined in the same
 * way. This demonstrates the use of the full replacement.
 *
 * Please Note: Using tabOverviewReplacementTemplate means you should display
 * the standard information such as pricing and billing details in your custom
 * template or they will not be visible to the end user.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/provisioning-modules/module-parameters/
 *
 * @return array
 */
function provisioningmodule_ClientArea(array $params)
{
    // Determine the requested action and set service call parameters based on
    // the action.
    $requestedAction = isset($_REQUEST['customAction']) ? $_REQUEST['customAction'] : '';
    if ($requestedAction == 'manage') {
        $serviceAction = 'get_usage';
        $templateFile = 'templates/manage.tpl';
    } else {
        $serviceAction = 'get_stats';
        $templateFile = 'templates/overview.tpl';
    }
    try {
        // Call the service's function based on the request action, using the
        // values provided by WHMCS in `$params`.
        $response = array();
        $extraVariable1 = 'abc';
        $extraVariable2 = '123';
        return array(
            'tabOverviewReplacementTemplate' => $templateFile,
            'templateVariables' => array(
                'extraVariable1' => $extraVariable1,
                'extraVariable2' => $extraVariable2,
            ),
        );
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'provisioningmodule',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
        // In an error condition, display an error page.
        return array(
            'tabOverviewReplacementTemplate' => 'error.tpl',
            'templateVariables' => array(
                'usefulErrorHelper' => $e->getMessage(),
            ),
        );
    }
}

function freeradius_WHMCSReconnect() {
    require( ROOTDIR . "/configuration.php" );

    $whmcsmysql = ($GLOBALS["___mysqli_ston"] = mysqli_connect($db_host,  $db_username,  $db_password));
    mysqli_select_db($GLOBALS["___mysqli_ston"], $db_name);
}

function date_range($nextduedate, $billingcycle) {
    $day = substr($nextduedate, 8, 2);
    $year = substr($nextduedate, 0, 4);
    $month = substr($nextduedate, 5, 2);


    if ($billingcycle == 'Monthly') {
        $new_time = mktime( 0, 0, 0, $month - 1, $day, $year );
    } else if ($billingcycle == 'Quarterly') {
        $new_time = mktime( 0, 0, 0, $month - 3, $day, $year );
    } else if ($billingcycle == 'Semi-Annually') {
        $new_time = mktime(0, 0, 0, $month - 6, $day, $year);
    } else if ($billingcycle == 'Annually') {
        $new_time = mktime(0, 0, 0, $month, $day, $year - 1);
    } else if ($billingcycle == 'Biennially') {
        $new_time = mktime(0, 0, 0, $month, $day, $year - 2);
    }

    $startdate = date('Y-m-d', $new_time );
    $enddate = '';

    if (date('Ymd', $new_time) >= date('Ymd')) {
        if ($billingcycle == 'Monthly') {
            $new_time = mktime(0, 0, 0, $month - 2, $day, $year);
        } else if ($billingcycle == 'Quarterly') {
            $new_time = mktime(0, 0, 0, $month - 6, $day, $year);
        } else if ($billingcycle == 'Semi-Annually') {
            $new_time = mktime(0, 0, 0, $month - 12, $day, $year);
        } else if ($billingcycle == 'Annually'){
            $new_time = mktime(0, 0, 0, $month, $day, $year - 2);
        } else if ($billingcycle == 'Biennially') {
            $new_time = mktime(0, 0, 0, $month, $day, $year - 4);
        }

        $startdate = date( "Y-m-d", $new_time );

        if ($billingcycle == 'Monthly') {
            $new_time = mktime(0, 0, 0, $month - 1, $day, $year);
        } else if ($billingcycle == 'Quarterly') {
            $new_time = mktime(0, 0, 0, $month - 3, $day, $year);
        } else if ($billingcycle == 'Semi-Annually') {
            $new_time = mktime(0, 0, 0, $month - 6, $day, $year);
        } else if ($billingcycle == 'Annually') {
            $new_time = mktime(0, 0, 0, $month, $day, $year - 1);
        } else if ($billingcycle == 'Biennially') {
            $new_time = mktime(0, 0, 0, $month, $day, $year - 2);
        }

        $enddate = date('Y-m-d', $new_time );
    }

    return array(
        'enddate' => $enddate,
        'startdate' => $startdate
    );
}

function collect_usage($params){
    $username = $params['username'];
    $serviceid = $params['serviceid'];

    $groupname = $params['configoption1'];
    $usage_limit = $params['configoption2'];

    $sqlhost = $params['serverip'];
    $sqldbname = $params['serveraccesshash'];
    $sqlusername = $params['serverusername'];
    $sqlpassword = $params['serverpassword'];

    $status = "Offline";
    $usage_limit = 0;

    $result = select_query(
      'tblhosting',
      'nextduedate, billingcycle',
      array('id' => $serviceid)
    );

    $data = mysqli_fetch_array($result);
    $date_range = date_range($data['nextduedate'], $data['billingcycle']);

    $enddate = $date_range['enddate'];
    $startdate = $date_range['startdate'];

    $freeradiussql = ($GLOBALS["___mysqli_ston"] = mysqli_connect($sqlhost,  $sqlusername,  $sqlpassword));
    mysqli_select_db($GLOBALS["___mysqli_ston"], $sqldbname);

    $query = "SELECT COUNT(*) AS logins,SUM(radacct.AcctSessionTime) AS logintime,SUM(radacct.AcctInputOctets) AS uploads,SUM(radacct.AcctOutputOctets) AS downloads,SUM(radacct.AcctOutputOctets) + SUM(radacct.AcctInputOctets) AS total FROM radacct WHERE radacct.Username='$username' AND radacct.AcctStartTime>='".$startdate."'";

    if ($enddate) {
        $query .= " AND radacct.AcctStartTime<='".$startdate."'";
    }

    $query .= " ORDER BY AcctStartTime DESC";

    $result = mysqli_query( $freeradiussql, $query);
    $data = mysqli_fetch_array($result);

    $total = $data[4];
    $logins = $data[0];
    $uploads = $data[2];
    $downloads = $data[3];
    $logintime = $data[1];

    $query = "SELECT radacct.AcctStartTime as start, radacct.AcctStopTime as stop FROM radacct WHERE radacct.Username='$username' ORDER BY AcctStartTime DESC LIMIT 0,1";
    $result = mysqli_query($freeradiussql, $query);
    $data = mysqli_fetch_array($result);

    $sessions = mysqli_num_rows($result);

    $end = $data[1];
    $start = $data[0];

    if ($end) {
        $status = 'Logged in at ' . $start;
    }

    if ($sessions < 1) {
        $status = 'No logins';
    }

    freeradius_WHMCSReconnect();

    if (empty($usage_limit) && !is_numeric($usage_limit)) {
        $usage_limit = 0;
    }

    foreach ($params['configoptions'] as $key => $value) {
        $Megabytes = 0;
        $Gigabytes = 0;

        if ($key == 'Megabytes') {
            if (is_numeric($value)) {
                $Megabytes = $value * 1024 * 1024;
            }
        }

        if ($key == 'Gigabytes') {
            if (is_numeric($value)) {
                $Gigabytes = $value * 1024 * 1024 * 1024;
            }
        }

        if (($Megabytes > 0) || ($Gigabytes > 0)) {
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

function secs_to_h($secs) {
    $s = "";
    $units = array(
        "day" => 24*3600,
        "hour" => 3600,
        "week" => 7*24*3600,
        "minute" => 60
    );

    if ( $secs == 0 ) return "0 seconds";
    if ( $secs < 60 ) return "{$secs} seconds";

    foreach ($units as $name => $divisor) {
        if ($quot = intval($secs / $divisor)) {
            $s .= $quot." ".$name;
            $s .= (abs($quot) > 1 ? "s" : "") . ", ";
            $secs -= $quot * $divisor;
        }
    }

    return substr($s, 0, -2);
}

function byte_size($bytes) {
    $size = $bytes / 1024;

    if ($size < 1024) {
        $size = number_format($size, 2);
        $size .= ' KB';
    } else if ($size / 1024 < 1024) {
        $size = number_format($size / 1024, 2);
        $size .= ' MB';
    } else if ($size / 1024 / 1024 < 1024) {
        $size = number_format($size / 1024 / 1024, 2);
        $size .= ' GB';
    }

    return $size;
}
