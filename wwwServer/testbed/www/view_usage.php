<?php

#
# EMULAB-COPYRIGHT
# Copyright (c) 2000-2006 University of Utah and the Flux Group.
# All rights reserved.
#

include("defs.php");

#
# Only admins
#

#$this_user = CheckLoginOrDie();
#$isadmin = ISADMIN();

#if (!$isadmin)
#    UserError("Must be admin");

ini_set('max_execution_time', 90);

# see whether to only display rows which have a null value
$optargs = OptionalPageArguments("rtype", PAGEARG_BOOLEAN);
$add ="";
$cond = "";
if (isset($optargs['rtype']))
{
   $rtype = $optargs['rtype']; 
   if ($rtype == "Class")
   {
     $add = ", projects c";
     $cond = "c.research_type = 'Class' and c.pid = e.pid and ";
   }
   else
   if ($rtype == "Internal")
   {
     $add = ", projects c";
     $cond = "c.research_type = 'Internal' and c.pid = e.pid and ";
   }
   else
   if ($rtype == "Research")
   {
     $add = ", projects c";
     $cond = "c.research_type != 'Class' and c.research_type != 'Internal' and c.pid = e.pid and ";
   }
}

#
# Standard Testbed Header
#
PAGEHEADER("View DETER Usage Statistics");

# Get begin and end time for statistics
$r1 = DBQueryFatal(
    'select unix_timestamp(min(start_time)), unix_timestamp(max(end_time)) from testbed_stats'
    );

$row = mysql_fetch_row($r1);

define ('SECSINDAY',86400);

# If we did not record an experiment's swapout assume it lasted 1 hour
define ('FEDURATION',3600);
       
$begin = $row[0];
$end = $row[1];
?>

