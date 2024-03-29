<?php
#
# EMULAB-COPYRIGHT
# Copyright (c) 2000-2008 University of Utah and the Flux Group.
# All rights reserved.
#
include("defs.php");

#
# Only known and logged in users.
#
$this_user = CheckLoginOrDie();
$uid       = $this_user->uid();
$isadmin   = ISADMIN();
$swaplock  = TBGetSiteVar("swap/lockout");

# This will not return if its a sajax request.
include("showlogfile_sup.php");

#
# Check that swaps are not disabled.
#
if ( ! $isadmin && $swaplock  ) {
    USERERROR("Experiment swapping is disabled.  Check DETER news.", 1);
}

#
# Verify Page Arguments.
#
$reqargs = RequiredPageArguments("experiment", PAGEARG_EXPERIMENT,
				 "inout",      PAGEARG_STRING);
$optargs = OptionalPageArguments("canceled",   PAGEARG_STRING,
				 "confirmed",  PAGEARG_STRING,
				 "force",      PAGEARG_BOOLEAN,
				 "forcetype",  PAGEARG_STRING,
				 "idleswap",   PAGEARG_BOOLEAN,
				 "autoswap",   PAGEARG_BOOLEAN);
				 
if (!isset($inout) ||
    (strcmp($inout, "in") && strcmp($inout, "out") &&
     strcmp($inout, "pause") && strcmp($inout, "restart") &&
     strcmp($inout, "instop"))) {
    USERERROR("Improper inout argument!", 1);
}

# Canceled operation redirects back to showexp page. See below.
if (isset($canceled) && $canceled) {
    header("Location: ". CreateURL("showexp", $experiment));
    return;
}

#
# Standard Testbed Header, after cancel above.
#
PAGEHEADER("Swap Control");

#
# Only admins can issue a force swapout
# 
if (isset($force) && $force == 1) {
	if (! $isadmin) {
		USERERROR("Only testbed administrators can forcibly swap ".
			  "an experiment out!", 1);
	}
	if (!isset($idleswap)) { $idleswap=0; }
	if (!isset($autoswap)) { $autoswap=0; }
	if (!isset($forcetype)){ $forcetype="force"; }
	if ($forcetype=="idleswap") { $idleswap=1; }
	if ($forcetype=="autoswap") { $autoswap=1; }
}
else {
    # Must go through the geni interfaces.
    if ($experiment->geniflags()) {
	USERERROR("You must use forceable swap on ProtoGeni experiments", 1);
    }
    
    #
    # If the user is not a member of the group, it
    # must be an admin, and in that case we want him to use the force
    # swapout path to avoid permission issues.
    #
    $group = $experiment->Group();
    if (!isset($group)) {
	TBERROR("Could not get group object for $pid/eid", 1);
    }
    if (!$group->IsMember($this_user, $ignore) && $isadmin) {
	USERERROR("Since you are an administrator trying to swap out ".
		  "an experiment in a project/group you do not belong to, ".
		  "please go back and use the forcible swap options instead.",
		  1);
    }
    $force = 0;
    $idleswap=0;
    $autoswap=0;
}

# Need these below
$pid = $experiment->pid();
$eid = $experiment->eid();
$unix_gid = $experiment->UnixGID();

#
# Verify permissions.
#
if (!$experiment->AccessCheck($this_user, $TB_EXPT_MODIFY)) {
    USERERROR("You do not have permission for $eid!", 1);
}

$isbatch       = $experiment->batchmode();
$state         = $experiment->state();
$exptidx       = $experiment->idx();
$swappable     = $experiment->swappable();
$idleswap_bit  = $experiment->idleswap();
$idleswap_time = $experiment->idleswap_timeout();
$idlethresh    = min($idleswap_time/60.0,TBGetSiteVar("idle/threshold"));
$lockdown      = $experiment->lockdown();

# Convert inout to informative text.
if (!strcmp($inout, "in")) {
    if ($isbatch) 
	$action = "queue";
    else
	$action = "swapin";
}
elseif (!strcmp($inout, "out")) {
    if ($isbatch) 
	$action = "swapout and dequeue";
    else {
	if ($state == $TB_EXPTSTATE_ACTIVATING) {
	    $action = "cancel";
	}
	else {
	    $action = "swapout";
	}
    }
}
elseif (!strcmp($inout, "pause")) {
    if (!$isbatch)
	USERERROR("Only batch experiments can be dequeued!", 1);
    $action = "dequeue";
}
elseif (!strcmp($inout, "restart")) {
    $action = "restart";
}

echo "<font size=+2>Experiment <b>";
echo "<a href=\"showproject.php?pid=$pid\">$pid</a>";
echo "/";
echo "<a href=\"showexp.php?pid=$pid&eid=$eid\">$eid</a>";
echo "</b></font><br>\n";
flush();

# A locked down experiment means just that!
if ($lockdown) {
    echo "<br><br>\n";
    USERERROR("Cannot proceed; the experiment is locked down!", 1);
}

