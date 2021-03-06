<?php
#
# EMULAB-COPYRIGHT
# Copyright (c) 2000-2007 University of Utah and the Flux Group.
# All rights reserved.
#
include("defs.php");

#
# Standard Testbed Header is sent below.
#
$optargs = OptionalPageArguments("show_archived",   PAGEARG_BOOLEAN,
				 "archive",         PAGEARG_STRING,
				 "single",          PAGEARG_BOOLEAN,
				 "restore",         PAGEARG_STRING,
				 "delete" ,         PAGEARG_STRING,
				 "delete_confirm",  PAGEARG_STRING,
                                 "showmydeter",     PAGEARG_STRING,
                                 "hidemydeter",     PAGEARG_STRING,
				 "add",             PAGEARG_STRING,
				 "addnew",          PAGEARG_STRING,
				 "edit",            PAGEARG_STRING,
				 "subject",         PAGEARG_ANYTHING,
				 "author",          PAGEARG_STRING,
				 "bodyfile",        PAGEARG_STRING,
				 "body",            PAGEARG_ANYTHING,
				 "msgid",           PAGEARG_STRING,
				 "date",            PAGEARG_STRING);

#
# If user is an admin, present edit options.
#
$this_user = CheckLogin($check_status);

if (! isset($show_archived)) {
    $show_archived = 0;
}
$show_archived = ($show_archived ? 1 : 0);

if ($this_user) {
    $uid     = $this_user->uid();
    $isadmin = ISADMIN();
}
else {
    $isadmin = 0;
}

