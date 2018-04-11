<?php
#
# EMULAB-COPYRIGHT
# Copyright (c) 2000-2008 University of Utah and the Flux Group.
# All rights reserved.
#

include("defs.php");

function mmddyyyyToStamp($input) {
    $time = explode("/", $input);
    $stamp = 0;
    if (count($time) == 3)
    {
	if (!preg_match("/^0[0-9]|10|11|12$/", $time[0])  || !preg_match("/^2\d{3}$/", $time[2]) || !preg_match("/^\d+$/", $time[1]) || $time[1] < 1 || $time[1] > 31)
    	{
	    USERERROR("$input is not a valid date in mm/dd/yyyy format!",1);
    	}

    	$stamp=mktime(0,0,0,$time[0],$time[1],$time[2]); 
    } else {
	USERERROR("$input is not a valid date in mm/dd/yyyy format!",1);
    }
    return($stamp);
}

#
# Only known and logged in users.
#
$this_user = CheckLogin($status);
if ($this_user)
   $uid = $this_user->uid();
$isadmin = ISADMIN();

#
# Verify page arguments.
#

$optargs  = OptionalPageArguments("project", PAGEARG_PROJECT, "month", PAGEARG_NUMERIC, "year",  PAGEARG_NUMERIC, "chore", PAGEARG_STRING);
if (!$isadmin && !isset($optargs["project"]))
{
    USERERROR("No project argument given.");
}

if (!$isadmin || isset($optargs["project"]) || isset($_POST['project']))
{
    if (isset($_POST["project"]))
       $project  = Project::Lookup($_POST["project"]);
    else
	$project  = $optargs["project"];
    $group    = $project->Group();
    $pid      = $project->pid();
    $pid_idx  = $project->pid_idx();
}

if (isset($optargs["month"]) && isset($optargs["year"]))
{
    $month=$optargs["month"];
    if (!preg_match("/^0[0-9]|10|11|12$/", $month))
    {
	USERERROR("Not a valid month $month!",1);
    }
    $year=$optargs["year"];
    if (!preg_match("/^2\d{3}$/", $year))
    {
	USERERROR("Not a valid year $year!",1);
    }
    if ($year < date('Y') || ($month < date('m') && $year == date('Y')))
    {
	USERERROR("Date $month/$year is in the past!", 1); 
    }
}
else
{ 
    $month=date('m');
    $year=date('Y');
}


$months = array(
    "01" => "January",
    "02" => "February",
    "03" => "March",
    "04" => "April",
    "05" => "May",
    "06" => "June",
    "07" => "July",
    "08" => "August",
    "09" => "September",
    "10" => "October",
    "11" => "November",
    "12" => "December"
);

#
# Standard Testbed Header
#
if (isset($pid))
    PAGEHEADER("Class assignment limits for $pid");
else
    PAGEHEADER("Class assignment schedules");
if (!$isadmin)
{
    if (!($this_user && $project->AccessCheck($this_user, $TB_PROJECT_READINFO)))
       USERERROR("You are not a member of project $pid.", 1);
    if (!TBMinTrust(TBGrpTrust($uid, $pid, $pid), $TBDB_TRUST_GROUPROOT))
       USERERROR("You cannot input schedule for project $pid.", 1);
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
    $semesterends = date("m/d/Y",$semesterstamp);
} else {
    $semesterstamp = 0;
    $semesterends = "mm/dd/yyyy";
}

SUBPAGESTART();
SUBMENUSTART("Project Scheduling");
WRITESUBMENUBUTTON("Assignment node limits",
                   "setup_class.php?pid=$pid&chore=limitnodes");
WRITESUBMENUBUTTON("Semester end date",
                   "setup_class.php?pid=$pid&chore=setenddate");
SUBMENUEND();
SUBPAGEEND();


if (isset($_POST['classend'])) {
    $semesterstamp = mmddyyyyToStamp($_POST['classend']);
    $semesterends = date("m/d/Y",$semesterstamp);
    $dbtime = date("Y-m-d H:i:s",$semesterstamp);
    DBQueryFatal("replace into project_attributes values".
	    "('$pid',$pid_idx,'class_ends','$dbtime')");

}

