<?php
## GORAN TEST
#/********************************************************************************
 #
 #    <"testbedGS" - Runtime structures and modular distributed component
 #      architecture providing infrastructure and platform to build testbeds>
 #
 #    Copyright (C) <2018>  <Goran Scuric, goran@usa.net, igismo.com>
 #
 #    GNU GENERAL PUBLIC LICENSE ... Version 3, 29 June 2007
 #
 #    This program is free software: you can redistribute it and/or modify
 #    it under the terms of the GNU General Public License as published by
 #    the Free Software Foundation, either version 3 of the License, or
 #    (at your option) any later version.
 #
 #    This program is distributed in the hope that it will be useful,
 #    but WITHOUT ANY WARRANTY; without even the implied warranty of
 #    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 #    GNU General Public License for more details.
 #
 #    You should have received a copy of the GNU General Public License
 #    along with this program.  If not, see <http://www.gnu.org/licenses/>.
 #*********************************************************************************/
function createbatchexp($exp_pid,$exp_gid, $exp_id,$linktestarg, $extragroups, $exp_swappable,
                        $exp_desc, $batcharg, $uid, $thensfile)
{
    $handle = @fopen("/var/www/expmasterip","r");
    if ($handle) {
        $expmasterip = fread($handle, filesize("/var/www/expmasterip"));
        fclose($handle);#
        echo "<h3> Send to $expmasterip </h3>";
        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

        $msgtype = "EXPCREATE";
        $bodystring =      "{\"Project\":\"" .
            $exp_pid    . "\",\"Experiment\":\"" .
            $exp_id     . "\",\"UserName\":\""   .
            $uid        . "\",\"FileName\":\""   .
            $thensfile  . "\"}";
       $msgbody = $bodystring ;
       $receiver = "\"MsgReceiver\":{\"Name\":\"TB-EXPMASTER\",\"OsId\":0,\"TimeCreated\":\"1522700051352295\",\"Address\":{\"IP\":\"172.18.0.5\",\"Port\":1200,\"Zone\":\"\"}}";
       $sender   = "\"MsgSender\":{\"Name\":\"TB-WEBMASTER\",\"OsId\":0,\"TimeCreated\":\"1522711672844736\",\"Address\":{\"IP\":\"172.18.0.4\",\"Port\":1200,\"Zone\":\"\"}}";
       $msgtype  = "\"MsgType\":\"EXPCREATE\"";
       $timesent = "\"TimeSent\":\"1522779588000965\"";

       $msg = "{" .
                $receiver        . "," .
                $sender          . "," .
                $msgtype         . "," .
                $timesent        . "," .
                "\"MsgBody\":" .
                $msgbody         . ## "\"" .
                "}";

       $len = strlen($msg);

       socket_sendto($socket, $msg, $len, 0, $expmasterip, 1200);
       socket_close($socket);
    }
}
####################################
function byteStr2byteArray($s) {
    return array_slice(unpack("C*", "\0".$s), 1);
}

#	Project         string // $exp_pid
#	Experiment      string // $exp_id
#	GroupId         string // $exp_gid
#	LinktestArgs    string // $linktestarg
#	ExtraGroups     string // $extragroups
#	ExpSwappable    string // $exp_swappable
#	ExpDesc         string // $exp_desc
#	BatchArgs       string // $batcharg
#	UserName        string // $uid
#	FileName        string // $thensfile
    #echo "<h3> Start of GoranTest </h3>";
    #$project = "DeterTest";
    #$experiment = "test1";
    #$thisuser = "scuric";
    #$thisfile = "test1.xml";
    #$msgbody = unpack('C*', $bodystring);
    #$msgbody = byteStr2byteArray($bodystring);
    ###var_dump($msgbody);
    #$msgbody = pack("nvc*",$bodystring);