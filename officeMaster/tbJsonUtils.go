/********************************************************************************
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
// NAME              REV  DATE       REMARKS			@
// Goran Scuric      1.0  01012018  Initial design     goran@usa.net
//================================================================================
/* SaveJSON
 */
package main

import ( 
    "encoding/json"
    "fmt"
	"os"
)


func TBmarshal(key interface{}) ([]byte, error) {
    msg, err := json.Marshal(key)
    return msg,err
}

func TBunmarshal(input []byte, key interface{}) (error) {
	err := json.Unmarshal(input, &key)
	return err
}

func TBmarshalAndSave(filename string,key interface{}) ([]byte, error) {
	// Marshall into file
	if filename != "" {
		outFile, err := os.Create(filename)
		checkError1(err)
		encoder := json.NewEncoder(outFile)
		err = encoder.Encode(key)
		outFile.Close()
	}
	// Marshall into msg
	msg, err := json.Marshal(key)
	return msg,err
}

func TBloadAndUnmarshal(fileName string, key interface{}) {
	inFile, err := os.Open(fileName)
	checkError1(err)
	decoder := json.NewDecoder(inFile)
	err = decoder.Decode(key)
	checkError1(err)
	inFile.Close()
}

func checkError1(err error) {
	if err != nil {
		fmt.Println("Fatal error ", err.Error())
		os.Exit(1)
	}
}
