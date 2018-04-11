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
{
	USERERROR("You must be logged on to manage class materials and assignments");
}



# Standard Testbed Header
#
PAGEHEADER("Assign materials to students", null, null, null);

$pid=trim($_POST["pid"]);

foreach ($_POST as $key => $value)
{
   if (preg_match("/^material/", $key))
   {
      $mid = $value;
      echo "Will delete $mid<br>";
   }
   if (preg_match("/^visible/", $key))
   {
      $mid = substr($key, 7);
      echo "Material $mid will be visible to $value<br>";  
   }
} 

#
# Standard Testbed Footer
# 
PAGEFOOTER();
?>



