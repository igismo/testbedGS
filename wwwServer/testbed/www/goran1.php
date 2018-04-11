<?php
# 
# EMULAB-COPYRIGHT
# Copyright (c) 2000-2009 University of Utah and the Flux Group.
# All rights reserved.
#
include("defs.php");
include("new_user_form.php");
### GORAN ...
use Spiral\Goridge;
require "./vendor/autoload.php";
###--------------------------------
use Decoder\ClassDecoder;
use PhpJsonMarshaller\Marshaller\JsonMarshaller;
use Reader\DoctrineAnnotationReader;
use PhpJsonMarshaller;
#require "./vendor/php-json-marshaller/vendor/autoload.php";


function goran()
{
#use PhpJsonMarshaller;
#use PhpJsonMarshaller\Decoder\ClassDecoder;
#use PhpJsonMarshaller\Marshaller\JsonMarshaller;
#use PhpJsonMarshaller\Reader\DoctrineAnnotationReader;

$rpc = new Goridge\SocketRelay("172.18.0.3", 1200);
$doctr = new Goridge\PhpJsonMarshaller\Reader\DoctrineAnnotationReader();
$dcdr  = new Goridge\PhpJsonMarshaller\ClassDecoder($doctr);
$mrsh  = new Marshaller\JsonMarshaller($dcdr);

#$marshaller = new Marshaller\JsonMarshaller(new ClassDecoder(new DoctrineAnnotationReader()));
#$json = '{
#    "id": 12345,
#    "name": "Anuj"
#}';
#// Marshall the class
#$marshalled = $marshaller->marshall($user);
#echo $rpc->send($marshalled);  ### ("Antony");
}


## GORAN END
#
# No PAGEHEADER since we spit out a Location header later. See below.
#

#
# Get current user.
#
$this_user = CheckLogin($check_status);
$is_student = 0;

#
# Verify page arguments.
#
$optargs = OptionalPageArguments("submit",       PAGEARG_STRING,
                                 "finished",     PAGEARG_BOOLEAN,
                                 "target_pid",   PAGEARG_STRING,
                                 "target_gid",   PAGEARG_STRING,
                                 "formfields",   PAGEARG_ARRAY);

#
# If a uid came in, then we check to see if the login is valid.
# We require that the user be logged in to start a second project.
#
if ($this_user) {
    # Allow unapproved users to join multiple groups ...
    # Must be verified though.
    CheckLoginOrDie(CHECKLOGIN_UNAPPROVED|
                    CHECKLOGIN_WEBONLY);
    $uid = $this_user->uid();
    $returning = 1;
    if ($this_user->CourseAcct()) {
        $is_student = 1;
    }
}
else {
    #
    # No uid, so must be new.
    #
    $returning = 0;
}

unset($addpubkeyargs);

$ACCOUNTWARNING =
    "Before continuing, please make sure your username " .
    "reflects your normal login name. ".
    "DETERLab accounts are not to be shared amongst users!";

$EMAILWARNING =
    "Before continuing, please make sure the email address you have ".
    "provided is current and non-pseudonymic. Redirections and anonymous ".
    "email addresses are not allowed.";

