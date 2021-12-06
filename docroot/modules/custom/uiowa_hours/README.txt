*** RecServ API ***
This module provides a resource to get facility and area data.

** Resources **

Locations (node) - The location (Facility or Area node).
Endpoint: api/v1.0/locations

** Example Queries **
API discovery.
GET https://recserv.uiowa.edu.dd:8084/api

Get all locations.
GET https://recserv.uiowa.edu.dd:8084/api/v2.0/locations

Get one location, with hours for a specific day.
GET https://recserv.uiowa.edu.dd:8084/api/v2.0/locations
http://recserv.uiowa.edu.dd:8083/api/v1.0/locations/1191?date=05/15/2017

Documentation about locations call.
OPTIONS https://recserv.uiowa.edu.dd:8084/api/v2.0/locations
We can learn the allowed values and labels of the `type` property.
