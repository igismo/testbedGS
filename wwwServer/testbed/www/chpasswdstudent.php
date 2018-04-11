<?php
#
# EMULAB-COPYRIGHT
# Copyright (c) 2000-2007 University of Utah and the Flux Group.
# All rights reserved.
#
include("defs.php");

#
# Verify page arguments.
#
$reqargs = RequiredPageArguments("user",   PAGEARG_STRING);
$optargs = OptionalPageArguments("simple", PAGEARG_BOOLEAN,
				 "key",    PAGEARG_STRING,
				 "reset",  PAGEARG_STRING);

# Display a simpler version of this page.
if (isset($simple) && $simple) {
    $simple = 1;
    $view = array('hide_banner' => 1,
		  'hide_copyright' => 1,
		  'hide_sidebar' => 1);
}
else {
    $simple = 0;
    $view   = array();
}

if (!isset($user) || $user == "" || !User::ValidWebID($user) ||
    !isset($key) || $key == "" || !preg_match("/^[\w]+$/", $key)) {
    PAGEARGERROR();
}

# Must use https!
if (!isset($_SERVER["HTTPS"])) {
    PAGEHEADER("Reset Your Password", $view);
    USERERROR("Must use https:// to access this page!", 1);
}

#
# Must not be logged in.
# 
if (GETLOGIN() != FALSE) {
    PAGEHEADER("Reset Your Password", $view);

    echo "<h3>
              You are logged in. You must already know your password!
          </h3>\n";

    PAGEFOOTER($view);
    die("");
}

function validate($item, $type)
{
    # For names just check that there are two 
    # alphabetical strings

    if ($type == "name")
       if (!preg_match("/\S\s+\S/", $item))
       	  return false;
       else
	  return true;

    # For phones check that there are at least 6 digits, and that there are
    # only digits
    if ($type == "phone")
       if (!preg_match("/^(?:(?:\+?1\s*(?:[.-]\s*)?)?(?:\(\s*([2-9]1[02-9]|[2-9][02-8]1|[2-9][02-8][02-9])\s*\)|([2-9]1[02-9]|[2-9][02-8]1|[2-9][02-8][02-9]))\s*(?:[.-]\s*)?)?([2-9]1[02-9]|[2-9][02-9]1|[2-9][02-9]{2})\s*(?:[.-]\s*)?([0-9]{4})(?:\s*(?:#|x\.?|ext\.?|extension)\s*(\d+))?$/", $item))
       	  return false;
       else
	  return true;

}
#
# Spit out the form.
# 
function SPITFORM($target_user, $key, $failed, $simple, $view)
{
    global	$TBBASE;

    
    PAGEHEADER("Reset Your Password", $view);

    if ($failed) {
	echo "<center>
              <font size=+1 color=red>
              $failed. Please try again.
              </font>
              </center><br>\n";
    }


    $chpass_url = CreateURL("chpasswdstudent", $target_user,
			    "key", $key, "simple", $simple);
	
    echo "<table align=center border=1>
          <form action='${TBBASE}/$chpass_url' method=post>\n";

    $target_uid = $target_user->uid();
    $sql = "select usr_name, usr_phone from users where uid='$target_uid'";
    $query_results = DBQueryFatal($sql);
    $row = mysql_fetch_array($query_results);
    $name = $row['usr_name'];
    $phone = $row['usr_phone'];

    if (!$failed)
       if (preg_match("/^Student/", $name) || preg_match("/^Teaching/", $name) || preg_match("/^111/", $phone))
    {
	echo "<center>
              <font size=+1>
              Please enter your name, phone and a new password.<br><br>
              </font>
              </center>\n";
    }
    else
    {
	echo "<center>
              <font size=+1>
              Please enter a new password.<br><br>
              </font>
              </center>\n";

    }

    if (preg_match("/^Student/", $name) || preg_match("/^Teaching/", $name))
    {
        echo "<tr>
              <td>First and last name:</td>
              <td class=left>
                  <input type=text
                         name=\"usr_name\"
                         size=30></td>
          </tr>\n";
	
    }

    if (preg_match("/^111/", $phone))
    {
    echo "<tr>
              <td>Phone:</td>
              <td class=left>
                  <input type=text
                         name=\"usr_phone\"
                         size=12></td>
          </tr>\n";
    }

 
    

    echo "<tr>
              <td>Password:</td>
              <td class=left>
                  <input type=password
                         name=\"password1\"
                         size=12></td>
          </tr>\n";

    echo "<tr>
              <td>Retype Password:</td>
              <td class=left>
                  <input type=password
                         name=\"password2\"
                         size=12></td>
         </tr>\n";

    echo "<tr>
             <td align=center colspan=2>
                 <b><input type=submit value=\"Reset Password\"
                           name=reset></b></td>
          </tr>\n";
    

    echo "</form>
          </table>\n";
}

