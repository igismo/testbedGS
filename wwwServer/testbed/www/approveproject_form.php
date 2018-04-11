<?php
#
# EMULAB-COPYRIGHT
# Copyright (c) 2000-2008 University of Utah and the Flux Group.
# All rights reserved.
#
include("defs.php");

#
# Only known and logged in users can do this.
#
$this_user = CheckLoginOrDie();
$uid       = $this_user->uid();
$isadmin   = ISADMIN();
$isexec    = ISEXEC();

#
# Of course verify that this uid has admin privs!
#
if (! ($isadmin || $isexec)) {
    USERERROR("You do not have admin privileges to approve projects!", 1);
}

#
# Verify page arguments.
#
$reqargs = RequiredPageArguments("project", PAGEARG_PROJECT);
$optargs = OptionalPageArguments("head_uid", PAGEARG_STRING,
				 "user_interface", PAGEARG_STRING,
				 "message", PAGEARG_ANYTHING,
				 "silent", PAGEARG_BOOLEAN,
				 "pcplab_okay", PAGEARG_BOOLEAN,
				 "ron_okay", PAGEARG_BOOLEAN,
				 "back", PAGEARG_STRING);

#
# Check to make sure thats this is a valid PID.
#
if (! ($this_project = $reqargs["project"])) {
    USERERROR("Unknown project", 1);
}
$pid = $this_project->pid();
$projleader = $this_project->GetLeader();

#
# Standard Testbed Header
#
PAGEHEADER("New Project Approval");
$drewheader = 0;

#
# DETER: calculate whether there's an agreement (for exec member)
#
$agreement = $this_project->AgreementReached();
$age = $this_project->Age();

if ($isexec && $agreement) {
    echo "<center><h4>An agreement has been reached ";

    if ($age > 2)
        echo "and the project is more than two days old";
    else
        echo "but the project is not yet two days old";

    echo "</h4></center>\n";
}
# End DETER

echo "<center><h3>You have the following choices:</h3></center>
      <table class=stealth align=center border=0>";

# only admins can deny/destroy projects
if ($isadmin) {
    echo "
        <tr>
            <td class=stealth>Deny</td>
            <td class=stealth>-</td>
            <td class=stealth>Deny project application (kills project records)</td>
        </tr>

        <tr>
            <td class=stealth>Destroy</td>
            <td class=stealth>-</td>
            <td class=stealth>Deny project application, and kill the user account</td>
        </tr>";
}

# a member of exec can approve a project once an agreement is reached
# admins can always override
if ($isadmin || ($isexec && $agreement && $age > 2)) {
    echo "
        <tr>
            <td class=stealth>Approve</td>
            <td class=stealth>-</td>
            <td class=stealth>Approve the project</td>
        </tr>";
}

# both admins and the exec can request more info or postpone
echo "
        <tr>
            <td class=stealth>More Info</td>
            <td class=stealth>-</td>
            <td class=stealth>Ask for more info</td>
        </tr>

        <tr>
            <td class=stealth>Postpone</td>
            <td class=stealth>-</td>
            <td class=stealth>Twiddle your thumbs some more</td>
        </tr>

      </table>\n";

#
# Show stuff
#
$this_project->Show();
$rt = $this_project->research_type();

$projleader = $this_project->GetLeader();

echo "<center>
      <h3>Project Leader Information</h3>
      </center>
      <table align=center border=0>\n";

$projleader->Show();

#
# Check to make sure that the head user is 'unapproved' or 'active'
#
$headstatus = $projleader->status();
if (!strcmp($headstatus,TBDB_USERSTATUS_UNAPPROVED) ||
	!strcmp($headstatus,TBDB_USERSTATUS_ACTIVE)) {
    $approvable = 1;
} else {
    $approvable = 0;
}

#
# Now put up the menu choice along with a text box for an email message.
#
echo "<center>
      <h3>What would you like to do?</h3>
      </center>
      <table align=center border=1>
      <form action='" . CreateURL("approveproject", $project) .
             "' method='post'>\n";

