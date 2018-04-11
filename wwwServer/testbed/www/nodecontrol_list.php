<?php
#
# Copyright (c) 2000-2012 University of Utah and the Flux Group.
# 
# {{{EMULAB-LICENSE
# 
# This file is part of the Emulab network testbed software.
# 
# This file is free software: you can redistribute it and/or modify it
# under the terms of the GNU Affero General Public License as published by
# the Free Software Foundation, either version 3 of the License, or (at
# your option) any later version.
# 
# This file is distributed in the hope that it will be useful, but WITHOUT
# ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
# FITNESS FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public
# License for more details.
# 
# You should have received a copy of the GNU Affero General Public License
# along with this file.  If not, see <http://www.gnu.org/licenses/>.
# 
# }}}
#
include("defs.php");
include_once("node_defs.php");

#
# This page is used for both admin node control, and for mere user
# information purposes. Be careful about what you do outside of
# $isadmin tests.
# 

#
# Only known and logged in users can do this.
#
$this_user = CheckLoginOrDie();
$uid       = $this_user->uid();
$isadmin   = ISADMIN();

#
# Verify page arguments.
#
$optargs = OptionalPageArguments("target_user",	PAGEARG_USER,
				 "showtype",    PAGEARG_STRING,
				 "typefilter",  PAGEARG_STRING,
				 "bypid",       PAGEARG_STRING);

if (isset($target_user)) {
    if (! $target_user->AccessCheck($this_user, $TB_USERINFO_READINFO)) {
	USERERROR("You do not have permission to do this!", 1);
    }
    $target_uid  = $target_user->uid();
    $target_idx  = $target_user->uid_idx();
}
else {
    $target_uid  = $uid;
    $target_idx  = $this_user->uid_idx();
    $target_user = $this_user;
}

#
# Standard Testbed Header
#
PAGEHEADER("Node Control Center");

echo "<b>Tabular views: <a href='nodecontrol_list.php?showtype=summary'>summary</a>,
               <a href='nodecontrol_list.php?showtype=pcs'>pcs</a>";

if ($isadmin) {
    echo    ", <a href='nodeutilization.php'>utilization</a>,
               <a href='nodecontrol_list.php?showtype=virtnodes'>virtual</a>,
               <a href='nodecontrol_list.php?showtype=physical'>physical</a>,
               <a href='nodecontrol_list.php?showtype=all'>all</a>";
}
echo ".</b><br>\n";

if (!isset($showtype)) {
    $showtype='summary';
}

$additionalVariables = "";
$additionalLeftJoin  = "";

if (! strcmp($showtype, "summary")) {
    # Separate query below.
    $role   = "";
    $clause = "";
    $view   = "Free Node Summary";
}
elseif (! strcmp($showtype, "all")) {
    $role   = "(role='testnode' or role='virtnode')";
    $clause = "";
    $view   = "All";
}
elseif (! strcmp($showtype, "pcs")) {
    $role   = "(role='testnode')";
    $clause = "and (nt.class='pc')";
    $view   = "PCs";
}
elseif (! strcmp($showtype, "virtnodes")) {
    $role   = "(role='virtnode')";
    $clause = "";
    $view   = "Virtual Nodes";
}
elseif (! strcmp($showtype, "physical")) {
    $role   = "";
    $clause = "(nt.isvirtnode=0)";
    $view   = "Physical Nodes";
}
elseif (preg_match("/^[-\w]+$/", $showtype)) {
    $role   = "(role='testnode')";
    $clause = "and (nt.type='$showtype')";
    $view   = "only <a href=shownodetype.php?node_type=$showtype>$showtype</a>";
}
else {
    $role   = "(role='testnode')";
    $clause = "and (nt.class='pc')";
    $view   = "PCs";
}

# If adding an additional type filter list, do that...
if (isset($typefilter)) {
    $types = explode(",",$typefilter);
    $typeclause = "and nt.type in (";
    foreach ($types as $t) {
	# Sanitize.
	if (!preg_match("/^[-\w]+$/", $t)) {
	    PAGEARGERROR("Invalid characters in typefilter argument '$t'.");
	}
	$typeclause .= "'$t',";
    }
    $typeclause = rtrim($typeclause,",");
    $typeclause .= ")";
    $clause .= " $typeclause";
}

