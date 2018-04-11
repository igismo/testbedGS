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
	USERERROR("You must be logged on to add materials to class");
}



# Standard Testbed Header
#
PAGEHEADER("Add materials to class", null, null, null);

$title=trim($_POST["sharedtitle"]);
$type=trim($_POST["sharedtype"]);
$url=trim($_POST["sharedURL"]);
$sharedmethod=trim($_POST["sharedmethod"]);
$uid=trim($_POST["uid"]);
$pid=trim($_POST["pid"]);
$gid=trim($_POST["adoptmethod"]);

if ($gid == "gid")
   $gid = $_POST["adopttogid"];
else
   $gid = 'all';


if ($title == "")
{  
   USERERROR("Empty title is not allowed");
}   
if (!preg_match("/^[a-zA-Z\s\-0-9]*$/",$title))
{
   USERERROR("Title can only contain letters, numbers, spaces and dashes");
}
if ($type == "")
{  
   USERERROR("You must select a type for the material you are sharing");
}

# Check that the url is properly set
if ($url != "")
{
  if (!preg_match("/^http\:\/\//", $url))
    USERERROR("You must specify a URL that starts with http://");
}   

$ok = 0;
$tts = "";

# Generate a random number for filename
mt_srand();
$target_name=rand();

# If sharing a ZIP file
if ($sharedmethod == "zip" && basename($_FILES["filetoshare"]["name"]) != "")
{
  $target_dir = "/tmp/";
  $path_parts = pathinfo(basename($_FILES["filetoshare"]["name"]));
  $ext = $path_parts['extension'];
  $target_dir = $target_dir . $target_name . "." . $ext;
  $uploadOk=1;
  $uploaded=0;

  if (move_uploaded_file($_FILES["filetoshare"]["tmp_name"], $target_dir)) {
     $tts="file";
  }
 else
 {
    USERERROR("Your file is larger than 2 MB and cannot be uploaded.");
 }
}

# First store in DB so we get mid
# Now store everything in the DB
# if not already there
if ($sharedmethod == "zip")
    $path = "";
else
    $path = $url;

$sql = "select * from class_materials where pid='$pid' and title='$title'";
$query_result = DBQueryFatal($sql);      
if (mysql_num_rows($query_result) == 0)
{     
    $sql = "insert into class_materials (uid, pid, gid, title, path, type) values ('$uid', '$pid', 'none', '$title', '$path', '$type')"; 
    $query_result = DBQueryFatal($sql);
}

# Now get the mid
$sql = "select mid from class_materials where pid='$pid' and title='$title'";
$query_result = DBQueryFatal($sql);      
$row = mysql_fetch_array($query_result);
$mid = $row['mid'];

if ($sharedmethod == "zip")
{
  $message = "";
  $mygid = $pid;
  if ($gid == "all")
     $path = "/groups/$pid/$pid/$mid";
  else
  {
     $path = "/groups/$pid/$gid/$mid";
     $group = Group::LookupByPidGid($pid, $gid);
     $mygid = $group->unix_name();
  }
  if ($fp = popen("$TBSUEXEC_PATH $uid $mygid webaddfile $target_dir $tts $type $path $uid $pid $gid", "r")) {
   while (!feof($fp)) {
       $string = fgets($fp, 1024);
       $message .= $string;
       if (preg_match('/OK/',$string))
       {
	$ok = 1;
       } 
   }
 pclose($fp);
 }

 if($sharedmethod == "file")
   unlink($target_dir);
}
else
  $ok = 1;


if ($ok)
{
   $sql = "update class_materials set uid='$uid', gid='$gid', title='$title', type='$type', path='$path' where mid='$mid'";
   $query_result = DBQueryFatal($sql);
   # Redirect to assign to students
   echo "<script type=\"text/javascript\">\n";
   echo "window.location = \"manage_class.php?pid=$pid&action=assigntostudents\"";
   echo "</script>";	    
}
else
{
   $ermsg="";
   preg_match_all('/(Error:.*)/', $message, $match, PREG_PATTERN_ORDER);
   foreach ($match[0] as $value)
      $ermsg .= ($value . "<br>");
   USERERROR($ermsg);
}



#
# Standard Testbed Footer
# 
PAGEFOOTER();
?>



