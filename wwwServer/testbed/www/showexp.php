<?php
#
# EMULAB-COPYRIGHT
# Copyright (c) 2000-2008 University of Utah and the Flux Group.
# All rights reserved.
#
include("defs.php");
require("Sajax.php");
include("showstuff.php");
include_once("node_defs.php");
sajax_init();
sajax_export("GetExpState", "Show", "FreeNodeHtml");

#
# Only known and logged in users can look at experiments.
#
$this_user = CheckLoginOrDie();
$uid       = $this_user->uid();
$student   = ($this_user->CourseAcct() ? 1 : 0);
$isadmin   = ISADMIN();

#
# Verify page arguments.
#
$reqargs = RequiredPageArguments("experiment", PAGEARG_EXPERIMENT);
$optargs = OptionalPageArguments("sortby",     PAGEARG_STRING,
				 "showclass",  PAGEARG_STRING);

if (!isset($sortby)) {
    if ($experiment->pid() == $TBOPSPID)
	$sortby = "rsrvtime-down";
    else
	$sortby = "";
}
if (!isset($showclass))
     $showclass = null;

# Need these below.
$exp_eid = $eid = $experiment->eid();
$exp_pid = $pid = $experiment->pid();
$tag = "Experiment";

#
# Verify Permission.
#
if (!$experiment->AccessCheck($this_user, $TB_EXPT_READINFO)) {
    USERERROR("You do not have permission to view experiment $exp_eid!", 1);
}

#
# For the Sajax Interface
#
function FreeNodeHtml()
{
    global $this_user, $experiment;
    
    return ShowFreeNodes($this_user, $experiment->Group());
}

function GetExpState($a, $b)
{
    global $experiment;

    return $experiment->state();
}

function Show($which, $arg1, $arg2)
{
    global $experiment, $uid, $TBSUEXEC_PATH, $TBADMINGROUP;
    $pid  = $experiment->pid();
    $eid  = $experiment->eid();
    $html = "";

    if ($which == "settings") {
	ob_start();
	$experiment->Show();
	$html = ob_get_contents();
	ob_end_clean();
    }
    if ($which == "details") {
	$showevents = $arg1;
	$output = array();
	$retval = 0;
	$html   = "";

        # Show event summary and firewall info.
        $flags = ($showevents ? "-e -a" : "-b -e -f");

	$result = exec("$TBSUEXEC_PATH $uid $TBADMINGROUP ".
		       "webtbreport $flags $pid $eid",
		       $output, $retval);

	$html = "<pre><div align=left id=\"showexp_details\" ".
	    "class=\"showexp_codeblock\">";
	for ($i = 0; $i < count($output); $i++) {
	    $html .= htmlentities($output[$i]);
	    $html .= "\n";
	}
	$html .= "</div></pre>\n";

	$html .= "<button name=showevents type=button value=1";
	$html .= " onclick=\"ShowEvents();\">";
	$html .= "Show Events</button>\n";
	
	$html .= "&nbsp &nbsp &nbsp &nbsp &nbsp &nbsp ";

	$html .= "<button name=savedetails type=button value=1";
	$html .= " onclick=\"SaveDetails();\">";
	$html .= "Save to File</button>\n";
    }
    if ($which == "vis") {
	$zoom   = $arg1;
	$detail = $arg2;
	
	if ($zoom == 0) {
            # Default is whatever we have; to avoid regen of the image.
	    $query_result =
		DBQueryFatal("select zoom,detail from vis_graphs ".
			     "where pid='$pid' and eid='$eid'");

	    if (mysql_num_rows($query_result)) {
		$row    = mysql_fetch_array($query_result);
		$zoom   = $row['zoom'];
		$detail = $row['detail'];
	    }
	    else {
		$zoom   = 1.15;
		$detail = 1;
	    }
	}
	else {
            # Sanity check but lets not worry about throwing an error.
	    if (!TBvalid_float($zoom))
		$zoom = 1.25;
	    if (!TBvalid_integer($detail))
		$detail = 1;
    	}

	$html = ShowVis($pid, $eid, $zoom, $detail);

	$zoomout = sprintf("%.2f", $zoom / 1.25);
	$zoomin  = sprintf("%.2f", $zoom * 1.25);

	$html .= "<button name=viszoomout type=button value=$zoomout";
	$html .= " onclick=\"VisChange('$zoomout', $detail);\">";
	$html .= "Zoom Out</button>\n";
	$html .= "<button name=viszoomin type=button value=$zoomin";
	$html .= " onclick=\"VisChange('$zoomin', $detail);\">";
	$html .= "Zoom In</button>\n";

	if ($detail) {
	    $html .= "<button name=hidedetail type=button value=0";
	    $html .= " onclick=\"VisChange('$zoom', 0);\">";
	    $html .= "Hide Details</button>\n";
	}
	else {
	    $html .= "<button name=showdetail type=button value=1";
	    $html .= " onclick=\"VisChange('$zoom', 1);\">";
	    $html .= "Show Details</button>\n";
	}
	$html .= "&nbsp &nbsp &nbsp &nbsp &nbsp &nbsp ";
	$html .= "<button name=fullscreenvis type=button value=1";
	$html .= " onclick=\"FullScreenVis();\">";
	$html .= "Full Screen</button>\n";
    }
    if ($which == "nsfile") {
	$nsdata = $experiment->NSFile();
	
	$html = "<pre><div align=left class=\"showexp_codeblock\">".
	    "$nsdata</div></pre>\n";

	$html .= "<button name=savens type=button value=1";
	$html .= " onclick=\"SaveNS();\">";
	$html .= "Save</button>\n";
    }
    return $html;
}

