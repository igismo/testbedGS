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

import (
	"os"
	"fmt"
	"io/ioutil"
	"encoding/xml"
	"encoding/json"
	"strings"
	"regexp"
)

//====================================================================================
//====================================================================================

//-=-=-=-=-=-=--=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=---=-=-=-=-=-=-=-=-=-=-=-=
type virtual_experimentX struct {
	pid 	string `xml:"pid,attr"`
	eid 	string `xml:"eid,attr"`

	virtualNodes [] virtNode

	virtualLanLans [] virtLanLan

	virtualVtypes [] virtVtype

	virtualPrograms [] virtProgram

	eventGroups [] eventGroup

	virtualAgents1   [] virtAgent1

	experiment	expInfo

	virtualAgents2   [] virtAgent1

	eventLists [] eventList
}
func main44() {

	// Open our xmlFile
	xmlFile, err := os.Open("users.xml")
	// if we os.Open returns an error then handle it
	if err != nil {
		fmt.Println(err)
	}

	fmt.Println("Successfully Opened users.xml")
	// defer the closing of our xmlFile so that we can parse it later on
	defer xmlFile.Close()

	// read our opened xmlFile as a byte array.
	byteValue, _ := ioutil.ReadAll(xmlFile)

	// we initialize our Users array
	var exp virtual_experiment
	// we unmarshal our byteArray which contains our
	// xmlFiles content into 'users' which we defined above
	xml.Unmarshal(byteValue, &exp)

	jsonData, _ := json.Marshal(exp)
	fmt.Println(string(jsonData))
	// we iterate through every user within our users array and
	// print out the user Type, their name, and their facebook url
	// as just an example
	//for i := 0; i < len(exp.virtualNodes ); i++ {
	//fmt.Println("virtualNode Type: " + exp.Users[i].Type)
	//fmt.Println("User Name: " + exp.Users[i].Name)
	//fmt.Println("Facebook Url: " + exp.Users[i].Social.Facebook)
	//}

}
//====================================================================================
//====================================================================================
/// REVERSE
func main6() {
	rawJsonData := "{\"people\": [{\"firstname\": \"Nic\", \"lastname\": \"Raboy\"}]}"
	var data virtual_experiment
	json.Unmarshal([]byte(rawJsonData), &data)
	xmlData, _ := xml.Marshal(data)
	fmt.Println(string(xmlData))
}
//====================================================================================


