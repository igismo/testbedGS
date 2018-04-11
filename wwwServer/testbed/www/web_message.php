<?php
require("db_defs.php");
$message = TBGetSiteVar("web/message");
if (0 != strcmp($message,"")) {
    echo $message;
}
?>
