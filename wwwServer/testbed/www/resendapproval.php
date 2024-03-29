<?php
#
# EMULAB-COPYRIGHT
# Copyright (c) 2003-2007 University of Utah and the Flux Group.
# All rights reserved.
#
include("defs.php");

#
# Only known and logged in users can do this.
#
$this_user = CheckLoginOrDie();
$uid       = $this_user->uid();
$isadmin   = ISADMIN();

if (!$isadmin) {
    USERERROR("You do not have permission to access this page!", 1);
}

#
# Verify form arguments.
#
$reqargs = RequiredPageArguments("project", PAGEARG_PROJECT);
$optargs = OptionalPageArguments("submit",  PAGEARG_STRING,
				 "message", PAGEARG_ANYTHING);
$pid = $project->pid();

PAGEHEADER("Resend Project Approval Message");

#
# Form to allow text input.
#
function SPITFORM($project, $message, $errors)
{
    global $this_user;
    
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

    #
    # Show stuff
    #
    $project->Show();

    $url = CreateURL("resendapproval", $project);

    echo "<br>";
    echo "<table align=center border=1>\n";
    echo "<form action='$url' method='post'>\n";

    echo "<tr>
              <td>Use the text box (70 columns wide) to add a message to the
                  email notification. </td>
          </tr>\n";

    echo "<tr>
             <td align=center class=left>
                 <textarea name=message rows=15 cols=70></textarea>
             </td>
          </tr>\n";

    echo "<tr>
              <td align=center>
                  <b><input type='submit' value='Submit' name='submit'></td>
          </tr>
          </form>
          </table>\n";
}

#
# On first load, display a virgin form and exit.
#
if (! isset($submit)) {
    SPITFORM($project, "", null);
    PAGEFOOTER();
    return;
}

# If there is a message in the text box, it is appended below.
if (! isset($message)) {
    $message = "";
}

if (! ($leader = $project->GetLeader())) {
    TBERROR("Error getting leader for $pid", 1);
}
$headuid       = $leader->uid();
$headuid_email = $leader->email();
$headname      = $leader->name();

SendProjAdminMail(
       $pid,
       "ADMIN",
       "$headname '$headuid' <$headuid_email>",
       "Project '$pid' Approval",
       "\n".
       "This message is to notify you that your project '$pid'\n".
       "has been approved.  We recommend that you save this link so that\n".
       "you can send it to people you wish to have join your project.\n".
       "Otherwise, tell them to go to ${TBBASE} and join it.\n".
       "\n".
       "    ${TBBASE}/joinproject.php?target_pid=$pid\n".
       "\n".
       ($message != "" ? "${message}\n\n" : "") .
       "Thanks,\n".
       "Testbed Operations\n");

echo "<center>
      <h2>Done!</h2>
      </center><br>\n";

sleep(1);

PAGEREPLACE(CreateURL("showproject", $project));

#
# Standard Testbed Footer
# 
PAGEFOOTER();
?>
