<?php
#
# Copyright (c) 2000-2004 University of Utah and the Flux Group.
# All rights reserved.
# This file is part of the Netbed/Emulab network testbed software.
# In brief, you can't redistribute it or use it for commercial purposes,
# and you must give appropriate credit and return improvements to Utah.
# See the file LICENSE at the root of the source tree for details.
# 
#
include("defs.php");
include("showstuff.php");

#
# Standard Testbed Header
#

#
# Only known and logged in users can end experiments.
#
$uid = GETLOGIN();

#
# Verify page arguments.
#
$optargs = OptionalPageArguments("showby",     PAGEARG_STRING,
                                 "sortby",     PAGEARG_STRING,
                                 "range",  PAGEARG_STRING);


# Page args,
if (! isset($showby)) {
    $showby = "projects";
}
if (! isset($sortby)) {
    $sortby = "pdays";
}
if (! isset($range)) {
    $range = "month";
}

switch ($range) {
	case "day":
		$header_term = "Daily";
		break;
	case "week":
		$header_term = "Weekly";
		break;
	case "month":
		$header_term = "Monthly";
		break;
	case "quarter":
		$header_term = "Quarterly (previous 91 days)";
		break;
	default:
		$header_term = "Custom";
		break;
}


PAGEHEADER("Deter Testbed $header_term Stats");

#
# This version prints out the simple summary info for the entire table.
# No ranges, just ordered. 
#
function showsummary ($showby, $sortby) {
    switch ($showby) {
        case "projects":
	    $which = "pid";
	    $table = "project_stats";
	    $title = "Project Summary Stats (Epoch)";
	    $link  = "showproject.php?pid=";
	    break;
        case "users":
	    $which = "uid";
	    $table = "user_stats";
	    $title = "User Summary Stats (Epoch)";
	    $link  = "showuser.php?target_uid=";
	    break;
        default:
	    USERERROR("Invalid showby argument: $showby!", 1);
    }
    $wclause = "";
    switch ($sortby) {
        case "pid":
	    $order   = "pid";
	    $wclause = "where pid!='$TBOPSPID'";
	    break;
        case "uid":
	    $order = "uid";
	    break;
        case "pnodes":
	    $order = "allexpt_pnodes desc";
	    break;
        case "pdays":
	    $order = "pnode_days desc";
	    break;
        case "edays":
	    $order = "expt_days desc";
	    break;
        case "swapins":
	    $order = "expt_swapins desc";
	    break;
        case "new":
	    $order = "expt_new desc";
	    break;
        default:
	    USERERROR("Invalid sortby argument: $sortby!", 1);
    }

    $query_result =
	DBQueryFatal("select $which, allexpt_pnodes, ".
		     "allexpt_pnode_duration / (24 * 3600) as pnode_days, ".
		     "allexpt_duration / (24 * 3600) as expt_days, ".
		     "exptswapin_count+exptstart_count as expt_swapins, ".
		     "exptpreload_count+exptstart_count as expt_new ".
		     "from $table  ".
		     "$wclause ".
		     "order by $order");

    if (mysql_num_rows($query_result) == 0) {
	USERERROR("No summary stats of interest!", 1);
    }

    #
    # Gather some totals first.
    #
    $pnode_total  = 0;
    $pdays_total  = 0;
    $edays_total  = 0;
    $swapin_total = 0;
    $new_total    = 0;
    while ($row = mysql_fetch_assoc($query_result)) {
	$pnodes  = $row["allexpt_pnodes"];
	$pdays   = $row["pnode_days"];
	$edays   = $row["expt_days"];
	$swapins = $row["expt_swapins"];
	$new     = $row["expt_new"];
	
	$pnode_total  += $pnodes;
	$pdays_total  += $pdays;
	$edays_total  += $edays;
	$swapin_total += $swapins;
	$new_total    += $new;
    }

    SUBPAGESTART();
    echo "<table>
           <tr><td colspan=2 nowrap align=center>
               <b>Totals</b></td>
           </tr>
           <tr><td nowrap align=right><b>Pnodes</b></td>
               <td align=left>$pnode_total</td>
           </tr>
           <tr><td nowrap align=right><b>Pnode Days</b></td>
               <td align=left>$pdays_total</td>
           </tr>
           <tr><td nowrap align=right><b>Expt Days</b></td>
               <td align=left>$edays_total</td>
           </tr>
           <tr><td nowrap align=right><b>Swapins</b></td>
               <td align=left>$swapin_total</td>
           </tr>
           <tr><td nowrap align=right><b>New</b></td>
               <td align=left>$new_total</td>
           </tr>

          </table>\n";
    SUBMENUEND_2B();
    
    echo "<center><b>$title</b></center><br>\n";
    echo "<table align=center border=1>
          <tr>
             <th><a class='static'
                    href='updatestats.php?showby=$showby&sortby=$which'>
                    $which</th>
             <th><a class='static'
                    href='updatestats.php?showby=$showby&sortby=pnodes'>
                    Pnodes</th>
             <th><a class='static'
                    href='updatestats.php?showby=$showby&sortby=pdays'>
                    Pnode Days</th>
             <th><a class='static'
                    href='updatestats.php?showby=$showby&sortby=edays'>
                    Expt Days</th>
             <th><a class='static'
                    href='updatestats.php?showby=$showby&sortby=swapins'>
                    Swapins</th>
             <th><a class='static'
                    href='updatestats.php?showby=$showby&sortby=new'>
                    New</th>
          </tr>\n";

    mysql_data_seek($query_result, 0);    
    while ($row = mysql_fetch_assoc($query_result)) {
	$heading = $row[$which];
	$pnodes  = $row["allexpt_pnodes"];
	$phours  = $row["pnode_days"];
	$ehours  = $row["expt_days"];
	$swapins = $row["expt_swapins"];
	$new     = $row["expt_new"];

	echo "<tr>
                <td><A href='$link${heading}'>$heading</A></td>
                <td>$pnodes</td>
                <td>$phours</td>
                <td>$ehours</td>
                <td>$swapins</td>
                <td>$new</td>
              </tr>\n";
    }
    echo "</table>\n";
    SUBPAGEEND();
}

