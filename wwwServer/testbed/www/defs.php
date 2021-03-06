<?php
#
# EMULAB-COPYRIGHT
# Copyright (c) 2000-2008 University of Utah and the Flux Group.
# All rights reserved.
#
# Lets emulate register_globals=off for a while.
include("unregister_globals.php");

#
# Standard definitions.
#
#########===================
#$TBDIR                      = "/usr/testbed/";
#$OURDOMAIN                  = "minibed.deterlab.net";
#$BOSSNODE                   = "boss.minibed.deterlab.net";
#$USERNODE                   = "users.minibed.deterlab.net";
#$WIKINODE                   = 'docfilter.deterlab.net';
#$TBADMINGROUP               = "tbadmin";
#$WWWHOST                    = "www.minibed.deterlab.net";
#$WWW                        = "www.minibed.deterlab.net";
#$TBAUTHDOMAIN               = ".minibed.deterlab.net";
#$TBBASE                     = "https://www.minibed.deterlab.net";
#$TBDOCBASE                  = "http://www.minibed.deterlab.net";
#$TBWWW                      = "<https://www.minibed.deterlab.net/>";
#$THISHOMEBASE               = "minibed.deterlab.net";
########====================
$TBDIR                      = "/usr/testbed/";
$OURDOMAIN                  = "localhost";
$BOSSNODE                   = "$OURDOMAIN"; ## boss.
$USERNODE                   = "$OURDOMAIN"; ## users.
$WIKINODE	            = 'docfilter.deterlab.net';
$TBADMINGROUP               = "tbadmin";
$WWWHOST	            = "www.$OURDOMAIN";
$WWW		            = "www.$OURDOMAIN";
$TBAUTHDOMAIN	            = ".$OURDOMAIN";
$TBBASE		            = "https://www.$OURDOMAIN";
$TBDOCBASE                  = "http://www.$OURDOMAIN";
$TBWWW		            = "<https://www.$OURDOMAIN/>";
$THISHOMEBASE               = "$OURDOMAIN";
#############################
$ELABINELAB                 = 0;
$PLABSUPPORT                = 0;
$TRACSUPPORT                = 0;
$MAILMANSUPPORT             = 0;
$ISOLATEADMINS              = 0;
$CONTROL_NETWORK            = "192.168.0.0";
$CONTROL_NETMASK            = "255.255.252.0";
$TRACCOOKIENAME             = "TracCookie";
$MAILMANURL                 = "http://${USERNODE}/mailman";
$WIKIDOCURL                 = "http://${WIKINODE}/wikidocs/wiki";
$MIN_UNIX_UID               = 10000;
$MIN_UNIX_GID               = 6000;
$EXPOSELINKTEST             = 1;
$EXPOSESTATESAVE            = 0;
$EXPOSEARCHIVE              = 0;
$USERSELECTUIDS             = 1;
$REMOTEWIKIDOCS             = 1;
$GMAP_API_KEY               = "";

$TBMAILADDR_OPS		        = "testbed-ops@minibed.deterlab.net";
$TBMAILADDR_WWW		        = "testbed-ops@minibed.deterlab.net";
$TBMAILADDR_APPROVAL	    = "testbed-approval@minibed.deterlab.net";
$TBMAILADDR_LOGS	        = "testbed-logs@minibed.deterlab.net";
$TBMAILADDR_AUDIT	        = "testbed-ops@minibed.deterlab.net";

# Can override this in the defs file. 
$TBAUTHTIMEOUT              = "21600";
$TBMAINSITE                 = "0";
$TBSECURECOOKIES            = "1";
$TBCOOKIESUFFIX             = "";
$FANCYBANNER                = "0";

$TBWWW_DIR	                = "$TBDIR"."www/";
$TBBIN_DIR	                = "$TBDIR"."bin/";
$TBETC_DIR	                = "$TBDIR"."etc/";
$TBLIBEXEC_DIR	            = "$TBDIR"."libexec/";
$TBSUEXEC_PATH              = "$TBLIBEXEC_DIR/suexec";
$TBCHKPASS_PATH             = "$TBLIBEXEC_DIR/checkpass";
$TBCSLOGINS                 = "$TBETC_DIR/cslogins";
$UUIDGEN_PATH               = "/bin/uuidgen";

## MOVED FROM THE END OF THIS FILE 
require("tbauth.php");
require("Sajax.php");
require("menu.php");

