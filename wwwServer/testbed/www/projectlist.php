<?php
#
# EMULAB-COPYRIGHT
# Copyright (c) 2000-2002, 2007 University of Utah and the Flux Group.
# All rights reserved.
#
include("defs.php");

$status    = CHECKLOGIN_NOTLOGGEDIN;
$this_user = CheckLogin($status);
$isadmin = ISADMIN();

#
# Standard Testbed Header
#
# Change when do check for number of experiments.
PAGEHEADER("Projects that have actively used ".strtolower($THISHOMEBASE));

#
# We let anyone access this page.  Its basically a pretty-printed version
# of the current testbed clients, who have not opted out from this display.
#
# Complete information is better viewed with the "Project Information" link.
# That requires a logged in user though. 
#

#
# Helper
#
function GENPLIST ($query_result)
{
    global $isadmin;

    echo "<tr>
             <th>Name</th>
             <th>Institution</th>";
    if ($isadmin)
	echo "<th>PID</th><th>Public</th>";
    echo "</tr>\n";

    while ($projectrow = mysql_fetch_array($query_result)) {
	$pname  = $projectrow["name"];
	$url    = $projectrow["URL"];
	$affil  = $projectrow["usr_affil"];
	$pid    = $projectrow["pid"];
	$public = $projectrow["public"] ? 'Yes' : 'No';

	echo "<tr>\n";

	if (!$url || strcmp($url, "") == 0) {
	    echo "<td>$pname</td>\n";
	}
	else {
	    echo "<td><A href=\"$url\">$pname</A></td>\n";
	}

	echo "<td>$affil</td>\n";

	if ($isadmin) {
            echo "<td><a href=\"showproject.php?pid=$pid\">$pid</a></td>\n";
	    echo "<td>$public</td>\n";
	}

	echo "</tr>\n";

    }
}

#
# Get the "active" project list.
#
$query =	 "SELECT pid,name,URL,usr_affil,public FROM projects ".
		 "left join users on projects.head_idx=users.uid_idx ".
		 "where projects.status='approved' and expt_count>0 ";

# red dot can see private projects
if (!$isadmin)
    $query .= " and public ";

$query .= "order by name";

$query_result = DBQueryFatal($query);

echo "<table width=\"100%\" border=0 cellpadding=2 cellspacing=2
             align='center'>\n";

if (mysql_num_rows($query_result)) {
    GENPLIST($query_result);
}

#
# Get the "inactive" project list.
#
$query_result =
$query =	 "SELECT pid,name,URL,usr_affil,public FROM projects ".
		 "left join users on projects.head_idx=users.uid_idx ".
		 "where projects.status='approved' and expt_count=0 ";
if (!$isadmin)
    $query .= "and public ";
$query .= "order by name";

$query_result = DBQueryFatal($query);

if (mysql_num_rows($query_result)) {
    echo "<tr><th colspan=2>
              Other projects registered on DETERlab:</h4>
              </th>
          </tr>\n";
    GENPLIST($query_result);
}

echo "</table>\n";

#
# Standard Testbed Footer
# 
PAGEFOOTER();
?>