# If admin, show the vname too. 
$showvnames = 0;
if ($isadmin) {
    $showvnames = 1;
}

#
# Summary info very different.
# 
if (! strcmp($showtype, "summary")) {
    # Get permissions table so as not to show nodes the user is not allowed
    # to see.
    $perms = array();
    
    if (!$isadmin || isset($bypid)) {
	$query_result =
	    DBQueryFatal("select type from nodetypeXpid_permissions");

	while ($row = mysql_fetch_array($query_result)) {
	    $perms{$row[0]} = 0;
	}

	$pidclause = "";
	if (isset($bypid)) {
	    if ($bypid == "" || !TBvalid_pid($bypid)) {
		PAGEARGERROR("Invalid characters in 'bypid' argument!");
	    }
	    if (! ($target_project = Project::Lookup($bypid))) {
		PAGEARGERROR("No such project '$bypid'!");
	    }
	    if (!$target_project->AccessCheck($this_user,
					      $TB_PROJECT_READINFO)){
		USERERROR("You are not a member of project '$bypid!", 1);
	    }
	    $pidclause = "and g.pid='$bypid'";
	}
	if ($isadmin) {
	    $query_result =
		DBQueryFatal("select distinct type ".
			     "  from nodetypeXpid_permissions ".
			     "where pid='$bypid'");
	}
	else {
	    $query_result =
		DBQueryFatal("select distinct type from group_membership as g ".
			     "left join nodetypeXpid_permissions as p ".
			     "     on g.pid=p.pid ".
			     "where uid_idx='$target_idx' $pidclause");
	}
	
	while ($row = mysql_fetch_array($query_result)) {
	    $perms{$row[0]} = 1;
	}
    }

    # Get a node type to cluster map
    $query_result = DBQueryFatal("SELECT distinct nodes.type, IFNULL(nta.attrvalue, 'n/a') as cluster ".
                                 "FROM nodes ".
                                 "LEFT JOIN node_type_attributes AS nta ".
                                 "ON nodes.type=nta.type AND nta.attrkey = 'cluster' ");

    while ($row = mysql_fetch_array($query_result)) {
        $type_to_cluster{$row[0]} = $row[1];
    }

    # Get totals by cluster
    $query_result =
                  DBQueryFatal("SELECT nta.attrvalue as cluster, count(*) as total ".
                               "FROM nodes ".
                               "LEFT JOIN node_type_attributes AS nta ON nodes.type=nta.type AND nta.attrkey = 'cluster' ".
                               "LEFT JOIN node_type_attributes AS ntb ON nodes.type = ntb.type AND ntb.attrkey = 'special_hw' ".
                               "WHERE ntb.attrvalue != 1 AND nta.attrvalue IS NOT NULL ".
                               "GROUP BY cluster");

    while ($row = mysql_fetch_array($query_result)) {
        $cluster_totals{$row[0]} = $row[1];
    }

    # Get free by cluster
    $query_result =
                  DBQueryFatal("SELECT nta.attrvalue as cluster, ".
                               "count(*) as free ".
                               "FROM nodes AS n ".
                               "LEFT JOIN nodes AS np ON np.node_id=n.phys_nodeid ".
                               "LEFT JOIN node_types AS nt ON n.type=nt.type ".
                               "LEFT JOIN reserved AS r ON r.node_id=n.node_id ".
                               "LEFT JOIN reserved AS rp ON rp.node_id=n.phys_nodeid ".
                               "LEFT JOIN node_type_attributes AS nta ON n.type = nta.type AND nta.attrkey = 'cluster' ".
                               "LEFT JOIN node_type_attributes AS ntb ON n.type = ntb.type AND ntb.attrkey = 'special_hw' ".
                               "WHERE (n.role='testnode') ".
                               "AND r.pid IS NULL AND rp.pid IS NULL ".
                               "AND n.reserved_pid IS NULL AND np.reserved_pid IS NULL ".
                               "AND nta.attrvalue IS NOT NULL ".
                               "AND ntb.attrvalue != 1 ".
                               "GROUP BY cluster");

    while ($row = mysql_fetch_array($query_result)) {
        $cluster_freecounts{$row[0]} = $row[1];
    }

    
    # Get totals by type.
    $query_result =
	DBQueryFatal("select n.type,count(*), na.attrvalue from nodes as n ".			    
		     "left join node_types as nt on n.type=nt.type ".
		     "left join node_type_attributes as na on n.type=na.type ".
		     "where (role='testnode') ".
		     "      and na.attrkey ='special_hw' ".
		     "group BY n.type, na.attrvalue");

    $totals    = array();
    $freecounts = array();
    $specials = array();
    $unknowncounts = array();

    while ($row = mysql_fetch_array($query_result)) {
	$type  = $row[0];
	$count = $row[1];
	$specials[$type] = $row[2];

	$totals[$type]    = $count;
	$freecounts[$type] = 0;
	$unknowncounts[$type] = 0;
    }

    if ($isadmin) {
	$emptytypes = array();
	# find defined types without any nodes.
	$query_result =
	    DBQueryFatal("select type from node_types as nt ".
			 "where (select count(*) from nodes as n ".
			 "       where n.type=nt.type and n.role='testnode') <= 0");
	while ($row = mysql_fetch_array($query_result)) {
	    $emptytypes[] = $row[0];
	}
    }

    # Get free totals by type.  Note we also check that the physical node
    # is free, see note on non-summary query for why.
    $query_result =
	DBQueryFatal("select n.eventstate,n.type,count(*) from nodes as n ".
		     "left join nodes as np on np.node_id=n.phys_nodeid ".
		     "left join node_types as nt on n.type=nt.type ".
		     "left join reserved as r on r.node_id=n.node_id ".
		     "left join reserved as rp on rp.node_id=n.phys_nodeid ".
		     "where (n.role='testnode') ".
		     "      and r.pid is null and rp.pid is null ".
		     "      and n.reserved_pid is null and np.reserved_pid is null ".
		     "group BY n.eventstate,n.type");

    while ($row = mysql_fetch_array($query_result)) {
	$type  = $row[1];
	$count = $row[2];
        # XXX Yeah, I'm a doofus and can't figure out how to do this in SQL.
	if (($row[0] == TBDB_NODESTATE_ISUP) ||
	    ($row[0] == TBDB_NODESTATE_PXEWAIT) ||
	    ($row[0] == TBDB_NODESTATE_ALWAYSUP) ||
	    ($row[0] == TBDB_NODESTATE_POWEROFF)) {
	    $freecounts[$type] += $count;	    
	}
	else {
	    $unknowncounts[$type] += $count;
	}
    }

    $projlist = $target_user->ProjectAccessList($TB_PROJECT_CREATEEXPT);
    if (count($projlist) > 1) {
	echo "<b>By Project Permission: ";
	while (list($project) = each($projlist)) {
	    echo "<a href='nodecontrol_list.php?".
		"showtype=summary&bypid=$project'>$project</a>,\n";
	}
	echo "<a href='nodecontrol_list.php?showtype=summary'>".
	    "combined membership</a>.\n";
	echo "</b><br>\n";
    }

    $alltotal  = 0;
    $allfree   = 0;
    $allunknown = 0;


    if (isset($cluster_totals)) {
        echo "<br><center>
              <b>Cluster Totals for General Nodes</b>
              <br>\n";
        echo "<table>
              <tr>
                  <th>Cluster</th>
                  <th align=center>Free<br>Nodes</th>
                  <th align=center>Total<br>Nodes</th>
                  <th align=center>Free (%)</th>
              </tr>\n";

        foreach($cluster_totals as $cluster => $cluster_total) {
            $cluster_freecount = $cluster_freecounts[$cluster];
            $percent_free = sprintf("%.1f%%", 100 * $cluster_freecount/$cluster_total);
            echo "<tr>
                     <td>$cluster</td>
                     <td>$cluster_freecount</td>
                     <td>$cluster_total</td>
                     <td>$percent_free</td>
                  </tr>\n";
        }
        echo "</table></center>";
    }
    
    echo "<br><center>
          <b>General Nodes</b>
          <br>\n";
    if (isset($bypid)) {
	echo "($bypid)<br><br>\n";
    }
    echo "<table>
          <tr>
             <th>Type</th>
             <th>Cluster</th>
             <th align=center>Free<br>Nodes</th>
             <th align=center>Total<br>Nodes</th>
             <th align=center>Free %</th>
          </tr>\n";

    foreach($totals as $key => $value) {
    	if ($specials[$key] == 1)
	   continue;
	$freecount = $freecounts[$key];

	# Check perm entry.
	if (isset($perms[$key]) && !$perms[$key])
	    continue;

	$allfree   += $freecount;
	$allunknown += $unknowncounts[$key];
	$alltotal  += $value;

	if ($unknowncounts[$key])
	    $ast = "*";
	else
	    $ast = "";

	echo "<tr>\n";
	echo "<td><a href=\"shownodetype.php?node_type=$key\">$key</a>\n";

	if ($isadmin)
	    echo "<small>(<a href=\"editnodetype.php?node_type=$key\">edit</a>)</small>\n";

        echo "</td>\n";
        echo "<td align=center>" . $type_to_cluster[$key] . "</td>\n";
        echo "<td align=center>${freecount}${ast}</td>\n";
        echo "<td align=center>$value</td>\n";
        echo "<td align=center>" . sprintf("%.1f%%", 100 * $freecount/$value) . "</td>\n";
        echo "</tr>\n";
    }
    echo "<tr></tr>\n";
    $allfree_percent = sprintf("%.1f%%", 100 * $allfree/$alltotal);
    echo "<tr>
            <td><b>Totals</b></td>
              <td></td>
              <td align=center>$allfree</td>
              <td align=center>$alltotal</td>
              <td align=center>$allfree_percent</td>
              </tr>\n";

    if ($isadmin) {
	# Give admins the option to create a new type
	echo "<tr></tr>\n";
	echo "<th colspan=3 align=center>
                <a href=editnodetype.php?new_type=1>Create a new type</a>
              </th>\n";
    }
    echo "</table>\n";
    if ($allunknown > 0) {
	    echo "<br><font size=-1><b>*</b> - Some general nodes ($allunknown) are ".
		    "free, but currently in an unallocatable state.</font>";
    }
    $alltotal  = 0;
    $allfree   = 0;
    $allunknown = 0;

    echo "<br><br><center>
          <b>Special Hardware</b>
          <br>\n";
    if (isset($bypid)) {
	echo "($bypid)<br><br>\n";
    }
    echo "<table>
          <tr>
             <th>Type</th>
             <th align=center>Free<br>Nodes</th>
             <th align=center>Total<br>Nodes</th>
          </tr>\n";

    foreach($totals as $key => $value) {
    	if ($specials[$key] == 0)
	   continue;
	$freecount = $freecounts[$key];

	# Check perm entry.
	if (isset($perms[$key]) && !$perms[$key])
	    continue;

	$allfree   += $freecount;
	$allunknown += $unknowncounts[$key];
	$alltotal  += $value;

	if ($unknowncounts[$key])
	    $ast = "*";
	else
	    $ast = "";

	echo "<tr>\n";
	echo "<td><a href=\"shownodetype.php?node_type=$key\">$key</a>\n";

	if ($isadmin)
	    echo "<small>(<a href=\"editnodetype.php?node_type=$key\">edit</a>)</small>\n";

        echo "</td>\n";
        echo "<td align=center>${freecount}${ast}</td>\n";
        echo "<td align=center>$value</td>\n";
        echo "</tr>\n";
    }
    echo "<tr></tr>\n";
    echo "<tr>
            <td><b>Totals</b></td>
              <td align=center>$allfree</td>
              <td align=center>$alltotal</td>
              </tr>\n";

    if ($isadmin) {
	# Give admins the option to create a new type
	echo "<tr></tr>\n";
	echo "<th colspan=3 align=center>
                <a href=editnodetype.php?new_type=1>Create a new type</a>
              </th>\n";
    }
    echo "</table>\n";
    if ($allunknown > 0) {
	    echo "<br><font size=-1><b>*</b> - Some special hardware ($allunknown) is ".
		    "free, but currently in an unallocatable state.</font>";
    }
    if ($isadmin && (count($emptytypes) > 0)) {
	echo "<br><br><center>
		  <b>Node-free Types</b>
		  <br>\n";
	echo "<table>
		  <tr>
		    <th colspan=2 align=center >Type</th>
		  </tr>\n";
	foreach($emptytypes as $type) {
	    echo "<tr>\n";
	    echo "<td><a href=\"shownodetype.php?node_type=$type\">$type</a>\n";
	    echo "</td>\n";
	    echo "<td><small>(<a href=\"editnodetype.php?node_type=$type\">edit</a>)</small>\n";
	    echo "</td>\n";
	    echo "</tr>\n";
	}
	echo "</table>\n";
    }
    PAGEFOOTER();
    exit();
}

