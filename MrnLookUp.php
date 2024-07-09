<?php
namespace Stanford\MrnLookUp;

require_once "emLoggerTrait.php";

use Exception;

class MrnLookUp extends \ExternalModules\AbstractExternalModule
{
    use emLoggerTrait;

    // -- Hook functions
    /**
     * Add/Edit Records Page
     *    Hook to allow the Add new record button to be overwritten with an Add a new MRN button
     *
     * @param $project_id
     * @param $instrument
     * @param $event_id
     */
    function redcap_add_edit_records_page($project_id, $instrument, $event_id) {

        // Override the 'Add new record' button with the 'Add a new MRN' button
        $this->overrideNewRecordButton("AddRecord");
    }

    /**
     *  Record Status Dashboard Page
     *     Hook to allow the Add new record button to be overwritten with an Add a new MRN button
     */
    function redcap_every_page_before_render($project_id) {

        if (PAGE === 'DataEntry/record_status_dashboard.php') {
            // Override the 'Add new record' button with the 'Add a new MRN' button
            $this->overrideNewRecordButton("Dashboard");
        }
    }


    // -- Functions used to replace the Add new record button

    /**
     *  This function is called from the Dashboard and the Add/Edit Record pages.  These pages have the Add New Record button
     *  which we are overriding. I didn't find any other page with the Add New Record button.
     */
    function overrideNewRecordButton($page) {

        // Find the URL to the MRN Verifier
        $url = $this->getUrl("VerifyMRNandCreateRecord.php");
        $allow_blank_mrn = $this->getProjectSetting('allow_blank_mrns');

        // Retrieve the html that will create the modal and overwrite the Add new record button
        $modal = $this->createHTMLModal();

        ?>

        <!-- Look for the 'Add new record' button and override it with the 'Add a new MRN' button -->
        <script type="text/javascript">

            window.onload = function() {
                var page = '<?php echo $page; ?>';
                var buttonElement;

                // Find the 'Add new record' button on the page that we are on. The Dashboard has the button within a <div>
                // and the Add/Edit Records page has the button with a <td>
                if (page === 'Dashboard') {
                    buttonElement = document.querySelectorAll("div > button");
                } else if (page === "AddRecord") {
                    buttonElement = document.querySelectorAll("td > button");
                }

                for (var ncnt = 0; ncnt < buttonElement.length; ncnt++) {
                    var button = buttonElement[ncnt];
                    if (button.innerHTML.includes('Add new record')) {
                        var parent = button.parentNode;
                        parent.innerHTML = '<?php echo $modal; ?>';
                    }
                }
            };


            /**
             * This function is called when the 'Verify MRN' button on the modal is selected.
             * A post is made to VerifyMRNandCreateRecord.php to see if the MRN is valid and a record has not
             * been created yet.
             */
            function verifyMRN() {

                var url = '<?php echo $url; ?>';
                var allow_blank_mrns = '<?php echo $allow_blank_mrn; ?>';
                var redcap_csrf_token = '<?php echo $this->getCSRFToken(); ?>';

                document.getElementById('savebuttonid').style.display = 'none';
                var newMRN = document.getElementById('newMRN').value;

                if ((allow_blank_mrns === '1') && (newMRN.length === 0)) {
                    document.getElementById('messages').style.color = 'red';
                    document.getElementById('messages').innerHTML = '* Select Save to create a new record with a blank MRN';
                    document.getElementById('savebuttonid').style.display = 'inline';
                } else if (newMRN.length > 10 || newMRN.length < 7) {
                    document.getElementById('messages').style.color = 'red';
                    document.getElementById('messages').innerHTML = "* MRN can only be 7-10 numbers";
                    return;
                } else if (isNaN(newMRN)) {
                    document.getElementById('messages').style.color = 'red';
                    document.getElementById('messages').innerHTML = "* MRN can only be numbers";
                    return;
                } else {
                    document.getElementById('messages').innerHTML = "";

                    $.ajax({
                        type: "POST",
                        url: url,
                        data: { "mrn"               : newMRN,
                                "redcap_csrf_token" : redcap_csrf_token,
                                "action"            : "verify"
                        },
                        success: function(data, textStatus, jqXHR) {

                            var data_array;
                            if (data.length != 0) {
                                data_array = JSON.parse(data);
                            }

                            // Check return status to see what we should do.
                            // Status = 0 - Found some type of error
                            // Status = 1 - MRN already in project - go to that record
                            // Status = 2 - Found MRN, display demographics
                            // Status = 3 - MRN not found but ask if they want to create a record anyways
                            if (data_array.status === 0) {
                                document.getElementById('messages').style.color = 'red';
                                document.getElementById('messages').innerHTML = data_array.message;
                            } else if (data_array.status === 1) {
                                document.getElementById('newMRN').value = '';
                                window.open(data_array.url, '_self');
                            } else if (data_array.status === 2) {
                                document.getElementById('messages').style.color = 'black';
                                document.getElementById('messages').innerHTML = data_array.message;
                                document.getElementById('demographics').innerHTML = data_array.demographics;
                                document.getElementById('savebuttonid').style.display = 'inline';
                            } else if (data_array.status === 3) {
                                document.getElementById('messages').style.color = 'red';
                                document.getElementById('messages').innerHTML = data_array.message;
                                document.getElementById('savebuttonid').style.display = 'inline';
                            }
                        },
                        error: function(hqXHR, textStatus, errorThrown) {
                        }
                    });
                }
            }

            /**
             * This function is called when the 'Save MRN' button on the modal is selected.
             * A post is made to VerifyMRNandCreateRecord.php to save a new record with this MRN.
             */
            function saveMRN() {

                var url = '<?php echo $url; ?>';
                var newMRN = document.getElementById('newMRN').value;
                var demographics = document.getElementById('demographics').innerHTML;
                var redcap_csrf_token = '<?php echo $this->getCSRFToken(); ?>';

                $.ajax({
                    type: "POST",
                    url: url,
                    data: { "mrn"                : newMRN,
                            "demographics"       : demographics,
                            "redcap_csrf_token"  : redcap_csrf_token,
                            "action"             : "save"},
                    success: function(data, textStatus, jqXHR) {

                        var data_array = JSON.parse(data);

                        // Check return status to see what we should do.
                        // Status = 0 - Found some type of error
                        // Status = 1 - MRN record was created - go to that record
                        if (data_array.status === 0) {
                            document.getElementById('messages').style.color = 'red';
                            document.getElementById('messages').innerHTML = data_array.message;
                        } else if (data_array.status === 1) {
                            document.getElementById('newMRN').value = '';
                            window.open(data_array.url, '_self');
                       }
                    },
                    error: function(hqXHR, textStatus, errorThrown) {
                    }
                });
            }

            /**
             * Clear the modal fields and close it.
             */
            function closeModal() {
                document.getElementById('newMRN').value = '';
                document.getElementById('messages').innerHTML = '';
                document.getElementById('demographics').innerHTML = '';
                document.getElementById('savebuttonid').style.display = 'none';

                $('#mrnmodal').modal("hide");
            }

        </script>

        <?php

    }

