/********************************************************************************

    <"testbedGS" - Runtime structures and modular distributed component
      architecture providing infrastructure and platform to build testbeds>

    Copyright (C) <2018>  <Goran Scuric, goran@usa.net, igismo.com>

    GNU GENERAL PUBLIC LICENSE ... Version 3, 29 June 2007

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*********************************************************************************/
package main

// An example streaming XML parser.

import (
	"fmt"
	"os"
	"flag"
	"encoding/xml"
	"strconv"
	"golang.org/x/net/html/charset"
	"encoding/json"
	"errors"
)

var Virt_nodes struct {
	Row struct {
		Vname         string `xml:"vname" json:"vname"`
		Type          string `xml:"type" json:"type"`
		Ips           string `xml:"ips" json:"ips"`
		Osname        string `xml:"osname" json:"osname"`
		Cmd_line      string `xml:"cmd_line" json:"cmd_line"`
		Rpms          string `xml:"rpms" json:"rpms"`
		Startupcmd    string `xml:"startupcmd" json:"startupcmd"`
		Tarfiles      string `xml:"tarfiles" json:"tarfiles"`
		Failureaction string `xml:"failureaction" json:"failureaction"`
		Routertype    string `xml:"routertype" json:"routertype"`
		Fixed         string `xml:"fixed" json:"fixed"`
		Plab_plcnet   string `xml:"plab_plcnet" json:"plab_plcnet"`
	} `xml:"row" json:"row"`
} //`xml:"virt_nodes" json:"virt_nodes"`

var Virt_agents struct {
	Row struct {
		Vnode      string `xml:"vnode" json:"vnode"`
		Vname      string `xml:"vname" json:"vname"`
		Objecttype string `xml:"objecttype" json:"objecttype"`
	} `xml:"row" json:"row"`
} // `xml:"virt_agents" json:"virt_agents"`

var Virt_lan_lans struct {
	Row struct {
		Vname string `xml:"vname" json:"vname" ` //<vname>phys0008</vname>
	} `xml:"row" json:"row"`
} //`xml:"virt_lan_lans" json:"virt_lan_lans"`

