<?php
#
# EMULAB-COPYRIGHT
# Copyright (c) 2000-2007, 2009 University of Utah and the Flux Group.
# All rights reserved.
#
include("defs.php");

#
# Verify page arguments.
#
$optargs = OptionalPageArguments("simple", PAGEARG_BOOLEAN,
                 "reset",  PAGEARG_STRING,
                 "email",  PAGEARG_STRING,
                 "phone",  PAGEARG_STRING);

# Display a simpler version of this page.
if (!isset($simple)) {
    $simple = 0;
}

#
# Turn off some of the decorations and menus for the simple view
#
if ($simple) {
    $view = array('hide_banner' => 1, 'hide_copyright' => 1,
    'hide_sidebar' => 1);
} else {
    $view = array();
}

# Must use https!
if (!isset($_SERVER["HTTPS"])) {
    PAGEHEADER("Forgot Your Password?", $view);
    USERERROR("Must use https:// to access this page!", 1);
}

#
# Must not be logged in.
# 
if (CheckLogin($check_status)) {
    PAGEHEADER("Forgot Your Password?", $view);

    echo "<h3>
              You are logged in. You must already know your password!
          </h3>\n";
    
    PAGEFOOTER($view);
    die("");
}

#
# Spit out the form.
# 
function SPITFORM($email, $phone, $failed, $simple, $view)
{
    global  $TBBASE;
    global  $WIKIDOCURL;
    
    PAGEHEADER("Forgot Your Username or Password?", $view);

    if ($failed) {
        echo "<center>
              <font size=+1 color=red>
              $failed
              Please try again.
              </font>
              </center><br>\n";
    }

    echo "<br><blockquote>
	      If the email address and phone number you give us matches
          our user records, we will email your username and a URL
          that will allow you to change your password.  Please
          provide your phone number in standard dashed notation;
          no extensions or room numbers, etc. We will do our best
          to match it up against our user records.

          </blockquote>\n";

    echo "<table align=center border=1>
          <form action=${TBBASE}/password.php method=post>
          <tr>
              <td>Email Address:</td>
              <td><input type=text
                         value=\"$email\"
                         name=email size=30></td>
          </tr>
          <tr>
              <td>Phone Number:</td>
              <td><input type=text
                         value=\"$phone\"
                         name=phone size=20></td>
          </tr>
          <tr>
             <td align=center colspan=2>
                 <b><input type=submit value=\"Reset Password\"
                           name=reset></b>
             </td>
          </tr>\n";
    
    if ($simple) {
        echo "<input type=hidden name=simple value=$simple>\n";
    }

    echo "</form>
          </table>\n";

    echo "<br><br>\n";
}

#
# If not clicked, then put up a form.
#
if (!isset($reset)) {
    if (!isset($email))
    $email = "";
    if (!isset($phone))
    $phone = "";
    
    SPITFORM($email, $phone, 0, $simple, $view);
    return;
}

#
# Reset clicked. See if we find a user with the given email/phone. If not
# zap back to the form. 
#

# remove all non-numbers so users can enter phones with (), etc.
# resolves #237
$phone = preg_replace('/[^\d]/', '', $phone);

if (!isset($phone) || $phone == "" || !TBvalid_phone($phone) ||
    !isset($email) || $email == "" || !TBvalid_email($email)) {
    $v1 = TBvalid_phone($phone);
    $v2 = TBvalid_email($email);
    SPITFORM($email, $phone,
         "The email($v2) or phone($v1) contains invalid characters.",
         $simple, $view);
    return;
}

if (! ($user = User::LookupByEmail($email))) {
    SPITFORM($email, $phone,
         "The email or phone does not match an existing user.",
         $simple, $view);
    return;
}
$uid       = $user->uid();
$usr_phone = $user->phone();
$uid_name  = $user->name();
$uid_email = $user->email();

#
# Compare phone by striping out anything but the numbers.
#
if (preg_replace("/[^0-9]/", "", $phone) !=
    preg_replace("/[^0-9]/", "", $usr_phone)) {
    SPITFORM($email, $phone,
         "The email or phone does not match an existing user.",
         $simple, $view);
    return;
}

#
# A matched user, but if frozen do not go further. Confuses users.
#
if ($user->weblogin_frozen()) {
    PAGEHEADER("Forgot Your Password?", $view);
    echo "<center>
         The password cannot be changed; please contact $TBMAILADDR.<br>
             <br>
          <font size=+1 color=red>
            Please do not attempt to change your password again;
                it will not work!
          </font>
          </center><br>\n";
    return;
}

#
# Yep. Generate a random key and send the user an email message with a URL
# that will allow them to change their password. 
#
$key  = md5(uniqid(rand(),1));


PAGEHEADER("Forgot Your Password?", $view);

$user->SetChangePassword($key, "UNIX_TIMESTAMP(now())+(60*60)");

TBMAIL("$uid_name <$uid_email>",
       "Password Reset requested by '$uid'",
       "\n".
       "Your DETER username is '$uid'\n\n".
       "If you need to reset your password, follow the link below\n".
       "within the next 60 minutes.  If the link expires, you can\n".
       "request a new one from the web interface.\n".
       "\n".
       "    ${TBBASE}/chpasswd.php?user=$uid&key=$key&simple=$simple\n".
       "\n".
       "The request originated from IP: " . $_SERVER['REMOTE_ADDR'] . "\n".
       "The web browser used to make the request was: " . $_SERVER['HTTP_USER_AGENT'] . "\n".
       "\n".
       "Thanks,\n".
       "Testbed Operations\n",
       "From: $TBMAIL_OPS\n".
       "Bcc: $TBMAIL_AUDIT\n".
       "Errors-To: $TBMAIL_WWW");

echo "<br>
      An email message has been sent to your account. In it you
      will find your username and a URL that will allow you to
      change your password. The link will <b>expire in 60 minutes</b>.
      If the link does expire before you have a chance to use it,
      simply come back and request a <a href='password.php'>new
      one</a>.
      \n";

#
# Standard Testbed Footer
# 
PAGEFOOTER();
?>