#
# Spit the form out using the array of data. 
# 
function SPITFORM($formfields, $returning, $errors)
{
    global $TBDB_UIDLEN, $TBDB_PIDLEN, $TBDB_GIDLEN;
    global $ACCOUNTWARNING, $EMAILWARNING;
    global $is_student, $USERSELECTUIDS;
    global $WIKIDOCURL;

    PAGEHEADER("Apply for Project Membership");

    if ($is_student) {
        echo "<center>\n";

        echo "<font size=+1>
               You have a DETERLab student account.
               <font color=red>Student accounts are separate from research accounts and are per-class.</font>
              </font>
               Please log out before applying for another account.\n";
        echo "</center><br>\n"; 
        return;
    }

    if (! $returning) {
        echo "<div class=returning >\n";

        echo "If you already have an DETERLab account,
               <a href=login.php?refer=1>
               <span>please log on first!</span></a>\n";
        echo "</div>\n"; 
    }

    if ($errors) {
        echo "<div class=error-text >
                &nbsp;Oops, please fix the following errors!&nbsp;
              </div>\n";

        while (list ($name, $message) = each ($errors)) {
            echo "<div class=error-msg >
                       <p>$name: <span>$message</span></p>
                  </div>\n";
        }
    }

    echo "<div class=req-fields>
            <p>All fields are required.</p>
            <p>Project membership must be approved by project leader after account is verified.</p>
          </div>\n
          <div class=formwrap>
          <form name=gorantest enctype=multipart/form-data
                action=\"gorantest.php\" id=gorantest method=post>\n";

    #
    # Project Name:
    #
    echo "<div>
            <fieldset>
            <legend>Project Information:</legend>
          <div class=field>
            <label for=\"formfields[pid]\">Existing Project Name: <span class=help-text>Project membership needs to be approved by
              the project leader after your account has been verified.</span></label>
            <input type=text
                   required 
                   aria-required=true 
                   name=\"formfields[pid]\"
                   id=\"formfields[pid]\"
                   value=\"" . $formfields["pid"] . "\" 
                   size=$TBDB_PIDLEN maxlength=$TBDB_PIDLEN>
          </div>\n";

    #
    # Group Name:
    #
    echo "<div class=field>
            <label for=\"formfields[gid]\">Group Name: <span class=help-text>Leave blank unless you <em>know</em> the group
              name.</span></label>  
            <input type=text
             name=\"formfields[gid]\"
             id=\"formfields[gid]\"
             value=\"" . $formfields["gid"] . "\" 
             size=$TBDB_GIDLEN maxlength=$TBDB_GIDLEN>
             </div>
             </fieldset>
          </div>\n";

    if (! $returning) {
        SPITNEWUSERFORM($formfields, $USERSELECTUIDS, 1);
    }


    echo "<div><input type=submit name=submit value=Submit></div>\n";

    echo "</form>\n";

    echo "<div class=project-reqs>
          <ol>
            <li> Project membership  after your account has been verified.
	    <li> You will receive an email to verify your email address.
            <li> Passwords are checked for strength.
            ";
    echo "</ol>
          </div>
          </div>\n";
}

#
# The conclusion of a join request. See below.
# 
if (isset($finished)) {
    PAGEHEADER("Apply for Project Membership");

    #
    # Generate some warm fuzzies.
    #
    if (! $returning) {
        echo "<p>
              As a pending user of the Testbed you will receive a key via email.
              When you receive the message, please follow the instructions
              contained in the message, which will verify your identity.
              <br>
              <p>
              When you have done that, the project leader will be
              notified of your application. ";
    }
    else {
          echo "<p>
                The project leader has been notified of your application. ";
    }

    echo "He/She will make a decision and either approve or deny your
          application, and you will be notified via email as soon as
          that happens.\n";

    PAGEFOOTER();
    return;
}

#
# On first load, display a virgin form and exit.
#
if (! isset($submit) || $is_student) {
    $defaults = array();
    $defaults["pid"]         = "";
    $defaults["gid"]         = "";
    $defaults["uid"] = "";
    $defaults["usr_name"]    = "";
    $defaults["usr_email"]   = "";
    $defaults["usr_addr"]    = "";
    $defaults["usr_addr2"]   = "";
    $defaults["usr_city"]    = "";
    $defaults["usr_state"]   = "";
    $defaults["usr_zip"]     = "";
    $defaults["usr_country"] = "";
    $defaults["usr_phone"]   = "";
    $defaults["usr_title"]   = "";
    $defaults["usr_affil"]   = "";
    $defaults["usr_affil_abbrev"] = "";
    $defaults["password1"]   = "";
    $defaults["password2"]   = "";
    $defaults["usr_URL"]     = "$HTTPTAG";
    $defaults["usr_country"] = "USA";

    #
    # These two allow presetting the pid/gid.
    # 
    if (isset($target_pid) && strcmp($target_pid, "")) {
        $defaults["pid"] = $target_pid;
    }
    if (isset($target_gid) && strcmp($target_gid, "")) {
        $defaults["gid"] = $target_gid;
    }
    
    SPITFORM($defaults, $returning, 0);
    PAGEFOOTER();
    return;
}
# Form submitted. Make sure we have a formfields array.
if (!isset($formfields)) {
    PAGEARGERROR("Invalid form arguments.");
}