#
# We run this twice. The first time we are checking for a confirmation
# by putting up a form. The next time through the confirmation will be
# set. Or, the user can hit the cancel button, in which case we 
# redirect the browser back to the experiment page (see above).
#
if (!isset($confirmed)) {
    echo "<center><h2><br>
          Are you sure you want to ";
    if ($force) {
	echo "<font color=red><br>forcibly</br></font> ";
    }

    echo "$action experiment";
    echo " '$eid?'
          </h2>\n";
    
    $experiment->Show(1);

    echo "<form action='swapexp.php?inout=$inout&pid=$pid&eid=$eid'
                method=post>";

    if ($force) {
	if (!$swappable) {
	    echo "<h2>Note: This experiment is <em>NOT</em> swappable!</h2>\n";
	}
	echo "Force Type: <select name=forcetype>\n";
	echo "<option value=force ".($forcetype=="force"?"selected":"").
	    ">Forced Swap</option>\n";
	echo "<option value=idleswap ".($forcetype=="idleswap"?"selected":"").
	    ">Idle-Swap</option>\n";
	echo "<option value=autoswap ".($forcetype=="autoswap"?"selected":"").
	    ">Auto-Swap</option>\n";
	echo "</select><br><br>\n";
	echo "<input type=hidden name=force value=$force>\n";
	echo "<input type=hidden name=idleswap value=$idleswap>\n";
	echo "<input type=hidden name=autoswap value=$autoswap>\n";
    }
    
    echo "<b><input type=submit name=confirmed value=Confirm></b>\n";
    echo "<b><input type=submit name=canceled value=Cancel></b>\n";
    echo "</form>\n";

    if ($inout!="out" && $idleswap_bit) {
	if ($idleswap_time / 60.0 != $idlethresh) {
	    echo "<p>Note: The Idle-Swap time for your experiment will be
		 reset to $idlethresh hours.</p>\n";
	}
    }
    
    if (!strcmp($inout, "restart")) {
	echo "<p>
              <a href='$TBDOCBASE/kb-faq.php#restart'>
                 (Information on experiment restart)</a>\n";
    }
    else {
	echo "<p>
              <a href='$TBDOCBASE/kb-faq.php#swapping'>
                 (Information on experiment swapping)</a>\n";
    }
    echo "</center>\n";

    PAGEFOOTER();
    return;
}

STARTBUSY("Starting");

#
# Run the scripts. We use a script wrapper to deal with changing
# to the proper directory and to keep some of these details out
# of this. 
#
# Avoid SIGPROF in child.
# 
set_time_limit(0);

# Args for idleswap It passes them on to swapexp, or if it is just a
# plain force swap, it passes -f for us.
$args = ($idleswap ? "-i" : ($autoswap ? "-a" : ""));

$retval = SUEXEC($uid, "$pid,$unix_gid",
		  ($force ?
		   "webidleswap $args $pid,$eid" :
		   "webswapexp -s $inout $pid $eid"),
		 SUEXEC_ACTION_IGNORE);

HIDEBUSY();

#
# Fatal Error. Report to the user, even though there is not much he can
# do with the error. Also reports to tbops.
# 
if ($retval < 0) {
    SUEXECERROR(SUEXEC_ACTION_DIE);
    #
    # Never returns ...
    #
    die("");
}

#
# Exit status 0 means the experiment is swapping, or will be.
#
echo "<br>\n";
if ($retval) {
    echo "<h3>$action could not proceed</h3>";
    echo "<blockquote><pre>$suexec_output<pre></blockquote>";
}
else {
    if ($isbatch) {
	if (strcmp($inout, "in") == 0) {
	    echo "<p>Batch Mode experiments will be run when enough resources
                  become available.  This might happen immediately, or it
                  may take hours or days.  You will be notified via email
                  when the experiment has been run.  In the meantime, you can
                  check the web page to see how many attempts have been made,
                  and when the last attempt was.</p>
                  <p><b>Only one batch mode experiment per user can be active at 
                  any given time.</b>   There is no limitation on how many 
                  experiments can be queued and they will be run in the order
                  which they were queued.</p>\n";
	}
	elseif (strcmp($inout, "out") == 0) {

	    echo "Batch mode experiments take a few moments to stop. Once
                  it does, the experiment will enter the 'paused' state.
                  You can requeue the batch experiment at that time.\n";

	    echo "<br><br>
                  If you do not receive
                  email notification within a reasonable amount of time, please
                  <a href=\"http://trac.deterlab.net/wiki/GettingHelp\">file a
                  ticket</a>.\n";
	}
	elseif (strcmp($inout, "pause") == 0) {
	    echo "Your experiment has been dequeued.
		  You may requeue your experiment at any time.\n";
	}
	STARTWATCHER($experiment);
    }
    else {
	echo "<div>";
	if (strcmp($inout, "out") == 0 &&
	    strcmp($state, $TB_EXPTSTATE_ACTIVATING) == 0) {

	    echo "Your experiment swapin has been marked for cancelation.
                  It typically takes a few minutes for this to be recognized,
                  assuming you made your request early enough. You will
                  be notified via email when the original swapin request has
                  either aborted or finished. ";
	}
	else {
	    if (strcmp($inout, "in") == 0)
		$howlong = "two to ten";
	    else
		$howlong = "less than two";
    
	    echo "<b>Your experiment has started its $action.</b> 
                 You will be notified via email when the operation is complete.
                 This typically takes $howlong minutes, depending on the
                 number of nodes in the experiment. ";
	}
	echo "If you do not receive
              email notification within a reasonable amount of time, please
              <a href=\"http://trac.deterlab.net/wiki/GettingHelp\">file a
              ticket</a>.\n";
	echo "</div>";
	echo "<br>\n";
	STARTLOG($experiment);
    }
}

#
# Standard Testbed Footer
# 
PAGEFOOTER();
?>