func XmlPath2SrtructLinesNoNesting(paths []string) (string, map[string]StructNode, map[string]map[string]StructNode) {
	var RootName string
	RootStruct := make(map[string]StructNode)
	RestStructs := make(map[string]map[string]StructNode)

	RootName = strings.Split(paths[0], ".")[0]

	deDuplicateMap := make(map[string]string)

	removeNum := regexp.MustCompile(`\[(\d+)\]`)
	for _, path := range paths {
		path = removeNum.ReplaceAllString(path, "[]")
		Flods := strings.Count(path, "[")
		path = strings.Replace(path, "[]", "", -1)
		splitedPath := strings.Split(path, ".")
		last := splitedPath[len(splitedPath)-1]
		if strings.Index(last, "-") == 0 { //Attr
			if RootName == splitedPath[len(splitedPath)-2] { //RootAttr
				NodeName := strings.Title(last[1:])
				xmlRoute := "`xml:" + `"` + last[1:] + `,attr"` + "`"
				if _, exist := deDuplicateMap[NodeName]; exist {
					if deDuplicateMap[NodeName] != xmlRoute {
						NodeName = "Rss" + NodeName
						deDuplicateMap[NodeName] = xmlRoute
					}
				} else {
					deDuplicateMap[NodeName] = xmlRoute
				}
				StructLineAppend := StructNode{Name: NodeName, Type: "string", Path: xmlRoute}
				RootStruct[xmlRoute] = StructLineAppend
			} else { //NoneRootAttr
				NodeName := strings.Title(splitedPath[len(splitedPath)-2])
				xmlRoute := strings.Join(splitedPath[1:len(splitedPath)-1], ">")
				xmlPath := "`xml:" + `"` + xmlRoute + `"` + "`"
				Stype := NodeName
				for i := 0; i < Flods; i++ {
					Stype = "[]" + Stype
				}
				if _, exist := deDuplicateMap[NodeName]; exist {
					if deDuplicateMap[NodeName] != xmlRoute {
						NodeName = ""
						for _, v := range strings.Split(xmlRoute, ">") {
							NodeName = strings.Title(v) + NodeName
						}
						deDuplicateMap[NodeName] = xmlRoute
					}
				} else {
					deDuplicateMap[NodeName] = xmlRoute
				}
				StructLineAppend := StructNode{Name: NodeName, Type: Stype, Path: xmlPath}
				RootStruct[xmlRoute] = StructLineAppend

				LeafName := strings.Title(last[1:])
				RsetStructLineAppend := StructNode{Name: LeafName, Type: "string", Path: "`xml:" + `"` + last[1:] + `,attr"` + "`"}

				// log.Println(NodeName, LeafName)
				if _, exist := RestStructs[NodeName]; exist {
					RestStructs[NodeName][LeafName] = RsetStructLineAppend
				} else {
					NewLeafStruct := make(map[string]StructNode)
					NewLeafStruct[LeafName] = RsetStructLineAppend
					RestStructs[NodeName] = NewLeafStruct
				}

			}
		} else if strings.Index(last, "#") == 0 { //chardata
			if RootName == splitedPath[len(splitedPath)-2] { //RootChartata
				NodeName := strings.Title(last[1:])
				xmlRoute := "`xml:" + `",chardata"` + "`"
				if _, exist := deDuplicateMap[NodeName]; exist {
					if deDuplicateMap[NodeName] != xmlRoute {
						NodeName = "Rss" + NodeName
						deDuplicateMap[NodeName] = xmlRoute
					}
				} else {
					deDuplicateMap[NodeName] = xmlRoute
				}
				StructLineAppend := StructNode{Name: NodeName, Type: "string", Path: xmlRoute}
				RootStruct[xmlRoute] = StructLineAppend
			} else { //NonRootChardata
				NodeName := strings.Title(splitedPath[len(splitedPath)-2])
				xmlRoute := strings.Join(splitedPath[1:len(splitedPath)-1], ">")
				xmlPath := "`xml:" + `"` + xmlRoute + `"` + "`"
				Stype := NodeName
				for i := 0; i < Flods; i++ {
					Stype = "[]" + Stype
				}
				if _, exist := deDuplicateMap[NodeName]; exist {
					if deDuplicateMap[NodeName] != xmlRoute {
						NodeName = ""
						for _, v := range strings.Split(xmlRoute, ">") {
							NodeName = strings.Title(v) + NodeName
						}
						deDuplicateMap[NodeName] = xmlRoute
					}
				} else {
					deDuplicateMap[NodeName] = xmlRoute
				}
				StructLineAppend := StructNode{Name: NodeName, Type: Stype, Path: xmlPath}
				RootStruct[xmlRoute] = StructLineAppend

				LeafName := strings.Title(last[1:])
				RsetStructLineAppend := StructNode{Name: LeafName, Type: "string", Path: "`xml:" + `",chardata"` + "`"}

				if _, exist := RestStructs[NodeName]; exist {
					RestStructs[NodeName][LeafName] = RsetStructLineAppend
				} else {
					NewLeafStruct := make(map[string]StructNode)
					NewLeafStruct[LeafName] = RsetStructLineAppend
					RestStructs[NodeName] = NewLeafStruct
				}
			}
		} else { //NormalString
			NodeName := strings.Title(splitedPath[len(splitedPath)-1])
			xmlRoute := strings.Join(splitedPath[1:], ">")
			xmlPath := "`xml:" + `"` + xmlRoute + `"` + "`"
			Stype := "string"
			for i := 0; i < Flods; i++ {
				Stype = "[]" + Stype
			}
			if _, exist := deDuplicateMap[NodeName]; exist {
				if deDuplicateMap[NodeName] != xmlRoute {
					NodeName = ""
					for _, v := range strings.Split(xmlRoute, ">") {
						NodeName = strings.Title(v) + NodeName
					}
					deDuplicateMap[NodeName] = xmlRoute
				}
			} else {
				deDuplicateMap[NodeName] = xmlRoute
			}
			StructLineAppend := StructNode{Name: NodeName, Type: Stype, Path: xmlPath}
			RootStruct[xmlRoute] = StructLineAppend
		}
	}
	return strings.Title(RootName), RootStruct, RestStructs
}