if (isset($optargs['chore']) && ($optargs['chore'] == 'setenddate')) {
    echo "<div><P><form name=form1 ".
       "action=\"setup_class.php?pid=$pid&chore=setenddate\" method=\"post\">\n";
    echo "Semester Ends: <input type=text name=classend value=$semesterends></input>";
    echo "<input type=\"submit\"></input></form></div>";
    goto done;
}
    

if(isset($_POST['start']) && isset($_POST['stop']) && isset($_POST['limit']) && isset($pid))
{
    $start = $_POST['start'];
    $stop = $_POST['stop'];
    $limit = $_POST['limit'];
    $time = explode("/", $start);

    if ($semesterstamp < time()) {
	 $badend ="You may not reserve any machines without specifying ".
	     "a (future) end date";
	 USERERROR($badend,1);
    }
    if (count($time) == 3)
    {
	if (!preg_match("/^0[0-9]|10|11|12$/", $time[0])  || !preg_match("/^2\d{3}$/", $time[2]) || !preg_match("/^\d+$/", $time[1]) || $time[1] < 1 || $time[1] > 31)
    	{
	    USERERROR("Not a valid date in mm/dd/yyyy format $start!",1);
    	}

    	$start_time=mktime(0,0,0,$time[0],$time[1],$time[2]); 
    	$start_month = $time[0];
    	$start_day = $time[1];
    	$start_year = $time[2];
    }
    $time = explode("/", $stop);
    if (count($time) == 3)
    {
	if (!preg_match("/^0[0-9]|10|11|12$/", $time[0])  || !preg_match("/^2\d{3}$/", $time[2]) || !preg_match("/^\d+$/", $time[1]) || $time[1] < 1 || $time[1] > 31)
    	{
	    USERERROR("Not a valid date in mm/dd/yyyy format $stop!",1);
    	}
    	$stop_time=mktime(0,0,0,$time[0],$time[1],$time[2]); 
    }
    if ($start_time && $stop_time)
    {
	if ($start_time > $stop_time)
	{
	    USERERROR("$start is after $stop.", 1);
	}
	else if ($start_time < mktime(0,0,0, date('m'), date('j'), date('Y')))
	{
	    USERERROR("$start is in the past.", 1);
	}
	else if (($start_time < $stop_time) && ($stop_time < time()))
	{
	    USERERROR("$stop is in the past.", 1);
	}
	else if ($semesterstamp < $stop_time) {
	    USERERROR("To date ($stop) is after the class ends.", 1);
	}
	else if (!preg_match("/^\d+$/", $limit))
	{
	    USERERROR("Limit $limit is not numeric.", 1);
	}

	# Find all nodes on the empty testbed
	# assign 2/3 of these as available to classes

	$query_result = DBQueryFatal(
    	      'select count(*) as count from nodes where role=\'testnode\'');
		
       	while ($row = mysql_fetch_array($query_result))
       	{
	    $count = $row['count'];
	}
	$available = intval($count*2/3);


	# Draw the calendar
	$day_start = mktime(0,0,0,$start_month,$start_day,$start_year);
	$day_end = mktime(23,59,59,$start_month,$start_day,$start_year);

	while ($day_start <= $stop_time)
	{
	    $query_result = DBQueryFatal(
       	      'select pid, node_limit from class_resource_limits ' .
       	      ' where unix_timestamp(time) <= ' . $day_end . 
	      ' and unix_timestamp(time) >= ' . $day_start 
        	);

            $sum = 0;
            while ($row = mysql_fetch_array($query_result))
            {
		$limits[$row['pid']] = $row['node_limit'];
		if ($row['pid'] != $pid)
	 	   $sum += $row['node_limit'];
	    }
	    if ($sum + $limit > $available)
	    {
		$left = $available - $sum;
	#	USERERROR("Requested $limit machines but on $start_month/$start_day/$start_year only $left are available!", 1);		
		echo "<p><b>Warning: Requested $limit machines but on $start_month/$start_day/$start_year only $left are available!</b></p>";
	    }

	    # Instead of checking if we need to insert or update, 
	    # delete the existing record if any and then insert
	    $query_result = DBQueryFatal("delete from class_resource_limits where pid='" . $pid . "' and time=from_unixtime(" . $day_start . ")");
	    $query_result = DBQueryFatal("insert into class_resource_limits (pid, pid_idx, node_limit, time) values('" . $pid . "','" . $pid_idx . "','" . $limit . "', from_unixtime(" . $day_start . "))");

	    $day_start = $day_end+1;

	    $start_month = date('m', $day_start);
	    $start_day = date('j', $day_start);
	    $start_year = date('Y', $day_start);
	    $day_start = mktime(0,0,0,$start_month,$start_day,$start_year);
	    $day_end = mktime(23,59,59,$start_month,$start_day,$start_year);
        }
    }	
}

