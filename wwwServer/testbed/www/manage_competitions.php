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
$reqargs  = RequiredPageArguments("pid", PAGEARG_PID, "cid", PAGEARG_INTEGER, "action", PAGEARG_STRING);

$pid  = $reqargs["pid"];
$cid  = $reqargs["cid"];
$action  = $reqargs["action"];

$project = Project::LookupByPid($pid);

$group    = $project->Group();
$pid      = $project->pid();
$pid_idx = $project->pid_idx();

$cmd = 'select * from competitions where cid=' . $cid;
$query_result = DBQueryFatal($cmd);

$projrow = mysql_fetch_array($query_result);
$name = $projrow["name"];
$copies = $projrow["copies"];
$type = $projrow["type"];


if (! ($this_user && $project->AccessCheck($this_user, $TB_PROJECT_READINFO))) {
   USERERROR("You are not a member of project $pid.", 1);
}

if (!TBMinTrust(TBGrpTrust($uid, $pid, $pid), $TBDB_TRUST_GROUPROOT)) {
   USERERROR("You cannot manage project $pid.", 1);
}

# Which groups relate to this competition

$cmd = 'select gid from groups where gid like \'%team\_' . $cid . '\_%\'';
$query_result = DBQueryFatal($cmd);

$teams = array();
while($projrow = mysql_fetch_array($query_result))
{
	$gid = $projrow["gid"];
	$teams[$gid] = 1;
}
$n=count($teams)+1;

# Remember group membership
$query_result = DBQueryFatal('select uid from users u join projects p on u.uid_idx = p.head_idx where pid=\'' . $pid . '\'');
$projrow = mysql_fetch_array($query_result);
$head_uid = $projrow['uid'];

$query_result = DBQueryFatal('select gm.uid, u.usr_name, gm.gid, e.usr_email from group_membership gm left join email_aliases e on gm.uid=e.uid left join users u on u.uid=gm.uid where pid=\'' . $pid . '\'');

$names = array();
$users = array();
$membership = array();
$emails = array();

while($projrow = mysql_fetch_array($query_result))
{
	$muid = $projrow["uid"];
	$mname=$projrow["usr_name"];
	$gid = $projrow["gid"];
	$email = $projrow["usr_email"];
	$id = $muid . "_" . $gid;
	if ($gid == $pid && $muid != $head_uid)
	{  
	   array_push($users, $muid);
	   $names[$muid] = $mname;
	   $emails[$muid] = $email;
        }
	elseif(array_key_exists($gid, $teams))
	   $membership[$id] = 1;
}

$group = Group::LookupByPidGid($pid, $pid);
$unix_gid = $group->unix_gid();


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if(isset($_POST['action']))
      $action = $_POST['action'];
}
else
{
      $action = $_GET['action'];
      $pid = $_GET['pid'];
      $cid = $_GET['cid'];
}