#
# COmparison functions for sort.
#
function intcmp ($a, $b) {
    if ($a == $b) return 0;
    return ($a > $b) ? -1 : 1;
}
function pnodecmp ($a, $b) {
    return intcmp($a["pnodes"], $b["pnodes"]);
}
function pdaycmp ($a, $b) {
    return intcmp($a["pseconds"], $b["pseconds"]);
}
function edaycmp ($a, $b) {
    return intcmp($a["eseconds"], $b["eseconds"]);
}
function swapincmp ($a, $b) {
    return intcmp($a["swapins"], $b["swapins"]);
}
function newexptcmp ($a, $b) {
    return intcmp($a["new"], $b["new"]);
}

function showrange ($showby, $sortby, $range) {
    global $TBOPSPID, $TB_EXPTSTATE_ACTIVE, $debug;
    $now   = time();
    unset($rangematches);
    
    switch ($range) {
        case "day":
	    $span = 3600 * 24 * 1;
	    break;
        case "week":
	    $span = 3600 * 24 * 7;
	    break;
        case "month":
	    $span = 3600 * 24 * 31;
	    break;
        case "quarter":
            $span = 3600 * 24 * 91;
	    break;
        default:
	    if (!preg_match("/^(\d*)\/(\d*)\/(\d*)-(\d*)\/(\d*)\/(\d*)$/",
			    $range, $rangematches)) 
		USERERROR("Invalid range argument: $range!", 1);
    }
    if (isset($rangematches)) {
	$spanstart = mktime(1,1,1,
			    $rangematches[1],$rangematches[2],
			    $rangematches[3]);
	$spanend = mktime(23,59,59,
			    $rangematches[4],$rangematches[5],
			    $rangematches[6]);

	if ($spanend <= $spanstart)
	    USERERROR("Invalid range: $spanstart to $spanend", 1);
    }
    else {
	$spanend   = $now;
	$spanstart = $now - $span;
    }
    if ($debug)
	echo "start $spanstart end $spanend<br>\n";

    # Summary info, indexed by pid and uid. Each entry is an array of the
    # summary info.
    $pid_summary  = array();
    $uid_summary  = array();

    if (!isset($rangematches)) {
        #
        # First get current swapped in experiments. Instead of using reserved
        # table, use the experiment_stats record so we can more easily separate
        # pnodes from vnodes (although ignoring vnodes at the moment).
	# We do this because there are no "swapout" events for these. We could
	# also do this as a post pass below, and I might do that at some point.
        #
	$query_result =
	    DBQueryFatal("select e.pid,e.eid,e.expt_swap_uid as swapper, ".
			 " UNIX_TIMESTAMP(now())-UNIX_TIMESTAMP(e.expt_swapped)".
			 "   as swapseconds, r.pnodes,r.vnodes ".
			 " from experiments as e ".
			 "left join experiment_stats as s on s.exptidx=e.idx ".
			 "left join experiment_resources as r on ".
			 "     s.rsrcidx=r.idx ".
			 "where e.state='" . $TB_EXPTSTATE_ACTIVE . "'" .
			 "  and e.pid!='$TBOPSPID' and ".
			 "      not (e.pid='ron' and e.eid='all') ");

	while ($row = mysql_fetch_assoc($query_result)) {
	    $pid         = $row["pid"];
	    $eid         = $row["eid"];
	    $uid         = $row["swapper"];
	    $swapseconds = $row["swapseconds"];
	    $pnodes      = $row["pnodes"];
	    $vnodes      = $row["vnodes"];

	    if ($pnodes == 0)
		continue;

	    if ($debug)
		echo "$pid $eid $uid $swapseconds $pnodes $vnodes<br>\n";

	    if ($swapseconds > $span) {
		$swapseconds = $span;
		if ($debug)
		    echo "Span to $swapseconds<br>\n";
	    }

	    if (!isset($pid_summary[$pid])) {
		$pid_summary[$pid] = array('pnodes'   => 0,
					   'pseconds' => 0,
					   'eseconds' => 0,
					   'current'  => 1,
					   'new'      => 0,
					   'swapins'  => 0);
	    }
	    if (!isset($uid_summary[$uid])) {
		$uid_summary[$uid] = array('pnodes'   => 0,
					   'pseconds' => 0,
					   'eseconds' => 0,
					   'current'  => 1,
					   'new'      => 0,
					   'swapins'  => 0);
	    }
	    $pid_summary[$pid]["pnodes"]   += $pnodes;
	    $pid_summary[$pid]["pseconds"] += $pnodes * $swapseconds;
	    $pid_summary[$pid]["eseconds"] += $swapseconds;
	    $uid_summary[$uid]["pnodes"]   += $pnodes;
	    $uid_summary[$uid]["pseconds"] += $pnodes * $swapseconds;
	    $uid_summary[$uid]["eseconds"] += $swapseconds;
	}
    }

    $query_result =
	DBQueryFatal("select s.pid,s.eid,t.uid,t.action,t.exptidx,t.exitcode,".
		     "  r1.pnodes as pnodes1,r2.pnodes as pnodes2, ".
		     "  UNIX_TIMESTAMP(t.end_time) as ttstamp ".
		     " from testbed_stats as t ".
		     "left join experiment_stats as s on ".
		     "  s.exptidx=t.exptidx ".
		     "left join experiment_resources as r1 on ".
		     "  r1.idx=t.rsrcidx ".
		     "left join experiment_resources as r2 on ".
		     "  r2.idx=r1.lastidx and r1.lastidx is not null ".
		     "where t.exitcode=0 && ".
		     "    (UNIX_TIMESTAMP(t.end_time) <= $spanend && ".
		     "     UNIX_TIMESTAMP(t.end_time) >= $spanstart) ".
		     "order by t.end_time");

    # Experiment start time, indexed by pid:eid.
    $expt_start = array();

    while ($row = mysql_fetch_assoc($query_result)) {
	$pid     = $row["pid"];
	$eid     = $row["eid"];
	$uid     = $row["uid"];
	$idx	 = $row["exptidx"];
	$tstamp  = $row["ttstamp"];
	$action  = $row["action"];
	$pnodes  = $row["pnodes1"];
	$pnodes2 = $row["pnodes2"];
	$ecode   = $row["exitcode"];

	if ($pnodes == 0)
	    continue;

	#
	# If a swapmod, and there is no record, one of two things. Either
	# it was swapped in before the interval, or the experiment was
	# was swapped out, and the user did a swapmod on it. We need to
	# know that, since swapmod of a swapped out experiment does not
	# count! 
	# 
	if ($action == "swapmod" &&
	    ! isset($expt_start["$pid:$eid"])) {
	    $swapper_result =
		DBQueryFatal("select action,exitcode from testbed_stats ".
			     "where exptidx=$idx and ".
			     "      UNIX_TIMESTAMP(end_time)<$tstamp ".
			     "order by end_time desc");

	    while ($srow = mysql_fetch_assoc($swapper_result)) {
		$saction = $srow["action"];
		$secode  = $srow["exitcode"];

		if ($saction != "swapmod")
		    break;
	    }
	    if (!$srow ||
		($saction == "swapout" || $saction == "preload" ||
		 ($saction == "swapin" && $secode)))
		continue;
	    
	    if ($debug)
		echo "M $pid $eid $idx $saction<br>\n";
	}

	if (!isset($pid_summary[$pid])) {
	    $pid_summary[$pid] = array('pnodes'   => 0,
				       'pseconds' => 0,
				       'eseconds' => 0,
				       'current'  => 0,
				       'new'      => 0,
				       'swapins'  => 0);
	}
	if (!isset($uid_summary[$uid])) {
	    $uid_summary[$uid] = array('pnodes'   => 0,
				       'pseconds' => 0,
				       'eseconds' => 0,
				       'current'  => 0,
				       'new'      => 0,
				       'swapins'  => 0);
	}

	if ($debug) 
	    echo "$idx $pid $eid $uid $tstamp $action $pnodes $pnodes2<br>\n";

	switch ($action) {
        case "preload":
	    $pid_summary[$pid]["new"]++;
	    $uid_summary[$uid]["new"]++;
	    break;
        case "start":
	    $pid_summary[$pid]["new"]++;
	    $uid_summary[$uid]["new"]++;
        case "swapin":
	    $expt_start["$pid:$eid"] = array('pnodes' => $pnodes,
					     'uid'    => $uid,
					     'pid'    => $pid,
					     'idx'    => $idx,
					     'stamp'  => $tstamp);
	    $pid_summary[$pid]["swapins"]++;
	    $uid_summary[$uid]["swapins"]++;
	    break;
	case "destroy":
	    #
	    # Yuck, this happens. Treat it like swapout if there is a record.
	    #
        case "swapout":
        case "swapmod":
	    if (isset($expt_start["$pid:$eid"])) {
		# Use the original data, especially pnodes since if this
		# was a swapmod, the nodes are for the new config, not
		# the old config. Besides, we want to credit the original
		# swapper (in), not the current swapper/modder. 
		$uid    = $expt_start["$pid:$eid"]["uid"];
		$pnodes = $expt_start["$pid:$eid"]["pnodes"];
		$diff = $tstamp - $expt_start["$pid:$eid"]["stamp"];
	    }
	    elseif ($action == "destroy") {
		break;
	    }
	    else {
		#
                # The start was before the time span being looked at, so
                # no start/swapin event was returned. Add a record for it.
	        #
		$diff = $tstamp - $spanstart;
		if ($action == "swapmod") {
                    # A pain. We need the number of pnodes for the original
		    # version of the experiment, not the new version.
		    $pnodes = $pnodes2;
		}
		else {
		    $pid_summary[$pid]["swapins"]++;
		    $uid_summary[$uid]["swapins"]++;
		}
	    }
	    if ($debug) 
		echo "S $pid $eid $uid $action $diff $pnodes $pnodes2<br>\n";
	    
	    $pid_summary[$pid]["pnodes"]   += $pnodes;
	    $pid_summary[$pid]["pseconds"] += $pnodes * $diff;
	    $pid_summary[$pid]["eseconds"] += $diff;
	    $uid_summary[$uid]["pnodes"]   += $pnodes;
	    $uid_summary[$uid]["pseconds"] += $pnodes * $diff;
	    $uid_summary[$uid]["eseconds"] += $diff;
	    unset($expt_start["$pid:$eid"]);
	    
	    # Basically, start the clock ticking again with the new
	    # number of pnodes.
	    #
	    # XXX This errorcode is special. See swapexp/tbswap.
	    #
	    if ($action == "swapmod") {
		# Yuck, we redefined uid/pnodes above, but we want to start the
		# new record for the current swapper/#pnodes.
		$expt_start["$pid:$eid"] = array('pnodes' => $row['pnodes1'],
						 'uid'    => $row['uid'],
						 'pid'    => $pid,
						 'idx'    => $idx,
						 'stamp'  => $tstamp);
	    }
	    break;
        default:
	    TBERROR("Invalid action: $action!", 1);
	}
    }

    #
    # Anything still in the expt_start array is still running at the end
    # of the date range. We want to add that extra time in.
    #
    # Note that we caught "current" experiments in the first query above,
    # so we ignore them. I think we can roll that into this at some point.
    #
    if (isset($rangematches)) {
	foreach ($expt_start as $key => $value) {
	    $pnodes  = $value["pnodes"];
	    $pid     = $value["pid"];
	    $uid     = $value["uid"];
	    $stamp   = $value["stamp"];
	    $idx     = $value["idx"];
	    $diff    = $now - $stamp;

	    if ($debug)
		echo "$pnodes, $key, $uid, $stamp, $diff<br>\n";

	    $pid_summary[$pid]["pnodes"]   += $pnodes;
	    $pid_summary[$pid]["pseconds"] += $pnodes * $diff;
	    $pid_summary[$pid]["eseconds"] += $diff;
	    $uid_summary[$uid]["pnodes"]   += $pnodes;
	    $uid_summary[$uid]["pseconds"] += $pnodes * $diff;
	    $uid_summary[$uid]["eseconds"] += $diff;
	}
    }
    
    switch ($showby) {
        case "projects":
	    $which = "pid";
	    $table = $pid_summary;
	    $title = "Project Summary Stats ($range)";
	    $link  = "showproject.php?pid=";
	    break;
        case "users":
	    $which = "uid";
	    $table = $uid_summary;
	    $title = "User Summary Stats ($range)";
	    $link  = "showuser.php?target_uid=";
	    break;
        default:
	    USERERROR("Invalid showby argument: $showby!", 1);
    }
    switch ($sortby) {
        case "pid":
        case "uid":
	    ksort($table);
	    break;
        case "pnodes":
	    uasort($table, "pnodecmp");
	    break;
        case "pdays":
	    uasort($table, "pdaycmp");
	    break;
        case "edays":
	    uasort($table, "edaycmp");
	    break;
        case "swapins":
	    uasort($table, "swapincmp");
	    break;
        case "new":
	    uasort($table, "newexptcmp");
	    break;
        default:
	    USERERROR("Invalid sortby argument: $sortby!", 1);
    }

    #
    # Gather some totals first.
    #
    $pnode_total  = 0;
    $pdays_total  = 0;
    $edays_total  = 0;
    $swapin_total = 0;
    $new_total    = 0;

    foreach ($table as $key => $value) {
	$pnodes  = $value["pnodes"];
	$swapins = $value["swapins"];
	$new     = $value["new"];
	$pdays   = sprintf("%.2f", $value["pseconds"] / (3600 * 24));
	$edays   = sprintf("%.2f", $value["eseconds"] / (3600 * 24));

	if ($debug)
	    echo "$key $value[pseconds] $value[eseconds]<br>\n";
	
	$pnode_total  += $pnodes;
	$pdays_total  += $pdays;
	$edays_total  += $edays;
	$swapin_total += $swapins;
	$new_total    += $new;
    }

    SUBPAGESTART();
    echo "<table>
           <tr><td colspan=2 nowrap align=center>
               <b>Totals</b></td>
           </tr>
           <tr><td nowrap align=right><b>Pnodes</b></td>
               <td align=left>$pnode_total</td>
           </tr>
           <tr><td nowrap align=right><b>Pnode Days</b></td>
               <td align=left>$pdays_total</td>
           </tr>
           <tr><td nowrap align=right><b>Expt Days</b></td>
               <td align=left>$edays_total</td>
           </tr>
           <tr><td nowrap align=right><b>Swapins</b></td>
               <td align=left>$swapin_total</td>
           </tr>
           <tr><td nowrap align=right><b>New</b></td>
               <td align=left>$new_total</td>
           </tr>
          </table>\n";
    SUBMENUEND_2B();
    
    echo "<center>
               <b>$title</b><br>
               (includes current experiments (*))
          </center><br>\n";
    echo "<table align=center border=1>
          <tr>
             <th><a class='static'
                    href='updatestats.php?showby=$showby&sortby=$which&range=$range'>
                    $which</th>
             <th><a class='static'
                    href='updatestats.php?showby=$showby&sortby=pnodes&range=$range'>
                    Pnodes</th>
             <th><a class='static'
                    href='updatestats.php?showby=$showby&sortby=pdays&range=$range'>
                    Pnode Days</th>
             <th><a class='static'
                    href='updatestats.php?showby=$showby&sortby=edays&range=$range'>
                    Expt Days</th>
             <th><a class='static'
                    href='updatestats.php?showby=$showby&sortby=swapins&range=$range'>
                    Swapins</th>
             <th><a class='static'
                    href='updatestats.php?showby=$showby&sortby=new&range=$range'>
                    New</th>
          </tr>\n";

    foreach ($table as $key => $value) {
	$heading = $key;
	$pnodes  = $value["pnodes"];
	$swapins = $value["swapins"];
	$new     = $value["new"];
	$current = $value["current"];
	$pdays   = sprintf("%.2f", $value["pseconds"] / (3600 * 24));
	$edays   = sprintf("%.2f", $value["eseconds"] / (3600 * 24));

	# We caught a swapout, where the swapin was before the interval
	# being looked at.
	if (!$pnodes)
	    continue;

	if ($current)
	    $current = "*";
	else
	    $current = "";

	echo "<tr>
                <td><A href='$link${heading}'>$heading $current</A></td>
                <td>$pnodes</td>
                <td>$pdays</td>
                <td>$edays</td>
                <td>$swapins</td>
                <td>$new</td>
              </tr>\n";
    }
    echo "</table>\n";
    SUBPAGEEND();
}

if ($range == "epoch") {
    showsummary($showby, $sortby);
}
else {
    showrange($showby, $sortby, $range);
}

#
# Standard Testbed Footer
# 
PAGEFOOTER();
?>
