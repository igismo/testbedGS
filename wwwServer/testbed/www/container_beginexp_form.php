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

    ### GORAN:OPTIONS
    $defaults["--config"]       = ""; 
    $defaults["--debug"] = "";
    $defaults["--pnode-types"] = "";
    $defaults["--openvz-template"] = "";
    $defaults["--end-node-shaping"] = "";
    $defaults["--packing"] = "10";
    $defaults["--force-partition"] = "";
    $defaults["--size"] = "";
    $defaults["--vde-switch-shaping"] = "";
    $defaults["--no-image"] = "--image";
    $defaults["--pass-pnodes"] = ""; 
    $defaults["--nodes-only"] = "";
    $defaults["--default-container"] = "";
    $defaults["--openvz-diskspace"] = "";
    $defaults["--openvz-template-dir"] = "";
    $defaults["--pass-nodes-only"] = "";
    $defaults["--pass-size"] = "";
    $defaults["--pass-pack"] = "";
    $defaults["--pre-routing"] = "";
    $defaults["--pnode-limit"] = "";
    $defaults["--prefer-qemu-users"] = "";
    $defaults["--keep-tmp"] = "";
    
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
    $args    = array();
    $args[0] = $formfields;
    $xmlcode = xmlrpc_encode_request("dummy", $args);
    return $xmlcode;
}

#
# Spit the form out using the array of data.
#
#              window.open('','ContainerizeWin','width=650,height=400,toolbar=no,".
#                              "resizeable=yes,scrollbars=yes,status=yes,".
#                              "menubar=yes');

function SPITFORM($formfields, $errors)
{
    global $TBDB_PIDLEN, $TBDB_GIDLEN, $TBDB_EIDLEN, $TBDOCBASE;
    global $view, $view_style, $projlist, $linktest_levels;
    global $EXPOSELINKTEST, $EXPOSEARCHIVE;
    global $EXPOSESTATESAVE;
    global $TBVALIDDIRS_HTML;
    global $WIKIDOCURL;

    PAGEHEADER("Create Containerized Experiment"); 


    echo "<script language=JavaScript>
          <!--
          function PythonSubmit() {
              var action = document.form1.action;
               window.open('','ContainerizeWin','width=650,height=400,toolbar=no,".
                              "resizeable=yes,scrollbars=yes,status=yes,".
                              "menubar=yes');             
              document.form1.action='container_create.php';
              document.form1.target='ContainerizeWin';
              document.form1.submit();
                        
              document.form1.action=action;
              document.form1.target='_self';
              document.form1.submit();

          }
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
#GORAN commented out
#    echo "<form name=form1 enctype=multipart/form-data
#                onsubmit=\"return false;\"
#                action=beginexp_html.php method=post>\n";
     echo "<form name=form1 enctype=multipart/form-data
                onsubmit=\"return false;\"
                method=post>\n";
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
                  <td class='pad4'><font color=blue>Your TCL/NS file:</font>/n
		      <input type=submit disabled id=syntax name=syntax value='Syntax Check' onclick=\"SyntaxCheck();\">
  		  </td>

                  <td><table cellspacing=0 cellpadding=0 border=0>
                    <tr>
                      <td class='pad4'>Upload
			<font size='-1'>(500k&nbsp;max)</font></td>
                      <td class='pad4'>
                        <input type=hidden name=MAX_FILE_SIZE value=512000>
	                <input type=file
                               name=exp_nsfile
                               value=\"" . $formfields['exp_nsfile'] . "\"
	                       size=40
			       onchange=\"this.form.syntax.disabled=(this.value=='')\">
                      </td>
                    
                    </tr><tr>
                      <td class='pad4'>or Upload from Server<br>
                              <font size='-1'>(" . $TBVALIDDIRS_HTML .
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
    # Swapping
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

	#
    	# Swap In Immediately (aka exp_swapin)
        #
	if (isset($view['hide_swapin'])) {
        	if ($formfields['exp_swapin']) {
            		echo "<input type='hidden' name='formfields[exp_swapin]'
                         value='" . $formfields['exp_swapin'] . "'>\n";
        	}
	} else {
        	echo "<input type=checkbox name='formfields[exp_swapin]' value='Yep'";

	        if (isset($formfields['exp_swapin']) && strcmp($formfields['exp_swapin'], "Yep") == 0) {
			echo " checked='1'";
		}

        	echo ">\n";
	        echo "<b>Swap In Immed</b></td>\n";
	}

 	#
    	# Batch Experiment?
    	#
	if (isset($view['hide_batch'])) {
        	if ($formfields['exp_batched']) {
			echo "<input type='hidden' name='formfields[exp_batched]'
                        value='" . $formfields['exp_batched'] . "'\n";
        	}
    	} else {
        	echo "</tr><tr>
		<td class='pad4' colspan=2>
          	<input type=checkbox name='formfields[exp_batched]'  value='Yep'";

        	if (isset($formfields['exp_batched']) && strcmp($formfields['exp_batched'], "Yep") == 0) {
                	echo " checked='1'";
        	}

        	echo ">";
        	echo "<b>Batch Mode Experiment</b><font size='-1'>(See
                <a href='$WIKIDOCURL/Tutorial' target='_blank'>Tutorial</a>
                for more information)</font>
         	</td>
         	</tr>\n";
	}

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
                  	<td><a href='$WIKIDOCURL/linktest' target='_blank'>
                            <font color=blue>Linktest Option:</font></td>

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
    			echo "    </td>\n";
		}
	}
	# End of Link Test

        ### SUBMIT BUTTON
         echo " 
              <td class='pad4' align=center colspan=2>
                  <b><input type=button class=button1 value=SUBMIT name=beginexpXXX
                 onclick=\"this.disabled = true; PythonSubmit(); \"></b>
              </td>
         </tr>";


