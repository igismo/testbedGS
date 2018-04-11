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


# Print sharing info
PAGEHEADER("Public Access to Shared Materials", null, null, $notice);


echo "<script type='text/javascript' language='javascript'>\n
function toggle(source)
{
   var c = new Array();
   c = document.getElementsByTagName('input');

   for (var i = 0; i < c.length; i++)
   {
       if (c[i].type == 'checkbox')
       {
           c[i].checked = true;
       }
   }
}
</script>";


echo "<form name='form11' enctype='multipart/form-data' action='search.php' method='POST'>\n";
echo "<center><h3>Find shared materials</h3>\n";
echo "<center><table class=stealth width=80%>\n";
echo "<tr><td class=color align='right' width='20%'>Enter search terms separated by space:</td>";
echo "<td class=color alight='left'><input type='text' size='100'  value='All' name='searchtags'></td></tr>\n";
echo "<tr><td class=color align='right'><br>Check all types that apply</td><td class=color align='left'>\n";

$type_result = DBQueryFatal("show columns in shared_materials where Field='type'");

$arr = "";
if (mysql_num_rows($type_result)) {

while ($typerow = mysql_fetch_array($type_result)) {
        $result = $typerow["Type"];
	$result = str_replace(array("enum('", "')", "''"), array('', '', "'"), $result);
	$arr = explode("','", $result);
	$i=1;
	foreach ($arr as $value) {
	echo "<input type=\"checkbox\" name=\"searchtype$i\" value=\"$value\">$value\n";
	$i++;
	}
	echo "<input type=\"checkbox\" onClick=\"toggle(this)\" />All<br/>\n";
	}
}
$filename='';

echo "</td></tr><tr><td colspan=2 align='center'><br><button type='button' onclick='javascript: document.form11.submit()'; style='font: bold 14px Arial'>Search</button></a></table>";
echo "<input type=hidden name=uid value=$uid></form>";

#
# Standard Testbed Footer
# 
PAGEFOOTER();
?>



