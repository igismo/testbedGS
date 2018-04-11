<?php
#
# EMULAB-COPYRIGHT
# Copyright (c) 2000-2008 University of Utah and the Flux Group.
# All rights reserved.
#
include("defs.php");

#
# Only known and logged in users can end experiments.
#
$this_user = CheckLoginOrDie();
$uid       = $this_user->uid();
$isadmin   = ISADMIN();

$debug  = 0;
$debug2 = 0;
$debug3 = 0;

# Summary data for admins only.
if (!$isadmin && !STUDLY()) {
    USERERROR("You are not allowed to view this page!", 1);
}

#
# Verify page arguments.
#
$optargs = OptionalPageArguments("showby",     PAGEARG_STRING,
				 "range",      PAGEARG_STRING,
				 "experiment", PAGEARG_EXPERIMENT);

#
# Standard Testbed Header
#
PAGEHEADER("Testbed Summary Stats");

# Page args,
if (! isset($showby)) {
    $showby = "users";
}
if (! isset($range)) {
    $range = "epoch";
}

echo "<b>Show: <a class='static' href='showexpstats.php'>
                  Experiment Stats</a>,
               <a class='static' href='showstats.php'>
                  Testbed Stats</a>.
      </b><br>\n";

echo "<b>Show by: ";
# By Users.
if ($showby != "users") {
    echo "<a class='static' href='showsumstats.php?showby=users'>
             Users</a>, ";
}
else {
    echo "Users, ";
}
# By Projects
if ($showby != "projects") {
    echo "<a class='static' href='showsumstats.php?showby=projects'>
             Projects</a>, ";
}
else {
    echo "Projects. ";
}
echo "</b><br>\n";

echo "<b>Range: ";
if ($range != "epoch") {
    echo "<a class='static'
            href='showsumstats.php?showby=$showby&range=epoch'>
            Epoch</a>, ";
}
else {
    echo "Epoch, ";
}
if ($range != "day") {
    echo "<a class='static'
            href='showsumstats.php?showby=$showby&range=day'>
            Day</a>, ";
}
else {
    echo "Day, ";
}
if ($range != "week") {
    echo "<a class='static'
            href='showsumstats.php?showby=$showby&range=week'>
            Week</a>, ";
}
else {
    echo "Week, ";
}
if ($range != "month") {
    echo "<a class='static'
            href='showsumstats.php?showby=$showby&range=month'>
            Month</a>, ";
}
else {
    echo "Month, ";
}
if ($range != "year") {
    echo "<a class='static'
            href='showsumstats.php?showby=$showby&range=year'>
            Year</a> ";
}
else {
    echo "Year ";
}
echo "</b>";

#
# Spit out a range form!
#
$formrange = "mm/dd/yy-mm/dd/yy";
if (preg_match("/^(\d*)\/(\d*)\/(\d*)-(\d*)\/(\d*)\/(\d*)$/", $range))
    $formrange = $range;

echo "<form action=showsumstats.php method=get>
      <input type=text
             name=range
             value=\"$formrange\">
      <input type=hidden name=showby value=\"$showby\">
      <b><input type=submit name=Get value=Get></b>\n";
echo "<br><br>\n";

#
# Helper function to record stats by research type
#
function addRtypeStats(&$saver, $row, $statnames) {
     if (!isset($row['rtype']) || ($row['rtype'] == "")) {
	 $row['rtype'] = 'Internal';
     }
     $rtypeList = preg_split("/,/", ucwords($row['rtype']));
     foreach ($rtypeList as $key => $value) {
	$value = trim($value);
	if (!isset($saver[$value])) {
	    $rtstats = array();
	    foreach ($statnames as $name => $ignored) {
	      if (isset($row[$name]))
		$s = $rtstats[$name] = $row[$name];
	    }
	    $saver[$value] = $rtstats;
	} else {
	    foreach ($statnames as $name => $ignored) {
	       if (isset($row[$name]))
		$s = $saver[$value][$name] += $row[$name];
	    }
	}
     }
}

