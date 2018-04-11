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
	"encoding/xml"
	"bytes"
	"fmt"
	"os"
)

type VirtNodes struct {
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


func main() {
	inputFile := "smokeParse.xml"
	xmlFile, err := os.Open(inputFile)
	if err != nil {
		fmt.Println("Error opening file:", err)
		return
	}
	defer xmlFile.Close()

	decoder := xml.NewDecoder(xmlFile)
	total := 0

	var inElement string
	for {
		t, _ := decoder.Token()
		if t == nil {
			fmt.Println("token = nil")
			break
		}
		// Inspect the type of the token just read.
		switch se := t.(type) {
		case xml.StartElement:
			inElement = se.Name.Local
			fmt.Println("inElement=" + inElement)
			if inElement == "virt_nodes" {
				var o VirtNodes
				decoder.DecodeElement(&o, &se)
				fmt.Println("vname=" + o.Row.Vname)
				fmt.Println("osname=" + o.Row.Osname)
				total++
			}
		default:
		}


	}

	fmt.Printf("Total: %d \n", total)
}
var data = []byte(`
<content>
    <p>this is content area</p>
    <animal>
        <p>This id dog</p>
        <dog>
           <p>tommy</p>
        </dog>
    </animal>
    <birds>
        <p>this is birds</p>
        <p>this is birds</p>
    </birds>
    <animal>
        <p>this is animals</p>
    </animal>
</content>
`)

type Node struct {
	XMLName xml.Name
	Content []byte `xml:",innerxml"`
	Nodes   []Node `xml:",any"`
}

func main01() {
	buf := bytes.NewBuffer(data)
	dec := xml.NewDecoder(buf)

	var n Node
	err := dec.Decode(&n)
	if err != nil {
		panic(err)
	}

	walk([]Node{n}, func(n Node) bool {
		if n.XMLName.Local == "p" {
			fmt.Println(string(n.Content))
		}
		return true
	})
}

func walk(nodes []Node, f func(Node) bool) {
	for _, n := range nodes {
		if f(n) {
			walk(n.Nodes, f)
		}
	}
}
