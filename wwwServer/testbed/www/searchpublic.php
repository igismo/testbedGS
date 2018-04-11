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

$this_user = null;
global $CHECKLOGIN_USER;
$status = LoginStatus();
if ($status & (CHECKLOGIN_USERSTATUS|CHECKLOGIN_WEBONLY))
    $this_user = $CHECKLOGIN_USER;

# Standard Testbed Header
#
PAGEHEADER("Search shared materials", null, null, null);


$cmd = "SELECT * from shared_materials";
$tags = "";
$types = "";

$uid=trim($_POST["uid"]);
$searchtags=trim($_POST["searchtags"]);

if (!preg_match("/^[a-zA-Z\s\-0-9]*$/",$searchtags))
{
   USERERROR("Search tags can only contain letters, numbers, spaces and dashes");
}

echo "<center><h2>Shared materials found for search ";

foreach ($_POST as $key => $value)
{
  if ($key == "searchtags")
  {
	$tags = explode(" ", strtolower($value));
	echo " <FONT COLOR='blue'>$value</FONT></h2>";
  }
  elseif(preg_match("/searchtype/",$key))	
  {
       if ($types != "")
       {
           $types .= " or ";
       }	
      $types .= "type=\"$value\"";
  }
}
  

if ($types != "")
  {
     $cmd .= " where " . $types;
  }

$query_result = DBQueryFatal($cmd);


echo "<TABLE WIDTH=80%><TR><TH>Relevance</TH><TH>Type</TH><TH>Title</TH><TH>Description</TH><TH>Contact</TH><TH>Tags</TH></TR>\n";

$results = array();
$outputs = array();
$i=0;

while($row = mysql_fetch_array($query_result))
{
    $type = $row["type"];
    $title = $row["title"];
    $desc = $row["description"];
    $path = $row["path"];
    $cuid = $row["contact_email"];
    $mid = $row["mid"];
    $contenttags = $row["tags"];

    $items = explode("/", $path);
    $folder = $items[count($items)-1];    

    $contentwords = explode(" ", strtolower($contenttags));

    # Calculate a score, how many tags match
    $score=0;

    # Deal with All search
    if ($searchtags == "All")
       $score = 1;
    else
    {
      foreach ($tags as $v)
      {
    	  if (in_array($v, $contentwords))
	     $score ++;
      }
    }
    if ($score > 0)
    {
	$output = "<TR><TD>$score</TD><TD>$type</TD><TD>";
	$output .= "<A HREF=\"filepublic.php?mid=$mid\" "; 
	$output .= "target=\"_blank\">$title</A></TD><TD>$desc</TD><TD><A HREF='mailto:$cuid'>$cuid</A></TD><TD>$contenttags</TD></TR>\n";
	$results[$i] = $score;
	$outputs[$i++] = $output;
    }
}
arsort($results);
foreach ($results as $key=>$value)
	echo $outputs[$key];
echo "</TABLE>";
    echo "<P><center>";







#
# Standard Testbed Footer
# 
PAGEFOOTER();
?>



