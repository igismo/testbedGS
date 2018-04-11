<?php
#
# EMULAB-COPYRIGHT
# Copyright (c) 2000-2008 University of Utah and the Flux Group.
# All rights reserved.
#
include("defs.php");

#
# Note the difference with which this page gets it arguments!
# I invoke it using GET arguments, so uid and pid are are defined
# without having to find them in URI (like most of the other pages
# find the uid).
#

#
# Only known and logged in users.
#
$this_user = CheckLoginOrDie();
$uid       = $this_user->uid();
$isadmin   = ISADMIN();

#
# Verify page arguments.
#
$reqargs  = RequiredPageArguments("project", PAGEARG_PROJECT);
$optargs  = OptionalPageArguments("token", PAGEARG_STRING);
$project  = $reqargs["project"];
$group    = $project->Group();
$pid      = $project->pid();

#
# Standard Testbed Header
#
PAGEHEADER("Project $pid");

#
# Verify that this uid is a member of the project being displayed.
#
if (! ($this_user && $project->AccessCheck($this_user, $TB_PROJECT_READINFO))) {
    #
    # Special: if there's a vote token, see if it's for this project
    #
    $hastoken = 0;

    if (isset($token) && preg_match('/^[0-9a-z]{32,40}$/', $token)) {
	$query_result = DBQueryFatal(
	    'SELECT pid_idx ' .
	    '  from votes ' .
	    " where token = '$token'"
	);
	if (mysql_num_rows($query_result) == 1) {
	    $row = mysql_fetch_array($query_result);
	    $pid_idx = $row["pid_idx"];
	    $hastoken = $pid_idx == $project->pid_idx();
	}
    }

    if (!$hastoken)
	USERERROR("You are not a member of Project $pid.", 1);
}

SUBPAGESTART();
SUBMENUSTART("Project Options");
WRITESUBMENUBUTTON("Create Subgroup",
		   "newgroup.php?pid=$pid");
WRITESUBMENUBUTTON("Edit User Trust",
		   "editgroup.php?pid=$pid&gid=$pid");
WRITESUBMENUBUTTON("Remove Users",
		   "showgroup.php?pid=$pid&gid=$pid");
WRITESUBMENUBUTTON("Show Project History",
		   "showstats.php?showby=project&pid=$pid");
WRITESUBMENUBUTTON("Free Node Summary",
		   "nodecontrol_list.php?showtype=summary&bypid=$pid");
if ($isadmin) {
    WRITESUBMENUDIVIDER();
    WRITESUBMENUBUTTON("Delete this project",
		       "deleteproject.php?pid=$pid");
    WRITESUBMENUBUTTON("Resend Approval Message",
		       "resendapproval.php?pid=$pid");
}
SUBMENUEND();

# Gather up the html sections.
ob_start();
$project->Show();
$profile_html = ob_get_contents();
ob_end_clean();

ob_start();
$group->ShowMembers();
$members_html = ob_get_contents();
ob_end_clean();

ob_start();
$project->ShowGroupList();
$groups_html = ob_get_contents();
ob_end_clean();

# Project wide Templates.
$experiments_html = '';
if ($this_user) {
    ob_start();
    ShowExperimentList("PROJ", $this_user, $project);
    $experiments_html = ob_get_contents();
    ob_end_clean();
}
    
$stats_html = null;
if ($isadmin) {
    ob_start();
    $project->ShowStats();
    $stats_html = ob_get_contents();
    ob_end_clean();
}

#
# Show number of PCS
#
$numpcs = $project->PCsInUse();

if ($numpcs) {
    echo "<center><font color=Red size=+2>\n";
    echo "Project $pid is using $numpcs PC" . ($numpcs != 1 ? 's' : '' ) . "!\n";
    echo "</font></center><br>\n";
}

#
# Function to change what is being shown.
#
echo "<script type='text/javascript' language='javascript'>
        var li_current = 'li_profile';
        var div_current = 'div_profile';
        function Show(which) {
	    li = getObjbyName(li_current);
            li.style.backgroundColor = 'black';
            li.style.color = 'white';
            li.style.borderBottom = '1px solid #778';
            div = getObjbyName(div_current);
            div.style.display = 'none';

            li_current = 'li_' + which;
	    li = getObjbyName(li_current);
            li.style.backgroundColor = 'white';
            li.style.color = 'black';
            li.style.borderBottom = '1px solid white';
            div_current = 'div_' + which;
            div = getObjbyName(div_current);
            div.style.display = 'block';

            return false;
        }
        function Setup(which) {
            li_current = 'li_' + which;
            div_current = 'div_' + which;
	    li = getObjbyName(li_current);
            li.style.backgroundColor = 'white';
            li.style.color = 'black';
            li.style.borderBottom = '1px solid white';
            div = getObjbyName(div_current);
            div.style.display = 'block';
        }
      </script>\n";

#
# This is the topbar
#
echo "<div width=\"100%\" align=center>\n";
echo "<ul id=\"topnavbar\">\n";
if ($experiments_html) {
     echo "<li>
            <a href=\"#B\" class=topnavbar onfocus=\"this.hideFocus=true;\" ".
               "id=\"li_experiments\" onclick=\"Show('experiments');\">".
               "Experiments</a></li>\n";
}
if ($groups_html) {
    echo "<li>
          <a href=\"#C\" class=topnavbar onfocus=\"this.hideFocus=true;\" ".
	      "id=\"li_groups\" onclick=\"Show('groups');\">".
              "Groups</a></li>\n";
}
if ($members_html) {
    echo "<li>
          <a href=\"#D\" class=topnavbar onfocus=\"this.hideFocus=true;\" ".
	      "id=\"li_members\" onclick=\"Show('members');\">".
              "Members</a></li>\n";
}
echo "<li>
      <a href=\"#E\" class=topnavbar onfocus=\"this.hideFocus=true;\" ".
           "id=\"li_profile\" onclick=\"Show('profile');\">".
           "Profile</a></li>\n";

if ($isadmin && $stats_html) {
    echo "<li>
          <a href=\"#F\" class=topnavbar onfocus=\"this.hideFocus=true;\" ".
	      "id=\"li_stats\" onclick=\"Show('stats');\">".
              "Project Stats</a></li>\n";
}
echo "</ul>\n";
echo "</div>\n";
echo "<div align=center id=topnavbarbottom>&nbsp</div>\n";

if ($experiments_html) {
     echo "<div class=invisible id=\"div_experiments\">$experiments_html</div>";
}
if ($groups_html) {
     echo "<div class=invisible id=\"div_groups\">$groups_html</div>";
}
if ($members_html) {
     echo "<div class=invisible id=\"div_members\">$members_html</div>";
}
echo "<div class=invisible id=\"div_profile\">$profile_html</div>";
if ($isadmin && $stats_html) {
    echo "<div class=invisible id=\"div_stats\">$stats_html</div>";
}
SUBPAGEEND();

#
# Get the active tab to look right.
#
echo "<script type='text/javascript' language='javascript'>
      Setup(\"profile\");
      </script>\n";

#
# Standard Testbed Footer
# 
PAGEFOOTER();
?>