func JsonPath2SrtructLinesNoNesting(paths []string) (map[string]StructNode, map[string]map[string]StructNode) {
	RootStruct := make(map[string]StructNode)
	RestStructs := make(map[string]map[string]StructNode)

	deDuplicateMap := make(map[string]string)
	removeNum := regexp.MustCompile(`\[(\d+)\]`)

	for _, path := range paths {
		path = removeNum.ReplaceAllString(path, "[]")
		Flods := strings.Count(path, "[")
		path = strings.Replace(path, "[]", "", -1)
		splitedPath := strings.Split(path, ".")
		last := splitedPath[len(splitedPath)-1]
		NodeName := strings.Title(last)
		jsonRoute := strings.Join(splitedPath, ">")
		jsonPath := "`json:" + `"` + jsonRoute + `"` + "`"
		Stype := "string"
		for i := 0; i < Flods; i++ {
			Stype = "[]" + Stype
		}
		if _, exist := deDuplicateMap[NodeName]; exist {
			if deDuplicateMap[NodeName] != jsonRoute {
				NodeName = ""
				for _, v := range strings.Split(jsonRoute, ">") {
					NodeName = strings.Title(v) + NodeName
				}
				deDuplicateMap[NodeName] = jsonRoute
			}
		} else {
			deDuplicateMap[NodeName] = jsonRoute
		}
		StructLineAppend := StructNode{Name: NodeName, Type: Stype, Path: jsonPath}
		RootStruct[jsonRoute] = StructLineAppend
	}
	return RootStruct, RestStructs

}
//======
//======
type StructNode struct {
	Name string
	Type string
	Path string
}

func Xml2Struct(xdata []byte, Nesting bool) string {
	/*
	m, err := mxj.NewMapXml(xdata)
	if err != nil {
		panic(err)
	}
	paths := m.LeafPaths()
	if Nesting {
		return "Not implement yet..."
	} else {
		RootName, RootStruct, RestStructs := XmlPath2SrtructLinesNoNesting(paths)
		return RootDatas2Struct(RootName, RootStruct, RestStructs)
	}
	*/
	return "Not implement yet..."
}

func Json2Struct(jdata []byte, RootName string, Nesting bool) string {
	/*
	m, err := mxj.NewMapJson(jdata)
	if err != nil {
		panic(err)
	}
	paths := m.LeafPaths()
	if Nesting {
		return "Not implement yet..."
	} else {
		RootStruct, RestStructs := JsonPath2SrtructLinesNoNesting(paths)
		return RootDatas2Struct(strings.Title(RootName), RootStruct, RestStructs)
	}
	*/
	return "Not implement yet..."
}

func RootDatas2Struct(RootName string, RootLines map[string]StructNode, RestStructs map[string]map[string]StructNode) string {
	Structs := "type " + RootName + " struct {\n"
	for _, v := range RootLines {
		Structs += "\t" + v.Name + "\t" + v.Type + "\t" + v.Path + "\n"
	}
	Structs += "}\n\n"

	for NodeName, v1 := range RestStructs {
		Structs += "type " + NodeName + " struct {\n"
		for _, v2 := range v1 {
			Structs += "\t" + v2.Name + "\t" + v2.Type + "\t" + v2.Path + "\n"
		}
		Structs += "}\n"
	}
	return Structs
}


//-=-=-=-=-=-=-=-=-=-=-==-=-=-==-=-=
var xml1 = []byte(`
<virt_nodes>
<vname>crypto1</vname>
<typex>MicroCloud</typex>
<ips>0:10.1.3.3 1:10.1.1.3</ips>
<osname>Ubuntu1404-64-STD</osname>
<cmd_line></cmd_line>
<rpms></rpms>
<startupcmd></startupcmd>
<tarfiles></tarfiles>
<failureaction>nonfatal</failureaction>
<routertype>static</routertype>
<fixed></fixed>
<plab_plcnet>none</plab_plcnet>

</virt_nodes>
</xml1>`)



