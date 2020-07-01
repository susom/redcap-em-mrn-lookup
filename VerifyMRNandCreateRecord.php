<?php
namespace Stanford\MrnLookUp;
/** @var \Stanford\MrnLookUp\MrnLookUp $module **/

use REDCap;
use Exception;

$pid = isset($_GET['pid']) && !empty($_GET['pid']) ? $_GET['pid'] : null;
$mrn = isset($_POST['mrn']) && !empty($_POST['mrn']) ? $_POST['mrn'] : null;
$action = isset($_POST['action']) && !empty($_POST['action']) ? $_POST['action'] : null;

$user = USERID;

if ($action === "verify") {

    // Log who is asking for the request
    $module->emDebug("User $user is requesting MRN LookUp for patient MRN $mrn for project $pid");

    // Find the name of the record id field
    $recordFieldName = REDCap::getRecordIdField();

    // Retrieve locations on where to store the data
    $projSettings = $module->getProjectSettings();

    // Check for blank MRN and see whether or not the config specifies we should create a new record

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

        // Go to the record page
        $module->emDebug("Found mrn $mrn in record $record_string for project $pid");
        $record_home = $_SERVER["HTTP_ORIGIN"] . APP_PATH_WEBROOT . '/DataEntry/record_home.php?pid=' . $pid . '&id=' . $record_string;

        print json_encode(array(
            "status" => 1,
            "url" => $record_home));
        return;
    }

    // Verify the IRB is valid and retrieve the privacy attestation to make sure we have the correct privileges
    $return = $module->checkIRBAndGetAttestation($pid, $projSettings['dob']['value']);
    if (!$return["status"]) {
        print json_encode($return);
        return;
    }

    // Retrieve a token to the ID manager so we can send the MRN validity request check
    $return = $module->retrieveIdToken();
    if (!$return["status"]) {
        print json_encode($return);
        return;
    }

    // Send the request to verify the MRN
    $valid_mrn = $module->apiPost($pid, $mrn, $return["token"], $return["url"]);
    if (!$valid_mrn["status"]) {

        print json_encode($valid_mrn);
        return;

    } else if (empty($valid_mrn["person"])) {

        // If the response is empty, this MRN is invalid
        // See if the user wants to be asked to create a record anyway
        $query_for_new_record = $module->getProjectSetting("query_for_nonvalid_mrns");
        if ($query_for_new_record) {
            $message = " The MRN number " . $mrn . " is invalid <br><br>" .
                "If you would like to create a new record anyway, select the 'Save' button.<br>" .
                "To cancel, select the 'Cancel' button";
            print json_encode(array("status" => 3,
                "message" => $message));
            $module->emDebug("For user $user, mrn $mrn is invalid for project $pid. User will be asked to create record anyways.");
        } else {
            $message = "* MRN is invalid";
            $module->emDebug("For user $user, mrn $mrn is invalid for project $pid");
            print json_encode(array("status" => 0,
                "message" => $message));
        }
        return;
    } else {

        // The MRN is valid. Display the name and DoB so the user can make sure it is the correct person.
        $message = " The person with MRN " . $mrn . " is: <br><br>" .
                   "<ul style='list-style:none'>" .
                   "<li>Name: &nbsp;" . ucwords(strtolower($valid_mrn["person"]['firstName'])) ." " . ucwords(strtolower($valid_mrn["person"]["lastName"])) . "</li>";
        if ($projSettings['dob']['value']) {
            $message .= "<li>DoB:  &nbsp;&nbsp;&nbsp;" . substr($valid_mrn["person"]["birthDate"], 0, 10) . "</li>";
        }
        $message .= "</ul>" .
                   "If this is the correct person and you would like to create a new record,<br>" .
                   "select the 'Save' button. To cancel, select the 'Cancel' button";

        $module->emDebug("Person Info: " . json_encode($valid_mrn["person"]));
        $module->emDebug("Display message: " . $message);
        print json_encode(array(
            "status"            => 2,
            "message"           => $message,
            "demographics"      => json_encode($valid_mrn["person"]))
        );
        return;
    }

} else if ($action == "save") {

    $demographics = isset($_POST['demographics']) && !empty($_POST['demographics']) ? $_POST['demographics'] : null;
    $demo = json_decode($demographics, true);

    // Retrieve locations on where to store the data
    $projSettings = $module->getProjectSettings();

    // Find the name of the record id field
    $recordFieldName = REDCap::getRecordIdField();

    // Create a new record in this project and save the data specified in the config
    //
    // This is the format of $personInfo
    // {"mrn":"xxxxxxxx","birthDate":"yyyy-mm-ddThh:mi:ss-0000","firstName":"Jane","lastName":"Doe","gender":"Female","canonicalEthnicity":"Non-Hispanic","canonicalRace":"White"
    //
    // This is the format of $projSettings
    //{"mrn":{"value":"mrn"},"first_name":{"value":"first_name"},"last_name":{"value":"last_name"},"dob":{"value":"dob"},"record_name_prefix":{"value":"Study-"},"gender":{"value":"gender"},"ethnicity":{"value":"ethnicity"},"race":{"value":"race"}, "number_pad_size":{"value":"5"}}
    //

    // Find the next record ID to use for the new record
    $number_padding_size = $projSettings["number_pad_size"]["value"];
    $newRecordID = findNextRecordNumber($projSettings["record_name_prefix"]["value"], $number_padding_size, $recordFieldName);

    // Save the data that the user asked for. Record ID and MRN are required.
    $newMRNRecord = array();
    $newMRNRecord[$recordFieldName] = $newRecordID;
    $newMRNRecord[$projSettings["mrn"]["value"]] = $mrn;

    // See what data the project will start.  Make first/last name camelcase.
    if (!empty($projSettings["first_name"]["value"])) {
        $newMRNRecord[$projSettings["first_name"]["value"]] = ucwords(strtolower($demo['firstName']));
    }
    if (!empty($projSettings["last_name"]["value"])) {
        $newMRNRecord[$projSettings["last_name"]["value"]] = ucwords(strtolower($demo["lastName"]));
    }
    if (!empty($projSettings["dob"]["value"])) {
        $newMRNRecord[$projSettings["dob"]["value"]] = substr($demo["birthDate"], 0, 10);
    }
    if (!empty($projSettings["gender"]["value"])) {
        $newMRNRecord[$projSettings["gender"]["value"]] = $demo["gender"];
    }
    if (!empty($projSettings["ethnicity"]["value"])) {
        $newMRNRecord[$projSettings["ethnicity"]["value"]] = $demo["canonicalEthnicity"];
    }
    if (!empty($projSettings["race"]["value"])) {
        $newMRNRecord[$projSettings["race"]["value"]] = $demo["canonicalRace"];
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


/**
 * This function will create the next record label based on the inputs from the config file
 * and the existing records.
 *
 * @param $record_prefix - user entered in config
 * @param $number_padding_size - user entered number length in config
 * @param $recordFieldName - fieldname in project of record id
 * @return string - new record label
 */
function findNextRecordNumber($record_prefix, $number_padding_size, $recordFieldName) {
    global $module, $pid;

    $module->emDebug("Record prefix $record_prefix, num padding size $number_padding_size, record Field Name $recordFieldName");
    $filter = "starts_with([" . $recordFieldName . "],'" .$record_prefix . "')";
    $record_field_array = array($recordFieldName);
    $recordIDs = REDCap::getData($pid, 'array', null, $record_field_array, null, null, null, null, null, $filter);
    $module->emDebug("Record ids: " . json_encode($recordIDs));

    // Get the part of the record name after the prefix.  Changing to uppercase in case someone hand enters a record
    // and uses the same prefix with different case.
    $record_array_noprefix = array();
    foreach($recordIDs as $record_num => $recordInfo) {
        $record_noprefix = trim(str_replace(strtoupper($record_prefix), "", strtoupper($record_num)));
        if (is_numeric($record_noprefix)) {
            $record_array_noprefix[] = $record_noprefix;
        }
    }

    $module->emDebug("Records with same prefix: " . json_encode($record_array_noprefix));
    // Retrieve the max value so we can add one to create the new record label
    $highest_record_number = max($record_array_noprefix);
    $module->emDebug("Highest record_number: " . $highest_record_number);
    if (!empty($number_padding_size)) {
        $numeric_part = str_pad(($highest_record_number + 1), $number_padding_size, '0', STR_PAD_LEFT);
    } else {
        $numeric_part = ($highest_record_number + 1);
    }
    $newRecordLabel = $record_prefix . $numeric_part;
    $module->emDebug("New record label: " . $newRecordLabel);

    return $newRecordLabel;
}
