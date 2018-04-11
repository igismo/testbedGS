<?php
#
# EMULAB-COPYRIGHT
# Copyright (c) 2000-2006 University of Utah and the Flux Group.
# All rights reserved.
#
include("defs.php");

#
# Standard Testbed Header
#
PAGEHEADER("Historical statistics");

#
# Only known and logged in users can end experiments.
#
$uid = GETLOGIN();
LOGGEDINORDIE($uid);
$isadmin = ISADMIN($uid);

# Return the length of the period bounded by $st and $ed (as start and end
# respecitively) that falls in $pst and $ped (start and end again).  The
# parameters are all UNIX timestamps - seconds since the epoch.
function clip_period($st, $ed, $pst, $ped) {
	if ( $ed < $pst || $st > $ped ) return 0;
	else if ( $st < $pst && $ed > $ped ) return $ped - $pst;
	else if ( $st > $pst && $ed < $ped ) return $ed -$st;
	else if ( $st < $pst ) return $ed - $pst;
	else return $ped - $st;
}

# return true if $t is between $s and $e.  $s should be less than $e.  This is
# used to see if the time stamp in $t is in the period between $s and $e where
# all are UNIX timestamps as above.
function in_period($t, $s, $e) {
	return ( $t >= $s && $t <= $e);
}