if ($isadmin) {
    if (isset($delete_confirm)) {
	$safeid = addslashes($delete_confirm);

	DBQueryFatal("DELETE FROM webnews WHERE msgid='$safeid'");

	header("Location: news.php?show_archived=$show_archived");
	return;
    }
    if (isset($archive)) {
	$safeid = addslashes($archive);

	DBQueryFatal("update webnews set archived=1,archived_date=now() ".
		     "where msgid='$safeid'");

	header("Location: news.php?show_archived=$show_archived");
	return;
    }
    if (isset($restore)) {
	$safeid = addslashes($restore);

	DBQueryFatal("update webnews set archived=0,archived_date=NULL ".
		     "where msgid='$safeid'");

	header("Location: news.php?show_archived=$show_archived");
	return;
    }
    if (isset($showmydeter)) {
        $safeid = addslashes($showmydeter);

        DBQueryFatal("update webnews set show_mydeterlab=1 ".
                     "where msgid='$safeid'");

        header("Location: news.php?show_archived=$show_archived");
        return;
    }
    if (isset($hidemydeter)) {
        $safeid = addslashes($hidemydeter);

        DBQueryFatal("update webnews set show_mydeterlab=0 ".
                     "where msgid='$safeid'");

        header("Location: news.php?show_archived=$show_archived");
        return;
    }

    PAGEHEADER("News",NULL,$RSS_HEADER_NEWS);
    
    if (isset($delete)) {
	$delete = addslashes($delete);
	echo "<center>";
	echo "<h2>Are you sure you want to delete message #$delete?</h2>";
	echo "<form action='news.php' method='post'>\n";
	echo "<button name='delete_confirm' value='$delete'>Yes</button>\n";
	echo "&nbsp;&nbsp;";
	echo "<button name='nothin'>No</button>\n";
	echo "</form>";
	echo "</center>";
	PAGEFOOTER();
	die("");
    }

    if (isset($add)) {
	if (!isset($subject) || !strcmp($subject,"") ) {
	    # USERERROR("No subject!",1);
	    $subject = "Testbed News";
	} 
	if (!isset($author) || !strcmp($author,"") ) {
	    # USERERROR("No author!",1);
	    $author = "testbed-ops";
	} 

	if (isset($bodyfile) && 
	    strcmp($bodyfile,"") &&
	    strcmp($bodyfile,"none")) {
	    $bodyfile = addslashes($bodyfile);
	    $handle = @fopen($bodyfile,"r");
	    if ($handle) {
		$body = fread($handle, filesize($bodyfile));
		fclose($handle);
	    } else {
		USERERROR("Couldn't open uploaded file!",1);
	    }
	}

	if (!isset($body) || !strcmp($body,"")) {
	    USERERROR("No message body!",1);
	} 

	$subject = htmlspecialchars($subject);
	$slash_subject = addslashes($subject);
	$author  = addslashes($author);
	$body = addslashes($body);

	if (isset($msgid)) {
	    $msgid = addslashes($msgid);
	    if (!isset($date) || !strcmp($date,"") ) {
		USERERROR("No date!",1);
	    }
	    $date = addslashes($date);
	    DBQueryFatal("UPDATE webnews SET ".
			 "subject='$slash_subject', ".
			 "author='$author', ".
			 "date='$date', ".
			 "body='$body' ".			
			 "WHERE msgid='$msgid'");
	    echo "<h3>Updated message with subject '$subject'.</h3><br />";
	} else {	    
	    DBQueryFatal("INSERT INTO webnews (subject, date, author, body, show_mydeterlab) ".
			 "VALUES ('$slash_subject', NOW(), '$author', '$body', 1)");
	    echo "<h3>Posted message with subject '$subject'.</h3><br />";
	}
	flush();
	sleep(1);
	PAGEREPLACE("news.php");
	PAGEFOOTER();
	die("");
    }

    if (isset($edit)) {
	$edit = addslashes($edit);
	$query_result = 
	    DBQueryFatal("SELECT subject, author, body, msgid, date ".
			 "FROM webnews ".
		         "WHERE msgid='$edit'" );

	if (!mysql_num_rows($query_result)) {
	    USERERROR("No message with msgid '$edit'!",1);
	} 

	$row = mysql_fetch_array($query_result);
	$subject = htmlspecialchars($row["subject"], ENT_QUOTES);
	$date    = htmlspecialchars($row["date"],    ENT_QUOTES);
	$author  = htmlspecialchars($row["author"],  ENT_QUOTES);
	$body    = htmlspecialchars($row["body"],    ENT_QUOTES);
	$msgid   = htmlspecialchars($row["msgid"],   ENT_QUOTES);
    }

    if (isset($edit) || isset($addnew)) {	
	if (isset($addnew)) {
	    $author = $uid;
	    echo "<h3>Add new message:</h3>\n";
	} else {
	    echo "<h3>Edit message:</h3>\n";
	}

	echo "<form action='news.php' ".
             "enctype='multipart/form-data' ".
             "method='post'>\n";

	if (isset($msgid)) {
	    echo "<input type='hidden' name='msgid' value='$msgid' />";
	}
	
	if (!isset($subject)) {
	    $subject = "";
	}
	
	echo "<b>Subject:</b><br />".
	     "<input type='text' name='subject' size='50' value='$subject'>".
	     "</input><br /><br />\n";
	if (isset($date)) {
	    echo "<b>Date:</b><br />".
	         "<input type='text' name='date' size='50' value='$date'>".
		 "</input><br /><br />\n";
	}
	echo "<b>Posted by:</b><br />". 
	     "<input type='text' name='author' size='50' value='$author'>".
	     "</input><br /><br />\n". 
	     "<b>Body (HTML):</b><br />".
       	     "<textarea cols='60' rows='25' name='body'>";

	if (isset($addnew)) {
	    echo "&lt;p&gt;\n&lt;!-- ".
		 "place message between 'p' elements ".
		 "--&gt;\n\n&lt;/p&gt;";
	} else {
	    echo $body;
	}
	echo "</textarea><br /><br />";
	echo "<b>or Upload Body File:</b><br />";
	echo "<input type='file' name='bodyfile' size='50'>";
	echo "</input>";
	echo "<br /><br />\n";
	if (isset($addnew)) {
	    echo "<button name='add'>Add</button>\n";
	} else {
	    echo "<button name='add'>Edit</button>\n";
	}
	echo "&nbsp;&nbsp;<button name='nothin'>Cancel</button>\n";
	echo "</form>";
	PAGEFOOTER();
	die("");
    } else {
	echo "<form action='news.php' method='post'>\n";
	echo "<button name='addnew'>Add a new message</button>\n";
	echo "</form>";
    }
}
else {
    PAGEHEADER("News",NULL,$RSS_HEADER_NEWS);
}

?>
<table align=center class=stealth border=0>
<tr><td class=stealth align=center><h1>News</h1></td></tr>
<?php
if ($TBMAINSITE && ! (isset($single) && $single)) {
    echo "<tr><td class=stealth align=center>
                  <a href = 'doc/changelog.php'>
                     (Changelog/Technical Details)</a></td></tr>\n";
}
if (!isset($single) || !$single) {
    echo "<tr><td class=stealth align=center>
                  <a href = '$TBDOCBASE/news-rss.php'>
                  <img src='rss.png' width=27 height=14 border=0>
                  RSS Feed
                  </a></td></tr>";
}
echo "</table>
      <br />\n";

