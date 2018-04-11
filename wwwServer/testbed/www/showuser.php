<?php
#
# EMULAB-COPYRIGHT
# Copyright (c) 2000-2007 University of Utah and the Flux Group.
# All rights reserved.
#
include("defs.php");
include("showstuff.php");
include_once("table_defs.php");

# ignore warnings for is_file permission errors
error_reporting(0);

#
# Only known and logged in users can do this.
#

# this bit of ugliness wraps up CheckLogin fail
# This is a DETER hack to allow the deter-exec group to view project applicant
# user info without being logged in.
$this_user = null;
global $CHECKLOGIN_USER;
$status = LoginStatus();
if ($status & (CHECKLOGIN_USERSTATUS|CHECKLOGIN_WEBONLY))
    $this_user = $CHECKLOGIN_USER;

$isadmin   = ISADMIN();
$swaplock = TBGetSiteVar("swap/lockout");

#
# Verify page arguments.
#
$optargs = OptionalPageArguments("target_user", PAGEARG_USER,
				 "token", 	PAGEARG_STRING);

if (! isset($target_user)) {
    if ($this_user)
        $target_user = $this_user;
    else
        USERERROR("You must specify a user");
}
$userstatus = $target_user->status();
$target_idx = $target_user->uid_idx();
$target_uid = $target_user->uid();


#
# Verify that this uid is a member of one of the projects that the
# target_uid is in. Must have proper permission in that group too. 
#
if (!$isadmin && 
    (!$this_user || !$target_user->AccessCheck($this_user, $TB_USERINFO_READINFO))) {
    #
    # Special: if there's a vote token, see if it's for this user
    #
    $hastoken = 0;

    if (isset($token) && preg_match('/^[0-9a-z]{32,40}$/', $token)) {
	$query_result = DBQueryFatal(
	    'SELECT p.head_idx ' .
	    '  from projects p, votes v ' .
	    ' where p.pid_idx = v.pid_idx and ' .
	    "       v.token = '$token'"
	);
	if (mysql_num_rows($query_result) == 1) {
	    $row = mysql_fetch_array($query_result);
	    $head_idx = $row["head_idx"];
	    $hastoken = $head_idx == $target_user->uid_idx();
	}

        if ($hastoken) {
            $query_result = DBQueryFatal(
                "select uid_idx from votes where token='$token'"
            );
            $row = mysql_fetch_array($query_result);
            $this_user = User::Lookup($row['uid_idx']);
        }
    }

    if (!$hastoken)
	USERERROR("You do not have permission to view this user's information!", 1);
}

$uid       = $this_user->uid();
$uid_idx   = $this_user->uid_idx();

#
# Tell the user how many PCs he is using.
#
$notice  = null;
$yourpcs = $target_user->PCsInUse();

if ($yourpcs) {
    $numpcs = "$yourpcs PC" . ($yourpcs != 1 ? 's' : '');
    if (! $this_user->SameUser($target_user))
	$notice = "$target_uid is using $numpcs!\n";
    else
	$notice = "You are using $numpcs!\n";
}



#
# Standard Testbed Header, now that we know what we want to say.
#
if (! $this_user->SameUser($target_user)) {
    PAGEHEADER("${target_uid}'s DETERlab", null, null, $notice);
}
else {
    PAGEHEADER("My DETERlab", null, null, $notice);

if (isset($_GET['tab']))
   $tab = $_GET['tab'];


    #
    # DETER additions: swap disabled message and news
    #
    if ($swaplock) {
        echo "<center><div style=\"background: #fe8\"><h1 style=\"margin-bottom: 0em\"><font color=Red>\n";
        echo "<blink>Swaps Disabled</blink>\n";
        echo "</font></h1>See news for explanation</div></center>\n";
    }

    # show the news
    $query_result= DBQueryFatal(
        'select DATE_FORMAT(date, "%Y-%m-%d") as pdate, subject ' .
        '  from webnews ' .
        ' where !archived and show_mydeterlab ' .
        ' order by date desc'
    );

    if (mysql_num_rows($query_result)) {
        echo "<p style=\"text-align: center\"><span style=\"color: red; font-weight: bold\">News:</span><br />\n";
        while ($row = mysql_fetch_array($query_result)) {
            $date = $row['pdate'];
            $subject = $row['subject'];
            echo "<span style=\"font-size: 120%\">$subject ($date)</span><br />\n";
        }
        echo "<a href=\"news.php\">Full news stories</a></p>\n";
    }
}

$html_groups    = null;
$html_stats     = null;
$html_pubs      = null;
$html_community = null;
$html_class 	= null;
$html_teach 	= null;
$html_share	= null;
$html_compete	= null;

