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
$loggedin = 0;
if ($status & (CHECKLOGIN_USERSTATUS|CHECKLOGIN_WEBONLY))
{
   $this_user = $CHECKLOGIN_USER;
   $loggedin = 1;

}

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


echo "<script type='text/javascript' language='javascript'>\n
function showHide(name)
{
   elem = document.getElementById(name);
   if (elem.style.display == 'none')
      elem.style.display='block';
   else
      elem.style.display='none';
}

function showselected()
{
  f = document.form2;\n
  sel = f.adoptmethod.selectedIndex;\n
  if (sel == 0)
  {
     f.adopttoclass.style.display='none';\n
  }
  if (sel == 1)
  {
     f.adopttoclass.style.display='inline';\n
  }
}</script>";

echo "<form name='form2' id='form2' enctype='multipart/form-data' action='adopt.php' method='POST'>\n";
echo "<input type=hidden name=uid value='$uid'>\n";

$results = array();
$outputs = array();
$obt = array();
$count = array();
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
	$output = "<TR><TD>";
	$output .= "<A HREF=\"file.php?file=$path\" "; 
	$output .= "target=\"_blank\">$title</A></TD><TD>$desc</TD><TD><A HREF='mailto:$cuid'>$cuid</A></TD><TD>$contenttags</TD>";
	if ($loggedin)
	   $output .= "<TD><input type='checkbox' name='adopt$mid' value='$title|$path|$type'></TD></TR>\n";
	else
	   $output .= "</TR>\n";
	$results[$i] = $score;
	$outputs[$i] = $output;
	if (!array_key_exists($type, $obt))
	{
	   $obt[$type] = "";
	   $count[$type]=0;
	}
	$obt[$type] .= $output;
	$count[$type]++;
	$i++;
    }
}
foreach ($obt as $type => $value)
{
      echo "<TABLE class=stealth WIDTH=20% style='table-layout:fixed'><TR><TD valign=top WIDTH=10%><a href='#' onclick='showHide(\"$type\");return false;'><img src='plus.jpg' width=20></a></TD><TD valign=center align=left><B>$type ($count[$type])</B></TD></TR></TABLE>\n";
      echo "<TR><TD><TABLE class=stealth WIDTH=80% id='$type' style='display:none'><TR><TH width='15%'>Title</TH><TH width='35%'>Description</TH><TH width='10%'>Contact</TH><TH width='25%'>Tags</TH>";
      if ($loggedin)
      	 echo "<TH width='5%'>Adopt?</TH></TR>\n";
      else
         echo "</TR>\n";
      echo "$obt[$type]</TABLE>";
}

if ($loggedin)
{
 echo "<P><center>";

 $cmd =         'select p.pid from projects p, group_membership m ' .
        ' where p.pid_idx = m.pid_idx and m.pid_idx = m.gid_idx and ' .
        '       p.class and (m.trust = "group_root" or m.trust = "project_root") and ' .
        "       m.uid = '$uid' " .
        ' order by p.pid ';
 $query_result = DBQueryFatal($cmd);


  if ($i > 0)
  {
	echo "<TABLE class=stealth><TR><TD>With shared materials: <select onchange='showselected()' name='adoptmethod' ID='adoptmethod' form='form2'>
	<option value='zip' selected>Download as zip</option>
	<option value='class'>Adopt to my class</option> 
	</select>
	<input type='text' value='' size='50'  name='adopttofolder' style='display: none'>\n
	<select name='adopttoclass' form='form2' style='display: none'>\n";
 	while ($row = mysql_fetch_array($query_result)) {
            $pid = $row['pid'];
	    echo "<option>$pid</option>\n";
	}
	echo "</select></td></tr>";

	echo "<tr><td colspan=2 align='center'><p><button onclick='javascript: document.form2.submit()' style='font: bold 14px Arial' type='button'>Adopt</button></table></form><br><br>\n";
}
else
    echo "</center></form>";
}



#
# Standard Testbed Footer
# 
PAGEFOOTER();
?>