type virt_nodes struct {
		//row struct {
			vname         string `xml:"vname"`
			typex          string `xml:"typex"`
			ips           string `xml:"ips"`
			osname        string `xml:"osname"`
			cmd_line      string `xml:"cmd_line"`
			rpms          string `xml:"rpms"  `
			startupcmd    string `xml:"startupcmd"`
			tarfiles      string `xml:"tarfiles"`
			failureaction string `xml:"failureaction"`
			routertype    string `xml:"routertype"`
			fixed         string `xml:"fixed"`
			plab_plcnet   string `xml:"plab_plcnet"`
		//} //`xml:"row" json:"row"`
	} // `xml:"virt_nodes" json:"virt_nodes"`
// <person>
//<virtual_experiment> pid='DeterTest' eid=smokeGS</virtual_experiment>
//<name>Workin gender='male' age='12'</name>
//<name>Luann Van Houten gender='female' age='32'</name>
//	<yyy>xxx pid='DeterTest' eid='smokeGS'</yyy>
var personXML = []byte(`
<virtual_experiment1>

<virtual_experiment  eid='smokeGS' pid='DeterTest' ></virtual_experiment>
<addresses xxx='yyy'>
	<address type="secondary">
		<street>321 MadeUp Lane</street>
		<city>Shelbyville</city>
	</address>

	<address type="primary">
		<street>123 Fake St</street>
		<city>Springfield</city>
	</address>
</addresses>

</virtual_experiment1>`)

// </person>`)
// type Person struct {
type	vir  struct{

//virtual_experiment struct {
//yyy string `xml:"yyy"`
//	Eid string `xml:"eid,attr"`
//vvv struct {
	VirtExp struct {
		//xxx string `xml:"virtual_experiment"`
		Pid string `xml:"pid,attr"`
		Eid string `xml:"eid,attr"`
	}

	Addresses []struct {
		Street string `xml:"street"`
		City   string `xml:"city"`
		Type   string `xml:"type,attr"`
	} `xml:"addresses>address"`
//}
}
//}

func main() {
var luann vir
xml.Unmarshal(personXML, &luann)
fmt.Println(luann)
}
//=-=-=-=-=-=-=-=-=-=-=-=-=-=-=--==-=-=-=-=-=-=-=-==-=-=-=--
func mainx() {
var data virt_nodes
xml.Unmarshal(xml1, &data)
fmt.Println(data)
}

//type virtualExperiment struct {
type	virtual_experiment struct {
	pid string `xml:"pid,attr"`
	eid string `xml:"eid,attr"`

	virt_nodes struct {
		row struct {
			vname         string `xml:"vname" json:"vname"`
			Type          string `xml:"type" json:"type"`
			ips           string `xml:"ips" json:"ips"`
			osname        string `xml:"osname" json:"osname"`
			cmd_line      string `xml:"cmd_line" json:"cmd_line"`
			rpms          string `xml:"rpms" json:"rpms"`
			startupcmd    string `xml:"startupcmd" json:"startupcmd"`
			tarfiles      string `xml:"tarfiles" json:"tarfiles"`
			failureaction string `xml:"failureaction" json:"failureaction"`
			routertype    string `xml:"routertype" json:"routertype"`
			fixed         string `xml:"fixed" json:"fixed"`
			plab_plcnet   string `xml:"plab_plcnet" json:"plab_plcnet"`
		} `xml:"row" json:"row"`
	} `xml:"virt_nodes" json:"virt_nodes"`

	}
//}
//-=-=-=-=-=-=--=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=---=-=-=-=-=-=-=-=-=-=-=-=