#
# See if any mailman lists owned by the user. If so we add a menu item.
#
$mm_result =
    DBQueryFatal("select owner_uid from mailman_listnames ".
		 "where owner_uid='$target_uid'");
#
# Table defs for functions that generate tables.
#
$tabledefs = array('#html' => TRUE);

# The user profile.
# Add all the menu stuff. Ick.
ob_start();
SUBPAGESTART();
SUBMENUSTART("Options");

#
# Permission check not needed; if the user can view this page, they can
# generally access these subpages, but if not, the subpage will still whine.
#
WRITESUBMENUBUTTON("Edit Profile",
		   CreateURL("moduserinfo", $target_user));

if ($isadmin || $target_user->SameUser($this_user)) {
    WRITESUBMENUBUTTON("Edit SSH Keys",
		       CreateURL("showpubkeys", $target_user));
    
    WRITESUBMENUBUTTON("Generate SSL Cert",
		       CreateURL("gensslcert", $target_user));

    WRITESUBMENUBUTTON("Download your SSL Cert",
               CreateURL("getsslcert", $target_user));

    if ($MAILMANSUPPORT) {
        #
        # See if any mailman lists owned by the user. If so we add a menu item.
        #
	$mm_result =
	    DBQueryFatal("select owner_uid from mailman_listnames ".
			 "where owner_uid='$target_uid'");

	if (mysql_num_rows($mm_result)) {
	    WRITESUBMENUBUTTON("Show Mailman Lists",
			       CreateURL("showmmlists", $target_user));
	}
    }
}

if ($isadmin) {
   SUBMENUSECTION("Admin Options");
    
    if ($target_user->status() == TBDB_USERSTATUS_FROZEN) {
	WRITESUBMENUBUTTON("Thaw User",
			     CreateURL("freezeuser", $target_user,
				       "action", "thaw"));
    }
    else {
	WRITESUBMENUBUTTON("Freeze User",
			     CreateURL("freezeuser", $target_user,
				       "action", "freeze"));
    }
    if ($target_user->status() == TBDB_USERSTATUS_ARCHIVED)
        WRITESUBMENUBUTTON("<strike>Delete User</strike> <small>(archived)</small>");
    else
        WRITESUBMENUBUTTON("Delete User",
                             CreateURL("deleteuser", $target_user));

    WRITESUBMENUBUTTON("SU as User",
			 CreateURL("suuser", $target_user));

    if ($target_user->status() == TBDB_USERSTATUS_UNAPPROVED) {
	WRITESUBMENUBUTTON("Change UID",
			     CreateURL("changeuid", $target_user));
    }

    if ($target_user->status() == TBDB_USERSTATUS_NEWUSER ||
	$target_user->status() == TBDB_USERSTATUS_UNVERIFIED) {
	WRITESUBMENUBUTTON("Resend Verification Key",
			     CreateURL("resendkey", $target_user));
    }
    else {
	WRITESUBMENUBUTTON("Send Test Email Message",
			     CreateURL("sendtestmsg", $target_user));
    }
} else {
    if ($target_user->InstructedBy($this_user)) {
       SUBMENUSECTION("Instructor Options");

	$course = $target_user->CourseAcct();
	if (isset($course)) {
	    WRITESUBMENUBUTTON("Recycle Student Account",
				 CreateURL("deleteuser", $target_user));
	}

	WRITESUBMENUBUTTON("SU as User",
			     CreateURL("suuser", $target_user));

	if ($target_user->status() == TBDB_USERSTATUS_NEWUSER ||
	    $target_user->status() == TBDB_USERSTATUS_UNVERIFIED) {
	    WRITESUBMENUBUTTON("Resend Verification Key",
				 CreateURL("resendkey", $target_user));
	}
	else {
	    WRITESUBMENUBUTTON("Send Test Email Message",
				 CreateURL("sendtestmsg", $target_user));
	}
    }
}

SUBMENUEND();

$target_user->Show();
SUBPAGEEND();
$html_profile = ob_get_contents();
ob_end_clean();
list ($html_profile, $button_profile) =
	TableWrapUp($html_profile, FALSE, FALSE,
		    "profile_table", "profile_button");

#
# Lets show Experiments.
#
$html_experiments =
    ShowExperimentList("USER", $this_user,
				$target_user,
				array('#html' => TRUE,
				      '#id'   => 'experiments_sorted'));
if ($html_experiments) {
    list ($html_experiments, $button_experiments) =
	TableWrapUp($html_experiments, FALSE, TRUE,
		    "experiments_table", "experiments_button");
}

# We will use this pid later for sharing materials
$sharingpid="";

