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
PAGEHEADER("DETER User Location Map (US and Canada)");

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

$qstr="select usr_zip, count(*) as num from users where usr_country='USA' or usr_country='Canada' group by usr_zip";

$result = DBQueryFatal($qstr);

?>



    <script src="http://maps.google.com/maps?file=api&amp;v=2&amp;key=ABQIAAAAU578PRir_R9XHtQLwLNwfhTaAYFDzAZcdO30PbEqAEvg8yrxhBTyjaCKwkpog551G51T87NqMnIKGw"
      type="text/javascript"></script>
    <script type="text/javascript">
    //<![CDATA[
    function load() {
      if (GBrowserIsCompatible()) {
        var map = new GMap2(document.getElementById("map"));
        var geocoder = new GClientGeocoder();
        map.setCenter(new GLatLng(41, -94), 4);
        map.addControl(new GSmallMapControl());
<?php
	while ( $row = mysql_fetch_row($result)) {
	        print "showAddress(\"$row[0]\", \"$row[1]\");\n";
	}
?>
      }

                function showAddress(address, num_users) {
                geocoder.getLatLng(    address,
                        function(point) {
                                if (!point) {
                                        // alert(address + " not found");
                                } else {
                                        var marker = new GMarker(point);
                                        GEvent.addListener(marker, "click", function() {    marker.openInfoWindowHtml
("<b>Area Code:</b>" + address + "<br><b>Users:</b>" + num_users);  });
                                        map.addOverlay(marker);

                                }
                        }
                );
        }

    }
    //]]>
    </script>

  <body onload="load()" onunload="GUnload()">
    <div id="map" style="width: 800px; height: 500px"></div>
  </body>

<?php
#
# Standard Testbed Footer
# 
PAGEFOOTER();
?>
