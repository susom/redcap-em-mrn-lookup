<?php
namespace Stanford\MrnLookUp;
/** @var \Stanford\MrnLookUp\MrnLookUp $module **/

use REDCap;

$pid = isset($_GET['pid']) && !empty($_GET['pid']) ? $_GET['pid'] : null;
$mrn = isset($_POST['mrn']) && !empty($_POST['mrn']) ? $_POST['mrn'] : null;

$user = USERID;

// Log who is asking for what
$module->emDebug("User $user is requesting MRN LookUp for patient MRN $mrn for project $pid");

// Find the name of the record id field
$recordFieldName = REDCap::getRecordIdField();

// Retrieve locations on where to store the data
$projSettings = $module->getProjectSettings();

// First check to see if there is already a record created with this MRN
$filter = "[" . $projSettings["mrn"]["value"] . "]= '" . $mrn . "'";
$duplicate = REDCap::getData($pid, 'array', null, array($recordFieldName), null, null, null, null, null, $filter);
if (!empty($duplicate)) {

    $oldRecordID = array_keys($duplicate);
    if (is_array($oldRecordID)) {
        $record_string = implode(',', $oldRecordID);
    } else {
        $record_string = $oldRecordID;
    }

    // Launch a new tab with the record page
    $module->emDebug("Found mrn $mrn in record $record_string for project $pid");
    $record_home = $_SERVER["HTTP_ORIGIN"] . APP_PATH_WEBROOT . '/DataEntry/record_home.php?pid=' .$pid . '&id=' . $record_string;

    print json_encode(array("status" => 1,
                            "url" => $record_home));
    return;
}

// Get a valid API token from the vertx token manager
$service = "id";
$VTM = \ExternalModules\ExternalModules::getModuleInstance('vertx_token_manager');
$token = $VTM->findValidToken($service);

if ($token == false) {
    $module->emError("Could not retrieve valid access token for service $service");
    print json_encode(array("status" => 0,
                            "message" => "* Internal Access problem - please contact the REDCap team"));
    return;
} else {

    // Retrieve ID URL from the system settings
    $api_url = $module->getSystemSetting("url_to_id_api");

    // Use Susan's API to see if this MRN is valid and if so, retrieve name, dob,
    $body = array("mrns" => array($mrn));
    $timeout = null;
    $content_type = 'application/json';
    $basic_auth_user = null;
    $headers = array("Authorization: Bearer " . $token);

    // Call the API to verify the MRN and retrieve data if valid
    $result = http_post($api_url, $body, $timeout, $content_type, $basic_auth_user, $headers);
    $returnData = json_decode($result, true);
    $personInfo = $returnData["result"][0];
}

if (empty($personInfo)) {
    $module->emDebug("Mrn $mrn is invalid for project $pid");
    print json_encode(array("status" => 0,
                            "message" => "* MRN is invalid"));
    return;
} else {

    // Create a new record in this project and save the data specified in the config
    //
    // This is the format of $personInfo
    // {"mrn":"xxxxxxxx","birthDate":"yyyy-mm-ddThh:mi:ss-0000","firstName":"Jane","lastName":"Doe","gender":"Female","canonicalEthnicity":"Non-Hispanic","canonicalRace":"White"
    //
    // This is the format of $projSettings
    //{"mrn":{"value":"mrn"},"first_name":{"value":"first_name"},"last_name":{"value":"last_name"},"dob":{"value":"dob"},"record_name_prefix":{"value":"Study-"},"gender":{"value":"gender"},"ethnicity":{"value":"ethnicity"},"race":{"value":"race", "number_pad_size":{"value":"5"}}
    //

    // Find the next record ID to use for the new record
    $number_padding_size = $projSettings["number_pad_size"]["value"];
    $newRecordID = findNextRecordNumber($projSettings["record_name_prefix"]["value"], $number_padding_size, $recordFieldName);

    // Save the data that the user asked for. Record ID and MRN are required.
    $newMRNRecord = array();
    $newMRNRecord[$recordFieldName] = $newRecordID;
    $newMRNRecord[$projSettings["mrn"]["value"]] = $personInfo["mrn"];

    // See what data the project will start.  Make first/last name camelcase.
    if (!empty($projSettings["first_name"]["value"])) {
        $newMRNRecord[$projSettings["first_name"]["value"]] = ucwords(strtolower($personInfo["firstName"]));
    }
    if (!empty($projSettings["last_name"]["value"])) {
        $newMRNRecord[$projSettings["last_name"]["value"]] = ucwords(strtolower($personInfo["lastName"]));
    }
    if (!empty($projSettings["dob"]["value"])) {
        $newMRNRecord[$projSettings["dob"]["value"]] = substr($personInfo["birthDate"], 0, 10);
    }
    if (!empty($projSettings["gender"]["value"])) {
        $newMRNRecord[$projSettings["gender"]["value"]] = $personInfo["gender"];
    }
    if (!empty($projSettings["ethnicity"]["value"])) {
        $newMRNRecord[$projSettings["ethnicity"]["value"]] = $personInfo["canonicalEthnicity"];
    }
    if (!empty($projSettings["race"]["value"])) {
        $newMRNRecord[$projSettings["race"]["value"]] = $personInfo["canonicalRace"];
    }

    // Save the new record
    $return = REDCap::saveData($pid, 'json', json_encode(array($newRecordID => $newMRNRecord)));
    $module->emDebug("Return from creating new record $newRecordID: " . json_encode($return));

    // Put together the URL to the new record
    $record_home = $_SERVER["HTTP_ORIGIN"] . APP_PATH_WEBROOT . '/DataEntry/record_home.php?pid=' .$pid . '&id=' . $newRecordID;
    if (!empty($return["errors"])) {
        $module->emDebug("Error creating new record $newRecordID for mrn $mrn in project $pid");
        print json_encode(array("status"    => 0,
                                "message"   => '* Problem saving new record. Please contact REDCap team'));
    } else {
        $module->emDebug("Successfully created new record $newRecordID for mrn $mrn in project $pid");
        print json_encode(array("status"    => 1,
                                "url"       =>  $record_home));
    }
}

return;


function findNextRecordNumber($record_prefix, $number_padding_size, $recordFieldName) {
    global $module, $pid;

    $filter = "starts_with([" . $recordFieldName . "],'" .$record_prefix . "')";
    $record_field_array = array($recordFieldName);
    $recordIDs = REDCap::getData($pid, 'array', null, $record_field_array, null, null, null, null, null, $filter);

    // Get the part of the record name after the prefix.  Changing to uppercase in case someone hand enters a record
    // and uses the same prefix with different case.
    $record_array_noprefix = array();
    foreach($recordIDs as $record_num => $recordInfo) {
        $record_noprefix = trim(str_replace(strtoupper($record_prefix), "", strtoupper($record_num)));
        if (is_numeric($record_noprefix)) {
            $record_array_noprefix[] = $record_noprefix;
        }
    }

    // Retrieve the max value so we can add one to create the new record label
    $highest_record_number = max($record_array_noprefix);
    if (!empty($number_padding_size)) {
        $numeric_part = str_pad(($highest_record_number + 1), $number_padding_size, '0', STR_PAD_LEFT);
    } else {
        $numeric_part = ($highest_record_number + 1);
    }
    $newRecordLabel = $record_prefix . $numeric_part;

    return $newRecordLabel;
}