#
# If the timezone is not set in php.ini, we set to a LA default.
# A list of timezones can be found at:
#  http://www.php.net/manual/en/timezones.php
# 
$tz_set = (bool) ini_get('date.timezone');

if ( $tz_set == False ) {
    date_default_timezone_set('America/Los_Angeles');
}

#
# Hardcoded check against $WWWHOST, to prevent anyone from accidentally setting
# $TBMAINSITE when it should not be
#
if ($WWWHOST != "www.emulab.net") {
    $TBMAINSITE = 0;
}

#
# The wiki docs either come from the local node, or in most cases
# they are redirected back to Utah's emulab.
#
#if ($TBMAINSITE) {
#    $WIKIDOCURL  = "https://${WIKINODE}/wikidocs/wiki";
#}
#elseif ($REMOTEWIKIDOCS) {
#    $WIKIDOCURL  = "https://users.emulab.net/wikidocs/wiki";
#}
#else {
#    $WIKIDOCURL  = "/wikidocs/wiki";
#}

$TBPROJ_DIR     = "/proj";
$TBUSER_DIR	= "/users";
$TBGROUP_DIR	= "/groups";
$TBSCRATCH_DIR	= "";
$TBSHARE_DIR    = "/share";
$TBNSSUBDIR     = "nsdir";

$TBVALIDDIRS	  = "$TBPROJ_DIR, $TBUSER_DIR, $TBGROUP_DIR, $TBSHARE_DIR";
$TBVALIDDIRS_HTML = "<code>$TBPROJ_DIR</code>, <code>$TBUSER_DIR</code>, <code>$TBGROUP_DIR</code>, <code>$TBSHARE_DIR</code>";
if ($TBSCRATCH_DIR) {
    $TBVALIDDIRS .= ", $TBSCRATCH_DIR";
    $TBVALIDDIRS_HTML .= ", <code>$TBSCRATCH_DIR</code>";
}

$TBAUTHCOOKIE   = "NewHashCookie" . $TBCOOKIESUFFIX;
$TBNAMECOOKIE   = "NewMyUidCookie" . $TBCOOKIESUFFIX;
$TBLOGINCOOKIE  = "NewLoginCookie" . $TBCOOKIESUFFIX;

$HTTPTAG        = "http://";
$HTTPSTAG        = "https://";

$TBMAIL_OPS		= "Testbed Ops <$TBMAILADDR_OPS>";
$TBMAIL_WWW		= "Testbed WWW <$TBMAILADDR_WWW>";
$TBMAIL_APPROVAL	= "Testbed Approval <$TBMAILADDR_APPROVAL>";
$TBMAIL_LOGS		= "Testbed Logs <$TBMAILADDR_LOGS>";
$TBMAIL_AUDIT		= "Testbed Audit <$TBMAILADDR_AUDIT>";
$TBMAIL_NOREPLY		= "no-reply@$OURDOMAIN";

#
# This just spits out an email address in a page, so it does not need
# to be configured per development tree. It could be though ...
# 
$TBMAILADDR     = "<a href=\"mailto:$TBMAILADDR_OPS\">
                      Testbed Operations ($TBMAILADDR_OPS)</a>";

# So subscripts always know ...
putenv("HTTP_SCRIPT=1");

#
# Special headers alterting browsers to the fact that there's an RSS feed
# available for the page. Intended to be passed as an $extra_headers argument
# to PAGEHEADER
#
$RSS_HEADER_NEWS = "<link rel=\"alternate\" type=\"application/rss+xml\" " .
           "title=\"Emulab News\" href=\"$TBDOCBASE/news-rss.php\" />";


#
# Database constants and the like.
#
include("db_defs.php");
include("url_defs.php");
include("user_defs.php");
include("group_defs.php");
include("project_defs.php");
include("experiment_defs.php");

#
# Control how error messages are returned to the user. If the session is
# not actually "interactive" then do not send any output to the browser.
# Just save it up and let the page deal with it. 
#
$session_interactive  = 1;
$session_errorhandler = 0;

#
# Wrap up the mail function so we can prepend a tag to the subject
# line that indicates what testbed. Useful when multiple testbed
# email to the same list.
#
# 
function TBMAIL($to, $subject, $message, $headers = 0)
{
    global $THISHOMEBASE;
    global $SCRIPT_NAME;

    $subject = strtoupper($THISHOMEBASE) . ": $subject";

    $tag = "X-NetBed: " . basename($SCRIPT_NAME);
    
    if ($headers) {
	$headers = "$headers\n" . $tag;
    }
    else {
	$headers = $tag;
    }
    return mail($to, $subject, $message, $headers);
}

