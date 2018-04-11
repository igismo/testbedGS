<?php
#
# EMULAB-COPYRIGHT
# Copyright (c) 2000-2007 University of Utah and the Flux Group.
# All rights reserved.
#
include("defs.php");
include("showstuff.php");
include_once("table_defs.php");

# ignore warnings for is_file permission errors
error_reporting(0);

#
# Only known and logged in users can do this.
#

# this bit of ugliness wraps up CheckLogin fail
# This is a DETER hack to allow the deter-exec group to view project applicant
# user info without being logged in.
$this_user = null;
global $CHECKLOGIN_USER;
$status = LoginStatus();
if ($status & (CHECKLOGIN_USERSTATUS|CHECKLOGIN_WEBONLY))
    $this_user = $CHECKLOGIN_USER;

$isadmin   = ISADMIN();

if (!$isadmin && !$this_user)
	USERERROR("You must be logged on to assign materials to students");



# Standard Testbed Header
#
PAGEHEADER("Assign materials to students", null, null, null);
$reqargs  = RequiredPageArguments("duedate", PAGEARG_DATE, "pid", PAGEARG_STRING, "base", PAGEARG_STRING, "assignmethod", PAGEARG_STRING, "limit", PAGEARG_ARRAY, "perstudent", PAGEARG_ARRAY);

$tuid=$this_user->uid();
$pid=trim($_POST["pid"]);
$base=trim($_POST["base"]);
$assignmethod=trim($_POST["assignmethod"]);
$duedate=trim($_POST["duedate"]);
$limit=trim($_POST["limit"][0]);
$perstudent=trim($_POST["perstudent"][0]);

# Check that this user is teacher or TA of the given class
if (!$this_user->IsTeacherOrTA($pid))
   USERERROR("You don't have privileges to create assignments in class $pid");

if ($assignmethod == "gid")
   $gid = $_POST["assigntogid"];
else 
   $gid = $pid;

# Check due date
ValidateArgument(PAGEARG_DATE, $duedate);
$timenow = mktime(0,0,0,date('n'), date('j'), date('Y'));
if (strtotime($duedate) < $timenow)
   USERERROR("Due date is in the past");

if ((!isset($perstudent) || $perstudent == 0) && (!isset($limit) || $limit == 0))
   USERERROR("You must set the limit per student or per class!");


$users = array();

# Collect uids of students that will get this assignment
if ($assignmethod == "gid" or $assignmethod == "all")
{ 
     $sql = "select g.uid, u.usr_name,e.usr_email from group_membership g left join users u on g.uid=u.uid left join email_aliases e on g.uid=e.uid where pid='$pid' and trust!='group_root' and g.gid='$gid' and g.uid like '%$base%' and u.usr_email not like '%localhost%' order by g.uid";
     $query_result = DBQueryFatal($sql);
     while($row = mysql_fetch_array($query_result))
     {
	$uid = $row['uid'];
	array_push($users, $uid);
     }
}
else if($assignmethod == "individual")
{
  foreach ($_POST as $key => $value)
  {
   if (preg_match("/^student/", $key) && $key != "students")
	array_push($users, $value);
  }
}

if (!isset($perstudent) || $perstudent == "")
   $perstudent = 0;

if ($limit == 0)
   $limit = $perstudent*count($users);


foreach ($_POST as $key => $value)
{
   if (preg_match("/^assignmid\d+/", $key))
   {
      $pieces = explode("|", $value);
      $title=$pieces[0];
      $path=$pieces[1];
      $type=$pieces[2];
      $mid=$pieces[3];

      # Change visibility if needed
      $query_result = DBQueryFatal("select gid, path from class_materials where mid='$mid'");

      $row = mysql_fetch_array($query_result);
      $oldgids[$mid] = $row['gid'];
      if ($oldgids[$mid] == "all")
      	 $oldgids[$mid] = $pid;
      $oldpaths[$mid] = $row['path'];

      if ($oldgids[$mid] != $gid)
      {
        if (preg_match("/^\/groups\//", $oldpaths[$mid]))
	{
	  $new = "/groups/" . $pid . "/" . $gid . "/" . $mid;
	  $old = "/groups/" . $pid . "/" . $oldgids[$mid] . "/" . $mid;

	  if ($old != $new)
	  {
		$group = Group::LookupByPidGid($pid, $gid);
	  	$unix_name = $group->unix_name();

	  	$query_result = DBQueryFatal("update class_materials set gid='$gid',path='$new' where mid='$mid'");
	  	STARTBUSY("Changing the visibility");
	  	$retval = SUEXEC($tuid, $unix_name, "webcpfile $old $new 1", SUEXEC_ACTION_IGNORE);	   
	  	STOPBUSY();
	  }
	 }
	 else
	 {
	   $query_result = DBQueryFatal("update class_materials set gid='$gid' where mid='$mid'");
	 }
      }

      $sql = "update class_materials set perstudent=$perstudent, classlimit=$limit where mid=$mid";
      DBQueryFatal($sql);

      $timenow = time();
      foreach ($users as $u)
      {
	print "User $u<BR>";
        # Check that we don't duplicate materials
        $sql = "select * from class_assignments where mid='$mid' and uid='$uid'";
        $query_result = DBQueryFatal($sql);
        if (mysql_num_rows($query_result) == 0)
        {
            $sql = "insert into class_assignments (uid, pid, mid, state, assigned, due) values ('$u', '$pid', '$mid', 'assigned', from_unixtime($timenow), '$duedate')";
            $query_result = DBQueryFatal($sql);
      	}
     }
   }
}

# Now update schedule so we can update resource limits
$sql = "select assigned,due, perstudent, classlimit from class_assignments a join class_materials m on a.mid=m.mid where a.pid='$pid' group by a.mid order by a.assigned";
$assigned_result = DBQueryFatal($sql);
$sql = "select assigned,due, perstudent, classlimit from class_schedule where pid='$pid' order by assigned";
$schedule_result = DBQueryFatal($sql);
while($srow = mysql_fetch_array($schedule_result))
{
        $assigned_s = $srow['assigned'];
	$due_s = $srow['due'];
	$perstudent_s = $srow['perstudent'];
	$classlimit_s = $srow['classlimit'];

	if ($arow = mysql_fetch_array($assigned_result))
	{
	   $assigned_a = $arow['assigned'];
	   $assigned_a = mktime(0,0,0,date('n'), date('j'), date('Y'));
	   $due_a = $arow['due'];
	   $perstudent_a = $arow['perstudent'];
	   $classlimit_a = $arow['classlimit'];
	   $sql = "update class_schedule set assigned=from_unixtime($assigned_a), due='$due_a', perstudent='$perstudent_a', classlimit='$classlimit_a' where
	   pid='$pid' and assigned='$assigned_s' and due='$due_s' and perstudent='$perstudent_s' and classlimit='$classlimit_s'";
	   DBQueryFatal($sql);
	}
}
while($arow = mysql_fetch_array($assigned_result))
{
  $assigned_a = $arow['assigned'];
  $due_a = $arow['due'];
  $perstudent_a = $arow['perstudent'];
  $classlimit_a = $arow['classlimit'];
  $sql = "insert into class_schedule (pid, assigned, due, perstudent, classlimit) values ('$pid', '$assigned_a', '$due_a','$perstudent_a', '$classlimit_a')";
  DBQueryFatal($sql);
}

echo "<script type=\"text/javascript\">\n";
echo "window.location = \"manage_class.php?pid=$pid&action=manageassignments\"";
echo "</script>";


#
# Standard Testbed Footer
# 
PAGEFOOTER();
?>