# Make competitions menu visible?
$compenabled = 0;
$cmd = "select distinct(g.pid) from group_membership g where g.uid='" . $uid . "' and g.trust='group_root' and g.pid in (select pid from project_attributes where attrkey='competitions' and attrvalue=1)";
$query_result = DBQueryFatal($cmd);
if (mysql_num_rows($query_result) > 0)
   $compenabled = 1;
#
# Lets show project and group membership.
#
$query_result =
    DBQueryFatal("select distinct g.pid,g.gid,g.trust,p.name,gr.description, ".
    		 "       count(distinct r.node_id) as ncount ".
		 " from group_membership as g ".
		 "left join projects as p on p.pid=g.pid ".
		 "left join groups as gr on gr.pid=g.pid and gr.gid=g.gid ".
		 "left join experiments as e on g.pid=e.pid and g.gid=e.gid ".
		 "left join reserved as r on e.pid=r.pid and e.eid=r.eid ".
		 "left join group_membership as g2 on g2.pid=g.pid and ".
		 "     g2.gid=g.gid and ".
		 "     g2.uid_idx='" . $this_user->uid_idx() . "' ".
		 "where g.uid_idx='$target_idx' ".
		 ($isadmin ? "" : "and g2.uid_idx is not null ") .
	### GORAN TEMP was failing without g.trust
                "group by g.pid, g.gid, g.trust ". 
		### "group by g.pid, g.gid ".
	         "order by g.pid,gr.created");

if (mysql_num_rows($query_result)) {
    ob_start();
    echo "<center>
          <h3>Project and Group Membership</h3>
          </center>
          <table align=center border=1 cellpadding=1 cellspacing=2>\n";

    echo "<tr>
              <th>PID</th>
              <th>GID</th>
	      <th>Nodes</th>
              <th>Name/Description</th>
              <th>Trust</th>
              <th>MailTo</th>
          </tr>\n";


    while ($projrow = mysql_fetch_array($query_result)) {
	$pid   = $projrow["pid"];
	if ($sharingpid == "")
	   $sharingpid=$pid;
	$gid   = $projrow["gid"];
	$name  = $projrow["name"];
	$desc  = $projrow["description"];
	$trust = $projrow["trust"];
	$nodes = $projrow["ncount"];

	echo "<tr>
                 <td><A href='showproject.php?pid=$pid'>
                        $pid</A></td>
                 <td><A href='showgroup.php?pid=$pid&gid=$gid'>
                        $gid</A></td>\n";

	echo "<td>$nodes</td>\n";

	if (strcmp($pid,$gid)) {
	    echo "<td>$desc</td>\n";
	    $mail  = $pid . "-" . $gid . "-users@" . $OURDOMAIN;
	}
	else {
	    echo "<td>$name</td>\n";
	    $mail  = $pid . "-users@" . $OURDOMAIN;
	}

	$color = ($trust == TBDB_TRUSTSTRING_NONE ? "red" : "black");

	echo "<td><font color=$color>$trust</font></td>\n";

	if ($MAILMANSUPPORT) {
            # Not sure what I want to do here ...
	    echo "<td nowrap><a href=mailto:$mail>$mail</a></td>";
	}
	else {
	    echo "<td nowrap><a href=mailto:$mail>$mail</a></td>";
	}
	echo "</tr>\n";
    }
    echo "</table>\n";

    echo "<center>
          Click on the GID to view/edit group membership and trust levels.
          </center>\n";
    $html_groups = ob_get_contents();
    list ($html_groups, $button_groups) =
	TableWrapUp($html_groups, FALSE, FALSE,
		    "groups_table", "groups_button");
    ob_end_clean();
}


if ($isadmin) {
    $html_stats = $target_user->ShowStats();
    $html_stats = "<center><h3>User Stats</h3></center>$html_stats";
    list ($html_stats, $button_stats) =
	TableWrapUp($html_stats, FALSE, FALSE,
		    "stats_table", "stats_button");
}

if ($isadmin) {
    echo "<a href='" . CreateURL("showstats", $target_user, "showby", "user") .
	"'>Experiment History</a>";
}

#
# Special banner message.
#
$message = TBGetSiteVar("web/banner");
if ($message != "") {
    echo "<center><font color=Red size=+1>\n";
    echo "$message\n";
    echo "</font></center><br>\n";
}


