<head>
<script type='text/javascript' src='https://www.google.com/jsapi'></script>
    <script type='text/javascript'>
      google.load('visualization', '1', {'packages':['corechart']});
      google.setOnLoadCallback(drawCharts);
      function drawCharts()
      {
	drawChart1();
	drawChart2();
      }

      function drawChart1() {
        var data = new google.visualization.DataTable();
        data.addColumn('string', 'Quarter');
        data.addColumn('number', 'Active');
        data.addColumn('number', 'New Active');
        data.addColumn('number', 'Active - realistic projection');
        data.addColumn('number', 'New Active - realistic projection');
        data.addColumn('number', 'Active - conservative projection');
        data.addColumn('number', 'New Active - conservative projection');
<?php
	exec("/users/sunshine/deter/QR.pl", $output=array());
	$result1 = array();
	foreach($output as $o)
	{
	   $vals = preg_split("/ /", $o);	
	   $i=0; 
	   if (strpos($vals[0], "Q"))
	   {
		$st = "";
		foreach ($vals as $v)
	   	{
			if ($i == 0)
			{
			   print "data.addRow(['$v'";
			   $st = $st . "$v";
			   if (strpos($vals[0], "P"))
			    {
				 print ", undefined, undefined";
				 $st = $st . ",,";
			    }				 
			     
			}
			else
			{
			   if ((strpos($vals[0],"Y") && (strpos($vals[0],"P") || ($i == 3) || ($i == 5))) ||
			   (strpos($vals[0],"Q") && (($i == 3) || ($i == 5) || ($i == 8) || ($i == 10))))
			   {
				$items = preg_split("/=/", $v);
		           	print ", $items[1]";
	 			$st = $st . ", $items[1]";	
		           }
		        }
		       $i++;
                }
		if (!strpos($vals[0], "P"))	
		{
		        $st = $st . ",,,,";
			print ", undefined, undefined, undefined, undefined";
		}
	       print "]);\n";
	       array_push($result1, $st);
	   }
       }

?>

  var chart = new google.visualization.LineChart(document.getElementById('chart_div1'));
        chart.draw(data,  {curveType: "function",
                  width: 1000, height: 600,
                  vAxis: {maxValue: 10}});      
}


      function drawChart2() {
        var data = new google.visualization.DataTable();
        data.addColumn('string', 'Quarter');
        data.addColumn('number', 'Active');
        data.addColumn('number', 'New Active');
        data.addColumn('number', 'Active - realistic projection');
        data.addColumn('number', 'New Active - realistic projection');
        data.addColumn('number', 'Active - conservative projection');
        data.addColumn('number', 'New Active - conservative projection');
<?php
	$result2 = array();
	foreach($output as $o)
	{
	   $vals = preg_split("/ /", $o);	
	   $i=0; 
	   if (strpos($vals[0], "Y"))
	   {
		$st = "";
		foreach ($vals as $v)
	   	{
			if ($i == 0)
			{
			   print "data.addRow(['$v'";
			   $st = $st . "$v";
			   if (strpos($vals[0], "P"))
			    {
				 print ", undefined, undefined";
				 $st = $st . ",,";
			    }				 
			     
			}
			else
			{
			   if ((strpos($vals[0],"Y") && (strpos($vals[0],"P") || ($i == 3) || ($i == 5))) ||
			   (strpos($vals[0],"Q") && (($i == 3) || ($i == 5) || ($i == 8) || ($i == 10))))
			   {
				$items = preg_split("/=/", $v);
		           	print ", $items[1]";
	 			$st = $st . ", $items[1]";	
		           }
		        }
		       $i++;
                }
		if (!strpos($vals[0], "P"))	
		{
		        $st = $st . ",,,,";
			print ", undefined, undefined, undefined, undefined";
		}
	       print "]);\n";
	       array_push($result2, $st);
	   }
       }

?>

  var chart = new google.visualization.LineChart(document.getElementById('chart_div2'));
        chart.draw(data,  {curveType: "function",
                  width: 1000, height: 600,
                  vAxis: {maxValue: 10}});      
}

      </script>
</head>
<body>

    <div id='chart_div1' style='width:1000px; height: 400px;'></div>


<?php
  print "<TABLE BORDER=1><TR><TD>Time</TD><TD COLSPAN=2>Actual</TD><TD COLSPAN=2>Realistic projection</TD><TD COLSPAN=2>Conservative projection</TD></TR>\n";
  print "<TR><TD></TD><TD>Active</TD><TD>New and active</TD><TD>Active</TD><TD>New and active</TD><TD>Active</TD><TD>New and active</TD></TR><TR>\n";
  foreach ($result1 as $r)
  {
	print "<TR>\n";
        $vals = preg_split("/,/", $r);
	foreach ($vals as $v)
	{
		print "<TD>$v</TD>";
        }
	print "</TR><BR>";
  } 
  print "</TABLE>\n";
?>

  <div id='chart_div2' style='width:1000px; height: 400px;'></div>


<?php
  print "<TABLE BORDER=1><TR><TD>Time</TD><TD COLSPAN=2>Actual</TD><TD COLSPAN=2>Realistic projection</TD><TD COLSPAN=2>Conservative projection</TD></TR>\n";
  print "<TR><TD></TD><TD>Active</TD><TD>New and active</TD><TD>Active</TD><TD>New and active</TD><TD>Active</TD><TD>New and active</TD></TR><TR>\n";
  foreach ($result2 as $r)
  {
	print "<TR>\n";
        $vals = preg_split("/,/", $r);
	foreach ($vals as $v)
	{
		print "<TD>$v</TD>";
        }
	print "</TR><BR>";
  } 
  print "</TABLE>\n";
?>