###############  Input all the options for containerize.py script *******************
  echo "<tr>
              <td class='pad4' align=center colspan=4> <font size=+2 color=blue> <b> O P T I O N S</b></font>
              </td>
         </tr>";

########## Name of the file containing options
      echo "<tr>
           <td class='pad4'><font color=blue>Options File</font>
                              <font size='-3'>(". $TBVALIDDIRS_HTML . ")</font></td>
           <td><input type=text name=\"formfields[--config]\"
                               value=\"" . $formfields['--config'] . "\" size=44
                               onchange=\"this.form.syntax.disabled=(this.value=='')\"> (--config=)
           </td>";
########## Create Visualization of the virtual topology      
      if (isset($view['hide_swapin'])) {
                if ($formfields['--no-image']) {
                        echo "<input type='hidden' name='formfields[--no-image]' value='" . $formfields['--no-image'] . "'>\n";
                }
        } else {
                echo "<td class=pad4 colspan=2><input type=checkbox name='formfields[--no-image]' value='--image'";
                if (isset($formfields['--no-image']) && strcmp($formfields['--no-image'], "--image") == 0) {
                        echo " checked='1'";
                }
                echo "><b>Create visualization of the virtual topology = --image </b></td>\n";
        }     
        echo "</tr>";

########## pnode_types
	echo "<tr>
              <td class='pad4'><font color=blue>Pnode Types:</font>
              <font size='-3'>list of node types</font></td>
              <td class='pad4' class=left>
                  <input type=text name=\"formfields[--pnode-types]\"
                         value=\"" . $formfields['--pnode-types'] . "\"
                         size=44  maxlength=44> (--pnode-type=)
              </td>
          ";
######### DEBUG
       if (isset($view['hide_swapin'])) {
                if ($formfields['--debug']) {
                        echo "<input type='hidden' name='formfields[--debug]' value='" . $formfields['--debug'] . "'>\n";
                }
        } else {
                echo "<td class=pad4 colspan=2><input type=checkbox name='formfields[--debug]' value='--debug'";
                if (isset($formfields['--debug']) && strcmp($formfields['--debug'], "--debug") == 0) {
                        echo " checked='1'";
                }
                echo "><b>Enable debugging output and leave failed experiments = --debug</b></td>\n";
        }
        echo "</tr>";
########## --openvz-template
        echo "<tr>
              <td class='pad4'><font color=blue>OpenVZ Template:</font> 
              <font size='-3'>default is Ubuntu10.04-x86</font></td>
              <td class='pad4' class=left>
                  <input type=text name=\"formfields[--openvz-template]\"
                         value=\"" . $formfields['--openvz-template'] . "\"
                         size=44  maxlength=44> (--openvz-template=)
              </td>
          ";