#
# Show unapproved projects if exec member
#
if ($isadmin || ISEXEC()) {
    $query_result = DBQueryFatal(
        'select p.pid, v.token, datediff(now(), p.created) elapsed ' .
        '  from votes v, projects p, users u ' .
        ' where v.pid_idx = p.pid_idx and v.uid_idx = u.uid_idx and ' .
        "       u.uid_idx = $target_idx and " .
        "       p.status = 'unapproved' " .
        ' order by p.created desc '
    );

    if (mysql_num_rows($query_result) > 0) {
        print "<div style=\"background: #f47; width: 40%; margin: auto; padding: 1em\">\n";
        print "<center><b>Projects pending approval:</b></center>\n";
        print "<table style=\"margin-left: auto; margin-right: auto; text-align: center\">\n";
        print "<tr><th>Project</th><th>Age</th><th>Vote</th></tr>\n";

        while ($row = mysql_fetch_array($query_result)) {
            $pid = $row['pid'];
            $token = $row['token'];
            $project_link = "<a href=\"showproject.php?pid=$pid&token=$token\">$pid</a>";

            $elapsed = $row['elapsed'];
            $age = $elapsed . ' day' . ($elapsed != 1 ? 's' : '');

            $vote_link = "<a href=\"voteproject.php?token=$token\">Vote</a>";

            print "<tr><td>$project_link</td><td>$age</td><td>$vote_link</td></tr>\n";
        }
        print "</table>\n";
        print "</div>\n<br />";
    }
}

#
# Show class resource limits
#

# fetch the limits
$limits = array();
$query_result = DBQueryFatal(
    'select p.pid, count from group_policies g, projects p ' .
    ' where g.pid_idx = p.pid_idx and p.class '
);
while ($row = mysql_fetch_array($query_result))
    $limits[$row['pid']] = $row['count'];

# show all class projects if red dot
if ($isadmin && $target_idx == $uid_idx)
    $query_result = DBQueryFatal(
        'select pid from projects where class order by pid'
    );

# show class projects of which the subject user is a member
else
    $query_result = DBQueryFatal(
        'select p.pid from projects p, group_membership m ' .
        ' where p.pid_idx = m.pid_idx and m.pid_idx = m.gid_idx and ' .
        '       p.class and m.trust != "none" and ' .
        "       m.uid_idx = $target_idx " .
        ' order by p.pid '
    );

# format the rows
$limit_display = array();
while ($row = mysql_fetch_array($query_result)) {
    $pid = $row['pid'];
    $proj = Project::Lookup($pid);
    $in_use = $proj->PCsInUse();
    $limit = $limits[$pid];
    if ($in_use > 0 || $limit > 0)
        $limit_display[] = array($pid, $in_use, $limit);
}

if (count($limit_display)) {
?>
<center><b>Notice:</b> <a href="http://docs.deterlab.net/education/course-setup/#resource-limits">Class resource limits</a> are in effect</center>
<table style="margin-left: auto; margin-right: auto; text-align: center">
<tr><th>PID</th><th>In use</th><th>Max</th><th></th><tr>
<?
    foreach ($limit_display as $limit) {
        $project = $limit[0];
        if ($isadmin) { # give admins a link to the PID
            $url = "/showproject.php?pid=$project";
            $project = "<a href=\"$url\">$project</a>";
        }
        $in_use = $limit[1];
        $avail = $limit[2];

        if ($avail) {
            $pct = floor($in_use / $avail * 100 + 0.5);
            if ($pct < 0) $pct = 0;
            if ($pct > 100) $pct = 100;
            $avail_pct = 100 - $pct;
        }
        else {
            $pct = -1;
            $avail_pct = -1;
            $avail = '&#8734;';
        }
?>
<tr>
    <td><? echo $project ?></td>
    <td><? echo $in_use ?></td>
    <td><? echo $avail ?></td>
    <td>
    <table width="200" cellpadding="0" cellspacing="0"><tr>
<?
        if ($pct > 0)
            echo "        <td width=\"$pct%\" style=\"background: red\">&nbsp;</td>\n";
        if ($avail_pct > 0)
            echo "        <td width=\"$avail_pct%\" style=\"background: green\">&nbsp;</td>\n";
        if ($pct < 0)
            echo "        <td style=\"background: blue\">&nbsp;</td>\n";
?>
    </tr></table>
    </td>
</tr>
<?
    }
    echo "</table>\n";

    # show a link to class usage statistics if admin or PI of a class project
    $class_pids = $this_user->PIof(1);
    if ($class_pids || $isadmin) {
        echo "<center><a href=\"class_usage.php\">Class Usage Statistics</a></center>\n";
    }
    echo "<br />\n";
}

