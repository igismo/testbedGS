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

#
# Standard Testbed Header
#
PAGEHEADER("Experiment Information Listing");

#
# Only known and logged in users can end experiments.
#
$uid = GETLOGIN();
LOGGEDINORDIE($uid);

$isadmin     = 1;
$clause      = 0;
$having      = "";
$active      = 0;
$idle        = 0;

if (! isset($showtype))
    $showtype="active";
if (! isset($sortby))
    $sortby = "normal";
if (! isset($thumb)) 
    $thumb = 0;
if (! isset($noignore)) 
    $noignore = 0;

echo "Please note that the experiments created by Testbed-Ops are not displayed
here.
      <br>\n";


# Default value for showlastlogin is 1 for admins
$showlastlogin = $isadmin;
# Note: setting this to 1 for non-admins still does not make the column
# show properly... it just shows the header in the table, over the
# wrong column. 

# How many hours is considered idle...
$idlehours = TBGetSiteVar("idle/threshold");

#
# Handle showtype
# 
if (! strcmp($showtype, "all")) {
    $title  = "All";
}
elseif (! strcmp($showtype, "active")) {
    # Active is now defined as "having nodes reserved" - we just depend on
    # the fact that we skip expts with no nodes further down...
    # But for speed, explicitly exclude expts that say they are "swapped"
    # (leave in activating, swapping, etc.)
    $clause = "e.state !='$TB_EXPTSTATE_SWAPPED'";
    $title  = "Active";
    $having = "having (ncount>0)";
    $active = 1;
}
elseif (! strcmp($showtype, "batch")) {
    $clause = "(e.batchmode=1 and e.state!='" .
               $TB_EXPTSTATE_SWAPPED . "')";
    $title  = "Batch";
}
elseif ((!strcmp($showtype, "idle")) && $isadmin ) {
    # Do not put active in the clause for same reason as active
    # However, it takes a long time with so many swapped expts
    $clause = "e.state !='$TB_EXPTSTATE_SWAPPED'";
    $title  = "Idle";
    #$having = "having (lastswap>=1)"; # At least one day since swapin
    $having = "having (lastswap>=0)";
    $idle = 1;
    $showlastlogin = 0; # do not show a lastlogin column
}
else {
    # See active above
    $title  = "Active";
    $having = "having (ncount>0)";
    $active = 1;
}

#
# Handle sortby.
# 
if (! strcmp($sortby, "normal") ||
    ! strcmp($sortby, "pid"))
    $order = "e.pid,e.eid";
elseif (! strcmp($sortby, "eid"))
    $order = "e.eid,e.expt_head_uid";
elseif (! strcmp($sortby, "uid"))
    $order = "e.expt_head_uid,e.pid,e.eid";
elseif (! strcmp($sortby, "name"))
    $order = "e.expt_name";
elseif (! strcmp($sortby, "pcs"))
    $order = "ncount DESC,e.pid,e.eid";
elseif (! strcmp($sortby, "idle"))
    $order = "canbeidle desc,idle_ignore,idlesec DESC,ncount desc,e.pid,e.eid";
else 
    $order = "e.pid,e.eid";

#
# Show a menu of all experiments for all projects that this uid
# is a member of. Or, if an admin type person, show them all!
#
if ($clause) {
    $clause = "and ($clause)";
} else {
    $clause = "";
}

# Notes about the queries below:
# - idle is fudged by 121 seconds so that in the five minutes between 
#   slothd reports we do not get active expts showing up as idle for 0.1 hrs

