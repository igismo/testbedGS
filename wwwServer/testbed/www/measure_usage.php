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

# see whether to only display rows which have a null value
$optargs = OptionalPageArguments("rtype", PAGEARG_STRING, 
	   			 "range", PAGEARG_STRING,
				 "force", PAGEARG_BOOLEAN);

#
# Standard Testbed Header
#
PAGEHEADER("View DETER Usage Statistics");

$formrange = "mm/dd/yy-mm/dd/yy";
if (!isset($rtype))
{
	$rtype = "";
}

if (!isset($force))
{	
	$forceup = 0;
}
else
{
	$forceup = $force; 
}


if (isset($range) && preg_match("/^(\d*)\/(\d*)\/(\d*)-(\d*)\/(\d*)\/(\d*)$/", $range))
{
	    $formrange = $range;
	    if (!preg_match("/^(\d*)\/(\d*)\/(\d*)-(\d*)\/(\d*)\/(\d*)$/",
                            $range, $rangematches))
                USERERROR("Invalid range argument: $range!", 1);
    }				   
    if (isset($rangematches)) {
        $spanstart = mktime(0,0,0,
                            $rangematches[1],$rangematches[2],
                            $rangematches[3]);
        $spanend = mktime(23,59,59,
                            $rangematches[4],$rangematches[5],
                            $rangematches[6]);
    if ($spanend <= $spanstart)
            USERERROR("Invalid range: $spanstart to $spanend", 1);
}

if (isset($rtype))
{  
   if ($rtype != "")
   {  
     if (!preg_match("/^[a-zA-Z][a-zA-Z0-9_\-]*$/", $rtype))
	USERERROR("Invalid project name: $rtype", 1);	    
   }

};


echo "<form action=measure_usage.php method=get><P>
     <TABLE><TR><TD>
      Date range </TD><TD><input type=text
             name=range
             value=\"$formrange\"></TR>
	     <TR><TD>Project </TD><TD><input type=text
             name=rtype
             value=\"$rtype\"></TD></TR><TR>
</TABLE>
	    
      <b><input type=submit name=Get value=Get></b>\n";
echo "<br><br>\n";
flush();
if (isset($range))
{
       $begin = $spanstart;
       $end = $spanend;
       $lastread =`tail -2 stats/eventsall | head -1 | awk '{print $2}'`;
       if ($lastread < $end)
       {
           print "<P>Updating from DB .. this may take a few minutes ..\n";
	   flush();
          system("cd stats; ./update.sh > /dev/null; cd ..");      
          print "Updated!\n";       
	  flush();
       }
       print "<P>Calculating usage, this may take a minute.<P> \n";
       flush();
       system("cd stats;./calcusage.pl $begin $end $rtype; cd ..");
       $s = file_get_contents("stats/results.txt");
       print "$s";
}



#
# Standard Testbed Footer
# 
PAGEFOOTER();
?>
