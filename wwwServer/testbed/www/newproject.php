<?php
#
# EMULAB-COPYRIGHT
# Copyright (c) 2000-2009 University of Utah and the Flux Group.
# All rights reserved.
#
include("defs.php");
include("new_user_form.php");

#
# No PAGEHEADER since we spit out a Location header later. See below.
# 

#
# Get current user.
#
$this_user = CheckLogin($check_status);

#
# Verify page arguments.
#
$optargs = OptionalPageArguments("submit",       PAGEARG_STRING,
                                 "finished",     PAGEARG_BOOLEAN,
                                 "formfields",   PAGEARG_ARRAY);

#
# See if we are in an initial Emulab setup.
#
$FirstInitState = (TBGetFirstInitState() == "createproject");

#
# If a uid came in, then we check to see if the login is valid.
# If the login is not valid. We require that the user be logged in
# to start a second project.
#
if ($this_user && !$FirstInitState) {
    # Allow unapproved users to create multiple projects ...
    # Must be verified though.
    CheckLoginOrDie(CHECKLOGIN_UNAPPROVED|CHECKLOGIN_WEBONLY);
    $uid = $this_user->uid();
    $returning = 1;
}
else {
    #
    # No uid, so must be new.
    #
    $returning = 0;
}
unset($addpubkeyargs);

#
# Some global Variables
#

# Arrays for various select form elements
$CHOICES["proj_org"] = array("Academic", "Industry", "Government", "Internal");

$CHOICES["proj_class"] = array("Yes", "No");

$CHOICES["proj_research_type"] = array("Architecture", "Botnets", "Class", "Comprehensive", "Congestion", "DDoS", "DNS", "Evaluation", "Forensics", "Infrastructure", "Internal", 
                          "Intrusions", "Malware", "Metrics", "Monitoring", "Multicast", 
                          "Overlays", "Privacy", "Routing", "Scanning", "Spam", "Spoofing", "Testbeds", 
                          "Traceback", "Trust", "Watermarking", "Wireless", "Worms", "Other");

 $CHOICES["proj_funders"] = array("Government Research Grant", "Other Government Contract", "Academic Institution Support", "Corporate Funding", "Private Funding", "Other");

 $CHOICES["proj_public"] = array("Yes", "No");

#
# Spit a select HTML element
#
function SPITSELECT($element_name, $default_option, $options) {
  echo "<select name=\"$element_name\" id=\"$element_name\" required>";
  echo "<option></option>";
  foreach ($options as $choice) {
    if (strcmp($choice, $default_option) == 0) {
          echo "<option value=\"$choice\" selected=\"selected\">$choice</option>";
    } else {
          echo "<option value=\"$choice\">$choice</option>";
    }
  }
  echo "</select>";

}