#
# Check to make sure that the key is valid and that the timeout has not
# expired.
#
if (! ($target_user = User::Lookup($user))) {
    # Silent error about invalid users.
    PAGEARGERROR();
}
$usr_email  = $target_user->email();
$usr_name   = $target_user->name();
$target_uid = $target_user->uid();

# Silent error when there is no key/timeout set for the user.
if (!$target_user->chpasswd_key() || !$target_user->chpasswd_expires()) {
    PAGEARGERROR();
}
if ($target_user->chpasswd_key() != $key) {
    USERERROR("You do not have permission to change your password!", 1);
}
if (time() > $target_user->chpasswd_expires()) {
    USERERROR("Your key has expired. Please request a
               <a href='password.php'>new key</a>.", 1);
}

#
# If not clicked, then put up a form.
#
if (! isset($reset)) {
    SPITFORM($target_user, $key, 0, $simple, $view);
    PAGEFOOTER();
    return;
}

#
# Reset clicked. Verify a proper password. 
#
$password1 = $_POST['password1'];
$password2 = $_POST['password2'];
$new_name = addslashes($_POST['usr_name']);
$new_phone = $_POST['usr_phone'];

$target_uid = $target_user->uid();
$sql = "select usr_name, usr_phone from users where uid='$target_uid'";
$query_results = DBQueryFatal($sql);
$row = mysql_fetch_array($query_results);
$name = $row['usr_name'];
$phone = $row['usr_phone'];
$setname = false;
$setphone = false;

# Did we need to reset the name and phone?
if (preg_match("/^Student/", $name) || preg_match("/^Teaching/", $name)) 
   if(!validate($new_name, "name"))
   	SPITFORM($target_user, $key,
             "You must enter a valid first and last name", $simple, $view);
   else
	$setname = true;

if (preg_match("/^111/", $phone))
   if(!validate($new_phone, "phone"))
      SPITFORM($target_user, $key,
             "You must enter a valid phone number", $simple, $view);
   else
	$setphone = true;


if (!isset($password1) || $password1 == "" ||
    !isset($password2) || $password2 == "") {
    SPITFORM($target_user, $key,
	     "You must supply a password", $simple, $view);
    PAGEFOOTER();
    return;
}
if ($password1 != $password2) {
    SPITFORM($target_user, $key,
	     "Two passwords do not match", $simple, $view);
    PAGEFOOTER();
    return;
}
if (! CHECKPASSWORD($target_uid,
		    $password1, $usr_name, $usr_email, $checkerror)){
    SPITFORM($target_user, $key, $checkerror, $simple, $view);
    PAGEFOOTER();
    return;
}

# Clear the cookie from the browser.
setcookie($TBAUTHCOOKIE, "", time() - 1000000, "/", $TBAUTHDOMAIN, 0);

# Okay to spit this now that the cookie has been sent (cleared).
PAGEHEADER("Reset Your Password", $view);

$encoding = crypt("$password1");
$safe_encoding = escapeshellarg($encoding);

STARTBUSY("Resetting your password");

# 
# Change name and phone in DB
#

if ($setname)
  DBQueryFatal("update users set usr_name='$new_name' where uid='$target_uid'");
if ($setphone)
  DBQueryFatal("update users set usr_phone='$new_phone' where uid='$target_uid'");


#
# Invoke backend to deal with this.
#
if (!HASREALACCOUNT($target_uid)) {
    SUEXEC("www", "www",
	   "webtbacct passwd $target_uid $safe_encoding",
	   SUEXEC_ACTION_DIE);
}
else {
    SUEXEC($target_uid, "www",
	   "webtbacct passwd $target_uid $safe_encoding",
	   SUEXEC_ACTION_DIE);
}

CLEARBUSY();

echo "<br>
      Your password has been changed.\n";

#
# Standard Testbed Footer
# 
PAGEFOOTER();
?>