#
# Dump the visualization into its own iframe.
#
function ShowVis($pid, $eid, $zoom = 1.25, $detail = 1) {
    $html = "<div id=fee style='display: block; overflow: hidden; ".
	    "     position: relative; z-index:1010; height: 450px; ".
	    "     width: 90%; border: 2px solid black;'>\n".
            " <div id=myvisdiv style='position:relative;'>\n".
	    "   <img id=myvisimg border=0 style='cursor: move;' ".
	    "        onLoad=\"setTimeout('ShowVisInit();', 10);\" ".
	    "        src='top2image.php?pid=$pid&eid=$eid".
	    "&zoom=$zoom&detail=$detail'>\n".
	    " </div>\n".
	    "</div>\n";

    return $html;
}

#
# See if this request is to the above function. Does not return
# if it is. Otherwise return and continue on.
#
sajax_handle_client_request();

#
# Need some DB info.
#
$expindex   = $experiment->idx();
$expstate   = $experiment->state();
$isbatch    = $experiment->batchmode();
$linktest_running = $experiment->linktest_pid();
$paniced    = $experiment->paniced();
$panic_date = $experiment->panic_date();
$lockdown   = $experiment->lockdown();
$geniflags  = $experiment->geniflags();

if (! ($experiment_stats = $experiment->GetStats())) {
    TBERROR("Could not get experiment stats object for $expindex", 1);
}
$rsrcidx    = $experiment_stats->rsrcidx();
if (! ($experiment_resources = $experiment->GetResources())) {
    TBERROR("Could not get experiment resources object for $expindex", 1);
}

#
# Standard Testbed Header.
#
PAGEHEADER("$tag ($pid/$eid)");

echo "<script type='text/javascript' src='showexp.js'></script>\n";
#
# This has to happen ...
#
$bodyclosestring = "<script type='text/javascript'>SET_DHTML();</script>\n";

echo "<script type='text/javascript' src='js/wz_dragdrop.js'></script>";
echo "<script type='text/javascript' language='javascript'>\n";
sajax_show_javascript();
echo "StartStateChangeWatch('$pid', '$eid', '$expstate');\n";
echo "</script>\n";

#
# Get a list of node types and classes in this experiment
#
$query_result =
    DBQueryFatal("select distinct v.type,t1.class,v.fixed,".
		 "   t2.type as ftype,t2.class as fclass from virt_nodes as v ".
		 "left join node_types as t1 on v.type=t1.type ".
		 "left join nodes as n on v.fixed is not null and ".
		 "     v.fixed=n.node_id ".
		 "left join node_types as t2 on t2.type=n.type ".
		 "where v.eid='$eid' and v.pid='$pid'");
while ($row = mysql_fetch_array($query_result)) {
    if (isset($row['ftype'])) {
	$classes[$row['fclass']] = 1;
	$types[$row['ftype']] = 1;
    }
    else {
	$classes[$row['class']] = 1;
	$types[$row['type']] = 1;
    }
}

SUBPAGESTART();

SUBMENUSTART("$tag Options");

