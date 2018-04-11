<?php
#
# EMULAB-COPYRIGHT
# Copyright (c) 2000-2008 University of Utah and the Flux Group.
# All rights reserved.
#

include("defs.php");

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

$optargs  = OptionalPageArguments("pid", PAGEARG_PROJECT, "gid", PAGEARG_STRING, "month", PAGEARG_NUMERIC, "year",  PAGEARG_NUMERIC);
if (!isset($optargs["pid"]) && !isset($_POST['pid']))
{
    USERERROR("No pid argument given.");
}

if (isset($_POST["project"]))
   $project  = Project::Lookup($_POST["project"]);
else
   $project  = $optargs["pid"];

$pid      = $project->pid();
$pid_idx  = $project->pid_idx();
if (isset($_POST["group"]))
   $group  = Group::LookupByPidGid($pid, $_POST["group"]);
else if (isset($optargs["gid"]))
   $group  = Group::LookupByPidGid($pid, $optargs["gid"]);
else
   $group  = Group::LookupByPidGid($pid, $pid);	
if (!isset($group))
{
	USERERROR("There is no such group in project $pid");
}
$gid      = $group->gid();
$gid_idx  = $group->gid_idx();

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
    PAGEHEADER("Project $pid group $gid");
else
    PAGEHEADER("All projects");

if (!$isadmin)
{
    if (!($this_user && $project->AccessCheck($this_user, $TB_PROJECT_READINFO)))
       USERERROR("You are not a member of project $pid.", 1);
    if (!TBMinTrust(TBGrpTrust($uid, $pid, $gid), $TBDB_TRUST_GROUPROOT))
       USERERROR("You cannot input schedule for project $pid group $gid.", 1);
}

if (isset($pid) && $pid != 'SAFER')
{
   USERERROR("You can only set projections for SAFER project");
}


