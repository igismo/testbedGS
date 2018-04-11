<?php
#
# EMULAB-COPYRIGHT
# Copyright (c) 2000-2009 University of Utah and the Flux Group.
# All rights reserved.
#

$login_user       = null;
$login_status     = CHECKLOGIN_NOTLOGGEDIN;
$drewheader       = 0;
$noheaders	  = 0;
$autorefresh      = 0;
$javascript_debug = 0;
$currentusage     = 1;
$currently_busy   = 0;
$sortedtables     = array();
$bodyclosestring  = "";
$navmenu          = null;
$navmenuopen      = FALSE;

#
# This has to be set so we can spit out http or https paths properly!
# Thats because browsers do not like a mix of secure and nonsecure.
# 
$BASEPATH	  = "";

#
# Determine the proper basepath, which depends on whether the page
# was loaded as http or https. This lets us be consistent in the URLs
# we spit back, so that users do not get those pesky warnings. These
# warnings are generated when a page *loads* (say, images, style files),
# a mix of http and https. Links can be mixed, and in fact when there
# is no login active, we want to spit back http for the documentation,
# but https for the start/join pages.
#
if (isset($_SERVER["HTTPS"])) {
    $BASEPATH = $TBBASE;
}
else {
    $BASEPATH = $TBDOCBASE;
}

# Blank space dividers in the menus are handled by adding a class to the menu
# item that starts a new group.
$nextnavbarcl     = null;
$nextsubmenucl    = null;

# Add a table id to the list of sorted tables to initialize on current page.
function AddSortedTable($id) {
    global $sortedtables;

    $sortedtables[] = $id;
}

#
# TOPBARCELL - Make a cell for the topbar. Actually, the name lies, it can be
# used for cells in a bottombar too.
#
function TOPBARCELL($contents) {
    echo "<td class=\"topbaropt\">";
    echo "<span class=\"topbaroption\">&nbsp;";
    echo $contents;
    echo "&nbsp;</span>";
    echo "</td>";
    echo "\n";
}

#
# WRITETOPBARBUTTON(text, base, link): Write a button in the topbar
#
function WRITETOPBARBUTTON($text, $base, $link ) {
    $link = "$base/$link";
    TOPBARCELL("<a href=\"$link\">$text</a>");
}
# same as above with "new" gif next to it.
function WRITETOPBARBUTTON_NEW($text, $base, $link ) {
    $link = "$base/$link";
    TOPBARCELL("<a href=\"$link\">$text</a>&nbsp;<img src=\"/new.gif\" />");
}

#
# Start the primary navmenu.
#
function NavMenuStart() {
    global $navmenu, $login_user;

    if ($login_user) 
	$navmenu = new menuBar();
}

#
# Start a new navmenu section (a new dropdown menu).
#
function NavMenuSection($id, $title) {
    global $navmenu, $login_user, $navmenuopen;

    if ($login_user)
	$navmenu->addMenu($title, $id);
    else {
	if ($navmenuopen) {
	    echo "</ul>\n";
	}
	$navmenuopen = TRUE;
	STARTSIDEBARMENU($id, $title);
    }
}
function NavMenuSectionEnd() {
    global $navmenu, $login_user, $navmenuopen;

    if (! $login_user) {
	if ($navmenuopen) {
	    echo "</ul>\n";
	}
	$navmenuopen = FALSE;
    }
}

#
# Add a new button to the current dropdown menu.
# 
function NavMenuButton($text, $link = null, $extratext = null,
		       $target = null, $divider = FALSE, $mouseover = null)
{
    global $navmenu, $login_user;

    if ($login_user)
	$navmenu->addItem($link, $text, $target, $divider,
			  $extratext, $mouseover);
    else
	WRITESIDEBARBUTTON($text, $link, $extratext);
	
}
# Ditto, but with a new icon.
function NavMenuButtonNew($text, $link = null, $divider = FALSE)
{
    global $navmenu, $login_user;

    if ($login_user)
	NavMenuButton($text, $link, "&nbsp;<img src=\"/new.gif\">",
		      null, $divider);
    else
	WRITESIDEBARBUTTON($text, $link, "&nbsp;<img src=\"/new.gif\" />");
}
# Ditto, but with a divider
function NavMenuButtonDivider($text, $link = null)
{
    global $navmenu, $login_user;

    if ($login_user) 
	NavMenuButton($text, $link, null, null, TRUE);
    else {
	WRITESIDEBARDIVIDER();
	WRITESIDEBARBUTTON($text, $link);
    }
}

#
# Render the menu ...
# 
function NavMenuRender()
{
    global $navmenu, $navmenuopen, $BASEPATH, $TBBASE, $login_user;

    if (!$login_user) {
	if ($navmenuopen)
	    echo "</ul>\n";
	$navmenuopen = FALSE;
	return;
    }

    $navmenu->writeMenuBar();
}

#
# STARTSIDEBARMENU(). Start a menu section.
#
function STARTSIDEBARMENU($id, $title, $visible = true) {
    $arrow = "";
    $class = "navmenu";
    if (0) {
	$png   = ($visible ? 'menu-expanded.png' : 'menu-collapsed.png');
	$class = ($visible ? 'navmenu' : 'navmenu-collapsed');
	$arrow = "<img class=menuarrow src='$png' ".
	    "onclick=\"return toggle_menu('${id}_list', '${id}_arrow');\" ".
	    "id='${id}_arrow'>";
    }
    echo "<h3 class='menuheader' id='${id}_header'>$arrow $title</h3>";
    echo "<ul class='$class' id='${id}_list'>\n";
}