#
# This version prints out the simple summary info for the entire table.
# No ranges, just ordered. 
#
function showsummary ($showby) {
    global $TBOPSPID;
    switch ($showby) {
        case "projects":
	    $which = "pid";
	    $table = "project_stats";
	    $title = "Project Summary Stats (Epoch)";
	    $link  = "showproject.php?pid=";
	    $order   = "pid";
	    break;
	case "users":
	    $which = "uid";
	    $table = "user_stats";
	    $title = "User Summary Stats (Epoch)";
	    $link  = "showuser.php?user=";
	    $order   = "uid";
	    break;
        default:
	    USERERROR("Invalid showby argument: $showby!", 1);
    }

    if ($showby == "users") {
	    $q = "select s.uid, allexpt_pnodes, ".
			 "allexpt_pnode_duration / (24 * 3600) as pnode_days,".
			 "allexpt_duration / (24 * 3600) as expt_days, ".
			 "exptswapin_count+exptstart_count as expt_swapins, ".
			 "exptpreload_count+exptstart_count as expt_new, ".
			 "exptswapmod_count as expt_swapmods, ".
			 "u.usr_name, s.uid_idx ".
			 "from user_stats as s ".
			 "left join users as u on u.uid_idx=s.uid_idx ".
			 "order by $order";
    }
    else {
	    $q = "select s.$which, allexpt_pnodes, ".
			 "allexpt_pnode_duration / (24 * 3600) as pnode_days,".
			 "allexpt_duration / (24 * 3600) as expt_days, ".
			 "exptswapin_count+exptstart_count as expt_swapins, ".
			 "exptpreload_count+exptstart_count as expt_new, ".
			 "exptswapmod_count as expt_swapmods, ".
			 "s.${which}_idx ".
			 ", c.org_type stat_type, c.research_type as rtype ".
			 "from $table  as s join projects as c ".
			 "on c.pid=s.pid ".
#			 "where c.org_type<>'Testbed' ".
			 "order by $order";
    }

    # echo "$q\n";
    $query_result = DBQueryFatal($q);
    if (mysql_num_rows($query_result) == 0) {
	USERERROR("No summary stats of interest!", 1);
    }

    #
    # Gather some totals first.
    #
    $pnode_total   = 0;
    $pdays_total   = 0;
    $edays_total   = 0;
    $swapin_total  = 0;
    $swapmod_total = 0;
    $new_total     = 0;

    $statsByRtype = array();
    $statnames = array( "allexpt_pnodes" => "Pnodes",
	"pnode_days" => "Pnode Days", "expt_days" => "Expt Days",
	"expt_swapins" => "Swapins", "expt_new" => "New",
	"expt_swapmods" => "Swapmods", "ppid" => "Projects");

    while ($row = mysql_fetch_assoc($query_result)) {
	$pnodes  = $row["allexpt_pnodes"];
	$pdays   = $row["pnode_days"];
	$edays   = $row["expt_days"];
	$swapins = $row["expt_swapins"];
	$new     = $row["expt_new"];
	$swapmods= $row["expt_swapmods"];
	$row["ppid"] = 1;

	$pnode_total   += $pnodes;
	$pdays_total   += $pdays;
	$edays_total   += $edays;
	$swapin_total  += $swapins;
	$swapmod_total += $swapmods;
	$new_total     += $new;

	if ($showby != "users") {addRtypeStats($statsByRtype, $row, $statnames);}
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
           <tr><td nowrap align=right><b>Swapmods</b></td>
               <td align=left>$swapmod_total</td>
           </tr>
           <tr><td nowrap align=right><b>New</b></td>
               <td align=left>$new_total</td>
           </tr>

          </table>\n";
    SUBMENUEND_2B();
    
    echo "<center><b>$title</b></center><br>\n";
    echo "<table align=center border=1 id=sumstats>
	  <thead class='sort'>
          <tr>
             <th>$which</th>";
    if ($which == "pid")
    echo "   <th>Project type</th>";
    echo "   <th>Pnodes</th>
             <th>Pnode Days</th>
             <th>Expt Days</th>
             <th>Swapins</th>
             <th>Swapmods</th>
             <th>New</th>
          </tr></thead>\n";

    mysql_data_seek($query_result, 0);    
    while ($row = mysql_fetch_assoc($query_result)) {
	$heading = $row[$which];
	$idx     = $row["${which}_idx"];
	$pnodes  = $row["allexpt_pnodes"];
	$phours  = $row["pnode_days"];
	$ehours  = $row["expt_days"];
	$swapins = $row["expt_swapins"];
	$swapmods= $row["expt_swapmods"];
	$new     = $row["expt_new"];
        if ($which == "pid")
           $rtype = $row["rtype"];

	echo "<tr>";
	if ($showby == "users") {
	    # A current or a deleted user?
	    $usr_name = $row["usr_name"];
	    
	    if (isset($usr_name)) {
		echo "<td><A href='$link{$heading}'>$heading</A></td>";
	    }
	    else {
		echo "<td>$heading</td>";
	    }
	}
	else {
	    echo "<td><A href='$link{$heading}'>$heading</A></td>";
	}
        if ($which == "pid")
	echo "  <td>$rtype</td>";
	echo "  <td>$pnodes</td>
      	        <td>$phours</td>
                <td>$ehours</td>
                <td>$swapins</td>
                <td>$swapmods</td>
                <td>$new</td>
              </tr>\n";
    }
    echo "</table>\n";
  if ($showby != "users") {
    echo "<br><br><center><b>Summary by Project Type</b></center><br>\n";
    echo "<table align=center border=1 id=sumstats2>
	  <thead class='sort'>
          <tr>
             <th>Project Type</th>
	     <th>Projects</th>
             <th>Pnodes</th>
             <th>Pnode Days</th>
             <th>Expt Days</th>
             <th>Swapins</th>
             <th>Swapmods</th>
             <th>New</th>
          </tr></thead>\n";

    foreach ($statsByRtype  as $heading => $row) {
	$pnodes  = $row["allexpt_pnodes"];
	$phours  = $row["pnode_days"];
	$ehours  = $row["expt_days"];
	$swapins = $row["expt_swapins"];
	$swapmods= $row["expt_swapmods"];
	$new     = $row["expt_new"];
	$projs	 = $row["ppid"];	 

	echo "  <td>$heading</td>
	     	<td>$projs</td>
                <td>$pnodes</td>
                <td>$phours</td>
                <td>$ehours</td>
                <td>$swapins</td>
                <td>$swapmods</td>
                <td>$new</td>
              </tr>\n";
    }
    echo "</table>\n";
 }
    SUBPAGEEND();
}

function showrange ($showby, $range) {
    global $TBOPSPID, $TB_EXPTSTATE_ACTIVE, $debug, $debug2, $debug3;
    global $experiment;

    $ACTIVE   = TBDB_USERSTATUS_ACTIVE;
    $ARCHIVED = TBDB_USERSTATUS_ARCHIVED;
    
    $now   = time();
    $inactive_swapmods = 0;
    unset($rangematches);
    $users_created    = 0;
    $projects_created = 0;
    
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
        case "year":
	    $span = 3600 * 24 * 365;
	    break;
        default:
	    if (!preg_match("/^(\d*)\/(\d*)\/(\d*)-(\d*)\/(\d*)\/(\d*)$/",
			    $range, $rangematches)) 
		USERERROR("Invalid range argument: $range!", 1);
    }
    if (isset($rangematches)) {
	$spanstart = mktime(0,0,0,
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

    #
    # Get users/projects created during that time.
    #
    $query_result =
	DBQueryFatal("select count(uid_idx) from users ".
		     "where UNIX_TIMESTAMP(usr_created) >= $spanstart and ".
		     "      UNIX_TIMESTAMP(usr_created) <  $spanend and ".
		     "      (status='$ACTIVE' or status='$ARCHIVED') and ".
		     "      usr_email not like '%flux.utah.edu'");
    if ($query_result && mysql_num_rows($query_result)) {
	$row = mysql_fetch_row($query_result);
	$users_created = $row[0];
    }
    $query_result =
	DBQueryFatal("select count(pid_idx) from projects ".
		     "where UNIX_TIMESTAMP(created) >= $spanstart and ".
		     "      UNIX_TIMESTAMP(created) <  $spanend and ".
		     "      status='approved'");
    if ($query_result && mysql_num_rows($query_result)) {
	$row = mysql_fetch_row($query_result);
	$projects_created = $row[0];
    }


    # Summary info, indexed by pid and uid. Each entry is an array of the
    # summary info.
    $pid_summary  = array();
    $uid_summary  = array();
    $experiments  = array();


    #
    # Get current set of experiments so we can mark them as current.
    #
    $query_result =
	DBQueryFatal("select e.pid,e.eid,u.uid,r.pnodes,r.vnodes, ".
		     "    c.research_type as rtype, ".
		     "    r.swapin_time,r.idx ".
		     "  from experiments as e ".
		     "left join experiment_stats as s on s.exptidx=e.idx ".
		     "left join experiment_resources as r on ".
		     "     s.rsrcidx=r.idx ".
		     "left join users as u on u.uid_idx=r.uid_idx ".
		     "join projects as c on e.pid=c.pid ".
		     "where e.state='" . $TB_EXPTSTATE_ACTIVE . "' and r.swapin_time > 0" );

    while ($row = mysql_fetch_assoc($query_result)) {
	$pid         = $row["pid"];
	$eid         = $row["eid"];
	$uid         = $row["uid"];
	$swapin_time = $row["swapin_time"];
	$swapseconds = 0;
	$pnodes      = $row["pnodes"];
	$vnodes      = $row["vnodes"];
	$rsrcidx     = $row["idx"];
	$rtype       = $row["rtype"];

	if ($swapin_time > $spanend) {
	    continue;
	}
	if ($swapin_time < $spanstart) 
	    $swapseconds = $spanend - $spanstart;
	else
	    $swapseconds = $spanend - $swapin_time;

	if ($debug2)
	    echo "$pid $eid $uid $swapin_time $swapseconds $pnodes<br>\n";

	# uid may not be set cause of a problem with how resource records
	# converted over. Not worth worrying about.
	if (!isset($uid) || !$uid)
	    continue;

	if (!isset($pid_summary[$pid])) {
	    $pid_summary[$pid] = array('ppid'	  => 1,
				       'pnodes'   => 0,
				       'vnodes'   => 0,
				       'pseconds' => 0,
				       'eseconds' => 0,
				       'current'  => 1,
				       'rtype' => $rtype,
				       'preloaded'=> 0,
				       'started'  => 0,
				       'swapmods' => 0,
				       'swapins'  => 0);
	}
	if (!isset($uid_summary[$uid])) {
	    $uid_summary[$uid] = array('pnodes'   => 0,
				       'vnodes'   => 0,
				       'pseconds' => 0,
				       'eseconds' => 0,
				       'current'  => 1,
				       'preloaded'=> 0,
				       'started'  => 0,
				       'swapmods' => 0,
				       'swapins'  => 0);
	}
	$experiments[$rsrcidx]          = $rsrcidx;
	$pid_summary[$pid]["vnodes"]   += $vnodes;
	$pid_summary[$pid]["pnodes"]   += $pnodes;
	$pid_summary[$pid]["pseconds"] += $pnodes * $swapseconds;
	$pid_summary[$pid]["eseconds"] += $swapseconds;
	$uid_summary[$uid]["vnodes"]   += $vnodes;
	$uid_summary[$uid]["pnodes"]   += $pnodes;
	$uid_summary[$uid]["pseconds"] += $pnodes * $swapseconds;
	$uid_summary[$uid]["eseconds"] += $swapseconds;
    }

    $query_result =
	DBQueryFatal("select s.exptidx,s.pid,u.uid,r.pnodes,r.vnodes, ".
		     "   swapin_time,swapout_time,swapmod_time,byswapmod, ".
		     "   c.research_type as rtype, ".
		     "   e.eid_uuid,r.idx,r.lastidx,byswapin ".
		     " from experiment_resources as r ".
		     "left join experiment_stats as s on ".
		     "     r.exptidx=s.exptidx ".
		     "left join experiments as e on e.idx=s.exptidx ".
		     "join projects as c on s.pid=c.pid ".
		     "left join users as u on u.uid_idx=r.uid_idx ".
		     "where (UNIX_TIMESTAMP(r.tstamp) >= $spanstart) and ".
		     "      (UNIX_TIMESTAMP(r.tstamp) <= $spanend) ".
		     (isset($experiment) ?
		      "and r.exptidx=" . $experiment->idx() . " " : " ") .
		     "order by s.exptidx,UNIX_TIMESTAMP(r.tstamp)");

    while ($row = mysql_fetch_assoc($query_result)) {
	$exptidx      = $row["exptidx"];
	$pid          = $row["pid"];
	$uid          = $row["uid"];
	$pnodes       = $row["pnodes"];
	$vnodes       = $row["vnodes"];
	$swapin_time  = $row["swapin_time"];
	$swapout_time = $row["swapout_time"];
	$swapmod_time = $row["swapmod_time"];
	$byswapmod    = $row["byswapmod"];
	$byswapin     = $row["byswapin"];
	$uuid	      = $row["eid_uuid"];
	$rsrcidx      = $row["idx"];
	$lastidx      = $row["lastidx"];
	$rtype        = $row["rtype"];
	$swapseconds  = 0;

	if ($debug2) 
	    echo "$exptidx $rsrcidx $lastidx $pid $uid $pnodes $vnodes ".
		"$swapin_time $swapout_time $swapmod_time $byswapmod<br>\n";

	# uid may not be set cause of a problem with how resource records
	# converted over. Not worth worrying about.
	if (!isset($uid) || !$uid)
	    continue;

	if (!isset($pid_summary[$pid])) {
	    $pid_summary[$pid] = array('ppid'	  => 1,
				       'pnodes'   => 0,
				       'vnodes'   => 0,
				       'pseconds' => 0,
				       'eseconds' => 0,
				       'current'  => 0,
				       'rtype'  => $rtype,
				       'preloaded'=> 0,
				       'started'  => 0,
				       'swapmods' => 0,
				       'swapins'  => 0);
	}
	if (!isset($uid_summary[$uid])) {
	    $uid_summary[$uid] = array('pnodes'   => 0,
				       'vnodes'   => 0,
				       'pseconds' => 0,
				       'eseconds' => 0,
				       'current'  => 0,
				       'preloaded'=> 0,
				       'started'  => 0,
				       'swapmods' => 0,
				       'swapins'  => 0);
	}

	if (!$lastidx) {
	    if ($swapin_time) {
		$pid_summary[$pid]["started"]++;
		$uid_summary[$uid]["started"]++;
	    }
	    else {
		$pid_summary[$pid]["preloaded"]++;
		$uid_summary[$uid]["preloaded"]++;
	    }
	}
	if ($byswapin) {
	    $pid_summary[$pid]["swapins"]++;
	    $uid_summary[$uid]["swapins"]++;
	}
	if ($byswapmod) {
	    $pid_summary[$pid]["swapmods"]++;
	    $uid_summary[$uid]["swapmods"]++;
	    if (!($pnodes || $vnodes))
		$inactive_swapmods++;
	}

	# Current experiment and this resource record is the one we
	# saw in the fist query above?, then skip it.
	if (isset($experiments[$rsrcidx]))
	    continue;

	if (!$pnodes)
	    continue;

	$begin = $end = 0;

	#
	# If no swapin for the record skip it. Not supposed to happen!
	# If there is no swapout or swapmod, it is supposed to be a
	# current experiment.
	#
	if ($swapin_time == 0) {
	    if ($debug)
		echo "No swapin time: $exptidx, $rsrcidx<br>\n";
	    continue;
	}
	if ($swapout_time == 0 && $swapmod_time == 0) {
	    if (!isset($uuid)) {	    
		if ($debug)
		    echo "No swapout/swapmod time: $exptidx, $rsrcidx<br>\n";
		continue;
	    }
	    if ($spanend < $swapin_time)
		continue;

	    $begin = ($swapin_time < $spanstart ? $spanstart : $swapin_time);
	    $end   = $spanend;
	}
	elseif ($swapout_time) {
	    if ($spanend < $swapin_time)
		continue;

	    $begin = ($swapin_time  < $spanstart ? $spanstart : $swapin_time);
	    $end   = ($swapout_time > $spanend   ? $spanend   : $swapout_time);
	}
	else {
	    if (! $swapin_time || $spanend < $swapin_time)
		continue;

	    $begin = ($swapin_time  < $spanstart ? $spanstart : $swapin_time);
	    $end   = ($swapmod_time > $spanend   ? $spanend   : $swapmod_time);
	}
	$swapseconds = $end - $begin;

	if ($debug2) 
	    echo " $swapseconds<br>\n";
	
	if ($swapseconds < 0)
	    continue;

	$pid_summary[$pid]["vnodes"]   += $vnodes;
	$pid_summary[$pid]["pnodes"]   += $pnodes;
	$pid_summary[$pid]["pseconds"] += $pnodes * $swapseconds;
	$pid_summary[$pid]["eseconds"] += $swapseconds;
	$uid_summary[$uid]["vnodes"]   += $vnodes;
	$uid_summary[$uid]["pnodes"]   += $pnodes;
	$uid_summary[$uid]["pseconds"] += $pnodes * $swapseconds;
	$uid_summary[$uid]["eseconds"] += $swapseconds;
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
	    $link  = "showuser.php?user=";
	    break;
        default:
	    USERERROR("Invalid showby argument: $showby!", 1);
    }
    ksort($table);
    
    #
    # Gather some totals first.
    #
    $pnode_total  = 0;
    $vnode_total  = 0;
    $pdays_total  = 0;
    $edays_total  = 0;
    $swapin_total = 0;
    $swapmod_total= 0;
    $preload_total= 0;
    $started_total= 0;

    $statsByRtype = array();
    $statnames = array( "pnodes" => "Pnodes", "vnodes" => "Vnodes",
	"pseconds" => "Pnode Days", "eseconds" => "Expt Days",
	"swapins" => "Swapins", "started" => "Started",
	"preloaded" => "Preloaded", "current" => "Current",
	"swapmods" => "Swapmods", "ppid" => "Projects");


    foreach ($table as $key => $value) {
	$pnodes  = $value["pnodes"];
	$vnodes  = $value["vnodes"];
	$swapins = $value["swapins"];
	$swapmods= $value["swapmods"];
	$preload = $value["preloaded"];
	$starts  = $value["started"];
	$pdays   = sprintf("%.2f", $value["pseconds"] / (3600 * 24));
	$edays   = sprintf("%.2f", $value["eseconds"] / (3600 * 24));

	if ($debug2)
	    echo "$key $value[pseconds] $value[eseconds]<br>\n";
	
	$pnode_total   += $pnodes;
	$vnode_total   += $vnodes;
	$pdays_total   += $pdays;
	$edays_total   += $edays;
	$swapin_total  += $swapins;
	$swapmod_total += $swapmods;
	$preload_total += $preload;
	$started_total += $starts;

	if ($showby != "users") {
	    addRtypeStats($statsByRtype, $value, $statnames);
	}
    }

    SUBPAGESTART();
    echo "<table>
           <tr><td colspan=2 nowrap align=center>
               <b>Totals</b></td>
           </tr>
           <tr><td nowrap align=right><b>New Projects</b></td>
               <td align=left>$projects_created</td>
           </tr>
           <tr><td nowrap align=right><b>New Users</b></td>
               <td align=left>$users_created</td>
           </tr>
           <tr><td nowrap align=right><b>Pnodes</b></td>
               <td align=left>$pnode_total</td>
           </tr>
           <tr><td nowrap align=right><b>Vnodes</b></td>
               <td align=left>$vnode_total</td>
           </tr>
           <tr><td nowrap align=right><b>Pnode Days</b></td>
               <td align=left>$pdays_total</td>
           </tr>
           <tr><td nowrap align=right><b>Expt Days</b></td>
               <td align=left>$edays_total</td>
           </tr>
           <tr><td nowrap align=right><b>Starts</b></td>
               <td align=left>$started_total</td>
           </tr>
           <tr><td nowrap align=right><b>Swapins</b></td>
               <td align=left>$swapin_total</td>
           </tr>
           <tr><td nowrap align=right><b>Total Swapmods</b></td>
               <td align=left>$swapmod_total</td>
           </tr>
           <tr><td nowrap align=right><b>Inactive Swapmods</b></td>
               <td align=left>$inactive_swapmods</td>
           </tr>
           <tr><td nowrap align=right><b>Preloaded</b></td>
               <td align=left>$preload_total</td>
           </tr>
          </table>\n";
    SUBMENUEND_2B();

    showrangetable($table, $title, $which, $link);
    if ($showby != "users") {
	$title2 = "Summary by Project Type";
	showrangetable($statsByRtype, $title2, "Project Type", $link);
    }
    SUBPAGEEND();
}

function showrangetable($table, $title, $which, $link) {
    
    echo "<center>
               <b>$title</b><br>
               (includes current experiments (*))
          </center><br>\n"; 
    if ($which != "Project Type")
       echo "<table align=center border=1 id=sumstats>";
    else
       echo "<table align=center border=1 id=sumstats2>";
    echo " <thead class='sort'>
          <tr>
             <th>$which</th>";
    if ($which == "Project Type")
    echo "   <th>Projects</th>";
    else if ($which == "pid")
    echo "   <th>Project Type</th>";
    echo "   <th>Pnodes</th>
             <th>Pnode Days</th>
             <th>Expt Days</th>
             <th>Starts</th>
             <th>Swapins</th>
             <th>Swapmods</th>
             <th>Preloaded</th>
             <th>Vnodes</th>
          </tr></thead>\n";

    foreach ($table as $key => $value) {
	$heading = $key;
    if ($which == "Project Type")
	$projs	 = $value["ppid"];
    else if ($which == "pid")
        $rtype   = $value["rtype"];
	$pnodes  = $value["pnodes"];
	$vnodes  = $value["vnodes"];
	$swapins = $value["swapins"];
	$swapmods= $value["swapmods"];
	$preload = $value["preloaded"];
	$starts  = $value["started"];
	$current = $value["current"];
	$pdays   = sprintf("%.2f", $value["pseconds"] / (3600 * 24));
	$edays   = sprintf("%.2f", $value["eseconds"] / (3600 * 24));

	if ($current)
	    $current = "*";
	else
	    $current = "";

	if (($which == 'pid') || ($which == 'uid')) {
	    echo "<tr><td><A href='$link${heading}'>$heading $current</A></td>";
	} else {
	    echo "<tr><td>$heading $current</td>";
	}
    if ($which == "Project Type")
        echo   "<td>$projs</td>";
    else if ($which == "pid")
        echo   "<td>$rtype</td>";
	echo   "<td>$pnodes</td>
                <td>$pdays</td>
                <td>$edays</td>
                <td>$starts</td>
                <td>$swapins</td>
                <td>$swapmods</td>
                <td>$preload</td>
                <td>$vnodes</td>
              </tr>\n";
    }
    echo "</table>\n";
}

if ($range == "epoch") {
    showsummary($showby);
}
else {
    showrange($showby, $range);
}

echo "<script type='text/javascript' language='javascript'>
       sorttable.makeSortable(getObjbyName('sumstats'));
       sorttable.makeSortable(getObjbyName('sumstats2'));
      </script>\n";
#
# Standard Testbed Footer
# 
PAGEFOOTER();
?>