#
#
# Identical to perl function of the same name
#
#
function SendProjAdminMail($proj, $from, $to, $subject, $message, $headers = "")
{
    global $MAILMANSUPPORT, $TBMAIL_APPROVAL, $TBMAIL_AUDIT, $OURDOMAIN, $TBMAIL_WWW;
    $projadminmail = $MAILMANSUPPORT ? "$proj-admin@$OURDOMAIN" : $TBMAIL_APPROVAL;
    if ($headers) {
        $headers .= "\n";
    }
    if ($from == 'ADMIN') {
	$from = $projadminmail;
	$headers .= "Bcc: $projadminmail\n";
    } elseif ($to == 'ADMIN') {
	$to = $projadminmail;
	$headers .= "Reply-To: $projadminmail\n";
    } else {
	$headers .= "Bcc: $projadminmail\n";
    }
    $headers .= "From: $from\n";
    if ($from == 'AUDIT') {
	$from = $TBMAIL_AUDIT;
	$headers .= "Bcc: $TBMAIL_AUDIT\n";
    } elseif ($to == "AUDIT") {
	$to = $TBMAIL_AUDIT;
    } else {
	$headers .= "Bcc: $TBMAIL_AUDIT\n";
    }
    $headers .= "Errors-To: $TBMAIL_WWW\n"; # FIXME: Why?
    $headers = substr($headers, 0, -1);
    TBMAIL($to, $subject, $message, $headers);
}

#
# Internal errors should be reported back to the user simply. The actual 
# error information should be emailed to the list for action. The script
# should then terminate if required to do so.
#
function TBERROR ($message, $death, $xmp = 0) {
    global $TBMAIL_WWW, $TBMAIL_OPS, $TBMAILADDR, $TBMAILADDR_OPS;
    global $session_interactive, $session_errorhandler;
    $script = urldecode($_SERVER['REQUEST_URI']);

    CLEARBUSY();

    TBMAIL($TBMAIL_OPS,
         "WEB ERROR REPORT",
         "\n".
	 "In $script\n\n".
         "$message\n\n".
         "Thanks,\n".
         "Testbed WWW\n",
         "From: $TBMAIL_OPS\n".
         "Errors-To: $TBMAIL_WWW");

    if ($death) {
        PAGEERROR("defs.php:$message: Could not continue. Please contact <a href=\"http://trac.deterlab.net/wiki/GettingHelp\">Testbed Operations</a>");
	exit(1);
    }
    return 0;
}

#
# General user errors should print something warm and fuzzy.  If a
# header is not already printed and the dealth paramater is true, then
# assume the error is a precheck error and send an appropriate HTTP
# response to prevent robots from indexing the page.  This currently
# defaults to a "400 Bad Request", but that may change in the future.
#
function USERERROR($message, $death = 1, 
	           $status_code = HTTP_400_BAD_REQUEST) {
    global $TBMAILADDR;
    global $session_interactive, $session_errorhandler;

    CLEARBUSY();

    if (! $session_interactive) {
	if ($session_errorhandler)
	    $session_errorhandler($message, $death);
	else
	    echo "$message";

	if ($death)
	    exit(1);
	return;
    }

    $msg = "<font size=+1><br>
            $message
      	    </font>
            <br><br><br>
            <font size=-1>
            Please <a href=\"http://trac.deterlab.net/wiki/GettingHelp\">file a ticket</a> if you feel this message is an error.
            </font>\n";

    if ($death) {
	PAGEERROR($msg, $status_code);
    }
    else
        echo "$msg\n";
}

#
# A form error.
#
function FORMERROR($field) {
    USERERROR("Missing field; ".
              "Please go back and fill out the \"$field\" field!", 1);
}

#
# A page argument error. 
# 
function PAGEARGERROR($msg = 0) {
#    $default = "Invalid page arguments: " .
#          	htmlspecialchars($_SERVER['REQUEST_URI']);
#
#    if ($msg) {
#	$default = "$default<br><br>$msg";
#    }
#    USERERROR($default, 1, HTTP_400_BAD_REQUEST);
}

#
# SUEXEC stuff.
#
# Save this stuff so we can generate better error messages and such.
# 
$suexec_cmdandargs   = "";
$suexec_retval       = 0;
$suexec_output       = "";
$suexec_output_array = null;

