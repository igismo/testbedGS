<?php
#
# EMULAB-COPYRIGHT
# Copyright (c) 2000-2007 University of Utah and the Flux Group.
# All rights reserved.
#

function CheckProto($proto)
{
	return ($proto == "tcp" || $proto == "udp");
}

function CheckPort($port)
{
   return (preg_match("/^[0-9]+$/", $port) && $port < 65536);
}

function CheckIP($address)
{
  return (ip2long($address) !== FALSE);
}

function CheckOptions($toprint, $formfields, $nsfilecont)
{
  $malware  = 0; 
  $selfprop = 0;
  $conn = 0;
  $error = false;
  $errormsg = "";
  $nsfile = "";
  if (isset($formfields)) {
   if (isset($formfields['exp_malware']))
       $malware = $formfields['exp_malware'];
   if (isset($formfields['exp_selfprop']))	
       $selfprop = $formfields['exp_selfprop'];
   if (isset($formfields['exp_conn']))	
       $conn = $formfields['exp_conn'];
   $source = $formfields['malware_type'];
   $expips = $formfields['expips'];          
   $remips = $formfields['remips'];
   $comm = $formfields['comm_type'];
   if (!$malware)
     { 
      if ($source != "None")
        {
		$errormsg = $errormsg."<p>You must check <b>live malware</b> box if you choose $source for code source.";
		$error = true;
    	}
    	if ($selfprop)
    	{
		$errormsg = $errormsg."<p>You must check <b>live malware</b> box if you choose self-propagating malware.";
		$error = true;
    	}
    }
   else if($source == "None")
    {
	$errormsg = $errormsg ."<p>If you check <b>live malware</b> box you cannot choose $source for code source.";
	$error = true;
    }
   if (!$conn)
    {
	if ($comm != "None")
	{
    	   $errormsg = $errormsg."<p>You must check <b>outside connectivity</b> box if you choose $comm for communication type.";
	   $error = true;
	}
	if ($expips != "")
	{
    	   $errormsg = $errormsg."<p>You must check <b>outside connectivity</b> box if you specify experiment nodes that require connectivity with the outside.";
	   $error = true;
	}
	if ($remips != "")
	{
    	   $errormsg = $errormsg."<p>You must check <b>outside connectivity</b> box if you specify remote nodes that require connectivity with your experiment.";
	   $error = true;
	}
    }
   else
    {
	if ($comm == "None")
        {
           $errormsg = $errormsg."<p>If you checked <b>outside connectivity</b> box you cannot choose $comm for communication type.";
           $error = true;
        }
	if ($expips=="" && $remips == "")
        {
           $errormsg = $errormsg."<p>If you checked <b>outside connectivity</b> box you must specify at least one destination (either experimental node or remote IP in textboxes located in <b>Connectivity needs</b> area).";
           $error = true;
        }
	if ($expips != "")
	{
	   foreach(preg_split("/\n/",$expips) as $item)	
	   {
		$elem=explode("/",$item);
		if (count($elem)==3)
		{
		   if (!CheckProto(trim($elem[2])))
		   {
			if (trim($elem[2]) !="")
		           $errormsg = $errormsg."<p>$elem[2] is not a valid protocol on experimental node $elem[0] in line $item.";
			else
			   $errormsg = $errormsg."<p>You failed to specify a protocol on experimental node $elem[0] in line $item.";
           	 	$error = true;
		   }
		   if (!CheckPort(trim($elem[1])))
		   {
			if (trim($elem[1]) !="")
		           $errormsg = $errormsg."<p>$elem[1] is not a valid port number on experimental node $elem[0] in line $item.";
			else
			   $errormsg = $errormsg."<p>You failed to specify a port number on experimental node $elem[0] in line $item.";
           	 	$error = true;
	           }
		}
		else if (count($elem) > 1)
		{
		  $errormsg = $errormsg."<p>You failed to specify node name, port number or protocol in line $item. All three parts must be specified.";
		  $error = true;
		  }
	   }
	}	
	if ($remips != "")
	{
	   foreach(preg_split("/\n/",$remips) as $item)	
	   {
		$elem=explode("/",trim($item));
		if (count($elem) > 1 && (!CheckIP(trim($elem[0]))))
		{
		        if (trim($elem[0]) != "")
				$errormsg = $errormsg."<p>$elem[0] is not a valid IP address in line $item.";
		        else
				$errormsg = $errormsg."<p>You failed to specify an IP address in line $item.";
           	 	$error = true;
		}
		if (count($elem)==3)
		{
		  if (!CheckProto(trim($elem[2])))
		   {
			if ($elem[2]!="")
		           $errormsg = $errormsg."<p>$elem[2] is not a valid protocol on remote node $elem[0] in line $item.";
			else
			   $errormsg = $errormsg."<p>You failed to specify a protocol on remote node $elem[0] in line $item.";
           	 	$error = true;
		   }
		   if (!CheckPort(trim($elem[1])))
		   {
			if ($elem[1]!="")
		           $errormsg = $errormsg."<p>$elem[1] is not a valid port number on remote IP $elem[0] in line $item.";
			else
			   $errormsg = $errormsg."<p>You failed to specify a port number on remote IP $elem[0] in line $item.";
           	 	$error = true;
	           }
		}
		else if (count($elem) > 1)
		{
		  $errormsg = $errormsg."<p>You failed to specify node name, port number or protocol in line $item. All three parts must be specified.";
		  $error = true;
		}
             }
	}		
    }
    # Now check if tunnel nodes exist in the file
    # and has the proper OS, hw and is linked to the topology
    if ($nsfilecont != "")
    {
	# Array of node OSes and hardware
	$OS = array();
	$hw = array();
	$link = false;
	$tunnel = false;
	# If there is a tunnel node check that it has a proper name and OS
	foreach(preg_split("/\n/",$nsfilecont) as $item)	
	{
	   if (preg_match("/tb-set-node-os.*/",$item))
          {
		$elem = explode(" ",trim($item));
		$os[substr($elem[1],1)] = $elem[2];
	  }
	  if (preg_match("/tb-set-hardware/",$item))
	  {
		$elem = explode(" ",trim($item));
		if ((substr($elem[1],1) != "tunnel") && ($elem[2] == "pc3000_tunnel"))
		{
			$error = true;
			$errormsg = $errormsg . "<p>You can only assign the hardware type pc3000_tunnel to a node called <B>tunnel</B>.";
	        }
		$hw[substr($elem[1],1)] = $elem[2];
	  }
	  elseif (preg_match("/set.*duplex-link.*tunnel/",$item))
	  {
		 $link = true;
	  }
        }
	# Check that the link with the tunnel wasn't deleted accidentally by user
	if ($tunnel && !$link)
	{
	      $error = true;
	      $errormsg = $errormsg . "<p>It looks like you have deleted a link with the tunnel node. <br> 
	      Without it you cannot communicate to the outside. To amend this run Modify Experiment, <br>
	      delete all references to node <b>tunnel</b> from NS file and let us insert the code automatically.<br>";
    	}
    }
    if ($error)
    {
	if ($toprint)
	  USERERROR($errormsg);
    }
    else
    {
	if ($toprint)
    	{
		echo "<center>";
		echo "<br>";
		echo "<h2>Options you selected look good!</h2>";
		echo "</center>\n";
	}
    }
  }
  return $errormsg;
}


?>
