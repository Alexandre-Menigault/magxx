# Magproc

## Config

### Dependencies

* Apache2
* PHP 7.1

### Files to change

* ### php.ini

    ``` ini
    max_execution_time = 0
    ```

---

## **Routes**

---

### **GET /api/data/\<obs\>/\<date\>/\<type\>/?interval=\<interval\>**

``` txt
    Retrieve data of an observatory within a date interval

    <obs>      => The observatory short code
    <date>     => The start date where the data should be read (Teno)
    <type>     => The type of file to get, eg. raw, log, env
    <interval> => The interval to search for
                  1 digit + 1 letter from d (day), h (hour), eg. 1d ( one day interval )
                  Defaults to 1d

    Returns an JSON object
        {
            [0] => {
                "header": [<every header of the file>],
                "type: "<One of: raw, log, env>"
                {must be removed}<if type is raw>: "colors": [ <colors of the data plots> ]
            },
            [*] => [<data of each header, match the number of headers>]
        }
```

### **GET POST /api/upload-csv**

``` txt
    Uploads a data file to the observatory upstore.
    The filename must be formated as:
            // TODO: change format to OBSX-teno-type.csv
        <OBS><Teno>-<type>.csv
    Once uploaded the file is named:
        <OBS>-<Teno>-<type>.csv

    <OBS>      => The observatory short code
    <Teno>     => The start date where the data should be read (Teno)
    <type>     => The type of file to get, eg. raw, log, env

    On success, returns a JSON object
        {
            "saved": true,
            "md5": <hash of the file>,
            "size": <Size of the file in bytes>,
            "imported": <the number of lines in the file minus the header>
        }

    On error, returns a JSON object
        {
            "saved": false,
            "error": {
                "message": <error_message>,
                "trace": [ <stack_trace_list> ]
            }
        }
```

### **GET /api/observatories**

GET the list of all observatories short codes

Returns a JSON array

``` json
    [
        "<Observatory1 code >",
        "<Observatory2 code >"
        "..."
    ]
```

### **GET /api/users**

Get the list of all users

Returns a JSON array

``` json
    [
        "<user1>",
        "<user2>",
        "..."
    ]
```

### **POST /api/measure**

Uploads a new absolute measurement

Expected input (JSON object)

``` json
     {
         "date": "<YYYY-MM-DD>",
         "obs": "<Obs short code>",
         "observer": "<observer short name>",
         "measurementA": {
             "declination": "<number>"
             "inclination": "<number>"
             "residues": [
                 {
                     "time": "<hh:mm:ss>",
                     "value": "<number>"
                 },
                 {
                     "<8 values>": ""
                 }
             ],
             "sighting": [
                 "<number>",
                 "<number>",
                 "<number>",
                 "<number>"
             ]
         },
         "measurementB": "<same as measurementA>",
         "pillarMeasurement": [
            {
                "time": "<hh:mm:ss>",
                "value": "<number>"
            },
            {
                "<6 values>": ""
            }
         ]
     }

```

### **GET /api/teno/utc?teno=\<teno\>**

Convert \<teno\> to UTC

Returns a JSON object

``` json
{
    "teno" : 0,
    "yyyy" : 2000,
    "mmmm" : 1,
    "dddd" : 1,
    "hh" : 0,
    "mm" : 0,
    "ss" : 0,
}
```

### **GET /api/utc/teno?year=2000&month=1&day=1&hour=0&minutes=0&seconds=0**

Convert UTC to teno

Returns a JSON object

``` json
{
    "teno" : 0,
    "yyyy" : 2000,
    "mmmm" : 1,
    "dddd" : 1,
    "hh" : 0,
    "mm" : 0,
    "ss" : 0,
}
```