var Virt_lans struct {
	Row struct {
		Vname          string  `xml:"vname" 	json:"vname"`          //<vname>phys0008</vname>
		Member         string  `xml:"member" 	json:"member"`         //<member>vrouter:2</member>
		Mask           string  `xml:"mask" 	json:"mask"`           		//<mask>255.255.255.0</mask>
		Delay          float32 `xml:"delay" 	json:"delay"`          //<delay>0.0</delay>
		Rdelay         float32 `xml:"rdelay" 	json:"rdelay"`         //<rdelay>0.0</rdelay>
		Bandwidth      int     `xml:"bandwidth" 	json:"bandwidth"`  //<bandwidth>1000000</bandwidth>
		Rbandwidth     int     `xml:"rbandwidth" 	json:"rbandwidth"` //<rbandwidth>1000000</rbandwidth>
		Backfill       int     `xml:"backfill" 	json:"backfill"`       //<backfill>0</backfill>
		Rbackfill      int     `xml:"rbackfill" 	json:"rbackfill"`  //<rbackfill>0</rbackfill>
		Lossrate       int     `xml:"lossrate" 	json:"lossrate"`       //<lossrate>0</lossrate>
		Rlossrate      int     `xml:"rlossrate" 	json:"rlossrate"`  //<rlossrate>0</rlossrate>
		Cost           int     `xml:"cost" 	json:"cost"`           		//<cost>1</cost>
		Widearea       int     `xml:"widearea" 	json:"widearea"`       //<widearea>0</widearea>
		Emulated       int     `xml:"emulated" 	json:"emulated"`       //<emulated>0</emulated>
		Uselinkdelay   int     `xml:"uselinkdelay" 	json:"uselinkdelay"`   //<uselinkdelay>0</uselinkdelay>
		Nobwshaping    int     `xml:"nobwshaping" 	json:"nobwshaping"`    //<nobwshaping>0</nobwshaping>
		Encap_style    string  `xml:"encap_style" 	json:"encap_style"`    //<encap_style>default</encap_style>
		Q_limit        int     `xml:"q_limit" 	json:"q_limit"`        //<q_limit>100</q_limit>
		Q_maxthresh    int     `xml:"q_maxthresh" 	json:"q_maxthresh"`    //<q_maxthresh>15</q_maxthresh>
		Q_minthresh    int     `xml:"q_minthresh" 	json:"q_minthresh"`    //<q_minthresh>5</q_minthresh>
		Q_weight       float32 `xml:"q_weight" 	json:"q_weight"`       //<q_weight>0.002</q_weight>
		Q_qinbytes     int     `xml:"q_qinbytes" 	json:"q_bytes"`     //<q_qinbytes>0</q_qinbytes>
		Q_bytes        int     `xml:"q_bytes" 	json:"q_bytes"`        //<q_bytes>0</q_bytes>
		Q_meanpsize    int     `xml:"q_meanpsize" 	json:"q_meanpsize"`    //<q_meanpsize>500</q_meanpsize>
		Q_wait         int     `xml:"q_wait" 	json:"q_wait"`         //<q_wait>1</q_wait>
		Q_setbit       int     `xml:"q_setbit" 	json:"q_setbit"`       //<q_setbit>0</q_setbit>
		Q_droptail     int     `xml:"q_droptail" 	json:"q_droptail"`     //<q_droptail>1</q_droptail>
		Q_red          int     `xml:"q_red" 	json:"q_red"`          //<q_red>0</q_red>
		Q_gentle       int     `xml:"q_gentle" 	json:"q_gentle"`       //<q_gentle>0</q_gentle>
		Trivial_ok     int     `xml:"trivial_ok" 	json:"trivial_ok"`     //<trivial_ok>1</trivial_ok>
		Protocol       string  `xml:"protocol" 	json:"protocol"`       //<protocol>ethernet</protocol>
		Is_accesspoint int     `xml:"is_accesspoint" 	json:"is_accesspoint"` //<is_accesspoint>0</is_accesspoint>
		Vnode          string  `xml:"vnode" 	json:"vnode"`          //<vnode>vrouter</vnode>
		Vport          int     `xml:"vport" 	json:"vport"`          //<vport>2</vport>
		Ip             string  `xml:"ip" 	json:"ip"`             //<ip>10.1.1.2</ip>
		Mustdelay      int     `xml:"mustdelay" 	json:"mustdelay"`      //<mustdelay>0</mustdelay>
	} `xml:"row" 	json:"row"`
} // `xml:"virt_lans" 	json:"virt_lans"`

var Virt_vtypes struct {
	Row struct {
		Name string `xml:"name" json:"name"` //<vname>phys0008</vname>
		Weight float32 `xml:"weight" json:"weight"`
		Members string `xml:"members" json:"members"`
	} `xml:"row" json:""`
} //`xml:"virt_vtypes" json:""`

var Virt_programs struct {
	Row struct {
		Vnode string `xml:"vnode" json:"vnode"`							//<vnode>vrouter</vnode>
		Vname string `xml:"vname" json:"vname"`							//<vname>vrouter_startcmd</vname>
		Command string `xml:"command" json:"command"`						//<command>()</command>
		Dir string `xml:"dir" json:"dir"`								//<dir></dir>
		Timeout int `xml:"timeout" json:"timeout"` 						//<timeout></timeout>
		Expected_exit_code int `xml:"expected_exit_code" json:"expected_exit_code"`
	} `xml:"row" json:"row"`
} // `xml:"virt_programs" json:"virt_programs"`

var Event_groups struct {
	Row struct {
		Group_name string `xml:"group_name" json:"group_name"`	//<group_name>__all_programs</group_name>
		Agent_name string `xml:"agent_name" json:"agent_name"`	//<agent_name>vrouter_startcmd</agent_name>
	} `xml:"row" json:"row"`
} // `xml:"event_groups" json:"event_groups"`

