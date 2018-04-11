<?php
#
# This file is part of the Netbed/Emulab network testbed software.
# In brief, you can't redistribute it or use it for commercial purposes,
# and you must give appropriate credit and return improvements to Utah.
# See the file LICENSE at the root of the source tree for details.
# 
# Copyright (c) 2000-2002, 2004 University of Utah and the Flux Group.
# All rights reserved.
#
require("defs.php");

#
# Only known and logged in users can do this.
#
$uid = GETLOGIN();
LOGGEDINORDIE($uid);
$isadmin = ISADMIN($uid);

#
# Standard Testbed Header
#
PAGEHEADER("Internal Documentation");
?>


<h3>Please refer to the DETER wiki at <a href="https://trac.deterlab.net">https://trac.deterlab.net</a>.</h3>

<?php
#
# Standard Testbed Footer
# 
PAGEFOOTER();
?>