type virtNode struct {
	virt_nodes struct {
		row struct {
			vname         string `xml:"vname" json:"vname"`
			Type          string `xml:"type" json:"type"`
			ips           string `xml:"ips" json:"ips"`
			osname        string `xml:"osname" json:"osname"`
			cmd_line      string `xml:"cmd_line" json:"cmd_line"`
			rpms          string `xml:"rpms" json:"rpms"`
			startupcmd    string `xml:"startupcmd" json:"startupcmd"`
			tarfiles      string `xml:"tarfiles" json:"tarfiles"`
			failureaction string `xml:"failureaction" json:"failureaction"`
			routertype    string `xml:"routertype" json:"routertype"`
			fixed         string `xml:"fixed" json:"fixed"`
			plab_plcnet   string `xml:"plab_plcnet" json:"plab_plcnet"`
		} `xml:"row" json:"row"`
	} `xml:"virt_nodes" json:"virt_nodes"`

	virt_agents struct {
		row struct {
			vnode      string `xml:"vnode" json:"vnode"`
			vname      string `xml:"vname" json:"vname"`
			objecttype int    `xml:"objecttype" json:"objecttype"`
		} `xml:"row" json:"row"`
	} `xml:"virt_agents" json:"virt_agents"`
}

type virtLanLan struct {
	virt_lan_lans struct {
		row struct {
			vname string `xml:"vname" json:"vname" ` //<vname>phys0008</vname>
		} `xml:"row" json:"row"`
	} `xml:"virt_lan_lans" json:"virt_lan_lans"`

	virtLanLink [] struct {
		virt_lans struct {
			row struct {
				vname          string  `xml:"vname" 	json:"vname"`          //<vname>phys0008</vname>
				member         string  `xml:"member" 	json:"member"`         //<member>vrouter:2</member>
				mask           string  `xml:"mask" 	json:"mask"`           //<mask>255.255.255.0</mask>
				delay          float32 `xml:"delay" 	json:"delay"`          //<delay>0.0</delay>
				rdelay         float32 `xml:"rdelay" 	json:"rdelay"`         //<rdelay>0.0</rdelay>
				bandwidth      int     `xml:"bandwidth" 	json:"bandwidth"`      //<bandwidth>1000000</bandwidth>
				rbandwidth     int     `xml:"rbandwidth" 	json:"rbandwidth"`     //<rbandwidth>1000000</rbandwidth>
				backfill       int     `xml:"backfill" 	json:"backfill"`       //<backfill>0</backfill>
				rbackfill      int     `xml:"rbackfill" 	json:"rbackfill"`      //<rbackfill>0</rbackfill>
				lossrate       int     `xml:"lossrate" 	json:"lossrate"`       //<lossrate>0</lossrate>
				rlossrate      int     `xml:"rlossrate" 	json:"rlossrate"`      //<rlossrate>0</rlossrate>
				cost           int     `xml:"cost" 	json:"cost"`           //<cost>1</cost>
				widearea       int     `xml:"widearea" 	json:"widearea"`       //<widearea>0</widearea>
				emulated       int     `xml:"emulated" 	json:"emulated"`       //<emulated>0</emulated>
				uselinkdelay   int     `xml:"uselinkdelay" 	json:"uselinkdelay"`   //<uselinkdelay>0</uselinkdelay>
				nobwshaping    int     `xml:"nobwshaping" 	json:"nobwshaping"`    //<nobwshaping>0</nobwshaping>
				encap_style    string  `xml:"encap_style" 	json:"encap_style"`    //<encap_style>default</encap_style>
				q_limit        int     `xml:"q_limit" 	json:"q_limit"`        //<q_limit>100</q_limit>
				q_maxthresh    int     `xml:"q_maxthresh" 	json:"q_maxthresh"`    //<q_maxthresh>15</q_maxthresh>
				q_minthresh    int     `xml:"q_minthresh" 	json:"q_minthresh"`    //<q_minthresh>5</q_minthresh>
				q_weight       float32 `xml:"q_weight" 	json:"q_weight"`       //<q_weight>0.002</q_weight>
				q_qinbytes     int     `xml:"q_qinbytes" 	json:"q_bytes"`     //<q_qinbytes>0</q_qinbytes>
				q_bytes        int     `xml:"q_bytes" 	json:"q_bytes"`        //<q_bytes>0</q_bytes>
				q_meanpsize    int     `xml:"q_meanpsize" 	json:"q_meanpsize"`    //<q_meanpsize>500</q_meanpsize>
				q_wait         int     `xml:"q_wait" 	json:"q_wait"`         //<q_wait>1</q_wait>
				q_setbit       int     `xml:"q_setbit" 	json:"q_setbit"`       //<q_setbit>0</q_setbit>
				q_droptail     int     `xml:"q_droptail" 	json:"q_droptail"`     //<q_droptail>1</q_droptail>
				q_red          int     `xml:"q_red" 	json:"q_red"`          //<q_red>0</q_red>
				q_gentle       int     `xml:"q_gentle" 	json:"q_gentle"`       //<q_gentle>0</q_gentle>
				trivial_ok     int     `xml:"trivial_ok" 	json:"trivial_ok"`     //<trivial_ok>1</trivial_ok>
				protocol       string  `xml:"protocol" 	json:"protocol"`       //<protocol>ethernet</protocol>
				is_accesspoint int     `xml:"is_accesspoint" 	json:"is_accesspoint"` //<is_accesspoint>0</is_accesspoint>
				vnode          string  `xml:"vnode" 	json:"vnode"`          //<vnode>vrouter</vnode>
				vport          int     `xml:"vport" 	json:"vport"`          //<vport>2</vport>
				ip             string  `xml:"ip" 	json:"ip"`             //<ip>10.1.1.2</ip>
				mustdelay      int     `xml:"mustdelay" 	json:"mustdelay"`      //<mustdelay>0</mustdelay>
			} `xml:"row" 	json:"row"`
		} `xml:"virt_lans" 	json:"virt_lans"`
	}
}

