<?php
#
# EMULAB-COPYRIGHT
# Copyright (c) 2000-2007 University of Utah and the Flux Group.
# All rights reserved.
#
include("defs.php");
# T1T2 begin
include("risk.php");
# T1T2 end

$TMPDIR   = "/tmp/";

#
# Only known and logged in users.
#
$this_user = CheckLoginOrDie();
$uid       = $this_user->uid();
$isadmin   = ISADMIN();

# This will not return if its a sajax request.
include("showlogfile_sup.php");

$reqargs = RequiredPageArguments("experiment",      PAGEARG_EXPERIMENT);
$optargs = OptionalPageArguments("go",              PAGEARG_STRING,
				 "syntax",          PAGEARG_STRING,
				 "reboot",          PAGEARG_BOOLEAN,
				 "eventrestart",    PAGEARG_BOOLEAN,
				 "nsdata",          PAGEARG_ANYTHING,
				 "exp_localnsfile", PAGEARG_STRING,
				 "formfields",      PAGEARG_ARRAY);

#
# Standard Testbed Header
#
PAGEHEADER("Modify Experiment");

# Need these below.
$pid = $experiment->pid();
$eid = $experiment->eid();
$unix_gid = $experiment->UnixGID();
$expstate = $experiment->state();

if (!$experiment->AccessCheck($this_user, $TB_EXPT_MODIFY)) {
    USERERROR("You do not have permission to modify this experiment.", 1);
}

if ($experiment->lockdown()) {
    USERERROR("Cannot proceed; experiment is locked down!", 1);
}

if (strcmp($expstate, $TB_EXPTSTATE_ACTIVE) &&
    strcmp($expstate, $TB_EXPTSTATE_SWAPPED)) {
    USERERROR("You cannot modify an experiment in transition.", 1);
}

# Okay, start.
echo $experiment->PageHeader();
echo "<br>\n";
flush();

#
# Put up the modify form on first load.
# 
if (! isset($go)) {
   # T1T2 begin
   $errors = "";
   $formfields="";
   SPITFORM($formfields, $errors, $experiment, $uid, 1);
   # T1T2 end
}

 # T1T2 begin