#
# Suck out info for all the nodes.
#
# If a node is free we check to make sure that that the physical node
# is also.  This is based on the assumption that if a physical node is
# not available, neither is the node, such as the case with netpga2.
# This may not be true for virtual nodes, such as PlanetLab slices,
# but virtual nodes are allocated on demand, and thus are never free.
# 
$query_result =
    DBQueryFatal("select distinct n.node_id,n.phys_nodeid,n.type,ns.status, ".
		 "   n.def_boot_osid, ".
		 "   n.reserved_pid is null as noreserved_pid, ".
		 "   if(r.pid is not null,r.pid,rp.pid) as pid, ".
	         "   if(r.pid is not null,r.eid,rp.eid) as eid, ".
		 "   nt.class, ".
	 	 "   if(r.pid is not null,r.vname,rp.vname) as vname ".
		 "$additionalVariables ".
		 "from nodes as n ".
		 "left join node_types as nt on n.type=nt.type ".
		 "left join node_status as ns on n.node_id=ns.node_id ".
		 "left join reserved as r on n.node_id=r.node_id ".
		 "left join reserved as rp on n.phys_nodeid=rp.node_id ".
		 "$additionalLeftJoin ".
		 "where $role $clause ".
		 "ORDER BY priority");

if (mysql_num_rows($query_result) == 0) {
    echo "<center>Oops, no nodes to show you!</center>";
    PAGEFOOTER();
    exit();
}