var Experiments struct {
	Row struct {
		Mem_usage int `xml:"mem_usage" json:"mem_usage"`
		Cpu_usage int `xml:"cpu_usage" json:"cpu_usage"`
		Forcelinkdelays int `xml:"forcelinkdelays" json:"forcelinkdelays"`
		Uselinkdelays int `xml:"uselinkdelays" json:"uselinkdelays"`
		Usewatunnels int `xml:"usewatunnels" json:"usewatunnels"`
		Uselatestwadata int `xml:"uselatestwadata" json:"uselatestwadata"`
		Wa_delay_solverweight int `xml:"wa_delay_solverweight" json:"wa_delay_solverweight"`
		Wa_bw_solverweight int `xml:"wa_bw_solverweight" json:"wa_bw_solverweight"`
		Wa_plr_solverweight int `xml:"wa_plr_solverweight" json:""`
		Encap_style string `xml:"encap_style" json:"wa_plr_solverweight"`
		Allowfixnode int `xml:"allowfixnode" json:"allowfixnode"`
		Sync_server string `xml:"sync_server" json:"sync_server"`
		Use_ipassign int `xml:"use_ipassign" json:"use_ipassign"`
	} `xml:"row" json:"row"`
} //`xml:"experiments" json:"experiments"`

var Eventlist struct {
	Row struct {
		Time int `xml:"time" json:"time"`	//<time>0</time>
		Vnode string `xml:"vnode" json:"vnode"`	//<vnode>control</vnode>
		Vname string `xml:"vname" json:"vname"`	//<vname>control_startcmd</vname>
		Objecttype int `xml:"objecttype" json:"objecttype"`	//<objecttype>4</objecttype>
		Eventtype int `xml:"eventtype" json:"eventtype"`	//<eventtype>1</eventtype>
		Arguments string `xml:"arguments" json:"arguments"`	//<arguments>COMMAND=(?)</arguments>
		Atstring string `xml:"atstring" json:"atstring"`	//<atstring></atstring>
	} `xml:"row" json:""`
} // `xml:"eventlist" json:""`

var virtual_tables = map[string]map[string]string {
	"experiments"             :{"rows": "",
		"tag" : "settings",
		"row" : ""   },
	"virt_nodes"              :{"rows": "",
		"tag" : "nodes",
		"row" : "node"},
	"virt_lans"               :{"rows": "", "tag":"lan_members",        "row": "lan_member"},
	"virt_lan_lans"           :{"rows": "", "tag":"lans",               "row": "lan"},
	"virt_lan_settings"       :{"rows": "", "tag":"lan_settings",       "row": "lan_setting"},
	"virt_lan_member_settings":{"rows": "", "tag":"lan_member_settings","row": "lan_member_setting"},
	"virt_trafgens"           :{"rows": "", "tag":"trafgens",           "row": "trafgen"},
	"virt_agents"             :{"rows": "", "tag":"agents",             "row": "agent"},
	"virt_routes"             :{"rows": "", "tag":"routes",             "row": "route"},
	"virt_vtypes"             :{"rows": "", "tag":"vtypes",             "row": "vtype"},
	"virt_programs"           :{"rows": "", "tag":"programs",           "row": "program"},
	"virt_node_desires"       :{"rows": "", "tag":"node_desires",       "row": "node_desire"},
	"virt_node_startloc"      :{"rows": "", "tag":"node_startlocs",     "row": "node_startloc"},
	"virt_user_environment"   :{"rows": "", "tag":"user_environments",  "row": "user_environment"},
	"nseconfigs"              :{"rows": "", "tag":"nseconfigs",         "row": "nseconfig"},
	"eventlist"               :{"rows": "", "tag":"events",             "row": "event"},
	"event_groups"            :{"rows": "", "tag":"event_groups",       "row": "event_group"},
	"virt_firewalls"          :{"rows": "", "tag":"virt_firewalls",     "row": "virt_firewall"},
	"firewall_rules"          :{"rows": "", "tag":"firewall_rules",     "row": "firewall_rule"},
	"virt_tiptunnels"         :{"rows": "", "tag":"tiptunnels",         "row": "tiptunnel"},
	"virt_parameters"         :{"rows": "", "tag":"parameters",         "row": "parameter"},
	//# This is a fake table. See below. If we add more, lets generalize.
	"external_sourcefiles"   :{"rows": "", "tag": "nsfiles",           "row": "nsfiles"},
}