#
# WRITESIDEBARBUTTON(text, link): Write a button on the sidebar menu.
# We do not currently try to match the current selection so that its
# link looks different. Not sure its really necessary.
#
function WRITESIDEBARBUTTON($text, $link, $extratext="") {
    global $nextsidebarcl;
    
    $cl = "";
    if ($nextsidebarcl != "") {
	$cl = "class='$nextsidebarcl'";
	$nextsidebarcl = "";
    }
    echo "<li $cl><a href=\"$link\">$text</a>$extratext</li>\n";
}

# same as above with "new" gif next to it.
function WRITESIDEBARBUTTON_NEW($text, $base, $link) {
    WRITESIDEBARBUTTON($text,
		       $base,
		       $link,
		       "&nbsp;<img src=\"/new.gif\" />");
}

# writes a message to the sidebar, without clickability.
function WRITESIDEBARNOTICE($text) {
    echo "<span class='notice'>$text</span>\n";
}


#
# WRITESIDEBAR(): Write the menu. The actual menu options the user
# sees depends on the login status and the DB status.
#
function WRITESIDEBAR() {
    global $login_status, $login_user, $pid, $gid;
    global $TBBASE, $TBDOCBASE, $BASEPATH, $MAILMANSUPPORT;
    global $TRACSUPPORT;
    global $TBMAINSITE;
    global $THISHOMEBASE;
    global $currentusage, $FANCYBANNER, $ELABINELAB;
    global $WIKIDOCURL;
    $firstinitstate = TBGetFirstInitState();

    #
    # get post time of most recent news;
    # get both displayable version and age in days.
    #
    $query_result = 
	DBQueryFatal("SELECT DATE_FORMAT(date, '%M&nbsp;%e') AS prettydate, ".
		     " (TO_DAYS(NOW()) - TO_DAYS(date)) AS age ".
		     "FROM webnews ".
		     "WHERE archived=0 ".
		     "ORDER BY date DESC ".
		     "LIMIT 1");
    $newsDate = "";
    $newNews  = 0;

    #
    # This is so an admin can use the editing features of news.
    #
    if ($login_user) {
	$newsBase = $TBBASE; 
    } else {
	$newsBase = $TBDOCBASE;
    }

    if ($row = mysql_fetch_array($query_result)) {
	$newsDate = "(".$row["prettydate"].")";
	if ($row["age"] < 7) {
	    $newNews = 1;
	}
    }

    if ($login_user) {
	echo "<td>\n";
	echo "<div class='midtopcell'>\n";
	echo "<!-- main navigation menu begins -->\n";

	echo "<table id='navmenus' cellspacing='0' cellpadding='0'>".
	    "<tr><td>\n";

	# Logout option on first row.
	if ($login_status & (CHECKLOGIN_LOGGEDIN|CHECKLOGIN_MAYBEVALID)) {
	    echo "<a class=midtopcell ".
		"href='$TBBASE/" . CreateURL("showuser", $login_user) . "'>".
		"My DeterLab</a>\n";

	    echo " <font color=grey>|</font> ";

            # Logout option. No longer take up space with an image.
	    echo "<a class=midtopcell ".
		"href='$TBBASE/" . CreateURL("logout", $login_user) . "'>".
		"Logout</a>\n";
	    
	    echo " <font color=grey>|</font> ";

	    # News
	    echo "<a class=midtopcell ".
		"href='$newsBase/news.php'>News</a>";
	    if ($newNews) {
		echo "&nbsp;<img src=\"/new.gif\">\n";
	    }
	    
	    echo " <font color=grey>|</font> ";

	    echo "<a class=midtopcell href=\"http://trac.deterlab.net/wiki/GettingHelp\">".
		"Contact Us</a>\n";

	    if (ISADMINISTRATOR()) {
		echo " <font color=grey>|</font> ";

		if (ISADMIN()) {
		    $url = CreateURL("toggle", $login_user,
				     "type", "adminon", "value", 0);
		
		    echo "<a href=\"$TBBASE/$url\">
                             <img src='/redball.gif'
                                  border='0' alt='Admin On'></a>\n";
		}
		else {
		    $url = CreateURL("toggle", $login_user,
				     "type", "adminon", "value", 1);

		    echo "<a href=\"$TBBASE/$url\">
                              <img src='/greenball.gif'
                                   border='0' alt='Admin Off'></a>\n";
		}
	    }
        }
	echo "</td></tr>";
	# Extra row to force two rows to top and bottom of midtopcell.
	# See height value for tr/td in the style file.
	echo "<tr id=spacer><td id=spacer></td></tr>";
	echo "<tr><td>";
    }
   
    NavMenuStart();
    NavMenuSection("information", "Information");

    

    /* DETER Menu Items */
    NavMenuButton("<b>Documentation</b>", "http://docs.deterlab.net");
    NavMenuButton("<b>Support</b>", "https://trac.deterlab.net/wiki/GettingHelp");
    NavMenuButton("<b>Education with DeterLab</b>", "http://education.deterlab.net/");
    /* end DETER Menu Items */

    /* NavMenuButton("Emulab Documentation", "$WIKIDOCURL"); */

	NavMenuButton("Projects using DeterLab", "$BASEPATH/projectlist.php");
	NavMenuButton("DETER Project", "http://www.deter-project.org");

    if ($newNews) {
	NavMenuButtonNew("News $newsDate", "$newsBase/news.php");
    } else {
	NavMenuButton("News $newsDate", "$newsBase/news.php");
    }
    if ($TBMAINSITE) {
	NavMenuButton("<font color=red>In Memoriam</font>",
		      "$TBDOCBASE/jay.php");
    }

    #
    # Cons up a nice message.
    # 
    switch ($login_status & CHECKLOGIN_STATUSMASK) {
    case CHECKLOGIN_LOGGEDIN:
	if ($login_status & CHECKLOGIN_PSWDEXPIRED)
	    $login_message = "<div class=\"err\">Password Expired!<br>" . 
		"Set a new password to Re-access the testbed</div>";
	elseif ($login_status & CHECKLOGIN_UNAPPROVED)
	    $login_message = "<div class=\"err\">Login Unapproved!<br>" . 
		"Contact Testbed Operations if you have been " . 
		"approved to use the testbed.</br></div>";
	else
	    $login_message = 0;
	break;
    case CHECKLOGIN_TIMEDOUT:
	$login_message = "Login Timed out.";
	break;
    default:
	$login_message = 0;
	break;
    }

    # Start Interaction section if going to spit out interaction options.
    if ($login_status & (CHECKLOGIN_LOGGEDIN|CHECKLOGIN_MAYBEVALID)) {
	NavMenuSection("experimentation", "Experimentation");
    }

    if ($login_status & (CHECKLOGIN_LOGGEDIN|CHECKLOGIN_MAYBEVALID)) {
	$isstudent = 0;
	if ($firstinitstate != null) {    
	    if ($firstinitstate == "createproject") {
		NavMenuButton("<font color=red> Create First Project </font>&nbsp",
			      "$TBBASE/newproject.php");
	    }
	    elseif ($firstinitstate == "approveproject") {
		$firstinitpid = TBGetFirstInitPid();
		
		NavMenuButton("<font color=red> Approve First Project </font>&nbsp",
			      "$TBBASE/approveproject.php?pid=$firstinitpid".
			      "&approval=approve");
	    }
	}
	elseif ($login_status & CHECKLOGIN_ACTIVE) {
	    if ($login_status & CHECKLOGIN_PSWDEXPIRED) {
		NavMenuButton("<font color=red> Change Your Password </font>&nbsp",
			      "$TBBASE/moduserinfo.php");
	    }
	    elseif ($login_status & CHECKLOGIN_WEBONLY) {
		NavMenuButton("Update User Information",
			      "$TBBASE/" .
			         CreateURL("moduserinfo", $login_user));
	    }
	    else {
		NavMenuButton("My DeterLab",
			      "$TBBASE/" . CreateURL("showuser", $login_user));

		#
                # Since a user can be a member of more than one project,
                # display this option, and let the form decide if the 
                # user is allowed to do this.
                #
 		NavMenuButton("Begin an Experiment",
			      "$TBBASE/beginexp_html.php");

        NavMenuButton("<b>Create Containerized Exp</b>",
                  "$TBBASE/container_beginexp_html.php");

 		NavMenuButtonDivider("Node Status",
				     "$TBBASE/nodecontrol_list.php");

		NavMenuButton("Images",
			      "$TBBASE/showimageid_list.php");

		if ($login_status & CHECKLOGIN_TRUSTED &&
		    $login_user->ApprovalList(0)) {
		    # This includes a divider argument.
		    NavMenuButtonNew("New User Approval",
				     "$TBBASE/approveuser_form.php", TRUE);
		}
		$isstudent = $login_user->CourseAcct();
	    }
	}
	elseif ($login_status & (CHECKLOGIN_UNVERIFIED|CHECKLOGIN_NEWUSER)) {
	    NavMenuButton("New User Verification",
			  "$TBBASE/verifyusr_form.php");
	    NavMenuButton("Update User Information",
			  "$TBBASE/" . CreateURL("moduserinfo", $login_user));
	}
	elseif ($login_status & (CHECKLOGIN_UNAPPROVED)) {
	    NavMenuButton("Update User Information",
			  "$TBBASE/" . CreateURL("moduserinfo", $login_user));
	}
	#
	# Standard options for logged in users!
	#
	if (!$firstinitstate && !$isstudent) {
	    NavMenuButtonDivider("Start New Project",
				 "$TBBASE/newproject.php");
	    NavMenuButton("Join Existing Project",
			  "$TBBASE/joinproject.php");
	}
        ### GORAN FOR TESTING
        NavMenuButton("GORAN Testing",
                          "$TBBASE/gorantest.php");
    }


    # Start Using DeterLab section.
    if ($login_status & (CHECKLOGIN_LOGGEDIN|CHECKLOGIN_MAYBEVALID)) {
	NavMenuSection("using", "Using DeterLab");
    }

    NavMenuButton("Basic Guide (Core)", "http://docs.deterlab.net/core/core-guide/");
    NavMenuButton("Generate Traffic", "http://docs.deterlab.net/core/generating-traffic/");
    NavMenuButton("Connect to Remote Nodes", "http://docs.deterlab.net/core/using-nodes/");
    # NavMenuButton("View Results", "#");
    NavMenuButton("Orchestrate (for large experiments", "http://docs.deterlab.net/orchestrator/orchestrator-guide/");
    NavMenuButton("Virtualize (for large experiments)", "http://docs.deterlab.net/containers/containers-guide/");
    NavMenuButton("Federate (for multiple testbeds)", "http://fedd.deterlab.net/");

  
    # And now the Collaboration menu.
    if (($login_status & (CHECKLOGIN_LOGGEDIN|CHECKLOGIN_MAYBEVALID)) &&
	$MAILMANSUPPORT ) {

	NavMenuSection("collaboration", "Collaboration");

	if ($MAILMANSUPPORT) {
	    if (!isset($pid) || $pid == "") {
		if (($project = $login_user->FirstApprovedProject())) {
		    $firstpid = $project->pid();
		}
	    }
	}
	if ($MAILMANSUPPORT) {
	     NavMenuButton("My Mailing Lists",
			   "$TBBASE/" . CreateURL("showmmlists", $login_user));
	}
    }

    # Optional ADMIN menu.
    if ($login_status & CHECKLOGIN_LOGGEDIN && ISADMIN()) {
	NavMenuSection("administration", "Administration");
	
	NavMenuButton("List Projects",
		      "$TBBASE/showproject_list.php");

	NavMenuButton("List Experiments",
		      "$TBBASE/showexp_list.php");

	NavMenuButton("List Users",
		      "$TBBASE/showuser_list.php");

	NavMenuButton("View Testbed Stats",
		      "$TBBASE/testbedstats.php");

	NavMenuButton("Resource Usage Visualization",
		      "$TBBASE/rusage_viz.php");

	NavMenuButton("Approve New Projects",
		      "$TBBASE/approveproject_list.php");

	NavMenuButton("Edit Site Variables",
		      "$TBBASE/editsitevars.php");

	NavMenuButton("Edit Knowledge Base",
		      "$TBBASE/kb-manage.php");
		    
	$query_result = DBQUeryFatal("select new_node_id from new_nodes");
	if (mysql_num_rows($query_result) > 0) {
	    NavMenuButtonNew("Add Testbed Nodes",
			     "$TBBASE/newnodes_list.php");
	}
	else {
	    NavMenuButton("Add Testbed Nodes",
			     "$TBBASE/newnodes_list.php");
	}

        NavMenuButton("New Project Vote Links",
                      "$TBBASE/vote_links.php");
    }
    if (0 && $login_user) {
	NavMenuSection("Status", "Status");
	
	$freepcs = TBFreePCs();
	$reload  = TBReloadingPCs();
	$users   = TBLoggedIn();
	$active  = TBActiveExperiments();
	NavMenuButton("Status",
		      "$TBBASE/nodecontrol_list.php",
		      null, null, FALSE,
		      "$freepcs Free PCs, $reload PCs reloading<br> ".
		      "$users users logged in, $active active experiments");
    }
    # Terminate Interaction menu and render.
    NavMenuRender();
    
    if ($login_user) {
	echo "</td></tr></table>\n";
	
	# Close up div at start of navmenu
	echo "</div>\n";
	echo "</td>\n";
    }
}

