<?php
#
# EMULAB-COPYRIGHT
# Copyright (c) 2000-2007 University of Utah and the Flux Group.
# All rights reserved.
#

#
# Display a virgin form and exit.
# 
function INITFORM($formfields, $projlist)
{
    global $nsref, $guid, $copyid;
    
    $defaults = array();

    # These defaults possibly set below.
    $defaults["exp_pid"]          = "";
    $defaults["exp_gid"]          = "";
    $defaults["exp_id"]           = "";
    $defaults["exp_description"]  = "";

    #
    # This is for experiment copying ...
    #
    if (isset($copyid) && $copyid != "") {
	$defaults["copyid"] = $copyid;
    }
    else {
	unset($copyid);

        #
        # This stuff is here for netbuild. The initial post from netbuild will
        # include these; they point to the nsfile. The right approach for doing
        # this is to have another page for netbuild that does some magic and
        # redirects the browser to this page. 
        #
	if (isset($nsref) && $nsref != "" && preg_match("/^[0-9]+$/", $nsref))
	    $defaults["nsref"] = $nsref;
	else
	    unset($nsref);
	
	if (isset($guid) && $guid != "" &&  preg_match("/^[0-9]+$/", $guid))
	    $defaults["guid"] = $guid;
	else
	    unset($guid);
    }
    
    #
    # For users that are in one project and one subgroup, it is usually
    # the case that they should use the subgroup, and since they also tend
    # to be in the clueless portion of our users, give them some help.
    #
    if (count($projlist) == 1) {
	list($project, $grouplist) = each($projlist);

	if (count($grouplist) <= 2) {
	    $defaults["exp_pid"] = $project;
	    if (count($grouplist) == 1 || strcmp($project, $grouplist[0]))
		$defaults["exp_gid"] = $grouplist[0];
	    else
		$defaults["exp_gid"] = $grouplist[1];
	}
	reset($projlist);
    }

    $defaults["exp_swappable"]         = "1";
    $defaults["exp_noswap_reason"]     = "";
    $defaults["exp_idleswap"]          = "1";
    $defaults["exp_noidleswap_reason"] = "";
    $defaults["exp_idleswap_timeout"]  = TBGetSiteVar("idle/threshold");
    $defaults["exp_autoswap"]          = TBGetSiteVar("general/autoswap_mode");
    $defaults["exp_autoswap_timeout"]  = TBGetSiteVar("general/autoswap_threshold");
    $defaults["exp_localnsfile"]       = "";
    $defaults["exp_nsfile"]            = ""; # Multipart data.
    $defaults["exp_swapin"]            = "no";
    $defaults["exp_batched"]           = "no";
    $defaults["exp_linktest"]          = 3;
    $defaults["exp_savedisk"]          = "no";

    ### GORAN: NEO OPTIONS
    $defaults["exp_nodesfile"]         = ""; # 
    $defaults["exp_localnodesfile"]    = ""; # JSON description of nodes 
    $defaults["--server"] = "chef";
    $defaults["--port"] = "5320";
    $defaults["--loglevel"] = "debug";
    ### $formfields["--loglevel"] = "none";
    
    #
    # Allow formfields that are already set to override defaults
    #
    if (isset($formfields)) {
	while (list ($field, $value) = each ($formfields)) {
	    $defaults[$field] = $formfields[$field];
	}
    }

    SPITFORM($defaults, 0);
    return;
}

