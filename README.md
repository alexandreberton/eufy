# Jeedom Plugin for Eufy Cameras/Stations
Project fork of [alexandreberton/eufy](https://github.com/alexandreberton/eufy) 
<br>Uses [bropat/eufy-security-ws](https://github.com/bropat/eufy-security-ws) lib (docker prerequisite) 
<br>Installation instructions [here](https://community.jeedom.com/t/integration-de-materiel-eufy/76603)

## ChangeLog :
* v0.1 [lxrootard](https://github.com/lxrootard) 
<br> - Created separate equipement types for Station and Camera 
<br> - Added support for T8010 base, updated other devices types (T8113 OK, other types to be tested, 
see core/config/*.json)
<br> - Added support for Camera commands: activation, motionDetection, LED, antiTheft, refresh
<br> - Moved guard command to Station type, added IP, MAC address and status infos
<br> - Corrected guardMode update bug
* v0 [a.berton](https://github.com/alexandreberton)

#ToDo :
* Detailed Camera status (device.get_properties)
