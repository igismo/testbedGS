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
#// NAME              REV  DATE       REMARKS			@
#// Goran Scuric      1.0  01012018  Initial design     goran@usa.net
#//================================================================================
GOOS=linux go build -a  -o officeMaster .
docker build -t officemaster -f Dockerfile.scratch .
docker network create TB-NETWORK
docker rm TB-OFFICEMASTER
## first we creted the volume with docker volume create Tblog
## can examine by: docker run -v TBlog:/log busybox ls /log
## the tbConfig was: var TBlogPath = "/log/"
##docker run  -v TBlog:/log  --network TB-NETWORK --name TB-OFFICEMGR -it officemgr:latest
docker run  -v /Users/scuric/go/testbedGS/testbed/log:/log  \
            -v /Users/scuric/go/testbedGS/testbed/proj:/proj \
            -v /Users/scuric/go/testbedGS/wwwServer/testbed/www:/var/www \
            -v /Users/scuric/go/testbedGS/testbed:/testbed \
            --cap-add ALL \
            --privileged=true \
            --network TB-NETWORK --name TB-OFFICEMASTER -it officemaster:latest