#
# Actions for suexec. 
#
define("SUEXEC_ACTION_CONTINUE",	0);
define("SUEXEC_ACTION_DIE",		1);
define("SUEXEC_ACTION_USERERROR",	2);
define("SUEXEC_ACTION_IGNORE",		3);
define("SUEXEC_ACTION_DUPDIE",		4);
# SUEXEC_ACTION_MAIL_TBLOGS to be ored with one of the above actions
define("SUEXEC_ACTION_MAIL_TBLOGS",     64);

#
# An suexec error.
#
function SUEXECERROR($action)
{
    global $suexec_cmdandargs, $suexec_retval;
    global $suexec_output;

    $foo  = "Shell Program Error. Exit status: $suexec_retval\n";
    $foo .= "  '$suexec_cmdandargs'\n";
    $foo .= "\n";
    $foo .= $suexec_output;

    switch ($action) {
    case SUEXEC_ACTION_CONTINUE:
	TBERROR($foo, 0, 1);
        break;
    case SUEXEC_ACTION_DIE:
	TBERROR($foo, 1, 1);
        break;
    case SUEXEC_ACTION_USERERROR:
	USERERROR("<XMP>$foo</XMP>", 1);
        break;
    case SUEXEC_ACTION_IGNORE:
	break;
    case SUEXEC_ACTION_DUPDIE:
	TBERROR($foo, 0, 1);
	USERERROR("<XMP>$foo</XMP>", 1);
        break;
    default:
	TBERROR($foo, 1, 1);
    }
}

#
# Run a program as a user.
#
function SUEXEC($uid, $gid, $cmdandargs, $action) {
    global $TBSUEXEC_PATH;
    global $suexec_cmdandargs, $suexec_retval;
    global $suexec_output, $suexec_output_array;
    global $TBMAIL_LOGS;

    $mail_tblog = 0;
    if ($action & SUEXEC_ACTION_MAIL_TBLOGS) {
	$action &= ~SUEXEC_ACTION_MAIL_TBLOGS;
	$mail_tblog = 1;
    }

    ignore_user_abort(1);

    $suexec_cmdandargs   = "$uid $gid $cmdandargs";
    $suexec_output_array = array();
    $suexec_output       = "";
    $suexec_retval       = 0;
    
    exec("$TBSUEXEC_PATH $suexec_cmdandargs",
	 $suexec_output_array, $suexec_retval);

    # Yikes! Something is not doing integer conversion properly!
    if ($suexec_retval == 255) {
	$suexec_retval = -1;
    }

    if (count($suexec_output_array)) {
	for ($i = 0; $i < count($suexec_output_array); $i++) {
	    $suexec_output .= "$suexec_output_array[$i]\n";
	}
    }

    if ($mail_tblog) {
	$mesg  = "$TBSUEXEC_PATH $suexec_cmdandargs\n";
	$mesg .= "Return Value: $suexec_retval\n\n";
	$mesg .= "--------- OUTPUT ---------\n";
	$mesg .= $suexec_output;
	
	TBMAIL($TBMAIL_LOGS, "suexec: $cmdandargs", $mesg);
    }

    #
    # The output is still available of course, via $suexec_output.
    # 
    if ($suexec_retval == 0 || $action == SUEXEC_ACTION_IGNORE) {
	return $suexec_retval;
    }
    SUEXECERROR($action);
    # Must return the shell value!
    return $suexec_retval;
}

#
# We invoke addpubkey as user www all the time. The implied user is passed
# along in an HTTP_ variable (see tbauth). This avoids a bunch of confusion
# that results from new users who do not have a context yet. 
#
function ADDPUBKEY($cmdandargs) {
    global $TBSUEXEC_PATH;

    return SUEXEC("www", "www", "webaddpubkey $cmdandargs",
		  SUEXEC_ACTION_CONTINUE);
}

#
# Verify a URL.
#
function CHECKURL($url, &$error) {
    global $HTTPTAG;
    global $HTTPSTAG;

    if (strlen($url)) {
	if (strstr($url, " ")) {
	    $error = "URL is malformed; spaces are not allowed!";
	    return 0;
	}
	
	if (strcmp($HTTPTAG, substr($url, 0, strlen($HTTPTAG))) &&
		strcmp($HTTPSTAG, substr($url, 0, strlen($HTTPSTAG)))) {
	    $error = "URL is malformed; must begin with $HTTPTAG or $HHTPSTAG.";
	    return 0;
	}


	# DETER's boss cannot see out into the outside world, so further URL
	# checks are impossible. -- tvf
/*
	$fp = @fopen($url, "r");
	if (!$fp) {
	    $error = "URL is not valid; Cannot be accessed!";
	    return 0;
	}
	fclose($fp);
*/
    }
    return 1;
}