# Allow admin caller to flip the archive bit. 
$show_archive_clause = "archived=0";
if ($isadmin) {
    if ($show_archived) {
	$show_archive_clause = "1";
	echo "<a href='news.php?show_archived=0'>Hide Archived Messages</a>\n";
    }
    else {
	echo "<a href='news.php?show_archived=1'>Show Archived Messages</a>\n";
    }
}

# Allow users to view a single message
$which_msgid_clause = "1"; # MySQL will optimize this out
if (isset($single)) {
    $single = addslashes($single);    
    $which_msgid_clause = "msgid='$single'";
    $show_archive_clause = 1;
}

$query_result=
    DBQueryFatal("SELECT subject, author, body, msgid, ".
		 "DATE_FORMAT(date,'%W, %M %e, %Y, %l:%i%p') as prettydate, ".
		 "(TO_DAYS(NOW()) - TO_DAYS(date)) as age, ".
		 "archived, ".
		 "DATE_FORMAT(archived_date,'%W, %M %e, %Y, %l:%i%p') as ".
		 "  archived_date, ".
                 "show_mydeterlab ".
		 "FROM webnews ".
                 "WHERE " .
                 "$show_archive_clause " .
                 "AND " .
		 "$which_msgid_clause ".
		 "ORDER BY date DESC" );

if (!mysql_num_rows($query_result)) {
    echo "<h4>No messages.</h4>";
} else {

    if ($isadmin) {
	echo "<form action='news.php' method='post'>";
	echo "<input type='hidden' name=show_archived value='$show_archived'>";
    }

    while ($row = mysql_fetch_array($query_result)) {
	$subject = $row["subject"];
	$date    = $row["prettydate"];
	$author  = $row["author"];
	$body    = $row["body"];
	$msgid   = $row["msgid"];
	$age     = $row["age"];
	$archived = $row["archived"];
	$archived_date = $row["archived_date"];
        $show_mydeterlab = $row["show_mydeterlab"];

	echo "<a name=\"$msgid\"></a>\n";
	echo "<table class='nogrid' 
                     cellpadding=0 
                     cellspacing=0 
                     border=0 width='100%'>";

	echo "<tr><th style='padding: 4px;'><font size=+1>$subject</font>";

	if ($age < 7) {
	    echo "&nbsp;<img border=0 src='new.gif' />";
	}

	echo "</th></tr>\n".
	     "<tr><td style='padding: 4px; padding-top: 2px;'><font size=-1>";
	echo "<b>$date</b>; posted by <b>$author</b>.";
	if ($archived) {
	    echo "<font color=red> Archived on <b>$archived_date</b></font>.\n";
	}

	if ($isadmin) {
	    echo " (Message <b>#$msgid</b>)";
	}

	echo "</font></td></tr>";

	if ($isadmin) {
	    echo "<tr><td>";
	    echo "<button name='edit' value='$msgid'>".
		 "Edit</button>".
	         "<button name='delete' value='$msgid'>".
		 "Delete</button>";
	    if ($archived)
		echo "<button name='restore' value='$msgid'>".
		     "Restore</button>";
	    else
		echo "<button name='archive' value='$msgid'>".
		     "Archive</button>";
            if ($show_mydeterlab)
                echo "<button name='hidemydeter' value='$msgid'>".
                     "Hide from My DETERlab</button>";
            else
                echo "<button name='showmydeter' value='$msgid'>".
                     "Show on My DETERlab</button>";
	    echo "</td></tr>\n";
	}

	echo "<tr><td style='padding: 4px; padding-top: 2px;'>".
	     "<div style='background-color: #FFFFF2; ".
	     "border: 1px solid #AAAAAA; ".
      	     "padding: 6px'>".
	     $body.
	     "</div>".
	     "</td></tr>\n";

	echo "<!-- ' \" > IF YOU CAN READ THIS, YOU FORGOT AN ENDQUOTE -->\n";

	echo "</table><br />";
    } 

    if ($isadmin) { 
	echo "</form>\n"; 
    }
}		  

PAGEFOOTER();
?>