######### --end-node-shaping
       if (isset($view['hide_swapin'])) {
                if ($formfields['--debug']) {
                        echo "<input type='hidden' name='formfields[--end-node-shaping]' 
                         value='" . $formfields['--end-node-shaping'] . "'>\n";
                }
        } else {
                echo "<td class=pad4 colspan=2><input type=checkbox name='formfields[--end-node-shaping]' 
                     value='--end-node-shaping'";
                if (isset($formfields['--end-node-shaping']) && strcmp($formfields['--end-node-shaping'], 
                     "--end-node-shaping") == 0) {
                        echo " checked='1'";
                }
                echo "><b>Enable end node shaping = --end-node-shaping</b></td>\n";
        }
        echo "</tr>";

########## --packing=<number>
        echo "<tr>
              <td class='pad4'><font color=blue>Packing Factor:</font> 
              <font size='-3'>default is 10</font></td>
              <td class='pad4' class=left>
                  <input type=text name=\"formfields[--packing]\"
                         value=\"" . $formfields['--packing'] . "\"
                         size=4  maxlength=4>  ( --packing=number )
              </td>
          ";
######### --force-partition                       
       if (isset($view['hide_swapin'])) {
                if ($formfields['--force-partition']) {
                        echo "<input type='hidden' name='formfields[--force-partition]' value='" . $formfields['--force-partition'] . "'>\n";
                }
        } else {
                echo "<td class=pad4 colspan=2><input type=checkbox name='formfields[--force-partition]' value='--force-partition'";
                if (isset($formfields['--force-partition']) && strcmp($formfields['--force-partition'], "--force-partition") == 0) {
                        echo " checked='1'";
                }
                echo "><b>Repartition the experiment by force = --force-partition</b></td>\n";
        }
        echo "</tr>";

########## --size=<number>
        echo "<tr>
              <td class='pad4'><font color=blue># Nodes to use:</font>
              <font size='-3'> no default </font></td>
              <td class='pad4' class=left>
                  <input type=text name=\"formfields[--size]\"
                         value=\"" . $formfields['--size'] . "\"
                         size=4  maxlength=4>  ( --size=number )
              </td>
          ";
######### --vde-switch-shaping
       if (isset($view['hide_swapin'])) {
                if ($formfields['--vde-switch-shaping']) {
                        echo "<input type='hidden' name='formfields[--vde-switch-shaping]' value='" . $formfields['--vde-switch-shaping'] . "'>\n";
                }
        } else {
                echo "<td class=pad4 colspan=2><input type=checkbox name='formfields[--vde-switch-shaping]' value='--vde-switch-shaping'";
                if (isset($formfields['--vde-switch-shaping']) && strcmp($formfields['--vde-switch-shaping'], "--vde-switch-shaping") == 0) {
                        echo " checked='1'";
                }
                echo "><b>Enable VDE switch shaping = --vde-switch-shaping</b></td>\n";
        }
        echo "</tr>";

########## --pass-pnodes=<number>
        echo "<tr>
              <td class='pad4'><font color=blue> Assign Pnodes to pass:</font></td>
              <td><input type=text name=\"formfields[--pass-pnodes]\"
                         value=\"" . $formfields['--pass-pnodes'] . "\" size=44
                         onchange=\"this.form.syntax.disabled=(this.value=='')\"> (--pass-pnodes=)
              </td>

          ";

######### --nodes-only
       if (isset($view['hide_swapin'])) {
                if ($formfields['--nodes-only']) {
                        echo "<input type='hidden' name='formfields[--nodes-only]' value='" . $formfields['--nodes-only'] . "'>\n";
                }
        } else {
                echo "<td class=pad4 colspan=2><input type=checkbox name='formfields[--nodes-only]' value='--nodes-only'";
                if (isset($formfields['--nodes-only']) && strcmp($formfields['--nodes-only'], "--nodes-only") == 0) {
                        echo " checked='1'";
                }
                echo "><b>Only consider nodes for packing = --nodes-only</b></td>\n";
        }
        echo "</tr>";

########## --default-container=[openvz,qemu,process]
        echo "<tr>
              <td class='pad4'><font color=blue> Default Container:</font></td>
              <td><input type=text name=\"formfields[--default-container]\"
                         value=\"" . $formfields['--default-container'] . "\" size=10
                         onchange=\"this.form.syntax.disabled=(this.value=='')\"> 
                         ( --default-container=[<b>openvz</b>,qemu,process])
              </td>

          ";

