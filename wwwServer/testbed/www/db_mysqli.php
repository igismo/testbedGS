<?php
#
# Wrap legacy mysql calls with mysqli since Emulab has not updated
#
use Spiral\Goridge;
require "vendor/autoload.php";
function mysql_connect($myhost, $mybasename) {

    $mysqlip = "";
    $handle = @fopen("/var/www/mysqlip","r");
    if ($handle) {
        # USERERROR("HANDLE= $handle");
        $mysqlip = fread($handle, filesize("/var/www/mysqlip"));
        fclose($handle);
    } else {
         USERERROR("Couldn't open mysqlip file, MYSQL may not be UP!",1);
    }

    ## was localhost  172.20.0.3
    if ($mysqlip != "") {
        ##USERRROR("MYSQL IP= $mysqlip");
        ## $_SESSION['mylinkid'] = mysqli_connect($mysqlip, "root", "password", "tbdb");
        $_SESSION['mylinkid'] = mysqli_connect($mysqlip, "root", "", "tbdb");
        return $_SESSION['mylinkid'];
    }

    $handle = @fopen("/var/www/expmasterip","r");
    if ($handle) { // get experiment master ip
       $expip = fread($handle, filesize("/var/www/expmasterip"));
       fclose($handle);

       $rpc = new Goridge\RPC(new Goridge\SocketRelay($expip, 6666));
       #if ($rpc != NULL) {
            echo $rpc->call("App.Hi", "Hello From Testbed WEB SERVER");
       #}
    } else {
        USERERROR("Couldn't open expmasterip file, EXPMASTER may not be UP!",1);
    }

    return 0;
}

function mysql_select_db($mydb, $mylink) {
    return mysqli_select_db($mylink, $mydb);
}

function mysql_affected_rows($result) {
    return mysqli_affected_rows($result);
}

function mysql_num_rows($result) {
    return mysqli_num_rows($result);
}

function mysql_fetch_array($result) {
    return mysqli_fetch_array($result);
}

function mysql_fetch_assoc($result) {
    return mysqli_fetch_assoc($result);
}

function mysql_fetch_row($result) {
    return mysqli_fetch_row($result);
}

function mysql_data_seek($result, $row_number) {
    return mysqli_data_seek($result, $row_number);
}

function mysql_num_fields($result) {
    return mysqli_num_fields($result);
}

function mysql_field_name($result, $field_offset) {
    return mysqli_field_name($result, $field_offset);
}

function mysql_error() {
    return mysqli_error($_SESSION['mylinkid']);
}

function mysql_query($query) {
    return mysqli_query($_SESSION['mylinkid'], $query);
}

?>
