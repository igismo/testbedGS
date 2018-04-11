<?php
#
require("defs.php");

#
# Standard Testbed Header
#
PAGEHEADER("DETER IP Address");

if ($login_user) {
    echo "<H2>";
    echo "DETER believes that your ip address is: $REMOTE_ADDR";
    echo "</H2>";
} else {

    echo "<center>";
    echo "<font color=\"red\" size=\"+1\">Please log in.</font>";
    echo "</center>";
}
?>

<br>
<br>

<script language="JavaScript"><!--
function setjs() {
 if(navigator.product == 'Gecko') {
   document.loginform["interface"].value = 'mozilla';
 }else if(window.opera && document.childNodes) {
   document.loginform["interface"].value = 'opera7';
 }else if(navigator.appName == 'Microsoft Internet Explorer' &&
    navigator.userAgent.indexOf("Mac_PowerPC") > 0) {
    document.loginform["interface"].value = 'konqueror';
 }else if(navigator.appName == 'Microsoft Internet Explorer' &&
 document.getElementById && document.getElementById('ietest').innerHTML) {
   document.loginform["interface"].value = 'ie';
 }else if(navigator.appName == 'Konqueror') {  
    document.loginform["interface"].value = 'konqueror';
 }else if(window.opera) {
   document.loginform["interface"].value = 'opera';  
 }
}
//-->
</script>

<?php

#
# Standard Testbed Footer
#
PAGEFOOTER();
?>

