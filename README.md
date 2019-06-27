
# MRN LookUp

The MRN LookUp EM will override the Add new record button for a REDCap project. A new button names Add a new MRN will replace the button. When selected, a modal will open where users can enter an MRN and select the Verify MRN button.

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
The user can specify which fields that want stored in their project from the config file.  If a project field is not selected for that piece of data, it will not be stored in the project

A record label prefix can be specified in the configuration file.  This prefix will be used with a number appended to the end for the record ID.

# Dependencies
This EM depends on vertx Token Manager and EM Logger.
