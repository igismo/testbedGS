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

$optargs = OptionalPageArguments("shift", PAGEARG_STRING);

# Check the user supplied argument, telling us what time period
# we're looking at
if (!isset($shift) || !is_numeric($shift)) {
    $shift = 0;
}

if ($shift < 0) {
    $shift = 0;
}



#
# Standard Testbed Header
#
PAGEHEADER("View DETER Usage Statistics");

# Select start and end time from DB
$r1 = DBQueryFatal(
    'select unix_timestamp(min(start_time)), unix_timestamp(max(end_time)) from testbed_stats'
    );

$row = mysql_fetch_row($r1);

define ('SECSINDAY',86400);

# If we did not record an experiment's swapout assume it lasted 1 hour
define ('FEDURATION',3600);
       
$begin = $row[0];
$end = $row[1];
$rows = 90;
$erange = $end - $shift*SECSINDAY;
$brange = $erange - ($rows-1)*SECSINDAY;
$bdate = date("m/d/y", $brange);
$edate = date("m/d/y", $erange);
$shiftleft = $shift + $rows;
$shiftright = $shift - $rows;
?>

    
    <script type="text/javascript" src='https://magic-table.googlecode.com/svn/trunk/magic-table/javascript/magic_table.js'></script>
    

    </head>

  <body onload='drawTable();'>
    



	  <div>
<?php
print "<A HREF='http://www.deterlab.net/test_heatmap.php?shift=$shiftleft'><img src='lefta.gif' border=0></a>$rows days prior\n"; 
print "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
print "<A HREF='http://www.deterlab.net/test_heatmap.php?shift=$shiftright'><img src='righta.gif' border=0></a>$rows days later\n<P>";
print "Point a mouse on a cell to read the date (on the y axis), the project name (on the x axis), and number of machine-days (in the cell)";
?>
 </div>    
	  <div id='tableTargetDiv'></div>
	  <div style="width: 600px; height: 100%; float: right"></div>
	  <div style="height: 100%; margin: 50px; text-align: justify;">

	    
	    </div>
	  </div>
	</div>

      </div>
    
    <script type='text/javascript'>
      

      var defaultRowHeight = 25;
      var defaultColumnWidth = 70;
      var tablePositionX = 0;
      var tablePositionY = 0;
      var tableHeight = 700;
      var tableWidth = 1000;
      var rowHeaderCount = 1;
      var columnHeaderCount = 1;

<?php

$experiments = array();
$projects = array();
$projind = array();
$usemtrx = array();

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
	    $n =  (($etime - $this->lasttime)/SECSINDAY*$n); 
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
	      		if ($counter == getday($etime))
	      	 	   break;
	      		$n = $this->nodes;
	      		if ($c == $this->lasttime)
			   $n = ((SECSINDAY - (($c-8*3600) % SECSINDAY))/SECSINDAY * $n);
	      		if (array_key_exists($pid, $usemtrx[$counter]))
	      	 	   $usemtrx[$counter][$pid] += $n;
	      		else
			   $usemtrx[$counter][$pid] = $n;	 
               }
	       $counter = getday($etime);
	       $n = ((($etime-8*3600) % SECSINDAY)/SECSINDAY * $this->nodes);
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


$i = 0;

# Row counter for days, and a matrix that holds usage per 
# project per day
for($c = $begin; $c <= $end+SECSINDAY; $c+=SECSINDAY)
   {
    $counter = getday($c);
    $usemtrx[$counter]=array();
    }


$oldstate = "";
$oldtime = 0;
$fakeevents = array();

# Load testbed events in, order them by experiment ID and start time
# The goal of this phase is just to look for and fix inconsistencies in the DB

