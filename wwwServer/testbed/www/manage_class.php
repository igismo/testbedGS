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
$reqargs  = RequiredPageArguments("project", PAGEARG_PROJECT);
$optargs  = OptionalPageArguments("action", PAGEARG_STRING, "confirmed",  PAGEARG_STRING, "canceled", PAGEARG_STRING);
$project  = $reqargs["project"];
if (isset($optargs["action"]))
{
    $action = $optargs["action"];
}
$group    = $project->Group();
$pid      = $project->pid();
$pid_idx = $project->pid_idx();
if (isset($_POST['studentaction']))
   $action = $_POST['studentaction'];



#
# Standard Testbed Header
#
PAGEHEADER("Manage Class $pid");

if (! ($this_user && $project->AccessCheck($this_user, $TB_PROJECT_READINFO))) {
   USERERROR("You are not a member of project $pid.", 1);
}

if (!TBMinTrust(TBGrpTrust($uid, $pid, $pid), $TBDB_TRUST_GROUPROOT)) {
   USERERROR("You cannot manage project $pid.", 1);
}

#
# Check semester end
#
$query_result = DBQueryFatal("select attrvalue from project_attributes ".
		    "where pid ='$pid' and attrkey='class_ends'");
	
while ($row = mysql_fetch_array($query_result))
{
    $semesterends = $row['attrvalue'];
}
if (isset($semesterends)) {
    $semesterstamp = strtotime($semesterends,0);
} else {
    $semesterstamp = 0;
    $semesterends = "";
}

SUBPAGESTART();
SUBMENUSTART("Help");
WRITESUBMENUBUTTON("Teacher Guidelines",
		   "http://docs.deterlab.net/education/guidelines-for-teachers/");
SUBMENUSECTION("Class");
WRITESUBMENUBUTTON("Setup Class",
		   "manage_class.php?pid=$pid&action=setup");
WRITESUBMENUBUTTON("Recycle Class",
		   "manage_class.php?pid=$pid&action=recycle");
SUBMENUSECTION("People");
WRITESUBMENUBUTTON("Enroll Students or TAs",
		       "manage_class.php?pid=$pid&action=create");
WRITESUBMENUBUTTON("Manage Students or TAs",
		   "manage_class.php?pid=$pid&action=manage");
SUBMENUSECTION("Materials");
WRITESUBMENUBUTTON("Add Materials to Class",  "manage_class.php?pid=$pid&action=addmaterials");
WRITESUBMENUBUTTON("Manage Materials",  "manage_class.php?pid=$pid&action=managematerials");
SUBMENUSECTION("Assignments");
WRITESUBMENUBUTTON("Assign to Students",  "manage_class.php?pid=$pid&action=assigntostudents");
WRITESUBMENUBUTTON("Manage Assignments",  "manage_class.php?pid=$pid&action=manageassignments");
SUBMENUEND();

echo "<script type='text/javascript' language='javascript'>\n
function passtophp(params)
{
  var form = document.createElement('form');
  form.setAttribute('method', 'post');

  for(var key in params) {
    if (key == 'page')
       form.setAttribute('action', params[key]);
    else
    {
    if(params.hasOwnProperty(key)) {
      var hiddenField = document.createElement('input');
      hiddenField.setAttribute('type', 'hidden');
      hiddenField.setAttribute('name', key);
      hiddenField.setAttribute('value', params[key]);

      form.appendChild(hiddenField);
      }
    }   
  }

  document.body.appendChild(form);
  form.submit();
}


function updatelimit(item)
{
  f = document.form2;\n
  var students = f.students.value;
  var index = 'perstudent['.concat(item,']');
  var pers = document.getElementsByName(index)[0].value; 
  var lindex = 'limit['.concat(item,']');
  document.getElementsByName(lindex)[0].value = Math.ceil(pers*students*.25+1);
}
function showselected(type)
{
   f = document.form2;\n
   if (type == 'adopt')
   {
     sel = f.sharedmethod.selectedIndex;\n
     if (sel == 1)
     {
     f.filetoshare.style.display='none';\n
     f.sharedURL.style.display='inline';\n
     }
     if (sel == 0)
     {
     f.filetoshare.style.display='inline';\n
     f.sharedURL.style.display='none';\n
     }
     sel = f.adoptmethod.selectedIndex;\n
     if (sel == 1)
     {
     f.adopttogid.style.display='inline';\n
     }
     else
     {
     f.adopttogid.style.display='none';\n
     }
  }
  else if (type == 'assign')
 { 
    sel = f.assignmethod.selectedIndex;\n
    var elem = document.getElementById('assigntostudents');
    if (sel == 0)
    {
     f.assigntogid.style.display='none';\n
     elem.style.display='none';\n
     }
     else if (sel == 1)
     {
     f.assigntogid.style.display='inline';\n
     elem.style.display='none';\n
     }
     else if (sel == 2)
     {
     f.assigntogid.style.display='none';\n
     elem.style.display='inline';\n
   }
 }
}</script>";


# Gather up the html sections
$sql = "select attrvalue from project_attributes where pid='$pid' and attrkey='class_idbase'";
$query_result = DBQueryFatal($sql);
if (mysql_num_rows($query_result) == 1) {
    $row = mysql_fetch_array($query_result);
    $base = $row['attrvalue'];
}
else
{
   echo "Testbed ops did not assign a stem to your class. Please <A HREF='https://trac.deterlab.net/wiki/TechnicalSupport'>file a ticket!</A><P><P><P>";
   SUBPAGEEND();
   PAGEFOOTER();
   return;		
}

