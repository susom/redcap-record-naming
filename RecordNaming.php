<?php
namespace Stanford\RecordNaming;

use ExternalModules\ExternalModules;
use \REDCap;

require_once "emLoggerTrait.php";

/**
 * Class RecordNaming
 * @package Stanford\RecordNaming
 *
 * This package will automate the naming of records.  When DAGs are used, the format of the records are
 *             [DAG Name]-[number] or [DAG Name]-[left-padded number]
 *             [DAG ID]-[number] or [DAG ID]=[left-padded number]
 *
 * When DAGs are not used, the format of the records are
 *              [number] or [left-padded number]
 *
 * The naming options of Dag ID or Dag Name and number or left-padded number are retrieved from the config.json file.
 */
class RecordNaming extends \ExternalModules\AbstractExternalModule
{

    use emLoggerTrait;

    /****
     *  Record Add Edit Records Page
     *     Hook to allow the Add new record button to be overwritten so we can name the record
     *
     * @param $project_id
     * @param $instrument
     * @param $event_id
     */
    function redcap_add_edit_records_page($project_id, $instrument, $event_id) {

        // Determine what the record name should be based on the data entered in the config.json file
        $newRecordURL = $this->overrideNewRecordButton("AddRecord");
        if (!empty($newRecordURL)) {
            $this->emError("Could not find the next Record ID for project $project_id");
        }
    }

    /**
     *  Record Status Dashboard Page
     *     Hook to allow the Add new record button to be overwritten so we can name the record
     *
     * @param $project_id
     */
    function redcap_every_page_before_render($project_id) {

        if (PAGE === 'DataEntry/record_status_dashboard.php') {
            $newRecordURL = $this->overrideNewRecordButton("Dashboard");
            if (!empty($newRecordURL)) {
                $this->emError("Could not find the next Record ID for project $project_id");
            }
        } else if (PAGE === 'ProjectSetup/index.php') {
            $this->emDebug("In Project Setup");
            $this->disableAutoNumberingOption();
        }
    }

    /**
     * This function will overwrite the 'Add New Record' record on the Record dashboard and Add/Edit records
     * page.  We will intercept the creation of a new record so we can name it based on the selections in the
     * config.json file.
     *
     * @param $pageLabel
     */

    function overrideNewRecordButton ($pageLabel) {

        // This is the URL of the page which will figure out the new record ID
        $url = $this->getUrl("RecordNamingNextRecord.php", false, true);
        $this->emDebug("URL of the API that will determine the new record name" , $url);

        ?>

        <!-- Look for the 'Add new record' button and override it with this function so we can rename the record -->
        <script type="text/javascript">

            var page_label = '<?php echo $pageLabel; ?>';
            var url =  '<?php echo $url; ?>';

            window.onload = function() {
                var buttonElement;

                // Find the 'Add new record' button on the page that we are on. The Dashboard has the button within a <div>
                // and the Add/Edit Records page has the button with a <td>
                if (page_label === 'Dashboard') {
                    buttonElement = document.querySelectorAll("div > button");
                } else if (page_label === "AddRecord") {
                    buttonElement = document.querySelectorAll("td > button");
                }

                // Look for the button which creates the new record and override it with the function which
                // will determine the new record name
                for (var ncnt = 0; ncnt < buttonElement.length; ncnt++) {
                    var button = buttonElement[ncnt];
                    if (button.innerHTML.includes('Add new record')) {
                        var parent = button.parentElement;
                        parent.innerHTML = '<button class="btn btn-xs btn-rcgreen fs13" onclick="getNewRecordName()">' +
                                            '    <i class="fas fa-plus"></i> Add new record' +
                                            '    <input type="hidden" id="url" value="' + url + '">' +
                                            '</button>';
                    }
                }
            };

            // Retrieve the new record name and go to the record home page so the user can create the record.
            function getNewRecordName() {

                var getUrl = document.getElementById("url").value;

                $.ajax({
                    type: "POST",
                    url: getUrl,
                    success: function(return_status, textStatus, jqXHR) {

                        var return_array = JSON.parse(return_status);

                        // A return status of 1 means success, go to record Home page
                        if (return_array.status === 1) {
                            window.open(return_array.url, '_self');
                        } else {
                            // If there was a problem, set an alert with the error message
                            alert("Error message: " + return_array.error);
                        }
                    },
                    error: function(hqXHR, textStatus, errorThrown) {
                    }
                });
            }

        </script>

        <?php

    }

    /**
     *  This function will Disable the Auto-numbering option on the Project Setup page and will change the
     *  label to Numbering by External Module so users know they cannot change the format of record naming.
     */
    function disableAutoNumberingOption() {

        ?>
        <script type="text/javascript">

            window.onload = function() {

                var parentDiv = $( "div.chklisttext div" );

                // Find the correct section where the auto-numbering option is located
                for (var ncnt = 0; ncnt < parentDiv.length; ncnt++) {
                    var childDiv = parentDiv[ncnt];
                    if (childDiv.innerHTML.includes('Auto-numbering for records')) {

                        // Change the label so users know why they can't select auto-numbering
                        var string = childDiv.innerHTML;
                        var newString = string.replace(' Auto-numbering for records', ' Numbering by External Module');
                        childDiv.innerHTML = newString;

                        // Disable the button so users cannot select auto-numbering
                        var button = childDiv.getElementsByTagName('button');
                        button[0].disabled = true;
                  }
                }

            };
       </script>

        <?php
    }


}


