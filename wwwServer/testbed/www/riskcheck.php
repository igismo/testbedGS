<?php
#
# EMULAB-COPYRIGHT
# Copyright (c) 2000-2007 University of Utah and the Flux Group.
# All rights reserved.
#
include("defs.php");

#
# Define a stripped-down view of the web interface - less clutter
#
$view = array(
    'hide_banner' => 1,
    'hide_sidebar' => 1,
    'hide_copyright' => 1
);

#
# Only known and logged in users can begin experiments.
#
$this_user = CheckLoginOrDie();
$uid       = $this_user->uid();
$isadmin   = ISADMIN();

#
# Verify page arguments.
#
$optargs = OptionalPageArguments("formfields",      PAGEARG_ARRAY,
				 "fromform",        PAGEARG_BOOLEAN,	
				 "nsfilecont", 	    PAGEARG_STRING);

#
# Standard Testbed Header (not that we know if want the stripped version).
#
PAGEHEADER("Risk/Connectivity Sanity Check");

include("risk.php");

if (isset($formfields)) 
   if (isset($nsfilecont))
      CheckOptions(true, $formfields, $nsfilecont);
   else
      CheckOptions(true, $formfields, "");

#
# Standard Testbed Footer
# 
PAGEFOOTER();
?>
