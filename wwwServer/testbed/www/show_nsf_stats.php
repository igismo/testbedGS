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
PAGEHEADER("NSF statistics");

#
# Only known and logged in users can end experiments.
#
$uid = GETLOGIN();
LOGGEDINORDIE($uid);
$isadmin = ISADMIN($uid);

# Show a very simple table that results from a query of fields with numeric
# values.
function TVF_SHOW_TABLE($qstr) {

	$result = DBQueryFatal($qstr);
	print "<table class=\"centered\">\n";
	print "<tr>";
	for ($i = 0; $i< mysql_num_fields($result); $i++) {
	    print "<th>" . mysql_field_name($result, $i) . "</th>";
	}
	print "<tr>\n";
	while ( $row = mysql_fetch_row($result)) {
	    print ("<tr><td class=\"num\">" . 
		    implode("</td><td class=\"num\">", $row) .
		    "</td></tr>\n");
	}
	print "</table>\n";
}

# Print a table from a query where the results are a set of headers, a first
# alphabetic field describing the class of output and a set of numbers.
# Calculate totals, too and put them on the bottom.
function TVF_SHOW_TABLE_TOTAL($qstr) {

	$tot[0] = "Total";
	$result = DBQueryFatal($qstr);
	print "<table class=\"centered\">\n";
	print "<tr>";
	for ($i = 0; $i< mysql_num_fields($result); $i++) {
	    print "<th>" . mysql_field_name($result, $i) . "</th>";
	}
	print "<tr>\n";
	while ( $row = mysql_fetch_row($result)) {
		print "<tr><td>" .
			implode("</td><td class=\"num\">", $row) .
			"</td></tr>\n";
		for ($i=1;$i < mysql_num_fields($result); $i++) {
			$tot[$i] += $row[$i];
		}
	}
	print "<tr><td class=\"tot\">" .
		implode("</td><td class=\"totnum\">", $tot) .
		"</td></tr>\n";
	print "</table>\n";
}