#
# Simple version of above, that just writes the given menu.
# 
function WRITESIMPLESIDEBAR($menudefs) {
    $menutitle = $menudefs['title'];
    
    echo "<h3 class='menuheader'>$menutitle</h3>
          <ul class='navmenu'>";

    each($menudefs);    
    while (list($key, $val) = each($menudefs)) {
	WRITESIDEBARBUTTON("$key", null, "$val");
    }
    echo "</ul>\n";
}

#
# spits out beginning part of page
#
function PAGEBEGINNING( $title, $nobanner = 0, $nocontent = 0,
        $extra_headers = NULL ) {
    global $BASEPATH, $TBMAINSITE, $THISHOMEBASE, $ELABINELAB, $FANCYBANNER;
    global $TBDIR, $WWW;
    global $MAINPAGE;
    global $TBDOCBASE;
    global $autorefresh, $currentusage, $javascript_debug, $login_user;

    $MAINPAGE = !strcmp($TBDIR, "/usr/testbed/");

    # Add the site name into the title if we aren't the main site.
    if(strcmp($THISHOMEBASE, "www.isi.deterlab.net") != 0) {
        $title = "$THISHOMEBASE - $title";
    }

    echo "<!DOCTYPE HTML PUBLIC '-//W3C//DTD HTML 4.01 Transitional//EN' 
          'http://www.w3.org/TR/html4/loose.dtd'>
	<html>
	  <head>
	    <title>$title</title>
            <link rel=\"shortcut icon\" href=\"$BASEPATH/favicon.ico\" TYPE=\"image/vnd.microsoft.icon\">
    	    <!-- dumbed-down style sheet for any browser that groks (eg NS47). -->
	    <link rel='stylesheet' href='$BASEPATH/common-style.css' type='text/css' />
    	    <!-- do not import full style sheet into NS47, since it does bad job
            of handling it. NS47 does not understand '@import'. -->
    	    <style type='text/css' media='all'>
            <!-- @import url($BASEPATH/style.css?version=1); -->
            <!-- @import url($BASEPATH/cssmenu-new.css); -->";
    
    if (1 && !$MAINPAGE) {
	echo "<!-- @import url($BASEPATH/style-nonmain.css); -->";
    }
    echo "</style>\n";
    echo "<!-- [if gt IE 6.0]><style type=\"text/css\">".
	".menu ul li a:hover ul { top: 18px; }</style><![endif]> -->\n";

    # This needs to stay first! It defines things that might get used by
    # later scripts
    echo "<script type='text/javascript'
                  src='${BASEPATH}/onload.js'></script>\n";
    if ($extra_headers) {
        echo $extra_headers;
    }
    if ($javascript_debug) {
	echo "<script type='text/javascript'
                      src='${BASEPATH}/js/inline-console.js'></script>\n";
    }
    echo "</head><body>\n";
    
    if ($autorefresh) {
	echo "<meta HTTP-EQUIV=\"Refresh\" content=\"$autorefresh\">\n";
    }
    echo "<script type='text/javascript' language='javascript'
                  src='${BASEPATH}/emulab_sup.js'></script>\n";
    echo "<script type='text/javascript' language='javascript'
                  src='${BASEPATH}/sorttable.js'></script>\n";
    echo "<script type='text/javascript' language='javascript'
                  src='${BASEPATH}/textbox.js'></script>\n";

    echo "<link type='text/css' rel='stylesheet' media='all' href='deter-layout.css' />";
    if (!$nobanner) {
        #
        # We do the banner differently for the Utah site and other sites.
        # The process of generating the fancy Utah banner is kind of 
        # complicated
        #
	if ($login_user) {
		echo "<div class='topcell'>\n";
	}
	else {
	    if ($FANCYBANNER) {
		echo "<div id='fancybannercell'>\n";
	    }
	    else {
		echo "<div id='bannercell'>\n";
	    }
	}

        # NOTE: This has to come before any images in the div for the float to
        # work correctly.
	if ($currentusage && !$login_user) {
	    if ($FANCYBANNER) {
		$class = "transparentusageframe";
	    }
	    else {
		$class = "usageframe";
	    }
	    echo "<iframe src='$BASEPATH/currentusage.php' class='$class'
                          scrolling='no' frameborder='0'></iframe>\n";
	}
	if ($login_user) {
	    #
	    # It is a violation of Emulab licensing restrictions to remove
	    # this logo!
            #
            # NOTE: This has to come before any images in the div for the
	    # float to work correctly.
	    #
	    if (!$TBMAINSITE) {
		#echo "<a class='rightsidebuiltwith' ".
		#    "href='http://www.emulab.net'>";
		#echo "<img src='$BASEPATH/fancy-builtwith.png'></a>\n";
	    }
	    echo "<table id=topcelltable ".
		     "width=100% cellspacing=0 cellpadding=0 border=0><tr>";
	    
	    echo "<td>\n";
	    echo "<a id='topcellimage' href='$TBDOCBASE/index.php'>";
	    echo "<img border='0' ";
	    echo "alt='$THISHOMEBASE - the network testbed' ";
	    if ($FANCYBANNER)
	    	echo "src='$BASEPATH/fancy-sheader-" .
		        strtolower($THISHOMEBASE) . ".png' ";
	    elseif ($ELABINELAB) {
		echo "height='54' ";
		echo "src='$BASEPATH/overlay.elabinelab.gif' ";
	    }
	    else {
		echo "height='54' ";
			echo "src='$BASEPATH/masthead.jpg'";
	    }
	    echo "></a>\n";
	    echo "</td>\n";
	}
	else {
	    if ($FANCYBANNER) {
		echo "<a href='$TBDOCBASE/index.php'>
                        <img height='100px' width='365px' border='0' ";
		echo "src='$BASEPATH/fancy-header-" .
			strtolower($THISHOMEBASE) . ".png' ";
		echo "></a>\n";
	    }
	    else {
		      echo "<a href='$TBDOCBASE/index.php'>
                    <img height='100' border='0' usemap='#overlaymap' src='$BASEPATH/masthead.jpg' 
                            alt='GS Testbed: Modularized testbed  for cyber-security experimentation and testing'></a>\n";
            }
	}
        if (!$login_user) 
	    echo "</div>\n";
    }
    else {
	# Need this to force no top margin/padding. No idea why! 
	echo "<div id='nobannercell'></div>\n";
    }
    if (! $nocontent ) {
	if ($login_user) {
	    ;
	}
	else {
	    echo "<div class='leftcell'>\n";
	    echo "<!-- sidebar begins -->\n";
	}
    }
}