// Note that this is a map of array of maps
var virtualTablesRows =  map[string] [] map[string]interface{} {
	//var virtualTablesRows = map[string]map[string]interface{} {
	"experiments"             :{},
	"virt_nodes"              :{},
	"virt_lans"               :{},
	"virt_lan_lans"           :{},
	"virt_lan_settings"       :{},
	"virt_lan_member_settings":{},
	"virt_trafgens"           :{},
	"virt_agents"             :{},
	"virt_routes"             :{},
	"virt_vtypes"             :{},
	"virt_programs"           :{},
	"virt_node_desires"       :{},
	"virt_node_startloc"      :{},
	"virt_user_environment"   :{},
	"nseconfigs"              :{},
	"eventlist"               :{},
	"event_groups"            :{},
	"virt_firewalls"          :{},
	"firewall_rules"          :{},
	"virt_tiptunnels"         :{},
	"virt_parameters"         :{},
	"external_sourcefiles"    :{},
}
var virtualTablesStructs = map[string]interface{} {
	"experiments"             : &Experiments,
	"virt_nodes"              : &Virt_nodes,
	"virt_lans"               : &Virt_lans,
	"virt_lan_lans"           : &Virt_lan_lans,
	"virt_lan_settings"       : nil, // "Virt_lan_settings",
	"virt_lan_member_settings": nil, //"virt_lan_member_settings",
	"virt_trafgens"           : nil, //"virt_trafgens",
	"virt_agents"             : &Virt_agents,
	"virt_routes"             : nil, //"virt_routes",
	"virt_vtypes"             : &Virt_vtypes,
	"virt_programs"           : &Virt_programs,
	"virt_node_desires"       : nil, // "virt_node_desires",
	"virt_node_startloc"      : nil, // "virt_node_startloc",
	"virt_user_environment"   : nil, // "virt_user_environment",
	"nseconfigs"              : nil, // "nseconfigs",
	"eventlist"               : &Eventlist,
	"event_groups"            : &Event_groups,
	"virt_firewalls"          : nil, //"virt_firewalls",
	"firewall_rules"          : nil, // "firewall_rules",
	"virt_tiptunnels"         : nil, // "virt_tiptunnels",
	"virt_parameters"         : nil, // "virt_parameters",
	"external_sourcefiles"    : nil, // "external_sourcefiles",
}