<script type='text/javascript' src='https://www.google.com/jsapi'></script>
    <script type='text/javascript'>
    	     google.load("visualization", "1", {packages:["corechart"]});
      google.setOnLoadCallback(drawCharts);
      function drawCharts()
      {
	drawChart1();
      }

      function drawChart1() {
        var data = new google.visualization.arrayToDataTable([
	['Date','Machines used','Projects active', 'Capacity'],
    
<?php

$experiments = array();
$projects = array();
$usemtrx = array();
$totum = array();
$liveprojs = array();
$prjs = array();
$totnodes = 0;
$totprj = 0;
$lasttime = $begin;

# How many machines were there in DETER at the time. 
function capacity($key)
{
   if ($key <= 1123804761)
      $c = 64;
   else if ($key <= 1124226783)
      $c = 128;
   else if ($key <= 1144290296)
      $c = 190;
   else if ($key <= 1144361401)
      $c = 254;
   else if ($key <= 1144698241)
      $c = 286;
   else if ($key <= 1196210748)
      $c = 300;
   else if ($key <= 1201507967)
      $c = 331;
   else if ($key <= 1222210956)
      $c = 330;
   else
      $c = 393;  
   return $c;
}

# How many nodes are currently in use 
function updatetotnodes($etime, $nodes, $pid)
{
  global $usemtrx, $totnodes, $lasttime, $totum, $prjs, $projects, $liveprojs;
  if ($projects[$pid]->nodes > 0)
  {
   if (!array_key_exists($pid,$liveprojs))
	$liveprojs[$pid] = 1;	
  }
  else
	unset($liveprojs[$pid]);
   $lasttime = $etime;
   $totnodes += $nodes;
   $totum[$etime] = $totnodes;
   $c = capacity($etime);
   $prjs[$etime] = count($liveprojs);
}

# Translate a Unix timestamp into m/d/y format
function getday($epoch)
{
  return date("m/d/y",$epoch);
}

# Store usage data per project
class RProject{
  var $pid, $nodes, $lasttime;
  
  function __construct($pid, $when)
  {
     $this->pid = $pid;
     $this->nodes = 0;
     $this->lasttime = 0;
  }

  # Update usage data on new event
  # Each cell in matrix holds number of machine-days
  # So if one machine is used for 12 hours the cell value
  # will be 0.5
  function updatenodes($etime, $nodes, $usemtrx)
  {
	$pid = $this->pid;
	$day = getday($etime);
	if ($this->nodes > 0)
	{
	  $n = $this->nodes;
	  if (getday($etime) == getday($this->lasttime))
	  {
	    $counter = getday($etime);
	    $n =  ceil(($etime - $this->lasttime)/SECSINDAY*$n); 
	    if (array_key_exists($pid, $usemtrx[$counter]))
	    	$usemtrx[$counter][$pid] += $n;
	    else
	        $usemtrx[$counter][$pid] = $n;	      
          }
	  else
	  {
		for($c = $this->lasttime; $c < $etime; $c+=SECSINDAY)
	  	{
			$counter = getday($c);
	      		$n = $this->nodes;
	      		if ($c == $this->lasttime)
			   $n =  ceil((SECSINDAY - (($c-8*3600) % SECSINDAY))/SECSINDAY * $n);
			if (array_key_exists($pid, $usemtrx[$counter]))
			   $usemtrx[$counter][$pid] += $n;
	      		else
	         	   $usemtrx[$counter][$pid] = $n;	 
          	}
	    	$counter = getday($etime);
	   	$n = ceil((($etime-8*3600) % SECSINDAY)/SECSINDAY * $this->nodes);
           	if (array_key_exists($pid, $usemtrx[$counter]))
	       	   $usemtrx[$counter][$pid] += $n;
	   	else
		   $usemtrx[$counter][$pid] = $n;
	   }
	}
	$this->lasttime = $etime;
	$this->nodes += $nodes;
  }
}

# Store data about an experiment's usage of resources
class RExperiment{
  var $eid, $pid, $nodes, $when, $state;

  function __construct($eid, $pid, $state) {
    $this->eid = $eid;
    $this->pid = $pid;
    $this->nodes = 0;
    $this->state = $state;
  }

  function reserve($when, $nodes)
  {
   $this->nodes = $nodes;
   $this->when = $when;
  }

  function release($when)
  {
    $this->nodes = 0;
    $this->when = $when;
  }

  function getnodes()
  {
    return $this->nodes;
  }
}

# An event class, mostly there so we could create 
# swapout events if we missed them in the DB (such
# as it happens during a forcible swapout)
class Revent{
  var $when, $pid, $eid, $action;

  function makeFakeEvent($when, $pid, $eid, $action)
  {
    $this->when = $when;
    $this->pid = $pid;
    $this->eid = $eid;
    $this->action = $action;
  }
}

# Row counter for days, and a matrix that holds usage per 
# project per day
for($c = $begin; $c <= $end+SECSINDAY; $c+=SECSINDAY)
   {
    $counter = getday($c);
    $usemtrx[$counter]=array();
    }


$olde = NULL;
$oldstate = "";
$oldtime = 0;
$fakeevents = array();

# Load testbed events in, order them by experiment ID and start time
# The goal of this phase is just to look for and fix inconsistencies in the DB


  $result = DBQueryFatal(
    'select unix_timestamp(start_time), unix_timestamp(end_time), eid, state, t.exptidx, e.pid,'.
    ' r.pnodes, action, uid, exitcode from testbed_stats t, experiments e, '.
    ' experiment_resources r ' . $add . 
    '  where start_time is not null and t.exptidx = e.idx and ' .
    $cond .
    't.rsrcidx = r.idx and r.pnodes > 0 and exitcode = 0 order by t.exptidx, start_time'
    );

while ( $row = mysql_fetch_row($result)) {
      $stime = $row[0];
      $etime = $row[1];	
      $eid = $row[2];
      $state = $row[3];
      $eidx = $row[4];
      $pid = $row[5];
      $nodes = $row[6];
      $action = $row[7];
      $uid = $row[8];

      # Create new project entry if needed
      if (!array_key_exists($pid, $projects))
       {
      	$projects[$pid] = new RProject($pid, $stime);	
       }

      # Create new experiment entry if needed
      if (!array_key_exists($pid.$eid, $experiments)) 
       {
         # Insert a fake event if needed, events are sorted by expid and projid
	 # so if we came to the end of an experiment and it still has some nodes
	 # reserved but its current state is not active, create a fake "swapout"
         if (isset($olde) && $olde->nodes != 0 && $oldstate != 'active')
	 {
	   $n = $olde->nodes;
	   $oldpid = $olde->pid;
	   $oldeid = $olde->eid;
	   $fe = new Revent();	
	   $fe->makeFakeEvent($oldtime+FEDURATION, $oldpid, $oldeid, 'swapout');
	   array_push($fakeevents, $fe);
	 }      
         $experiments[$pid.$eid] = new RExperiment($eid, $pid, $state);
	 $e = &$experiments[$pid.$eid];
	 # $e->__construct($eid, $pid, $state);
     }
     $e = &$experiments[$pid.$eid];
     $olde = &$e;
     $oldstate = $state;
     $oldtime = $stime;

     # Update node reservations given an event
     if ($action == 'swapin' || $action == 'start')
     {
       if ($e->nodes == 0)
           $e->reserve($stime, $nodes);
     } 
    else if ($action == 'swapmod')
     {
       if ($e->getnodes() != $nodes && $e->getnodes() != 0)
        {
       	   $e->release($etime);
           $e->reserve($etime, $nodes);
        }
     }
    else if ($action == 'swapout')
    {
	$e->release($etime);
    }
  }

# Check if we need a fake event for the last entry
if (isset($olde) && $olde->nodes != 0 && $oldstate != 'active')
{
    $n = $olde->nodes;
    $oldpid = $olde->pid;
    $oldeid = $olde->eid;
    $fe = new Revent();
    $fe->makeFakeEvent($oldtime, $oldpid, $oldeid, 'swapout');
    array_push($fakeevents, $fe);
}

# Cleanup experiment state
foreach ($experiments as $key => $value)
{
  $experiments[$key]->nodes = 0;
}


# Now do this all again, this time insert fake events
# and collect stats, and periodically clean up memory
# Events are now ordered by start time

$result = DBQueryFatal(
    'select unix_timestamp(start_time), unix_timestamp(end_time), eid, state, t.exptidx, e.pid,'.
    ' r.pnodes, action, uid, exitcode from testbed_stats t, experiments e, '.
    ' experiment_resources r ' . $add . 
    '  where start_time is not null and t.exptidx = e.idx and ' .
    $cond .
    't.rsrcidx = r.idx and r.pnodes > 0 and exitcode = 0 order by start_time'
    );

while ( $row = mysql_fetch_row($result)) {
      $stime = $row[0];
      $etime = $row[1];	
      $eid = $row[2];
      $state = $row[3];
      $eidx = $row[4];
      $pid = $row[5];
      $nodes = $row[6];
      $action = $row[7];
      $uid = $row[8];

      # Check if we should handle a fake event
      foreach ($fakeevents as $key => $value)
      {
        $fe = &$fakeevents[$key];
	$fewhen = $fe->when;
        if ($fe->when < $stime)
	{
	  $n = $experiments[$fe->pid.$fe->eid]->nodes;
	  $experiments[$fe->pid.$fe->eid]->release($fe->when);
	  $projects[$fe->pid]->updatenodes($fe->when, -$n, $usemtrx);
	  updatetotnodes($fe->when, -$n, $fe->pid);
	  unset($fakeevents[$key]);
	 }
      }

      $e = &$experiments[$pid.$eid];

      # Handle node reservations
      if ($action == 'swapin' || $action == 'start')
      {
       $n = $e->nodes;
       
       if ($e->nodes == 0)
       {
	$e->reserve($stime, $nodes);
       	$projects[$pid]->updatenodes($stime,$nodes,$usemtrx);
	updatetotnodes($stime,$nodes, $pid);
	$nn = $experiments[$pid.$eid]->nodes;
       }
     } 
    else if ($action == 'swapmod')
     {
       if ($e->getnodes() != $nodes && $e->getnodes() != 0)
        {
           $projects[$pid]->updatenodes($stime,$nodes-$e->getnodes(),$usemtrx);
	   updatetotnodes($stime,$nodes-$e->getnodes(), $pid);
       	   $e->release($etime);
           $e->reserve($etime, $nodes);
        }
     }
    else if ($action == 'swapout')
    {
        $projects[$pid]->updatenodes($etime,-$e->getnodes(),$usemtrx);
	updatetotnodes($etime,-$e->getnodes(), $pid);
	$e->release($etime);
    }
  }

 # Artificially touch resources in all projects 
 # so we could update the use matrix in the end
 foreach ($projects as $key => $value)
 {
   $projects[$key]->updatenodes($end, 0, $usemtrx);
 }

 $first = 1;
 foreach ($totum as $key => $value)
 {
   # Just do this for a quarter, even though we have more data
   # since otherwise drawing gets too slow.
   # Draw numbers of machines, projects and total capacity.
   # This is all scaled pretty strangely by Google maps
   if ($key < $end - 100*SECSINDAY)
      continue;
   $p = $prjs[$key];
   $c = capacity($key);
   if ($first)	
      print "[new Date($key*1000), $value, $p, $c]";
   else
      print ",\n [new Date($key*1000), $value, $p, $c]";
   $first = 0;
 }
print"\n]);";
?>

var options = {
   title: 'Testbed Utilization'
  };
 
  var chart = new google.visualization.LineChart(document.getElementById('chart_div1'));
        chart.draw(data, options);       
}

      function drawChart2() {
        var data = new google.visualization.DataTable();
        data.addColumn('datetime', 'Date');
        data.addColumn('number', 'Utilization');
	data.addRows([
<?php

 $first = 1;
 foreach ($totum as $key => $value)
 {
   # Just do this for a year. Repeat drawing for utilization.
   # We could've done it all on the same image but it scales
   # really weird so we're doing it separately.
   if ($key < $end - 100*SECSINDAY)
      continue;
   $p = $prjs[$key];
   $c = capacity($key);
   if ($value > $c)
      $value = $c;
   if ($first)	
      print "[new Date($key*1000), $value/$c]";
   else
      print ",\n [new Date($key*1000), $value/$c]";
   $first = 0;
 }
print"\n]);";

?>

var options = {
   title: 'Testbed Utilization'
  };

  var chart = new google.visualization.LineChart(document.getElementById('chart_div2'));
        chart.draw(data, options);
}

<?php

# Calculate usage statistics in past 100 days, quarter, month and week, roughly
$thresholds=array(100, 90, 30, 7);
$ut= array();
$pr= array();
$m= array();

# Initialize stats to zero
foreach ($thresholds as $k => $v)
{
    $ut[$v] = 0;
    $pr[$v] = 0;
    $m[$v] = 0;
}

# Calculate statistics
foreach ($totum as $key => $value)
 {
   $p = $prjs[$key];
   $c = capacity($key);
   if ($value > $c)
      $value = $c;
   foreach ($thresholds as $k => $v)
   {
	if ($key > $end - $v*SECSINDAY)
	   {
		if($lastkey > $end - $v*SECSINDAY)
	   		    $l = $lastkey;
		else 	    
			    $l = $end - $v*SECSINDAY;
		$ut[$v] = $ut[$v]+($key-$l)/($v*SECSINDAY)*($value/$c);   
		$pr[$v] = $pr[$v]+($key-$l)/($v*SECSINDAY)*$p;   
		$m[$v] = $m[$v]+($key-$l)/($v*SECSINDAY)*$value;
	   }
    }
    $lastkey = $key;
}
?>

      </script>

    <H3>Machines used, number of active projects and capacity</H3><BR>
<?php
 # Display statistics
 foreach ($thresholds as $k => $v)
 {
	$p = round($pr[$v],2);
	$ma = round($m[$v],2);
 	print "Average machines used in the past $v days are $ma, by $p projects<BR>\n";
 }
?>
    <div id='chart_div1' style='width: 700px; height: 240px;'></div>
     <H3>Utilization</H3><BR>
<?php
 foreach ($thresholds as $k => $v)
 {
	$u = round($ut[$v],2);
 	print "Average utilization in the past $v days is $u<BR>\n";
 }
?>

    <div id='chart_div2' style='width: 700px; height: 240px;'></div><BR>





<?php
#
# Standard Testbed Footer
# 
PAGEFOOTER();
?>