# Show competition menu
#
#
  ob_start();
  SUBPAGESTART();
  SUBMENUSTART("Options");

  WRITESUBMENUBUTTON("New Competition",
		   CreateURL("new_competition", $target_user));
  WRITESUBMENUBUTTON("Help for Competitions",
		   "http://docs.deterlab.net/competitions/");

  SUBMENUEND();


  echo "</br><table class=stealth align=center>";
  echo "<th>Competition</th><th>State</th><th>Copies</th><th colspan=8>Actions</th></tr>";

  $cmd = 'select cid, c.state, c.copies, c.pid, c.name, nspath from competitions c, group_membership m, projects p ' .
        'where p.pid = c.pid and ' .
        'p.pid_idx = m.pid_idx and m.pid_idx = m.gid_idx and ' .
	'm.uid_idx = ' . $target_idx  .  
        ' order by cid';

  $query_result = DBQueryFatal($cmd);
  $i = 1;
  while ($projrow = mysql_fetch_array($query_result)) {
        $cid = $projrow["cid"];
	$name = $projrow["name"];
	$state = $projrow["state"];
	$copies = $projrow["copies"];

	echo "<tr><td>$name</td><td>$state</td><td align=right>$copies</td><td>\n";

	echo "<button onclick=\"location.href = 'manage_competitions.php?pid=$pid&cid=$cid&action=setup'\" style='font: bold 14px Arial' type='button'>Set up</button></form></td>";
	echo "<td><button onclick=\"location.href = 'manage_competitions.php?pid=$pid&cid=$cid&action=run'\" style='font: bold 14px Arial' type='button'>Run</button></form></td>";
	echo "<td><button onclick=\"location.href = 'manage_competitions.php?pid=$pid&cid=$cid&action=destroy'\" style='font: bold 14px Arial' type='button'>Destroy</button></td>";
	
  }
  echo "</table>";
  SUBPAGEEND();
  if ($compenabled)
  {
    $html_compete = ob_get_contents();
    list ($html_compete, $button_compete) =
	TableWrapUp($html_compete, FALSE, FALSE,
	"compete_table", "compete_button");
  }
  ob_end_clean();

#
# Show class administration menu
#


if ($this_user->IsTeacherOrTA()) {
    ob_start();

$query_result = DBQueryFatal(
        'select p.pid from projects p, group_membership m ' .
        ' where p.pid_idx = m.pid_idx and m.pid_idx = m.gid_idx and ' .
        '       p.class and (m.trust = "group_root" or m.trust = "project_root") and ' .
        "       m.uid_idx = $target_idx " .
        ' order by p.pid '
    );

#Display the Classes

SUBPAGESTART();
SUBMENUSTART("Options");

#
# Permission check not needed; if the user can view this page, they can
# generally access these subpages, but if not, the subpage will still whine.
#
WRITESUBMENUBUTTON("New Class Project",
		   CreateURL("newproject", $target_user));
WRITESUBMENUBUTTON("Help for Teachers",
		   "http://docs.deterlab.net/education/guidelines-for-teachers/");

SUBMENUEND();


echo "</br><table align=center>";
echo "<th> Manage Classes  </th>";



while ($projrow = mysql_fetch_array($query_result)) {
        $pid = $projrow["pid"];

	echo "<tr><td><a href='manage_class.php?pid=$pid'> $pid </td></tr>";
}
echo "</table>";


  SUBPAGEEND();
    $html_teach = ob_get_contents();
    list ($html_teach, $button_teach) =
	TableWrapUp($html_teach, FALSE, FALSE,
	"teach_table", "teach_button");
    ob_end_clean();
}

# Display student menu