#
# Otherwise, must validate and redisplay if errors
#
$errors = array();

#
# These fields are required!
#
if (! $returning) {
    if ($USERSELECTUIDS) {
        if (!isset($formfields["uid"]) ||
            strcmp($formfields["uid"], "") == 0) {
            $errors["Username"] = "Missing Field";
        }
        elseif (!TBvalid_uid($formfields["uid"])) {
            $errors["Username"] = TBFieldErrorString();
        }
        elseif (User::Lookup($formfields["uid"]) ||
                posix_getpwnam($formfields["uid"])) {
            $errors["Username"] = "Already in use. Pick another";
        }
        elseif (preg_match("/[A-Z]/", $formfields["uid"])) {
            $errors["Username"] = "Username must be lower case.";
        }
    }
    if (!isset($formfields["usr_addr"]) ||
        strcmp($formfields["usr_addr"], "") == 0) {
        $errors["Address 1"] = "Missing Field";
    }
    elseif (! TBvalid_addr($formfields["usr_addr"])) {
        $errors["Address 1"] = TBFieldErrorString();
    }
    # Optional
    if (! CHECKPASSWORD(($USERSELECTUIDS ?
                             $formfields["uid"] : "ignored"),
                            $formfields["password1"],
                            $formfields["usr_name"],
                            $formfields["usr_email"], $checkerror)) {
        $errors["Password"] = "$checkerror";
    }
}
if (!isset($formfields["pid"]) || $formfields["pid"] == "") {
    $errors["Project Name"] = "Missing Field";
}
else {
    # Confirm pid/gid early to avoid spamming the page.
    $pid = $formfields["pid"];

    if (isset($formfields["gid"]) && $formfields["gid"] != "") {
        $gid = $formfields["gid"];
    }
    else {
        $gid = $pid;
    }

    if (!TBvalid_pid($pid) || !Project::Lookup($pid)) {
        $errors["Project Name"] = "Invalid Project Name";
    }
    elseif (!TBvalid_gid($gid) || !Group::LookupByPidGid($pid, $gid)) {
        $errors["Group Name"] = "Invalid Group Name";
    }
}

# Present these errors before we call out to do pubkey stuff; saves work.
if (count($errors)) {
    SPITFORM($formfields, $returning, $errors);
    PAGEFOOTER();
    return;
}

#
# Need the user, project and group objects for the rest of this.
#
if (! ($project = Project::Lookup($pid))) {
    TBERROR("Could not lookup object for $pid!", 1);
}
if ($project->is_class()) {
    $CLASSWARNING =
	"The project you are seeking to join is a class. ".
	"Please contact the instructor and have him or her assign you a student or ".
	"TA Account.";
    $errors["Project Name"] = $CLASSWARNING;
}
if (! ($group = Group::LookupByPidGid($pid, $gid))) {
    TBERROR("Could not lookup object for $pid/$gid!", 1);
}
if ($returning) {
    $user = $this_user;
    if ($group->IsMember($user, $ignore)) {
        $errors["Membership"] = "You are already a member of the project";
    }
}



## GORAN TEST
$vendorDir = dirname(dirname(__FILE__));
$baseDir = dirname($vendorDir);
$adir= dirname("Goridge\SocketRelay");
$xdir = dirname(__DIR__);
echo "<h3> VENDOR=$vendorDir XDIR=$xdir....  BASEDIR=$baseDir ADIR=$adir </h3>";
goran();