    /**
     * HTML to create new button and create modal so user can input an MRN
     *
     * @return string - html to overwrite the Add new record button with the Add a new MRN button and creates a modal to input the MRN
     */
    function createHTMLModal() {

        // Substitute the Add a new record button with a button that will open the modal
        $modal  = '<button class="btn btn-xs btn-rcgreen fs13" data-bs-toggle="modal" data-bs-target="#mrnmodal">';
        $modal .= '    <i class="fas fa-plus"></i> Add a new MRN';
        $modal .= '</button>';

        // Make a modal so users can enter a new MRN
        $modal .= '<div id="mrnmodal" class="modal" tabindex="-1" role="dialog">';
        $modal .= '    <div class="modal-dialog" role="document">';
        $modal .= '        <div class="modal-content">';
        $modal .= '            <div class="modal-header" style="background-color:maroon;color:white">';
        $modal .= '                <h5 class="modal-title">MRN of the person you are trying to find</h5>';
        $modal .= '                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">';
        $modal .= '                    <span style="color:white;" aria-hidden="true">&times;</span>';
        $modal .= '                </button>';
        $modal .= '            </div>';    // modal header
        $modal .= '            <div class="modal-body text-left">';
        $modal .= '                <div id="demographics" style="display:none"></div>';
        $modal .= '                <div style="margin:20px 0 0 0;font-weight:bold;" > ';
        $modal .= '                     Enter an 8-10 character MRN (no dashes): ';
        $modal .= '                    <input id="newMRN" size="10px">';
        $modal .= '                     <input style="left-margin:10px" type="submit" onclick="verifyMRN()" value="Verify MRN">';
        $modal .= '                </div>';
        $modal .= '                <div>';
        $modal .= '                     <span style="font-weight:normal;">(ex: 12345678 or 01234567)</span>';
        $modal .= '                </div>';
        $modal .= '                <div id="messages" style="margin-top:10px;"></div>';
        $modal .= '                <div style="margin-top:40px; text-align:right;">';
        $modal .= '                    <input type="button" id="savebuttonid" style="display:none; margin-right:10px" onclick="saveMRN()" value="Save">';
        $modal .= '                    <input type="button" onclick="closeModal()" value="Cancel">';
        $modal .= '                </div>';
        $modal .= '            </div>';     // Modal body
        $modal .= '        </div>';         // Modal content
        $modal .= '     </div>';            // Mmdal dialog
        $modal .= '</div>';                 // modal

        return $modal;
    }


