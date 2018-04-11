<?php
#
# EMULAB-COPYRIGHT
# Copyright (c) 2000-2008 University of Utah and the Flux Group.
# All rights reserved.
#
include("defs.php");

#
# Only known and logged in users can end experiments.
#
$this_user = CheckLoginOrDie();
$uid       = $this_user->uid();
$isadmin   = ISADMIN();

$debug  = 0;
$debug2 = 0;
$debug3 = 0;

# Summary data for admins only.
if (!$isadmin && !STUDLY()) {
    USERERROR("You are not allowed to view this page!", 1);
}


#
# Standard Testbed Header
#
PAGEHEADER("Testbed Statistics");

echo "<H3>Testbed usage statistics</H3>";
echo "<A HREF=\"$TBBASE/showstats.php\">Show summary stats for the testbed</A><BR>";
echo "<A HREF=\"$TBBASE/view_usage.php\">View testbed usage (number of nodes) and utilization</A><BR>";
echo "<A HREF=\"$TBBASE/measure_usage.php\">View testbed usage in node hours</A><BR>";
echo "<A HREF=\"$TBBASE/view_heatmap.php\">View usage per project as a heatmap</A><BR>";
echo "<A HREF=\"$TBBASE/map_users.php\">Map testbed users geographically</A><BR>";
echo "<A HREF=\"$TBBASE/view_projects.php\">View project statistics (per research type, institution, etc.)</A><BR>";

echo "<H3>Forecast usage</H3>";
echo "<A HREF=\"$TBBASE/project_usage.php\">Forecast new and active project counts based on available data</A> (takes some time to load)<BR>";
	
#
# Standard Testbed Footer
# 
PAGEFOOTER();
?>