if (!isset($action) || $action == "manage")
{
	echo "<CENTER><H2>Manage students in $pid</H2>\n";
	$sql = "select distinct(g.uid), u.usr_name,e.usr_email, g.trust,u.uid_idx from group_membership g join users u on g.uid=u.uid join email_aliases e on g.uid=e.uid where pid='$pid' and g.uid like '%$base%' and u.usr_email not like '%localhost%' order by g.uid";

	$query_result = DBQueryFatal($sql);
	if (mysql_num_rows($query_result) >= 1) {
        echo "<form name='form2' id='form2' enctype='multipart/form-data' action='manage_class.php?pid=$pid' method='POST'>\n";
	echo "<TABLE><th>Select</th><th>Role</th><th>Username</th><th>Name</th><th>E-mail</th><th>View as</th></tr>";
     	while($row = mysql_fetch_array($query_result))
	  {
		$uid = $row['uid'];
		$uididx = $row['uid_idx'];
		$name = $row['usr_name'];
		$email = $row['usr_email'];
		$trust = $row['trust'];
		if ($trust == "group_root")
		   $role = "TA";
		else
	           $role = "Student";
		echo "<TR><TD><input type='checkbox' name='studentuid$uid' value='$uid|$email'></TD><TD>$role</TD><TD>$uid</TD><TD>$name</TD><TD>$email</TD><TD><A HREF='suuser.php?user=$uididx'><img src='viewas.png' width=40></A></TD>";
		echo "</TR>";
	  }
	  echo "</TABLE>";
          echo "<br>With selected students: <select name='studentaction' ID='studentaction' form='form2'>
	  <option value='none' selected>Do nothing</option>
 	  <option value='recycleone'>Recycle</option>
  	  <option value='resetpwd'>Reset password</option>
  	  <option value='rekeyone'>Reset user SSH keys</option>
  	  <option value='unfreeze'>Unfreeze Web access</option>
 	  <option value='setincomplete'>Grant incomplete</option>
	  </select>";	
  	  echo "<P><button onclick='javascript: document.form2.submit()' style='font: bold 14px Arial' type='button'>Perform action</button></table></form><br><br>\n";
	}
	else
	{
		echo "<CENTER><H2>There are no students enrolled in your class.\n";
	}

	SUBPAGEEND();
	PAGEFOOTER();
	return;
}
else if ($action == "setup")
{
    $students = 5;
    $classends = "";
    # Preload any values from DB
    $query_result = DBQueryFatal("select attrvalue from project_attributes ".
                    "where pid ='$pid' and attrkey='class_ends'");
    if (mysql_num_rows($query_result) == 1) {
     $row = mysql_fetch_array($query_result);  
     $classends = $row['attrvalue'];
    }

    $query_result = DBQueryFatal("select attrvalue from project_attributes ".
                    "where pid ='$pid' and attrkey='num_students'");
    if (mysql_num_rows($query_result) == 1) {
     $row = mysql_fetch_array($query_result);  
     $students = $row['attrvalue'];
    }

    echo "<center><H2>Setup class $pid</H2>";
    echo "<form name='form2' id='form2' enctype='multipart/form-data' action='manage_class.php?pid=$pid&action=finishsetup' method='POST'>\n";
    echo "<TABLE class=stealth width='80%'>";
    echo "<TR><TD COLSPAN=2><FONT COLOR='blue'>We understand that teaching schedules change. Please make your best guess with regard to the questions below. You can always edit your answers by visiting <B>Setup Class</B> option from the left menu</FONT></TD></TR>";
    echo "<TR><TD>Please enter the date when your class ends <INPUT TYPE='date' name='semesterends' value='$classends' placeholder='yyyy-mm-dd'></INPUT></TD>";
    echo "<TD>Anticipated number of students:  <SELECT name='students'>";
    for ($i=5; $i<=100; $i+=5)
    {
        $isselected = '';
	if ($i == $students)
	   $isselected = 'selected';	
    	echo "<option value='$i' $isselected>$i</option>\n";
    }
    echo "</SELECT>";
    echo "<TR><TD COLSPAN=2>Please enter details of your anticipated DeterLab use in this class:</TD></TR>";
    echo "<TR><TD COLSPAN=2 align='center'><TABLE><TH>Assignment</TH><TH>Date assigned</TH><TH>Date due</TH><TH>Machines per user</TH><TH>Class limit</TH></TR>\n";
    $i = 1;
    $query_result = DBQueryFatal("select assigned, due, perstudent, classlimit from class_schedule ".
                    "where pid ='$pid'");
    while($row = mysql_fetch_array($query_result)){
        $assigned = $row['assigned'];
	$due = $row['due'];
	$perstudent = $row['perstudent'];
	$classlimit = $row['classlimit'];
	
    	echo "<TR><TD ALIGN='right'>$i</TD><TD><INPUT TYPE='date' NAME='start[$i]' value=$assigned  placeholder='yyyy-mm-dd'></INPUT></TD><TD><INPUT TYPE='date' NAME='due[$i]' value=$due  placeholder='yyyy-mm-dd'></INPUT></TD><TD><INPUT TYPE-'text' NAME='perstudent[$i]'  onchange='updatelimit($i)' value=$perstudent></INPUT></TD><TD><INPUT TYPE='text' NAME='limit[$i]' value=$classlimit></INPUT></TD></TR>\n";
	$i++;
    }

    for (; $i<=10; $i++)
    	echo "<TR><TD ALIGN='right'>$i</TD><TD><INPUT TYPE='date' NAME='start[$i]'  placeholder='yyyy-mm-dd'></INPUT></TD><TD><INPUT TYPE='date' NAME='due[$i]'  placeholder='yyyy-mm-dd'></INPUT></TD><TD><INPUT TYPE-'text' NAME='perstudent[$i]' value=0 onchange='updatelimit($i)'></INPUT></TD><TD><INPUT TYPE='text' NAME='limit[$i]' value=0></INPUT></TD></TR>\n";

    echo "</TABLE>";
  echo "<TR><TD COLSPAN=2 ALIGN='center'><button onclick='javascript: document.form2.submit()' style='font: bold 14px Arial' type='button'>Set Up</button></TD></TD></TABLE></form><br><br>\n";
}
else if ($action == "finishsetup")
{
  if (!isset($_POST['semesterends']))
        USERERROR("You must specify a date for the end of your class");

  #Check that this is in the right format
  $classends = $_POST['semesterends'];

  ValidateArgument(PAGEARG_DATE, $classends);
  
  $timenow = mktime(0,0,0,date('n'), date('j'), date('Y'));
  
  if(strtotime($classends) < $timenow)
      USERERROR("End time for your class must be in the future.");
      
  $students = $_POST['students'];


 $query_result = DBQueryFatal("select attrvalue from project_attributes ".
                 "where pid ='$pid' and attrkey='class_ends'");
 if (mysql_num_rows($query_result) == 1) 
    DBQueryFatal("update project_attributes set attrvalue='$classends' where pid ='$pid' and attrkey='class_ends'");
 else
    DBQueryFatal("insert into project_attributes (pid, pid_idx, attrkey, attrvalue) values ('$pid','$pid_idx','class_ends','$classends')");

 $query_result = DBQueryFatal("select attrvalue from project_attributes ".
                 "where pid ='$pid' and attrkey='num_students'");

 if (mysql_num_rows($query_result) == 1) 
    DBQueryFatal("update project_attributes set attrvalue='$students' where pid ='$pid' and attrkey='num_students'");
 else
    DBQueryFatal("insert into project_attributes (pid, pid_idx, attrkey, attrvalue) values ('$pid','$pid_idx','num_students',$students)");

 # Reconstruct the state for the project schedule
 $start = $_POST['start'];
 $due = $_POST['due'];
 $perstudent = $_POST['perstudent'];
 $limit = $_POST['limit'];

 $setup = 0;
 # First check validity
 for ($i = 0; $i < count($start); $i++) {
     if (isset($start[$i+1]) && strtotime($start[$i+1]))
      {
          # Check that the dates are in the right format

  	  ValidateArgument(PAGEARG_DATE, $start[$i+1]);
	  ValidateArgument(PAGEARG_DATE, $due[$i+1]);

	  if ($perstudent[$i+1] == 0 && $limit[$i+1] == 0)
	     USERERROR("You must either enter the number of machines used per student or the class limit");
	  if ($limit[$i+1] == 0)
	     USERERROR("You must set a non-zero class limit for assignment " . ($i+1));
	  if (strtotime($start[$i+1]) >= strtotime($due[$i+1]))
	     USERERROR("Assignments must be assigned at least one day before they are due " . $start[$i+1] . " is not before " . $due[$i+1]);

  }
 }
 DBQueryFatal("delete from class_schedule where pid='$pid'");
 for ($i = 0; $i < count($start); $i++) {
      if (isset($start[$i+1]) && strtotime($start[$i+1]))
      {
          $sql = "insert into class_schedule (pid, assigned, due, perstudent, classlimit) values ('$pid','" . $start[$i+1] . 
 "','" . $due[$i+1] .  "','" . $perstudent[$i+1] . "','" . $limit[$i+1] . "')";
	  DBQueryFatal($sql);
	  $setup++;
      }
  }
 if ($setup == 0)
    USERERROR("You must input at least one assignment");
 # Setup was > 0
 $query_result = DBQueryFatal("select attrvalue from project_attributes ".
                 "where pid ='$pid' and attrkey='setup_done'");
 if (mysql_num_rows($query_result) == 1) 
    DBQueryFatal("update project_attributes set attrvalue='1' where pid ='$pid' and attrkey='setup_done'");
 else
    DBQueryFatal("insert into project_attributes (pid, pid_idx, attrkey, attrvalue) values ('$pid','$pid_idx','setup_done',1)");

  echo "<script type=\"text/javascript\">\n";
  echo "window.location = \"manage_class.php?pid=$pid&action=manage\"";
  echo "</script>";
}
else if ($action == "create")
{
    $query_result = DBQueryFatal("select attrvalue from project_attributes ".
                 "where pid ='$pid' and attrkey='setup_done'");
    if (mysql_num_rows($query_result) == 0)
       USERERROR("You must set up your class before enrolling students or TAs"); 


    $classends = "";
    $query_result = DBQueryFatal("select attrvalue from project_attributes ".
                    "where pid ='$pid' and attrkey='class_ends'");

    $row = mysql_fetch_array($query_result);
    $semesterends = $row['attrvalue'];
    if (isset($semesterends)) 
        $classends = strtotime($semesterends,0);
    $now = time();
    if ($classends < $now)
       USERERROR("You must set up your class before enrolling students or TAs"); 

    echo "<center><H2>Enter the email addresses of students or TAs</H2> ";

    $step2 = "assign";

    echo "<form name=form1 action=\"manage_class.php?pid=$pid&action=$step2\" method=\"post\">\n";
    echo "<table align=center border=1><tr>\n";
    echo "<tr><td>E-mails: <br><small>(one per line)</small></td>\n";
    echo "<td><textarea name=\"students\" rows=\"10\" cols=\"50\"></textarea></td>";
    echo "<td><select name='trust'><option name='localroot' value='localroot'>student</option>
    <option name='grouproot' value='grouproot'>TA</option></select>";

    echo "<tr> <td align=center colspan=3>
          <b><input type=\"submit\"\></td></tr>";
    echo "</table></form>";
}
else if ($action == "assigntostudents")
{
  $query_result = DBQueryFatal("select mid, title, path, type, gid from class_materials where pid='$pid'");
  if (mysql_num_rows($query_result) == 0) {
     USERERROR("There are no materials ready to assign. Please use \"Add Materials to Class\" option first.");
  }
  echo "<center><h2>Class materials ready to assign</h2>\n";
  echo "<form name='form2' id='form2' enctype='multipart/form-data' action='assign.php' method='POST'>\n";
  echo "<input type=hidden name=uid value='$uid'>\n";
  echo "<input type=hidden name=pid value='$pid'>\n";
  echo "<input type=hidden name=base value='$base'>\n";
  echo "<TABLE><TH>Select</TH><TH>Title</TH><TH>Type</TH>";
  $i=0;
  while($row = mysql_fetch_array($query_result))
  { 
    $mid = $row['mid'];
    $title = $row['title'];
    $path = $row['path'];
    $type = $row['type'];
    $gid = $row['gid'];
    echo "<TR><TD><input type='checkbox' name='assignmid$mid' value='$title|$path|$type|$mid'></TD><TD><A HREF='file.php?file=$path'>$title</A></TD><TD>$type</TD></TR>\n";
    $i++;
  }
  echo "</TABLE>";
  echo "<BR><TABLE class=stealth><TR><TD>Assign selected materials to: </TD><TD><select onchange='showselected(\"assign\")' name='assignmethod' ID='assignmethod' form='form2'>
  <option value='all'>All students</option>
  <option value='gid'>Group</option> 
  <option value='individual'>Individual students</option>
  </select></TD><TD>
  <select name='assigntogid' form='form2' style='display: none'>\n";
  $sql="select gid from groups where pid='$pid' and gid!='$pid'";
  $query_result = DBQueryFatal($sql);
  while ($row = mysql_fetch_array($query_result)) {
            $gid = $row['gid'];
	    echo "<option>$gid</option>\n";
  }
  echo "</select>";
  echo "<div id='assigntostudents' form='form2' style='display: none'>\n";
  $sql = "select g.uid, u.usr_name,e.usr_email from group_membership g left join users u on g.uid=u.uid left join email_aliases e on g.uid=e.uid where pid='$pid' and trust!='group_root' and g.uid like '%$base%' and u.usr_email not like '%localhost%' order by g.uid";
  $query_result = DBQueryFatal($sql);
  echo "<table class=stealth>";
  while ($row = mysql_fetch_array($query_result)) {
            $uid = $row['uid'];
            echo "<tr><td><input type='checkbox' name='student$uid' value='$uid'>$uid</input></td></tr>\n";
  }
  echo "</table></div></TD></TR></TABLE>";

  $query_result = DBQueryFatal("select attrvalue from project_attributes ".
               "where pid ='$pid' and attrkey='num_students'");
  $row = mysql_fetch_array($query_result);  
  $students = $row['attrvalue'];
  echo "<input type=hidden name=students value=$students>";
  echo "<BR><TABLE class=stealth><TH>Due date</TH><TH>Machines per student</TH><TH>Class limit</TH>";
  echo "<TR><TD class=stealth><input type='date' name='duedate' placeholder='yyyy-mm-dd'></td><TD><input type='text' name='perstudent[0]' onchange='updatelimit(0)'></TD><TD><input type='text' name='limit[0]'></tr></table>";
  echo "<P><button onclick='javascript: document.form2.submit()' style='font: bold 14px Arial' type='button'>Assign</button></table></form><br><br>\n";
  echo "</center></form>";
}
else if ($action == "modifymaterials")
{
   $todelete = array();
   $tomodify = array();
   $oldgids = array();
   foreach ($_POST as $key => $value)
   {
     if (preg_match("/^material/", $key))
     {
      $mid = substr($key, 8);     
      $todelete[$mid] = $value;
      $query_result = DBQueryFatal("select gid from class_materials where mid='$mid'");
      $row = mysql_fetch_array($query_result);
      $oldgids[$mid] = $row['gid'];
     }
    if (preg_match("/^visible/", $key))
    { 
      $mid = substr($key, 7);     
      $query_result = DBQueryFatal("select gid, path from class_materials where mid='$mid'");
      if (mysql_num_rows($query_result) == 1)
      {
         $row = mysql_fetch_array($query_result);
	 $visible = $row['gid'];
	 $opath = $row['path'];
	 if ($visible != $value)
	 {
	    $tomodify[$mid] = $value;
  	    $oldgids[$mid] = $visible;	
	    $oldpaths[$mid] = $opath;
	 }
      }
     }
   }
   if (!empty($todelete) && !isset($confirmed) && !isset($canceled)) {
   	echo "<center><h2><br>
          Are you sure you want to delete these materials? <BR><BR>";
	 foreach ($todelete as $td => $title)
	 {
		echo "<FONT COLOR='blue'>$title</FONT><BR>";
	 }
	  echo "<BR>This will delete any assignments that rely on these materials<BR>
	  and any submissions for these assignments.
          </h2>\n";
    
         echo "<form action='manage_class.php?pid=$pid&action=modifymaterials'
                method=post>";

     	foreach ($_POST as $key => $value)
        {
	   echo "<INPUT type=hidden name='$key' value='$value'></INPUT>";
        }

        echo "<b><input type=submit name=confirmed value=Confirm></b>\n";
        echo "<b><input type=submit name=canceled value=Cancel></b>\n";
        echo "</form>\n";
    }
    else 
    {
      if (!empty($todelete) && isset($confirmed))
      {
	foreach ($todelete as $td => $title)
	{
	  $query_result = DBQueryFatal("delete from class_materials where mid='$td'");	
	  $query_result = DBQueryFatal("delete from class_assignments where mid='$td'");		
	  STARTBUSY("Deleting materials and assignments");
	  $filepath = "/groups/" . $pid . "/" . $oldgids[$td] . "/" . $td;
	  $retval = SUEXEC($uid, "www", "webrmfile $filepath", SUEXEC_ACTION_IGNORE);
	  $filepath = "/groups/" . $pid . "/teachers/submissions/" . $td . "/*";
	  $retval = SUEXEC($uid, "www", "webrmfile $filepath", SUEXEC_ACTION_IGNORE);
	  STOPBUSY();
	}
       }
      if (!empty($tomodify))
      {
	STARTBUSY("Changing the visibility");
     
	foreach ($tomodify as $mid => $gid)
	{
	   if ($gid == "all")
	      $gid = $pid;
	   if ($oldgids[$mid] == "all")
	      $oldgids[$mid] = $pid;
           if (preg_match("/^\/groups\//", $oldpaths[$mid]))
	   {
	    $new = "/groups/" . $pid . "/" . $gid . "/" . $mid;
	    $old = "/groups/" . $pid . "/" . $oldgids[$mid] . "/" . $mid;

   	    if ($old != $new)
            {
                $query_result = DBQueryFatal("update class_materials set gid='$gid',path='$new' where mid='$mid'");
                $group = Group::LookupByPidGid($pid, $gid);
                $unix_name = $group->unix_name();

                STARTBUSY("Changing the visibility");

                $retval = SUEXEC($uid, $unix_name, "webcpfile $old $new 1 > /tmp/log", SUEXEC_ACTION_IGNORE);
                STOPBUSY();
            }
	 }
	 else
	 {
	   $query_result = DBQueryFatal("update class_materials set gid='$gid' where mid='$mid'");
	 }
	}
	STOPBUSY();
     }

    echo "<script type=\"text/javascript\">\n";
    echo "<!--\nwindow.location = \"manage_class.php?pid=$pid&action=managematerials\"\n//-->\n";
    echo "</script>";
   }
}
else if ($action == "managematerials")
{
  echo "<center><h2>Available materials in $pid</h2>\n";
  $query_result = DBQueryFatal("select mid, title, path, type, gid from class_materials where pid='$pid'");
  if (mysql_num_rows($query_result) == 0)
     USERERROR("There are no materials in your class.");
  echo "<form name='form2' id='form2' enctype='multipart/form-data' action='manage_class.php?pid=$pid&action=modifymaterials' method='POST'>\n";
  echo "<input type=hidden name=uid value='$uid'>\n";
  echo "<input type=hidden name=pid value='$pid'>\n";
  echo "<TABLE><TH>Delete?</TH><TH>Title</TH><TH>Type</TH><TH>Visible to</TH>";
  $i=0;
  $sql="select gid from groups where pid='$pid' and gid!='$pid'";
  $group_result = DBQueryFatal($sql);
  $groups = array('all');
  while ($row = mysql_fetch_array($group_result)) {
            $gid = $row['gid'];
	    array_push($groups, $gid);
  }	    
  while($row = mysql_fetch_array($query_result))
  { 
    $mid = $row['mid'];
    $title = $row['title'];
    $type = $row['type'];
    $gid = $row['gid'];
    $path = $row['path'];
    echo "<TR><TD><input type='checkbox' name='material$mid' value='$title'></input><TD><A HREF='file.php?file=$path'>$title</A></TD><TD>$type</TD><TD>";
    echo "<select name='visible$mid' form='form2'>\n";
    foreach ($groups as $g)
    {
    	if ($g == $gid || ($gid==$pid && $g == "all"))
	   $isselected = 'selected';
	else
	   $isselected = '';
    	echo "<option value=$g $isselected>$g</option>\n";
    }
    echo "</select></td></tr>";
}
  echo "</TABLE>";
  echo "<br>Select materials to delete. This will also delete any assignments based on these materials, and any submissions for these assignments. You can also change the visibility of materials by selecting the desired settings from drop-down menus.<br><button onclick='javascript: document.form2.submit()' style='font: bold 14px Arial' type='button'>Modify</button></table></form><br><br>\n";
}
else if ($action == "modifyassignments")
{
   $todelete = array();
   $tomodify = array();
   foreach ($_POST as $key => $value)
   {
     if (preg_match("/^assignment/", $key))
     {
      $mid = substr($key, 10);     
      $todelete[$mid] = $value;
     }
    if (preg_match("/^duedate/", $key))
    { 
      $mid = substr($key, 7);     
      $query_result = DBQueryFatal("select distinct(due) from class_assignments where mid='$mid'");
      if (mysql_num_rows($query_result) == 1)
      {
         $row = mysql_fetch_array($query_result);
	 $duedate = $row['due'];
	 if ($duedate != $value)
	    $tomodify[$mid] = $value;
      }
     }
   }
   if (!empty($todelete) && !isset($confirmed) && !isset($canceled)) {
   	echo "<center><h2><br>
          Are you sure you want to delete these assignments? <BR><BR>";
	  foreach ($todelete as $td => $title)
	  {
		echo "<FONT COLOR='blue'>$title</FONT><BR>";
	  }
	  echo "<BR>This will delete any submissions for these assignments.
          </h2>\n";
    
         echo "<form action='manage_class.php?pid=$pid&action=modifyassignments'
                method=post>";

     	foreach ($_POST as $key => $value)
        {
	   echo "<INPUT type=hidden name='$key' value='$value'></INPUT>";
        }

        echo "<b><input type=submit name=confirmed value=Confirm></b>\n";
        echo "<b><input type=submit name=canceled value=Cancel></b>\n";
        echo "</form>\n";        
    }
    else 
   {
     if (!empty($todelete) && isset($confirmed))
     {
	foreach ($todelete as $td => $title)
	{
	  $query_result = DBQueryFatal("delete from class_assignments where mid='$td'");
	  $filepath = "/groups/" . $pid . "/teachers/submissions/" . $td  . "/*";
	  STARTBUSY("Deleting assignments");
	  $retval = SUEXEC($uid, "www", "webrmfile $filepath", SUEXEC_ACTION_IGNORE);
	  STOPBUSY();
        }
      }
      if (!empty($tomodify))
      {
	foreach ($tomodify as $mid => $duedate)	
	   $query_result = DBQueryFatal("update class_assignments set due='$duedate'  where mid='$mid'");

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
	       $sql = "update class_schedule set assigned=from_unixtime($assigned_a), due='$due_a', perstudent='$perstudent_a', classlimit='$classlimit_a' where pid='$pid' and assigned='$assigned_s' and due='$due_s' and perstudent='$perstudent_s' and classlimit='$classlimit_s'";
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
      }
     echo "<script type=\"text/javascript\">\n";
     echo "<!--\nwindow.location = \"manage_class.php?pid=$pid&action=manageassignments\"\n//-->\n";
     echo "</script>";
  }

}
else if ($action == "viewprogress")
{
  $mid = $_POST['mid'];
  $sql = "select title from class_materials where mid='$mid'";

  $query_result = DBQueryFatal($sql);  
  $row=mysql_fetch_array($query_result);
  $title = $row['title'];
  echo "<center><h2>Progress on assignment $title</h2>\n";
  echo "<table><th>Username</th><th>Name</th><th>State</th>";
  $sql = "select u.uid, usr_name, state from users u join class_assignments a on u.uid=a.uid where mid='$mid'";
  $query_result = DBQueryFatal($sql);
  while($row=mysql_fetch_array($query_result))
  {
	$uid = $row['uid'];
	$name = $row['usr_name'];
	$state = $row['state'];
	echo "<tr><td>$uid</td><td>$name</td><td>$state</td></tr>";
  }
  echo "</table>";
}
else if ($action == "manageassignments")
{
  echo "<center><h2>Available assignments in $pid</h2>\n";
  $query_result = DBQueryFatal("select distinct(m.mid), m.title, m.type, m.path, a.due from class_materials m join class_assignments a on m.mid=a.mid where m.pid='$pid'");
  if (mysql_num_rows($query_result) == 0)
     USERERROR("There are no assignments in your class.");
  echo "<form name='form2' id='form2' enctype='multipart/form-data' action='manage_class.php?pid=$pid&action=modifyassignments' method='POST'>\n";
  echo "<input type=hidden name=uid value='$uid'>\n";
  echo "<input type=hidden name=pid value='$pid'>\n";
  echo "<TABLE><TH>Delete?</TH><TH>Title</TH><TH>Type</TH><TH>Due</TH><TH>View progress</TH><TH>Grade</TH>";
  while($row = mysql_fetch_array($query_result))
  { 
    $mid = $row['mid'];
    $title = $row['title'];
    $type = $row['type'];
    $due = $row['due'];
    $path = $row['path'];
    echo "<TR><TD><input type='checkbox' name='assignment$mid' value='$title'></input><TD><A HREF='file.php?file=$path'>$title</A></TD><TD>$type</TD><TD><INPUT type=date name='duedate$mid' value=$due placeholder='yyyy-mm-dd'></INPUT></TD><TD><button onclick='javascript: passtophp({page: \"manage_class.php\", action: \"viewprogress\", pid: \"$pid\", mid: $mid})' style='font: bold 14px Arial' type='button' name='progress$mid'>View progress</button></TD><TD><button onclick='javascript: passtophp({page: \"download.php\", pid: \"$pid\", mid: $mid})'
 style='font: bold 14px Arial' type='button' name='button$mid'>Download</button></TD></TR>";
}
  echo "</TABLE>";
  echo "<br><br>Select assignments to delete. This will also delete existing submissions for these assignments. <BR>You can also change the due date for any assignment.<br><br><button onclick='javascript: document.form2.submit()' style='font: bold 14px Arial' type='button'>Modify</button></table></form><br><br>\n";
}
else if ($action == "addmaterials")
{
  echo "<center><h2>Add materials to $pid</h2>\n";
  $type_result = DBQueryFatal("show columns in shared_materials where Field='type'");

  $typerow = mysql_fetch_array($type_result);
  $result = $typerow["Type"];
  $result = str_replace(array("enum('", "')", "''"), array('', '', "'"), $result);
  $arr = explode("','", $result);

  echo "<form name='form2' id='form2' enctype='multipart/form-data' action='add_to_class.php' method='POST'>\n";
  echo "<table class=stealth width='80%'>";
  echo "<input type=hidden name=uid value=$uid>";
  echo "<input type=hidden name=pid value=$pid>";
  echo "<tr><td class=color align='right' width='20%'>Title:</td><td class=color align='left'><input type='text' size='100'  value='' name='sharedtitle'></td></tr>\n";
  echo "<tr><td class=color align='right'>Materials to add:</td><td class=color valign='top' align='left'><select onchange='showselected(\"adopt\")' name='sharedmethod' ID='sharedmethod' form='form2'>
  <option value='zip' selected>Zip</option>
  <option value='url'>URL</option> 
  </select>";
  echo "<FONT SIZE='-1'>Zip files must contain non-empty index.html in the base folder</FONT>
  <input id='filetoshare' name='filetoshare' type='file'/>\n
  <input type='text' value='' size='50'  name='sharedpath' style='display: none'>\n
  <input type='text' value='' size='50'  name='sharedURL' style='display: none'> </td></tr>";

  echo "<tr><td class=color align='right'>Type:</td><td class=color>\n";
  foreach ($arr as $value) {
	echo "<input type=\"radio\" name=\"sharedtype\" value=\"$value\">$value\n";
	}
  echo "</td></tr>";
  echo "<tr><td class=color align='right'>Visible to:</td><td class=color align='left'>
  <select onchange='showselected(\"adopt\")' name='adoptmethod' ID='adoptmethod' form='form2'>
  <option value='all'>All students</option>
  <option value='gid'>Group</option> 
  </select>
  <select name='adopttogid' form='form2' style='display: none'>\n";
  $sql="select gid from groups where pid='$pid' and gid!='$pid'";
  $query_result = DBQueryFatal($sql);
  while ($row = mysql_fetch_array($query_result)) {
            $gid = $row['gid'];
	    echo "<option>$gid</option>\n";
  }
  echo "</select></td></tr>";

  echo "<tr><td colspan=2 align='center'><br><button onclick='javascript: document.form2.submit()' style='font: bold 14px Arial' type='button'>Add</button></table></form><br><br>\n";
}
else if ($action == "recycleone" || $action == "resetpwd" || $action == "unfreeze" || $action == "setincomplete" || $action == "rekeyone")
{
     # Collect usernames
     $users = array();
     $userstr = "";
     $emailstr = "";
     foreach ($_POST as $key => $value)
     {
        if (preg_match("/^studentuid/", $key))
	{
	   $pieces = explode("|", $value);
      	   $user=$pieces[0];
      	   $email=$pieces[1];
	   array_push($users, $user);
	   $userstr .= $user . "\n";
	   $emailstr .= $email . "\n";
        }
     }
     if ($action == "resetpwd")
     {
	 file_put_contents('/tmp/' . $pid . '.accounts', $userstr);

         $user = getenv("USER");

         # Run as user so we have the privileges we need
         putenv("USER=$uid");
         # Execute the instacct command
	 shell_exec("/usr/testbed/sbin/instacct $pid pwdreset /tmp/$pid.accounts >> /tmp/$pid.log &");
   
         # Return the USER variable to what it was before and assume this has all worked out
         putenv("USER=$user");
	 echo "<CENTER><BR><BR>The following users' passwords have been reset:<P><B>$userstr</B>";
	 SUBPAGEEND();
	 PAGEFOOTER();
     }
     else if ($action == "unfreeze")
     {
	foreach ($users as $target_uid)
	{
		$target_user = User::LookupByUid($target_uid);
		$target_user->SetWebFreeze(0);
		echo "User $target_uid has been thawed<BR>";
	}
    }
    else if ($action == "setincomplete")
    {

         file_put_contents('/tmp/' . $pid . '.accounts', $userstr);

         $user = getenv("USER");

         # Run as user so we have the privileges we need
         putenv("USER=$uid");
         # Execute the instacct command
         shell_exec("/usr/testbed/sbin/instacct $pid incompletes /tmp/$pid.accounts >> /tmp/$pid.log &");

         # Return the USER variable to what it was before and assume this has all worked out
         putenv("USER=$user");
         echo "<CENTER><BR><BR>The following users' have been granted incompletes:<P><B>$userstr</B>";
         SUBPAGEEND();
         PAGEFOOTER();
    }
    else if ($action == "recycleone" || $action == "rekeyone")
    {
	if ($action == "recycleone" && !isset($confirmed) && !isset($canceled)) {
   	echo "<center><h2><br>
          Are you sure you want to recycle accounts for these users? <P>$userstr
          </h2>\n";
    
         echo "<form action='manage_class.php?pid=$pid&action=recycleone'
                method=post>";

     	foreach ($_POST as $key => $value)
        {
	  if (preg_match("/^studentuid/", $key))
	   echo "<INPUT type=hidden name='$key' value='$value'></INPUT>";
        }

        echo "<b><input type=submit name=confirmed value=Confirm></b>\n";
        echo "<b><input type=submit name=canceled value=Cancel></b>\n";
        echo "</form>\n";
        }
	else if (isset($confirmed) || $action == "rekeyone")
	{	
	   $user = getenv("USER");

           # Run as user so we have the privileges we need
           putenv("USER=$uid");

	  foreach ($users as $user)
          {
         	# Execute the instacct command
		if ($action == "recycleone")
         	   shell_exec("/usr/testbed/sbin/instacct $pid recycle_one $user >> /tmp/$pid.log &");
		else
		   shell_exec("/usr/testbed/sbin/instacct $pid rekey_one $user >> /tmp/$pid.log &");
 	  }
         
   	 # Return the USER variable to what it was before and assume this has all worked out
         putenv("USER=$user");
	 if ($action == "recycleone")	
             echo "<CENTER><BR><BR>The following user accounts have been recycled:<P><B>$userstr</B>";
	 else 
	     echo "<CENTER><BR><BR>The following user accounts have been rekeyed:<P><B>$userstr</B>";
        }
	else if(isset($canceled))
	{
		echo "<script type=\"text/javascript\">\n";
     		echo "<!--\nwindow.location = \"manage_class.php?pid=$pid&action=manage\"\n//-->\n";
     		echo "</script>";
	}
    }   
}
else if ($action == "assign")
{
        $sem = $_POST['students'];
	$trust = $_POST['trust'];

        # Check that emails are properly formed
        $lines = explode("\r\n", $sem);
        shell_exec("rm /tmp/$pid.accounts");
        shell_exec("rm /tmp/$pid.log");
	$emails = array();

        for ($i=0; $i<count($lines); $i++)	
        {
	   if ($lines[$i] == "") continue;
	   if (!preg_match("/^\s?[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}\s?$/i", $lines[$i]))
      	      USERERROR("Invalid address <font color=\"red\">$lines[$i]</font>.", 1);          
      
      	   # Save addresses in a file   
      	   $output = shell_exec("echo $lines[$i] >> /tmp/$pid.accounts");
	   array_push($emails, $lines[$i]);
        }
 
       $option = "";  
       if ($trust == 'grouproot')
	$option = "--ta";
       else if ($trust == 'user')
	$option = "--user";
   
       $user = getenv("USER");

       # Run as user so we have the privileges we need
       putenv("USER=$uid");
   
       # Execute the command
       STARTBUSY("Your accounts are being created. This takes about 15 seconds per account.");
       shell_exec("/usr/testbed/sbin/instacct $option $pid $action /tmp/$pid.accounts >> /tmp/$pid.log ");
       STOPBUSY();
       # Return the USER variable to what it was before
       putenv("USER=$user");
       

       # If there are any TAs add them to teachers group
       if ($trust == "grouproot")
       {
        $formfields = array();
	foreach ($emails as $e)
	{
	  $sql = "select uid_idx from users u join email_aliases e on u.uid=e.uid where e.usr_email='$e'";	  
	  $query_result = DBQueryFatal($sql);

	  $row = mysql_fetch_array($query_result);
	  $newuididx = $row['uid_idx'];
	  
          $formfields["add_$newuididx"] = "permit";
      	  $formfields["U$newuididx\$\$trust"] = "group_root";
       }

       # Make sure that old members remain
       $sql = "select uid_idx from group_membership gm where gm.gid='teachers' and gm.pid='$pid'";
       $query_result = DBQueryFatal($sql);

       while($row = mysql_fetch_array($query_result))
       {
	  $newuididx = $row['uid_idx'];
          $formfields["change_$newuididx"] = "permit";
      	  $formfields["U$newuididx\$\$trust"] = "group_root";
       }       
       $group = Group::LookupByPidGid($pid,"teachers");
       $errors=array();
       $group->EditGroup($group, $uid, $formfields, $errors);
       }
       # Redirect to class list
       echo "<script type=\"text/javascript\">\n";
       echo "<!--\nwindow.location = \"manage_class.php?pid=$pid&action=manage\"\n//-->\n";
       echo "</script>";
       return;
}
else if ($action == "recycle")
{
    if (!isset($confirmed) && !isset($canceled)) {
    echo "<center><h2><br>
          Are you sure you want to recycle all accounts?
          </h2>\n";
    
    echo "<form action='manage_class.php?pid=$pid&action=recycle'
                method=post>";

    echo "<b><input type=submit name=confirmed value=Confirm></b>\n";
    echo "<b><input type=submit name=canceled value=Cancel></b>\n";
    echo "</form>\n";

    SUBPAGEEND();
    PAGEFOOTER();
    return;
  }
  else if (isset($canceled)) {
     
     # Redirect to class list
     echo "<script type=\"text/javascript\">\n";
     echo "<!--\nwindow.location = \"manage_class.php?pid=$pid&action=manage\"\n//-->\n";
     echo "</script>";
     return;
  }
  else
  {
     shell_exec("rm /tmp/$pid.log");
     $user = getenv("USER");

     # Run as user so we have the privileges we need
     putenv("USER=$uid");
   
     # Execute the command
     STARTBUSY("Your class is being recycled.");
     shell_exec("/usr/testbed/sbin/instacct $pid wipe_all >> /tmp/$pid.log ");
     STOPBUSY();

     # Return the USER variable to what it was before
     putenv("USER=$user");
 
     # Redirect to class list
     echo "<script type=\"text/javascript\">\n";
     echo "<!--\nwindow.location = \"manage_class.php?pid=$pid&action=manage\"\n//-->\n";
     echo "</script>";
     return;
   }
}
SUBPAGEEND();
PAGEFOOTER();
?>
