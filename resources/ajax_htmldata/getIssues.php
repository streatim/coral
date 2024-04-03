<?php
$resourceID = $_GET['resourceID'];
$resourceAcquisitionID = $_GET['resourceAcquisitionID'];
$resource = new Resource(new NamedArguments(array('primaryKey' => $resourceID)));
$resourceAcquisition = new ResourceAcquisition(new NamedArguments(array('primaryKey' => $resourceAcquisitionID)));

$util = new Utility();
$getIssuesFormData = "action=getIssuesList&resourceID=" . $resourceID . "&resourceAcquisitionID=" . $resourceAcquisitionID;
$getDowntimeFormData = "action=getDowntimeList&resourceID=" . $resourceID . "&resourceAcquisitionID=" . $resourceAcquisitionID;
$exportIssuesUrl = "export_issues.php?resourceID={$resourceID}&resourceAcquisitionID=" . $resourceAcquisitionID;
$exportDowntimesUrl = "export_downtimes.php?resourceID={$resourceID}&resourceAcquisitionID=" . $resourceAcquisitionID;

$organizationArray = $resource->getOrganizationArray();
$exportIssues = $exportIssuesArchived = $exportDowntimes = $exportDowntimesArchived = [];
if (count($organizationArray) > 0) {
  $issuedOrgs = [];
  foreach ($organizationArray as $orgData) {
    if (!in_array($orgData['organizationID'], $issuedOrgs)) {
      $organization = new Organization(new NamedArguments(array('primaryKey' => $orgData['organizationID'])));
      $exportIssues = array_merge($exportIssues, $organization->getExportableIssues());
      $exportIssues = array_merge($exportIssues, $resource->getExportableIssues());

      $exportIssuesArchived = array_merge($exportIssuesArchived, $organization->getExportableIssues(true));
      $exportIssuesArchived = array_merge($exportIssuesArchived, $resource->getExportableIssues(true));

      $exportDowntimes = array_merge($exportDowntimes, $organization->getExportableDowntimes());
      $exportDowntimes = array_merge($exportDowntimes, $resource->getExportableDowntimes());

      $exportDowntimesArchived = array_merge($exportDowntimesArchived, $organization->getExportableDowntimes(true));
      $exportDowntimesArchived = array_merge($exportDowntimesArchived, $resource->getExportableDowntimes(true));

      $issuedOrgs[] = $orgData['organizationID'];
    }
  }
}
?>

<table id="issueTable" class='linedFormTable issueTabTable'>
  <tr>
    <th><?php echo _("Issues/Problems"); ?></th>
  </tr>
  <tr>
    <td><a id="createIssueBtn" class="thickbox" href='javascript:void(0);' onclick='javascript:myDialog("ajax_forms.php?action=getNewIssueForm&resourceID=<?php echo $resourceID; ?>&resourceAcquisitionID=<?php echo $resourceAcquisitionID; ?>",500,600)'><?php echo _("report new issue"); ?></a></td>
  </tr>
  <tr>
    <td>
      <a href='javascript:void(0);' onclick='javascript:myDialog("ajax_htmldata.php?<?php echo $getIssuesFormData; ?>",500,500)' id="openIssuesBtn"><?php echo _("view open issues"); ?></a>
      <?php if (count($exportIssues)) { ?>
        <a target="_blank" href="<?php echo $exportIssuesUrl; ?>"><img src="images/xls.gif"/></a>
      <?php } ?>
      <div class="issueList" id="openIssues" style="display:none;"></div>
    </td>
  </tr>
  <tr>
    <td>
      <a href='javascript:void(0);' onclick='javascript:myDialog("ajax_htmldata.php?archived=1&<?php echo $getIssuesFormData; ?>",500,500)' id="archivedIssuesBtn"><?php echo _("view archived issues"); ?></a>
      <?php if (count($exportIssuesArchived)) { ?>
        <a target="_blank" href="<?php echo $exportIssuesUrl; ?>&archived=1"><img src="images/xls.gif"/></a>
      <?php } ?>
      <div class="issueList" id="archivedIssues"></div>
    </td>
  </tr>
</table>

<table id="downTimeTable" class='linedFormTable issueTabTable'>
  <tr>
    <th><?php echo _("Downtime"); ?></th>
  </tr>
  <tr>
    <td><a id="createDowntimeBtn" class="thickbox" href='javascript:void(0);' onclick='javascript:myDialog("ajax_forms.php?action=getNewDowntimeForm&resourceID=<?php echo $resourceID; ?>&resourceAcquisitionID=<?php echo $resourceAcquisitionID; ?>",300,400)'><?php echo _("report new Downtime"); ?></a></td>
  </tr>
  <tr>
    <td>
      <a href='javascript:void(0);' onclick='javascript:myDialog("ajax_htmldata.php?<?php echo $getDowntimeFormData; ?>",500,500)' id="openDowntimeBtn"><?php echo _("view current/upcoming downtime"); ?></a>
      <?php if (count($exportDowntimes)) { ?>
        <a target="_blank" href="<?php echo $exportDowntimesUrl; ?>"><img src="images/xls.gif"/></a>
      <?php } ?>
      <div class="downtimeList" id="currentDowntime" style="display:none;"></div>
    </td>
  </tr>
  <tr>
    <td>
      <a href='javascript:void(0);' onclick='javascript:myDialog("ajax_htmldata.php?archived=1&<?php echo $getDowntimeFormData; ?>",500,500)' id="archiveddowntimeBtn"><?php echo _("view archived downtime"); ?></a>
      <?php if (count($exportDowntimesArchived)) { ?>
        <a target="_blank" href="<?php echo $exportDowntimesUrl; ?>&archived=1"><img src="images/xls.gif"/></a>
      <?php } ?>
      <div class="downtimeList" id="archivedDowntime"></div>
    </td>
  </tr>
</table>

