<?php
#
# EMULAB-COPYRIGHT
# Copyright (c) 2000-2007 University of Utah and the Flux Group.
# All rights reserved.
#
include("defs.php");

#
# Define a stripped-down view of the web interface - less clutter
#
$view = array(
    'hide_banner' => 1,
    'hide_sidebar' => 1,
    'hide_copyright' => 1
);

#
# Only known and logged in users can begin experiments.
#
$this_user = CheckLoginOrDie();
$uid       = $this_user->uid();
$isadmin   = ISADMIN();

#
# Verify page arguments.
#
$optargs = OptionalPageArguments("nsdata",          PAGEARG_ANYTHING,
				 "formfields",      PAGEARG_ARRAY,
				 "fromform",        PAGEARG_BOOLEAN);

#
# Standard Testbed Header (not that we know if want the stripped version).
#

PAGEHEADER("Syntax Check NS File, then containerize",
	   (isset($fromform) || isset($nsdata) ? $view : null));

#
# Not allowed to specify both a local and an upload!
#
$speclocal  = 0;
$specupload = 0;
$specform   = 0;
$nsfile     = "";
$tmpfile    = 0;
$myPid = $formfields['exp_pid'];
$myExp = $formfields['exp_id'];

if (isset($formfields)) {
    $exp_localnsfile = $formfields['exp_localnsfile'];
}

if (isset($exp_localnsfile) && strcmp($exp_localnsfile, "")) {
    $speclocal = 1;
}
if (isset($_FILES['exp_nsfile']) &&
    $_FILES['exp_nsfile']['name'] != "" &&
    $_FILES['exp_nsfile']['tmp_name'] != "") {
    if ($_FILES['exp_nsfile']['size'] == 0) {
        USERERROR("Uploaded NS file does not exist, or is empty ");
    }
    $specupload = 1;
}
if (!$speclocal && !$specupload && isset($nsdata))  {
    $specform = 1;
}

if ($speclocal + $specupload + $specform > 1) {
    USERERROR("You may not specify both an uploaded NS file and an ".
	      "NS file that is located on the Emulab server", 1);
}
#
# Gotta be one of them!
#
if (!$speclocal && !$specupload && !$specform) {
    USERERROR("You must supply an NS file!", 1);
}

if ($speclocal) {
    #
    # No way to tell from here if this file actually exists, since
    # the web server runs as user www. The startexp script checks
    # for the file before going to ground, so the user will get immediate
    # feedback if the filename is bogus.
    #
    # Do not allow anything outside of the usual directories. I do not think
    # there is a security worry, but good to enforce it anyway.
    #
    if (!preg_match("/^([-\@\w\.\/]+)$/", $exp_localnsfile)) {
	USERERROR("NS File: Pathname includes illegal characters", 1);
    }
    if (!VALIDUSERPATH($exp_localnsfile)) {
	USERERROR("NS File: You must specify a server resident file in " .
		  "one of: ${TBVALIDDIRS}.", 1);
    }
    
    $nsfile = $exp_localnsfile;
    $nonsfile = 0;
}
elseif ($specupload) {
    #
    # XXX
    # Set the permissions on the NS file so that the scripts can get to it.
    # It is owned by www, and most likely protected. This leaves the
    # script open for a short time. A potential security hazard we should
    # deal with at some point.
    #
    $nsfile = $_FILES['exp_nsfile']['tmp_name'];
    chmod($nsfile, 0666);
    $nonsfile = 0;
} else # $specform
{
    #
    # Take the NS file passed in from the form and write it out to a file
    #
    $tmpfile = 1;

    #
    # Generate a hopefully unique filename that is hard to guess.
    # See backend scripts.
    # 
    list($usec, $sec) = explode(' ', microtime());
    srand((float) $sec + ((float) $usec * 100000));
    $foo = rand();

    $nsfile = "/tmp/$uid-$foo.nsfile";
    $handle = fopen($nsfile,"w");
    fwrite($handle,$nsdata);
    fclose($handle);
}

STARTBUSY("Starting containerize $myPid $myExp " .
	  ($speclocal ? "local file" :
	   ($specupload ? "uploaded file" :
	    ($specform ? "form" : "???"))));

### GORAN get all options from $formfields
$myOptions = " ";
$myNeoOptions = " ";
if (isset($formfields['--config']) && strcmp($formfields['--config'], "") != 0)
{ $myOptions .= " --config"."=" . $formfields['--config']; }
if (isset($formfields['--image']) && strcmp($formfields['--image'], "--image") == 0)
	{ $myOptions .= " " . $formfields['--image']; }