?>

<link rel="stylesheet" type="text/css" charset="utf-8" media="all" 
href="calendar.css">

<?php

/* draws a calendar */
function draw_calendar($month,$year, $pid, $available){
   
   $tmpmonth = $month+1;
   $nextyear = $year;
   if ($tmpmonth > 12)
   {
	$tmpmonth = 1; 
    	$nextyear++;
   }
   $nextmonth=sprintf("%02d", $tmpmonth);
   $tmpmonth = $month-1;
   $prevyear = $year;
   if ($tmpmonth < 1)
   {
	$tmpmonth = 12; 
    	$prevyear--;
   }
   $prevmonth=sprintf("%02d", $tmpmonth);
  
    /* draw table */
    $calendar = '<table cellpadding="0" cellspacing="0" class="wrapper">';
    if ($pid == "") 
       $calendar .= '<tr><td class="wrapper-cell" valign="top"><a href="setup_class.php?month=' . $prevmonth . '&year=' . $prevyear;
    else
	$calendar .= '<tr><td class="wrapper-cell" valign="top"><a href="setup_class.php?pid=' . $pid . '&month=' . $prevmonth . '&year=' . $prevyear;

    $calendar .= '"><IMG SRC="arrow-left.jpg" width=20></a></td><td class="wrapper-cell" align="center">';

    $isadmin = ISADMIN();

    if ($isadmin)    
        echo 'Available class machines - limits for all classes';
    else
        echo 'Available class machines ';

    if ($pid != "")
    {
	echo ' (limit for ' . $pid . ')';
    	$calendar .=  '</td><td class="wrapper-cell" valign="top"><a href="setup_class.php?pid=' . $pid . '&month=' . $nextmonth . '&year=' . $nextyear;
    }
    else
	$calendar .=  '</td><td class="wrapper-cell" valign="top"><a href="setup_class.php?month=' . $nextmonth . '&year=' . $nextyear;
  
    $calendar .= '"><IMG SRC="arrow-right.jpg" width=20></a></td></tr><tr><td class="wrapper-cell"><td class="wrapper-cell">';
    $calendar .= '<table cellpadding="0" cellspacing="0" class="calendar">';

    /* table headings */
    $headings = array('Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday');
    $calendar.= '<tr class="calendar-row"><td class="calendar-day-head">'.implode('</td><td class="calendar-day-head">',$headings).'</td></tr>';

    /* days and weeks vars now ... */
    $running_day = date('w',mktime(0,0,0,$month,1,$year));
    $days_in_month = date('t',mktime(0,0,0,$month,1,$year));
    $days_in_this_week = 1;
    $day_counter = 0;
    $dates_array = array();

    /* row for week one */
    $calendar.= '<tr class="calendar-row">';

    /* print "blank" days until the first of the current week */
    for($x = 0; $x < $running_day; $x++):
    	$calendar.= '<td class="calendar-day-np">&nbsp;</td>';
    	$days_in_this_week++;
    endfor;

    /* keep going with days.... */

    for($list_day = 1; $list_day <= $days_in_month; $list_day++):

        $day_start = mktime(0,0,0,$month,$list_day,$year);
      	$day_end = mktime(23,59,59,$month,$list_day,$year);

      	$query_result = DBQueryFatal(
       	'select pid, node_limit from class_resource_limits ' .
       	' where unix_timestamp(time) <= ' . $day_end . ' and unix_timestamp(time) >= ' . $day_start 
        );

     	$sum = $available;
     	$mylimit = "";
     	$calendar.= '<td class="calendar-day" valign="top">';

     	/* add in the day number */
     	$calendar .= '<div class="day-number">' . $list_day . '</div>';
           
     	$admininfo = "<table>";
     
	while ($row = mysql_fetch_array($query_result))
     	{
	    $limits[$row['pid']] = $row['node_limit'];
	    $sum -= $row['node_limit'];
	    if ($isadmin)
	    {
		$limit = $row['node_limit'];	 
	   	if ($limit != 0)
	   	   $admininfo .= "<tr><td valign='top'>" . $row['pid'] . "</td><td>" . $limit . "</td></tr>";		
	    }
	    if ($row['pid'] == $pid && $pid != "" && $row['node_limit'] != 0)
	    {
		$mylimit = $row['node_limit'];	 
	    }
        }
     	$admininfo .= "</table>";
      	
     	$calendar.= '<table cellpadding="0" cellspacing="0" class="transparent"><tr><td class="transparent-cell" width="50%" valign="top">' . $sum . '</td>';

     	if ($isadmin)
     	{
	    $calendar .= "<td>" . $admininfo . "</td>";
     	}
     	if ($mylimit != "")
     	{
	    $calendar .= '<td class="calendar-day-mine">' . $mylimit . '</td>';
     	}	
      
	$calendar.= '</tr></table></td>';
    	if($running_day == 6):
      	    $calendar.= '</tr>';
      	    if(($day_counter+1) != $days_in_month):
                $calendar.= '<tr class="calendar-row">';
      	    endif;
      	    $running_day = -1;
      	    $days_in_this_week = 0;
    	endif;
    	$days_in_this_week++; $running_day++; $day_counter++;
    endfor;

    /* finish the rest of the days in the week */
    if($days_in_this_week < 8):
        for($x = 1; $x <= (8 - $days_in_this_week); $x++):
      	     $calendar.= '<td class="calendar-day-np">&nbsp;</td>';
    	endfor;
    endif;

    /* final row */
    $calendar.= '</tr>';

    /* end the table */
    $calendar.= '</table>';
    $calendar .= '</td><td class="wrapper-cell"></td></tr></table>';
  
    /* all done, return result */
    return $calendar;
}

