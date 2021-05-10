<?php
namespace Stanford\RecordNaming;
/** @var RecordNaming $module **/

/**
 * This is the API endpoint called when users select the 'Add new record' button from either the record Dashboard
 * or the Add/Edit Record page. A record name will be created based on the DAG that the user belongs to
 * (if DAGs are used) and/or increasing the record number.
 *
 * Once the record name is created, the link to the new record is returned.
 *
 */

use REDCap;
// global $userid;
$msg = null;
$recordHome = null;
$return_status = array();
$return_status["status"] = 0;


$pid = $module->getProjectId();
$user = $module->getUser();
$userid = $user->getUsername();
$module->emDebug("PID from RecordNamingNextRecord is " . $pid . ", and user is " . $userid);

// Retrieve locations on where to store the data
$projSettings = $module->getProjectSettings();
$numberPaddingSize = $projSettings["record-numeric-size"];

// If the DAG is desired instead of the DAG ID, retrieve the name for this ID.
$useDagName = $projSettings["use-dag-name"];

// Find the name of the record id field
$recordFieldName = REDCap::getRecordIdField();

// If the user rights is empty, this user should not be in this project
$userRights = $user->getRights();

if (empty($userRights)) {
    $msg = "User $userid does not have access to this project";
    $module->emError($msg);
    $return_status["error"] = $msg;
    die(json_encode($return_status));
}

// Check to see if the user has privilege to create a new record
if (!$userRights['record_create']) {
    $msg = "User $userid does not have privileges to create a new record";
    $module->emError($msg);
    $return_status["error"] = $msg;
    die(print json_encode($return_status));
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

$module->emDebug("New record result: $status with " . $newRecordID);

if ($status == 1) {
	// URL of new record
	$return_status["url"] = APP_PATH_WEBROOT_FULL."redcap_v".REDCAP_VERSION.'/DataEntry/record_home.php?pid=' .$pid . '&id=' . $newRecordID;
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

    $recordFieldArray = array($recordFieldName);

    // Cast empty prefix to a string
    $recordPrefix = empty($recordPrefix) ? "" : $recordPrefix . "-";

	// Retrieve all records
	$allRecordIDs = REDCap::getData($projectId, 'array', null, $recordFieldArray);

	// Try to get the 'starting number' for the next record based on presence or absence of prefix
	// This is a bit slow as it loops through all records...
	$numArray = array();
	foreach($allRecordIDs as $recordId => $events) {
		// Go through each record and pull out the ones that are numeric so we can find the highest number record already created.
		$thisRecord = null;
		if (!empty($recordPrefix)) {
			// Does record_id start with the prefix - if so, sub it out
			if(strpos(strtoupper($recordId), $recordPrefix) === 0) {
				$thisRecord = trim(intval(substr($recordId, strlen($recordPrefix))));
			}
		} else {
			$thisRecord = $recordId;
		}

		if (is_numeric($thisRecord)) {
			$numArray[] = $thisRecord;
		}
	}

	$biggestNumber = empty($numArray) ? 0 : max($numArray);
	$module->emDebug("Largest numeric record of " . count($numArray) . " records with prefix [$recordPrefix] is " . $biggestNumber);


	// To handle issues where two users are simultaneously creating new records, we will reserve the next available record
	// Unless we have exceeded the maximum size permitted.
	$largestNumberAvail = intval(str_repeat("9", $numberPaddingSize));

	$maxTries = 10;
	$try = 0;
	$nextRecord = '';
	$error_msg = null;
	$status = null;

	while ($try++ < $maxTries) {
		$thisNum = $biggestNumber + $try;
		$newRecordNumber = str_pad($thisNum, $numberPaddingSize, '0', STR_PAD_LEFT);
		$nextRecord = $recordPrefix . $newRecordNumber;

		if ($thisNum > $largestNumberAvail && $largestNumberAvail > 0) {
			$error_msg = "New record value of $nextRecord has numerical portion greater than padding size of $largestNumberAvail.";
			$status = 0;
			break;
		}

		if (isset($allRecordIDs[$nextRecord])) {
			$error_msg = "Proposed next record, $nextRecord, already exists.  This shouldn't happen.";
			$module->emDebug($error_msg);
			continue;
		}

		$status = 1;
		break;
		// I wanted to reserve the ID, but had to remove this as REDCap will not let you save to this ID unless you
		// create it after reserving it in your code.  I don't want to create a new record each time if the user
		// doesn't press save
		// if ($autoCreate) {
		// 	$reserved = REDCap::reserveNewRecordId($projectId,$nextRecord);
		// 	if ($reserved) {
		// 		$payload = [
		// 			"project_id" => $projectId,
		// 			"dataFormat" => 'json',
		// 			"data" => json_encode([
		// 				[
		// 					$recordFieldName => $nextRecord,
		// 					"redcap_event_name" =>
		// 				]
		// 			])
		// 			];
		//
		// 		REDCap::saveData($projectId, 'array', [[$nextRecord => ]])
		// $status = 1;
		// break;
			// } else {
			// 	$module->emDebug("Unable to reserve record $nextRecord - must be in use by another process.");
			// }
		// }
	}

	if (is_null($status)) {
		// Exceeded maxTries
		$status = 0;
		$error_msg = "Exceeded $maxTries tries to find the next record after $biggestNumber - see logs for details.";
	}

	return array($status, $nextRecord, $error_msg);
}