if(isset($_POST['start']) && isset($_POST['stop']) && isset($_POST['limit']) && isset($pid))
{
    $start = $_POST['start'];
    $stop = $_POST['stop'];
    $limit = $_POST['limit'];
    $time = explode("/", $start);
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
	    USERERROR("Start time $start is in the past.", 1);
	}
	else if ($stop_time < mktime(0,0,0, date('m'), date('j'), date('Y')))
	{
	    USERERROR("End time $stop is in the past.", 1);
	}
	else if (!preg_match("/^\d+$/", $limit))
	{
	    USERERROR("Limit $limit is not numeric.", 1);
	}

	# Find all nodes on the empty testbed

	$query_result = DBQueryFatal(
        "select count(*) as count from nodes n left join node_types as nt on n.type=nt.type " .
	"left join node_type_attributes as na on n.type=na.type where (role='testnode') " .
	"and na.attrkey ='special_hw' and na.attrvalue='0'");
		
       	while ($row = mysql_fetch_array($query_result))
       	{
	    $count = $row['count'];
	}
	$available = $count;


	# Draw the calendar
	$day_start = mktime(0,0,0,$start_month,$start_day,$start_year);
	$day_end = mktime(23,59,59,$start_month,$start_day,$start_year);

	while ($day_start <= $stop_time)
	{
	    $query_result = DBQueryFatal(
       	      'select pid, gid, node_limit from resource_limits ' .
       	      ' where unix_timestamp(time) <= ' . $day_end . 
	      ' and unix_timestamp(time) >= ' . $day_start 
        	);

            $sum = 0;
            while ($row = mysql_fetch_array($query_result))
            {
		$limits[$row['pid'] . "-" . $row['gid']] = $row['node_limit'];
		if ($row['pid'] != $pid || $row['gid'] != $gid)
	 	   $sum += $row['node_limit'];
	    }
	    if ($sum + $limit > $available)
	    {
		$left = $available - $sum;
		USERERROR("Requested $limit machines but on $start_month/$start_day/$start_year only $left are available!", 1);		
	    }

	    # Instead of checking if we need to insert or update, 
	    # delete the existing record if any and then insert
	    $query_result = DBQueryFatal("delete from resource_limits where pid='" . $pid .  "' and gid='" . $gid . "' and time=from_unixtime(" . $day_start . ")");
	    if ($limit > 0)
	    	    $query_result = DBQueryFatal("insert into resource_limits (pid, pid_idx, gid, gid_idx, node_limit, time) values('" . $pid . "','" . $pid_idx . "','" . $gid . "','" . $gid_idx . "','" . $limit . "', from_unixtime(" . $day_start . "))");

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
function draw_calendar($month,$year, $pid, $gid, $available){
   
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
       $calendar .= '<tr><td class="wrapper-cell" valign="top"><a href="setup_safer.php?month=' . $prevmonth . '&year=' . $prevyear;
    else
	$calendar .= '<tr><td class="wrapper-cell" valign="top"><a href="setup_safer.php?pid=' . $pid . '&gid=' . $gid . '&month=' . $prevmonth . '&year=' . $prevyear;

    $calendar .= '"><IMG SRC="arrow-left.jpg" width=20></a></td><td class="wrapper-cell" align="center">';

    echo 'Available machines - limits for all groups';

    if ($pid != "")
    {
	echo ' (limit for ' . $pid . '-' . $gid . ')';
    	$calendar .=  '</td><td class="wrapper-cell" valign="top"><a href="setup_safer.php?pid=' . $pid . '&gid=' . $gid . '&month=' . $nextmonth . '&year=' . $nextyear;
    }
    else
	$calendar .=  '</td><td class="wrapper-cell" valign="top"><a href="setup_safer.php?month=' . $nextmonth . '&year=' . $nextyear;
  
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
       	'select pid, gid, node_limit from resource_limits ' .
       	' where unix_timestamp(time) <= ' . $day_end . ' and unix_timestamp(time) >= ' . $day_start 
        );
	
     	$sum = $available;
     	$mylimit = "";
     	$calendar.= '<td class	="calendar-day" valign="top">';

     	/* add in the day number */
     	$calendar .= '<div class="day-number">' . $list_day . '</div>';
           
     	$admininfo = "<table>";
     
	while ($row = mysql_fetch_array($query_result))
     	{
	    $limits[$row['pid'] . "-" . $row['gid']] = $row['node_limit'];
	    $sum -= $row['node_limit'];
		$limit = $row['node_limit'];	 
	   	if ($limit != 0)
	   	   $admininfo .= "<tr><td valign='top'>" . $row['gid'] . "</td><td>" . $limit . "</td></tr>";		
	    if ($row['pid'] == $pid && $row['gid'] == $gid && $pid != "" && $gid != "" && $row['node_limit'] != 0)
		$mylimit = $row['node_limit'];	 
        }
     	$admininfo .= "</table>";
      	
     	$calendar.= '<table cellpadding="0" cellspacing="0" class="transparent"><tr><td class="transparent-cell" width="50%" valign="top">' . $sum . '</td>';

        $calendar .= "<td>" . $admininfo . "</td>";
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
echo '<P class="ex">Use the table below to input projections for your project\'s use of DeterLab resources.';
   
if (!isset($pid))
   echo "<form name=form1 action=\"setup_safer.php\" method=\"post\">\n";
else
   echo "<form name=form1 action=\"setup_safer.php?pid=$pid\" method=\"post\">\n";
echo "<P class=\"ex\">From date: <input type=text name=start value=mm/dd/yyyy></input>";
echo "To date: <input type=text name=stop value=mm/dd/yyyy></input>";
echo "New limit: <input type=text name=limit></input>";

echo "Project: <input type=text name=project value='SAFER'></input>";
echo "Group: <input type=text name=group value='$gid'></input>";

echo "<input type=\"submit\"></input></form></div>";

echo '<center><h2>' . $months[$month] . ' ' . $year . ' </h2>';

$query_result = DBQueryFatal(
        "select count(*) as count from nodes n left join node_types as nt on n.type=nt.type " .
	"left join node_type_attributes as na on n.type=na.type where (role='testnode') " .
	"and na.attrkey ='special_hw' and na.attrvalue='0'");


while ($row = mysql_fetch_array($query_result))
{
    $count = $row['count'];
}
$available = $count;
if (!isset($pid))
   $pid = "";
echo draw_calendar($month,$year,$pid, $gid, $available);
echo '</center>';

#
# Standard Testbed Footer
# 
PAGEFOOTER();
?>