#
# First count up free nodes as well as status counts.
#
$num_free = 0;
$num_up   = 0;
$num_pd   = 0;
$num_down = 0;
$num_unk  = 0;
$num_reserve_pid_set = 0;
$freetypes= array();

while ($row = mysql_fetch_array($query_result)) {
    $pid                = $row["pid"];
    $status             = $row["status"];
    $type               = $row["type"];
    $noreserved_pid	= $row["noreserved_pid"];

    if (! isset($freetypes[$type])) {
	$freetypes[$type] = 0;
    }
    if (!$pid) {
	if ($noreserved_pid) {
	    $num_free++;
	    $freetypes[$type]++;
	} else {
	    $num_reserve_pid_set++;
	}
	continue;
    }
    switch ($status) {
    case "up":
	$num_up++;
	break;
    case "possibly down":
    case "unpingable":
	$num_pd++;
	break;
    case "down":
	$num_down++;
	break;
    default:
	$num_unk++;
	break;
    }
}
$num_total = ($num_free + $num_up + $num_down + $num_reserve_pid_set + $num_pd + $num_unk);
mysql_data_seek($query_result, 0);

echo "<br><center><b>
       View: $view\n";

echo "</b></center><br>\n";

SUBPAGESTART();

echo "<table>
       <tr><td align=right>
           <img src='/autostatus-icons/greenball.gif' alt=up>
           <b>Up</b></td>
           <td align=right>$num_up</td>
       </tr>
       <tr><td align=right nowrap>
           <img src='/autostatus-icons/yellowball.gif' alt='possibly down'>
           <b>Possibly Down</b></td>
           <td align=right>$num_pd</td>
       </tr>
       <tr><td align=right>
           <img src='/autostatus-icons/blueball.gif' alt=unknown>
           <b>Unknown</b></td>
           <td align=right>$num_unk</td>
       </tr>
       <tr><td align=right>
           <img src='/autostatus-icons/redball.gif' alt=down>
           <b>Down</b></td>
           <td align=right>$num_down</td>
       </tr>
       <tr><td align=right>
           <img src='/autostatus-icons/orangeball.gif' alt=reserved>
           <b>Reserved</b></td>
           <td align=right>$num_reserve_pid_set</td>
       </tr>
       <tr><td align=right>
           <img src='/autostatus-icons/whiteball.gif' alt=free>
           <b>Free</b></td>
           <td align=right>$num_free</td>
       </tr>
       <tr><td align=right><b>Total</b></td>
           <td align=right>$num_total</td>
       </tr>
       <tr><td colspan=2 nowrap align=center>
               <b>Free Subtotals</b></td></tr>\n";

