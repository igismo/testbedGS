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
$isadmin = null;
global $CHECKLOGIN_USER;
$status = LoginStatus();
if ($status & (CHECKLOGIN_USERSTATUS|CHECKLOGIN_WEBONLY))
{
  $this_user = $CHECKLOGIN_USER;
  $uid = $this_user->uid();
}
else
  $uid = "www";
  
$isadmin   = ISADMIN();

# Standard Testbed Header
$file = $_GET['file'];
$pid = "www";

$path_parts = pathinfo(basename($file));
$ext = $path_parts['extension'];
$dir = $path_parts['dirname'];
$mid = "";
if (isset($_GET['mid']))
   $mid = $_GET['mid'];

if (!$isadmin && !$this_user)
{
	# Public can only access items on /share/ and URLs
	$pos1 = strpos($file, "/share/shared");
	$pos2 = strpos($file, "http://");
	if ($pos1===FALSE && $pos2===FALSE)
	   USERERROR("You must be logged on to access protected files");
	else if ($pos1 > 0)
	   USERERROR("You must be logged on to access protected files");
   	else if ($pos2 > 0)
	   USERERROR("You must be logged on to access protected files");
}


if (!preg_match("/^http\:\/\//", $file) )
{
   if(!isset($ext))
  {
   $file = $file . "/index.html";
   $ext = "html";
  }

  # Read teacher manuals with right group
  $mygid = $pid;
  if (preg_match("/^\/proj\/teachers\//", $file))
  {
	if($this_user->IsTeacherOrTA())
	  $mygid = "teachers";
	else
	   USERERROR("You are not a teacher and cannot access teacher materials");
  }
  else if (preg_match("/^\/groups\/(.*?)\/(.*?)\//", $file, $matches))
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

  if ($fp = popen("$TBSUEXEC_PATH $uid $mygid webreadfile $file", "r")) {
    $newcontents = "";
    while (!feof($fp)) {
        $newcontents .= fgets($fp, 1024);
    }
  }
  if ($ext == "html" || $ext == "htm")
  {
      $newcontents=preg_replace("/education.deterlab.net\/stylesheets/", "www.isi.deterlab.net/", $newcontents);
      $newcontents=preg_replace("/education.deterlab.net\/common/", "www.isi.deterlab.net/", $newcontents);
  }

  # List all extensions for which contents will be handed to the browser
  if ($ext == "html" || $ext == "htm" || $ext == "php" || $ext == "txt" || $ext == "css" )
    echo $newcontents;
  else if ($ext == "png" || $ext == "gif" || $ext == "bmp" || $ext == "jpg" || $ext == "jpeg" || $ext == "tiff")
  {
   if (preg_match("/^You cannot/",$newcontents))
      echo $newcontents;
   else
   {
	header("Content-Type: image/" . $ext);
   	echo $newcontents;
   }
  }
  else
  {
   $base = basename($file);
   file_put_contents("/tmp/" . $base, $newcontents);
   $file="/tmp/" . $base;
   header("Content-Type: application/octet-stream");
   header("Content-Disposition: attachment; filename=$base");
   header("Content-Length: " . filesize($file));
   readfile($file);
 }
}
else
{  
  echo "<script type=\"text/javascript\">\n";
  echo "window.location = \"$file\"";
  echo "</script>";
}

if ($mid != "")
{
   $sql = "update class_assignments set state='started' where uid='$uid' and mid='$mid' and state='assigned'";	
   DBQueryFatal($sql);
}


?>