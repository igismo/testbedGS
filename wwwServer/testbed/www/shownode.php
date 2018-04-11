<?php
#
# EMULAB-COPYRIGHT
# Copyright (c) 2000-2007 University of Utah and the Flux Group.
# All rights reserved.
#
include("defs.php");
include_once("node_defs.php");

#
# Only known and logged in users can do this.
#
$this_user = CheckLoginOrDie();
$uid       = $this_user->uid();
$isadmin   = ISADMIN();

#
# Verify page arguments.
#
$reqargs = RequiredPageArguments("node", PAGEARG_NODE);

# Need these below
$node_id = $node->node_id();

#
# Standard Testbed Header
#
PAGEHEADER("Node $node_id");

#
# Admin users can look at any node, but normal users can only control
# nodes in their own experiments.
#
if (! $isadmin &&
    ! $node->AccessCheck($this_user, $TB_NODEACCESS_MODIFYINFO)) {

    $power_id = "";
    $query_result = DBQueryFatal("select power_id from outlets ".
				 "where node_id='$node_id'");
    if (mysql_num_rows($query_result) > 0) {
	$row = mysql_fetch_array($query_result);
	$power_id = $row["power_id"];
    }
    if (STUDLY() && ($power_id == "mail")) {
	    SUBPAGESTART();
	    SUBMENUSTART("Node Options");
	    WRITESUBMENUBUTTON("Update Power State",
			       "powertime.php?node_id=$node_id");
	    SUBMENUEND();
	    $node->Show(SHOWNODE_NOPERM);
	    SUBPAGEEND();
    }
    else {
	    $node->Show(SHOWNODE_NOPERM);
    }
    PAGEFOOTER();
    return;
}

# If reserved, more menu options.
if (($experiment = $node->Reservation())) {
    $pid   = $experiment->pid();
    $eid   = $experiment->eid();
    $vname = $node->VirtName();
}

SUBPAGESTART();
SUBMENUSTART("Node Options");

#
# Tip to node option
#
if ($node->HasSerialConsole()) {
    WRITESUBMENUBUTTON("Show Console Log",
		       "showconlog.php?node_id=$node_id&linecount=500");
}

#
# Edit option
#
WRITESUBMENUBUTTON("Edit Node Info",
		   "nodecontrol_form.php?node_id=$node_id");

if ($isadmin ||
    $node->AccessCheck($this_user, $TB_NODEACCESS_REBOOT)) {
    if ($experiment) {
	WRITESUBMENUBUTTON("Update Node",
			   "updateaccounts.php?pid=$pid&eid=$eid".
			   "&nodeid=$node_id");
    }
    WRITESUBMENUBUTTON("Reboot Node",
		       "boot.php?node_id=$node_id");

    WRITESUBMENUBUTTON("Show Boot Log",
		       "bootlog.php?node_id=$node_id");
}

if ($node->AccessCheck($this_user, $TB_NODEACCESS_LOADIMAGE)) {
    WRITESUBMENUBUTTON("Create a Disk Image",
		       "newimageid_ez.php?formfields[node_id]=$node_id&formfields[simple]=1");
}

if ($isadmin || OPSGUY()) {
    WRITESUBMENUBUTTON("Show Node Log",
		       "shownodelog.php?node_id=$node_id");
    WRITESUBMENUBUTTON("Show Node History",
		       "shownodehistory.php?node_id=$node_id");
}
if ($experiment && ($isadmin || (OPSGUY()) && $pid == $TBOPSPID)) {
    WRITESUBMENUBUTTON("Free Node",
		       "freenode.php?node_id=$node_id");
}

if ($isadmin || STUDLY() || OPSGUY()) {
    WRITESUBMENUBUTTON("Set Node Location",
		       "setnodeloc.php?node_id=$node_id");
    WRITESUBMENUBUTTON("Update Power State",
		       "powertime.php?node_id=$node_id");
}

if ($isadmin || STUDLY() || OPSGUY()) {
    WRITESUBMENUBUTTON("Modify Node Attributes",
                       "modnodeattributes_form.php?node_id=$node_id");
}

if ($isadmin) {
    if (!$node->reserved_pid()) {
	WRITESUBMENUBUTTON("Pre-Reserve Node",
			   "prereserve_node.php?node_id=$node_id");
    }
    else {
	WRITESUBMENUBUTTON("Clear Pre-Reserve",
			   "prereserve_node.php?node_id=$node_id&clear=1");
    }
}
SUBMENUEND();

#
# Dump record.
# 
$node->Show(SHOWNODE_NOFLAGS);

SUBPAGEEND();

#
# Standard Testbed Footer
# 
PAGEFOOTER();
?>




