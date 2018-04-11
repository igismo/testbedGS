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
if (!$this_user)
{
   USERERROR("You must be logged on to proceed");
}

$uid = $this_user->uid();
$target_idx = $this_user->uid_idx();

#
# Standard Testbed Header
#


$pid="";
$name="";
$copies="";
$nsfile="";
$cctffolder="";
$type="";


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

   if (!isset($_POST['pid']))
   {
	USERERROR("PID must be set in POST");
   }
   $pid = $_POST['pid'];
   $query_result = DBQueryFatal('select * from projects where pid=\'' . $pid . '\'');
   if(mysql_num_rows($query_result) == 0)
   {
	USERERROR("No such project $pid");
   }   				    

   if (!isset($_POST['action']))
   {
	USERERROR("Action must be set in POST");
   }

   $action = $_POST['action'];

   if ($action == "new")
   {
     PAGEHEADER("Creating New Competition");
     if (isset($_POST['name']))
     {
	$name = $_POST['name'];
      	if (!preg_match("/^[a-zA-Z].*/", $name))
		USERERROR("Name must start with letters $name");

	# Check for duplicate name
        $query_result = DBQueryFatal('select * from competitions where name=\'' . $name . '\' and pid=\'' . $pid . '\'');
        if(mysql_num_rows($query_result) > 0)
		USERERROR("Duplicate competition $name in project $pid");
	
     }     
     else
	USERERROR("You must specify the name for the competition!");	str_replace(" ", "", $name);
    
     if (isset($_POST['copies']))
        $copies = $_POST['copies'];
      
     if (isset($_POST['cctffolder']))
     {
	$cctffolder = $_POST['cctffolder'];
	$nsfile = $cctffolder . "/cctf.ns";
       	if (!preg_match("/^\/proj\/|^\/users\/|^\/share\//", $nsfile))
	   USERERROR("You must specify a path that starts with /proj/, /users/ or /share/");
     }


     $type = $_POST['type'];
     if ($type == 'circular' && $copies == 1)
     	USERERROR("Circular type requires at least two copies");

     $group = Group::LookupByPidGid($pid, $pid);
     $unix_gid = $group->unix_gid();

     $r = 0;

     STARTBUSY("Creating experiments and teams");

     # Create enough experiments for competitions
     for ($i=1; $i<=$copies; $i++)
     {
	$instname = $name . $i;

       	$retval=SUEXEC($uid, "$pid,$unix_gid", "webbatchexp -i -f -E $instname ".
		                      " -p $pid -g $pid -e $instname " .
				      $nsfile, SUEXEC_ACTION_IGNORE);
	$r = $r + $retval;
    }
    if ($r == 0)
    {
	$query_result = DBQueryFatal('insert into competitions (pid,name,nspath, copies,type,state) ' .
		      'values (\'' . $pid . '\',\'' . $name . '\',\'' .
		      $cctffolder . '\',\'' . $copies . '\',\'' . $type .
		      '\',\'created\')');

	# Get the cid
        $query_result = DBQueryFatal('select * from competitions where name=\'' . $name . '\' and pid=\'' . $pid . '\'');
 	$row = mysql_fetch_array($query_result);
        $cid = $row['cid'];

        $project = Project::LookupByPid($pid);
        $args = array();
        $errors = array();

        $args["project"]=$pid;	
	$args["group_leader"]=$uid;
	
	for($i=1; $i<=$copies;$i++)
	{
		$instname = $name . $i;
		$instid = $cid . "_" . $i;
		if ($type == 'circular')
		{
		  $name = "team_" . $instid;
		  $args["group_id"]=$name;
		  $args["group_description"]="Team for " . $instname;

 		  $team = Group::Create($project, $uid, $args, $errors);
                }
		else
		{
		  $gname = "bteam_" . $instid;
		  $args["group_id"]=$gname;
		  $args["group_description"]="Blue team for " . $instname;

 		  $team = Group::Create($project, $uid, $args, $errors);
		  $gname = "rteam_" . $instid;
		  $args["group_id"]=$gname;
		  $args["group_description"]="Red team for " . $instname;
		 		  
 		  $team = Group::Create($project, $uid, $args, $errors);
		}
	   }
        }
	else
	 USERERROR("Something failed during experiment creation");

	STOPBUSY();
        # Now redirect to another page
        echo "<script type=\"text/javascript\">\n";
        echo "<!--\nwindow.location = \"showuser.php?tab=compete\"\n//-->\n";
        echo "</script>";
   }

PAGEFOOTER();
return;
}

PAGEHEADER("New Competition");

echo "<CENTER>Please fill in the required data to start a new competition.<P><P><TABLE WIDTH='50%'><TR><TD>Before you can start competitions in a given project, this ability must be granted to the project. Please submit a ticket to request this.";
echo "<P>The competition folder path must contain the NS file called cctf.ns.";
echo "<P>The <B>copies</B> field asks how many experiments you want to allocate for this competition.";
echo "<P>There are two possible <B>team assignments</B>: paired or circular. For the circular assignment, you will need as many copies of the competition as you have teams. Each team will play defense on one experiment and offense on another experiment. ";
echo "For the paired assignment, you will need half as many copies of the competition as you have teams. Each team will either play defense or offenseon one experiment.";
echo "<form name='form1' id='form1' enctype='multipart/form-data' action='new_competition.php' method='POST'>\n";
echo "<input type=hidden name=action value='new'>\n";
echo "<CENTER><TABLE CLASS=stealth><TR><TD>Name:</TD><TD><INPUT type=text size=40 name='name'></INPUT></TD></TR>";
echo "<TR><TD>Project:</TD><TD>";
  $query_result = DBQueryFatal(
   'select p.pid from projects p, group_membership m ' .
   ' where p.pid_idx = m.pid_idx and m.pid_idx = m.gid_idx and ' .
   '       m.trust != "none" and ' .
   '       m.uid_idx = ' . $target_idx  .
   ' order by p.pid '
    );
echo "<SELECT name='pid'>";
while ($row = mysql_fetch_array($query_result)) {
   $pid = $row['pid'];
   echo "<option value='$pid'>$pid</option>\n";
}
echo "</SELECT>";
						    
echo "<TR><TD>Competition folder path:</TD><TD><INPUT type=text size=40 name='cctffolder'></INPUT></TD></TR>";
echo "<TR><TD>How many copies:</TD><TD>";
echo "<SELECT name='copies'>";
echo "<option value='1' selected>1</option>\n";
for($i=2;$i<10;$i++) {
   echo "<option value='$i'>$i</option>\n";
}
echo "</SELECT>";
echo "</TD></TR>";
echo "<TR><TD>Team assignment type:</TD><TD>";
echo "<SELECT name='type'>";
echo "<option value='circular'>circular</option>\n";
echo "<option value='paired' selected>paired</option>\n";
echo "</SELECT>";
echo "</TD></TR>";
echo "<TR><TD COLSPAN=2 ALIGN=center><button onclick='javascript: document.form1.submit()' style='font: bold 14px Arial' type='button'>Create</button></table></form><br><br></TABLE>\n";

PAGEFOOTER();
?>