###############$rpc = new Goridge\RPC(new Goridge\SocketRelay("172.18.0.3", 1200)); ### 6001));
################echo $rpc->call("App.Hi", "Antony");
#$rpc = new Goridge\SocketRelay("172.18.0.3", 1200);
####### next is 354
#######$doctr = new PhpJsonMarshaller\Reader\DoctrineAnnotationReader();

#$doctr = new Reader\DoctrineAnnotationReader();
#$dcdr  = new PhpJsonMarshaller\ClassDecoder($doctr);
#$mrsh  = new Marshaller\JsonMarshaller($dcdr);

#$marshaller = new Marshaller\JsonMarshaller(new ClassDecoder(new DoctrineAnnotationReader()));
#$json = '{
#    "id": 12345,
#    "name": "Anuj"
#}';
#// Marshall the class
#$marshalled = $marshaller->marshall($user);
#echo $rpc->send($marshalled);  ### ("Antony");
########## GORAN END

# Done with sanity checks!
if (count($errors)) {
    SPITFORM($formfields, $returning, $errors);
    PAGEFOOTER();
    return;
}

#
# Create a new user. We do this by creating a little XML file to pass to
# the newuser script.
#
if (! $returning) {
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
    if ($USERSELECTUIDS) {
        $args["login"] = $formfields["uid"];
    }

    # Backend verifies pubkey and returns error.
    if (isset($_FILES['usr_keyfile']) &&
        $_FILES['usr_keyfile']['name'] != "" &&
        $_FILES['usr_keyfile']['name'] != "none") {

        $localfile = $_FILES['usr_keyfile']['tmp_name'];
        $args["pubkey"] = file_get_contents($localfile);
    }
    if (! ($user = User::NewUser(TBDB_NEWACCOUNT_REGULAR,
                                    $args,
                                    $error)) != 0) {
        $errors["Error Creating User"] = $error;
        SPITFORM($formfields, $returning, $errors);
        PAGEFOOTER();
        return;
    }
    $uid = $user->uid();
}

#
# If this sitevar is set, check to see if this addition will create a
# mix of admin and non-admin people in the group. 
#
if ($ISOLATEADMINS &&
    !$project->IsMember($user, $ignore)) {
    $members = $project->MemberList();

    foreach ($members as $other_user) {
        if ($user->admin() != $other_user->admin()) {
            if ($returning) {
                $errors["Joining Project"] =
                    "Improper mix of admin and non-admin users";
                SPITFORM($formfields, $returning, $errors);
                PAGEFOOTER();
                return;
            }
            else {
                #
                # The user creation still succeeds, which is good. Do not
                # want the effort to be wasted. But need to indicate that
                # something went wrong. Lets send email to tbops since this
                # should be an uncommon problem.
                #
                TBERROR("New user '$uid' attempted to join project ".
                        "'$pid'\n".
                        "which would create a mix of admin and non-admin ".
                        "users\n", 0);
                
                header("Location: gorantest.php?finished=1");
                return;
            }
        }
    }
}

#
# If joining a subgroup, also add to project group.
#
if ($pid != $gid && ! $project->IsMember($user, $ignore)) {
    if ($project->AddNewMember($user) < 0) {
        TBERROR("Could not add user $uid to project group $pid", 1);
    }
}

#
# Add to the group, but with trust=none. The project/group leader will have
# to upgrade the trust level, making the new user real.
#
if ($group->AddNewMember($user) < 0) {
    TBERROR("Could not add user $uid to group $pid/$gid", 1);
}

#
# Generate an email message to the proj/group leaders.
#
if ($returning) {
    $group->NewMemberNotify($user);
}

#
# Spit out a redirect so that the history does not include a post
# in it. The back button skips over the post and to the form.
# See above for conclusion.
# 
header("Location: gorantest.php?finished=1");
?>