#
# finishes sidebar td
#
function FINISHSIDEBAR($nocontent = 0)
{
    global $TBMAINSITE, $TBBASE, $BASEPATH, $currentusage, $login_user;

    if (!$nocontent) {
	if ($currentusage && $login_user) {
	    $class = "navbarusageframe";
	    echo "<td>\n";
	    echo "<div onclick=\"ToggleUsageTable();\" ".
	    "onmouseover='return escape(\"Click to toggle mode\")' ".
	     "id=usagefreenodes>\n";
	     echo "</div>\n";
	    
	    echo "</td></tr></table>\n";
	}
	echo "<!-- sidebar ends -->
              </div>";
    }
}

#
# Spit out a vanilla page header.
#
function PAGEHEADER($title, $view = NULL, $extra_headers = NULL,
		    $notice = NULL) {
    global $login_status, $login_user;
    global $TBBASE, $TBDOCBASE, $THISHOMEBASE;
    global $BASEPATH, $drewheader, $autorefresh;
    global $TBMAINSITE;

    $drewheader = 1;
    if (isset($_GET['refreshrate']) && is_numeric($_GET['refreshrate'])) {
	$autorefresh = $_GET['refreshrate'];
    }

    #
    # Figure out who is logged in, if anyone.
    #
    if (($login_user = CheckLogin($status)) != null) {
	$login_status = $status;
	$login_uid    = $login_user->uid();
    }

    #
    # If no view options were specified, get the ones for the current user
    #
    if (!$view) {
	$view = GETUSERVIEW();
    }

    #
    # Set some DETER view options
    #
    $view['hide_versioninfo'] = 1;

    #
    # Check for NOLOGINS. 
    # We want to allow admin types to continue using the web interface,
    # and logout anyone else that is currently logged in!
    #
    if (NOLOGINS() && $login_user && ($status & CHECKLOGIN_ISADMIN) != CHECKLOGIN_ISADMIN) {
	DOLOGOUT($login_user);
	$login_status = CHECKLOGIN_NOTLOGGEDIN;
	$login_user   = null;
    }
    
    header('Content-type: text/html; charset=utf-8');
    header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
    
    if (1) {
	header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
	header("Cache-Control: no-cache, must-revalidate");
	header("Pragma: no-cache");
    }
    else {
	header("Expires: " . gmdate("D, d M Y H:i:s", time() + 300) . " GMT"); 
    }

    if (VIEWSET($view, 'hide_banner')) {
	$nobanner = 1;
    } else {
	$nobanner = 0;
    }
    $contentname = ($login_user ? "content" : "rightcontent");
    $nocontent = VIEWSET($view, 'hide_sidebar') && !VIEWSET($view, 'menu');
    PAGEBEGINNING( $title, $nobanner,
		   $nocontent,
		   $extra_headers );
    if (!VIEWSET($view, 'hide_sidebar')) {
	WRITESIDEBAR();
    }
    elseif (VIEWSET($view, 'menu')) {
	WRITESIMPLESIDEBAR($view['menu']);
    }
    else {
	$contentname = "fullcontent";
    }
    FINISHSIDEBAR($nocontent);

    if ($login_user) {
	# This is only going to happen when its an admin person
	# still logged in while web interface disabled. Want to make it
	# clear that the web interface is disabled.
	if (NOLOGINS() && ($status & CHECKLOGIN_ISADMIN) == CHECKLOGIN_ISADMIN) {
	    echo "<div class=webmessage>";
	    echo "Web Interface Temporarily Unavailable</div>\n";
	}
	else {
	    $message = TBGetSiteVar("web/message");
	    if ($message != "") {
		echo "<div class=webmessage>$message</div>\n";
	    }
	}
    }

    echo "<div class='$contentname'>\n";
    echo "<!-- content body -->\n";

    if ($login_user)
	echo "<div id='contentheader'>";
    else {
	echo "<div class='contentbody'>";
	echo "<div id='rightcontentheader'>";
    }
    echo "<div id='logintime'>";
    echo "<span class='loggedin'>";
    $now = date("D M d g:ia T");
    if ($login_user) {
	echo "<span class='uid'>$login_uid</span> Logged in.";
    }
    echo "</span>";
    if ($login_user)
       echo "<span class='timestamp'>$now</span>\n";

    echo "</div>";

    if ($login_user || VIEWSET($view, 'hide_versioninfo'))
	$versioninfo = "";
    else {
	$major = "";
	$minor = "";
	$build = "";
	TBGetVersionInfo($major, $minor, $build);
	
	#$versioninfo = "Vers: $major.$minor Build: $build";
	$versioninfo = "Software Build: $build";
    }
    echo "<div id='versioninfo'>$versioninfo</div>";
    if ($notice) {
       if ($login_user)
    	echo "<span class='headernotice'>$notice</span>";      
    }
    if ($login_user)
	echo "<h2 class='contenttitle'>\n";
    else
	echo "<h2 class='rightcontenttitle'>\n";
    echo "$title</h2>";

    # Close off 'contentheader' (rightcontentheader);
    echo "</div>\n";

    if ($login_user) {
	# And start the contentbody.
	echo "<div id='fullcontentbody'>";
    }
    echo "<!-- begin content -->";
}

