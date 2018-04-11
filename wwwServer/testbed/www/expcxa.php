<?php
#
# EMULAB-COPYRIGHT
# Copyright (c) 2000-2007 University of Utah and the Flux Group.
# Copyright (c) 2013 USC-ISI
# Copyright (c) 2013 Regents, University of California
# All rights reserved.
#
include("defs.php");
include("risk.php");

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
				 "nsdata",          PAGEARG_ANYTHING,
				 "exp_localnsfile", PAGEARG_STRING,
				 "formfields",      PAGEARG_ARRAY);

#
# Standard Testbed Header
#
PAGEHEADER("Modify Connectivity");

# Need these below.
$pid = $experiment->pid();
$eid = $experiment->eid();
$unix_gid = $experiment->UnixGID();
$expstate = $experiment->state();

if (!$experiment->AccessCheck($this_user, $TB_EXPT_MODIFY)) {
    USERERROR("You do not have permission to modify this experiment.", 1);
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
   $errors = "";
   $formfields="";
   SPITFORM($formfields, $errors, $experiment, $uid, 1);
}

function read_cxa($pid, $eid, $unix_gid, $uid)
{
   $riskparams = null;
   # This is a hack but we must copy the file so we have permissions to 
   # access it
   $risk = "/tmp/$pid.$eid.risk";
   if (! ($fp = fopen($risk, "w"))) {
	    TBERROR("Could not create file $risk", 1);
   } else { fclose($fp); chmod($risk, 0666); }

   SUEXEC("$uid", "$pid,$unix_gid", "cxa_setup -t db2file $pid $eid",
	     SUEXEC_ACTION_IGNORE);    	

   # If there was no file use empty contents
   # This enables users to modify existing non-risky experiments and
   # turn them into risky experiments

    $riskcont = file_get_contents("$risk");
    if ($riskcont === FALSE) { $riskcont = ""; }

    $riskparams = xmlrpc_decode($riskcont);
    unlink($risk);
    echo "<b><p> Here's what we read from xmlcode </p></b><br>";
    print_r($riskparams);
    return $riskparams;
}

function SPITFORM($formfields, $errors, $experiment, $uid, $first)
{
    global $TB_EXPTSTATE_ACTIVE, $TBDOCBASE;
    $pid = $experiment->pid();
    $eid = $experiment->eid();
    $gid = $experiment->gid();
    $unix_gid = $experiment->UnixGID();
    $expstate = $experiment->state();

    echo "<a href='kb-faq.php#swapmod'>".
	 "Modify Experiment Documentation (FAQ)</a></h3>";

    echo "<script language=JavaScript>
          <!--
          function NormalSubmit() {
              document.form1.target='_self';
              document.form1.submit();
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

    echo "<table align=center border=1>\n";

    $url = CreateURL("expcxa", $experiment, "go", 1);

    # If this is initially displayed form
    if ($first)
    {
	$riskparams = read_cxa($pid, $eid, $unix_gid, $uid);

    	 $address = "";
    	 $formfields['expips']= "";
    	 $formfields['remips']= "";
    	 $formfields['comm_type']="None";
    	 $formfields['malware_type']="None";
    	 if ($riskparams && $riskparams[0]) {  
	     $formfields = $riskparams[0];
	     if (strcmp($formfields['external_ok'], "1")) {
		USERERROR("<H2>You must apply for external access
				for $pid/$gid</H2",1);
	     }
    	 }
    }

    echo "<form name='form1' action='$url' method='post'
             onsubmit=\"return false;\" enctype=multipart/form-data>";
    
    
    $malware = array("None", "Unknown", "Experimenter-supplied", "SEER", "Metasploit");
    $commtype = array("None", "Benign", "Malware-generated", "Mixed");

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

     echo "<tr><th colspan=2><center><input type=button name='go' value='Modify Connectivity'
                 onclick='NormalSubmit();'></center></th></tr>\n";

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


# Do a risk check
$errormsg = CheckOptions(false, $formfields, "");
if ($errormsg != "")
{
   if ($tmpfile) {
        unlink($nsfile);
   }
   $errors["Risk/Connectivity problem"] = $errormsg;
   SPITFORM($formfields, $errors, $experiment, $uid, 1);
   PAGEFOOTER();
   exit(1);
}

# Encode request
$args = read_cxa($pid, $eid, $unix_gid, $uid);
if (! $args) {
  $args    = array();
}
$formfields['idx'] = $experiment->idx();
$formfields['pid'] = $pid;
$formfields['eid'] = $eid;
$args[0] = $formfields;
$xmlcode = xmlrpc_encode_request("dummy", $args);

# Get risk/connectivity parameters and save in XML representation.
# So far this is saved in /tmp/pid.eid.cxa but it should be
# eventually inserted into database.
#
$risk = "/tmp/$pid.$eid.risk";
$retval = file_put_contents($risk, $xmlcode);
if ($retval === FALSE) {
	TBERROR("Could not create file $risk", 1);
}
else
{
   $retval = SUEXEC("$uid", "$pid,$unix_gid",
	"cxa_setup -t file2db $pid $eid", SUEXEC_ACTION_IGNORE);    	
}
$requestcopy = xmlrpc_decode($xmlcode);
echo "<b><p> Here's the printout of the xmlcode </p></b><br>";
print_r($requestcopy);
unlink($risk);


# Avoid SIGPROF in child.
set_time_limit(0);

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

echo "<br>\n";
if ($retval) {
    echo "<h3>External Access not (yet) granted</h3>";
    echo "<blockquote><pre>$suexec_output<pre></blockquote>";
} else {
    #
    # Exit status 0 means the gateway and nodes were updated
    #
}

#
# Standard Testbed Footer
# 
PAGEFOOTER();
?>