######### --openvz-diskspace=number [M,G]
      echo "<td class='pad4'>
              <font color=blue> VZ Disk: </font></td>
              <td><input type=text name=\"formfields[--openvz-diskspace]\"
                         value=\"" . $formfields['--openvz-diskspace'] . "\" size=10
                         onchange=\"this.form.syntax.disabled=(this.value=='')\"> 
                         ( --openvz-diskspace=xxx M/G)
              </td>

       </tr>";

########## --openvz-template-dir=dir
      echo "<tr>
              <td class='pad4'><font color=blue> Additional Openvz Dirs:</font></td>
              <td><input type=text name=\"formfields[openvz-template-dir]\"
                         value=\"" . $formfields['--openvz-template-dir'] . "\" size=44
                         onchange=\"this.form.syntax.disabled=(this.value=='')\">
                         (--openvz-template-dir=)
              </td>

          ";

######### --pass-nodes-only
      echo "<td class='pad4'>
              <font color=blue> NodesOnly: </font></td>
              <td><input type=text name=\"formfields[--pass-nodes-only]\"
                         value=\"" . $formfields['--pass-nodes-only'] . "\" size=20
                         onchange=\"this.form.syntax.disabled=(this.value=='')\">
                         ( --pass-nodes-only=pass,pass,)
              </td>

       </tr>";

########## --pass-size=pass:size,pass:size
      echo "<tr>
              <td class='pad4'><font color=blue> Pass Size (pass:size):</font></td>
              <td><input type=text name=\"formfields[--pass-size]\"
                         value=\"" . $formfields['--pass-size'] . "\" size=44
                         onchange=\"this.form.syntax.disabled=(this.value=='')\">
                         (--pass-size=pass:size,)
              </td>
         
          ";
                  
######### --pass-pack=k:l,m:n,..
      echo "<td class='pad4'>
              <font color=blue> Pass Pack: </font></td>
              <td><input type=text name=\"formfields[--pass-pack]\"
                         value=\"" . $formfields['--pass-pack'] . "\" size=20
                         onchange=\"this.form.syntax.disabled=(this.value=='')\">
                         ( --pass-pack=pass:pack,..)
              </td>
                
       </tr>";
     
########## --pre-routing=file
      echo "<tr>
              <td class='pad4'><font color=blue> Pre Routing File Name:</font></td>
              <td><input type=text name=\"formfields[--pre-routing]\"
                         value=\"" . $formfields['--pre-routing'] . "\" size=44
                         onchange=\"this.form.syntax.disabled=(this.value=='')\">
                         (--pre-routing=)
              </td>
              
          ";
                                                           
######### --pnode-limit=#interfaces
      echo "<td class='pad4'>
              <font color=blue> IF Limit </font></td>
              <td><input type=text name=\"formfields[--pnode-limit]\"
                         value=\"" . $formfields['--pnode-limit'] . "\" size=4
                         onchange=\"this.form.syntax.disabled=(this.value=='')\">
                         ( --pnode-limit=num mpx interfaces)
              </td>

       </tr>";

########## --prefer-qemu-users=users,...
      echo "<tr>
              <td class='pad4'><font color=blue> Prefer Qemu Users:</font></td>
              <td><input type=text name=\"formfields[--prefer-qemu-users]\"
                         value=\"" . $formfields['--prefer-qemu-users'] . "\" size=44
                         onchange=\"this.form.syntax.disabled=(this.value=='')\">
                         (--prefer-qemu-users=)
              </td>
              
          ";
######### --keep-tmp
       if (isset($view['hide_swapin'])) {
		if ($formfields['--keep-tmp']) {
                        echo "<input type='hidden' name='formfields[--keep-tmp]' value='" . $formfields['--keep-tmp'] . "'>\n";
                }
        } else {
                echo "<td class=pad4 colspan=2><input type=checkbox name='formfields[--keep-tmp]' value='--keep-tmp'";
                if (isset($formfields['--keep-tmp']) && strcmp($formfields['--keep-tmp'], "--keep-tmp") == 0) {
                        echo " checked='1'";
                }
                echo "><b>Keep temporary files = --keep-tmp</b></td>\n";
        }
        echo "</tr>";                      
#######################################################
    echo "</form>
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
