<?php
#
# EMULAB-COPYRIGHT
# Copyright (c) 2000-2003 University of Utah and the Flux Group.
# All rights reserved.
#
include("defs.php");

#
# Standard Testbed Header
#
PAGEHEADER("Request a New DETERlab Account");

echo "<center><font size=+1>
       If you already have a DETERlab account,
       <a href=login.php>
       <font color=red>please log on first!</font></a>
       <br><br>
       <a href=joinproject.php>Join an Existing Project</a>.
       <br>
	(with an approval of a project leader).
	<br>
       or
       <br>
       <a href=newproject.php>Create a New DETERlab Project</a>.
       <br><br>
	<br>
       <font size=-1>
       If you are a <font color=red>student (undergrad or graduate)</font>,
       please do not try to start a project!<br> Your advisor must do it.
       </font>

      </font></center><br>\n";

#
# Standard Testbed Footer
# 
PAGEFOOTER();
?>
