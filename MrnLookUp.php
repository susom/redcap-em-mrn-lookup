<?php
namespace Stanford\MrnLookUp;

require_once "emLoggerTrait.php";


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
        $this->emDebug("This is the URL: " . $url);
        $allow_blank_mrn = $this->getProjectSetting('allow_blank_mrns');
        $this->emDebug("Allow Blank MRNS: " . $allow_blank_mrn);

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

                document.getElementById('savebuttonid').style.display = 'none';
                var newMRN = document.getElementById('newMRN').value;

                if ((allow_blank_mrns === '1') && (newMRN.length === 0)) {
                    document.getElementById('messages').style.color = 'red';
                    document.getElementById('messages').innerHTML = '* Select Save to create a new record with a blank MRN';
                    document.getElementById('savebuttonid').style.display = 'inline';
                } else if (newMRN.length !== 8) {
                    document.getElementById('messages').style.color = 'red';
                    document.getElementById('messages').innerHTML = "* MRN must be exactly 8 numbers";
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
                        data: {"mrn"        : newMRN,
                            "action"     : "verify"},
                        success: function(data, textStatus, jqXHR) {

                            var data_array = JSON.parse(data);

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

                $.ajax({
                    type: "POST",
                    url: url,
                    data: {"mrn"            : newMRN,
                           "demographics"   : demographics,
                           "action"         : "save"},
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
        $modal  = '<button class="btn btn-xs btn-rcgreen fs13" data-toggle="modal" data-target="#mrnmodal">';
        $modal .= '    <i class="fas fa-plus"></i> Add a new MRN';
        $modal .= '</button>';

        // Make a modal so users can enter a new MRN
        $modal .= '<div id="mrnmodal" class="modal" tabindex="-1" role="dialog">';
        $modal .= '    <div class="modal-dialog" role="document">';
        $modal .= '        <div class="modal-content">';
        $modal .= '            <div class="modal-header" style="background-color:maroon;color:white">';
        $modal .= '                <h5 class="modal-title">MRN of the person you are trying to find</h5>';
        $modal .= '                <button type="button" class="close" data-dismiss="modal" aria-label="Close">';
        $modal .= '                    <span style="color:white;" aria-hidden="true">&times;</span>';
        $modal .= '                </button>';
        $modal .= '            </div>';    // modal header
        $modal .= '            <div class="modal-body text-left">';
        $modal .= '                <div id="demographics" style="display:none"></div>';
        $modal .= '                <div style="margin:20px 0 0 0;font-weight:bold;" > ';
        $modal .= '                     Enter an 8 character MRN (no dashes): ';
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

}