#
# Spit out a vanilla page footer.
#
function PAGEFOOTER($view = NULL) {
    global $TBDOCBASE, $TBMAILADDR, $THISHOMEBASE, $BASEPATH, $TBBASE;
    global $TBMAINSITE, $bodyclosestring, $currently_busy;
    global $login_user, $javascript_debug, $sortedtables;

    if ($currently_busy) {
	CLEARBUSY();
	$currently_busy = 0;
    }

    if (!$view) {
	$view = GETUSERVIEW();
    }

    $today = getdate();
    $year  = $today["year"];

    echo "</div><script type='text/javascript' src='$BASEPATH/js/wz_tooltip.js'></script>";
    echo "<div class='contentfooter'>\n";
    echo "<div class='footer_logo'><a href='${TBBASE}'><img border='0' src='$BASEPATH/masthead.jpg' alt='isi.deterlab.net - the network testbed'></a></div>";
    echo "<div class='footer_links'>
      <a href='http://deter-project.org'>DETER Project</a>|
      <a href='http://policies.usc.edu/p2admOpBus/privacy_personal_information.html'>Privacy Policy</a>|
      <a href='https://trac.deterlab.net/wiki/Policy'>Usage Policy</a>|
			<a href='http://trac.deterlab.net/newticket'>File Ticket</a> |
		  <a href='http://trac.deterlab.net/wiki/GettingHelp'>Contact Us</a><br>";
    if (!VIEWSET($view, 'hide_copyright')) {
	echo "
                <!-- begin copyright -->
		<span class='copyright'><a href='$TBDOCBASE/docwrapper.php?docname=copyright.html'>Copyright &copy; 2000-2017 USC Information Sciences Institute and University of Utah</a></span>
                </font>
		</center>\n";
    }
    echo "      </div>\n";
    echo "<div class='emulab'><a href='http://www.emulab.net'><img src='$BASEPATH/fancy-builtwith.png'></a></div>";
    echo "      <!-- end copyright -->\n";
    echo "</div>";
    echo "</div>";

    if ($javascript_debug) {
	echo "<div id='inline-console'></div>\n";
    }

    if ($login_user) {
	echo "<script>\n";
	sajax_show_javascript();
	?>
	    var cookieresults =
		document.cookie.match('usagetablemode=(.*?)(;|$)');
	    var usagetablemode =
		(cookieresults ? cookieresults[1] : "status");
	    
	    function x_FreeNodeHtml() {
		sajax_do_call("<?php echo $TBBASE; ?>/currentusage.php",
			      "FreeNodeHtml",
			      x_FreeNodeHtml.arguments);
	    }
	    function FreeNodeHtml_CB(stuff) {
		getObjbyName('usagefreenodes').innerHTML = stuff;
		setTimeout('GetFreeNodeHtml()', 60000);
	    }
	    function GetFreeNodeHtml() {
		x_FreeNodeHtml(usagetablemode, FreeNodeHtml_CB);
	    }
            function ToggleUsageTable() {
		if (usagetablemode == "status") {
		    usagetablemode = "freenodes";
		}
		else if (usagetablemode == "freenodes") {
		    usagetablemode = "stats";
		}
		else {
		    usagetablemode = "status";
		}
		document.cookie = "usagetablemode=" + usagetablemode;
		GetFreeNodeHtml();
            }
	    GetFreeNodeHtml();
	<?php
	echo "</script>\n";
    }
    
    # Prime all the sortable tables.
    if (count($sortedtables)) {
	echo "<script type='text/javascript' language='javascript'>\n";
	foreach ($sortedtables as $i => $id) {
	    echo "sorttable.makeSortable(getObjbyName('$id'));\n";
	}
	echo "</script>\n";
    }

    # This has to be after all the tooltip definitions.
    echo "<script type='text/javascript' src='${TBBASE}/js/wz_tooltip.js'>".
	"</script>";
    echo $bodyclosestring;
    echo "\n";
    if ($login_user) {
        # This closes the fullcontentbody div.
	echo "</div>\n";
    }
    echo "</body></html>\n";
}

