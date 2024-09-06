<?php
/*
**************************************************************************************************************************
** CORAL Usage Statistics Module
**
** Copyright (c) 2010 University of Notre Dame
**
** This file is part of CORAL.
**
** CORAL is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
**
** CORAL is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License along with CORAL.  If not, see <http://www.gnu.org/licenses/>.
**
**************************************************************************************************************************
*/

ini_set("auto_detect_line_endings", true);
include_once 'directory.php';

$util = new Utility();

function cleanValue($value) {
  //strip everything after (Subs from Title
  if (strpos($value,' (Subs') !== false) {
    $value = substr($value, 0, strpos($value, ' (Subs'));
  }
  //remove " quotes
  $value = str_replace("\"","",$value);

  // set value to &nbsp; if value is empty
  $value = (($value == '') || ($value == ' ')) ? "&nbsp;" : $value;
  return trim($value);
}

$importLogID = filter_input(INPUT_GET, 'importLogID', FILTER_VALIDATE_INT);
$fromSushi = !empty($importLogID) && $importLogID > 0;

// The page data differs for sushi and manual uploads. This adds clarity to what is rendered onto the page
$page = array(
  'title' => '',
  'reportName' => '',
  'formValues' => array(),
  'status' => array(),
  'errors' => array(),
  'warnings' => array(),
);

//this file has been created from SUSHI
if ($fromSushi) {

  $importLog = new ImportLog(new NamedArguments(array('primaryKey' => $_GET['importLogID'])));
  $layout = new Layout();
  $layout->getByLayoutCode($importLog->layoutCode);

  // read file
  $file_handle = $util->utf8_fopen_read($importLog->fileName, true);

  // page values
  $page['title'] = _('SUSHI Import Confirmation');
  $page['reportName'] = $layout->name;
  $page['formValues']['importLogID'] = $importLogID;
  $page['formValues']['overrideInd'] = 'Y';
  $page['warnings'][] = _("File has been imported from SUSHI. The default behavior for imported SUSHI files is to overwrite previously imported data. If this is incorrect, please contact a system administrator.");

} else {
  //This came from the File Import Page.
  //If there's any error, we're always taking folks back to import.php with the error code. Just using a function to keep things in one spot.
  function sendError($errorCode){
    header("Location: import.php?error={$errorCode}");
    exit;
  }

  // before assessing file, check that the layoutID is valid.
  // First, use the selected Layout ID to confirm it exists in the database.
  $layoutID = filter_input(INPUT_POST, 'layoutID', FILTER_VALIDATE_INT);
  $layout = new Layout(new NamedArguments(array('primaryKey' => $layoutID)));
  $notInLayoutDatabase = (!$layout->name);
  if($notInLayoutDatabase){sendError('1');}

  // next read the layouts ini file and confirm it exists there too.
  $layoutsArray = parse_ini_file("layouts.ini", true);
  $layoutKey = $layoutsArray['ReportTypes'][$layout->layoutCode];
  $notInReportTypes = (empty($layoutKey));
  $notDefinedInIni = (empty($layoutsArray[$layoutKey]));
  if($notInReportTypes || $notDefinedInIni) {sendError('2');}

  //Grab columns to check and such.
  $columnsToCheck = $layoutsArray[$layoutKey]['columnToCheck'];
  $layoutColumns = $layoutsArray[$layoutKey]['columns'];

  // check file validity
  // get fileinfo
  $pathInfo = pathinfo($_FILES['usageFile']['name']);
  // check the extension is valid
  $validExtensions = ['txt', 'tsv'];
  $fileExtension = strtolower($pathInfo['extension']);
  $invalidFileExtension = (!in_array($fileExtension, $validExtensions));
  if($invalidFileExtension){sendError('3');}

  //Check that the file has no other errors waiting for it. 
  $errorMessage = $_FILES['usageFile']['error'];
  //0 is a valid file upload.
  if($errorMessage !== 0){
    header("Location: import.php?fileerror={$errorMessage}");
    exit;
  }
  
  // check that the doc is the correct mimetype. If the file wasn't uploaded, there's no path, so this has to be checked after checking the file upload errors.
  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  $mtype = finfo_file($finfo, $_FILES['usageFile']['tmp_name']);
  finfo_close($finfo);
  $incorrectFileType = ($mtype !== "text/plain");
  if($incorrectFileType){sendError('4');}

  // store the file
  // TODO: In the following code, uploading the file repeatedly will overwrite the archive/ file...until the next day.
  // This is slightly odd because the file is saved but never imported
  $targetDirectory = BASE_DIR."counterstore/";
  if(!is_writable($targetDirectory)){sendError('5');}

  $targetPath = "{$targetDirectory}{$pathInfo['filename']}{$pathInfo['extension']}";
  if(move_uploaded_file($_FILES['usageFile']['tmp_name'], $targetPath)) {
    $page['status'][] = _("The file "). $pathInfo['basename'] ._(" has been uploaded successfully.")."<br />"._("Please confirm the following data:")."<br />";
  } else{sendError('6');}

	// file upload was OK, now we can read the file to output for confirmation

  // read this file
  $file_handle = $util->utf8_fopen_read($targetPath, false);

  // get first line of file
  $firstLine = stream_get_line($file_handle, 10000000, "\n");
  $firstArray = array_map(function($v) {
    return cleanValue($v);
  }, explode("\t",$firstLine));

  $missingColumns = [];
  foreach($columnsToCheck as $position => $check) {
    if ($check != $firstArray[$position]) {
      $missingColumns[] =  '<span style="margin-left: 15px;"> &bull; '
        . _("Looking for ") . $check . _(" in column ") . $position . _(" but found ") . $firstArray[$position]
        .'</span>';
    }
  }

  if (!empty($missingColumns)) {
    $page['errors'][] = _('Error with Format') . ': ' ._("Report format is set to ") . $layout->name .
      _(" but does not match the column names listed in layouts.ini for this format") . '<br>'
      . implode('<br>', $missingColumns)
      . '<br><br>' . _("Expecting columns: ") . implode(', ', $columnsToCheck) . '<br><br>' . _("Found columns: ")
      . implode(',', $firstArray) . '<br><br>'
      . _("If problems persist you can copy an existing header that works into this file.");
  }



  $page['title'] = _('Upload Process Confirmation');
  $page['reportName'] = $layout->name;
  $page['formValues']['file'] = $targetPath;
  $page['formValues']['layoutID'] = $layoutID;

  if (isset($_POST['overrideInd'])){
    $page['warnings'][] = _("File is flagged to override verifications of previous month data.  If this is incorrect use 'Cancel' to fix.");
    $page['formValues']['overrideInd'] = 1;
  }else{
    $page['formValues']['overrideInd'] = 0;
  }
}

