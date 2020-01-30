<?php
namespace Stanford\RecordNaming;
/** @var \Stanford\RecordNaming\RecordNaming $module **/

/**
 * This is the API endpoint called when users select the 'Add new record' button from either the record Dashboard
 * or the Add/Edit Record page. A record name will be created based on the DAG that the user belongs to
 * (if DAGs are used) and/or increasing the record number.
 *
 * Once the record name is created, the link to the new record is returned.
 *
 */

use REDCap;
global $userid;
$msg = null;
$recordHome = null;
$return_status = array();
$return_status["status"] = 0;


$pid = isset($_GET['pid']) && !empty($_GET['pid']) ? $_GET['pid'] : null;
$module->emDebug("PID from RecordNamingNextRecord is " . $pid . ", and user is " . $userid);

// Retrieve locations on where to store the data
$projSettings = $module->getProjectSettings();
$numberPaddingSize = $projSettings["record-numeric-size"]["value"];

// If the DAG is desired instead of the DAG ID, retrieve the name for this ID.
$useDagName = $projSettings["use-dag-name"]["value"];

// Find the name of the record id field
$recordFieldName = REDCap::getRecordIdField();

// If the user rights is empty, this user should not be in this project
$allUserRights = REDCap::getUserRights($userid);

$userRights = $allUserRights[$userid];
if (empty($userRights)) {
    $msg = "User $userid does not have access to this project";
    $module->emError($msg);
    $return_status["error"] = $msg;
    print json_encode($return_status);
}

// Check to see if the user has privilege to create a new record
if (!$userRights['record_create']) {
    $msg = "User $userid does not have privileges to create a new record";
    $module->emError($msg);
    $return_status["error"] = $msg;
    print json_encode($return_status);
}

// Retrieve the DAG that this person belongs to
$dagId = $userRights['group_id'];
if (($useDagName) and (is_numeric($dagId))) {
    $groupId = REDCap::getGroupNames(false, $dagId);
} else {
    $groupId = $dagId;
}

// Create a new record in this project using the format DAG-0004 with the number of padding characters coming from the config file.
// Find the next record ID to use for the new record
list($status, $newRecordID, $error_msg) = findNextRecordNumber($pid, $groupId, $numberPaddingSize, $recordFieldName);
$module->emDebug("New record number for project $pid: " . $newRecordID);

if ($status == 1) {
    // URL of new record
    $return_status["url"] = $_SERVER["HTTP_ORIGIN"] . APP_PATH_WEBROOT . 'DataEntry/record_home.php?pid=' .$pid . '&id=' . $newRecordID;
    $return_status["status"] = $status;
} else {
    $return_status["error"] = $error_msg;
}

print json_encode($return_status);


/**
 * This function will create the next record label based on the inputs from the config file
 * and the existing records.

 * @param $projectId
 * @param $recordPrefix
 * @param $numberPaddingSize
 * @param $recordFieldName
 * @return array
 */
function findNextRecordNumber($projectId, $recordPrefix, $numberPaddingSize, $recordFieldName) {
    global $module;

    $error_msg = null;
    $status = 1;
    $newRecordLabel = null;
    $recordFieldArray = array($recordFieldName);
    $module->emDebug("Record prefix: " . $recordPrefix);

    // If there is no prefix given (like a DAG ID which means this person does not belong to a DAG), retrieve all records
    // and we will have to go through the list manually
    if (is_null($recordPrefix) or empty($recordPrefix)) {

        // Retrieve all records
        $allRecordIDs = REDCap::getData($projectId, 'array', null, $recordFieldArray);

        // Loop over the records that are already created and look for the records that fit our criteria
        $numArray = array();
        foreach($allRecordIDs as $recordNum => $recordInfo) {

            // Go through each record and pull out the ones that are numeric so we can find the highest number record already created.
            if (is_numeric($recordNum)) {
                $numArray[] = $recordNum;
            }
        }

        // Find the biggest number record that is already created
        if (($numArray == null) or empty($numArray)) {
            $biggestNumber = 0;
        } else {
            // Find the largest numbered record that is already created
            $biggestNumber = ltrim(intval(max($numArray)));
        }

    } else {

        // This section is for records that belong to a DAG and we need to add the DagID or Dag Name to the record
        $filter = "starts_with([" . $recordFieldName . "],'" . $recordPrefix . "')";
        $module->emDebug("Filter: " . $filter);
        $recordIDs = REDCap::getData($projectId, 'array', null, $recordFieldArray, null, null, null, null, null, $filter);

        // If there are records with this prefix, start numbering at 0 so the record will be 1.
        if (($recordIDs == '') or empty($recordIDs)) {
            $biggestNumber = 0;
        } else {

            // There were records already created with this prefix, find what the largest number is
            $biggestRecord = max(array_keys($recordIDs));
            $module->emDebug("Biggest current record: " . $biggestRecord);
            $length = strlen($biggestRecord);

            // Extract the number portion of the record
            $biggestNumber = ltrim(intval(substr($biggestRecord, strlen($recordPrefix) + 1)));
        }
    }

    // Check the value of the max existing record to make sure this portion is numeric
    if (is_numeric($biggestNumber)) {

        // See if this value should be padded. If number_padding_size is not set, just use the number as is.
        if (($numberPaddingSize == null) or (empty($numberPaddingSize))) {
            $newRecordNumber = ++$biggestNumber;
        } else {

            // Find out what the largest number possible is
            $largestNumberAvail = '';
            for ($ncnt = 0; $ncnt < $numberPaddingSize; $ncnt++) {
                $largestNumberAvail .= '9';
            }

            // Make sure that adding 1 to the number, does not overflow the size
            $maxAvailNum = intval($largestNumberAvail);
            $nextNumValue = ++$biggestNumber;
            if ($maxAvailNum >= $nextNumValue) {
                $newRecordNumber = str_pad($nextNumValue, $numberPaddingSize, '0', STR_PAD_LEFT);
            } else {
                $error_msg = "New record value of $nextNumValue is greater than the allotted size of $largestNumberAvail.";
                $status = 0;
            }
        }

        // Add the record prefix to the number
        if ($status == 1) {
            if (($recordPrefix == null) or (empty($recordPrefix))) {
                $newRecordLabel = $newRecordNumber;
            } else {
                $newRecordLabel = $recordPrefix . '-' . $newRecordNumber;
            }
        }

    } else {
        $error_msg = "Expecting numbers for last portion of record ID $biggestNumber and it is not numeric";
        $module->emError($error_msg);
        $status = 0;
    }

    return array($status, $newRecordLabel, $error_msg);
}