#
# Check form and return XML to the main page. 
# 
function CHECKFORM(&$formfields, $projlist)
{
    $errors = array();
    
    #
    # Must convert uploaded file for the XML page. We pass it along inline.
    # Check size though. 
    #

    $formfields['exp_nsfile'] = "";
    
    if (isset($_FILES['exp_nsfile']) && $_FILES['exp_nsfile']['size'] != 0) {
	if ($_FILES['exp_nsfile']['size'] > (1024 * 500)) {
	    $errors["Local NS File"] = "Too big!";
	}
	elseif ($_FILES['exp_nsfile']['name'] == "") {
	    $errors["Local NS File"] =
		"Local filename does not appear to be valid";
	}
	elseif ($_FILES['exp_nsfile']['tmp_name'] == "") {
	    $errors["Local NS File"] =
		"Temp filename does not appear to be valid";
	}
	elseif (!preg_match("/^([-\@\w\.\/]+)$/",
			    $_FILES['exp_nsfile']['tmp_name'])) {
	    $errors["Local NS File"] = "Temp path includes illegal characters";
	}
        #
        # For the benefit of the form. Remember to pass back actual filename, not
        # the php temporary file name. Note that there appears to be some kind
        # of breakage, at least in opera; filename has no path.
        #
        $formfields['exp_nsfile'] = $_FILES['exp_nsfile']['name'];

	if (count($errors)) {
	    SPITFORM($formfields, $errors);
	    PAGEFOOTER();
	    exit(1);
	}

	# Read the entire file into a string.
	$file_contents = file_get_contents($_FILES['exp_nsfile']['tmp_name']);
	if (!$file_contents || $file_contents == "") {
	    $errors["Local NS File"] = "Error reading file on server";
	    SPITFORM($formfields, $errors);
	    PAGEFOOTER();
	    exit(1);
	}
	$formfields["exp_nsfile_contents"] = urlencode($file_contents);
    }


###
$goran = 10;
if ($goran) {
    if (isset($_FILES['exp_nodesfile']) && $_FILES['exp_nodesfile']['size'] != 0) {
        if ($_FILES['exp_nodesfile']['size'] > (1024 * 500)) {
            $errors["Local JSON File"] = "Too big!";
        }
        elseif ($_FILES['exp_nodesfile']['name'] == "") {
            $errors["Local JSON File"] =
                "Local JSON filename does not appear to be valid";
        }
        elseif ($_FILES['exp_nodesfile']['tmp_name'] == "") {
            $errors["Local JSON File"] =
                "Temp filename does not appear to be valid";
        }
        elseif (!preg_match("/^([-\@\w\.\/]+)$/",
                            $_FILES['exp_nodesfile']['tmp_name'])) {
            $errors["Local JSON File"] = "Temp path includes illegal characters";
        }
        #
        # For the benefit of the form. Remember to pass back actual filename, not
        # the php temporary file name. Note that there appears to be some kind
        # of breakage, at least in opera; filename has no path.
        #
        $formfields['exp_nodesfile'] = $_FILES['exp_nodesfile']['name'];

        if (count($errors)) {
            SPITFORM($formfields, $errors);
            PAGEFOOTER();
            exit(1);
        }

        # Read the entire file into a string.
        $file_contents = file_get_contents($_FILES['exp_nodesfile']['tmp_name']);
        if (!$file_contents || $file_contents == "") {
            $errors["Local JSON File"] = "Error reading file on server";
            SPITFORM($formfields, $errors);
            PAGEFOOTER();
            exit(1);
        }
        $formfields["exp_nodesfile_contents"] = urlencode($file_contents);
    }
}
###
    $args    = array();
    $args[0] = $formfields;
    $xmlcode = xmlrpc_encode_request("dummy", $args);
    return $xmlcode;
}

