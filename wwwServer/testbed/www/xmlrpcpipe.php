<?php
#
# EMULAB-COPYRIGHT
# Copyright (c) 2004, 2005, 2006 University of Utah and the Flux Group.
# All rights reserved.
#
# This is an included file. No headers or footers.
#
# Stuff to use the xmlrpc client/server. This is functionally equivalent
# to the perl stuff I wrote in xmlrpc/libxmlrpc.pm.in.
#
include("defs.php");

$RPCSERVER  = "boss.minibed.deterlab.net";
$RPCPORT    = "3069";
$FSDIR_USERS = "/users";

#
# Emulab XMLRPC defs.
#
# WARNING: If you change this stuff, also change defs in xmlrpc directory.
#
define("XMLRPC_RESPONSE_SUCCESS",	0);
define("XMLRPC_RESPONSE_BADARGS",	1);
define("XMLRPC_RESPONSE_ERROR",		2);
define("XMLRPC_RESPONSE_FORBIDDEN",	3);
define("XMLRPC_RESPONSE_BADVERSION",	4);
define("XMLRPC_RESPONSE_SERVERERROR",	5);
define("XMLRPC_RESPONSE_TOOBIG",	6);
define("XMLRPC_RESPONSE_REFUSED",	7);
define("XMLRPC_RESPONSE_TIMEDOUT",	8);

##
# The package version number
#
define("XMLRPC_PACKAGE_VERSION",	0.1);

$this_user = CheckLoginOrDie();
$uid       = $this_user->uid();
$isadmin   = ISADMIN();

#
# Invoke the ssl xmlrpc client in raw mode, passing it an encoded XMLRPC
# string, reading back an XMLRPC encoded response, which is converted to
# a PHP datatype with the ParseResponse() function above. In other words,
# we invoke a method on a remote xmlrpc server, and get back a response.
# Invoked as the current user, but the actual uid of the caller is contained
# in the ssl certificate we use, which for now is the elabinelab certificate
# of the creator (since that is the only place this code is being used).
#

$descriptorspec = array(0 => array("pipe", "r"),
		        1 => array("pipe", "w"));

$process = proc_open("$TBSUEXEC_PATH $uid www webxmlrpc -r ".
		     "-s $RPCSERVER -p $RPCPORT ".
		     "--cert $FSDIR_USERS/$uid/.ssl/emulab.pem",
		     $descriptorspec, $pipes);

if (! is_resource($process)) {
	TBERROR("Could not invoke XMLRPC backend!\n".
		"$uid www $method\n".
		print_r($arghash, true), 1);
}

# $pipes now looks like this:
# 0 => writeable handle connected to child stdin
# 1 => readable handle connected to child stdout

#
# Send request
#
fwrite($pipes[0], file_get_contents("php://input"));
#
# Signal end-of-transmission
#
fclose($pipes[0]);

#
# Now read back the results.
#
$output = stream_get_contents($pipes[1]);
fclose($pipes[1]);

# It is important that you close any pipes before calling
# proc_close in order to avoid a deadlock.
$return_value = proc_close($process);

if ($return_value || $output == "") {
	TBERROR("XMLRPC backend failure!\n".
		"$uid returned $return_value\n".
		"XML:\n" .
		"$all_data\n\n" .
		"Output:\n" .
		"$output\n", 1);
}

header("Content-Type: text/xml");
header("Content-Length: " . strlen($output));

echo $output;
