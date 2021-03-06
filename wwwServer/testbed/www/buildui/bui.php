<?php
#
# EMULAB-COPYRIGHT
# Copyright (c) 2000-2007 University of Utah and the Flux Group.
# All rights reserved.
#
chdir("..");
require("defs.php");
chdir("buildui");

#
# Only known and logged in users can do this.
#
$this_user = CheckLoginOrDie();
$uid       = $this_user->uid();
$isadmin   = ISADMIN();

#
# Verify page arguments.
#
$optargs = OptionalPageArguments("action", PAGEARG_STRING,
				 "experiment", PAGEARG_EXPERIMENT);

if (isset($action) && $action == "modify") {
    if (!isset($experiment)) {
	USERERROR("Must provide experiment to modify!", 1);
    }
    $pid = $experiment->pid();
    $eid = $experiment->eid();
    echo "<h3>Modifying $pid/$eid:</h3>";
}

#
# Standard Testbed Header
#
PAGEHEADER("NetBuild");

?>

<applet code="Netbuild.class" width=800 height=600 MAYSCRIPT>
  <param name='exporturl'
         value="<?php echo $TBBASE?>/buildui/nssave.php">
  <param name='importurl'
         value="<?php echo $TBBASE?>/shownsfile.php">
  <param name='modifyurl'
         value="<?php echo $TBBASE?>/modifyexp.php">
  <param name='uid'
	 value="<?php echo $uid?>">
  <param name='auth'
	 value="<?php echo $HTTP_COOKIE_VARS[$TBAUTHCOOKIE]?>">
  <param name='expcreateurl'
         value="<?php echo $TBBASE?>/beginexp_html.php">
<?php
    if (isset($action) && $action == "modify") {
	echo "<param name='action' value='modify'>";
	echo "<param name='pid' value='$pid'>";
	echo "<param name='eid' value='$eid'>";
    }
?>
<pre>
NetBuild requires Java.

If you want to use NetBuild,
you should either enable Java in your browser 
or use the latest version of a Java-compliant browser 
(such as Mozilla, Netscape or Internet Explorer.)

Once you've gotten your Java on, 
please come back and enjoy NetBuild.
We'll still be here waiting for you.	

   - Testbed Ops
</pre>
</applet>

<hr>
<h2>Basic usage:</h2>
<ul>
<li>
  Drag Nodes and LANs from the <i>Palette</i> on the left into the <i>Workarea</i> in the middle.
</li>
<li>
  To link a Node to a Node (or to a LAN,) select the node (by clicking it,) then hold "ctrl" and click on the node (or LAN) you wish to link it to.
</li>
<li>
  Clicking the "create experiment" button will send you to the Emulab "create experiment" web page, automatically generating and sending an NS file for your designed topology along. From that page, you may create the experiment and/or view the generated NS file.
</li>
</ul>
<p>
<a href="<? echo $WIKIDOCURL ?>/netbuilddoc">Netbuild Full Reference</a>
</p>

<?php
#
# Standard Testbed Footer
# 
PAGEFOOTER();
?>
