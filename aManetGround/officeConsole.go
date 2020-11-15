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
// NAME              REV  DATE       REMARKS			@
// Goran Scuric      1.0  01012018  Initial design     goran@usa.net
//================================================================================

package main

import (
	"bufio"
	"os"
	"flag"
	"fmt"
	"strings"
	"testbedGS/common/tbMessages"
	"testbedGS/common/tbMsgUtils"
	"testbedGS/common/tbJsonUtils"
	"testbedGS/common/tbConfiguration"
)
//=======================================================================
// Early work to test some options for officeMaster console
//=======================================================================
func startConsole(consoleInput <-chan string) {

	go func(ch <-chan string) {
		reader := bufio.NewReader(os.Stdin)
		for {
			swapinCommand := flag.NewFlagSet("swapin", flag.ContinueOnError)
			projectFlag := swapinCommand.String("p", "", "project")
			experimentFlag := swapinCommand.String("e", "", "experiment")
			userFlag := swapinCommand.String("u", "goran", "user")

			swapoutCommand := flag.NewFlagSet("swapout", flag.ContinueOnError)
			projectFlagOut := swapoutCommand.String("p", "", "-p <project>")
			experimentFlagOut := swapoutCommand.String("e", "", "-e <experiment>")
			userFlagOut := swapoutCommand.String("u", "goran", "user")

			commandOK := ""
			s, err := reader.ReadString('\n')
			if err != nil { // Maybe log non io.EOF errors, if you want
				fmt.Printf("Console:Error during ReadString")
			}

			//ch <- s

			s1 := strings.Split(s, "\n")
			sa := strings.Split(s1[0], " ")

			//for i := 0; i < length; i++ {println("START SA[", i, "]=", sa[i])}

			switch sa[0] {
			case "swapin":
				swapinCommand.Parse(sa[1:])
			case "swapout":
				swapoutCommand.Parse(sa[1:])
			default:
				flag.PrintDefaults()
				fmt.Printf("%q is not valid command.\n", sa[0])
				commandOK = "Error Bad Command"
				break
			}

			if commandOK == "" {
				if swapinCommand.Parsed() {
					if *projectFlag == "" {
						fmt.Println("SwapIn: Please supply the project name")
					} else {fmt.Printf("SwapIn p=%q", *projectFlag)}

					if *experimentFlag == "" {
						fmt.Println("SwapIn: Please supply experiment name")
					} else {fmt.Printf("SwapIn e=%q", *experimentFlag)}
					if *userFlag == "" {
						fmt.Println("SwapIn: Please supply the user name.")
					} else {fmt.Printf("Swapin u=%q", *userFlag)}
					sendSwapInMsg(*projectFlag, *experimentFlag,*userFlag, "")
				}
				if swapoutCommand.Parsed() {
					if *projectFlagOut == "" {
						fmt.Println("SwapOut: Please supply the project name")
					} else {fmt.Printf("\nSwapOut p=%q", *projectFlagOut)}
					if *experimentFlagOut == "" {
						fmt.Println("SwapOut: Please supply the experiment name")
					} else {fmt.Printf("\nSwapOut e=%q", *experimentFlagOut)}

					fmt.Printf("\nSwapOut u=%q", *userFlagOut)
					sendSwapOutMsg(*projectFlagOut, *experimentFlagOut,*userFlagOut, "")
				}
			}
		} // end of for ever
		//close(ch)
	}(consoleInput)
}
//=======================================================================
//
//=======================================================================
func sendSwapInMsg(project, experiment,userName, fileName string) {
	expMgrName := tbConfig.TBexpMgrName
	expMgrFullName := TBlocateMngr(sliceOfMgrs, expMgrName)
	if expMgrFullName != nil && expMgrFullName.Up == true {
		swapIn := tbMessages.SwapIn{Project:project, Experiment:experiment,
						UserName:userName, FileName: fileName}
		messageBody, _ := tbJsonUtils.TBmarshal(swapIn)
		newMsg := tbMsgUtils.TBswapinMsg(myFullName, expMgrFullName.Name, string(messageBody))

		tbMsgUtils.TBsendMsgOut(newMsg, expMgrFullName.Name.Address, myConnection)
	} else {
		fmt.Println("Console: Exp Master not available - try later")
	}
}
//=======================================================================
//
//=======================================================================
func sendSwapOutMsg(project, experiment,userName, fileName string) {
	expMgrName := tbConfig.TBexpMgrName
	expMgrFullName := TBlocateMngr(sliceOfMgrs, expMgrName)
	if expMgrFullName != nil && expMgrFullName.Up == true {
		swapOut := tbMessages.SwapOut{Project:project, Experiment:experiment,
			UserName:userName, FileName: fileName}
		messageBody, _ := tbJsonUtils.TBmarshal(swapOut)
		newMsg := tbMsgUtils.TBswapoutMsg(myFullName, expMgrFullName.Name, string(messageBody))

		tbMsgUtils.TBsendMsgOut(newMsg, expMgrFullName.Name.Address, myConnection)
	} else {
		fmt.Println("Console: Exp Master not available - try later")
	}
}