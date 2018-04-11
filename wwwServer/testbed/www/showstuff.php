<?php
#
# EMULAB-COPYRIGHT
# Copyright (c) 2000-2007, 2009 University of Utah and the Flux Group.
# All rights reserved.
#
#
# This is an included file. No headers or footers.
#
# Functions to dump out various things.  
#
include_once("osinfo_defs.php");
include_once("node_defs.php");

#
# Check if a string is a valid IP
#
function CheckIP($address)
{
    return preg_match("/^([0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(\.([0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3}$/",$address); 
}

#
# Show Node information for an experiment.
#
function SHOWNODES($pid, $eid, $sortby, $showclass) {
    global $SCRIPT_NAME;
    global $TBOPSPID;
    
    #
    # If this is an expt in emulab-ops, we also want to see the reserved
    # time. Note that vname might not be useful, but show it anyway.
    #
    # XXX The above is not always true. There are also real experiments in
    # emulab-ops.
    #
    if (!isset($sortby)) {
	$sortclause = "n.type,n.priority";
    }
    elseif ($sortby == "vname") {
	$sortclause = "r.vname";
    }
    elseif ($sortby == "rsrvtime-up") {
	$sortclause = "rsrvtime asc";
    }
    elseif ($sortby == "rsrvtime-down") {
	$sortclause = "rsrvtime desc";
    }
    elseif ($sortby == "nodeid") {
	$sortclause = "n.node_id";
    }
    else {
	$sortclause = "n.type,n.priority";
    }

    # XXX
    if ($pid == "emulab-ops" &&
	($eid == "hwdown" || $eid == "hwbroken")) {
	$showlastlog = 1;
	if (empty($showclass)) {
	    $showclass = "no-pcplabphys";
	}
    }
    else {
	$showlastlog = 0;
    }	

    $classclause = "";
    $noclassclause = "";
    
    if (!empty($showclass)) {
	$opts = explode(",", $showclass);
	foreach ($opts as $opt) {
	    if (preg_match("/^no-([-\w]+)$/", $opt, $matches)) {
		if (!empty($noclassclause)) {
		    $noclassclause .= ",";
		}
		$noclassclause .= "'$matches[1]'";
	    } elseif ($opt == "all") {
		$classclause = "";
		$noclassclause = "";
	    } else {
		if (!empty($classclause)) {
		    $classclause .= ",";
		}
		$classclause .= "'$opt'";
	    }
	}
	if (!empty($classclause)) {
	    $classclause = "and nt.class in (" . $classclause . ")";
	}
	if (!empty($noclassclause)) {
	    $noclassclause = "and nt.class not in (" . $noclassclause . ")";
	}
    }

    #
    # Discover whether to show or hide certain columns
    #
    $colcheck_query_result = 
      DBQueryFatal("SELECT sum(oi.OS = 'Windows') as winoscount, ".
                   "       sum(nt.isplabdslice) as plabcount ".
                   "from reserved as r ".
                   "left join nodes as n on n.node_id=r.node_id ".
                   "left join os_info as oi on n.def_boot_osid=oi.osid ".
                   "left join node_types as nt on n.type = nt.type ".
                   "WHERE r.eid='$eid' and r.pid='$pid'");
    $colcheckrow = mysql_fetch_array($colcheck_query_result);
    $anywindows = $colcheckrow['winoscount'];
    $anyplab    = $colcheckrow['plabcount'];

    if ($showlastlog) {
	#
	# We need to extract, for each node, just the latest nodelog message.
	# I could not figure out how to do this in a single select so instead
	# create a temporary table of node_id and latest log message date
	# for all reserved nodes to re-join with nodelog to extract the latest
	# log message.
	#
	if (!empty($classclause) || !empty($noclassclause)) {
	    DBQueryFatal("CREATE TEMPORARY TABLE nodelogtemp ".
			 "SELECT r.node_id, MAX(reported) AS reported ".
			 "FROM reserved AS r ".
			 "LEFT JOIN nodelog AS l ON r.node_id=l.node_id ".
			 "LEFT JOIN nodes AS n ON r.node_id=n.node_id ".
			 "LEFT JOIN node_types AS nt ON n.type=nt.type ".
			 "WHERE r.eid='$eid' and r.pid='$pid' ".
			 "$classclause $noclassclause ".
			 "GROUP BY r.node_id");
	} else {
	    DBQueryFatal("CREATE TEMPORARY TABLE nodelogtemp ".
			 "SELECT r.node_id, MAX(reported) AS reported ".
			 "FROM reserved AS r ".
			 "LEFT JOIN nodelog AS l ON r.node_id=l.node_id ".
			 "WHERE r.eid='$eid' and r.pid='$pid' ".
			 "GROUP BY r.node_id");
	}
	#
	# Now join this table and nodelog with the standard set of tables
	# to get all the info we need.  Note the inner join with the temp
	# table, this is faster and still safe since it has an entry for
	# every reserved node.
	#
	$query_result =
	    DBQueryFatal("SELECT r.*,n.*,nt.isvirtnode,nt.isplabdslice, ".
                         " oi.OS,tip.tipname,wa.site,wa.hostname, ".
		         " ns.status as nodestatus, ".
		         " date_format(rsrv_time,\"%Y-%m-%d&nbsp;%T\") as rsrvtime, ".
		         "nl.reported,nl.entry,nt.isjailed,nt.isremotenode ".
		         "from reserved as r ".
		         "left join nodes as n on n.node_id=r.node_id ".
                         "left join widearea_nodeinfo as wa on wa.node_id=n.phys_nodeid ".
		         "left join node_types as nt on nt.type=n.type ".
		         "left join node_status as ns on ns.node_id=r.node_id ".
		         "left join os_info as oi on n.def_boot_osid=oi.osid ".
			 "left join tiplines as tip on tip.node_id=r.node_id ".
		         "inner join nodelogtemp as t on t.node_id=r.node_id ".
		         "left join nodelog as nl on nl.node_id=r.node_id and nl.reported=t.reported ".

		         "WHERE r.eid='$eid' and r.pid='$pid' ".
			 "$classclause $noclassclause".
		         "ORDER BY $sortclause");
	DBQueryFatal("DROP table nodelogtemp");
    }
    else {
	$query_result =
	    DBQueryFatal("SELECT r.*,n.*,nt.isvirtnode,nt.isplabdslice, ".
                         " oi.OS,tip.tipname,wa.site,wa.hostname, ".
		         " ns.status as nodestatus, ".
		         " date_format(rsrv_time,\"%Y-%m-%d&nbsp;%T\") ".
			 "   as rsrvtime,nt.isjailed,nt.isremotenode, ".
			 " nta.attrvalue as image_src ".
		         "from reserved as r ".
		         "left join nodes as n on n.node_id=r.node_id ".
                         "left join widearea_nodeinfo as wa on wa.node_id=n.phys_nodeid ".
		         "left join node_types as nt on nt.type=n.type ".
		         "left join node_status as ns on ns.node_id=r.node_id ".
		         "left join os_info as oi on n.def_boot_osid=oi.osid ".
			 "left join tiplines as tip on tip.node_id=r.node_id ".
			 "left join node_type_attributes as nta on nta.type=n.type and nta.attrkey='image_src' ".
		         "WHERE r.eid='$eid' and r.pid='$pid' ".
			 "$classclause $noclassclause".
		         "ORDER BY $sortclause");
    }
    
    if (mysql_num_rows($query_result)) {
	echo "<div align=center>
              <br>
              <a href=" . $_SERVER["REQUEST_URI"] . "#reserved_nodes>
                <font size=+1><b>Reserved Nodes</b></font></a>
              <a NAME=reserved_nodes></a>
              <table id='nodetable' align=center border=1>
              <thead class='sort'>
              <tr>
                <th>Node ID</th>
                <th>Name</th>\n";

        # Only show 'Site' column if there are plab nodes.
        if ($anyplab) {
            echo "  <th>Site</th>
                    <th>Widearea<br>Hostname</th>\n";
        }

	if ($pid == $TBOPSPID) {
	    echo "<th class='sorttable_nosort'>Reserved<br>
                      <a href=\"$SCRIPT_NAME?pid=$pid&eid=$eid".
		         "&sortby=rsrvtime-up&showclass=$showclass\">Up</a> or 
                      <a href=\"$SCRIPT_NAME?pid=$pid&eid=$eid".
		         "&sortby=rsrvtime-down&showclass=$showclass\">Down</a>
                  </th>\n";
	}
	echo "  <th>Type</th>
                <th>Default OSID</th>
                <th>Node<br>Status</th>
                <th>Hours<br>Idle[<b>1</b>]</th>
                <th>Startup<br>Status[<b>2</b>]</th>\n";
	if ($showlastlog) {
	    echo "  <th>Last Log<br>Time</th>
		    <th class='sorttable_nosort'>Last Log Message</th>\n";
	}

        echo "  <th class='sorttable_nosort'>Disk Image</th>
		<th class='sorttable_nosort'>Snapshot</th>
                <th class='sorttable_nosort'>Log</th>";
	echo "  </tr></thead>\n";

	$stalemark = "<b>?</b>";
	$count = 0;
	$sharednodes = 0;

	while ($row = mysql_fetch_array($query_result)) {
	    $node_id = $row['node_id'];
	    $vname   = $row['vname'];
	    $rsrvtime= $row['rsrvtime'];
	    $type    = $row['type'];
            $wasite  = $row['site'];
            $wahost  = $row['hostname'];
	    $def_boot_osid = $row['def_boot_osid'];
	    $startstatus   = $row['startstatus'];
	    $status        = $row['nodestatus'];
	    $bootstate     = $row['eventstate'];
	    $isvirtnode    = $row['isvirtnode'];
            $isplabdslice  = $row['isplabdslice'];
	    $isjailed      = $row['isjailed'];
	    $isremote      = $row['isremotenode'];
	    $tipname       = $row['tipname'];
	    $iswindowsnode = $row['OS']=='Windows';
	    $sharemode     = $row['sharing_mode'];
	    $image_src     = $row['image_src'];

	    if (is_null($image_src)) {
		$image_src = TRUE;
	    }
	    if (! ($node = Node::Lookup($node_id))) {
		TBERROR("SHOWNODES: Could not map $node_id to its object", 1);
	    }
	    $idlehours = $node->IdleTime();
	    $stale     = $node->IdleStale();

	    $idlestr = $idlehours;
	    if ($idlehours > 0) {
		if ($stale) {
		    $idlestr .= $stalemark;
		}
	    }
	    elseif ($idlehours == -1) {
		$idlestr = "&nbsp;";
	    }

	    if (!$vname)
		$vname = "--";

	    if ($count & 1) {
		echo "<tr></tr>\n";
	    }
	    $count++;

	    echo "<tr>
                    <td><a href='shownode.php?node_id=$node_id'>$node_id</a>";
	    if (isset($sharemode) && $sharemode == "using_shared_local") {
		echo " *";
		$sharednodes++;
	    }
	    echo "</td>\n";
	    echo "<td>$vname</td>\n";

            if ($isplabdslice) {
              echo "  <td>$wasite</td>
                      <td>$wahost</td>\n";
            }
            elseif ($anyplab) {
              echo "  <td>&nbsp;</td>
                      <td>&nbsp;</td>\n";
            }

	    if ($pid == $TBOPSPID)
		echo "<td>$rsrvtime</td>\n";
            echo "  <td>$type</td>\n";
	    if ($def_boot_osid) {
		echo "<td>";
		SPITOSINFOLINK($def_boot_osid);
		echo "</td>\n";
	    }
	    else
		echo "<td>&nbsp;</td>\n";

	    if ($isvirtnode && !($isjailed || $isplabdslice)) {
		echo "  <td>$bootstate</td>\n";
	    }
	    elseif ($bootstate != "ISUP") {
		echo "  <td>$status ($bootstate)</td>\n";
	    }
	    else {
		echo "  <td>$status</td>\n";
	    }
	    
	    echo "  <td>$idlestr</td>
                    <td align=center>$startstatus</td>\n";

	    if ($showlastlog) {
		echo "  <td>" . $row['reported'] . "</td>\n";
		echo "  <td>" . $row['entry'] . "
                           (<a href='shownodelog.php?node_id=$node_id'>LOG</a>)
                        </td>\n";
	    }


        if ((! $def_boot_osid) || $isvirtnode || ($bootstate != "ISUP") || ($image_src == FALSE)) {
		    echo "  <td align=center>&nbsp;</td>\n";
		    echo "  <td align=center>&nbsp;</td>\n";

	} else {
		echo "<td align=center>
			<a href='newimageid_ez.php?formfields[simple]=1&formfields[node_id]=$node_id'>
			Create New Disk Image
			</a></td>\n";

               if (! ($osinfo = OSinfo::Lookup($def_boot_osid)) || ($image_src == FALSE)) {
		    echo "  <td align=center>&nbsp;</td>\n";
	       } else {
                    # os id and image id are the same for ezid images.
		    if($osinfo->ezid()) {
		    	echo "<td align=center>
				<a href='loadimage.php?imageid=$def_boot_osid&node=$node_id'>
				Snapshot Disk to Image
				</a></td>\n";
		     } else {
			    echo "  <td align=center>&nbsp;</td>\n";
                     }
              }
	}

	    if ($isvirtnode || !isset($tipname) || $tipname = '') {
		if ($isvirtnode) {
		    echo "  <td align=center>
                                <a href='bootlog.php?node_id=$node_id'>
                                <img src=\"/console.gif\" alt='boot log'></a>
                            </td>\n";
		}
		else {
		    echo "  <td>&nbsp;</td>\n";
		}
	    }
	    else {
		echo "  <td align=center>
                            <a href='showconlog.php?node_id=$node_id".
		                  "&linecount=200'>
                            <img src=\"/console.gif\" alt='console log'></a>
                        </td>\n";
	    }

	    echo "</tr>\n";
	}
	echo "</table>\n";
	if ($sharednodes) {
	    echo "<center>* This experiment is using <a href='showpool.php'>";
	    echo "<b>shared nodes</b></a></center>\n";
	}

	echo "<div style=\"width:70%; margin:auto;\">\n";
	echo "<font size=-1>\n";
	echo "<ol style=\"text-align: left;\">
	        <li>A $stalemark indicates that the data is stale, and
	            the node has not reported on its proper schedule.</li>
                <li>Exit value of the node startup command. A value of
                        666 indicates a testbed internal error.</li>\n";
        echo "</ol>
              </font></div>\n";
	echo "</div>\n";
	
	# Sort initialized later when page fully loaded.
	AddSortedTable('nodetable');
    }

// T1T2 begin
$query_result = DBQueryFatal("SELECT * from risky_experiments where eid='$eid' and pid='$pid'");
if (mysql_num_rows($query_result)) {
    $row = mysql_fetch_array($query_result);
    $malware = $row["malware"];
    $selfprop = $row["selfprop"];
    $malware_src = $row["malware_src"];
    $conn_type = $row["conn_type"];
    $conn_rsrcidx = $row["conn_rsrcidx"];
    $connect = 0;
    echo "<div align=center>
          <br>
          <a href=" . $_SERVER["REQUEST_URI"] . "#risky_exp_settings>
          <font size=+1><b>Risky Experiment Settings</b></font></a>
          <a NAME=risky_exp_settings>
          <table id='rextable' align=center border=1>
          <thead class='sort'>
      <tr><th>Malware</th><th>Connectivity</th></tr></thead>
          <tbody><tr><td>";
    if ($malware) {
        echo "Yes<br>";
        if ($selfprop) echo "Self-propagating<br>";
        echo "Source: $malware_src";
    } else {
        echo "No";
    }
    echo "</td><td>";
    $query_result = DBQueryFatal("SELECT * from risky_resources where idx='$conn_rsrcidx'");
    if (mysql_num_rows($query_result)) {
        $connect = 1;
        echo "$conn_type<BR>";
    } else {
        echo "No<br>";
    }
    echo "</table>";
    if ($connect) {
        echo "<div align=center>
          <br>
          <a href=" . $_SERVER["REQUEST_URI"] . "#conn_info>
          <font size=+1><b>Allowed External Connectivity</b></font></a>
          <a NAME=conn_info>
          <table id='contable' align=center border=1>
          <thead class='sort'>
          <tr><th>From</th><th>To</th><th>Proto</th><th>via NAT settings</th><th>Status</th></tr></thead>
          <tbody><tr>";
        while ($row = mysql_fetch_array($query_result)) {
            $from = $row["from"];
            $fromport = $row["fromport"];
            $to = $row["to"];
            $toport = $row["toport"];
            $proto = $row["proto"];
            $real = $row["real"];
            if (CheckIP($to)) echo "<td>Experiment</td><td>$to:$toport</td><td>$proto</td><td></td>";
            else {
                echo "<td>Non-DETER nodes</td><td>$to:$toport</td><td>$proto</td>";
                if ($real) echo "<td>$from:$fromport</td>";
                else echo "<td></td>";
            }
            if ($real) echo "<td>Granted</td></tr>";
            else echo "<td>Requested</td></tr>";
        }
        echo "</table>";
    }
}
}

#
# Spit out an OSID link in user format.
#
function SPITOSINFOLINK($osid)
{
    if (! ($osinfo = OSinfo::Lookup($osid)))
	return;

    $osname = $osinfo->osname();
    
    echo "<a href='showosinfo.php?osid=$osid'>$osname</a>\n";
}

#
# This is an included file.
# 
?>
