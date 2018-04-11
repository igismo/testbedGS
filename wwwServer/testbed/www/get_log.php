<?php
#
# EMULAB-COPYRIGHT
# Copyright (c) 2000-2008 University of Utah and the Flux Group.
# All rights reserved.
#
include("defs.php");

#
# Note the difference with which this page gets it arguments!
# I invoke it using GET arguments, so uid and pid are are defined
# without having to find them in URI (like most of the other pages
# find the uid).
#

#
# Only known and logged in users.
#
$this_user = CheckLogin($status);
if ($this_user)
    $uid = $this_user->uid();
$isadmin   = ISADMIN();

#
# Verify page arguments.
#
$reqargs  = RequiredPageArguments("project", PAGEARG_PROJECT);
$optargs  = OptionalPageArguments("action", PAGEARG_STRING);
$project  = $reqargs["project"];
if (isset($optargs["action"]))
{
	$action = $optargs["action"];
}
$group    = $project->Group();
$pid      = $project->pid();

#
# Standard Testbed Header
#


if (! ($this_user && $project->AccessCheck($this_user, $TB_PROJECT_READINFO))) {
   USERERROR("You are not a member of project $pid.", 1);
}

if (!TBMinTrust(TBGrpTrust($uid, $pid, $pid), $TBDB_TRUST_GROUPROOT)) {
   USERERROR("You cannot manage project $pid.", 1);
}

$output = file_get_contents("/tmp/$pid.log");
echo "$output";
?>