#
# Check a password.
#
function CHECKPASSWORD($uid, $password, $name, $email, &$error)
{
    global $TBCHKPASS_PATH;

    # Watch for caller errors since this calls to the shell.
    if (empty($uid) || empty($password) || empty($name) || empty($email)) {
	$error = "Other required fields missing.";
	return 0;
    }

    $uid      = escapeshellarg($uid);
    $password = escapeshellarg($password);
    $stuff    = escapeshellarg("$name:$email");
    
    $mypipe = popen("$TBCHKPASS_PATH $password $uid $stuff", "w+");
    
    if ($mypipe) { 
        $retval=fgets($mypipe, 1024);
        if (strcmp($retval,"ok\n") != 0) {
            if(preg_match_all('/dictionary/i', $retval)) {
                $error = "Invalid DETER Password: it is based on a well known password. " . 
                         "Please see our <a href=\"https://trac.deterlab.net/wiki/Passwords\">Password Policy.</a>";
            } else {
                $error = "Invalid DETER Password: $retval";
            }
	    return 0;
	}
	return 1;
    }
    TBERROR("Checkpass Failure! Returned '$mypipe'.\n\n".
	    "$TBCHKPASS_PATH $password $uid '$name:$email'", 1);
}

#
# Grab a UUID (universally unique identifier).
#
function NewUUID()
{
    global $UUIDGEN_PATH;

    $uuid = shell_exec($UUIDGEN_PATH);
    
    if (isset($uuid) && $uuid != "") {
	return rtrim($uuid);
    }
    TBERROR("$UUIDGEN_PATH Failure", 1);
}

function LASTNODELOGIN($node)
{
}

function VALIDUSERPATH($path, $uid="", $pid="", $gid="", $eid="")
{
    global $TBPROJ_DIR, $TBUSER_DIR, $TBGROUP_DIR, $TBSCRATCH_DIR, $TBSHARE_DIR;

    #
    # No ids specified, just make sure it starts with an appropriate prefix.
    #
    if (!$uid && !$pid && !$gid && !$eid) {
	if (preg_match("#^$TBPROJ_DIR/.*#", $path) ||
	    preg_match("#^$TBUSER_DIR/.*#", $path) ||
	    preg_match("#^$TBGROUP_DIR/.*#", $path) ||
	    preg_match("#^$TBSHARE_DIR/.*#", $path)) {
	    return 1;
	}
	if ($TBSCRATCH_DIR && preg_match("#^$TBSCRATCH_DIR/.*#", $path)) {
	    return 1;
	}
	return 0;
    }

    # XXX for now, see tbsetup/libtestbed.pm for what should happen
    return 0;
}

#
# A function to print the contents of an array (recursively).
# Mostly useful for debugging.
#
function ARRAY_PRINT($arr) {
  if (!is_array($arr)) { echo "non-array '$arr'\n"; }
  foreach ($arr as $i => $val) {
    echo("'$i' - '$val'\n");
    if (is_array($val)) {
      echo "Sub-array $i:\n";
      array_print($val);
      echo "End Sub-array $i.\n";
    }
  }
}

#
# Return Yes or No given boolean
#
function YesNo($bool) {
    return ($bool ? "Yes" : "No");
}

#
# If the page was accessed via http redirect to https and exit
# otherwise do nothing
#
function RedirectHTTPS() {
    global $WWWHOST,$drewheader;
    if ($drewheader) {
	trigger_error(
	    "PAGEHEADER called before RedirectHTTPS ".
	    "Won't be able to redirect to HTTPS if necessary ".
	    "in ". $_SERVER['SCRIPT_FILENAME'] . ",",
	    E_USER_WARNING);
    } else if (!@$_SERVER['HTTPS'] && $_SERVER['REQUEST_METHOD'] == 'GET') {
	header("Location: https://$WWWHOST". $_SERVER['REQUEST_URI']);
	exit;
    }
}

# MOVED TO FRONT
# Beware empty spaces (cookies)!
# 
#require("tbauth.php");
#
#
# Okay, this is what checks the login and spits out the menu.
#
#require("Sajax.php");
#require("menu.php");
?>