$result = DBQueryFatal(
    'select unix_timestamp(start_time), unix_timestamp(end_time), eid, state, t.exptidx, pid,'.
    ' r.pnodes, action, uid, exitcode from testbed_stats t, experiments e, '.
    ' experiment_resources r where start_time is not null and t.exptidx = e.idx and '.
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
	# remember index of this project for drawing
	$projindex[$pid] = count($projects)-1;
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

   $offset = ceil(($end - $begin)/SECSINDAY) - $rows + 1 - $shift;
   print("var rows = $rows + columnHeaderCount;\n");
   $cnt = count($projects);
   print("var columns = $cnt + rowHeaderCount;\n");
?>


      var count = 0;
      var tableModel
      
      function drawTable()
      {
      tableModel = new greg.ross.visualisation.TableModel(rows, columns, defaultRowHeight, defaultColumnWidth, rowHeaderCount, columnHeaderCount);

      setRowHeaders();
      setColumnHeaders();
      drawData();
      
      var targetElement = document.getElementById('tableTargetDiv');
<?php      
      print "var fisheyeTable = new greg.ross.visualisation.FisheyeTable(tableModel, tablePositionX, tablePositionY, 
      tableWidth, tableHeight, \"DETER usage per project from $bdate to $edate\", targetElement);\n";
?>
      }
      
      function setRowHeaders()
      {
      tableModel.setColumnWidth(0, 130);
      var i = rows-1;
      
      tableModel.setContentAt(0, 0, "Date / Project");
      
<?php

# Print out table rows
$i=1;
while ($i <= $rows)
{
	$ar = getdate($begin+($i-1+$offset)*SECSINDAY);
	$y=$ar["year"];
        $m=$ar["mon"];
        $d=$ar["mday"];
	print("tableModel.setContentAt($i, 0, '$m/$d/$y');\n");
	$i=$i+1;
}
?>
}
      
      function setColumnHeaders()
      {
<?php
$i=1;

# Print out table columns
foreach ($projects as $key => $value)
{
	print("tableModel.setContentAt(0, $i, '$key');\n");
	$i=$i+1;
}
?>


      }
     
     function drawData() 
     {
<?php 

# Now do this all again, this time insert fake events
# and collect stats, and periodically clean up memory
# Events are now ordered by start time

$result = DBQueryFatal(
    'select unix_timestamp(start_time), unix_timestamp(end_time), eid, state, t.exptidx, pid,'.
    ' r.pnodes, action, uid, exitcode from testbed_stats t, experiments e, '.
    ' experiment_resources r where start_time is not null and t.exptidx = e.idx and '.
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
               $projects[$pid]->updatenodes($stime,$nodes, $usemtrx);
	$nn = $experiments[$pid.$eid]->nodes;
       }
     } 
    else if ($action == 'swapmod')
     {
       if ($e->getnodes() != $nodes && $e->getnodes() != 0)
        {
           $projects[$pid]->updatenodes($stime,$nodes-$e->getnodes(), $usemtrx);
       	   $e->release($etime);
           $e->reserve($etime, $nodes);
        }
     }
    else if ($action == 'swapout')
    {
        $projects[$pid]->updatenodes($etime,-$e->getnodes(), $usemtrx);
	$e->release($etime);
    }
  }

 # Artificially touch resources in all projects 
 # so we could update the use matrix
 foreach ($projects as $key => $value)
 {
   $projects[$key]->updatenodes($end, 0, $usemtrx);
 }

 # Draw the table
 for($c = $begin; $c <= $end+SECSINDAY; $c+=SECSINDAY)
   {
    $counter = getday($c);
    if (count($usemtrx[$counter]) == 0)
      	   continue;
    foreach ($projects as $key => $value)
    {
         if (array_key_exists($key, $usemtrx[$counter]))
         {
	  $n = round($usemtrx[$counter][$key],2);
	  $x = floor(($c - $begin)/SECSINDAY) - $offset + 1;
	  $y = $projindex[$key]+1;
	  if ($x <= $rows && $x > 0)
	     print ("tableModel.setContentAt($x, $y, $n);\n");
          }
  }	
  }


?>

      tableModel.recalculateMinMaxValues();
   }


      </script>

    </body>
</html>
