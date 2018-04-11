<?php
#
# This file is part of the Netbed/Emulab network testbed software.
# In brief, you can't redistribute it or use it for commercial purposes,
# and you must give appropriate credit and return improvements to Utah.
# See the file LICENSE at the root of the source tree for details.
# 
# Copyright (c) 2000-2003 University of Utah and the Flux Group.
# All rights reserved.
#
require("defs.php");

#
# Only known and logged in users can do this.
#
$uid = GETLOGIN();
LOGGEDINORDIE($uid);

# Page arguments.
$printable = $_GET['printable'];
$docname   = $_GET['docname'];

# Pedantic page argument checking. Good practice!
if (!isset($docname) ||
    (isset($printable) && !($printable == "1" || $printable == "0"))) {
    PAGEARGERROR();
}
if (!isset($printable))
    $printable = 0;

#
# Standard Testbed Header
#
if (!$printable) {
    PAGEHEADER("DETERlab Documentation");
}

#
# Need to sanity check the path! Allow only [word].html files
#
if (!preg_match("/^[-\w]+\.(html|txt)$/", $docname)) {
    USERERROR("Illegal document name: $docname!", 1);
}

if ($printable) {
    #
    # Need to spit out some header stuff.
    #
    echo "<html>
          <head>
  	  <link rel='stylesheet' href='tbstyle-plain.css' type='text/css'>
          </head>
          <body>\n";
}
else {
	echo "<b><a href=" . $_SERVER['REQUEST_URI'] . "&printable=1>
                 Printable version of this document</a></b><br>\n";
}

readfile("$docname");

#
# Standard Testbed Footer
# 
if ($printable) {
    echo "</body>
          </html>\n";
}
else {
    PAGEFOOTER();
}
?>