if ($this_user->IsStudent()) {
    ob_start();
  

  $query_result = DBQueryFatal(
        'select p.pid, p.name from projects p, group_membership m ' .
        ' where p.pid_idx = m.pid_idx and m.pid_idx = m.gid_idx and ' .
        '       p.class and (m.trust = "local_root" or m.trust = "user") and ' .
        "       m.uid_idx = $target_idx " .
        ' order by p.pid '
    );

  # By our implementation one uid can only be member of one class
  $projrow = mysql_fetch_array($query_result);
  $pid = $projrow["pid"];
  $pname = $projrow["name"];


  echo "<CENTER><h2>Class $pid</h2>";

  SUBPAGESTART();
  SUBMENUSTART("Help");

  WRITESUBMENUBUTTON("Help for Students",
		   "http://docs.deterlab.net/education/guidelines-for-students/");

  SUBMENUEND();


  echo "<table class=stealth align=center>";
  echo "<tr><td class=stealth align='center'><h3>Materials</h3>";
  echo "</td><td class=stealth align='center'><h3>Assignments</h3></tr>";

  echo "<form name='form3' enctype='multipart/form-data' action='uploadsubmissions.php' method='POST'>\n";
  echo "<tr><td valign='top'><TABLE class=stealth><TH>Title</TH><TH>Type</TH>";
  $sql = "select title, type, path from class_materials where pid='$pid' and (gid = 'all' or gid in (select gid from group_membership where uid ='$uid')) and mid not in (select mid from class_assignments where uid='$uid')";

  $query_result = DBQueryFatal($sql);
  while ($row = mysql_fetch_array($query_result)) {
      $path = $row['path'];
      $title = $row['title'];
      $type = $row['type'];
      echo "<TR><TD><A HREF='file.php?file=$path'>$title</A></TD><TD>$type</TD></TR>";
  }
  echo "</TABLE></td>";


  echo "<td valign='top'><TABLE class=stealth><TH>Title</TH><TH>Type</TH><TH>Due</TH><TH>Submitted</TH><TH>Submit</TH>";
  $sql = "select title, type, m.path, ass.mid, due, submission_time, submission_path from class_assignments ass join class_materials m on ass.mid=m.mid where ass.uid='$uid'";
  $query_result = DBQueryFatal($sql);
  while ($row = mysql_fetch_array($query_result)) {
      $path = $row['path'];
      $title = $row['title'];
      $type = $row['type'];
      $duestring = $row['due'];
      $pieces = explode(" ", $duestring);
      $due = $pieces[0];
      $mid = $row['mid'];
      $subtime = $row['submission_time'];     
      $spath = $row['submission_path'];
      $submitted = '';
      if ($subtime != '')
      	 $submitted = "submitted at $subtime";
      echo "<INPUT TYPE=hidden NAME=pid VALUE=$pid>";
      echo "<TR><TD><A HREF='file.php?file=$path&mid=$mid'>$title</A></TD><TD>$type</TD><TD>$due</TD><TD>$submitted</TD><TD> <input id='filesubmitted$mid' name='filesubmitted$mid' type='file'/> <button onclick='javascript: document.form3.submit()' style='font: bold 14px Arial' type='button' name='button$mid'>Submit</button></TD></TR>";
  }
  echo "</TABLE></td>";

  echo "</form></TABLE>";


  SUBPAGEEND();
    $html_class = ob_get_contents();
    list ($html_class, $button_class) =
	TableWrapUp($html_class, FALSE, FALSE,
	"class_table", "class_button");
    ob_end_clean();
}

# Print sharing info
ob_start();

SUBPAGESTART();
SUBMENUSTART("Help");
WRITESUBMENUBUTTON("Help on Sharing",
		   "http://docs.deterlab.net/core/sharing/#what-can-be-shared");
SUBMENUEND();


echo "<script type='text/javascript' language='javascript'>\n
function toggle(source)
{
   var c = new Array();
   c = document.getElementsByTagName('input');

   for (var i = 0; i < c.length; i++)
   {
       if (c[i].type == 'checkbox')
       {
           c[i].checked = true;
       }
   }
}
</script>";


echo "<form name='form11' enctype='multipart/form-data' action='search.php' method='POST'>\n";
echo "<center><h3>Find shared materials</h3>\n";
echo "<center><table class=stealth width=80%>\n";
echo "<tr><td class=color align='right' width='20%'>Enter search terms separated by space:</td>";
echo "<td class=color alight='left'><input type='text' size='100'  value='All' name='searchtags'></td></tr>\n";
echo "<tr><td class=color align='right'><br>Check all types that apply</td><td class=color align='left'>\n";

$type_result = DBQueryFatal("show columns in shared_materials where Field='type'");

$arr = "";
if (mysql_num_rows($type_result)) {

while ($typerow = mysql_fetch_array($type_result)) {
        $result = $typerow["Type"];
	$result = str_replace(array("enum('", "')", "''"), array('', '', "'"), $result);
	$arr = explode("','", $result);
	$i=1;
	foreach ($arr as $value) {
	echo "<input type=\"checkbox\" name=\"searchtype$i\" value=\"$value\">$value\n";
	$i++;
	}
	echo "<input type=\"checkbox\" onClick=\"toggle(this)\" />All<br/>\n";
	}
}
$filename='';