#
# Spit the form out using the array of data. 
# 
function SPITFORM($formfields, $returning, $errors)
{
    global $TBDB_UIDLEN, $TBDB_PIDLEN, $TBDOCBASE, $WWWHOST;
    global $usr_keyfile, $FirstInitState;
    global $USERSELECTUIDS;
    global $WIKIDOCURL;
    global $CHOICES;
    
    # page header with additional CSS
    PAGEHEADER("Start Using DeterLab: New Project Application Form", NULL, "");


    #
    # First initialization gets different text
    #
    if ($FirstInitState == "createproject") {
        echo "<div class=createproject><p>
              Please create your initial project.<br> A good Project Name
              for your first project is probably 'testbed', but you can
              choose anything you like.
              </p></div>\n";
    }
    else {
	echo "<div class=\"newprojectintro\">
          <p><strong>To start using DeterLab, you need to apply for a project and
	        user account using this web form</strong>. Before doing so, please first
	        read the instructions on what type of DeterLab access you
	        need &#45; <a
	        href=\"https://trac.deterlab.net/wiki/GettingStarted\">Get
	        Started in DeterLab</a> &#45; and read the <a
	        href=\"https://trac.deterlab.net/wiki/Policy\">DeterLab Usage
	        Policy</a>. Then use the appropriate web form to submit
	        your request.</p>\n";

	echo "<p><strong>For PIs,  project leaders, and sponsors only</strong>, the
	        form below is the mechanism to provide DETER project staff
	        with key information required to confirm eligibility to use
	        DeterLab and to set up a user account. A DETER project staff
	        member will contact you using the email address you provide.
	        <i>An organization-affiliated email address is required.
	        Other email addresses (Gmail, Hotmail, email addresses from
	        ISPs) are not accepted.</i></p>
          </div>\n";

        if (! $returning) {
            echo "<div class=loginmsg>
                   If you already have a DETERLab account,
                   <a href=login.php?refer=1>
                   <span class=imp-color>please log on first!</span></a>. <br/>If you are
         	        still not quite ready to start and need additional assistance,
         	        please <a
         	        href=http://www.deter-project.org/participate-deter-research-community>contact
         	        us</a>.<br/><br/>
                   <strong>Students may not fill out this form.</strong> Students must have a <strong>faculty sponsor</strong> fill out this form in order to create a supervised project. Your sponsor will give you instructions on how to join as a member if the application is approved.
                   </div>\n";
        }
    }

    if ($errors) {
        echo "<div class=fixerrors>
                <p>
                  Oops, please fix the following errors!
                </p>
              </div>\n";

        while (list ($name, $message) = each ($errors)) {
            echo "<div class=errormsg>
                    <p><span class=error-name>$name</span>: 
                      <span class=error-msg>$message</span>
                    </p>
                  </div>\n";
        }
    }

    # new users have some non-required fields
    $non_required = '';
    if (!$returning)
        $non_required = 'except those with a gray background';

    echo "<div class=formwrap><form enctype=multipart/form-data name=myform id=newproject
                action=newproject.php method=post>\n";

    if (! $returning) {
        #
        # Start user information stuff. Presented for new users only.
        #
        echo "<h3 class=formheader>
                      Project Leader Information:<br/>
                      <span class=form_note>
                      All fields are required
                      </span>
              </h3>\n";

        if ($USERSELECTUIDS || $FirstInitState == "createproject")
          SPITNEWUSERFORM($formfields, 1, 1);
        else
          SPITNEWUSERFORM($formfields, 0, 1);
    }

    #
    # Project information
    #
    echo "<h3 class=formheader>
               Project Information: 
          </h3>\n";

    #
    # Project Name:
    #
    echo "<div class=field>
              <label for=\"formfields[proj_name]\">Project Name: <span class=help-text>Your project's name or brief description.</span></label> 
              
              <input type=text 
                required 
                aria-required=true 
                name=\"formfields[proj_name]\"
                id=\"formfields[proj_name]\"
                value=\"" . $formfields["proj_name"] . "\"
                >
          </div>\n";
    #
    # Project Plan:
    #
    echo "<div class=field>
            <label for=\"formfields[proj_why]\">Project Description: <span class=help-text>Briefly describe your project's goals, and how you plan to use DETERLab.</span></label> 
            <textarea
              required 
              aria-required=true 
              name=\"formfields[proj_why]\"
              id=\"formfields[proj_why]\"
              cols=80 rows=10>
              " . $formfields["proj_why"] . "
            </textarea>
          </div>\n";
    #
    # Project ID:
    #
    echo "<div class=field>
              <label for=\"formfields[pid]\">Project Identifier (6-to-$TBDB_PIDLEN numbers and letters only): <span class=help-text>This identifier will be used as a group-id name for members of your project.</span></label> 
              <input type=text 
                required 
                aria-required=true 
                name=\"formfields[pid]\"
                id=\"formfields[pid]\"
                value=\"" . $formfields["pid"] . "\"
                maxlength=$TBDB_PIDLEN>
          </div>\n";

    #
    # Project URL:
    #
    echo "<div class=field>
            <label for=\"formfields[proj_URL]\">Project Web Site: <span class=help-text>A page in a web site about your project, or 
            your sponsoring organization.</span></label>
            <input type=text 
              required 
              aria-required=true 
              name=\"formfields[proj_URL]\"
              id=\"formfields[proj_URL]\"
              value=\"" . $formfields["proj_URL"] . "\"
              >
          </div>\n";

    #
    # Project Organization Type
    #
    echo "<div class=\"field newselect\">
            <label for=\"formfields[proj_org]\">Project Organization Type: <span class=help-text>Select one broad category that best fits your project.</span></label>";
    echo  SPITSELECT("formfields[proj_org]", $formfields["proj_org"],  $CHOICES["proj_org"]);
    echo "</div>\n";

    #
    # Is this project for class?
    # If yes, hide following field - if no, show following field
    echo "<div class=\"field newradio\">
            <fieldset>
              <legend>Is this project for a class?</legend>         
              <input type=radio
                     onclick=\"javascript:yesnoCheck();\"
                     name=yesno
                     id=yesCheck
                     value=Yes
                     style=\"height:15px; width:15px; vertical-align: middle;\"/>
              <label for=yesCheck>Yes</label> 
              <input type=radio
                     onclick=\"javascript:yesnoCheck();\"
                     name=yesno
                     id=noCheck
                     value=No
                     style=\"height:15px; width:15px; vertical-align: middle;\" />
              <label for=noCheck>No</label>
            </fieldset>";
    echo "</div>\n";

    #
    # Project Research Focus
    # If proj_class equals No,
    echo "<div class=\"field newselect\" id=research_type style=\"display:none;\">
            <label for=\"formfields[proj_research_type]\">Project Research Focus: <span class=help-text>Select one research area that best fits your project.</span></label>";
    echo  SPITSELECT("formfields[proj_research_type]", $formfields["proj_research_type"],  $CHOICES["proj_research_type"]);
    echo "</div>\n";

    #
    # Project Funding
    #
    echo "<div class=\"field newselect\">
            <label for=\"formfields[proj_funders]\">Project Funding or Support: <span class=help-text>Select one type of funding or support that best fits your project.</span></label>";
    echo  SPITSELECT("formfields[proj_funders]", $formfields["proj_funders"],  $CHOICES["proj_funders"]);
    echo "</div>\n";

    #
    # Project Listing
    #
    echo "<div class=\"field newselect\">
            <label for=\"formfields[proj_public]\">Include project in public listing? <span class=help-text>Include your project on the <a href=\"projectlist.php\">public list</a> of DETERLab projects.</span></label>";
    echo  SPITSELECT("formfields[proj_public]", $formfields["proj_public"],  $CHOICES["proj_public"]);
    echo "</div>\n";

    #
    # Now a submit button
    #
    echo "<div><input type=submit name=submit value=Submit></div>\n";

    echo "</form>
          </div>\n";
    #
    # Javascript to display or hide the project research type field based on answer
    # to "Is this a class?" field.
    #
    echo "<script type=text/javascript>
    
            function yesnoCheck() {
              
              var yesRadio = document.getElementById(\"yesCheck\");
              var resDiv = document.getElementById(\"research_type\");
              var resType = document.getElementById(\"formfields[proj_research_type]\");
              
              if (yesRadio.checked) {
                resDiv.style.display=\"none\";
                resType.selectedIndex = 3;
              }
              
              else {
                resDiv.style.display=\"block\";
                resType.selectedIndex = 0;
              }
              
            }
            
          </script>\n";
}

#
# The conclusion of a newproject request. See below.
# 
if (isset($finished)) {
    PAGEHEADER("Start a New Testbed Project");

    echo "<center><h2>
           Your project request has been successfully queued.
          </h2></center>
          Testbed Operations has been notified of your application.
          Most applications are reviewed within a day; some even within
          the hour, but sometimes as long as a week (rarely). We will notify
          you by e-mail when a decision has been made.\n";

    if (! $returning) {
        echo "<br>
              <p>
              In the meantime, as a new user of the Testbed you will receive
              a key via email.
              When you receive the message, please follow the instructions
              contained in the message on how to verify your account.\n";
    }
    
    PAGEFOOTER();
    return;
}

#
# On first load, display a virgin form and exit.
#
if (! isset($submit)) {
    $defaults = array();

    # User defaults
    $defaults["uid"]                = "";
    $defaults["usr_name"]                     = "";
    $defaults["usr_title"]                    = "";
    $defaults["usr_affil"]                    = "";
    $defaults["usr_affil_abbrev"]             = "";
    $defaults["usr_URL"]                      = "$HTTPTAG";
    $defaults["usr_email"]                    = "";
    $defaults["usr_addr"]                     = "";
    $defaults["usr_addr2"]                    = "";
    $defaults["usr_city"]                     = "";
    $defaults["usr_state"]                    = "";
    $defaults["usr_zip"]                      = "";
    $defaults["usr_country"]                  = "USA";
    $defaults["usr_phone"]                    = "";
    $defaults["password1"]                    = "";
    $defaults["password2"]                    = "";
    
    # Project Defaults
    $defaults["proj_name"]       = "";     # AKA Project Name
    $defaults["proj_why"]        = "";     # AKA Project Plan
    $defaults["pid"]                          = "";
    $defaults["proj_URL"]                     = "$HTTPTAG";
    $defaults["proj_org"]                     = "";
    $defaults["proj_class"]                   = "";
    $defaults["proj_research_type"]           = "";
    $defaults["proj_funders"]                 = "";
    $defaults["proj_public"]                  = "Yes";

    if ($FirstInitState == "createproject") {
        $defaults["pid"]                      = "testbed";
        $defaults["proj_funders"]             = "Other";
        $defaults["proj_name"]   = "Your Testbed Project";
        $defaults["proj_why"]    = "This project is used for testbed ".
            "administrators to develop and test new software. ";
        $defaults["proj_org"]                 = "Internal";
        $defaults["proj_research_type"]       = "Internal";
        $defaults["proj_URL"]                 = "$HTTPTAG$WWWHOST";

    }
    
    SPITFORM($defaults, $returning, 0);
    PAGEFOOTER();
    return;
}

################################################################################
#
#  Validation the form submission!
#

# Form submitted. Make sure we have a formfields array.
if (!isset($formfields)) {
    PAGEARGERROR("Invalid form arguments.");
}

# TBERROR("A\n\n" . print_r($formfields, TRUE), 0);
# print "<pre>\n"; print_r($formfields); exit;

#
# Otherwise, must validate and redisplay if errors
#
$errors = array();

#
# Validation helper functions
#
function missing($name) {
    global $formfields;
    return !isset($formfields[$name]) || $formfields[$name] == '';
}

# validates a yes/no radio button
function valid_yesno($field, $name) {
    global $formfields, $errors;

    if (missing($field))
        $errors[$name] = 'Missing Field';
    elseif ($formfields[$field] != 'Yes' && $formfields[$field] != 'No')
        $errors[$name] = 'Invalid selection';
    else
        return $formfields[$field] == 'Yes';

    return 0;
}

# validates a text field
function valid_text($field, $name) {
    global $formfields, $errors;

    if (missing($field))
        $errors[$name] = 'Missing Field';
    elseif (!TBvalid_description($formfields[$field]))
        $errors[$name] = TBFieldErrorString();
}

# validate a muiltiline textbox
function valid_textbox($name, $desc) {
    global $formfields, $errors;

    if (missing($name))
        $errors[$desc] = 'Missing Field';
    elseif (!TBvalid_why($formfields[$name]))
        $errors[$desc] = TBFieldErrorString();
}

# validates a multiple select field
function valid_selection($field, $name, $values) {
    global $formfields, $errors;

    if (missing($field))
        $errors[$name] = 'Missing Field';
    else {
        $valid = 0;
        foreach ($values as $value)
            if ($formfields[$field] == $value) {
                $valid = 1;
                break;
            }

        if (!$valid)
            $errors[$name] = 'Invalid Selection';
    }
}

# Yeah NO XSS!
foreach ($formfields as $name => $value)
    $formfields[$name] = htmlentities($value);

#
# Validate the User account fields if this is a new user
#
if (! $returning) {
    
    # Validate Full Name
    if (missing('usr_name'))
        $errors["Full Name"] = "Missing Field";
    elseif (!TBvalid_usrname($formfields["usr_name"]))
        $errors["Full Name"] = TBFieldErrorString();
    else {
        # Make sure user name has at least two tokens!
        $tokens = preg_split("/[\s]+/", $formfields["usr_name"], -1, PREG_SPLIT_NO_EMPTY);
        if (count($tokens) < 2)
            $errors["Full Name"] = "Please provide a first and last name";
    }

    # Validate Email Address
    if (missing('usr_email'))
        $errors["Email Address"] = "Missing Field";
    elseif (!TBvalid_email($formfields["usr_email"]))
        $errors["Email Address"] = TBFieldErrorString();
    elseif (User::LookupByEmail($formfields["usr_email"]) || 
	    User::StudentEmail($formfields["usr_email"]))
        #
        # Treat this error separate. Not allowed.
        #
        $errors["Email Address"] = "Already in use. <b>Did you forget to login?</b>";

    # Validate Phone Number
    if (missing('usr_phone'))
        $errors["Phone Number"] = "Missing Field";
    elseif (!TBvalid_phone($formfields["usr_phone"]))
        $errors["Phone Number"] = TBFieldErrorString();
    
    # Valicate Position, Title, or Job Description
    if (missing('usr_title'))
        $errors["Position, Title, or Job Description"] = "Missing Field";
    elseif (!TBvalid_title($formfields["usr_title"]))
        $errors["Position, Title, or Job Description"] = TBFieldErrorString();

    # Validate Full Name of Employer or Affiliated Institution
    if (missing('usr_affil'))
        $errors["Full Name of Employer or Affiliated Institution"] = "Missing Field";
    elseif (!TBvalid_affiliation($formfields["usr_affil"]))
        $errors["Full Name of Employer or Affiliated Institution"] = TBFieldErrorString();

    # Validate Institution Abbreviation
    if (missing('usr_affil_abbrev'))
        $errors["Institution Abbreviation"] = "Missing Field";
    elseif (!TBvalid_affiliation_abbreviation($formfields["usr_affil_abbrev"]))
        $errors["Institution Abbreviation"] = TBFieldErrorString();

    # Validate Institution's Web Site
    if (isset($formfields["usr_URL"]) &&
        strcmp($formfields["usr_URL"], "") &&
        strcmp($formfields["usr_URL"], $HTTPTAG) &&
        ! CHECKURL($formfields["usr_URL"], $urlerror)) {
        $errors["Institution's Web Site"] = $urlerror;
    }

    # Validate Address 1
    if (missing('usr_addr'))
        $errors["Address 1"] = "Missing Field";
    elseif (!TBvalid_addr($formfields["usr_addr"]))
        $errors["Address 1"] = TBFieldErrorString();

    # Optional Address 2
    if (isset($formfields["usr_addr2"]) && !TBvalid_addr($formfields["usr_addr2"]))
        $errors["Address 2"] = TBFieldErrorString();

    # Validate City
    if (missing('usr_city'))
        $errors["City"] = "Missing Field";
    elseif (!TBvalid_city($formfields["usr_city"]))
        $errors["City"] = TBFieldErrorString();

    # Validate State
    if (missing('usr_state'))
        $errors["State"] = "Missing Field";
    elseif (!TBvalid_state($formfields["usr_state"]))
        $errors["State"] = TBFieldErrorString();
    
    # Validate ZIP/Postal Code
    if (missing('usr_zip'))
        $errors["ZIP/Postal Code"] = "Missing Field";
    elseif (!TBvalid_zip($formfields["usr_zip"]))
        $errors["Zip/Postal Code"] = TBFieldErrorString();

    # Validate Country
    if (missing('usr_country'))
        $errors["Country"] = "Missing Field";
    elseif (!TBvalid_country($formfields["usr_country"]))
        $errors["Country"] = TBFieldErrorString();

    # Validate Username
    if ($USERSELECTUIDS || $FirstInitState == "createproject") {
        if (missing('uid'))
            $errors["Username"] = "Missing Field";
        elseif (!TBvalid_uid($formfields["uid"]))
            $errors["Username"] = TBFieldErrorString();
        elseif (User::Lookup($formfields["uid"]) || posix_getpwnam($formfields["uid"]))
            $errors["Username"] = "Already in use. Pick another";
        elseif (preg_match("/[A-Z]/", $formfields["uid"])) 
            $errors["Username"] = "Username must be lower case.";
    }
    
    # Validate Password
    if (missing('password1'))
        $errors["Password"] = "Missing Field";

    if (missing('password2'))
        $errors["Retype Password"] = "Missing Field";
    elseif ($formfields["password1"] != $formfields["password2"])
        $errors["Retype Password"] = "Does not match Password";
    elseif (! CHECKPASSWORD((($USERSELECTUIDS ||
                             $FirstInitState == "createproject") ?
                             $formfields["uid"] : "ignored"),
                            $formfields["password1"],
                            $formfields["usr_name"],
                            $formfields["usr_email"], $checkerror))
        $errors["Password"] = "$checkerror";
} elseif ($this_user->CourseAcct())
    $errors["Email address"] = "Students may not head projects!";

#
# Validate Project Fields
#

# Validate Project Name
if (missing('proj_name'))
    $errors["Project Name"] = "Missing Field";
elseif (!TBvalid_description($formfields["proj_name"]))
    $errors["Project Name"] = TBFieldErrorString();

# Validate Project Plan
valid_textbox('proj_why', 'Project Plan');

# Validate Project ID
if (missing('pid'))
    $errors["Project ID"] = "Missing Field";

else {
    if (!TBvalid_newpid($formfields["pid"])) {
        $errors["Project ID"] = TBFieldErrorString();
    }
    elseif (Project::LookupByPid($formfields["pid"])) {
        $errors["Project ID"] =
            "Already in use. Select another";
    }
}

# Validate Project Web Site
if (missing('proj_URL') || $formfields["proj_URL"] == $HTTPTAG)
    $errors["Project Web Site"] = "Missing Field";
elseif (!CHECKURL($formfields["proj_URL"], $urlerror))
    $errors["Project Web Site"] = $urlerror;

# Validate Project Organization Type
valid_selection("proj_org", "Project Organization Type", $CHOICES["proj_org"]);

# Validate Project Focus Type
valid_selection("proj_research_type", "Project Focus Type", $CHOICES["proj_research_type"]);

# Validate Project Funding or Support
valid_selection("proj_funders", "Project Funding or Support", $CHOICES["proj_funders"]);

# Validate Project Listing
valid_selection("proj_public", "Project Listing", $CHOICES["proj_public"]);


# Present these errors before we call out to do anything else.
if (count($errors)) {
    SPITFORM($formfields, $returning, $errors);
    PAGEFOOTER();
    return;
}


################################################################################
#
#  Validation is passed, send to the backend for processing!
#


#
# Create the User first, then the Project/Group.
# Certain of these values must be escaped or otherwise sanitized.
#
if (!$returning) {
    $args = array();
    $args["name"]          = $formfields["usr_name"];
    $args["email"]         = $formfields["usr_email"];
    $args["address"]       = $formfields["usr_addr"];
    $args["address2"]      = $formfields["usr_addr2"];
    $args["city"]          = $formfields["usr_city"];
    $args["state"]         = $formfields["usr_state"];
    $args["zip"]           = $formfields["usr_zip"];
    $args["country"]       = $formfields["usr_country"];
    $args["phone"]         = $formfields["usr_phone"];
    $args["shell"]         = 'bash';
    $args["title"]         = $formfields["usr_title"];
    $args["affiliation"]   = $formfields["usr_affil"];
    $args["affiliation_abbreviation"] = $formfields["usr_affil_abbrev"];
    $args["password"]      = $formfields["password1"];


    if (isset($formfields["usr_URL"]) &&
        $formfields["usr_URL"] != $HTTPTAG && $formfields["usr_URL"] != "") {
        $args["URL"] = $formfields["usr_URL"];
    }
    if ($USERSELECTUIDS || $FirstInitState == "createproject") {
        $args["login"] = $formfields["uid"];
    }

    # Just collect the user XML args here and pass the file to NewProject.
    # Underneath, newproj calls newuser with the XML file.
    #
    # Calling newuser down in Perl land makes creation of the leader account
    # and the project "atomic" from the user's point of view.  This avoids a
    # problem when the DB is locked for daily backup: in newproject, the call
    # on NewUser would block and then unblock and get done; meanwhile the
    # PHP thread went away so we never returned here to call NewProject.
    #
    if (! ($newuser_xml = User::NewUserXML($args, $errors)) != 0) {
        $errors["Error Creating User XML"] = $error;
        TBERROR("B\n${error}\n\n" . print_r($args, TRUE), 0);
        SPITFORM($formfields, $returning, $errors);
        PAGEFOOTER();
        return;
    }
}

$affil = '';

#
# Now for the new Project
#
$args = array();
if (isset($newuser_xml)) {
    $args["newuser_xml"]   = $newuser_xml;
    $affil = $formfields['usr_affil'];
}
if ($returning) {
    # An existing, logged-in user is starting the project.
    $args["leader"]        = $this_user->uid();
    $affil = $this_user->affil();
}

$args["name"]             = $formfields["proj_name"]; # AKA Project Name
$args["why"]              = $formfields["proj_why"];  # AKA Project Plan
$args["pid"]              = $formfields["pid"];
$args["URL"]              = $formfields["proj_URL"];
$args["org_type"]         = $formfields["proj_org"];
$args["research_type"]    = $formfields["proj_research_type"];
$args["funders"]          = $formfields["proj_funders"];


if (!isset($formfields["proj_public"]) ||
    $formfields["proj_public"] != "Yes") {
    $args["public"] = 0;
}
else {
    $args["public"] = 1;
}

#if (isset($formfields["proj_class"]) &&
#    $formfields["proj_class"] = "Yes") {
#    $args["research_type"] = "Class";
#}
#else {
#    $args["research_type"] = $formfields["proj_research_type"];
#}

#
# Form fields are processed by the backend.  Do not use SQL in this page.
#
if (! ($project = Project::NewProject($args, $error))) {
    $errors["Error Creating Project"] = $error;
    TBERROR("C\n${error}\n\n" . print_r($args, TRUE), 0);
    SPITFORM($formfields, $returning, $errors);
    PAGEFOOTER();

    return;
}

#
# Need to do some extra work for the first project; eventually move to backend
# 
if ($FirstInitState) {
    $leader = $project->GetLeader();
    $uid = $leader->uid();
    # Set up the management group (emulab-ops).
    Group::Initialize($uid);
    
    #
    # Move to next phase. 
    # 
    $pid = $formfields["pid"];
    TBSetFirstInitPid($pid);
    TBSetFirstInitState("approveproject");
    header("Location: approveproject.php?pid=$pid&approval=approve");
    return;
}

#
# Spit out a redirect so that the history does not include a post
# in it. The back button skips over the post and to the form.
# See above for conclusion.
# 
header("Location: newproject.php?finished=1");

?>