else	{ $myOptions .= " " . $formfields['--no-image']; }
if (isset($formfields['--pnode-type']) && strcmp($formfields['--pnode-type'], "") != 0)
{ $myOptions .= " --pnode-types"."=".$formfields['--pnode-types']; }
if (isset($formfields['--debug']) && strcmp($formfields['--debug'], "--debug") == 0)
{ $myOptions .= " --debug"; }
if (isset($formfields['--openvz-template']) && strcmp($formfields['--openvz-template'], "") != 0)
{ $myOptions .= " --openvz-template=" . $formfields['--openvz-template']; }
if (isset($formfields['--end-node-shaping']) && strcmp($formfields['--end-node-shaping'], "--end-node-shaping") == 0)
{ $myOptions .= " --end-node-shaping"; }
if (isset($formfields['--packing']) && strcmp($formfields['--packing'], "") != 0)
{ $myOptions .= " --packing=" . $formfields['--packing']; }
if (isset($formfields['--force-partition']) && strcmp($formfields['--force-partition'], "") != 0)
{ $myOptions .= " --force-partition"; }
if (isset($formfields['--size']) && strcmp($formfields['--size'], "") != 0)
{ $myOptions .= " --size=" . $formfields['--size']; }
if (isset($formfields['--vde-switch-shaping']) && strcmp($formfields['--vde-switch-shaping'], "") != 0)
{ $myOptions .= " --vde-switch-shaping"; }
if (isset($formfields['--pass-pnodes']) && strcmp($formfields['--pass-pnodes'], "") != 0)
{ $myOptions .= " --pass-pnodes=" . $formfields['--pass-pnodes']; }
if (isset($formfields['--nodes-only']) && strcmp($formfields['--nodes-only'], "") != 0)
{ $myOptions .= " --nodes-only"; }
if (isset($formfields['--default-container']) && strcmp($formfields['--default-container'], "") != 0)
{ $myOptions .= " --default-container=" . $formfields['--default-container']; }
if (isset($formfields['--openvz-diskspace']) && strcmp($formfields['--openvz-diskspace'], "") != 0)
{ $myOptions .= " --openvz-diskspace=" . $formfields['--openvz-diskspace']; }
if (isset($formfields['--openvz-template-dir']) && strcmp($formfields['--openvz-template-dir'], "") != 0)
{ $myOptions .= " --openvz-template-dir=" . $formfields['--openvz-template-dir']; }
if (isset($formfields['--pass-nodes-only']) && strcmp($formfields['--pass-nodes-only'], "") != 0)
{ $myOptions .= " --pass-nodes-only=" . $formfields['--pass-nodes-only']; }
if (isset($formfields['--pass-size']) && strcmp($formfields['--pass-size'], "") != 0)
{ $myOptions .= " --pass-size=" . $formfields['--pass-size']; }
if (isset($formfields['--pass-pack']) && strcmp($formfields['--pass-pack'], "") != 0)
{ $myOptions .= " --pass-pack=" . $formfields['--pass-pack']; }
if (isset($formfields['--pre-routing']) && strcmp($formfields['--pre-routing'], "") != 0)
{ $myOptions .= " --pre-routing=" . $formfields['--pre-routing']; }
if (isset($formfields['--pnode-limit']) && strcmp($formfields['--pnode-limit'], "") != 0)
{ $myOptions .= " --pnode-limit=" . $formfields['--pnode-limit']; }
if (isset($formfields['--prefer-qemu-users']) && strcmp($formfields['--prefer-qemu-users'], "") != 0)
{ $myOptions .= " --prefer-qemu-users=" . $formfields['--prefer-qemu-users']; }
if (isset($formfields['--keep-tmp']) && strcmp($formfields['--keep-tmp'], "--keep-tmp") == 0)
{ $myOptions .= " --keep-tmp "; }
$myOptions .= " ";
print("MY OPTIONS: '$myOptions'\n");

if (isset($formfields['--server']) && strcmp($formfields['--server'], "") != 0)
{ $myNeoOptions .= " --server ".$formfields['--server']; }
if (isset($formfields['--port']) && strcmp($formfields['--port'], "") != 0)
{ $myNeoOptions .= " --port ".$formfields['--port']; }
if (isset($formfields['--loglevel']) && strcmp($formfields['--loglevel'], "") != 0)
{ $myNeoOptions .= " --loglevel ".$formfields['--loglevel']; }
$myNeoOptions .= " ";
print("MY NEO OPTIONS: '$myNeoOptions'\n");

$retval = 100;
### $retval = SUEXEC($uid, "www", "containerNeoCreate $myOptions $myPid $myExp $nsfile ", SUEXEC_ACTION_IGNORE);

if ($tmpfile) {
    unlink($nsfile);
}

#
# Fatal Error. Report to the user, even though there is not much he can
# do with the error. Also reports to tbops.
# 
if ($retval < 0) {
    SUEXECERROR(SUEXEC_ACTION_DIE);
    #
    # Never returns ...
    #
    die("");
}
STOPBUSY();

echo "<br><br>
      containerize status = $retval:
      <br>
      <XMP>$suexec_output</XMP>\n";

#echo "<center>";
#echo "<br>";
#echo "<h2>Your NS file looks good!</h2>";
#echo "</center>\n";

#
# Standard Testbed Footer
# 
PAGEFOOTER();
?>