echo '<div class="ex">';
if (!$isadmin)
   {
      if ($semesterstamp < time()) {
	  echo '<P class="ex">You <b>must</b> provide the <b>end date</b> '.
		"for your class (and it should be after today).<br>".
		"Please enter this date in the 'Semester Ends' box.</P>";
      }
      echo '<P class="ex">Your class <b>must</b> have a limit before it can allocate any nodes. To input or change a limit for your class (maximum number of machines that can be in use simultaneously by your students) please enter the start and end dates (inclusive) and the limit, and click Submit.';
      echo '<P class="ex">Please choose a reasonable class limit that gives your students sufficient machines without monopolizing resources for others. Here is an example of a resonable limit calculation:
<pre>
   Students in class: 20
   Machines per student: 2
   Max usage: 20*2 = 40
   Limit (1/2*Max for Max < 80, 1/4*Max for Max >= 80): 20
</pre>';
}
   
if (!isset($pid))
   echo "<form name=form1 action=\"setup_class.php\" method=\"post\">\n";
else
   echo "<form name=form1 action=\"setup_class.php?pid=$pid\" method=\"post\">\n";
echo "<P class=\"ex\">Semester Ends: <input type=text name=classend value=$semesterends></input>";
echo "From date: <input type=text name=start value=mm/dd/yyyy></input>";
echo "To date: <input type=text name=stop value=mm/dd/yyyy></input>";
echo "New limit: <input type=text name=limit></input>";
if ($isadmin)
   echo "Project: <input type=text name=project></input>";
echo "<input type=\"submit\"></input></form></div>";

echo '<center><h2>' . $months[$month] . ' ' . $year . ' </h2>';

$query_result = DBQueryFatal(
        'select count(*) as count from nodes where role=\'testnode\'');
		
while ($row = mysql_fetch_array($query_result))
{
    $count = $row['count'];
}
$available = intval($count*2/3);
if (!isset($pid))
   $pid = "";
echo draw_calendar($month,$year,$pid, $available);
echo '</center>';

#
# Standard Testbed Footer
# 
done:
PAGEFOOTER();
?>
