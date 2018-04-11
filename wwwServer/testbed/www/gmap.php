<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml">
  <head>
    <meta http-equiv="content-type" content="text/html; charset=UTF-8"/>
    <title>Google Maps JavaScript API Example: 		Extraction of Geocoding Data</title>

<?php
#
# EMULAB-COPYRIGHT
# Copyright (c) 2000-2006 University of Utah and the Flux Group.
# All rights reserved.
#
include("defs.php");

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

$qstr="select city, state, country, lat, lng, count(*) as num from usr_address_san group by city";

$result = DBQueryFatal($qstr);

?>



<script src="http://maps.google.com/maps?file=api&amp;v=2.x&amp;key=ABQIAAAAbYKTOOZ-Z0hwH5AuK9tb5xQMd4dHxc0FCVKr5DwB3LqGEHro-RQ3nsavDsMhoTHnCCpbgZe4zCTn6Q" type="text/javascript"></script>
<script type="text/javascript">

    var map;
    var geocoder;

    function initialize() {
      map = new GMap2(document.getElementById("map_canvas"));
      map.setCenter(new GLatLng(34, 0), 1);
      geocoder = new GClientGeocoder();
<?php
        $i = 0;
	while ( $row = mysql_fetch_row($result)) {
             $i = $i+1;
             if ($i < 200)
             {
	       $t = $i*100;
	       print "setTimeout('showAddress(\'$row[0] $row[1] $row[2]\', $row[5])', $t);\n";
	     }
	}
?> 
    }

    // addAddressToMap() is called when the geocoder returns an
    // answer.  It adds a marker to the map with an open info window
    // showing the nicely formatted version of the address and the country code.
    function addAddressToMap(response) {
      //map.clearOverlays();
      if (!response || response.Status.code != 200) {
        //alert("Sorry");
        //alert(response.Placemark[0].address);
      } else {
        place = response.Placemark[0];
        point = new GLatLng(place.Point.coordinates[1],
                            place.Point.coordinates[0]);
        marker = new GMarker(point);
        map.addOverlay(marker);
        marker.openInfoWindowHtml(place.address + '<br>' +
          '<b>Country code:</b> ' + place.AddressDetails.Country.CountryNameCode);
      }
    }

    function showAddress(address, num_users) {
      document.forms[0].q.value = address;
      geocoder.getLocations(address, addAddressToMap);
    }

    // showLocation() is called when you click on the Search button
    // in the form.  It geocodes the address entered into the form
    // and adds a marker to the map at that location.
    function showLocation() {
      var address = document.forms[0].q.value;
      geocoder.getLocations(address, addAddressToMap);
    }

   // findLocation() is used to enter the sample addresses into the form.
    function findLocation(address) {
      document.forms[0].q.value = address;
      showLocation();
    }

    </script>
  </head>

  <body onload="initialize()">

    <!-- Creates a simple input box where you can enter an address
         and a Search button that submits the form. //-->
    <form action="#" onsubmit="showLocation(); return false;">
      <p>
        <b>Search for an address:</b>
        <input type="text" name="q" value="" class="address_input" size="40" />
        <input type="submit" name="find" value="Search" />

      </p>
    </form>
    <div id="map_canvas" style="width: 500px; height: 300px"></div>

   <!-- Sample addresses //-->
   <p><b>Try these:</b><br />

   <a href="javascript:void(0)"
     onclick="findLocation('1600 amphitheatre mountain view ca');return false;">1600
     amphitheatre mountain view ca</a><br />

   <a href="javascript:void(0)"
     onclick="findLocation('1 Telegraph Hill Blvd, San Francisco, CA, USA');return false;">1
     Telegraph Hill Blvd, San Francisco, CA, <b>USA</b></a><br />

   <a href="javascript:void(0)"
     onclick="findLocation('4141 Avenue Pierre-De-Coubertin, Montr&#x00E9;al, QC, Canada');return false;">4144
     Avenue Pierre-De-Coubertin, Montr&#x00E9;al, <b>Canada</b></a><br />

   <a href="javascript:void(0)"
     onclick="findLocation('Champ de Mars 75007 Paris, France');return false;">Champ
     de Mars 75007 Paris, <b>France</b></a><br />

   <a href="javascript:void(0)"
     onclick="findLocation('Piazza del Colosseo, Roma, Italia');return false;">Piazza
     del Colosseo, Roma, <b>Italia</b></a><br />

   <a href="javascript:void(0)"
     onclick="findLocation('Domkloster 3,  50667 K&#x00F6;ln, Deutschland');return false;">Domkloster
     3,  50667 K&#x00F6;ln, <b>Deutschland</b></a><br />

   <a href="javascript:void(0)"
     onclick="findLocation('Plaza de la Virgen de los Reyes, 41920, Sevilla, Espa&#x00F1;a');return false;">Plaza
     de la Virgen de los Reyes, 41920, Sevilla, <b>Espa&#x00F1;a</b></a><br />

   <a href="javascript:void(0)"
     onclick="findLocation('123 Main St, Googleville');return false;">
     123 Main St, <b>Googleville</b></a>

  </p>
  </body>
</html>