echo "<tr>
          <td align=center>
              <select name=approval>
                      <option value='postpone'>Postpone</option>";
if ($approvable && ($isadmin || ($isexec && $agreement && $age > 2))) {
    echo "                  <option value='approve'>Approve</option>";
}
echo "
                      <option value='moreinfo'>More Info</option>";
if ($isadmin) {
    echo "
                      <option value='deny'>Deny</option>
                      <option value='destroy'>Destroy</option>";
}
echo "
              </select>";

if (!$approvable) {
	echo "              <br><b>WARNING:</b> Project cannot be approved,";
	echo"               since head user has not been verified";
}
echo "
          </td>
       </tr>\n";

echo "<tr>
          <td align=center>
	  If class project enter 6 char abbreviation 	  
	   <input type=\"text\" name=\"prprefix\"></tr></tr>";	

echo "<tr>
         <td align=center>
	    <input type=checkbox value=Yep ".
               ((isset($silent) && $silent == "Yep") ? "checked " : " ") .
                     "name=silent>Silent (no email sent for deny,destroy)
	 </td>
       </tr>\n";

#
# Allow the approver to change the projects head UID - gotta find everyone in
# the default group, first
#
echo "<tr>
          <td align=center>
	      Head UID:
              <select name=head_uid>
                      <option value=''>(Unchanged)</option>";

$allmembers = $this_project->MemberList();

foreach ($allmembers as $other_user) {
    $this_uid   = $other_user->uid();
    $this_webid = $other_user->webid();
    $sel = ((isset($head_uid) && $head_uid == $this_webid) ? "selected" : "");
    
    echo "             <option $sel value='$this_webid'>$this_uid</option>\n";
}
echo "        </select>
          </td>
       </tr>\n";

#
# Set the user interface.
#
echo "<tr>
          <td align=center>
              Default User Interface:
              <select name=user_interface>\n";

foreach ($TBDB_USER_INTERFACE_LIST as $interface) {
    $sel = ((isset($user_interface) &&
	     $user_interface == $interface) ? "selected" : "");
    
    echo "            <option $sel value='$interface'>$interface</option>\n";
}
echo "        </select>
          </td>
       </tr>\n";

#
# XXX
# Temporary Plab hack.
# See if remote nodes requested and put up checkboxes to allow override.
#
# These are now booleans, not actual counts.
#$num_pcplab = $this_project->num_pcplab();
#$num_ron    = $this_project->num_ron();
#
#if ($num_ron || $num_pcplab) {
#        # Default these on.
#        if (!isset($back)) {
#	    $pcplab_okay = "Yep";
#	    $ron_okay = "Yep";
#	}
#    
#	echo "<tr>
#                 <td align=center>\n";
#	if ($num_pcplab) {
#		echo "<input type=checkbox value=Yep ".
#		     ((isset($pcplab_okay) && $pcplab_okay == "Yep")
#		      ? "checked " : " ") . 
#		    " name=pcplab_okay>
#                                 Allow Plab &nbsp\n";
#	}
#	if ($num_ron) {
#		echo "<input type=checkbox value=Yep ".
#		     ((isset($ron_okay) && $ron_okay == "Yep")
#		      ? "checked " : " ") . 
#                               " name=ron_okay>
#                                 Allow RON (PCWA) &nbsp\n";
#	}
#	echo "   </td>
#              </tr>\n";
#}

echo "<tr>
          <td>Use the text box (70 columns wide) to add a message to the
              email notification. </td>
      </tr>\n";

echo "<tr>
         <td align=center class=left>
             <textarea name=message rows=15 cols=70>";
if (isset($message)) {
    echo preg_replace("/\r/", "", $message);
}
echo "</textarea>
         </td>
      </tr>\n";

echo "<tr>
          <td align=center colspan=2>
              <b><input type='submit' value='Submit' name='OK'></td>
      </tr>
      </form>
      </table>\n";

#
# Standard Testbed Footer
# 
PAGEFOOTER();
?>