define("HTTP_400_BAD_REQUEST", 400);
define("HTTP_403_FORBIDDEN", 403);
define("HTTP_404_NOT_FOUND", 404);

function PAGEERROR($msg, $status_code = 0) {
    global $drewheader, $noheaders;

    if (! $drewheader && $status_code != 0)
        header(' ', true, $status_code);

    if (! $drewheader && ! $noheaders) {
	PAGEHEADER("Page Error");
    }

    echo "$msg\n";

    if (! $noheaders) 
	PAGEFOOTER();
    die("");
}

#
# Sub Page/Menu Stuff
#
function WRITESUBMENUBUTTON($text, $link = null, $target = null) {
    global $nextsubmenucl;

    #
    # Optional 'target' agument, so that we can pop up new windows
    #
    $targettext = "";
    if ($target) {
	$targettext = "target='$target'";
    }
    $cl = "";
    if ($nextsubmenucl) {
	$cl = "class='$nextsubmenucl'";
	$nextsubmenucl = null;
    }
    if ($link) {
	echo "<li $cl><a href='$link' $targettext>$text</a></li>\n";
    }
    else {
	echo "<li $cl>$text</li>\n";
    }
}

function WRITESUBMENUDIVIDER() {
    global $nextsubmenucl;
    
    $nextsubmenucl = "newgroup";
}