func printElmt(element interface{}, depth int) {
	/*  if debug {
	name := ""
	pos  := ""
	switch element.(type) {
	case xml.StartElement:
		name = element.(xml.StartElement).Name.Local
		pos  = "START"
	case xml.EndElement:
		name = element.(xml.EndElement).Name.Local
		pos  = "END  "
	case xml.CharData:
		name = "\""+string([]byte(element.(xml.CharData)))+"\""
		pos  = "CHAR "
	case xml.Comment:
		name = "Comment"
		pos = "COMM"
	case xml.ProcInst:
		name = "ProcInst"
		pos = "PROC"
	case xml.Directive:
		name = "Directive"
		pos = "DIR"
	default:
		name = "Unknown"
		pos  = "Unknown"
	}

	fmt.Println(pos +" Element (" + strconv.Itoa(depth) + ") = ", name)
	*/
}
func printRowMap() {
	/*
for k, v := range ourRowMap {
	switch vv := v.(type) {
	case string:
		fmt.Println(k, "is string", vv)
	case float64:
		fmt.Println(k, "is float64", vv)
	case []interface{}:
		fmt.Println(k, "is an array:")
		for i, u := range vv {
			fmt.Println(i, u)
		}
	default:
		fmt.Println(k, "is of a type I don't know how to handle")
	}
}
*/
}
func processXmlElement(decoder *xml.Decoder, token xml.Token, depth *int) error {

	var inTable string

	switch se := token.(type) {
	case xml.StartElement:
		*depth++ // just for testing
		inTable = se.Name.Local
		thisStartElement := xml.StartElement(se)

		printElmt(thisStartElement, *depth)
		if inTable == "virtual_experiment" {
			fmt.Println("...return because nothing todo for " + inTable)
			return nil
		}
		//if inTable == "virt_nodes" {
		fmt.Println("decode  .........." + inTable)

		var tableStruct = virtualTablesStructs[inTable]
		if tableStruct == nil {
			// Note that it is all bad from here as we did not consume
			// everything between beginning and end of "row" and </inTable>
			fmt.Println("No Table structure for " + inTable)
			return errors.New( "No Table structure for " + inTable)
		}

		decoder.DecodeElement(&tableStruct, &se)
		jsonString, _ := json.Marshal(tableStruct)
		// fmt.Println(string(jsonString))

		var rowMap map[string]map[string]interface{}
		err := json.Unmarshal(jsonString, &rowMap)
		if err != nil { return errors.New( "Bad unmarshall of rowMap")}

		ourRowMap := rowMap["row"]
		printRowMap()
		// Note that each inTable has array of maps
		fmt.Println("SAVE to virtualTablesRows[inTable]")
		virtualTablesRows[inTable] = append(virtualTablesRows[inTable], ourRowMap)

		*depth-- // just for testing
		//}

	case xml.EndElement:
		*depth--
		elmt := xml.EndElement(se)
		printElmt(elmt, *depth)
	case xml.CharData:
		bytes := xml.CharData(se)
		printElmt(bytes, *depth, )
	case xml.Comment:
		printElmt(se, *depth)
	case xml.ProcInst:
		printElmt(se, *depth)
	case xml.Directive:
		printElmt(se, *depth)
	default:
		fmt.Println("Unknown")
	}
	return nil
}

func processXmlFile(inputFile string) {

	flag.Parse()
	fmt.Println("Open file " + inputFile)
	xmlFile, err := os.Open(inputFile)
	if err != nil {
		fmt.Println("Error opening file:", err)
		return
	}
	/*
	xmlFile1, err := os.Open("smokeParse.xml")
	defer xmlFile.Close()
	reader := bufio.NewReader(xmlFile1)
	line1, _, _ := reader.ReadLine()
	line2, _, _ := reader.ReadLine()
	*/
	//fmt.Println("LINE2=" + "<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?>\n" + "...")
	//if string(line1) != "#### BEGIN XML ####" || string(line2) != "<?xml version=\\\"1.0\\\" encoding=\\\"ISO-8859-1\\\"?>" {
	//	fmt.Println( "Bad XML File Format line1=" + string(line1) + "\n->Line2=" + string(line2))
	//return
	//}

	decoder := xml.NewDecoder(xmlFile)
	decoder.CharsetReader = charset.NewReaderLabel
	fmt.Println("Read tokens")
	var count = 0
	depth := 0
	for {
		// Read tokens from the XML document in a stream.
		token, err := decoder.Token()
		count += 1
		if err != nil {
			fmt.Println("Decoder ERROR=" + err.Error())
		}
		if token == nil {
			fmt.Println("Token = nil,  count=" + strconv.Itoa(count))
			break
		}

		xmlErr := processXmlElement(decoder, token, &depth)
		if err != nil {
			fmt.Println("ERROR:" + xmlErr.Error())
			return }
	}
}

func main() {
	inputFile := "smokeParse.xml"
	processXmlFile(inputFile)
}