function SPITFORM($formfields, $errors, $experiment, $uid, $first)
{
    global $TB_EXPTSTATE_ACTIVE, $TBDOCBASE;
    $pid = $experiment->pid();
    $eid = $experiment->eid();
    $unix_gid = $experiment->UnixGID();
    $expstate = $experiment->state();
    # T1T2 end

    echo "<a href='kb-faq.php#swapmod'>".
	 "Modify Experiment Documentation (FAQ)</a></h3>";

   # T1T2 begin - SanityCheck function

    echo "<script language=JavaScript>
          <!--
          function NormalSubmit() {
              document.form1.target='_self';
              document.form1.submit();
          }
          function SyntaxCheck() {
              window.open('','nscheck','width=650,height=400,toolbar=no,".
                              "resizeable=yes,scrollbars=yes,status=yes,".
                              "menubar=yes');
              var action = document.form1.action;
              var target = document.form1.target;

              document.form1.action='nscheck.php?fromform=1';
              document.form1.target='nscheck';
              document.form1.submit();

              document.form1.action=action;
              document.form1.target=target;
          }
          function SanityCheck() {
              window.open('','riskcheck','width=800,height=400,toolbar=no,".
	                      "resizeable=yes,scrollbars=yes,status=yes,".
	                      "menubar=yes');
              var action = document.form1.action;
              var target = document.form1.target;

              document.form1.action='riskcheck.php';
              document.form1.target='riskcheck';
              document.form1.submit();

              document.form1.action=action;
              document.form1.target=target;
          }

          //-->
          </script>\n";
    
    if ($errors) {
	echo "<table class=nogrid
                     align=center border=0 cellpadding=6 cellspacing=0>
              <tr>
                 <th align=center colspan=2>
                   <font size=+1 color=red>
                      &nbsp;Oops, please fix the following errors!&nbsp;
                   </font>
                 </td>
              </tr>\n";

	while (list ($name, $message) = each ($errors)) {
	    echo "<tr>
                     <td align=right>
                       <font color=red>$name:&nbsp;</font></td>
                     <td align=left>
                       <font color=red>$message</font></td>
                  </tr>\n";
	}
	echo "</table><br>\n";
    }
    echo "<h2><center><a href=\"http://trac.deterlab.net/wiki/T1T2\">Did you apply for permission to create risky experiments in this project?</a></center></h2>";
    # T1T2 end

    echo "<table align=center border=1>\n";
    if (STUDLY()) {
	$ui_url = CreateURL("clientui", $experiment);
	
	echo "<tr><th colspan=2><font size='+1'>".
	    "<a href='$ui_url'>GUI Editor</a>".
	    " - Edit the topology using a Java applet.</font>";
	echo "</th></tr>";
    }

    # T1T2 begin
    $url = CreateURL("rmodifyexp", $experiment, "go", 1);
    # T1T2 end

    echo "<form name='form1' action='$url' method='post'
             onsubmit=\"return false;\" enctype=multipart/form-data>";
    
    echo "<tr><th>Upload new NS file: </th>";
    echo "<td><input type=hidden name=MAX_FILE_SIZE value=512000>";
    echo "    <input type=file name=exp_nsfile size=30
                      onchange=\"this.form.syntax.disabled=(this.value=='')\"/>
           </td></tr>\n";
    echo "<tr><th><em>or</em> NS file on server: </th> ";
    echo "<td><input type=text name=\"exp_localnsfile\" size=40
                     onchange=\"this.form.syntax.disabled=(this.value=='')\" />
          </td></tr>\n";
    
    echo "<tr><td colspan=2><b><em>or</em> Edit:</b><br>\n";
    echo "<textarea cols='100' rows='40' name='nsdata'
                    onchange=\"this.form.syntax.disabled=(this.value=='')\">";
    $nsfile = $experiment->NSFile();    
    if ($nsfile) {
	   echo "$nsfile";
    }
    else {
	echo "# There was no stored NS file for $pid/$eid.\n";
    	}
    echo "</textarea>";
    echo "</td></tr>\n";
    if (!strcmp($expstate, $TB_EXPTSTATE_ACTIVE)) {
	echo "<tr><td colspan=2><p><b>Note!</b> It is recommended that you 
	      reboot all nodes in your experiment by checking the box below.
	      This is especially important if changing your experiment
              topology (adding or removing nodes, links, and LANs).
	      If adding/removing a delay to/from an existing link, or
              replacing a lost node <i>without modifying the experiment
              topology</i>, this won't be necessary. Restarting the
	      event system is also highly recommended since the same nodes
	      in your virtual topology may get mapped to different physical
	      nodes.</p>";
	echo "<input type='checkbox' name='reboot' value='1' checked='1'>
	      Reboot nodes in experiment (Highly Recommended)</input>";
	echo "<br><input type='checkbox' name='eventrestart'
                         value='1' checked='1'>
	      Restart Event System in experiment (Highly Recommended)</input>";
      echo "</td></tr>";
    }
    echo "<tr><th colspan=2><center>";
    echo "<input type=submit disabled id=syntax name=syntax
                 value='Syntax Check' onclick=\"SyntaxCheck();\">";
    echo "<input type='reset'>";
    echo "</center></th></tr>\n";

    # T1T2 begin

    $malware = array("None", "Unknown", "Experimenter-supplied", "SEER", "Metasploit");
    $commtype = array("None", "Benign", "Malware-generated", "Mixed");

    # If this is initially displayed form
    if ($first)
    {
	 # Read the risk file first and initialize variables
    	 $riskfile = "/proj/$pid/exp/$eid/tbdata/$eid.rtxt";
    	 # This is a hack but we must copy the file so we have permissions to 
    	 # access it
	 $riskfilenew = "/tmp/$pid.$eid-existing.rtxt";
    	 $retval = SUEXEC("$uid", "$pid,$unix_gid", "copy_file $riskfile $riskfilenew",
	 	   SUEXEC_ACTION_IGNORE);    	
	 # If there was no file use empty contents
	 # This enables users to modify existing non-risky experiments and
	 # turn them into risky experiments
	 if ($retval > 0)
	 {
 	   $riskcont = "";
	}	
	else
        {
	  $riskcont = file_get_contents("$riskfilenew");
	  # Now delete the copy
    	  $retval = SUEXEC("$uid", "$pid,$unix_gid", "remove_file $riskfilenew",
	 	   SUEXEC_ACTION_IGNORE);    	 
        }

    	 $address = "";
    	 $formfields['expips']= "";
    	 $formfields['remips']= "";
    	 $formfields['comm_type']="None";
    	 $formfields['malware_type']="None";
    	 if ($riskcont && $riskcont != "")
    	 {  
       	 $eip=0;
       	 $rip=0;
       	 foreach(preg_split("/\n/",$riskcont) as $item)	
       	 {	
	   if (preg_match("/(.*:)(.*)/",$item, $groups))
	   {
		# Assume we can have options in any order although we really have them
		# always in specific order since they are written automatically
		if (trim($groups[1])=="Experiment IPs:")
		{
			$formfields['expips'] = $formfields['expips'] . trim($groups[2]) . "\n";
			$eip = 1;
			$rip = 0;
		}
		else if (trim($groups[1])=="Remote IPs:")
		{
			$formfields['remips'] = $formfields['remips'] . trim($groups[2]) . "\n";
			$rip = 1;
			$eip = 0;
		}
		else
		{	
		   if (trim($groups[1])=="Malware:" && trim($groups[2]) == "yes")
			$formfields['exp_malware']="Yep";		
		   else if (trim($groups[1])=="Self-Propagates:" && trim($groups[2]) == "yes")
		   	$formfields['exp_selfprop'] = "Yep";			
		   else if (trim($groups[1])=="Connectivity:" && trim($groups[2]) == "yes")
		   	$formfields['exp_conn'] = "Yep";				 
		   else if (trim($groups[1])=="Malware Type:")
		 	$formfields['malware_type'] = trim($groups[2]);
		   else if (trim($groups[1])=="Communication Type:")
			$formfields['comm_type'] = trim($groups[2]);
		   $eip=0;
		   $rip=0;
	      }
	   }
	   else if (preg_match("/(.*)(\/)(.*)(\/)(.*)/",$item,$groups))
           {
		if ($eip)
		  $formfields['expips'] = $formfields['expips'] . trim($item) . "\n";
		else if ($rip)
		  $formfields['remips'] = $formfields['remips'] . trim($item) . "\n";
           }	
    	 }
    	}
    }

    # Malware part
    echo "<tr><td><a href=\"https://trac.deterlab.net/wiki/T1T2\">Malware risk:</a>";
    echo "</td><td>";
    echo "<table cellpadding=0 cellspacing=0 border=0>";

    #
    # Are we running any malware?
    #
    echo "<tr><input type=checkbox onclick=\"this.form.sanity.disabled=(!this.checked && !this.form.formfields[exp_conn].checked)\" name='formfields[exp_malware]' value='Yep'";
     if (isset($formfields['exp_malware']) && strcmp($formfields['exp_malware'], "Yep") == 0)
     	echo "checked";
     echo ">\n";
     echo "Experiment uses live malware (worm, DDoS, etc.)? &nbsp;";
     echo "<font size='-1'>(Even if written by experimenter)</font>"; 
     echo "</tr>\n";

     #
     # Is malware self-propagating or not?
     #

     echo "<tr><dd><input type=checkbox name='formfields[exp_selfprop]' value='Yep'";
     if (isset($formfields['exp_selfprop']) && strcmp($formfields['exp_selfprop'], "Yep") == 0)
     	echo "checked";
     echo ">\n";
     echo "Malware self-propagates</a>? &nbsp;";
     echo "</tr>\n";
     
     #
     # What malware are you using?
     #

     echo " <tr><dd>Select malware code source</a>? &nbsp;

    <select name='formfields[malware_type]'>";

    for ($i = 0; $i < count($malware); $i++) {
    $selected ="";
    if (isset($formfields['malware_type']))
       echo $formfields['malware_type'];
       if (strcmp($formfields['malware_type'],$malware[$i])==0)
       	  $selected = "selected";
     echo "<option $selected value='$malware[$i]'>$malware[$i]</option>";
    }

     echo "</select>\n";
     echo "</tr>\n";	
     
    # End malware part
    echo "</table>";
    echo "</td> </tr>\n";

    # Connectivity part
    echo "<tr><td><a href=\"https://trac.deterlab.net/wiki/T1T2\">Connectivity needs:</a></td><td>";
    echo "<table cellpadding=0 cellspacing=0 border=0>";

    #
    # Do we require any connectivity?
    #


    echo "<tr><input type=checkbox onclick=\"this.form.sanity.disabled=(!this.checked && !this.form.formfields[exp_malware].checked)\" name='formfields[exp_conn]' value='Yep' ";
     if (isset($formfields['exp_conn']) && strcmp($formfields['exp_conn'], "Yep") == 0)
     	echo "checked";
	
     echo ">\n";
     echo "Experiment needs outside connectivity</a>? &nbsp;";
     echo "</tr>";

    #
    # What type of communication will occur?
    #
     echo " <tr><dd>Select type of communication</a> with the outside? &nbsp";
     echo "<select name='formfields[comm_type]' value='None'>";
    for ($i = 0; $i < count($commtype); $i++) {
    $selected ="";
    if (isset($formfields['comm_type']))
       echo $formfields['comm_type'];
       if (strcmp($formfields['comm_type'],$commtype[$i])==0)
       	  $selected = "selected";
     	echo "<option $selected value='$commtype[$i]'>$commtype[$i]</option>";
    }

     echo "</select>\n";
     echo "</tr>\n";	

    #
    # Destination/port info
    #

     echo " <tr><p><dd>Names and ports of <b>experiment</b> nodes that need to receive outside connections, <BR>and required transport protocol? &nbsp;
     </tr></tr>
     <font size=-1><dd>(Use names from your NS file / port number / tcp or udp, one per line. E.g., n1/80/tcp. </font>
     </tr><tr><dd><textarea rows=2 cols=20 name='formfields[expips]'>";
     if (isset($formfields['expips'])) 
          echo $formfields['expips'];
     echo  "</textarea>";

     echo " <tr><p><dd>IPs and ports of <b>outside nodes</b> that will receive connections from your experiment, <BR>and required transport protocol? &nbsp;

     </tr></tr>
     <font size=-1><dd>(Use IP / port number / tcp or udp notation, one per line. E.g., 1.2.3.4/22/tcp. </font>
     </tr><tr><dd><textarea rows=2 cols=20 name='formfields[remips]'>";
     if (isset($formfields['remips']))
          echo $formfields['remips'];
      echo "</textarea>";

    # end connectivity 
    echo "</table>";
    echo "</td> </tr>\n";

    $disabled = "disabled";
    if ((isset($formfields['exp_malware']) && strcmp($formfields['exp_malware'], "Yep") == 0) ||
        (isset($formfields['exp_conn']) && strcmp($formfields['exp_conn'], "Yep") == 0))
    $disabled = "";
    echo "<tr><td colspan=2><center><input type=submit $disabled id=sanity name=sanity value='Malware/Connectivity Sanity Check' onclick=\"SanityCheck();\"></td></tr>";

    # 
    # T1T2 end
    #

    # T1T2 begin
     echo "<tr><th colspan=2><center><input type=button name='go' value='Modify Experiment'
                 onclick='NormalSubmit();'></center></th></tr>\n";
    # T1T2 end

    echo "</table>\n";
    echo "</form>\n";
    PAGEFOOTER();
    exit();
}

#
# Okay, form has been submitted.
#
$speclocal  = 0;
$specupload = 0;
$specform   = 0;
$nsfile     = "";
$tmpfile    = 0;

if (isset($exp_localnsfile) && strcmp($exp_localnsfile, "")) {
    $speclocal = 1;
}
if (isset($_FILES['exp_nsfile']) &&
    $_FILES['exp_nsfile']['name'] != "" &&
    $_FILES['exp_nsfile']['tmp_name'] != "") {
    if ($_FILES['exp_nsfile']['size'] == 0) {
        USERERROR("Uploaded NS file does not exist, or is empty");
    }
    $specupload = 1;
}
if (!$speclocal && !$specupload && isset($nsdata))  {
    $specform = 1;
}

if ($speclocal + $specupload + $specform > 1) {
    USERERROR("You may not specify both an uploaded NS file and an ".
	      "NS file that is located on the Emulab server", 1);
}
#
# Gotta be one of them!
#
if (!$speclocal && !$specupload && !$specform) {
    USERERROR("You must supply an NS file!", 1);
}

if ($speclocal) {
    #
    # No way to tell from here if this file actually exists, since
    # the web server runs as user www. The startexp script checks
    # for the file before going to ground, so the user will get immediate
    # feedback if the filename is bogus.
    #
    # Do not allow anything outside of the usual directories. I do not think
    # there is a security worry, but good to enforce it anyway.
    #
    if (!preg_match("/^([-\@\w\.\/]+)$/", $exp_localnsfile)) {
	USERERROR("NS File: Pathname includes illegal characters", 1);
    }
    if (!VALIDUSERPATH($exp_localnsfile)) {
	USERERROR("NS File: You must specify a server resident file in " .
		  "one of: ${TBVALIDDIRS}.", 1);
    }
    
    $nsfile = $exp_localnsfile;
    $nonsfile = 0;
}
elseif ($specupload) {
    #
    # XXX
    # Set the permissions on the NS file so that the scripts can get to it.
    # It is owned by www, and most likely protected. This leaves the
    # script open for a short time. A potential security hazard we should
    # deal with at some point.
    #
    $nsfile = $_FILES['exp_nsfile']['tmp_name'];
    chmod($nsfile, 0666);
    $nonsfile = 0;
}
elseif ($specform) {
    #
    # Take the NS file passed in from the form and write it out to a file
    #
    $tmpfile = 1;

    #
    # Generate a hopefully unique filename that is hard to guess.
    # See backend scripts.
    # 
    list($usec, $sec) = explode(' ', microtime());
    srand((float) $sec + ((float) $usec * 100000));
    $foo = rand();

    $nsfile = "/tmp/$uid-$foo.nsfile";
    $handle = fopen($nsfile,"w");
    fwrite($handle,$nsdata);
    fclose($handle);
    chmod($nsfile, 0666);
}

# T1T2 begin
#
# Save ns file contents
#
$nsfilecont = file_get_contents($nsfile);
# T1T2 end

#
# Do an initial parse test.
#
$retval = SUEXEC($uid, "$pid,$unix_gid", "webnscheck $nsfile",
		 SUEXEC_ACTION_IGNORE);
 
if ($retval != 0) {
    if ($tmpfile) {
        unlink($nsfile);
    }
    
    # Send error to tbops.
    if ($retval < 0) {
	SUEXECERROR(SUEXEC_ACTION_CONTINUE);
    }
    echo "<br>";
    echo "<h3>Modified NS file contains syntax errors</h3>";
    echo "<blockquote><pre>$suexec_output<pre></blockquote>";

    PAGEFOOTER();
    exit();
}

# T1T2 begin
# Do a risk check
$errormsg = CheckOptions(false, $formfields, $nsfilecont);
if ($errormsg != "")
{
   if ($tmpfile) {
        unlink($nsfile);
   }
   $errors["Risk/Connectivity problem"] = $errormsg;
   SPITFORM($formfields, $errors, $experiment, $uid, 0);
   PAGEFOOTER();
   exit(1);
}

# Encode request
$args    = array();
$args[0] = $formfields;
$xmlcode = xmlrpc_encode_request("dummy", $args);

# Get risk/connectivity parameters and save in XML representation.
# So far this is saved in /tmp/pid.eid.risk but it should be
# eventually inserted into database.
#
$risk = "/tmp/$pid.$eid.risk";
if (! ($fp = fopen($risk, "w"))) {
	TBERROR("Could not create file $risk", 1);
    }
else
{
   fwrite($fp, $xmlcode);
   fclose($fp);
}
chmod($risk, 0666);

#
# Get risk/connectivity parameters and save in TXT representation,
# for easy parsing later.
# So far this is saved in /tmp/pid.eid.rtxt but it should be
# eventually inserted into database.
#
$connect = "";
$hasmalware = "";
$rtxt = "/tmp/$pid.$eid.rtxt";
if (! ($fp = fopen($rtxt, "w"))) {
	TBERROR("Could not create file $rtxt", 1);
    }
else
{
   if (isset($formfields["exp_malware"]))
   {
      $hasmalware="-M 1";
      fwrite($fp, "Malware: yes\n");
      if (isset($formfields["malware_type"]))
      {
        $mt = $formfields["malware_type"];
	fwrite($fp, "Malware Type: $mt\n");
      }
      if (isset($formfields["exp_selfprop"]))
      {
	$sp = $formfields["exp_selfprop"];
	fwrite($fp, "Self-Propagates: yes\n");
      }
   }
   else
   {
	$hasmalware = "-M 0";
   }
   if (isset($formfields["exp_conn"]))
   {
      $connect="-C 1";
      fwrite($fp, "Connectivity: yes\n");
      if (isset($formfields["comm_type"]))
      {
        $ct = $formfields["comm_type"];
	fwrite($fp, "Communication Type: $ct\n");
      }
      if (isset($formfields["expips"]))
      {
        $ei = trim($formfields["expips"]);
	if ($ei != "")
	  fwrite($fp, "Experiment IPs: $ei\n");
      }
      if (isset($formfields["remips"]))
      {
        $ri = trim($formfields["remips"]);
	if ($ri != "")
	   fwrite($fp, "Remote IPs: $ri\n");
      }
   }
   else
   {
	$connect="-C 0";
   }
   fclose($fp);
}
chmod($rtxt, 0666);
# T1T2 end

# Avoid SIGPROF in child.
set_time_limit(0);

# args.
$optargs = "";
if (isset($reboot) && $reboot) {
     $optargs .= " -r ";
}
if (isset($eventrestart) && $eventrestart) {
     $optargs .= " -e ";
}

# T1T2 begin
# Run the script.
$retval = SUEXEC($uid, "$pid,$unix_gid",
		 "rwebswapexp $optargs  $connect  $hasmalware -s modify $pid $eid $nsfile",
		 SUEXEC_ACTION_IGNORE);
	
# Delete risk files
unlink($risk);
unlink($rtxt);
# T1T2 end	 

# It has been copied out by the program!
if ($tmpfile) {
    unlink($nsfile);
}

#
# Fatal Error. Report to the user, even though there is not much he can
# do with the error. Also reports to tbops.
# 
if ($retval < 0) {
    SUEXECERROR(SUEXEC_ACTION_DIE);
    #
    # Never returns ...
    #
    die("");
}

#
# Exit status 0 means the experiment is swapping, or will be.
#
echo "<br>\n";
if ($retval) {
    echo "<h3>Experiment modify could not proceed</h3>";
    echo "<blockquote><pre>$suexec_output<pre></blockquote>";
}
else {
    #
    # Exit status 0 means the experiment is modifying.
    #
    echo "<b>Your experiment is being modified!</b> ";
    echo "You will be notified via email when the experiment has ".
	"finished modifying and you are able to proceed. This ".
	"typically takes less than 10 minutes, depending on the ".
	"number of nodes in the experiment. ".
	"If you do not receive email notification within a ".
        "reasonable amount time, please ".
        "<a href=\"http://trac.deterlab.net/wiki/GettingHelp\">file a ".
        "ticket</a>. ".
	"<br><br>".
	"While you are waiting, you can watch the log of experiment ".
	"modification in realtime:<br><br>\n";
    STARTLOG($experiment);    
}

#
# Standard Testbed Footer
# 
PAGEFOOTER();
?>


