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
PAGEHEADER("Share your materials", null, null, null);

$title=trim($_POST["sharedtitle"]);
$desc=trim($_POST["shareddesc"]);
$type=trim($_POST["sharedtype"]);
$serverpath=trim($_POST["sharedpath"]);
$url=trim($_POST["sharedURL"]);
$sharedmethod=trim($_POST["sharedmethod"]);
$maintainer=trim($_POST["contactauthor"]);
$replacementmid=trim($_POST["replacementmid"]);
$tags = "";
for ($i = 1; $i <= 10; $i++)
    $tags .= (" " . trim($_POST["sharedtags$i"]));
$uid=$this_user->uid();
$pid=trim($_POST["pid"]);
if (isset($_POST['relatedmaterial']) && $type == 'Teacher Manual')
   $mid = trim($_POST['relatedmaterial']);
   


if ($title == "")
   USERERROR("Empty title is not allowed");

if (!preg_match("/^[a-zA-Z\s\-0-9]*$/",$title))
   USERERROR("Title can only contain letters, numbers, spaces and dashes");

if ($desc == "")
   USERERROR("Empty description is not allowed");

if (!preg_match("/^[a-zA-Z\s\-0-9\.\,]*$/",$desc))
   USERERROR("Description can only contain letters, numbers, spaces, dashes, commas and periods.");

if ($type == "")
   USERERROR("You must select a type for the material you are sharing");

# Check that the path is properly set
if ($serverpath != "")
{
  if (!preg_match("/^\/proj\/|^\/users\/|^\/share\//", $serverpath))
    USERERROR("You must specify a path that starts with /proj/, /users/ or /share/");
 else
    $target_dir=$serverpath; 
}   

# Check that the url is properly set
if ($url != "")
{
  if (!preg_match("/^http\:\/\//", $url))
    USERERROR("You must specify a URL that starts with http://");
}   
# Check email
if ($maintainer == "")
    USERERROR("You must specify the email of the person in charge of maintaining this artifact");
if (!preg_match("/^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}$/i", $maintainer))
    USERERROR("You must specify a valid email");   

# Check tags
if ($tags == "")
   USERERROR("You must specify at least one tag");


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
    USERERROR("Your file is larger than 2 MB and cannot be uploaded.");
}
elseif ($sharedmethod == "folder" && $serverpath != "")
{
  $tts="path";
}


# For files and folders, copy their content to share
# For URLs just put metadata in DB
$contents = "";
if ($tts != "")
{
  $message = "";
  $shorttitle = str_replace(" ", "", $title);
  $typens = str_replace(" ", "", $type);
  $estitle=escapeshellarg($shorttitle);

  if ($fp = popen("$TBSUEXEC_PATH $uid www websharefile $target_dir $estitle $tts $typens $mid", "r")) {
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

 if($tts == "file")
   unlink($target_dir);
}
else
{
  # We're sharing a URL
  $ok = 1;
}

# Now store everything in the DB
# if not already there
if ($ok)
{ 
   if ($tts != "")
   {
      if ($type == 'Teacher Manual')
      	 $path = '/proj/teachers/' . $mid;
      else
	$path = "/share/shared/$shorttitle";
   }
   else
      $path = $url;
   $cmd = "select * from shared_materials where title='" . $title . "' and author_uid='" . $uid . "'";

   $query_result = DBQueryFatal($cmd);

  
   if($replacementmid != -1 && $replacementmid != "")
   {
      $cmd = "update shared_materials set  title='" . $title . "',type='" . $type . "', description='" . $desc .  "', path='" . $path .  "', contact_email='" . $maintainer .  "', tags='" . $tags . "' where mid=" . $replacementmid;
      $query_result = DBQueryFatal($cmd);
   }
   else 
   {
      if(mysql_num_rows($query_result) > 0) 
   	USERERROR("Cannot share two materials with the same title");

      $cmd = "insert into shared_materials (type, title, description, path, author_uid, contact_email, tags) values ('" . $type . "','" . $title ."','" . $desc . "','" . $path . "','" . $uid .  "','" . $maintainer . "','" . $tags . "')";

      $query_result = DBQueryFatal($cmd);
      $query_result = DBQueryFatal("select mid from shared_materials where title='" . $title . "' and author_uid='" . $uid . "'");
      $row = mysql_fetch_array($query_result);
      $mid = $row['mid'];
      $query_result = DBQueryFatal("insert into shared_materials_usage (mid, used) values ('$mid', 0)");
    }

 
   # And show the stored materials
   echo "<center><h2>Your shared materials are now online!</h2><P>";
   $cmd = "SELECT * from shared_materials where title='$title' and author_uid='$uid'";

   $query_result = DBQueryFatal($cmd);
   echo "<TABLE><TR><TH>Type</TH><TH>Title</TH><TH>Description</TH><TH>Materials</TH><TH>Contact</TH><TH>Tags</TH></TR>\n";

   while($row = mysql_fetch_array($query_result))
   {
    $type = $row["type"];
    $title = $row["title"];
    $desc = $row["description"];
    $path = $row["path"];
    $cuid = $row["contact_email"];
    $tags = $row["tags"];
    $url = $path;
    echo "<TR><TD>$type</TD><TD>$title</TD><TD>$desc</TD><TD><A HREF=\"file.php?file=$url\" target=\"_blank\">Materials</A></TD><TD><A HREF='mailto:$cuid'>$cuid</A></TD><TD>$tags</TD></TR>\n";
    }
    echo "</TABLE>";
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