#
# Spit the form out using the array of data.
#
function SPITFORM($formfields, $errors)
{
    global $TBDB_PIDLEN, $TBDB_GIDLEN, $TBDB_EIDLEN, $TBDOCBASE;
    global $view, $view_style, $projlist, $linktest_levels;
    global $EXPOSELINKTEST, $EXPOSEARCHIVE;
    global $EXPOSESTATESAVE;
    global $TBVALIDDIRS_HTML;
    global $WIKIDOCURL;

    PAGEHEADER("Create NEO Containerized Experiment"); 

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

              document.form1.action='nscheck.php';
              document.form1.target='nscheck';
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
    else {
       if (! isset($formfields['copyid'])) {
	if (!isset($formfields['nsref']) && !isset($view['quiet'])) {
	} else {
	    if (isset($view['plab_ns_message'])) {
		echo "<center>
                        <p><b>To finish creating your slice, edit the 
                              following information as needed, and click 
                              Submit.  PlanetLab <font size=+1 color=red>  
                              requires</font>  users
                              to provide detail on what their slice will
                              be doing via its description (i.e., what kind 
                              of network traffic it will be producing).
                              Be sure to read over the
                              <a href='http://www.planet-lab.org/php/aup/'>
                              PlanetLab AUP</a> if you haven't already.
                       </b></p>
                      </center>\n";
	    } else {
		echo "<p><b>Your automatically generated NS file has been " .
		     "uploaded.</b> To finish creating your experiment, " .
		     "please fill out the following information:</p>";
	    }
        }
       }
    }

    echo "<form name=form1 enctype=multipart/form-data
                onsubmit=\"return false;\"
                action=neo_beginexp_html.php method=post>\n";

    # Something funky going on ...
    echo "<input type=hidden name=beginexp value=Submit>\n";
    echo "<table align=center border=1>\n";

    #
    # Include view_style in a hidden field so that it gets preserved if there
    # are errors
    #
    if ($view_style) {
	echo "<input type='hidden' name='view_style' value='$view_style'>\n";
    }

    #
    # Select Project
    #
    if (isset($view['hide_proj']) && (count($projlist) == 1)) {
	# Just include the project as a hidden field - since the user has
	# only a single project, grab that project, which is the first thing
	# in $projlist
	list($project) = each($projlist);
	echo "<input type='hidden' name=\"formfields[exp_pid]\"
                     value='$project'>\n";
    } else {
	echo "<tr>
		  <td class='pad4'><font color=blue>Select Project:</td></font>
		  <td class='pad4'><select name=\"formfields[exp_pid]\">\n";

	# If just one project, make sure just the one option.
	if (count($projlist) != 1) {
	    echo "<option value=''>Please Select &nbsp</option>\n";
	}

	while (list($project) = each($projlist)) {
	    $selected = "";

	    if (strcmp($formfields["exp_pid"], $project) == 0)
		$selected = "selected";

	    echo "        <option $selected value=\"$project\">
				 $project </option>\n";
	}
	echo "       </select>";
	echo "    </td>
	      ";
    }

    #
    # Select a group
    #
    if (isset($view['hide_group'])) {
	if (isset($formfields['group'])) {
	    $group = $formfields['group'];
	} else {
	    $group = "";
	}
	echo "<input type='hidden' name=\"formfields[exp_gid]\"
                     value='$group'>\n";
    } else {
	echo "
		  <td class='pad4'><font color=blue>Group:</td></font>
		  <td class='pad4'><select name=\"formfields[exp_gid]\">
			<option value=''>Default Group </option>\n";

	reset($projlist);
	    while (list($project, $grouplist) = each($projlist)) {
		for ($i = 0; $i < count($grouplist); $i++) {
		$group    = $grouplist[$i];

		if (strcmp($project, $group)) {
		    $selected = "";

		    if (isset($formfields["exp_gid"]) &&
			isset($formfields["exp_pid"]) &&
			strcmp($formfields["exp_pid"], $project) == 0 &&
			strcmp($formfields["exp_gid"], $group) == 0)
			$selected = "selected";

		    echo "<option $selected value=\"$group\">
			       $project/$group</option>\n";
		}
	    }
	}
	echo "     </select>
	      <font size=-1>(Must be default or correspond to selected project)
	      </font>
		 </td>
	      </tr>\n";
    }

    #
    # Name:
    #
    echo "<tr>
              <td class='pad4'><font color=blue>Exp Name:</font>
              <font size='-1'>(No blanks)</font></td>
              <td class='pad4' class=left>
                  <input type=text
                         name=\"formfields[exp_id]\"
                         value=\"" . $formfields['exp_id'] . "\"
	                 size=$TBDB_EIDLEN
                         maxlength=$TBDB_EIDLEN >
              </td>
          ";

    #
    # Description
    #
    if (isset($view["plab_descr"])) {
          echo "
                    <td class='pad4'>Slice Description:<br>
                        <font size='-1'>(Please be detailed)</font></td>
                    <td class='pad4' class=left>
                        <textarea
                               name=\"formfields[exp_description]\"
	                       rows=5 cols=50>" .
	                       $formfields['exp_description'] .
                       "</textarea>
                    </td>
                </tr>\n";
    } else {
          echo "
                    <td class='pad4'><font color=blue>Description:</font>
                        </td>
                    <td class='pad4' class=left>
                        <input type=text
                               name=\"formfields[exp_description]\"
                               value=\"" . $formfields['exp_description'] . "\"
	                       size=60>
                    </td>
                </tr>\n";
    }

    #
    # NS file
    #
    if (isset($formfields['copyid'])) {
	$copyid = $formfields['copyid'];

	echo "<tr>
               <td class='pad4'>Copy of experiment $copyid: &nbsp</td>
               <td class='pad4'>
                   <a target=nsfile href=spitnsdata.php?copyid=$copyid>
                      Click for NS File</a>\n";

        echo "  </td>
                <input type=hidden name=\"formfields[copyid]\" value='$copyid'>
              </tr>\n";
    }
    elseif (isset($formfields['nsref'])) {
	$nsref = $formfields['nsref'];
	
	if (isset($formfields['guid'])) {
	    $guid = $formfields['guid'];
	    
	    echo "<tr>
                  <td class='pad4'>Your auto-generated NS file: &nbsp</td>
                      <input type=hidden name=\"formfields[nsref]\" value=$nsref>
                      <input type=hidden name=\"formfields[guid]\" value=$guid>
                  <td class='pad4'>
                      <a target=_blank
                                href=\"spitnsdata.php?nsref=$nsref&guid=$guid\">
                      View NS File</a></td>
                  </tr>\n";
        }
	else {
	    echo "<tr>
                   <td class='pad4'>Your auto-generated NS file: &nbsp</td>
                       <input type=hidden name=\"formfields[nsref]\"
                              value=$nsref>
                   <td class='pad4'>
                       <a target=_blank href=spitnsdata.php?nsref=$nsref>
                       View NS File</a></td>
                 </tr>\n";
        }
    }
    else {
	echo "<tr>
                  <td class='pad4'><font color=blue>Your TCL/NS file:</font><br>
		      <input type=submit enabled id=syntax name=syntax value='Syntax Check' 
onclick=\"SyntaxCheck();\" >
  		  </td>

                  <td><table cellspacing=0 cellpadding=0 border=0>
                    <tr>
                      <td class='pad4'>UPLOAD</td>
                      <td class='pad4'>
                        <input type=hidden name=MAX_FILE_SIZE value=512000>
	                <input type=file
                               name=exp_nsfile
                               value=\"" . $formfields['exp_nsfile'] . "\"
	                       size=40
			       onchange=\"this.form.syntax.disabled=(this.value=='')\">
                      </td>
                    
                    </tr><tr>
                      <td class='pad4'>or SELECT<br>
                              <font size='-3'>(" . $TBVALIDDIRS_HTML .
	    		      ")</font></td>
                      <td>
	                <input type=text
                               name=\"formfields[exp_localnsfile]\"
                               value=\"" . $formfields['exp_localnsfile'] . "\"
	                       size=44
			       onchange=\"this.form.syntax.disabled=(this.value=='')\">
                      </td>
                    </tr></table></td>\n";
    }

    #
    #
    # Add in hidden fields to send swappable and noswap_reason, since
    # they do not show on the form
    echo "<input type=hidden name=\"formfields[exp_swappable]\"
                 value='" . $formfields['exp_swappable'] . "'>\n";
    echo "<input type=hidden name=\"formfields[exp_noswap_reason]\" value='";
    echo htmlspecialchars($formfields['exp_noswap_reason'], ENT_QUOTES);
    echo "'>\n";
    
    if (isset($view['hide_swap'])) {
	$idlevars = array('exp_idleswap','exp_noidleswap_reason',
			  'exp_idleswap_timeout',
	                  'exp_autoswap','exp_autoswap_timeout');
	while (list($index,$value) = each($idlevars)) {
	    if (isset($formfields[$value])) {
		echo "<input type='hidden' name='formfields[$value]'
                             value='$formfields[$value]'>\n";
	    }
	}
    }
    else {  ## swapping no hide
	echo " <td class='pad4'> <a href='$WIKIDOCURL/Swapping%23swapping' target='_blank'>
	       <font color=blue>Swapping:</font></td> 
	       <td> <table cellpadding=0 cellspacing=0 border=0><tr>
	       <td><input type='checkbox' name='formfields[exp_idleswap]' value='1'";

	if (isset($formfields['exp_idleswap']) && $formfields['exp_idleswap'] == "1") {
	    echo " checked='1'";
	}

	echo "></td>
               <td><a href='$WIKIDOCURL/Swapping%23idleswap' target='_blank'>
	       <b>Idle-Swap:</b></a> Swap out after 
	       <input type='text' name='formfields[exp_idleswap_timeout]' value='";
	echo htmlspecialchars($formfields['exp_idleswap_timeout'], ENT_QUOTES);
	echo "' size='3'> hours idle. If not why not?</td>
	       </tr><tr><td></td>
	       <td><textarea rows=2 cols=50 name='formfields[exp_noidleswap_reason]'>";
			      
	echo htmlspecialchars($formfields['exp_noidleswap_reason'],ENT_QUOTES);
	echo "</textarea></td>
	      </tr><tr>
	      <td><input type='checkbox' name='formfields[exp_autoswap]' value='1' ";

	if (isset($formfields['exp_autoswap']) &&
	    $formfields['exp_autoswap'] == "1") {
	    echo " checked='1'";
	}

	echo "></td>
	       <td><a href='$WIKIDOCURL/Swapping%23autoswap' target='_blank'>
	       <b>Max. Duration:</b></a> Swap out after
	       <input type='text' name='formfields[exp_autoswap_timeout]' value='";
	echo htmlspecialchars($formfields['exp_autoswap_timeout'], ENT_QUOTES);
	echo "' size='3'> hours.";

	if (STUDLY() || $EXPOSESTATESAVE) {
		echo "<tr><td>
	         <input type=checkbox name='formfields[exp_savedisk]'
	         value='Yep'";

		if (isset($formfields['exp_savedisk']) &&
			strcmp($formfields['exp_savedisk'], "Yep") == 0) {
		    	echo " checked='1'";
	    	}

		echo "></td>\n";
	    	echo "<td><a href='$WIKIDOCURL/Swapping%23swapstatesave'>
			  <b>State Saving:</b></a> Save disk state on swapout</td>
			  </tr>";
	}
	echo "</table></td></tr>";
    }
##########################################################################################
   {
        echo "<tr>
                  <td class='pad4'><font color=blue>Your JSON node description file:</font>
                      <input type=submit disabled id=syntax name=syntax value='NO Syntax Check yet' >
                  </td>
     
                  <td><table cellspacing=0 cellpadding=0 border=0>
                    <tr>
                      <td class='pad4'>UPLOAD </td>
                      <td class='pad4'>
                        <input type=hidden name=MAX_FILE_SIZE value=512000>
                        <input type=file
                               name=exp_nodesfile
                               value=\"" . $formfields['exp_nodesfile'] . "\"
                               size=40
                               onchange=\"this.form.syntax.disabled=(this.value=='')\">
                      </td>

                    </tr><tr>
                      <td class='pad4'>or SELECT<br>
                              <font size='-3'>(". $TBVALIDDIRS_HTML .")</font></td>
                      <td>
                        <input type=text
                               name=\"formfields[exp_localnodesfile]\"
                               value=\"" . $formfields['exp_localnodesfile'] . "\"
                               size=44
                               onchange=\"this.form.syntax.disabled=(this.value=='')\">
                      </td>
                    </tr></table></td>\n";
    }
 
        echo " <td class='pad4'> 
               <font color=blue>NEO OPTIONS:</font></td>

               <td><table cellpadding=0 cellspacing=0 border=0>
                   <tr><td><b>CHEF Server: </b></td> 
                        <td> <class='pad4' class=left> <input type=text name=\"formfields[--server]\"
                         value=\"" . $formfields['--server'] . "\" size=27  maxlength=44>
                         <font size='-2'> (default: chef)</font> </td>
                   </tr>

                   <tr> <td> <b>Port Number: </b></td> 
                        <td> <class='pad4' class=left> <input type=text name=\"formfields[--port]\"
                         value='5320' size=5  maxlength=5> <font size='-2'> (default: 5320)</font></td>
                   </tr>

                   <tr><td> <b>Log Level:</b></td>
                   <td><select id=\"myLogLevel\" name=\"myLogLevel\"  >
                          <option value='debug'>debug </option>";
			    $neologlevels = array();
			    $neologlevels[0] = "debug";
			    $neologlevels[1] = "debug";
			    $neologlevels[2] = "info";
			    $neologlevels[3] = "warning";
			    $neologlevels[4] = "error";
			    $neologlevels[5] = "critical";

                          for ($i = 1; $i <= 5; $i++) {
                                echo "        <option value=$neologlevels[$i]>" . $neologlevels[$i] . "</option>\n";  
                         }
                         ##if(isset($_POST['formSubmit']) )
                         #{
                         #     $formfields['--loglevel'] = $_POST['myLogLevel'];
                         #}
                   echo "    </select></td>\n</tr>";
                   if(isset($_POST['myLogLevel']) )
                   {
                        $formfields['--loglevel'] = $_POST['myLogLevel'];
                   }
 

       echo "</td>\n";
       echo "</td></table>";
#########################################################################################
    #
    # Run linktest, and level.
    #
    if (STUDLY() || $EXPOSELINKTEST) {
      if (isset($view['hide_linktest'])) {
        if ($formfields['exp_linktest']) {
          echo "<input type='hidden' name='formfields[exp_linktest]'
                       value='" . $formfields['exp_linktest'] . "'\n";
        }
      } else {
    echo "<tr>
              <td><a href='$WIKIDOCURL/linktest' target='_blank'>Linktest</a> Option:</td>
              <td><select name=\"formfields[exp_linktest]\">
                          <option selected value=0>Skip Linktest </option>\n";

    for ($i = 1; $i <= TBDB_LINKTEST_MAX; $i++) {
        $selected = "";

        #Disabled for now.
        #if (strcmp($formfields[exp_linktest], "$i") == 0)
        #    $selected = "selected";

        echo "        <option $selected value=$i>Level $i - " .
            $linktest_levels[$i] . "</option>\n";
    }
    echo "       </select>";
    echo "    (<a href='$WIKIDOCURL/linktest' target='_blank'><b>What is this?</b></a>)";
    echo "    </td>
          </tr>\n";
      }
    }

    echo "<tr>
              <td class='pad4' align=center colspan=2>
                 <b><input type=button value=Submit name=beginexp
                           onclick=\"this.disabled = true; NormalSubmit();\"></b>
              </td>
         </tr>
        </form>
        </table>\n";

    if (!isset($view['quiet'])) {
	echo "<p>
	      <h3>Handy Links:</h3>
	      <ul>
                  <li> View a <a href='showosid_list.php' target='_blank'>list
                      of OSIDs</a> that are available for you to use in your NS
                      file.</li>
		 <li> Create your own <a href='newimageid_ez.php' target='_blank'>
		      custom disk images</a>.</li>
	      </ul>\n";
    }
}
?>
