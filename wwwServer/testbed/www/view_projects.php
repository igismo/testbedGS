<?php
#
# EMULAB-COPYRIGHT
# Copyright (c) 2000-2006 University of Utah and the Flux Group.
# All rights reserved.
#
include("defs.php");

$optargs = OptionalPageArguments("what", PAGEARG_STRING);

if (! isset($what)) {
    $what = "user";
}

#
# Standard Testbed Header
#
PAGEHEADER("View DETER Project Statistics");

echo "<script language=JavaScript>
          <!--
          function NormalSubmit() {
              document.form1.target='_self';
              document.form1.submit();
          }
	  //-->
          </script>\n";

   print  "<script type=text/javascript language=JavaScript><!--
   function HideContent(d) {
   document.getElementById(d).style.display = \"none\";
   }
   function ShowContent(d) {
   document.getElementById(d).style.display = \"block\";
   }
   function ReverseDisplay(d) {
   if(document.getElementById(d).style.display == \"none\") { document.getElementById(d).style.display = \"block\"; }
   else { document.getElementById(d).style.display = \"none\"; }
   }
//--></script>\n";

$result = DBQueryFatal(
    'select p.pid, p.head_uid, p.why, p.expt_count, p.expt_last, p.created, '.
    '       p.org_type, ifnull(p.research_type, "NULL"), ' . 
    '       ifnull(u.usr_affil, "NULL") ' .
    ' from projects p, users u' .
    ' where p.head_uid=u.uid and not p.org_type in ("EMIST", "Testbed")'
);
?>


<?php

class ProjectSt{
  var $pid, $user, $desc, $ec, $el, $cr, $inst, $acad, $type, $longtype;

  function __construct($pid, $user, $email, $desc, $ec, $el, $cr, $inst, $acad, $type) {
    $this->pid = $pid;
    $this->user = $user;
    $this->email = $email;
    $this->desc = $desc;
    $this->ec = $ec;
    $this->el = $el;
    $this->cr = $cr;
    $this->inst = $inst;
    $this->acad = $acad;	
    $this->longtype = $type;
    $this->type = preg_split("/,/", $type);
  }
}


class Type{
  var $name, $cnt;

  function __construct($name, $cnt) {
    $this->name = $name;
    $this->cnt = $cnt;
  }
}

$i = 0;
while ( $row = mysql_fetch_row($result)) {
      $pid = $row[0];
      $uid = $row[1];	
      $qstr = "select usr_name, usr_email from users where uid='$uid'";
      $resultu = DBQueryFatal($qstr); 
      $rowu = mysql_fetch_row($resultu);
      $user = $rowu[0];
      $email = $rowu[1];
      $desc = $row[2];
      $ec = $row[3];
      $el = $row[4];
      $cr = $row[5];
      $acad = $row[6];
      $type = $row[7];
      $inst = $row[8];
      $p[$i] = new ProjectSt($pid, $user, $email, $desc, $ec, $el, $cr, $inst, $acad, $type);
      if ($what == "type")
      {
	foreach ($p[$i]->type as $key => $value)	
      	{	
        	 $types[trim(ucwords($value))] = array();
         	 $counter[trim(ucwords($value))] = 0;
      	}
      }
      else
      {
	if ($what == "inst")
      	{
	  $bywhat = $p[$i]->inst;
      	}
	elseif ($what == "acad")
	{
	  $bywhat = $p[$i]->acad;
	}
	elseif ($what == "userpc" || $what == "user")
	{
	  $bywhat = $p[$i]->user;
	}	
	elseif ($what == "yearel")
	{
	  $values = preg_split("/-/",$p[$i]->el);
	  $bywhat = $values[0];
	  if ($bywhat == "")
	     $bywhat = "Never swapped in";
	}	
	elseif ($what == "yearcr")
	{
	  $values = preg_split("/-/",$p[$i]->cr);
	  $bywhat = $values[0];
	}	
	else
	{
	  $bywhat = $p[$i]->pid;
	}
	$types[trim(ucwords($bywhat))] = array();
	$counter[trim(ucwords($bywhat))] = 0;
      }
      $i = $i+1;
   }
   $j = 0;
   while ($j < $i)
   {
      if ($what == "type")
      {
	foreach ($p[$j]->type as $key => $value)
	{
	  array_push($types[trim(ucwords($value))], $j);
	  $counter[trim(ucwords($value))] = $counter[trim(ucwords($value))]+1;
	}
	$howtoorder = "bypc";
     }
     else
    {
	if ($what == "inst")
      	{
	  $bywhat = $p[$j]->inst;
      	}
	elseif ($what == "acad")
	{
	  $bywhat = $p[$j]->acad;
	}
	elseif ($what == "userpc" || $what=="user")
	{
	  $bywhat = $p[$j]->user;
	}	
	elseif ($what == "yearel")
	{
	  $values = preg_split("/-/",$p[$j]->el);
	  $bywhat = $values[0];
	  if ($bywhat == "")
	     $bywhat = "Never swapped in";
	}	
	elseif ($what == "yearcr")
	{
	  $values = preg_split("/-/",$p[$j]->cr);
	  $bywhat = $values[0];
	}	
	else
	{
	  $bywhat = $p[$j]->pid;
	}
	if ($what == "inst" || $what == "acad" || $what == "userpc")
	{
	  $howtoorder = "bypc";
	}
	elseif ($what == "yearel" || $what == "yearcr")
	{
	  $howtoorder = "byyear";
	}	
	elseif ($what == "pidec") 
	 {
	  $howtoorder = "byec";
	 }
	else
	 {
	  $howtoorder = "byname";
	 }

       	 array_push($types[trim(ucwords($bywhat))], $j);
	 if ($howtoorder == "bypc" || $howtoorder == "byyear")
	 {
       	 $counter[trim(ucwords($bywhat))] = $counter[trim(ucwords($bywhat))]+1;		}
	 elseif ($howtoorder == "byec")
	 {
       	 $counter[trim(ucwords($bywhat))] = $counter[trim(ucwords($bywhat))]+$p[$j]->ec;		
	 }
	 else
	 {
       	 $counter[trim(ucwords($bywhat))] = trim(ucwords($bywhat));
	 }
    }
     $j = $j+1;
   }

   $j = 0;
   foreach ($types as $key => $value)	{
            $order[$j] = new Type($key, $counter[$key]);
     	   $j = $j+1;
   }
   $max = $j;

   for ($i=0; $i<$max-1; $i = $i+1)
   {
      for ($j=$i+1; $j<$max; $j = $j+1)
      {
        $a = $order[$j]->cnt;
	$b = $order[$i]->cnt;
	if ($howtoorder == "byname")
	{
		if ($order[$j]->cnt < $order[$i]->cnt)
		{
	  	$temp = $order[$j];
	  	$order[$j] = $order[$i];
	  	$order[$i] = $temp;
		}
	}
	elseif ($howtoorder == "byyear")
	{
		if ($order[$j]->name < $order[$i]->name)
		{
	  	$temp = $order[$j];
	  	$order[$j] = $order[$i];
	  	$order[$i] = $temp;
		}
	}
	else
	{
		if ($order[$j]->cnt > $order[$i]->cnt)
		{
	  	$temp = $order[$j];
	  	$order[$j] = $order[$i];
	  	$order[$i] = $temp;
		}
	}	
      }
   } 
   print "<UL>";
   print "<LI>Projects, <A HREF=\"$TBBASE/view_projects.php?what=pid\">ordered alphabetically by name</a> 
            or <A HREF=\"$TBBASE/view_projects.php?what=pidec\">by experiment count</a>";
   print "<LI>Projects grouped per <A HREF=\"$TBBASE/view_projects.php?what=yearcr\">year of creation</a> 
            or <A HREF=\"$TBBASE/view_projects.php?what=yearel\">year of last swap in</a>";
   print "<LI>Research categories, <A HREF=\"$TBBASE/view_projects.php?what=type\">ordered by project count</a>";
   print "<LI>Users, <A HREF=\"$TBBASE/view_projects.php?what=user\">ordered by name</a> 
            or <A HREF=\"$TBBASE/view_projects.php?what=userpc\">project count</a>";
   print "<LI>PI Institutions <A HREF=\"$TBBASE/view_projects.php?what=inst\">ordered by project count</a>";
   print "<LI>PI Institution types<A HREF=\"$TBBASE/view_projects.php?what=acad\"> ordered by project count</a>";
   print "</UL>";
   print "<table>";
   for ($i=0; $i<$max; $i = $i+1)
	{
         $type = $order[$i]->name;
         $cnt = $order[$i]->cnt;
         print "<tr><td colspan =7><a href=\"javascript:ReverseDisplay('$type')\">$type</a>";
         if ($howtoorder == "bypc" || $howtoorder == "byyear") 
         {
           print " projects $cnt";
	 }
	 elseif ($howtoorder == "byec")
	 {
	  print " experiments $cnt";
	 }
	print "<div id=\"$type\" style=\"display:none;\"><table><tr><td>PID</td><td>User</td><td>Email</td><td>Description</td><td>Created</td><td>Experiment count</td><td>Last used DETER</td><td>Institution name</td><td>Institution classification</td><td>Research type</td></tr>";
	foreach($types[$type] as $k=>$v)
	{
	 $pr = $p[$v]; 
         print "<tr><td><b>$pr->pid</b></td><td>$pr->user</td><td>$pr->email</td><td><a href=\"javascript:ReverseDisplay('$v')\">Description<div id=\"$v\" style=\"display:none;\"></a>$pr->desc</div></td><td>$pr->cr</td><td>$pr->ec</td><td>$pr->el</td><td>$pr->inst</td><td>$pr->acad</td><td>$pr->longtype</td></tr>\n";
        }
  print "</table></div></td></tr>";
}
print "</table>";
?>

  <body onload="load()" onunload="GUnload()">
    <div id="map" style="width: 800px; height: 500px"></div>
  </body>

<?php
#
# Standard Testbed Footer
# 
PAGEFOOTER();
?>
