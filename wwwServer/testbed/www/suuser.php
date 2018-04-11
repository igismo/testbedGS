<?php
#
# EMULAB-COPYRIGHT
# Copyright (c) 2000-2003, 2006, 2007 University of Utah and the Flux Group.
# All rights reserved.
#
include("defs.php");

#
# Only known and logged in users allowed.
#
$this_user = CheckLoginOrDie();

#
# Verify arguments.
#
$reqargs = RequiredPageArguments("target_user", PAGEARG_USER);
$target_uid = $target_user->uid();
$target_uidx = $target_user->uid_idx();

if ((!ISADMIN()) && !$target_user->InstructedBy($this_user)) {
    USERERROR("You do not have permission to do this!", 1);
}

if (DOLOGIN_MAGIC($target_user->uid(), $target_user->uid_idx()) < 0) {
    USERERROR("Could not log you in as $target_uid", 1);
}
# So the menu and headers get spit out properly.
$_COOKIE[$TBNAMECOOKIE] = $target_uid;

echo "<script type=\"text/javascript\">\n";
echo "<!--\nwindow.location = \"showuser.php?user=$target_uidx\"\n//-->\n";
echo "</script>";

#
# Standard Testbed Footer
# 
PAGEFOOTER();
?>
