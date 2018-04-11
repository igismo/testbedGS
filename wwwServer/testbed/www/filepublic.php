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

PAGEHEADER("Public Education Materials", null, null, $notice);
#
# Public function
#

# Standard Testbed Header
$mid = $_GET['mid'];
$uid = "www";
$pid = "www";

$sql = "select type, path from shared_materials where mid='$mid'";	
$query_result = DBQueryFatal($sql);

if (mysql_num_rows($query_result) == 0)
   USERERROR("Such mid does not exist\n");

$row = mysql_fetch_array($query_result);
$file = $row["path"];
$type = $row["type"];

if ($type != "Lecture" && $type != "Homework" && $type != "CCTF")
   USERERROR("Materials of type $type cannot be accessed without a login.");


if (!preg_match("/^http\:\/\//", $file))
{
   if(!isset($ext))
  {
   $file = $file . "/index.html";
   $ext = "html";
  }


  if ($fp = popen("$TBSUEXEC_PATH $uid $pid webreadfile $file", "r")) {
    $newcontents = "";
    while (!feof($fp)) {
        $newcontents .= fgets($fp, 1024);
    }
  }
  # List all extensions for which contents will be handed to the browser
  if ($ext == "html" || $ext == "htm" || $ext == "php" || $ext == "txt" || $ext == "jpg" || $ext == "gif" || $ext == "png" || $ext == "bmp" || $ext == "css")
    echo $newcontents;
  # Any other file types will be downloaded
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



#
# Standard Testbed Footer
#
PAGEFOOTER();
?>
