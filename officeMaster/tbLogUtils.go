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
//=============================================================================
// FILE NAME: tbMsgUtils.go
// DESCRIPTION:
// Utilities for creating and handling messages used by the
// Experiment Master and Experiment Controllers.
// Contains description of all possible messages related to Experiments
//
// NAME              REV  DATE       REMARKS			@
// Goran Scuric      1.0  01012018  Initial design     goran@usa.net
//================================================================================

package main

import (
    "log"
	"os"
	"runtime"
	//"testbedGS/common/tbConfiguration"
	"time"
	//"testbedGS/common/tbExpUtils"
	//"testbedGS/common/tbDbaseUtils"
	//"strconv"
	//"database/sql"
	"fmt"
)
const (
	OS_READ = 04
	OS_WRITE = 02
	OS_EX = 01
	OS_USER_SHIFT = 6
	OS_GROUP_SHIFT = 3
	OS_OTH_SHIFT = 0

	OS_USER_R = OS_READ<<OS_USER_SHIFT
	OS_USER_W = OS_WRITE<<OS_USER_SHIFT
	OS_USER_X = OS_EX<<OS_USER_SHIFT
	OS_USER_RW = OS_USER_R | OS_USER_W
	OS_USER_RWX = OS_USER_RW | OS_USER_X

	OS_GROUP_R = OS_READ<<OS_GROUP_SHIFT
	OS_GROUP_W = OS_WRITE<<OS_GROUP_SHIFT
	OS_GROUP_X = OS_EX<<OS_GROUP_SHIFT
	OS_GROUP_RW = OS_GROUP_R | OS_GROUP_W
	OS_GROUP_RWX = OS_GROUP_RW | OS_GROUP_X

	OS_OTH_R = OS_READ<<OS_OTH_SHIFT
	OS_OTH_W = OS_WRITE<<OS_OTH_SHIFT
	OS_OTH_X = OS_EX<<OS_OTH_SHIFT
	OS_OTH_RW = OS_OTH_R | OS_OTH_W
	OS_OTH_RWX = OS_OTH_RW | OS_OTH_X

	OS_ALL_R = OS_USER_R | OS_GROUP_R | OS_OTH_R
	OS_ALL_W = OS_USER_W | OS_GROUP_W | OS_OTH_W
	OS_ALL_X = OS_USER_X | OS_GROUP_X | OS_OTH_X
	OS_ALL_RW = OS_ALL_R | OS_ALL_W
	OS_ALL_RWX = OS_ALL_RW | OS_GROUP_X
)

func LogLicenseNotice() {
	fmt.Println("<testbedGS > Copyright(C) < 2018 > < Goran Scuric >")
	fmt.Println("This program comes with ABSOLUTELY NO WARRANTY")
	fmt.Println("For details read the LICENSE file.This is free software,")
	fmt.Println(" and you are welcome to redistribute it under certain conditions")
}
//=============================================================================
// Functions to handle testbed (not experiment) log events. Typical Init sequence
//  var Log = tbLogUtils.LogInstance{} // global per container
//  Log.DebugLog   = true
//  Log.WarningLog = true
//  Log.ErrorLog   = true
/// tbLogUtils.CreateLog(&Log, myName)
//  Log.Warning(&Log,"this will be printed anyway")
//  Log.Debug(&Log," ...")
//  Log.Print(&Log," ...")
//  Log.Error(&Log," ...")
//=============================================================================
type LogInstance struct {
	DebugLog   bool
	ErrorLog   bool
	WarningLog bool
	MyLog      *log.Logger
	MyFileName string
}

func (m *LogInstance) Debug(Log *LogInstance,args ...interface{}) {
	if m.DebugLog { Log.MyLog.Print(args...)}
}
func (m *LogInstance) Print(Log *LogInstance, args ...interface{}) {
	Log.MyLog.Print(args...)
}
func (m *LogInstance) Warning(Log *LogInstance, args ...interface{}) {
	if m.WarningLog {Log.MyLog.Print(args...)}
}
func (m *LogInstance) Error(Log *LogInstance, args ...interface{}) {
	if m.ErrorLog {Log.MyLog.Print(args...)}
}

func CreateLog(Log *LogInstance, moduleName string) {

	//out, err1 := exec.Command("pwd").Output()
	//if err1 != nil {
	//	log.Fatal(err1)
	//}
	//fmt.Println("The pwd is \n", out)

	current := time.Now()
	formatedTime := current.Format(time.RFC3339)
	var logpath =  TBlogPath + moduleName + formatedTime + ".log"

	fmt.Println("CreateLog: MY LOG PATH=", logpath)
	// flag.Parse()
	// var file, err = os.Create(logpath)
	file, err := os.OpenFile(logpath, os.O_RDWR | os.O_CREATE | os.O_APPEND, 0666)
	if err != nil {
		fmt.Println("CreateLog: ERROR  ERROR   ERROR MY LOG PATH=", logpath)
		// TODO panic(err)
	}
	// Make sure file gets closed when we terminate
	defer func() {
		file.Close()
	}()

	xLog := log.New(file, "", log.LstdFlags|log.Lshortfile)
	xLog.Println("\nLogFile:" + logpath)

	Log.MyFileName = logpath
	Log.MyLog      = xLog
	Log.Warning(Log,"START")
}


//=============================================================================
// The following is example of log usage within other modules
//=============================================================================
func logExamples() {

	Log := LogInstance{}
	Log.DebugLog   = true
	Log.WarningLog = true
	Log.ErrorLog   = true
	CreateLog(&Log, "goran")

	// TODO ... well Log needs to be global or so

	Log.Debug  (&Log,"Server v%s",TBversion)
	Log.Print  (&Log,"this will be printed anyway")
	Log.Warning(&Log,"this will be printed anyway")
	// OR
	Log.MyLog.Printf("Server v%s pid=%d started with processes: %d", TBversion,
		        os.Getpid(),runtime.GOMAXPROCS(runtime.NumCPU()))

}


