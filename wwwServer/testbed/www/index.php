<?php
#
# EMULAB-COPYRIGHT
# Copyright (c) 2000-2009 University of Utah and the Flux Group.
# All rights reserved.
#
require("defs.php");

$optargs = OptionalPageArguments("stayhome", PAGEARG_BOOLEAN);

#
# The point of this is to redirect logged in users to their My Emulab
# page. 
#
if (($this_user = CheckLogin($check_status))) {
    $check_status = $check_status & CHECKLOGIN_STATUSMASK;
    if ($check_status == CHECKLOGIN_MAYBEVALID) {
        # Maybe the reason was because they where not using HTTPS ...
        RedirectHTTPS();
    }
    
    if (($firstinitstate = TBGetFirstInitState())) {
        unset($stayhome);
    }
    if (!isset($stayhome)) {
        if ($check_status == CHECKLOGIN_LOGGEDIN) {
            if ($firstinitstate == "createproject") {
                # Set admin mode so we can automatically approve the first project.
                SETADMINMODE(1);
                # Zap to NewProject Page,
                header("Location: $TBBASE/newproject.php");
            } else {
                # Zap to My Emulab page.
                header("Location: $TBBASE/".
                   CreateURL("showuser", $this_user));
            }
            return;
        }
    }
    # Fall through; display the page.
}


#
# Standard Testbed Header
#
if ($check_status == CHECKLOGIN_LOGGEDIN) {
    PAGEHEADER("TestLab: GS Experimental Research Laboratory", NULL, "notice");
} else {
    $view = array('hide_sidebar' => 1);
    PAGEHEADER("TestLab: LOGGEDIN GS Experimental Research Laboratory", $view, NULL, "notice");
}


#
# Login message. Set via 'web/message' site variable
#

$login_message = TBGetSiteVar("web/message");

if ($login_message) {
    echo "<strong>$login_message</strong>";
}

if (!NOLOGINS()) {
    if ($check_status != CHECKLOGIN_LOGGEDIN) {
	echo " 
    <div style='width:900px;margin:0 auto;''>
  <p><strong>DeterLab</strong> is a state-of-the-art scientific computing facility for cyber-security researchers engaged in research, development, discovery, experimentation, and testing of innovative cyber-security technology. To date, DeterLab-based projects have included behavior analysis and defensive technologies including DDoS attacks, worm and botnet attacks, encryption, pattern detection, and intrusion-tolerant storage protocols.</p>
  </div>
    <div style='width:370px;margin:0 auto;''>
          <table>
		  <form action='${TBBASE}/login.php' method=post>
			<th colspan=2>Please log in to access your DeterLab account</th>
			<tr>
			    <td>Username:</td>
			    <td><input type=text name=uid size=16></td>
			</tr><tr>
			    <td>Password:</td>
			    <td><input type=password name=password size=16></td>
			</tr><tr>
			    <td align=center colspan=2>
				<b><input type=submit value=Login name=login></b>
			     </td>
			</tr>
		      </form>
		 </table>\n";
    }
    echo "<br>";
    $firstinitstate = TBGetFirstInitState();
    if (!$firstinitstate) {
        echo "<h3>New Users</h3>";
	    echo "<a href=\"http://deter-project.org/about_deterlab\">Find out more about DeterLab</a><br>
              <a href=\"http://docs.deterlab.net\">Testbed Documentation</a><br>
              <a href=newproject.php>Create a New DeterLab Project</a><br> 
              <a href=joinproject.php>Join an Existing Project</a>
              </div>
                \n";
    }

} else {
    echo "<a id='webdisabled' href='$TBDOCBASE/nologins.php'>".
            "Web Interface Temporarily Unavailable</a>";
}


#
# Standard Testbed Footer
# 
PAGEFOOTER();
?>
