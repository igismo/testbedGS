<?php

include("defs.php");
$this_user = CheckLoginOrDie();
$idx = $this_user->uid_idx();
$isadmin = ISADMIN();

#
# People who are allowed to see this page:
#   - red dot
#
if (!$isadmin) {
   USERERROR("You are not authorized to see this page");
}

$title = "Class limit estimates for all class projects";

PAGEHEADER($title);

if (isset($_POST['start']) && isset($_POST['stop']) && isset($_POST['limit']) && isset($_POST['pid']))
{
	$limit = $_POST['limit'];
	$start = $_POST['start'];
	$stop = $_POST['stop'];
	$pid = $_POST['pid'];
	$project  = Project::Lookup($pid);
	$pid_idx  = $project->pid_idx();
	while($start <= $stop)
	{
	    # Instead of checking if we need to insert or update, 
	    # delete the existing record if any and then insert
	    $query_result = DBQueryFatal("delete from class_resource_limits where pid='" . $pid . "' and time=from_unixtime(" . $start . ")");
	    $query_result = DBQueryFatal("insert into class_resource_limits (pid, pid_idx, node_limit, time) values('" . $pid . "','" . $pid_idx . "','" . $limit . "', from_unixtime(" . $start . "))");
	    $start = $start + 86400;		
	}	
}

passthru("/usr/testbed/libexec/class_limit_trends");


PAGEFOOTER();
?>
