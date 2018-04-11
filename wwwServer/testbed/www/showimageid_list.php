<?php
#
# EMULAB-COPYRIGHT
# Copyright (c) 2000-2004, 2006, 2007 University of Utah and the Flux Group.
# All rights reserved.
#
include("defs.php");

#
#
# Only known and logged in users allowed.
#
$this_user = CheckLoginOrDie();
$uid       = $this_user->uid();
$isadmin   = ISADMIN();

#
# Admin users can see all ImageIDs, while normal users can only see
# ones in their projects or ones that are globally available.
#
$optargs = OptionalPageArguments("creator",  PAGEARG_USER);
$extraclause = "";

#
# Standard Testbed Header
#
PAGEHEADER("Image List");

#
# Allow for creator restriction
#
if (isset($creator)) {
    $creator_idx = $creator->uid_idx();
    
    if ($isadmin) {
	$extraclause = "where i.creator_idx='$creator_idx' ";
    }
    elseif ($creator->SameUser($this_user)) {
	$extraclause = "and i.creator_idx='$creator_idx' ";
    }
}

#
# Get the list.
#
if ($isadmin) {
    $query_result = DBQueryFatal(
        "SELECT distinct i.*, ifnull(o.OS, '&nbsp;') as OS " .
        "  FROM images as i " .
        "  left join osidtoimageid as id on i.imageid = id.imageid " .
        "  left join os_info as o on id.osid = o.osid " .
	" $extraclause ".
	" order by cast(o.OS as char), i.imagename");
}
else {
    #
    # User is allowed to view the list of all global images, and all images
    # in his project. Include images in the subgroups too, since its okay
    # for the all project members to see the descriptors. They need proper 
    # permission to use/modify the image/descriptor of course, but that is
    # checked in the pages that do that stuff. In other words, ignore the
    # shared flag in the descriptors.
    #
    $uid_idx = $this_user->uid_idx();
    
    $query_result =
        DBQueryFatal("select distinct i.imageid, i.description, ".
             "i.imagename, i.pid, o.OS from images as i ".
	     "left join group_membership as g on g.pid=i.pid ".
             "left join osidtoimageid as id ON id.imageid=i.imageid ".
             "left join os_info as o on id.osid=o.osid ".
	     "where (g.uid_idx='$uid_idx' or i.global) ".
             "and o.supported=0 ".
	     "$extraclause ".
	     "order by cast(o.OS as char), i.imagename");
}

function image_query($support) {
    return DBQueryFatal(
            "SELECT DISTINCT i.imageid, i.description, ".
            "i.imagename, i.pid, o.OS  from images as i ".
            "LEFT JOIN group_membership as g on g.pid=i.pid ".
            "LEFT JOIN osidtoimageid AS id ON id.imageid=i.imageid ".
            "LEFT JOIN os_info AS o on id.osid=o.osid ".
            "WHERE o.supported=$support ORDER BY cast(o.OS as char), i.imagename");
}

function image_list($title, $table, $result) {
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
            $imageid   = $row["imageid"];
            $descrip   = $row["description"];
            $imagename = $row["imagename"];
            $pid       = $row["pid"];
            $os        = $row["OS"];
            $url       = CreateURL("showimageid", URLARG_IMAGEID, $imageid);

            echo "<tr>   
                <td><A href='$url'>$imagename</A></td>
                <td>$os</td>
                <td>$pid</td>
                <td>$descrip</td>\n";
            echo "</tr>\n";
        }
        echo "</table>\n";
    }
}

SUBPAGESTART();
SUBMENUSTART("More Options");
if ($isadmin) {
    WRITESUBMENUBUTTON("Create an Image Descriptor",
                       "newimageid_ez.php");
    WRITESUBMENUBUTTON("Create an OS Descriptor",
		       "newosid.php");
}
WRITESUBMENUBUTTON("OS Descriptor list",
		   "showosid_list.php");
SUBMENUEND();

echo "Listed below are the Images that you can load on your nodes with the
      <a href='$WIKIDOCURL/nscommands%23OS'>
      <tt>tb-set-node-os</tt></a> directive. If the OS you have selected for
      a node is not loaded on that node when the experiment is swapped in,
      the Testbed system will automatically reload that node's disk with the
      appropriate image. You might notice that it takes a few minutes longer
      to start your experiment when selecting an OS that is not
      already resident. Please be patient.
      <br>
      More information on how to create your own Images is in the
      <a href='$WIKIDOCURL/Tutorial%23CustomOS'>Custom OS</a> section of
      the <a href='$WIKIDOCURL/Tutorial'>Emulab Tutorial.</a>
      <br>\n";

SUBPAGEEND();

image_list('Officially Supported', 'showsupportedlist', image_query(1));
image_list('Seasonal', 'showseasonallist', image_query(2));
image_list('Custom and Legacy', 'showimagelist', $query_result);

if ($isadmin)
    image_list('Hidden from normal users', 'showhiddenlist', image_query(3));


#
# Standard Testbed Footer
# 
PAGEFOOTER();
?>
