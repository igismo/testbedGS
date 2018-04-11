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
## All these a copy from www
GOOS=linux go build -a  -o mysqlmaster .
#docker run -w /app mysql/mysql-server:latest sh \
#           -c 'CGO_ENABLED=0 go build -a --installsuffix cgo --ldflags="-s" -o mysqlmaster'
docker build -t mysqlmaster -f Dockerfile .