type virtVtype struct {
	virt_vtypes struct {
		row struct {
			name string `xml:"name" json:"name"` //<vname>phys0008</vname>
			weight float32 `xml:"weight" json:"weight"`
			members string `xml:"members" json:"members"`
		} `xml:"row" json:""`
	} `xml:"virt_vtypes" json:""`
}

type virtProgram struct {
	virt_programs struct {
		row struct {
			vnode string `xml:"vnode" json:"vnode"`							//<vnode>vrouter</vnode>
			vname string `xml:"vname" json:"vname"`							//<vname>vrouter_startcmd</vname>
			command string `xml:"command" json:"command"`						//<command>()</command>
			dir string `xml:"dir" json:"dir"`								//<dir></dir>
			timeout int `xml:"timeout" json:"timeout"` 						//<timeout></timeout>
			expected_exit_code int `xml:"expected_exit_code" json:"expected_exit_code"`
		} `xml:"row" json:"row"`
	} `xml:"virt_programs" json:"virt_programs"`

	virt_agents struct {
		row struct {
			vnode      string `xml:"vnode" json:"vnode"`
			vname      string `xml:"vname" json:"vname"`
			objecttype int    `xml:"objecttype" json:"objecttype"`
		} `xml:"row" json:"row"`
	} `xml:"virt_agents" json:"virt_agents"`
}

type eventGroup struct {
	event_groups struct {
		row struct {
			group_name string `xml:"group_name" json:"group_name"`	//<group_name>__all_programs</group_name>
			agent_name string `xml:"agent_name" json:"agent_name"`	//<agent_name>vrouter_startcmd</agent_name>
		} `xml:"row" json:"row"`
	} `xml:"event_groups" json:"event_groups"`
}

type virtAgent1 struct {
	virt_agents struct {
		row struct {
			vnode      string `xml:"vnode" json:"vnode"`
			vname      string `xml:"vname" json:"vname"`
			objecttype int    `xml:"objecttype" json:"objecttype"`
		} `xml:"row" json:"row"`
	} `xml:"virt_agents" json:"virt_agents"`
}