if ($action == 'setup')
{
	$formname = "formcomp";
	#
	# Standard Testbed Header
	#
	PAGEHEADER("Set Up Competition $name");

	echo "<center><P>The following actions should be performed in order before you can start the competition.<P><P>";		 
	echo "<center><form name='" . $formname .  "' id='" . $formname . "' enctype='multipart/form-data' method='POST'>\n";

        $query_result = DBQueryFatal('select * from competitions where' .
              ' cid=' . $cid);
        $row = mysql_fetch_array($query_result);
        $name = $row["name"];
	$state = $row["state"];

	if ($state == "created")
	{
	   $statusroles='pending';
	   $statusteams='pending';
	}
	else
	{
	   $statusroles='done';
	   if ($state == "access")
	   {
	   	$statusteams='pending';
	   }
           else
	   {
		$statusteams='done';
	   }
	}
	
        echo "<table class=stealth><tr><th colspan=2>Action</th><th>Status</th></tr>";
        echo "<tr><td>Set up access to machines by blue or red team (or none)</td><td align=center> <button onclick='document.location.href=\"manage_competitions.php?action=roles&pid=$pid&cid=$cid\"' style='font: bold 14px Arial' type='button'>Blue/red</button></td><td>$statusroles</td></tr>";
        echo "<tr><td>Assign users to teams</td><td align=center><button onclick='document.location.href=\"manage_competitions.php?action=teams&pid=$pid&cid=$cid\"' style='font: bold 14px Arial' type='button'>Teams</button></td><td>$statusteams</td></tr>";
	echo "</table>";
	echo "<button onclick='document.location.href=\"showuser.php?tab=compete\"' style='font: bold 14px Arial' type='button'>Back</button></form>";
}
if ($action == 'run')
{
	$formname = "formcomp";
	#
	# Standard Testbed Header
	#
	PAGEHEADER("Run Competition $name");


        $query_result = DBQueryFatal('select * from competitions where' .
              ' cid=' . $cid);
        $row = mysql_fetch_array($query_result);
        $name = $row["name"];
	$state = $row["state"];

	if ($state == "created" || $state == "access")
	   USERERROR("This competition has not been set up.");


        if ($state == 'teams')
	{
	   $statusalloc='pending';
	   $statussetup='pending';
	}
	else
	{
	   $statusalloc='done';
	   if ($state == "allocated")	   
	     $statussetup = 'pending';
	   elseif($state == "active")
	     $statussetup = 'done';
         }       

	echo "<center><P>The competition must be allocated and set up before you can start and score it. ";
	echo "<P>Alocation may take a few minutes. When all experiments are swapped in you will be able to set up software. ";
	echo "<P>When software is set up, scoring starts automatically. You can zero-down the score using the Start button.<P><P>";

	echo "<center><form name='" . $formname .  "' id='" . $formname . "' enctype='multipart/form-data' method='POST'>\n";
        echo "<table class=stealth><tr><th colspan=2>Action</th><th>Status</th></tr>";
	echo "<tr><td>Allocate machines</td><td align=center><button onclick='document.location.href=\"manage_competitions.php?action=allocate&pid=$pid&cid=$cid\"' style='font: bold 14px Arial' type='button'>Allocate</button></td><td>$statusalloc</td></tr>";
	echo "<tr><td>Set up software on machines</td><td align=center><button onclick='document.location.href=\"manage_competitions.php?action=prepare&pid=$pid&cid=$cid\"' style='font: bold 14px Arial' type='button'>Set up</button></td><td>$statussetup</td></tr>";
	echo "<tr><td>Start competition - zero out score</td><td align=center><button onclick='document.location.href=\"manage_competitions.php?action=start&pid=$pid&cid=$cid\"' style='font: bold 14px Arial' type='button'>Start</button></td><td></td></tr>";
	echo "<tr><td>Score competition</td><td align=center><button onclick='document.location.href=\"manage_competitions.php?action=score&pid=$pid&cid=$cid\"' style='font: bold 14px Arial' type='button'>Score</button></td><td></td></tr>";
	echo "<tr><td>Retire machines</td><td align=center><button onclick='document.location.href=\"manage_competitions.php?action=deactivate&pid=$pid&cid=$cid\"' style='font: bold 14px Arial' type='button'>Retire</button></td><td></td></tr>";
	echo "</table>";
	echo "<button onclick='document.location.href=\"showuser.php?tab=compete\"' style='font: bold 14px Arial' type='button'>Back</button></form>";
}
elseif ($action == "roles")
{
   if (isset($_GET['cid']))
      $cid = $_GET['cid'];
   elseif (isset($_POST['cid']))
      $cid = $_POST['cid'];
   else
      USERERROR("Cid must be set in POST or GET");


     PAGEHEADER("Set up node access");

       echo "<script type='text/javascript' language='javascript'>\n
          function process()
       {
           document.form1.action.value = 'processroles';
           document.form1.submit();
      }
      </script>";

     $query_result = DBQueryFatal('select state from competitions where cid=' . $cid);
     $row = mysql_fetch_array($query_result);
     $state = $row['state'];
     if ($state == 'active' || $state == 'allocated')
     	USERERROR("Cannot update blue and red assignments on an active competition. Please swap out the experiments and try again.");

	
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    {
       $query_result = DBQueryFatal('delete from competition_access where cid=' . $cid);
       foreach ($_POST as $key => $value)
       {
	if (preg_match("/\_/", $key))
   	{
	  $items = explode("_", $key);
	  $team = $items[0];
	  $node = $items[1];
	  $cmd='insert into competition_access (cid,node, team) values (' .
		      $cid . ',\'' . $node . '\',\'' . $team . '\')';;
	  $query_result = DBQueryFatal($cmd);
	}
     }
     if ($state == 'created')
     {
	    $cmd='update competitions set state="access" where cid=' . $cid;
            $query_result = DBQueryFatal($cmd);
      }
    }
   }
   # Display the nodes again and their access settings	   

   echo "<form name='form1' id='form1' enctype='multipart/form-data' action='manage_competitions.php' method='POST'>\n";
   echo "<input type=hidden name=cid value='$cid'>\n";
   echo "<input type=hidden name=pid value='$pid'>\n";
   echo "<input type=hidden name=action value='roles'>\n";
   echo "<CENTER>Please select teams that can access the given nodes.<BR>";
   echo "An unchecked checkbox <B>removes</B> access for the given team(s)<P>";
   echo "<TABLE class=stealth><TR><TH>Node</TH><TH>Blue</TH><TH>Red</TH>";

   $cmd = 'select node, team from competition_access ca where cid=' . $cid;
   $query_result = DBQueryFatal($cmd);

   $blue = array();
   $red = array();
	   
   while ($row = mysql_fetch_array($query_result)) {

    $node = $row['node'];
    $team = $row['team'];
	      
    if ($team == 'blue')
      	 $blue[$node] = 1;
    if ($team == 'red')
      	 $red[$node] = 1;
  }
    $query_result = DBQueryFatal('select name from competitions where cid=' . $cid);
    $row = mysql_fetch_array($query_result);
    $name = $row['name'];

    $cmd = 'select vname from virt_nodes v where eid=\'' . $name . '1\' and pid=\'' . $pid . '\'';

    $query_result = DBQueryFatal($cmd);
    while ($row = mysql_fetch_array($query_result)) {
      $node = $row['vname'];
      echo "<TR><TD>$node</TD><TD align=center><input type='checkbox' name='blue_" . $node . "' ";
      if (array_key_exists($node, $blue))
      	 echo "checked";
      echo "></TD><TD align=center><input type='checkbox' name='red_" . $node . "'";
      if (array_key_exists($node, $red))
      	 echo "checked";
      echo "></TD></TD></TR>";
    }
     echo "<TR><TD COLSPAN=3 align=center><button onclick='javascript: document.form1.submit()' style='font: bold 14px Arial' type='button'>Setup</button>\n";
 	     
     echo "<button onclick='javascript: processroles()' style='font: bold 14px Arial' type='button'>Done</button></form></td></tr>";
     echo "</TABLE>";
}
elseif($action == 'processroles')
{
   if (!isset($_POST['cid']))
	USERERROR("Cid must be set in POST");
   $cid = $_POST['cid'];
   
   $cmd = 'select node, team from competition_access ca where cid=' . $cid;
   $query_result = DBQueryFatal($cmd);
   $output = "";
   while ($row = mysql_fetch_array($query_result)) {

              $node = $row['node'];
              $team = $row['team'];

	      $output .= $node . " " . $team . "\n";
      }

    $cmd = 'select * from competitions where cid=' . $cid;
    $query_result = DBQueryFatal($cmd);

    $projrow = mysql_fetch_array($query_result);
    $name = $projrow["name"];
    $copies = $projrow["copies"];
    $type = $projrow["type"];
    $group = Group::LookupByPidGid($pid, $pid);
    $unix_gid = $group->unix_gid();
    for ($i=1; $i<=$copies;$i++)
    {
      $filename = "/proj/" . $pid . "/exp/" . $name . $i . "/tbdata/bluered";
      SUEXEC($uid, "$pid,$unix_gid", "webtexttofile '$output' $filename", SUEXEC_ACTION_IGNORE);
   }
   # Now redirect to another page
   header("Location: showuser.php?tab=compete");
}
elseif ($action == 'teams')
{

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {

   if (!isset($_POST['pid']))
	USERERROR("PID must be set in POST");
   
   $pid = $_POST['pid'];

   $query_result = DBQueryFatal('select state from competitions where' .
   		 ' cid=' . $cid);
   $row = mysql_fetch_array($query_result);
   
   $state = $row['state'];

   if ($state == 'active' || $state == 'allocated')
       	USERERROR("Cannot update team assignments on an active competition. 
	Please swap out the experiments and try again.");

   if ($state == 'created')
       	USERERROR("Cannot update team assignments before access is set up");

   PAGEHEADER("Assign Users to Teams");
   STARTBUSY("Assigning members to teams.");  
   foreach ($_POST as $key => $value)
   {
        if (preg_match("/\_/", $key))
	{
	    $a = explode(",", $value);
	    foreach ($a as $usernames)
	   {
		  $strings = explode(" ", $usernames);
		  $muid = $strings[0];
		  if ($muid == '')
		    continue;
		  $mgid = $key;
	   	  $id = $muid . "_" . $mgid;
	          $group = Group::LookupByPidGid($pid, $mgid);
	   
	          # Check if user already in group
	          if (!array_key_exists($id, $membership))
		    # Add the user to group
	   	   SUEXEC($uid, $TBADMINGROUP, "webmodgroups -a $pid:$mgid:local_root $muid &", SUEXEC_ACTION_IGNORE);
	           $membership[$id] = 2;
	    }
	}
   }
   foreach ($membership as $key => $value)
   {
	# This membership should be removed
	if ($value == 1)
	{
		$a = explode("_", $key, 2);
		$muid = $a[0];
		$mgid = $a[1];
		$cmd = "webmodgroups -r $pid:$mgid $muid";
                SUEXEC($uid, $TBADMINGROUP, "webmodgroups -r $pid:$mgid $muid &", SUEXEC_ACTION_IGNORE);
		unset($membership[$key]);		 
	}
   }
   STOPBUSY();
   if ($state == 'access')
      $query_result = DBQueryFatal('update competitions set state=\'teams\' where cid=' . $cid);
  }
  else
  {
  PAGEHEADER("Assign Users to Teams");
  }

  echo "<script>
  function allowDrop(ev) {
    ev.preventDefault();
}

function drag(ev) {
    ev.dataTransfer.setData(\"text\", ev.target.id);
}

function drop(ev) {
    ev.preventDefault();
    var data = ev.dataTransfer.getData(\"text\");
    ev.target.appendChild(document.getElementById(data));
}

function goback() {
  document.form1.action='manage_competitions.php';
  document.form1.action.value = 'processteams';
  document.form1.submit();
}

function processteams(){
  var divs = document.getElementsByTagName(\"div\");
  for(var i = 0; i < divs.length; i++){
    if (divs[i].id.startsWith('div'))
    {   
       var hidden = divs[i].id.substring(3);
       var hel = document.getElementById(hidden);
       var children = divs[i].childNodes;

      for (var j = 0; j < children.length; j++) {
         hel.value += children[j].innerHTML;
	 hel.value += ',';
       }
    }
 }
 document.form1.submit();
}
</script>";

  $len=count($users)*20/4 . "px";
  $len4=count($users)*20 . "px";
  $groups=array();
  $teamtext=array();
  
  foreach ($teams as $gid => $value)
  {
	$groups[$gid] = array();
	$teamtext[$gid] = "";
  }
  $teamtext["unassigned"] = "";
  foreach ($users as $muid)
  {
    $found = 0;
    foreach ($teams as $gid => $value)
    if(isset($membership[$muid . "_" . $gid]))
    {
	$groups[$gid][$muid] = 1;
	$found = 1;
	break;
    }
    if (!$found)
    {
	$groups["unassigned"][$muid] = 1;
    }
  }
  foreach ($groups as $g => $value)
  {
     foreach($value as $muid => $number)
     {
	$name = $names[$muid];
	$teamtext[$g] .= "<div id=\"$muid\" draggable=\"true\" ondragstart=\"drag(event)\">$muid $name</div>";
     }
  }


  echo "<form name='form1' id='form1' enctype='multipart/form-data' action='manage_competitions.php?action=teams' method='POST'>\n";
  echo "<input type=hidden name=cid value='$cid'>\n";
  echo "<input type=hidden name=pid value='$pid'>\n";
  echo "<input type=hidden name=action value='teams'>\n";
  echo "<P><CENTER>Please drag users to teams. A user can only be in one team at one time.<P>";
  echo "<table class=stealth>";
  echo "<tr><th class=stealth>Unassigned</th><th class=stealth>Teams</th></tr>";
  echo "<tr><td class=stealth>";
  echo "<input type=hidden name='unassigned' id='unassigned' value=''>\n";
  echo "<style>#divunassigned {width:300px;height:$len4;padding:10px;border:1px solid #aaaaaa;background-color: white;}</style>\n";
  echo "<div id='divunassigned' ondrop=\"drop(event)\" ondragover=\"allowDrop(event)\">";
  echo $teamtext['unassigned'];
  echo "</div>";
  echo "</td><td class=stealth><table class=stealth>";
  foreach ($teams as $gid => $value)
  {
  $parts = explode("_", $gid);
  if (preg_match("/^b/", $parts[0]))
  {
     $teamname="Defense for experiment " . $parts[2];
  }
  elseif (preg_match("/^r/", $parts[0]))
  {
     $teamname="Offense for experiment " . $parts[2];
  }
  else
  {
     $prev = $parts[2];
     if ($prev == $copies)
       $prev = 1;
     else
        $prev = $prev + 1;
     $teamname="Defense for experiment " . $parts[2] . ",";
     $teamname.=" offense for experiment " . $prev;

  }
  echo "<tr><th class=stealth>$teamname</th></tr>";
  echo "<tr><td class=stealth>";

    echo "<input type=hidden name='$gid' id='$gid' value=''>\n";
    echo "<style>#div$gid {width:300px;height:$len;padding:10px;border:1px solid #aaaaaa;background-color: lightgrey;}</style>\n";
    echo "<div id='div$gid' ondrop=\"drop(event)\" ondragover=\"allowDrop(event)\">";
    echo $teamtext[$gid];
    echo "</div></td>";
  }
  echo "</tr></table></td></tr></table>";

  echo "<button onclick='javascript: processteams()' style='font: bold 14px Arial' type='button'>Assign</button>";
  echo "<button onclick='javascript: goback()' style='font: bold 14px Arial' type='button'>Back</button>";
  echo "</form>";
  PAGEFOOTER();
}
elseif ($action == 'processteams')
{
	if (!isset($_POST['cid']))
	   USERERROR("Cid must be set in POST");
	$cid = $_POST['cid'];

	$cmd = 'select * from competitions where cid=' . $cid;
	$query_result = DBQueryFatal($cmd);
	
        $projrow = mysql_fetch_array($query_result);
        $name = $projrow["name"];
        $copies = $projrow["copies"];
        $type = $projrow["type"];

	# Save teams and zero score file
	for ($i=1; $i<=$copies;$i++)
	{
              $output = "blue 0 red 0";
       	      $filename = "/proj/" . $pid . "/exp/" . $name . $i . "/tbdata/score";
              SUEXEC($uid, "$pid,$unix_gid", "webtexttofile '$output' $filename", SUEXEC_ACTION_IGNORE);
	      $output = "";
	      foreach ($teams as $gid => $value)
	      {
		if ($type == 'circular')
		{
		      $j = $i-1;
		      if ($j == 0)
		         $j = count($teams);
		      if($gid == ('team_' . $cid . "_" .  $i))
		         $output .= $gid . " blue\n";
		      elseif($gid == ('team_' . $cid . "_" . $j))
		         $output .= $gid . " red\n";
		      else
		         $output .= $gid . " none\n";
		}
		else
		{
		    if($gid == ('bteam_' . $cid . "_"  . $i))
		         $output .= ($gid . " blue\n");
		    elseif($gid == ('rteam_' . $cid . "_"  . $i))
		         $output .= ($gid . " red\n");
		}
	     }
	     $filename = "/proj/" . $pid . "/exp/" . $name . $i . "/tbdata/teams";
	     SUEXEC($uid, "$pid,$unix_gid", "webtexttofile '$output' $filename", SUEXEC_ACTION_IGNORE);
	  }	  
	  # Now redirect to showuser page
	  echo '<script type="text/javascript">
	       window.location = "showuser.php?tab=compete"
	       </script>';
}
elseif($action == 'allocate')
{
   $query_result = DBQueryFatal('select state from competitions where' .
   		 ' cid=' . $cid);
   $row = mysql_fetch_array($query_result);
   $state = $row['state'];
			    
   if ($state == 'allocated' || $state == 'active')
     	USERERROR("This competition is already allocated.");

   if ($state != 'teams')
     	USERERROR("This competition not ready for allocation. You must set up access to nodes by teams and assign users to teams first.");

   $query_result = DBQueryFatal('select * from competitions where cid=' . $cid);

   $projrow = mysql_fetch_array($query_result);
   $name = $projrow["name"];
   $copies = $projrow["copies"];
   $type = $projrow["type"];

   # Swap in experiments
   for ($i=1; $i<=$copies; $i++)
   {
	$instname = $name . $i;
	echo "Swapping in instance $instname<BR>";
	$retval=SUEXEC($uid, "$pid,$unix_gid", "webswapexp -s in " . 
                                      " $pid $instname", SUEXEC_ACTION_IGNORE);
   }
   echo "Swapin started, you will be redirected to the competitions page.";
   if ($state == 'teams')
      $query_result = DBQueryFatal('update competitions set state=\'allocated\' where cid=' . $cid);
   # Now redirect to showuser page
   sleep(3);
   echo '<script type="text/javascript">
       window.location = "showuser.php?tab=compete"
       </script>';   
}
elseif($action == 'prepare')
{
   $query_result = DBQueryFatal('select * from competitions where' .
   		 ' cid=' . $cid);
   $row = mysql_fetch_array($query_result);
   $state = $row['state'];
   $name = $row["name"];
   $nspath = $row["nspath"];
   $copies = $row["copies"];
			    
    # Check that all experiments are active.
    $active = 0;
    for ($i=1; $i<=$copies; $i++)
    {
	$instname = $name . $i;
	$query_result = DBQueryFatal('select state from experiments where pid=\'' . $pid . '\' and eid=\'' . $instname . '\'');
        $row = mysql_fetch_array($query_result);
   	$state = $row['state'];
	if ($state == 'active')
	   $active++;
    }
    if ($active == $copies)
    {
	# Run start.pl from the competition folder
	echo "All experiments are active, setting up the competition";
	for ($i=1; $i<=$copies;$i++)
	{
	   $instname = $name . $i;
	   SUEXEC($uid, "$pid,$unix_gid", "websetupcctf $nspath $instname $pid", SUEXEC_ACTION_IGNORE);
	}	
        # Record state as active
        $query_result = DBQueryFatal('update competitions set state=\'active\' where cid=' . $cid);
        echo '<script type="text/javascript">
        window.location = "showuser.php?tab=compete"
        </script>';   
    }
    else
       USERERROR("Only $active out of $copies experiments are swapped in. Please allocate the nodes (if you have not already) or wait longer (until the swapin completes).");
}
elseif($action == 'start')
{
   $query_result = DBQueryFatal('select * from competitions where' .
   		 ' cid=' . $cid);
   $row = mysql_fetch_array($query_result);
   $state = $row['state'];
   $name = $row["name"];
   $copies = $row["copies"];
			    
   if ($state != 'active')
     	USERERROR("Cannot start a competition that has not been set up.");

   # Simply clear the score file
   for ($i=1; $i<=$copies;$i++)
   {
        $output = "blue 0 red 0";
        $filename = "/proj/" . $pid . "/exp/" . $name . $i . "/tbdata/score";
        SUEXEC($uid, "$pid,$unix_gid", "webtexttofile '$output' $filename", SUEXEC_ACTION_IGNORE);
   }
   echo '<script type="text/javascript">
   window.location = "showuser.php?tab=compete"
   </script>';
}
elseif($action == 'deactivate')
{
   $query_result = DBQueryFatal('select * from competitions where' .
   		 ' cid=' . $cid);
   $row = mysql_fetch_array($query_result);
   $state = $row['state'];
   $name = $row["name"];
   $copies = $row["copies"];
			    
   if ($state != 'active' && $state != 'allocated')
     	USERERROR("This competition does not have nodes allocated.");

   # Swap out experiments
   for ($i=1; $i<=$copies; $i++)
   {
	$instname = $name . $i;
	echo "Swapping out instance $instname<BR>";
        $retval=SUEXEC($uid, "$pid,$unix_gid", "webswapexp -s out" .
	                                       " $pid $instname", SUEXEC_ACTION_IGNORE);
   }
   # Record state as teams					       
   $query_result = DBQueryFatal('update competitions set state=\'teams\' where cid=' . $cid);
   echo '<script type="text/javascript">
       window.location = "showuser.php?tab=compete"
       </script>';
}
elseif($action == 'score')
{
   $query_result = DBQueryFatal('select * from competitions where' .
   		 ' cid=' . $cid);
   $row = mysql_fetch_array($query_result);
   $state = $row['state'];
   $name = $row["name"];
   $copies = $row["copies"];

   if ($state != 'active')
      USERERROR("Cannot score a competition, which has not started");

   # State doesn't matter
   echo "<CENTER><h3>Scores for competition $name</h3><P>";
   echo "<TABLE class=stealth><TR><TH>Instance</TH><TH>Blue</TH><TH>Red</TH>\n";
   for ($i=1; $i<=$copies; $i++)
   {
	$instname = $name . $i;
	$score = "";
	# Read the score and display
	$filename = "/proj/" . $pid . "/exp/" . $name . $i . "/tbdata/score";
	if ($fp = popen("$TBSUEXEC_PATH $uid $pid webreadfile $filename", "r")) {
          while (!feof($fp)) {
          $string = fgets($fp, 1024);
          $score .= $string;
	  }
	$items = explode(" ", $score);
	echo "<TR><TH>$name$i</TH><TD>$items[1]</TD><TD>$items[3]</TD></TR>";
       }
   }
  echo "</TABLE>";
}
elseif($action == 'destroy')
{
   $query_result = DBQueryFatal('select * from competitions where' .
   		 ' cid=' . $cid);
   $row = mysql_fetch_array($query_result);
   $cid = $row["cid"];
   $pid = $row["pid"];
   $state = $row['state'];
   $name = $row["name"];
   $nspath = $row["nspath"];
   $type = $row["type"];
   $copies = $row["copies"];

			    
   if ($state == 'active' || $state == 'allocated')
     	USERERROR("Cannot destroy a competition that is still running. Please retire nodes and try again.");


   STARTBUSY("Deleting experiments and teams");
   $cmd = 'select * from group_membership where gid like \'%team\_' . $cid . '\_%\' and uid !=\'' . $head_uid . '\'';
   $query_result = DBQueryFatal($cmd);

   $participants = mysql_num_rows($query_result);

   # Record statistics

   $cmd = "insert ignore into competitions_stats (cid, pid, name, nspath, copies, type, participants) values (" .
   		 $cid . ",'" . $pid . "','" .
		 $name . "','" . $nspath . "'," .
		 $copies . ",'" . $type . "'," . $participants . ")";
   echo $cmd;
   $query_result = DBQueryFatal($cmd);
   
   # Terminate experiments
   for ($i=1; $i<=$copies; $i++)
   {
	$instname = $name . $i;
	$cmd = "webendexp $pid,$instname";
        $retval=SUEXEC($uid, "$pid,$unix_gid", "$cmd", SUEXEC_ACTION_IGNORE);
	echo "Terminating instance $instname retval $retval cmd $cmd<BR>";
   }
   # Remove teams
   foreach ($teams as $t => $value)
   {
	$group = Group::LookupByPidGid($pid, $t);
	$gid_idx  = $group->gid_idx();
 	echo "Removing team $t group $gid_idx<br>";
	SUEXEC($uid, $unix_gid, "webrmgroup $gid_idx", SUEXEC_ACTION_DIE);
   }
   # Remove from competitions and access tables
   $query_result = DBQueryFatal('delete from competitions where cid=' . $cid);
   $query_result = DBQueryFatal('delete from competition_access where cid=' . $cid);
   STOPBUSY();
   echo '<script type="text/javascript">
       window.location = "showuser.php?tab=compete"
       </script>';   
} 
?>
