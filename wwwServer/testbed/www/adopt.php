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
	USERERROR("You must be logged on to adopt materials to your class");



# Standard Testbed Header
#
PAGEHEADER("Adopt materials", null, null, null);

$method=trim($_POST["adoptmethod"]);
$class=trim($_POST["adopttoclass"]);
$uid=$this_user->uid();

# If class is selected check that the user is teacher or TA in this class
if ($method == "class")
{
   if (!$this_user->IsTeacherorTA($class))
      USERERROR("You do not have privileges to adopt materials to class $class");
}
else if ($method == "zip")
    $zipstring = "";

# Adopt to class
$i=1;
foreach ($_POST as $key => $value)
{
   if (preg_match("/adopt\d+/", $key))
   { 
      $pieces = explode("|", $value);
      $title=$pieces[0];
      $path=$pieces[1];
      $type=$pieces[2];
      $amid=substr($key, 5);
      if ($type == 'Teacher Manual' && $method == 'class')
      	 USERERROR('Teacher manuals cannot be adopted to a class. They can only be downloaded.');      

      if ($method == "class")
      {
         # Check that we don't duplicate materials
      	 $sql = "select * from class_materials where pid='$class' and title='$title' and type='$type'";
      	 $query_result = DBQueryFatal($sql);      
      	 if (mysql_num_rows($query_result) == 0)
      	 {	      
      	    $sql = "insert into class_materials (uid, pid, gid, title, type) values ('$uid', '$class', 'teachers', '$title', '$type')"; 
      	    $query_result = DBQueryFatal($sql);
	    
	    # Get the mid
	    $sql = "select mid from class_materials where pid='$class' and title='$title' and type='$type'";
	    $query_result = DBQueryFatal($sql);
            $row = mysql_fetch_array($query_result);
	    $mid = $row['mid'];

	    # Copy materials to the right folder if they are on disk
	    if (preg_match("/^\/share\//", $path))
	    {
	    	    $dst = "/groups/" . $class . "/teachers/" . $mid;
        	    $group = Group::LookupByPidGid($class, "teachers");
		    $gid = $group->unix_name();


	    	    $retval = SUEXEC($uid, $gid, "webcpfile $path $dst 0 >> /tmp/log", SUEXEC_ACTION_IGNORE);
	    	    $sql = "update class_materials set path='$dst' where mid='$mid'";
	    	    $query_result = DBQueryFatal($sql);
	    }
	    else
	    {
		    # Update path in DB
	    	    $sql = "update class_materials set path='$path' where mid='$mid'";
	    	    $query_result = DBQueryFatal($sql);
	    }

	    # Update use statistics
	    $sql = "update shared_materials_usage set used=used+1 where mid='$amid'";
	    $query_result = DBQueryFatal($sql);
      	 }
	 
      	 echo "<script type=\"text/javascript\">\n";
      	 echo "window.location = \"manage_class.php?pid=$class&action=assigntostudents\"";
      	 echo "</script>";	    
    }
    else if ($method == "zip")
    {	
     if (preg_match("/^\/share\//", $path) || preg_match("/^\/proj\/teachers\//", $path))
     {
       $zipstring .= "$path ";
       # Update use statistics
       $sql = "update shared_materials_usage set used=used+1 where mid='$amid'";
       $query_result = DBQueryFatal($sql);
     }
    }
  }
}

if ($method == "zip")
{
   if (file_exists(trim($zipstring)))
   {
     header('Set-Cookie: fileLoading=true'); 
     echo "Click <a href='showuser.php'>here</a> to go back";
     echo "<script type=\"text/javascript\">\n";
     echo "window.location = \"download.php?path='$zipstring'\"";
     echo "</script>";
   }
   else
   {
      echo "Something went wrong. Please file a ticket describing your problem and include the exercise you were trying to adopt and the following string $zipstring.";
   }
}

#
# Standard Testbed Footer
# 
PAGEFOOTER();
?>



