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
/* Various net utilities */

package main

import ( 
    "fmt"
    "net"
	"strconv"
	"strings"
	// "testbedGS/common/tbConfiguration"
)


//================================================
// GetLocalIp() - return non-loopback IP address
//================================================
func GetLocalIp() (string) {

    netInterfaceAddresses, err := net.InterfaceAddrs()

    if err == nil { // no error 
	    for _, netInterfaceAddress := range netInterfaceAddresses {

			networkIp, ok := netInterfaceAddress.(*net.IPNet)
			//fmt.Println("GetIP: ", networkIp.IP.String)
        	if ok && !networkIp.IP.IsLoopback() && networkIp.IP.To4() != nil {

				ip := networkIp.IP.String()
					fmt.Println("Resolved Host IP: " + ip)
						return ip
            }
        }
    }

    return ""
}

//====================================================================================
//
//====================================================================================
func GetMastersIP(master string) string {
	a := [] string{""}
	for i := 1; i < 10; i++ {
		currIp := "172.18.0." + strconv.Itoa(i)
		// fmt.Println("i=", i, "  LOOKUP ", currIp)

		a, _ = net.LookupAddr(currIp) //names []string, err error)
		if a != nil && len(a) > 0 {
			// fmt.Println("a[0]=", a[0])
			if strings.Contains(a[0], master) {
				// FOUND
				return currIp
				break;
			}
		}
	}
	return ""
}
//====================================================================================
//
//====================================================================================
func myfindUdpAddress(service string) {
	udpAddr, _ := net.ResolveUDPAddr("udp", service)

	fmt.Println("TB-SERVER found at ", udpAddr)
}