// for the header
$pageTitle = $page['title'];


include 'templates/header.php';

?>

<script language="javascript">
	function updateSubmit(){
		document.confirmForm.submitForm.disabled=true;
		document.confirmForm.submitForm.value=_("Processing Contents...");
		document.confirmForm.submit();
	}
</script>
<style>
  table {
    position: relative;
  }
  table.dataTable th {
    position: sticky;
    top: 0;
    background: rgba(200, 200, 200, 1) !important;
  }
  table.dataTable th:first-child {
    min-width: 300px;
  }
</style>




<table class="headerTable">
	<tr>
    <td>
			<div class="headerText"><?php echo $page['title']; ?></div>
			<br>

      <!-- ERRORS -->
      <?php if(!empty($page['errors'])): ?>
        <?php foreach($page['errors'] as $text): ?>
          <p style="color: red;"><?php echo $text; ?></p>
        <?php endforeach; ?>
        </td></tr></table>
        <?php exit(); ?>
      <?php endif; ?>

      <!-- STATUSES -->
      <?php if(!empty($page['status'])): ?>
        <?php foreach($page['status'] as $text): ?>
          <p><?php echo $text; ?></p>
        <?php endforeach; ?>
        <br>
      <?php endif; ?>

      <!-- WARNINGS -->
      <?php if(!empty($page['warnings'])): ?>
        <?php foreach($page['warnings'] as $text): ?>
          <p style="color: red;"><?php echo $text; ?></p>
        <?php endforeach; ?>
        <br>
      <?php endif; ?>


      <!-- REPORT NAME -->
      <p>
        <?php echo _('Report Format'); ?>: <?php echo $page['reportName']; ?>
        <br>
        <?php if(!$fromSushi): ?>
          <?php echo _('If this is incorrect, please use \'Cancel\' to go back and fix the headers of the file.'); ?>
        <?php endif; ?>
      </p>

      <table class='dataTable' style='width:895px;'>


			<?php
        $i = 0;

        // If this is not a sushi report, need to render headers
        if (!$fromSushi) {
          echo '<tr><th>' . implode('</th><th>', $firstArray) . '</th></tr>';
          $i = 1;
        }

        while (!feof($file_handle)) {

          //get each line out of the file handler
          $line = stream_get_line($file_handle, 10000000, "\n");
          //set delimiter
          $del = "\t";
          $lineArray = explode($del,$line);

          // If this is not a sushi report skip the first line if it begins with "Total"
          if (!$fromSushi && strtolower(substr($lineArray[0], 0, 5)) == 'total') {
            continue;
          }

          echo '<tr>';
          foreach($lineArray as $value){
            //Clean some of the data
            $display = cleanValue($value);
            if ($i == 0) {
              echo '<th>' . strtoupper($display) .'</th>';
            } else {
              echo "<td>$display</td>";
            }
          }
          echo '</tr>';
          $i++;
        }
        fclose($file_handle);
			?>

      </table>

			<br />
			<form id="confirmForm" name="confirmForm" enctype="multipart/form-data" method="post" action="uploadComplete.php">
        <!-- JR1 override warning -->
        <?php if(!$fromSushi && in_array($layout->layoutCode, array('JR1_R4','JR1a_R4'))): ?>
          <div style="background: lightgoldenrodyellow;padding: 10px;border: #8b7700 3px solid;">
            <?php echo _('Reporting period totals in COUNTER Release 4 JR1 reports refer to the reporting period only, which may be shorter than the current calendar year to date. By default, reporting period totals are not imported from JR1 reports in TSV format. Select the following option if you want to overwrite the reporting period totals with the data in this report for the titles specified.'); ?>
            <div style="margin-top: 8px">
              <label for="storeJR1Totals">
                <input type="checkbox" id="storeJR1Totals" name="storeJR1Totals" value="Y">
                <?php echo _('Update reporting period totals'); ?>
              </label>
            </div>
          </div>
        <?php endif; ?>
        <?php foreach($page['formValues'] as $key => $value): ?>
				  <input type="hidden" name="<?php echo $key; ?>" value="<?php echo $value; ?>">
        <?php endforeach; ?>
				<table>
					<tr valign="center">
						<td>
							<input type="button" name="submitForm" id="submitForm" value="<?php echo _('Confirm');?>" onclick="javascript:updateSubmit();" class="submit-button" />
						</td>
						<td>
							<input type="button" value="<?php echo _('Cancel');?>" onClick="javascript:history.back();" class='cancel-button'>
						</td>
					</tr>
				</table>
			</form>