# Link to a new Trac ticket
if (!$student) {
    WRITESUBMENUBUTTON(
	"<b>Submit a Trouble Ticket</b>",
	"https://trac.deterlab.net/newticket?description=[experiment:$pid:$eid]"
    );
    WRITESUBMENUDIVIDER();
}

if ($expstate) {
    if ($experiment->logfile() && $experiment->logfile() != "") {
	WRITESUBMENUBUTTON("View Activity Logfile",
			   CreateURL("showlogfile", $experiment));
    }
    WRITESUBMENUDIVIDER();

    if (!$lockdown) {
        # Swap option.
	if ($isbatch) {
	    if ($expstate == $TB_EXPTSTATE_SWAPPED) {
		WRITESUBMENUBUTTON("Queue Batch Experiment",
				   CreateURL("swapexp", $experiment,
					     "inout", "in"));
	    }
	    elseif ($expstate == $TB_EXPTSTATE_ACTIVE ||
		    $expstate == $TB_EXPTSTATE_ACTIVATING) {
		WRITESUBMENUBUTTON("Stop Batch Experiment",
				   CreateURL("swapexp", $experiment,
					     "inout", "out"));
	    }
	    elseif ($expstate == $TB_EXPTSTATE_QUEUED) {
		WRITESUBMENUBUTTON("Dequeue Batch Experiment",
				   CreateURL("swapexp", $experiment,
					     "inout", "pause"));
	    }
	}
	else {
	    if (!$geniflags && $expstate == $TB_EXPTSTATE_SWAPPED) {
		WRITESUBMENUBUTTON("Swap Experiment In",
				   CreateURL("swapexp", $experiment,
					     "inout", "in"));
	    }
	    elseif ($expstate == $TB_EXPTSTATE_ACTIVE ||
		    ($expstate == $TB_EXPTSTATE_PANICED && $isadmin)) {
		WRITESUBMENUBUTTON("Swap Experiment Out",
				   CreateURL("swapexp", $experiment,
					     "inout", "out"));
	    }
	    elseif ($expstate == $TB_EXPTSTATE_ACTIVATING) {
		WRITESUBMENUBUTTON("Cancel Experiment Swapin",
				   CreateURL("swapexp", $experiment,
					     "inout", "out"));
	    }
	}
    
	if ($expstate != $TB_EXPTSTATE_PANICED) {
	    WRITESUBMENUBUTTON("Terminate Experiment",
			       CreateURL("endexp", $experiment));
	}

        # Batch experiments can be modifed only when paused.
	if ( $expstate == $TB_EXPTSTATE_SWAPPED ||
	    (!$isbatch && $expstate == $TB_EXPTSTATE_ACTIVE)) {
	    WRITESUBMENUBUTTON("Modify Experiment",
			       CreateURL("modifyexp", $experiment));
	}
	    WRITESUBMENUBUTTON("Make Experiment Risky",
			       CreateURL("rmodifyexp", $experiment));
    }

    
    if ($expstate == $TB_EXPTSTATE_ACTIVE) {
	WRITESUBMENUBUTTON("Modify Traffic Shaping",
			   CreateURL("delaycontrol", $experiment));
    }
}

WRITESUBMENUBUTTON("Modify Settings",
		   CreateURL("editexp", $experiment));

WRITESUBMENUDIVIDER();

if ($expstate == $TB_EXPTSTATE_ACTIVE) {
    if (!$geniflags) {
	WRITESUBMENUBUTTON("Link Tracing/Monitoring",
			   CreateURL("linkmon_list", $experiment));
    
	WRITESUBMENUBUTTON("Event Viewer",
			   CreateURL("showevents", $experiment));
    }
    
    #
    # Admin and project/experiment leaders get this option.
    #
    if ($experiment->AccessCheck($this_user, $TB_EXPT_UPDATE)) {
	WRITESUBMENUBUTTON("Update All Nodes",
			   CreateURL("updateaccounts", $experiment));
    }

    # Reboot option
    if ($experiment->AccessCheck($this_user, $TB_EXPT_MODIFY)) {
	WRITESUBMENUBUTTON("Reboot All Nodes",
			   CreateURL("boot", $experiment));
    }
}

if (($expstate == $TB_EXPTSTATE_ACTIVE ||
     $expstate == $TB_EXPTSTATE_ACTIVATING ||
     $expstate == $TB_EXPTSTATE_MODIFY_RESWAP) &&
    (STUDLY() || $EXPOSELINKTEST)) {
    WRITESUBMENUBUTTON(($linktest_running ?
			"Stop LinkTest" : "Run LinkTest"),
		       CreateURL("linktest", $experiment) . 
		       ($linktest_running ? "&kill=1" : ""));
}

