# testbedGS
Runtime structures and modular distributed component architecture providing infrastructure and platform to build testbeds

The "system" consists of many compenents (or modules if you wish). To participate in the game they all need to register 
with the officeMaster first, and then participate in the keep alive exchange ...

At the moment the following four modules provide for a Emulab/Deter like experiment creation:
- officeMastert
- wwwMaster
- mysqlMaster
- expMaster

Each of them canbe started as a docker container for now, though the intention was to allow any combination of 
physical, VM and container mixture.

Each directory contains tha BUILD and RUN scripts 
It is not important in which order they are started ... eventaully they synchronize with officeMaster and learn 
IP addresses of other modules required by each of them to be fully functional ..

More later