type expInfo struct {
	experiments struct {
		row struct {
			mem_usage int `xml:"mem_usage" json:"mem_usage"`
			cpu_usage int `xml:"cpu_usage" json:"cpu_usage"`
			forcelinkdelays int `xml:"forcelinkdelays" json:"forcelinkdelays"`
			uselinkdelays int `xml:"uselinkdelays" json:"uselinkdelays"`
			usewatunnels int `xml:"usewatunnels" json:"usewatunnels"`
			uselatestwadata int `xml:"uselatestwadata" json:"uselatestwadata"`
			wa_delay_solverweight int `xml:"wa_delay_solverweight" json:"wa_delay_solverweight"`
			wa_bw_solverweight int `xml:"wa_bw_solverweight" json:"wa_bw_solverweight"`
			wa_plr_solverweight int `xml:"wa_plr_solverweight" json:""`
			encap_style string `xml:"encap_style" json:"wa_plr_solverweight"`
			allowfixnode int `xml:"allowfixnode" json:"allowfixnode"`
			sync_server string `xml:"sync_server" json:"sync_server"`
			use_ipassign int `xml:"use_ipassign" json:"use_ipassign"`
		} `xml:"row" json:"row"`
	} `xml:"experiments" json:"experiments"`
}


type eventList struct {
	eventlist struct {
		row struct {
			time int `xml:"time" json:"time"`	//<time>0</time>
			vnode string `xml:"vnode" json:"vnode"`	//<vnode>control</vnode>
			vname string `xml:"vname" json:"vname"`	//<vname>control_startcmd</vname>
			objecttype int `xml:"objecttype" json:"objecttype"`	//<objecttype>4</objecttype>
			eventtype int `xml:"eventtype" json:"eventtype"`	//<eventtype>1</eventtype>
			arguments string `xml:"arguments" json:"arguments"`	//<arguments>COMMAND=(?)</arguments>
			atstring string `xml:"atstring" json:"atstring"`	//<atstring></atstring>
		} `xml:"row" json:""`
	}`xml:"eventlist" json:""`
}
func main4() {

	// Open our xmlFile
	xmlFile, err := os.Open("users.xml")
	// if we os.Open returns an error then handle it
	if err != nil {
		fmt.Println(err)
	}

	fmt.Println("Successfully Opened users.xml")
	// defer the closing of our xmlFile so that we can parse it later on
	defer xmlFile.Close()

	// read our opened xmlFile as a byte array.
	byteValue, _ := ioutil.ReadAll(xmlFile)

	// we initialize our Users array
	var exp virtual_experiment
	// we unmarshal our byteArray which contains our
	// xmlFiles content into 'users' which we defined above
	xml.Unmarshal(byteValue, &exp)

	jsonData, _ := json.Marshal(exp)
	fmt.Println(string(jsonData))
	// we iterate through every user within our users array and
	// print out the user Type, their name, and their facebook url
	// as just an example
	//for i := 0; i < len(exp.virtualNodes ); i++ {
	//fmt.Println("virtualNode Type: " + exp.Users[i].Type)
	//fmt.Println("User Name: " + exp.Users[i].Name)
	//fmt.Println("Facebook Url: " + exp.Users[i].Social.Facebook)
	//}

}

func main1() {
	// Open our xmlFile
	xmlFile, err := os.Open("smokeParse.xml")
	// if we os.Open returns an error then handle it
	if err != nil {
		fmt.Println(err)
	}

	fmt.Println("Successfully Opened smokeParse.xml")
	// defer the closing of our xmlFile so that we can parse it later on
	defer xmlFile.Close()

	// read our opened xmlFile as a byte array.
	byteValue, _ := ioutil.ReadAll(xmlFile)

	// fmt.Println("BYTEVALUE=" + string(byteValue))
	// we initialize our Users array
	var exp virtual_experiment
	// we unmarshal our byteArray which contains our
	// xmlFiles content into 'users' which we defined above
	xml.Unmarshal(byteValue, &exp)

	jsonData, _ := json.Marshal(exp)
	fmt.Println(string(jsonData))
	// we iterate through every user within our users array and
	// print out the user Type, their name, and their facebook url
	// as just an example
	//for i := 0; i < len(exp.virtualNodes ); i++ {
	//fmt.Println("virtualNode Type: " + exp.Users[i].Type)
	//fmt.Println("User Name: " + exp.Users[i].Name)
	//fmt.Println("Facebook Url: " + exp.Users[i].Social.Facebook)
	//}

}