    function checkIRBAndGetAttestation($pid, $dob_requested=null)
    {
        // Instantiate the IRB Lookup module
        try {
            $IRBL = \ExternalModules\ExternalModules::getModuleInstance('irb_lookup');
        } catch (Exception $ex) {
            $msg = "The IRB Lookup module is not enabled, please contact REDCap support.";
            $this->emError($msg);
            return (array("status" => false,
                "message" => $msg));
        }

        // Retrieve the IRB Number entered into the project setup page.
        $irb_number = $IRBL->findIRBNumber($pid);
        if (is_null($irb_number)) {
            $msg = "The IRB Number is null for this project. Please modify your Project Settings to include the IRB number.";
            $this->emError($msg);
            return array("status" => false,
                "message" => $msg);
        }

        // Before checking the ID API, make sure the IRB is valid and the privacy attestation allows them to
        // see MRN, names and Dob
        $settings = $IRBL->getPrivacySettings($irb_number, $pid);
        if ($settings == false || !$settings['status']) {
            $this->emError("IRB/Privacy status is valid: " . $settings['message']);
            return array("status" => false,
                "message" => $settings['message']);
        }

        // Check to see if we are storing the birth date.  If not, don't check for privacy dates
        $dob_required = (is_null($dob_requested) ? 0 : 1);
        $privacy_settings = $settings['privacy'];
        if ($dob_required) {
            $needed_privacy = $privacy_settings['approved'] &&
                $privacy_settings['demographic']['phi_approved']['fullname'] &&
                $privacy_settings['demographic']['phi_approved']['mrn'] &&
                $privacy_settings['demographic']['phi_approved']['dates'];
            $priv = 'MRNs, full name and dates.';
        } else {
            $needed_privacy = $privacy_settings['approved'] &&
                $privacy_settings['demographic']['phi_approved']['fullname'] &&
                $privacy_settings['demographic']['phi_approved']['mrn'];
            $priv = 'MRNs and full name.';
        }
        if (!$needed_privacy) {
            $msg = "This attestation for IRB $irb_number does not have the correct privileges. <br>The necessary priveleges are " . $priv;
            $this->emError($msg);
            return array("status" => false,
                "message" => $msg);
        }

        return array("status" => true);
    }

    /**
     * This function gets a handle to the vertex token manager and requests a valid
     * token to the Identifiers API
     *
     * @return array
     */
    function retrieveIdToken() {

        // Find the vertex_token_manager to ask for a valid token to the IRB API
        try {
            $VTM = \ExternalModules\ExternalModules::getModuleInstance('vertx_token_manager');
        } catch (Exception $ex) {
            $msg = "The Vertx Token Manager module is not enabled, please contact REDCap support.";
            $this->emError($msg);
            return array("status" => false,
                "message" => $msg);
        }

        // Get a valid API token from the vertx token manager
        $service = "id";
        $token = $VTM->findValidToken($service);
        if ($token == false) {
            $this->emError("Could not retrieve valid access token for service $service");
            return array("status" => false,
                "message" => "* Internal Access problem - please contact the REDCap team");
        }

        // Retrieve ID URL from the system settings
        $api_url = $this->getSystemSetting("url_to_id_api");

        return array(
                "status" => true,
                "token" => $token,
                "url" => $api_url
        );
    }


    function apiPost($pid, $mrn, $token, $url) {

        // Use Susan's API to see if this MRN is valid and if so, retrieve name, dob,
        $body = array("mrns" => array($mrn));
        $timeout = null;
        $content_type = 'application/json';
        $basic_auth_user = null;
        $headers = array("Authorization: Bearer " . $token);

        // Call the API to verify the MRN and retrieve data if valid
        $result = http_post($url, $body, $timeout, $content_type, $basic_auth_user, $headers);
        if (is_null($result)) {
            $this->emError("Problem with API call to " . $url . " for project $pid");
            return array("status" => false,
                "message" => "* Could not verify MRN. Please contact REDCap team for help");
        } else {
            $returnData = json_decode($result, true);
            $personInfo = $returnData["result"][0];
        }

        return array(
                "status" => true,
                "person" => $personInfo
                    );
    }

}