foreach($freetypes as $key => $value) {
    echo "<tr>
           <td align=right><a href=shownodetype.php?node_type=$key>
                           $key</a></td>
           <td align=right>$value</td>
          </tr>\n";
}
echo "</table>\n";
SUBMENUEND_2B();

echo "<table border=2 cellpadding=2 cellspacing=2 id='nodelist'>\n";

echo "<thead class='sort'>";
echo "<tr>
          <th align=center>ID</th>\n";

if ($showvnames) {
    echo "<th align=center>Name</th>\n";
}

echo "    <th align=center>Type (Class)</th>
          <th align=center class='sorttable_nosort'>Up?</th>\n";

if ($isadmin) {
    echo "<th align=center class='sorttable_alpha'>PID</th>
          <th align=center class='sorttable_alpha'>EID</th>
          <th align=center>Default<br>OSID</th>\n";
}

echo "</tr></thead>\n";

while ($row = mysql_fetch_array($query_result)) {
    $node_id            = $row["node_id"]; 
    $phys_nodeid        = $row["phys_nodeid"]; 
    $type               = $row["type"];
    $noreserved_pid     = $row["noreserved_pid"];
    $class              = $row["class"];
    $def_boot_osid      = $row["def_boot_osid"];
    $pid                = $row["pid"];
    $eid                = $row["eid"];
    $vname              = $row["vname"];
    $status             = $row["status"];

    echo "<tr>";

    # Admins get a link to expand the node.
    if ($isadmin ||
	(OPSGUY() && (!$pid || $pid == $TBOPSPID))) {
	echo "<td><A href='shownode.php?node_id=$node_id'>$node_id</a> " .
	    (!strcmp($node_id, $phys_nodeid) ? "" :
	     "(<A href='shownode.php?node_id=$phys_nodeid'>$phys_nodeid</a>)")
	    . "</td>\n";
    }
    else {
	echo "<td>$node_id " .
  	      (!strcmp($node_id, $phys_nodeid) ? "" : "($phys_nodeid)") .
	      "</td>\n";
    }

    if ($showvnames) {
	if ($vname)
	    echo "<td>$vname</td>\n";
	else
	    echo "<td>--</td>\n";
    }
    
    echo "   <td>$type ($class)</td>\n";

    if (!$pid)
	if ($noreserved_pid) {
	    echo "<td align=center>
		      <img src='/autostatus-icons/whiteball.gif' alt=free></td>\n";
	} else {
	    echo "<td align=center>
		      <img src='/autostatus-icons/orangeball.gif' alt=reserved></td>\n";
	}
    elseif (!$status)
	echo "<td align=center>
                  <img src='/autostatus-icons/blueball.gif' alt=unk></td>\n";
    elseif ($status == "up")
	echo "<td align=center>
                  <img src='/autostatus-icons/greenball.gif' alt=up></td>\n";
    elseif ($status == "down")
	echo "<td align=center>
                  <img src='/autostatus-icons/redball.gif' alt=down></td>\n";
    else
	echo "<td align=center>
                  <img src='/autostatus-icons/yellowball.gif' alt=unk></td>\n";

    # Admins get pid/eid/vname, but mere users yes/no.
    if ($isadmin) {
	if ($pid) {
	    echo "<td><a href=showproject.php?pid=$pid>$pid</a></td>
                  <td><a href=showexp.php?pid=$pid&eid=$eid>$eid</a></td>\n";
	}
	else {
	    echo "<td>--</td>
   	          <td>--</td>\n";
	}
	if ($def_boot_osid &&
	    ($osinfo = OSinfo::Lookup($def_boot_osid))) {
	    $osname = $osinfo->osname();
	    echo "<td>$osname</td>\n";
	}
	else
	    echo "<td>&nbsp</td>\n";
    }

    echo "</tr>\n";
}

echo "</table>\n";
echo "<script type='text/javascript' language='javascript'>
         sorttable.makeSortable(getObjbyName('nodelist'));
      </script>\n";
SUBPAGEEND();

#
# Standard Testbed Footer
# 
PAGEFOOTER();
?>