#
# Start/End a page within a page. 
#
function SUBPAGESTART() {
    echo "<!-- begin subpage -->";
    echo "<table class=\"stealth\"
	  cellspacing='0' cellpadding='0' width='100%' border='0'>\n
            <tr>\n
              <td class=\"stealth\" valign=\"top\">\n";
}

function SUBPAGEEND() {
    echo "    </td>\n
            </tr>\n
          </table>\n";
    echo "<!-- end subpage -->";
}

#
# Start/End a sub menu, located in the upper left of the main frame.
# Note that these cannot be used outside of the SUBPAGE macros above.
#
function SUBMENUSTART($title = null) {
    echo "<!-- begin submenu -->\n";
    if ($title)
	echo "<h3 class=submenuheader>$title</h3>\n";
    echo "<ul class=submenu>\n";
}

function SUBMENUEND() {
    echo "</ul>\n" .
	 "<!-- end submenu -->\n".
	 "</td>\n".
	 "<td class=stealth valign=top align=left width='100%'>\n";
}

# Start a new section in an existing submenu
# This includes ending the one before it
function SUBMENUSECTION($title) {
    SUBMENUSECTIONEND();
    echo "<!-- new submenu section -->\n";
    echo "</ul>\n";
    echo "<h3 class=submenuheader>$title</h3>\n";
    echo "<ul class=submenu>\n";
}

