<?php
#
# EMULAB-COPYRIGHT
# Copyright (c) 2000-2002, 2004, 2005 University of Utah and the Flux Group.
# All rights reserved.
#
require("defs.php");

#
# Standard Testbed Header
#
PAGEHEADER("View Testbed Stats");
?>

<ul>
<li><dl>
<dt><b><a href="updatestats.php?range=month">
	      Show Monthly Stats</a></b></dt>
    <dd> </dd>
</dl><br>

<li><dl>
<dt><b><a href="updatestats.php?range=week">
              Show Weekly Stats</a></b></dt>
    <dd> </dd>
</dl><br>

<li><dl>

<dt><b><a href="showuserexp.php?range=week">
              Show all the experiments running on the testbed (except the ones by Testbed-Ops)</a></b></dt>
    <dd> </dd>
</dl><br>
</ul>

<?php
#
# Standard Testbed Footer
# 
PAGEFOOTER();
?>

