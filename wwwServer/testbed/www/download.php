<?php
#
# EMULAB-COPYRIGHT
# Copyright (c) 2000-2007 University of Utah and the Flux Group.
# All rights reserved.
#
include("defs.php");
include("showstuff.php");
include_once("table_defs.php");

# ignore warnings for is_file permission errors
error_reporting(0);

#
# Only known and logged in users can do this.
#

# this bit of ugliness wraps up CheckLogin fail
# This is a DETER hack to allow the deter-exec group to view project applicant
# user info without being logged in.
$this_user = null;
global $CHECKLOGIN_USER;
$status = LoginStatus();
if ($status & (CHECKLOGIN_USERSTATUS|CHECKLOGIN_WEBONLY))
    $this_user = $CHECKLOGIN_USER;

$isadmin   = ISADMIN();

if (!$isadmin && !$this_user)
	USERERROR("You must be logged on to download materials");

# Standard Testbed Header
#

$uid = $this_user->uid();
$pid = $_POST['pid'];
$ismid = 0;

if (isset($_POST['mid']))
{
   $mid = $_POST['mid'];
   $ismid = 1;
}
else if (isset($_GET['path']))
{ 
  $zipstring = $_GET['path'];
}

# Check that the user is the group root of the class 
# whose submissions we want to download

if ($ismid)
{
   $pi = $this_user->IsGroupOrProjectRoot($pid);

   $sql = "select pid from class_materials where mid=$mid";
   $query_result = DBQueryFatal($sql);

   $row = mysql_fetch_array($query_result);
   if ($pid != $row['pid'] || !$pi)
      USERERROR("You are not allowed to access materials in classes that you are not teaching");
}


# Now zip everything and send to user
# Generate a random number for filename
mt_srand();
$file=rand();

if ($ismid)
   $filepath = "/groups/" . $pid . "/teachers/submissions/" . $mid;
else
   $filepath = $zipstring;


# Read teacher manuals with right group
$mygid = "www";
if (preg_match("/\/proj\/teachers\//", $filepath))
{
   if($this_user->IsTeacherOrTA())
     $mygid = "teachers";
   else
     USERERROR("You are not a teacher and cannot access teacher materials");
}
else if (preg_match("/\/groups\/(.*?)\/(.*?)\//", $filepath, $matches))
{
      $pid = $matches[1];
      $gid = $matches[2];
      # Check if this user is member of the group
      $sql = "select * from group_membership where pid='$pid' and gid='$gid' and uid='$uid'";
      $query_result = DBQueryFatal($sql);
      if (mysql_num_rows($query_result) > 0) {
              $gr = Group::LookupByPidGid($pid, $gid);
              if (isset($gr))
                 $mygid = $gr->unix_name();
      }
      else
      {
          USERERROR("You are not a member of group $gid in project $pid");
      }
}
																		       


$filename="/tmp/". $file . ".zip";
$newcontents="";
if ($fp = popen("$TBSUEXEC_PATH $uid $mygid webdownload $filename $filepath", "r")) {
    $newcontents = "";
    while (!feof($fp)) {
       $newcontents .= fgets($fp, 1024);
    }
}
		      
#$retval = SUEXEC($uid, $mygid, "webdownload $filename $filepath", SUEXEC_ACTION_IGNORE);
$base = basename($filename);

header("Content-Type: application/zip");
header("Content-Disposition: attachment; filename=$base");
header("Content-Length: " . filesize($filename));
readfile($filename);

# TODO: Should remove the files here but this doesn't work
$retval = SUEXEC($uid, "www", "webrmfile $filename", SUEXEC_ACTION_IGNORE);

echo "<script type=\"text/javascript\">\n";
if ($ismid)
   echo "<!--\nwindow.location = \"manage_class.php?pid=$pid&action=manageassignments\"\n//-->\n";
echo "</script>";

?>