echo "</td></tr><tr><td colspan=2 align='center'><br><button type='button' onclick='javascript: document.form11.submit()'; style='font: bold 14px Arial'>Search</button></a></table>";
echo "<input type=hidden name=uid value=$uid></form>";
echo "<hr>";
echo "<h3>Share your materials</h3>\n";
echo "<script type='text/javascript' language='javascript'>\n
function showHide(name)
{
   elem = document.getElementById(name);
   
   if (elem.style.display == 'none')
      elem.style.display='block';
   else
      elem.style.display='none';
}
function showselected(f)
{
  sel = f.sharedmethod.selectedIndex;\n
  if (sel == 1)
  {
     f.filetoshare.style.display='none';\n
     f.sharedpath.style.display='inline';\n
     f.sharedURL.style.display='none';\n
  }
  if (sel == 0)
  {
     f.filetoshare.style.display='inline';\n
     f.sharedpath.style.display='none';\n
     f.sharedURL.style.display='none';\n
  }
  if (sel == 2)
  {
     f.filetoshare.style.display='none';\n
     f.sharedpath.style.display='none';\n
     f.sharedURL.style.display='inline';\n
  }
  val =  f.sharedtype.value;
  var elem = document.getElementById('relatedto');
  if (val == 'Teacher Manual'	)
  {
     elem.style.display='inline';\n
  }
  else
  {
     elem.style.display='none';\n
  }
}</script>";
echo "<form name='form2' id='form2' enctype='multipart/form-data' action='share.php' method='POST'>\n";
echo "<table class=stealth width='80%'>";
echo "<input type=hidden name=uid value=$uid>";
echo "<input type=hidden name=pid value=$sharingpid>";
echo "<tr><td class=color align='right' width='20%'>Title:</td><td class=color align='left'><input type='text' size='100'  value='' name='sharedtitle'></td></tr>\n";
echo "<tr><td class=color align='right'>One-line description:</td><td class=color align='left'><input type='text' size='100'  value='' name='shareddesc'></td></tr>\n";
echo "<tr><td class=color align='right'>Materials to share:</td><td class=color valign='top' align='left'><select onchange='showselected(this.form);' name='sharedmethod' ID='sharedmethod' form='form2'>
<option value='zip' selected>Zip</option>
<option value='folder'>Folder on /proj, /users or /share</option>
<option value='url'>URL</option> 
</select>
<input id='filetoshare' name='filetoshare' type='file'/>\n
<input type='text' value='' size='50'  name='sharedpath' style='display: none'>\n
<input type='text' value='' size='50'  name='sharedURL' style='display: none'> </td></tr>";

echo "<tr><td class=color align='right'>Type:</td><td class=color>\n";
$i=0;
echo "<table class=color><tr>";
foreach ($arr as $value) {
	echo "<td class=color><input type=\"radio\" name=\"sharedtype\" onclick='showselected(this.form)' value=\"$value\">$value</input>\n";
	if ($value == 'Teacher Manual')
        {
	   echo "<div name='relatedto' id='relatedto' form='form2' style='display: none'>for material <select name='relatedmaterial'>";
	   $sql = "select mid, title, type from shared_materials";
	   $query_result = DBQueryFatal($sql);
	   while($row = mysql_fetch_array($query_result))
	   {
		$mid = $row['mid'];
		$title = $row['title'];
		$type = $row['type'];
		echo "<option name='related' value='$mid'>$title ($type)</option>";
	   }
	   echo "</select></div>";
	}
	$i++;
	if ($i % 4 == 0)
	   echo "</tr><tr>";
}
echo "</table></td></tr>";
echo "<tr><td class=color align='right'>Keywords:</td><td class=color align='left'>";
for ($i = 1; $i <= 10; $i++) {
    echo "<input type='text' size='10' name='sharedtags$i'>";
    if ($i==5)
       echo "<br>";
}
echo "<tr><td class=color align='right'>Maintainer e-mail:</td><td class=color align='left'><input type='text' size='50' name='contactauthor'></td></tr>";


$query_result = DBQueryFatal("SELECT * from shared_materials where author_uid='" . $uid . "'");
if (mysql_num_rows($query_result) > 0)
{
   echo "<TR><TD>&nbsp;</TR><TR><TD COLSPAN=2 ALIGN=center>Select the material to replace (if any) \n";
   echo "<select name='replacementmid' ID='replacementmid' form='form2'>";
   echo "<option value='-1' selected>None</option>\n";
   while($uprow = mysql_fetch_array($query_result))
   {
	$reptype = $uprow["type"];
        $reptitle = $uprow["title"];
        $repmid = $uprow["mid"];
	echo "<option value='$repmid'>$reptitle ($reptype)</option>\n";
   }
   echo "</select>";
   echo "</TABLE>";
}
echo "<tr><td colspan=2 align='center'><br><button onclick='javascript: document.form2.submit()' style='font: bold 14px Arial' type='button'>Share</button></table></form><br><br>\n";
SUBPAGEEND();
$html_share= ob_get_contents();

list ($html_share, $button_share) =
     TableWrapUp($html_share, TRUE, FALSE,
     "share_table", "share_button");
    ob_end_clean();