if ($expstate == $TB_EXPTSTATE_ACTIVE) {
    if (!$geniflags && STUDLY() && isset($classes['pcvm'])) {
	WRITESUBMENUBUTTON("Record Feedback Data",
			   CreateURL("feedback", $experiment) .
			   "&mode=record");
    }
}

if (($expstate == $TB_EXPTSTATE_ACTIVE ||
     $expstate == $TB_EXPTSTATE_SWAPPED) &&
    !$geniflags && STUDLY()) {
    WRITESUBMENUBUTTON("Clear Feedback Data",
		       CreateURL("feedback", $experiment) . "&mode=clear");
    if (isset($classes['pcvm'])) {
	    WRITESUBMENUBUTTON("Remap Virtual Nodes",
			       CreateURL("remapexp", $experiment));
    }
}
    
WRITESUBMENUDIVIDER();

# History
WRITESUBMENUBUTTON("Show History",
                   "showstats.php?showby=expt&exptidx=$expindex");

WRITESUBMENUBUTTON("Duplicate Experiment",
                   "beginexp_html.php?copyid=$expindex");

if ($expstate == $TB_EXPTSTATE_ACTIVE) {
	if ($isadmin || STUDLY()) {
		SUBMENUSECTION("Beta-Test Options");
		WRITESUBMENUBUTTON("Restart Experiment",
			   	CreateURL("swapexp", $experiment,
				     	"inout", "restart"));
		WRITESUBMENUBUTTON("Replay Events",
			   	CreateURL("replayexp", $experiment));

		SUBMENUSECTIONEND();
	}

	if ($isadmin) {
		SUBMENUSECTION("Admin Options");
	
		if (!$geniflags) {
	    		WRITESUBMENUBUTTON("Send an Idle Info Request",
			       	CreateURL("request_idleinfo", $experiment));
	
	    		WRITESUBMENUBUTTON("Send a Swap Request",
			       	CreateURL("request_swapexp", $experiment));
		}
		WRITESUBMENUBUTTON("Force Swap Out (Idle-Swap)",
				   CreateURL("swapexp", $experiment,
					     "inout", "out", "force", 1));
	
		SUBMENUSECTIONEND();
	}
}
    
SUBMENUEND_2A();

echo "<br>\n";
echo "<script>\n";
echo "function FreeNodeHtml_CB(stuff) {
         getObjbyName('showexpusagefreenodes').innerHTML = stuff;
         setTimeout('GetFreeNodeHtml()', 60000);
      }
      function GetFreeNodeHtml() {
         x_FreeNodeHtml(FreeNodeHtml_CB);
      }
      setTimeout('GetFreeNodeHtml()', 60000);
      </script>\n";
	  
echo "<div id=showexpusagefreenodes>\n";
echo   ShowFreeNodes($this_user, $experiment->Group());
echo "</div>\n";

echo "<br>
      <a href='shownsfile.php?pid=$exp_pid&eid=$exp_eid'>
         <img border=1 alt='experiment vis'
              src='showthumb.php?idx=$rsrcidx'></a>";

SUBMENUEND_2B();