# End a submenu section - only need this on the last one of the list.
function SUBMENUSECTIONEND() {
    if (0)
	;/* Nothing to do */
    else 
	echo "</ul>\n";
}

# These are here so you can wedge something else under the menu in the left
# column.

function SUBMENUEND_2A() {
    echo "</ul>\n";
    echo "<!-- end submenu -->\n";
}

function SUBMENUEND_2B() {
    echo "</td><td class=stealth valign=top align=left width='85%'>";
}

#
# Get a view, for use with PAGEHEADER and PAGEFOOTER, for the current user
#
function GETUSERVIEW() {
    return array();
}

#
# Do we view something.
#
function VIEWSET($view, $thing, $value = null) {
    if (! array_key_exists($thing, $view))
	return 0;
    if ($value) {
	return $view[$thing] == $value;
    }
    $val = $view[$thing];
    return ! empty($val);
}

function STARTBUSY($msg) {
    global $currently_busy;

    # Allow for a repeated call; Do nothing.
    if ($currently_busy)
	return;

    echo "<div id='outer_loaddiv'>\n";
    echo "<center><div id='inner_loaddiv'>\n";
    echo "<b>$msg</b> ...<br>\n";
    echo "This will take a few moments; please be <em>patient</em>.<br>\n";
    echo "</div>\n";
    echo "<img id='busy' src='busy.gif'>".
	   "<span id='loading'> Working ...</span>";
    echo "<br><br>\n";
    echo "</center>\n";
    echo "</div>\n";
    flush();
    $currently_busy = 1;
}

function STOPBUSY() {
    global $currently_busy;

    if (!$currently_busy)
	return;

    echo "<script type='text/javascript' language='javascript'>\n";
    echo "ClearBusyIndicators('<center><b>Done!</b></center>');\n";
    echo "</script>\n";
    flush();
    $currently_busy = 0;
    sleep(1);
}

function CLEARBUSY() {
    global $currently_busy;
    
    if (!$currently_busy)
	return;

    echo "<script type='text/javascript' language='javascript'>\n";
    echo "ClearBusyIndicators('');\n";
    echo "</script>\n";
    flush();
    $currently_busy = 0;
}

function HIDEBUSY() {
    global $currently_busy;
    
    if (!$currently_busy)
	return;

    echo "<script type='text/javascript' language='javascript'>\n";
    echo "HideBusyIndicators();\n";
    echo "</script>\n";
    flush();
    $currently_busy = 0;
}

function PAGEREPLACE($newpage) {
    echo "<script type='text/javascript' language='javascript'>\n";
    echo "PageReplace('$newpage');\n";
    echo "</script>\n";
    flush();
}

class menuBar
{
    var $jj;
    var $kk;
    var $mO;

    #
    # Constructor.
    #
    ## GORAN function menuBar() {
    function __construct() {
	$this->jj = -1;
	$this->kk = -1;
	$this->mO = array();
    }

    function addMenu($title, $id = null) {
	$this->jj++;
	$this->mO[$this->jj] = array('#title'   => $title,
				     '#links'   => array(),
				     '#id'      => $id);
    }

    function addItem($link, $text, $target = null,
		     $divider = FALSE, $extratext = null, $mouseover = null) {
	$this->mO[$this->jj]['#links'][] = array('#link'      => $link,
						 '#text'      => $text,
						 '#target'    => $target,
						 '#divider'   => $divider,
						 '#mouseover' => $mouseover,
						 '#extratext' => $extratext);
    }

    function writeMenuBar() {
	echo "<div class=\"menu\">\n";
	
	foreach ($this->mO as $i => $menu) {
	    $title = $menu['#title'];
	    $index = $i + 1;

	    if (count($menu['#links']) == 1) {
		$item = $menu['#links'][0];
		$link = $item['#link'];
		$text = $item['#text'];

		if ($link == null || $link == "")
		    echo "$text\n";
		else {
		    $mouseover = "";
		    if ($item['#mouseover']) {
			$string = htmlentities($item['#mouseover']);
			$mouseover =
			    "onmouseover=\"return escape('$string')\"";
		    }
		    echo "<li class=toplevel><a $mouseover href=\"$link\">$text</a></li>\n";
		}
	    }
	    else {
		echo "<ul>\n";
		echo "<li> <a href=/index.php>$title <img class=droparrow src=menu-expanded.png>";
		echo "<!--[if gt IE 6]><!--></a><!--<![endif]-->";
		echo "<!--[if lt IE 7]>";
		echo "<table border=0 cellpadding=0 cellspacing=0><tr><td>";
		echo "<![endif]-->";
		echo "<ul>\n";

		foreach ($menu['#links'] as $h => $item) {
		    $link  = $item['#link'];
		    $text  = $item['#text'];
		    $extra = $item['#extratext'];
		    $div   = "";

                    # The divider comes before the item.
		    if ($item['#divider'])
			$div = "class=divider";

		    echo "<li $div><a href=\"$link\">$text $extra</a></li>\n";
		}
		echo "</ul>";
#		echo "</td></tr></table></a>";
		echo "<!--[if lte IE 6]></td></tr></table></a><![endif]-->";
		echo "</li>\n";
		echo "</ul>\n";
	    }
	}
	echo "</div>\n";
    }
}

?>