# This is fairly involved.  Generate a set of tables of all the swapin/swapout
# etc.  events and track each experiment's changes.  The code is similar to
# that in updatestats.php, but somewhat more conservative.  I believe it's
# correct, but it does give some smaller numbers than that code.  The input in
# the most general case is an array of UNIX timestamps.  A table is generated
# for each UNIX timestamp counting the statistics forward from that time.  If
# more than one element is in the array, the function returns an array of
# strings keyed on the time values.  If multiple copies of the same time value
# are given in the input array, only one output table for that time value
# appears in the return value.  E.g $rv =
# TVF_GET_LOAD_STATS(array(0,mktime(0,0,0,1,10,2000))) will fill $rv with 2
# values: $rv[0] and $rv[mktime(0,0,0,1,10,2000)].  Each value will be an HTML
# table with the appropriate stats.  If the caller passes either 1 value or a 1
# value array in, a single string containing an HTML table is returned.
#
# "I like overkill." -- Ron Post
#
function TVF_GET_LOAD_STATS($begin = 0) {
	# SQL query to get a lot of information about the actions on
	# experiments.  Specifically this gets the index (a unique id for the
	# experiment) action being taken, the number of nodes in an experiment,
	# NSF statistics category, and when the action was taken (as a UNIX
	# timestamp - time since the epoch).  This is only reported for
	# successful operations and is sorted first by index and then by time
	# so the result is a list of operations for each experiment ordered in
	# time from earliest to latest.
	$load_stats_qstring=
	"select " .
	    "s.eid eid, s.pid pid, t.exptidx idx, t.action action, pnodes, " .
	    "nsf_type as stat_type, UNIX_TIMESTAMP(start_time) start_time " .
	"from " .
	    "experiment_stats s inner join testbed_stats t " .
		"on s.exptidx = t.exptidx " .
	    "inner join experiment_resources r " .
		"on t.rsrcidx = r.idx " .
	"where " .
	    "t.exitcode=0 ".
	"order by s.exptidx, start_time;";

	# if a single scalar is passed in, put it in an array and overwrite
	# $begin with it.  The code below expects $begin to be an array.
	if ( !is_array($begin)) $begin = array($begin);

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

		$new_type = $row['stat_type'];
		$new_idx = $row['idx'];
		$action = $row['action'];
		$new_pnodes = $row['pnodes'];
		$new_start = $row['start_time'];

		# If true, we're done with this experiment.  If it's active and
		# swapped in, include the time in this swapin.
		if ($idx != $new_idx) {
			if ( $active && $is_swapped_in[$idx] ) {
				# Calculate stats for each period passed in.
				foreach ($begin as $b) {
				    $diff = ( $start < $b ) ? 
					($now - $b) : ( $now - $start );
				    if ( $pnodes ) {
					$totals["$b"][$type]['secs'] += $diff;
					$totals["$b"][$type]['load'] +=
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
				    if ( $new_start >= $b ) 
					$totals["$b"][$type]['swapins']++;
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
					if ( $active && $new_start >= $b ) {
					    $diff = ( $start < $b ) ? 
						( $new_start - $b) :
						( $new_start - $start );
					    if ( $pnodes ) {
						$totals["$b"][$type]['secs'] +=
							$diff;
						$totals["$b"][$type]['load'] +=
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
					if ( $active && $new_start >= $b) {
					    $diff = ( $start < $b ) ? 
						($new_start - $b) : 
						( $new_start - $start );
					    if ( $pnodes ) {
					    	$totals["$b"][$type]['secs'] +=
							$diff;
					    	$totals["$b"][$type]['load'] +=
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
		    $diff = ( $start < $b ) ? 
			($now - $b) : ( $now - $start );
		    if ( $pnodes ) {
			$totals["$b"][$type]['secs'] += $diff;
			$totals["$b"][$type]['load'] += $pnodes * $diff;
		    }
		}
	}

	# Now generate the tables for each period and put them in an array to
	# return.
	foreach ($begin as $b ) {
	    $tbl = "";
	    $tot_secs = $tot_swapins = $tot_load = 0;
	    $keys = array_keys($totals["$b"]);
	    sort($keys);

	    $tbl .=  "<table class=\"centered\">\n";
	    if ( $b) {
		$tbl .= "<tr><th class=\"centered\" colspan=\"4\">Usage from " .
			date("j M y", $b) .  " to the present</th></tr>";
	    }
	    else {
		$tbl .= "<tr><th class=\"centered\" colspan=\"4\">Usage " .
			"since the testbed began operation</th></tr>";
	    }
	    $tbl .= "<tr><th>Category</th><th>Swapin time (experiment-days)".
	    	"</th><th>Total Swap-ins</th><th>Load (node-days)</th></tr>\n";
	    foreach ($keys as $k ) {
		$tot_secs += $totals["$b"][$k]['secs']/ (3600 * 24);
		$tot_swapins += $totals["$b"][$k]['swapins'];
		$tot_load += $totals["$b"][$k]['load'] / (3600 * 24);
		$tbl .= "<tr>\n";
		$tbl .= "<td>$k</td>";
		$tbl .= sprintf("<td class=\"num\">%.2f</td>",
			$totals["$b"][$k]['secs']/(3600*24));
		$tbl .= sprintf("<td class=\"num\">%d</td>", 
			$totals["$b"][$k]['swapins']);
		$tbl .= sprintf("<td class=\"num\">%.2f</td>", 
			$totals["$b"][$k]['load']/(3600*24));
		$tbl .= "</tr>\n";
	    }
	    $tbl .= "<tr>\n";
	    $tbl .= "<td class=\"tot\">Total</td>";
	    $tbl .= sprintf("<td class=\"totnum\">%.2f</td>", $tot_secs);
	    $tbl .= sprintf("<td class=\"totnum\">%d</td>", $tot_swapins);
	    $tbl .= sprintf("<td class=\"totnum\">%.2f</td>", $tot_load);
	    $tbl .= "</tr>\n";
	    $tbl .= "</table>\n";
	    # No sense adding this to an array if we're just returning teh
	    # value.
	    if ( sizeof($begin) > 1 ) $rv[$b] = $tbl;
	}

	# All tables build, return 'em.  NB, if only one table was requested,
	# just send a scalar back, otherwise send the array.
	if ( sizeof($begin) > 1 ) return $rv;
	else return $tbl;
}

# Short SQL query to count all users and EMIST users.  Depends on the temporary
# emist_users table being properly initialized.
$total_users_qstring =
"select ".
    "count( case when is_emist = 1 then 1 else NULL end) \"EMIST Users\", ".
    "count(*) \"All Users\" ".
"from ".
    "users u inner join emist_users e ".
	"on u.uid = e.uid ".
"where status = \"active\";";

# This is long but straightforward.  It counts the number of EMIST and
# non-EMIST users that have logged into the web over the given periods.  The
# period counting code is just repeated and EMIST membership is derived from
# the temorary emist_users table.
$logins_history_qstring =
"select " .
    "case  " .
	"when is_emist = 1 then \"EMIST\" " .
	"when is_emist = 0 then \"Non-EMIST\" " .
	"else \"Total\" " .
    "end \"Type\", " .
    "count( " .
	"case " .
	    "when weblogin_last > date_sub(curdate(), interval 1 month) then 1 " .
	    "else NULL " .
	"end " .
    ") \"1 Month\", " .
    "count( " .
	"case " .
	    "when weblogin_last > date_sub(curdate(), interval 3 month) then 1 " .
	    "else NULL " .
	"end " .
    ") \"3 Months\", " .
    "count( " .
	"case " .
	    "when weblogin_last > date_sub(curdate(), interval 6 month) then 1 " .
	    "else NULL " .
	"end " .
    ") \"6 Months\", " .
    "count( " .
	"case " .
	    "when weblogin_last > date_sub(curdate(), interval 9 month) then 1 " .
	    "else NULL " .
	"end " .
    ") \"9 Months\", " .
    "count( " .
	"case " .
	    "when weblogin_last > date_sub(curdate(), interval 1 year) then 1 " .
	    "else NULL " .
	"end " .
    ") \"1 Year\" " .
"from " .
    "user_stats u inner join emist_users e " .
	"on u.uid = e.uid  " .
"group by is_emist desc;";

# Count rpojects in the various categories.  Pretty straightforward, but the
# grouping helps a lot.  A more modern mysql will allow us to do totals in a
# rollup.
$project_count_qstring =
"select org_type Category, " .
    "count(distinct " .
	"case " .
	    "when u.status = \"active\" then m.uid " .
	    "else null " .
	"end) Users, " .
    "count(distinct t.pid) Projects " .
"from " .
    "group_membership m inner join projects t on t.pid = m.pid " .
    "inner join users u on u.uid = m.uid " .
"group by org_type;";

# This array of QSL commands is executed in order to create the emist_users
# table that includes all users and a flag indicating if they're an EMIST user.
# The table's constructed by selecting all users who are members of one EMIST
# project.  The rest of the table is filled out by making a second table of all
# users not in the emuist_users table with a zero is_emist value and
# concatenating them.
$setup = array(
	"create temporary table emist_users " .
		"(uid varchar(20), is_emist tinyint);",
	#
	"insert emist_users " .
	    "select distinct m.uid, 1 " .
	    "from " .
		"group_membership m inner join projects t " .
		    "on m.pid = t.pid " .
	    "where org_type = \"EMIST\";",
	#
	"create temporary table not_emist_users " .
		"(uid varchar(20), is_emist tinyint);",
	#
	"insert not_emist_users " .
	    "select u.uid, 0 " .
	    "from " .
		"users u left outer join emist_users e " .
		    "on u.uid = e.uid " .
	    "where is_emist is null;", 
	#
	"insert emist_users select * from not_emist_users;",
	#
	"drop table not_emist_users;");

# Do the setup - create the temporary emist_users table.
foreach ($setup as $q) { DBQueryFatal($q); }
?>

<h2>Terminology</h2>
<dl>
<dt>User</dt>
<dd>
This is an experimenter, a person, and corresponds to a login account to the
testbed.  One user may be involved with multiple projects, and often a project
will have multiple users &ndash; e.g., a faculty member and several grad
students.
</dd>
<dt>Project</dt>
<dd>
This is an experimental program using DETER.  PIs must submit project proposals
that summarize the purpose and approach of the experiments in this project, as
well as the security risks involved.
</dd>
<dt>Experiment</dt>
<dd>
Each project will involve one or more experiments, i.e., experimental runs on
the testbed with some specific topology, traffic generators, data gathering
machinery, etc.
</dd>
<dt>Swap-in</dt>
<dd>
Instantiating an experiment in the testbed &ndash; allocating a set of nodes
and loading all the software preparatory to running the experiment &ndash; is
called a swap-in.  Emulab allows a swapped-in experiment to be swapped out and
later swapped in again.
</dd>
<h2>Data on Users</h2>
<p>
The table below breaks down all the current active users of DETER into EMIST
and non EMIST users.  An EMIST user is one who is a member of at least one
EMIST project.  Non-EMIST accounts include several testbed operations
and system accounts.
</p>
<?php
TVF_SHOW_TABLE($total_users_qstring);
?>
<p>
The following table shows user activity by displaying the
number of uers who logged in to the DETER web site for several periods during
this calendar year.  If a user logged in at all in the
interval summarized, that is counted once, but users who logged in many
times in that period are also counted once.
</p>
<?php
TVF_SHOW_TABLE_TOTAL($logins_history_qstring);
?>
<p>
This seems like a reasonable measure of the current active user community.
</p>

<p>
We can break down the user count by project within general category &ndash;
EMIST, other academic, industry, government, and operational overhead.
That information is summarized in Table 3.
Note that the total number of users here exceeds the numbers above,
because quite a few users appear in more than one category/project.
</p>
<?php
TVF_SHOW_TABLE_TOTAL($project_count_qstring);
?>
<h2>Data on Swap-ins</h2>
<p>
The following gives a rough idea of the load put on the testbed by each class
of project in terms of how often resources are successfully allocated (number
ofswap-ins) how long experiments are active (days swapped in) and the load on
the testbed in node-days of work.
</p>
<p>
This data is total use for the lifetime of the DETER testbed.
</p>
<?php 
# Get the usage stats for all the tables we want to display now, so we only
# have to walk the huge database query once.
$today = getdate();
$year_key = mktime(0,0,0,1,1,$today["year"]);
$tbl = TVF_GET_LOAD_STATS(array(0, $year_key));
# Now put out the "dawn-of-time" stats.
print $tbl[0]; ?>
<p>
This is year-to-date data.
</p>
<?php
# These are the ytd numbers.
print $tbl[$year_key];
#
# Standard Testbed Footer
# 
PAGEFOOTER();
?>
