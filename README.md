
# MRN LookUp

The MRN LookUp EM will override the Add new record button for a REDCap project. A new button named Add a new MRN will replace the button. When selected, a modal will open where users can enter an MRN and select the Verify MRN button.

The MRN will be verified using the Identifier API call to STARR.  Once verified, the following information will be retrieved and can be stored in the record:
```
    * first name
    * last name
    * date of birth
    * gender
    * race
    * ethnicity
```
# Configuration File
The user can specify which fields they want stored in their project in the config file.  If a project field is not selected for a piece of data, it will not be stored in the project

A record label prefix can be specified in the configuration file.  This prefix will be used with a number appended to the end for the record ID. The number size can be defined in the configuation file so record names can be uniform (i.e. STUDY-00001, STUDY-00002, etc.)

# Dependencies
This EM depends on vertx Token Manager and EM Logger.