if ($isadmin) {
    $experiments_result =
	DBQueryFatal("
select e.*, date_format(expt_swapped,'%Y-%m-%d') as d, 
date_format(expt_swapped,'%c/%e') as dshort, 
(to_days(now())-to_days(expt_swapped)) as lastswap, 
count(r.node_id) as ncount, swap_requests, 
round((unix_timestamp(now())-unix_timestamp(last_swap_req))/3600,1) as lastreq,
(unix_timestamp(now()) - unix_timestamp(max(greatest(
last_tty_act,last_net_act,last_cpu_act,last_ext_act)))) as idlesec,
(max(last_report) is not null) as canbeidle, s.rsrcidx
from experiments as e 
left join reserved as r on e.pid=r.pid and e.eid=r.eid
left join experiment_stats as s on e.idx=s.exptidx
left join nodes as n on r.node_id=n.node_id
left join node_activity as na on r.node_id=na.node_id
where (e.pid!='emulab-ops' and n.type!='dnard' or n.type is null) $clause 
group by e.pid,e.eid $having order by $order");
}
else {
    $experiments_result =
	DBQueryFatal("
select distinct e.*, date_format(expt_swapped,'%Y-%m-%d') as d, 
date_format(expt_swapped,'%c/%e') as dshort, count(r.node_id) as ncount, 
(unix_timestamp(now()) - unix_timestamp(max(greatest(
last_tty_act,last_net_act,last_cpu_act,last_ext_act)))) as idlesec,
(max(last_report) is not null) as canbeidle, s.rsrcidx
from group_membership as g 
left join experiments as e on g.pid=e.pid and g.pid=g.gid 
left join experiment_stats as s on e.idx=s.exptidx
left join reserved as r on e.pid=r.pid and e.eid=r.eid 
left join nodes as n on r.node_id=n.node_id 
left join node_activity as na on r.node_id=na.node_id
where (n.type!='dnard' or n.type is null) and 
 g.uid='$uid' and e.pid is not null and e.eid is not null $clause 
group by e.pid,e.eid $having order by $order");    
}
if (! mysql_num_rows($experiments_result)) {
    USERERROR("There are no experiments running in any of the projects ".
              "you are a member of.", 1);
}

if (mysql_num_rows($experiments_result)) {
    echo "<center>
           <h2>$title Experiments</h2>
          </center>\n";

    if ($idle) {
      echo "<p><center><b>Experiments that have been idle at least 
$idlehours hours</b><br>\n";
      if ($noignore) {
        echo "<a class='static' href='showexp_list.php?showtype=idle&sortby=$sortby&thumb=$thumb'>\n
Exclude idle-ignore experiments</a></center></p><br />\n";
      } else {
        echo "<a class='static' href='showexp_list.php?showtype=idle&sortby=$sortby&thumb=$thumb&noignore=1'>\n
Include idle-ignore experiments</a></center></p><br />\n";
      }
    }
    
    $idlemark = "<b>*</b>";
    $stalemark = "<b>?</b>";
    $parens = 0;
    
    #
    # Okay, I decided to do this as one big query instead of a zillion
    # per-exp queries in the loop below. No real reason, except my personal
    # desire not to churn the DB.
    # 
    $total_usage  = array();
    $perexp_usage = array();

    #
    # Geta all the classes of nodes each experiment is using, and create
    # a two-D array with their counts.
    # 
    $usage_query =
	DBQueryFatal("select r.pid,r.eid,nt.class, ".
		     "  count(nt.class) as ncount ".
		     "from reserved as r ".
		     "left join nodes as n on r.node_id=n.node_id ".
		     "left join node_types as nt on n.type=nt.type ".
		     "where n.type!='dnard' ".
		     "group by r.pid,r.eid,nt.class");

    while ($row = mysql_fetch_array($usage_query)) {
	$pid   = $row[0];
	$eid   = $row[1];
	$class = $row[2];
	$count = $row[3];
	
	$perexp_usage["$pid:$eid"][$class] = $count;
    }

    #
    # Now shove out the column headers.
    #
if ($thumb && !$idle) {
    if ($thumb == 2) {
	echo "<table border=2 cols=4 cellpadding=2 cellspacing=2 align=center><tr>";
    } else {
	echo "<table border=2 cols=2 cellpadding=2 cellspacing=2 align=center><tr>";
    }
    $thumbCount = 0;
 
    while ($row = mysql_fetch_array($experiments_result)) {	
	$pid  = $row["pid"];
	$eid  = $row["eid"]; 
	$huid = $row["expt_head_uid"];
	$name = htmlspecialchars($row["expt_name"]);
	$date = $row["dshort"];
        $rsrcidx = $row["rsrcidx"];
	
	if ($idle && ($str=="&nbsp;" || !$pcs)) { continue; }

	if ($thumb == 2) {
	    if ($pid != "emulab-ops") {
		echo "<td align=center>".
		     "<a href='shownsfile.php?pid=$pid&eid=$eid'>".
		     "<img border=1 class='stealth' ".
		     " src='showthumb.php?idx=$rsrcidx'>".
		     "<br>\n".
		     "<b>".
		     "<a href='showproject.php?pid=$pid'>$pid</a>/<br />".
		     "<a href='showexp.php?pid=$pid&eid=$eid'>$eid</a>".
		     "</b>".
		     "</td>";

		$thumbcount++;
		if (($thumbcount % 4) == 0) { echo "</tr><tr>\n"; }
	    }
	} else {

	    echo "<td>".
		 "<table border=0 cellpadding=4 cellspacing=0>".
		 "<tr>".
		 "<td width=160 align=center>".
	         "<a href='shownsfile.php?pid=$pid&eid=$eid'>".
		 "<img border=1 class='stealth' ".
		 " src='showthumb.php?idx=$rsrcidx'>".
	         "</a>".
                 "</td>".
	         "<td align=left class='paddedcell'>".
	         "<b>".
 	         "<a href='showproject.php?pid=$pid'>$pid</a>/".
                 "<a href='showexp.php?pid=$pid&eid=$eid'>$eid</a>".
	         "</b>".
	         "<br />\n".
	         "<b><font size=-1>$name</font></b>".
                 "<br />\n";


	    if ($isadmin) {
		$swappable= $row["swappable"];
		$swapreq=$row["swap_requests"];
		$lastswapreq=$row["lastreq"];
		$lastlogin = "";
		if ($state=="active") {
		    $daysidle=$row["lastswap"];
		    $lastlogin .= "$date swapin";
		}
		# if ($lastlogin=="") { $lastlogin="<td>&nbsp;</td>\n"; }
		if ($lastlogin != "") {
		    echo "<font size=-2><b>Last Login:</b> $lastlogin</font><br />\n";
		}
	    }	

	    echo "<font size=-2><b>Created by:</b> ".
		 "<a href='showuser.php?target_uid=$huid'>$huid</a>".
		 "</font><br />\n";

	    $special = 0;
	    $pcs     = 0;
	    reset($perexp_usage);
	    if (isset($perexp_usage["$pid:$eid"])) {
		while (list ($class, $count) = each($perexp_usage["$pid:$eid"])) {
		    if (strcmp($class, "pc")) {
			$special += $count;
		    } else {
			$pcs += $count;
		    }
		}
	    }

	    if ($pcs) {
		if ($pcs == 1) { $plural = ""; } else { $plural = "s"; }  
		echo "<font size=-1><b>Using <font color=red>$pcs PC</font> Node$plural</b></font><br />\n";
	    }
	    if ($special) {
		if ($special == 1) { $plural = ""; } else { $plural = "s"; }  
		echo "<font size=-1><b>Using <font color=red>$special Special</font> Node$plural</b></font><br />\n";
	    }

	    echo "</td></tr></table> \n";
	    echo "</td>";

	    $thumbcount++;
	    if (($thumbcount % 2) == 0) { echo "</tr><tr>\n"; }
	}
    }

    echo "</tr></table>";
} else {

    $ni = ($noignore ? "&noignore=$noignore" : "");
    
    echo "<table border=2 cols=0
                 cellpadding=0 cellspacing=2 align=center>
            <tr>
              <th width=8%>
               <a class='static' href='showexp_list.php?showtype=$showtype&sortby=pid$ni'>
                  PID</a></th>
              <th width=8%>
               <a class='static' href='showexp_list.php?showtype=$showtype&sortby=eid$ni'>
                  EID</a></th>
              <th align=center width=3%>
               <a class='static' href='showexp_list.php?showtype=$showtype&sortby=pcs$ni'>
                  PCs</a> [1]</th>
              <th align=center width=3%>
               <a class='static' href='showexp_list.php?showtype=$showtype&sortby=idle$ni'>
               Hours Idle</a> [2]</th>\n";
    
    if ($showlastlogin)
        echo "<th width=17% align=center>Last Login</th>\n";
    if ($idle) {
	#echo "<th width=4% align=center colspan=2>Swap Requests</th>\n";
	echo "<th width=4% align=center>Swap Requests</th>\n";
    }

    echo "    <th width=60%>
               <a class='static' href='showexp_list.php?showtype=$showtype&sortby=name$ni'>
                  Description</a></th>
              <th width=4%>
               <a class='static' href='showexp_list.php?showtype=$showtype&sortby=uid$ni'>
                  Head UID</a></th>
            </tr>\n";

    while ($row = mysql_fetch_array($experiments_result)) {
	$pid  = $row["pid"];
	$eid  = $row["eid"]; 
	$huid = $row["expt_head_uid"];
	$name = htmlspecialchars($row["expt_name"]);
	$date = $row["dshort"];
	$state= $row["state"];
	$ignore = $row['idle_ignore'];
	$idlesec= $row["idlesec"];
	$swapreqs = $row["swap_requests"];
	$isidle = ($idlesec >= 3600*$idlehours);
	$stale = TBGetExptIdleStale($pid,$eid);
	$daysidle=0;
	$idletime = ($idlesec > 300 ? round($idlesec/3600,1) : 0);
	# reset pcs
	$pcs=0;
	
	if ($swapreqs && !$isidle) {
	    $swapreqs = "";
	    DBQuery("update experiments set swap_requests='0' ".
			"where pid='$pid' and eid='$eid'");
	}

	if ($isadmin) {
	    $swappable= $row["swappable"];
	    $swapreq=$row["swap_requests"];
	    $lastswapreq=$row["lastreq"];
	    if ($lastswapreq > $idletime) {
		# My last request was from _before_ it was idle this time
		DBQuery("update experiments set swap_requests='0' ".
			    "where pid='$pid' and eid='$eid'");
		$swapreq=0;
	    }
	    $idlemailinterval = TBGetSiteVar("idle/mailinterval");
	    # Is it toosoon to send another mail?
	    $toosoon = ($swapreq>0 && ($lastswapreq < $idlemailinterval));
	    $lastlogin = "<td>";
	    if ($state=="active") {
	        $daysidle=$row["lastswap"];
	        $lastlogin .= "$date swapin";
	    }
	    $lastlogin.="</td>\n";
	    if ($lastlogin=="<td></td>\n") { $lastlogin="<td>&nbsp;</td>\n"; }
	}
	
	if ($idle) {
	    # If it is ignored, skip it now.
	    if ($ignore && !$noignore) { continue; }
	    #$lastlogin .= "<td align=center>$daysidle</td>\n";
	    if (isset($perexp_usage["$pid:$eid"]) &&
		isset($perexp_usage["$pid:$eid"]["pc"])) {
	      $pcs = $perexp_usage["$pid:$eid"]["pc"];
	    }
	    $foo = "<td align=center valign=center>\n";
	    #$label = "";
	    # Probably do not need this when we are using stalemark
	    #if ($stale) { $label .= "stale "; }
	    # Do not really need this if we are marking ignore with ()
	    #if ($ignore) { $label .= "ignore "; }
	    #if (!$swappable) { $label .= "unswap. "; }
 	    #if ($isidle && !$stale && !$ignore && !$toosoon && $pcs) {
	    #    $fooswap = "<td><a ".
	    #       "href=\"request_swapexp.php?pid=$pid&eid=$eid\">".
	    #       "<img border=0 src=\"redball.gif\"></a> $label</td>\n" ;
	    #} else {
	    #   if ($label == "") { $label = "&nbsp;"; }
	    #   $fooswap = "<td>$label</td>";
	    #   if (!$pcs) { $foo .= "(no PCs)\n"; }
	    #   else { $foo .="&nbsp;"; }
	    #}
	    if ($swapreq > 0) {
	      $foo .= "$swapreq&nbsp;sent<br>".
	              "<font size=-2>(${lastswapreq}&nbsp;hrs&nbsp;ago)</font>\n";
	    }
	    #$foo .= "</td>".$fooswap."\n"; 
	    if (!$swappable) { $foo .= "unswap.\n"; }
	    
	}

	if ($idle && (!$pcs || !$isidle)) { continue; }

	$nodes   = 0;
	$special = 0;
	reset($perexp_usage);
	if (isset($perexp_usage["$pid:$eid"])) {
	    while (list ($class, $count) = each($perexp_usage["$pid:$eid"])) {
		$nodes += $count;
		if (strcmp($class, "pc")) {
		    $special = 1;
		} else {
		    $pcs = $count;
		}


		# Summary counts for just the experiments in the projects
		# the user is a member of.
		if (!isset($total_usage[$class]))
		    $total_usage[$class] = 0;
			
		$total_usage[$class] += $count;
	    }
	}

	# in idle or active, skip experiments with no nodes.
	if (($idle || $active) && $nodes == 0) { continue; }
	if ($nodes==0) { $nodes = "&nbsp;"; }
	
	echo "<tr>
                <td><A href='showproject.php?pid=$pid'>$pid</A></td>
                <td><A href='showexp.php?pid=$pid&eid=$eid'>
                       $eid</A></td>\n";
	
	if ($isidle && !$ignore) { $nodes = $nodes.$idlemark; }
	# If multiple classes, then hightlight the number and show pcs/nodes.
	if ($special) {
	    if ($pcs)
		echo "<td nowrap><font color=red>$nodes</font> ($pcs)</td>\n";
	    else
		echo "<td nowrap><font color=red>$nodes</font></td>\n";
	}
	else
            echo "<td>$nodes</td>\n";

	if ($idletime == -1) {
	    $idlestr = "&nbsp;";
	} else {
	    $idlestr = "$idletime";
	    # Order between the next two lines determines if the
	    # stalemark goes inside or outside the parens
	    if ($stale && $pcs > 0) { $idlestr .= $stalemark; }
	    if ($ignore && $idletime !=0) {
		$idlestr = "($idlestr)";
		$parens = 1;
	    }
	}
	echo "<td>$idlestr</td>";
	
	if ($showlastlogin) echo "$lastlogin\n";
	if ($idle) echo "$foo\n";

        echo "<td>$name</td>
                <td><A href='showuser.php?target_uid=$huid'>
                       $huid</A></td>
               </tr>\n";
    }
    echo "</table>\n";

    
    echo "<font size=-1><ol>
             <li><font color=red>Red</font> indicates nodes other than PCs.
                 A $idlemark mark by the node count indicates that the
                 experiment is currently considered idle. The number of
                 local pcs is indicated in the parens. 
             <li>A $stalemark indicates that the data is stale, and
		 at least one node in the experiment has not reported
		 on its proper schedule.\n";
    if ($parens) {
	# don't show this unless we did it... most users shouldn't ever
	# need to know that you can set expts to have their idleness ignored
	echo "Values are in parenthesis for idle-ignore experiments.\n";
    }
    echo "</ol></font>\n";
    
    echo "<center><b>Node Totals</b></center>\n";
    echo "<table border=0
                 cellpadding=1 cellspacing=1 align=center>\n";
    $total = 0;
    ksort($total_usage);
    while (list($type, $count) = each($total_usage)) {
	    $total += $count;
	    echo "<tr>
                    <td align=right>${type}:</td>
                    <td>&nbsp &nbsp &nbsp &nbsp</td>
                    <td align=center>$count</td>
                  </tr>\n";
    }
    echo "<tr>
             <td align=right><hr></td>
             <td>&nbsp &nbsp &nbsp &nbsp</td>
             <td align=center><hr></td>
          </tr>\n";
    echo "<tr>
             <td align=right><b>Total</b>:</td>
             <td>&nbsp &nbsp &nbsp &nbsp</td>
             <td align=center>$total</td>
          </tr>\n";
    
    echo "</table>\n";
} # if ($thumb && !$idle)
} # if (mysql_num_rows($experiments_result))

#
# Standard Testbed Footer
# 
PAGEFOOTER();
?>