#
# The center area is a form that can show NS file, Details, or Vis.
# IE complicates this, although in retrospect, I could have used plain
# input buttons instead of the fancy rendering kind of buttons, which do not
# work as expected (violates the html spec) in IE. 
#
echo "<script type='text/javascript' language='javascript'>
        var li_current = 'li_settings';
        function Show(which) {
	    li = getObjbyName(li_current);
            li.style.backgroundColor = 'black';
            li.style.color = 'white';
            li.style.borderBottom = '1px solid #778';

            li_current = 'li_' + which;
	    li = getObjbyName(li_current);
            li.style.backgroundColor = 'white';
            li.style.color = 'black';
            li.style.borderBottom = '1px solid white';

            x_Show(which, 0, 0, Show_cb);
            return false;
        }
        function Show_cb(html) {
	    visarea = getObjbyName('showexp_visarea');
            if (visarea) {
                visarea.innerHTML = html;
            }
        }
        function ShowVisInit() {
            ADD_DHTML(\"myvisdiv\");
        }
        function VisChange(zoom, detail) {
            x_Show('vis', zoom, detail, Show_cb);
            return false;
        }
        function GraphChange(which) {
            x_Show('graphs', which, 0, Show_cb);
            return false;
        }
        function ShowEvents() {
            x_Show('details', 1, 0, Show_cb);
            return false;
        }
        function SaveDetails() {
            window.open('spitreport.php?pid=$pid&eid=$eid',
                        '_blank','width=700,height=400,toolbar=no,".
                        "resizeable=yes,scrollbars=yes,status=yes,".
	                "menubar=yes');
        }
        function SaveNS() {
            window.open('spitnsdata.php?pid=$pid&eid=$eid',
                        '_blank','width=700,height=400,toolbar=no,".
                        "resizeable=yes,scrollbars=yes,status=yes,".
	                "menubar=yes');
        }
        function FullScreenVis() {
	    window.location.replace('shownsfile.php?pid=$pid&eid=$eid');
        }
        function Setup() {
	    li = getObjbyName(li_current);
            li.style.backgroundColor = 'white';
            li.style.color = 'black';
            li.style.borderBottom = '1px solid white';
        }
      </script>\n";

#
# This is the topbar
#
echo "<div width=\"100%\" align=center>\n";
echo "<ul id=\"topnavbar\">\n";
echo "<li>
          <a href=\"#A\" class=topnavbar onfocus=\"this.hideFocus=true;\" ".
               "id=\"li_settings\" onclick=\"Show('settings');\">".
               "Settings</a></li>\n";
echo "<li>
          <a href=\"#B\" class=topnavbar onfocus=\"this.hideFocus=true;\" ".
               "id=\"li_vis\" onclick=\"Show('vis');\">".
               "Visualization</a></li>\n";
echo "<li>
          <a href=\"#C\" class=topnavbar onfocus=\"this.hideFocus=true;\"  ".
              "id=\"li_nsfile\" onclick=\"Show('nsfile');\">".
              "NS File</a></li>\n";
echo "<li>
          <a href=\"#D\" class=topnavbar onfocus=\"this.hideFocus=true;\" ".
              "id=\"li_details\" onclick=\"Show('details');\">".
              "Details</a></li>\n";

echo "</ul>\n";
echo "</div>\n";
echo "<div align=center id=topnavbarbottom>&nbsp</div>\n";

#
# Start out with details ...
#
echo "<div align=center width=\"100%\" id=\"showexp_visarea\">\n";
$experiment->Show();
echo "</div>\n";

if ($experiment->Firewalled() &&
    ($expstate == $TB_EXPTSTATE_ACTIVE ||
     $expstate == $TB_EXPTSTATE_PANICED ||
     $expstate == $TB_EXPTSTATE_ACTIVATING ||
     $expstate == $TB_EXPTSTATE_SWAPPING)) {
    echo "<center>\n";
    if ($paniced == 2) {
	#
	# Paniced due to failed swapout.
	# Only be semi-obnoxious (no blinking) since it was not their fault.
	#
	echo "<br><font size=+1 color=red>".
	     "Your experiment was cut off due to a failed swapout on $panic_date!".
	     "<br>".
	     "You will need to contact testbed operations to make further ".
  	     "changes (swap, terminate) to your experiment.</font>";
    }
    elseif ($paniced) {
	#
	# Paniced due to panic button.
  	# Full-on obnoxious is called for here!
	#
	echo "<br><font size=+1 color=red><blink>".
	     "Your experiment was cut off via the Panic Button on $panic_date!".
	     "<br>".
	     "You will need to contact testbed operations to make further ".
  	     "changes (swap, terminate) to your experiment.</blink></font>";
    }
    else {
	$panic_url = CreateURL("panicbutton", $experiment);
	
	echo "<br><a href='$panic_url'>
                 <img border=1 alt='panic button' src='panicbutton.gif'></a>";
	echo "<br><font color=red size=+2>".
	     " Press the Panic Button to contain your experiment".
	     "</font>\n";
    }
    echo "</center>\n";
}
SUBPAGEEND();

#
# Dump the node information.
#
echo "<center>\n";
SHOWNODES($exp_pid, $exp_eid, $sortby, $showclass);
echo "</center>\n";

if ($isadmin) {
    echo "<center>
          <h3>Experiment Stats</h3>
         </center>\n";

    $experiment->ShowStats();
}

#
# Get the active tab to look right.
#
echo "<script type='text/javascript' language='javascript'>
      Setup();
      </script>\n";

#
# Standard Testbed Footer
# 
PAGEFOOTER();
?>