# This is fairly involved.  Generate a set of tables of all the swapin/swapout
# etc.  events and track each experiment's changes.  The code is similar to
# that in updatestats.php, but somewhat more conservative.  I believe it's
# correct, but it does give some smaller numbers than that code.  It's stolen
# from the code I wrote in show_nfs_stats.php.  The input in the most general
# case is an array of pairs of UNIX timestamps.  A table is generated with a
# row for for each UNIX timestamp pair that counts the statistics forward over
# that period. 
function TVF_GET_LOAD_STATS($begin = 0) {
	# SQL query to get a lot of information about the actions on
	# experiments.  Specifically this gets the index (a unique id for the
	# experiment) action being taken, the number of nodes in an experiment,
	# and when the action was taken (as a UNIX timestamp - time since the
	# epoch).  This is only reported for successful operations and is
	# sorted first by index and then by time so the result is a list of
	# operations for each experiment ordered in time from earliest to
	# latest.
	$load_stats_qstring=
	"select " .
	    "s.eid eid, s.pid pid, t.exptidx idx, t.action action, pnodes, " .
	    "UNIX_TIMESTAMP(start_time) start_time " .
	"from " .
	    "experiment_stats s inner join testbed_stats t " .
		"on s.exptidx = t.exptidx " .
	    "inner join experiment_resources r " .
		"on t.rsrcidx = r.idx " .
	"where " .
	    "t.exitcode=0 ".
	"order by s.exptidx, start_time;";

	# SQL query to get the indices of currently swapped in experiments.
	# That list is used to differentiate between currently active
	# experiments and ones that are not active, but do not have a swapout
	# or destroy in the logs.
	$active_experiments_qstring =
	"select idx from experiments where state = \"active\"";

	$now = time();

	# Find the swapped in experiments
	$result = DBQueryFatal($active_experiments_qstring);
	while ( $row = mysql_fetch_array($result) ) {
	    $is_swapped_in[$row[0]]=1;
	}

	$result = DBQueryFatal($load_stats_qstring);
	# Now walk through the log of events for all experiments, gathering the
	# stats for each experiment.  The scalars are mostly state of the
	# current experiment.  Anything prefixed with new is data from this
	# record that is being compared with the old info about this experiment
	# before being committed to that state.  (Committed is a little loose
	# here. :-))
	while ($row = mysql_fetch_assoc($result) ) {
		$tot++;

		$new_type = $row['stat_type'];
		$new_idx = $row['idx'];
		$action = $row['action'];
		$new_pnodes = $row['pnodes'];
		$new_start = $row['start_time'];

		if ($new_pnodes == "" || $new_pnodes == "NULL" ) $null_pnodes++;

		# If true, we're done with this experiment.  If it's active and
		# swapped in, include the time in this swapin.
		if ($idx != $new_idx) {
			if ( $active && $is_swapped_in[$idx] ) {
				# Calculate stats for each period passed in.
				foreach ($begin as $b) {
				    $diff = clip_period($start, $now,
				    	$b[0], $b[1]);
				    if ( $pnodes ) {
					$totals[$b[0]]['secs'] += $diff;
					$totals[$b[0]]['load'] +=
					    $pnodes * $diff;
				    }
			        }
			}
			# The new experiment starts as inactive and we set idx
			# to its index.
			$active = 0;
			$idx = $new_idx;
		}
		$type = $new_type;	# NB, the rollup above depends on
					# adding values to the *old* type.
		switch ($action) {
			case "swapin":
			case "start":
				# The experiment is starting, make it active
				# and count the swapin.  It's possible to reach
				# here with the experiment active, which means
				# we've lost a swapout or destroy (e.g. a
				# swapout w/a non-zero exit code that neverthe
				# less removed the experiment).  Ignore that
				# data, because we don't have any way to know
				# the right time interval for it.
				foreach ($begin as $b ) 
				    if ( in_period($new_start, $b[0], $b[1])) 
					$totals[$b[0]]['swapins']++;
				$pnodes = $new_pnodes;
				$start = $new_start;
				$active = 1;
				break;
			case "swapout":
			case "destroy":
				# The experiment is ending its time in, if it's
				# active.  Count up the time-based statistics
				# and turn it inactive.
				# Calculate stats for each period passed in.
				foreach ($begin as $b ) {
					if ( $active ) {
					    $diff = clip_period($start,
					    	$new_start, $b[0], $b[1]);
					    if ( $pnodes ) {
						$totals[$b[0]]['secs'] +=
							$diff;
						$totals[$b[0]]['load'] +=
							$pnodes * $diff;
					    }
					}
				}
				$active = 0;
				break;
			case "swapmod":
				# This doesn't change active state, but if the
				# experiment was active, total up it's stats in
				# the old configuration and set up the new.
				# Right now we only track pnodes, so there are
				# cases where we could avoid some work.
				# Calculate stats for each period passed in.
				foreach ($begin as $b ) {
					if ( $active ) {
					    $diff = clip_period($start,
					    	$new_start, $b[0], $b[1]);
					    if ( $pnodes ) {
					    	$totals[$b[0]]['secs'] +=
							$diff;
					    	$totals[$b[0]]['load'] +=
							$pnodes * $diff;
						}
					}
				}
				$pnodes = $new_pnodes;
				$start = $new_start;
				break;
		}
	}
	# If the last experiment we considered is active, count up its time.
	if ( $active && $is_swapped_in[$idx] ) {
		# Calculate stats for each period passed in.
		foreach ($begin as $b ) {
		    $diff = clip_period($start, $now, $b[0], $b[1]);
		    if ( $pnodes ) {
			$totals[$b[0]]['secs'] += $diff;
			$totals[$b[0]]['load'] += $pnodes * $diff;
		    }
		}
	}

	# Now generate the table.  There's some table formatting done with
	# explicit style statements, which I loathe, but needs must when the
	# devil drives.  My mods to the style sheets keep disappearing.
        $tbl = "";
        $tbl .=  "<table style=\"margin-left: auto; margin-right: auto;\">\n";
        $tbl .= "<tr><th>When</th>". 
		"<th>Swapin time<br>(experiment-days)".
		"</th><th>Swapins</th><th>Load<br>(node-days)</th></tr>\n";
	foreach ($begin as $b ) {
            $tbl .= "<tr>\n";
	    $tbl .= "<td class=\"tot\">" . date("M y", $b[0]) . "</td>";
	    $tbl .= sprintf("<td style=\"text-align: right;\">%.2f</td>", 
	    	$totals[$b[0]]['secs']/(3600*24));
	    $tbl .= sprintf("<td style=\"text-align: right;\">%d</td>", 
	    	$totals[$b[0]]['swapins']);
	    $tbl .= sprintf("<td style=\"text-align: right;\">%.2f</td>",
	    	$totals[$b[0]]['load']/(3600*24));
	    $tbl .= "</tr>\n";
	    $tot_secs += $totals[$b[0]]['secs'];
	    $tot_swapins += $totals[$b[0]]['swapins'];
	    $tot_load += $totals[$b[0]]['load'];
	}
        $tbl .= "<tr>\n";
        $tbl .= "<td style=\"font-weight: bold;\">Total</td>";
        $tbl .= sprintf("<td style=\"font-weight: bold; " .
	    "text-align: right;\">%.2f</td>", $tot_secs/(3600*24));
        $tbl .= sprintf("<td style=\"font-weight: bold; " .
	    "text-align: right;\">%d</td>", $tot_swapins);
        $tbl .= sprintf("<td style=\"font-weight: bold; " . 
	    "text-align: right;\">%.2f</td>", $tot_load/(3600*24));
        $tbl .= "</tr>\n";
        $tbl .= "</table>\n";

	return $tbl;
}
?>
<!-- Some descriptive text -->
<p>
The following statistics are calculated from the current DETER database,
and run from its inception to the present.
</p>
<p>
The statistics are defined as:
</p>
<dl>
<dt style="font-weight: bold;">When</dt>
<dd>
<p style="text-indent: 0em;" >
The month over which the statistics are computed.
</p>
</dd>
<dt style="font-weight: bold;">Swapin time</dt>
<dd>
<p style="text-indent: 0em;" >
The amount of time experiments have been resident on the testbed in
experiment-days.   Five experiments swapped in for three days each is fifteen
experiment-days of swapin time.
</p>
</dd>
<dt style="font-weight: bold;">Swapins</dt>
<dd>
<p style="text-indent: 0em;" >
Number of successful experiment swapins that month.  This includes new
experiment starts as well as swapping in an experiment, but not modifications
without an intervening swapout.
</p>
</dd>
<dt style="font-weight: bold;">Load</dt>
<dd>
<p style="text-indent: 0em;" >
The amount of resources used by the experiments over the months in node-days.
A 10 node experiment swapped in for exactly a week would use 70 node-days (and
7 experiment-days).
</p>
</dd>
</dl>
<?php 
# Generate an array of month long periods up to the present.
$times = array();
$now = time();
$done = 0;
for ($y = 2004; !$done; $y++ ) {
    for ($m = 1; $m < 13; $m++) {
	$first = mktime(0,0,0,$m,1,$y);
	$last = mktime(0,0,0,$m+1,1,$y)-1;
        $times[] = array($first, $last);
        if ($last > $now ) { $done = 1; break; }
    }
}
# generate and print the table
print TVF_GET_LOAD_STATS($times);
#
# Standard Testbed Footer
# 
PAGEFOOTER();
?>
