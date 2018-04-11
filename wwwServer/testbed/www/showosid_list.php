<?php
#
# EMULAB-COPYRIGHT
# Copyright (c) 2000-2004, 2006, 2007 University of Utah and the Flux Group.
# All rights reserved.
#
include("defs.php");

#
# Only known and logged in users allowed.
#
$this_user = CheckLoginOrDie();
$uid       = $this_user->uid();
$isadmin   = ISADMIN();

#
# Admin users can see all OSIDs, while normal users can only see
# ones in their projects or ones that are globally available.
#
$optargs = OptionalPageArguments("creator",  PAGEARG_USER);

#
# Standard Testbed Header
#
PAGEHEADER("OS Descriptor List");

#
# Allow for creator restriction
#
$extraclause = "";
if (isset($creator)) {
    $creator_idx = $creator->uid_idx();

    if ($isadmin) 
	$extraclause = "where o.creator_idx='$creator_idx' ";
    else
	$extraclause = "and o.creator_idx='$creator_idx' ";
}

#
# Get the project list.
#
if ($isadmin) {
    $query_result =
	DBQueryFatal("SELECT * FROM os_info as o ".
		     "$extraclause ".
		     "order by cast(o.OS as char), o.osname");
}
else {
    $uid_idx = $this_user->uid_idx();
    
    $query_result =
	DBQueryFatal("select distinct o.* from os_info as o ".
		     "left join group_membership as g on g.pid=o.pid ".
		     "where ( g.uid_idx='$uid_idx' AND supported = 0) ".
		     "$extraclause ".
		     "order by cast(o.OS as char), o.osname");
}

#
# Get supported OS list
#

$supported_result = 
    DBQueryFatal("SELECT o.* FROM os_info AS o ".
            "WHERE supported=1 ORDER BY cast(o.OS as char), o.osname");

#
# Get Seasonal OS list
#

$unsup_result =
    DBQueryFatal("SELECT o.* FROM os_info AS o ".
            "WHERE supported=2 ORDER BY cast(o.OS as char), o.osname");

$hidden_result =
    DBQueryFatal("SELECT o.* FROM os_info AS o ".
            "WHERE supported=3 ORDER BY cast(o.OS as char), o.osname");

SUBPAGESTART();
SUBMENUSTART("More Options");

if ($isadmin) {
    WRITESUBMENUBUTTON("Create an Image Descriptor",
                       "newimageid_ez.php");
    WRITESUBMENUBUTTON("Create an OS Descriptor",
		       "newosid.php");
}
WRITESUBMENUBUTTON("Image Descriptor list",
		   "showimageid_list.php");
SUBMENUEND();

echo "Listed below are the OS Descriptors that you may use in your NS file
      with the <a href='$WIKIDOCURL/nscommands%23OS'>
      <tt>tb-set-node-os</tt></a> directive. If the OS you have selected for
      a node is not loaded on that node when the experiment is swapped in,
      the Testbed system will automatically reload that node's disk with the
      appropriate image. You might notice that it takes a few minutes longer
      to start your experiment when selecting an OS that is not
      already resident. Our most-common resident OS is
      <strong>Ubuntu1204-64-STD</strong>;
      please be patient if you choose something different.
      <br><br>
      More information on how to create your own Images is in the
      <a href='$WIKIDOCURL/Tutorial%23CustomOS'>Custom OS</a> section of
      the <a href='$WIKIDOCURL/Tutorial'>Emulab Tutorial.</a>
      <br>\n";

SUBPAGEEND();

echo "<script type='text/javascript' src=sorttable2.js'></script>\n";

osid_list('Officially Supported', 'showsupportedlist', $supported_result);
osid_list('Seasonal', 'showunsuplist', $unsup_result);
osid_list('Custom and Legacy', 'showosidlist', $query_result);

if ($isadmin)
    osid_list('Hidden from Normal Useres', 'showhiddenlist', $hidden_result);

function osid_list($title, $table, $result) {
    if (mysql_num_rows($result)) {
        echo "<div style='margin-top:15px; margin-bottom: -12px' align='center'><b>$title</b></div>";
        echo "<br>
            <table border=2 cellpadding=0 cellspacing=2
            align='center' id='$table' class='sortable'>\n";

        echo "<thead class='sort'>
            <tr>
            <th>Name</th>
            <th>OS</th>
            <th>PID</th>
            <th>Description</th>
            </tr>
            </thead>\n";


        while ($row = mysql_fetch_array($result)) {
            $osname  = $row["osname"];
            $osid    = $row["osid"];
            $descrip = $row["description"];
            $os      = $row["OS"];
            $pid     = $row["pid"];
            $url     = CreateURL("showosinfo", URLARG_OSID, $osid);

            echo "<tr>
                <td><A href='$url'>$osname</A></td>
                <td>$os</td>
                <td>$pid</td>
                <td>$descrip</td>\n";
            echo "</tr>\n";
        }
        echo "</table>\n";
    }
}


#
# Standard Testbed Footer
# 
PAGEFOOTER();
?>

