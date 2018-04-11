<?php
#
# EMULAB-COPYRIGHT
# Copyright (c) 2000-2006 University of Utah and the Flux Group.
# All rights reserved.
#
include("defs.php");

#
# Standard Testbed Header
#
#PAGEHEADER("DETER User Location Map");

#
# Only known and logged in users can end experiments.
#
#$uid = GETLOGIN();
#LOGGEDINORDIE($uid);
#$isadmin = ISADMIN($uid);

# Show a very simple table that results from a query of fields with numeric
# values.
function TVF_SHOW_TABLE($qstr) {

        $result = DBQueryFatal($qstr);
        print "<table class=\"centered\">\n";
        print "<tr>";
        for ($i = 0; $i< mysql_num_fields($result); $i++) {
            print "<th>" . mysql_field_name($result, $i) . "</th>";
        }
        print "<tr>\n";
        while ( $row = mysql_fetch_row($result)) {
            print ("<tr><td class=\"num\">" . 
                    implode("</td><td class=\"num\">", $row) .
                    "</td></tr>\n");
        }
        print "</table>\n";
}

$qstr="select uid, city, state, country, lat, lng, count(*) as num from usr_address_san where lat is not NULL group by city";

$result = DBQueryFatal($qstr);

?>
<html>
  <head>
 <title>isi.deterlab.net - DETER User Location Map</title>
            <link rel="shortcut icon" href="http://www.isi.deterlab.net/favicon.ico" TYPE="image/vnd.microsoft.icon">
    	        <!-- dumbed-down style sheet for any browser that groks (eg NS47). -->
		    <link rel='stylesheet' href='http://www.isi.deterlab.net/common-style.css' type='text/css' />
    		        <!-- do not import full style sheet into NS47, since it does bad job
            of handling it. NS47 does not understand '@import'. -->

    	        <style type='text/css' media='all'>
            <!-- @import url(http://www.isi.deterlab.net/style.css); -->
            <!-- @import url(http://www.isi.deterlab.net/cssmenu-new.css); --></style>
<!-- [if gt IE 6.0]><style type="text/css">.menu ul li a:hover ul { top: 18px; }</style><![endif]> -->
</head><body>

<div id='bannercell'>
<iframe src='http://www.isi.deterlab.net/currentusage.php' class='usageframe'
                          scrolling='no' frameborder='0'></iframe>
<map name='overlaymap'>
                         <area shape="rect" coords="100,60,339,100"
                               href='http://www.emulab.net/index.php'>

                         <area shape="rect" coords="0,0,339,100"
                               href='http://www.isi.deterlab.net/index.php'>
                      </map>
<img height='100' border='0' usemap="#overlaymap" width='339' src='http://www.isi.deterlab.net/overlay.isi.deterlab.net.gif' alt='isi.deterlab.net - the network testbed'>
</div>


<div class='leftcell'>
<!-- sidebar begins -->
<h3 class='menuheader' id='information_header'> Information</h3><ul class='navmenu' id='information_list'>
<li ><a href="http://www.isi.deterlab.net/index.php?stayhome=1">Home</a></li>
<li ><a href="http://www.emulab.net/">Utah Emulab</a></li>
<li ><a href="http://www.isi.deterlab.net/news.php">News (November&nbsp;12)</a></li>

<li ><a href="https://users.emulab.net/wikidocs/wiki">Documentation</a></li>
<li ><a href="http://www.isi.edu/deter">DETER Project home</a></li>
<li ><a href="http://www.isi.edu/deter/publications.php">DETER Publications</a></li>
<li ><a href="http://www.isi.edu/deter/tools.html">DETER Tools</a></li>
<li ><a href="http://seer.isi.deterlab.net">SEER Tool home</a></li>
<li ><a href="http://fedd.isi.deterlab.net/trac">DETER Federation</a></li>
<li ><a href="http://www.isi.deterlab.net/projectlist.php">Projects on DETERlab</a></li>
</ul>

<div id='searchrow'>
        <form method='get' action='http://www.isi.deterlab.net/search.php'>
        <table border='0' cellspacing='0' cellpadding='0'><tr>
             <td width='100%'><input class='textInputEmpty' name='query'
                        value='Search Documentation' id='searchbox'
                        onfocus='focus_text(this, "Search Documentation")'
                        onblur='blur_text(this, "Search Documentation")' />
               </td>
	            <td><input type='submit' id='searchsub' value=Go /></td>
        </table>
        </form>
	</div>

<div id='loginbox'><a href="https://www.isi.deterlab.net/reqaccount.php"><img alt="Request Account" border=0 src="http://www.isi.deterlab.net/requestaccount.gif" width="144" height="32"></a><strong>or</strong><a href="https://www.isi.deterlab.net/login.php"><img alt="logon" border=0 src="http://www.isi.deterlab.net/logon.gif" width="144" height="32"></a>
</div>       <a class='builtwith' href='http://www.emulab.net'>
                             <img src='http://www.isi.deterlab.net/builtwith.png'></a><!-- sidebar ends -->
              </div>
<div class='rightcontent'>
<!-- content body -->
<div class='contentbody'><div id='rightcontentheader'><div id='logintime'><span class='loggedin'></span><span class='timestamp'>Tue Feb 03 1:54am PST</span>
</div><div id='versioninfo'>Vers: 4.160 Build: 01/21/2009</div><h2 class='rightcontenttitle'>
DETER User Location Map</h2></div>
<!-- begin content -->


    <script src="http://maps.google.com/maps?file=api&amp;v=2&amp;key=ABQIAAAAU578PRir_R9XHtQLwLNwfhTv3S_ff0qtKu8iQDPPHPidCQunWRQaM5VVm6eeq_J3yBfitzXir4odcQ"
      type="text/javascript"></script>
    <script type="text/javascript">
    function load() {
      if (GBrowserIsCompatible()) {
        var map = new GMap2(document.getElementById("map"));
        var geocoder = new GClientGeocoder();
        map.setCenter(new GLatLng(40, 0), 2);
        map.addControl(new GSmallMapControl());
<?php
	$sum = 0;
	$loc = 0;
	while ( $row = mysql_fetch_row($result)) {
	$loc = $loc + 1;
	$sum = $sum + $row[6];
	if ($row[2] != "")
      	{
         $key = "$row[1], $row[2], $row[3]";
       }
       else
       {
         $key = "$row[1], $row[3]";
       }
      if (isset($map[$row[3]]))
      {
	$map[$row[3]] = $map[$row[3]]+1;
      }     
      else
      {
        $map[$row[3]]=1;
      }

	print "showAddress(\"$key\", \"$row[4]\", \"$row[5]\",\"$row[6]\");\n";
	}
	$countries = count($map);
?>
      }

  function showAddress(address, lat, lng, num_users) {
	if (num_users > 100) {
		num_users = 100;
	}
  	var Icon = new GIcon(G_DEFAULT_ICON);
	Icon.iconSize = new GSize(20+num_users/5, 20+num_users/5);
        Icon.shadowSize = new GSize(5, 5);
	Icon.iconAnchor = new GPoint(10, 10);
	Icon.image = "man.png";
        markerOptions = { icon:Icon };
        var marker = new GMarker(new GLatLng(lat, lng), markerOptions);
	GEvent.addListener(marker, "click", function() {    
	marker.openInfoWindowHtml("<b>Area Code:</b>" + address + "<br><b>Users:</b>" + num_users);  
	});
	map.addOverlay(marker);
	
    }
}
    </script>

  <body onload="load()" onunload="GUnload()">
    <div id="map" style="width: 800px; height: 500px"></div>
<?php
	print "$sum users at $loc locations, $countries countries<br>";
?>
  </body>

<?php
#
# Standard Testbed Footer
# 
PAGEFOOTER();
?>

