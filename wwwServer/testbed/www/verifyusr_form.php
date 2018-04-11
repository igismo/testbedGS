<?php
#
# EMULAB-COPYRIGHT
# Copyright (c) 2000-2002, 2005, 2006 University of Utah and the Flux Group.
# All rights reserved.
#
include("defs.php");

#
# Only known and logged in users can be verified.
#
$this_user = CheckLoginOrDie(CHECKLOGIN_UNVERIFIED|CHECKLOGIN_NEWUSER|
			     CHECKLOGIN_WEBONLY);
$uid       = $this_user->uid();

#
# Standard Testbed Header
#
PAGEHEADER("New User Verification");

echo "<p>
      The purpose of this page is to verify, for security purposes, that
      information given in your application is authentic. If you never
      received a key at the email address given on your application, please
      <a href=\"http://trac.deterlab.net/wiki/GettingHelp\">file a ticket</a>
      for further assistance.
      <p>\n";

echo "<table align=\"center\" border=\"1\">
      <form action=\"verifyusr.php\" method=\"post\">\n";

echo "<tr>
          <td>Key:</td>
          <td><input type=\"text\" name=\"key\" size=20></td>
      </tr>\n";

echo "<tr>
         <td colspan=\"2\" align=\"center\">
             <b><input type=\"submit\" value=\"Submit\"></b></td>
      </tr>\n";

echo "</form>\n";
echo "</table>\n";

#
# Standard Testbed Footer
# 
PAGEFOOTER();
?>




