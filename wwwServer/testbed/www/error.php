<?php
#
# EMULAB-COPYRIGHT
# Copyright (c) 2000-2002, 2007 University of Utah and the Flux Group.
# All rights reserved.
#
require("defs.php");

# No page arguments, but make sure that the environment is clean
RequiredPageArguments();

#
# Standard Testbed Header
#
PAGEHEADER("Non Existent Page!");

$uri=htmlentities($_SERVER['REQUEST_URI']);

USERERROR("The URL you gave: <b>$uri</b>
           is not available or is broken.", 1);

#
# Standard Testbed Footer
# 
PAGEFOOTER();
?>

