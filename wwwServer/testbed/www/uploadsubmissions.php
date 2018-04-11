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
	USERERROR("You must be logged on to share your materials");

# Standard Testbed Header
#
PAGEHEADER("Upload your materials", null, null, null);

$uploadOk=1;

$pid=trim($_POST["pid"]);
$uid=$this_user->uid();

$ok = 0;

# Use uid for filename
$target_name=$uid;

# Check that user is a student in a given class
if (!$this_user->IsStudent($pid))
   USERERROR("You cannot upload materials for classes where you are not a student");

# Check what we're uploading
foreach ($_FILES as $key => $f)
{
   if ($f['error'] == 0)
   {
	$mid = substr($key, 13);
	echo "Mid $mid";
 
        # Check that there is an assignment with this uid and mid
        $query_result = DBQueryFatal("select * from class_assignments where pid='$pid' and uid='$uid' and mid='$mid'");
$sql = "select * from class_assignments where pid='$pid' and uid='$uid' and mid='$mid'";
        if (mysql_num_rows($query_result) == 0)
           USERERROR("You cannot upload materials for an assignment that you do not have");


        $target_dir = "/tmp/";
 	$path_parts = pathinfo(basename($f["name"]));
	$ext = $path_parts['extension'];
	$target_dir = $target_dir . $target_name . "." . $ext;
	$uploaded=0;

	if (!move_uploaded_file($f["tmp_name"], $target_dir)) 
   	   USERERROR("Your file is larger than 2 MB and cannot be uploaded.");

	$dst_dir = "/groups/" . $pid . "/teachers/submissions/" . $mid;
	$spath = $dst_dir . "/" . $target_name . "." . $ext;

	# Find who is the head of the class and execute copy in his/her name
	$class = Project::LookupByPid($pid);
	$headuid =$class->head_uid();

	if ($fp = popen("$TBSUEXEC_PATH $headuid $pid webuploadfile $target_dir $dst_dir", "r")) {
   	   while (!feof($fp)) {
       	      $string = fgets($fp, 1024);
       	      $message .= $string;
       	      if (preg_match('/OK/',$string))
       	      {
		$time = time();
	  	# Update database
	  	$sql = "update class_assignments set state='submitted',submission_time=from_unixtime($time),submission_path='$spath' where pid='$pid' and uid='$uid' and mid='$mid'";
	  	DBQueryFatal($sql);
	      } 
   	     }
           pclose($fp);
	 }

	 unlink($target_dir);
   }
}
echo "<script type=\"text/javascript\">\n";
echo "<!--\nwindow.location = \"showuser.php\"\n//-->\n";
echo "</script>";
?>