#
# Function to change what is being shown.
#
echo "<script type='text/javascript' language='javascript'>
        var li_current = 'li_experiments';
        var table_current = 'experiments_table';
        function Show(which) {
	    li = getObjbyName(li_current);
            li.style.backgroundColor = 'black';
            li.style.color = 'white';
            li.style.borderBottom = '1px solid #778';
            table = getObjbyName(table_current);
            table.style.display = 'none';

            li_current = 'li_' + which;
	    li = getObjbyName(li_current);
            li.style.backgroundColor = 'white';
            li.style.color = 'black';
            li.style.borderBottom = '1px solid white';
            table_current = which + '_table';
            table = getObjbyName(table_current);
            table.style.display = 'block';

            return false;
        }
        function Setup(which) {
            li_current = 'li_' + which;
            table_current = which + '_table';
	    li = getObjbyName(li_current);
            li.style.backgroundColor = 'white';
            li.style.color = 'black';
            li.style.borderBottom = '1px solid white';

            table = getObjbyName(table_current);
            table.style.display = 'block';
        }
	</script>\n";

#
# This is the topbar
#
echo "<div width=\"100%\" align=center>\n";
echo "<ul id=\"topnavbar\">\n";
if ($html_experiments) {
     echo "<li>
            <a href=\"#B\" class=topnavbar onfocus=\"this.hideFocus=true;\" ".
               "id=\"li_experiments\" onclick=\"Show('experiments');\">".
               "Experiments</a></li>\n";
}
if ($html_groups) {
    echo "<li>
          <a href=\"#D\" class=topnavbar onfocus=\"this.hideFocus=true;\" ".
	      "id=\"li_groups\" onclick=\"Show('groups');\">".
              "Projects</a></li>\n";
}
echo "<li>
      <a href=\"#E\" class=topnavbar onfocus=\"this.hideFocus=true;\" ".
           "id=\"li_profile\" onclick=\"Show('profile');\">".
           "Profile</a></li>\n";


if ($isadmin && $html_stats) {
    echo "<li>
          <a href=\"#F\" class=topnavbar onfocus=\"this.hideFocus=true;\" ".
	      "id=\"li_stats\" onclick=\"Show('stats');\">".
              "User Stats</a></li>\n";
}
if ($html_pubs) {
    echo "<li>
          <a href=\"#G\" class=topnavbar onfocus=\"this.hideFocus=true;\" ".
	      "id=\"li_pubs\" onclick=\"Show('pubs');\">".
              "Publications</a></li>\n";
}
if ($html_community) {
    echo "<li>
          <a href=\"#H\" class=topnavbar onfocus=\"this.hideFocus=true;\" ".
	      "id=\"li_community\" onclick=\"Show('community');\">".
              "Community</a></li>\n";
}
if ($html_class) {
   echo "<li>
      <a href=\"#I\" class=topnavbar onfocus=\"this.hideFocus=true;\" ".
           "id=\"li_class\" onclick=\"Show('class');\">".
           "My Classes</a></li>\n";
}

if ($html_teach) {
   echo "<li>
      <a href=\"#J\" class=topnavbar onfocus=\"this.hideFocus=true;\" ".
           "id=\"li_teach\" onclick=\"Show('teach');\">".
           "Teaching</a></li>\n";
}

if ($html_share) {
   echo "<li>
      <a href=\"#K\" class=topnavbar onfocus=\"this.hideFocus=true;\" ".
           "id=\"li_share\" onclick=\"Show('share');\">".
           "Sharing</a></li>\n";
}


if ($html_compete) {
   echo "<li>
            <a href=\"#L\" class=topnavbar onfocus=\"this.hideFocus=true;\" ".
           "id=\"li_compete\" onclick=\"Show('compete');\">".
           "Competitions</a></li>\n";
}


echo "</ul>\n";
echo "</div>\n";
echo "<div align=center id=topnavbarbottom>&nbsp</div>\n"; 

if ($html_groups) {
    echo $html_groups;
}
echo $html_profile;
if ($isadmin && $html_stats) {
    echo $html_stats;
}
if ($html_pubs) {
    echo $html_pubs;
}


if ($html_experiments) {
    echo $html_experiments;
}
if ($html_community) {
    echo $html_community;
}
if ($html_class) {
    echo $html_class;
}
if ($html_teach) {
    echo $html_teach;
}

if ($html_share) {
    echo $html_share;
}
if ($html_compete) {
    echo $html_compete;
}


#
# Get the active tab to look right.
#
$current = ($html_experiments ? "experiments" : "profile");

if (!isset($tab) || $tab == "")
   $tab = $current;

echo "<script type='text/javascript' language='javascript'>
      Setup(\"$current\");Show(\"$tab\");
      </script>\n";

#
# Standard Testbed Footer
# 
PAGEFOOTER();
?>



