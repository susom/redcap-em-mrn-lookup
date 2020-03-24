
# MRN LookUp

The MRN LookUp EM will override the Add new record button for a REDCap project. A new button named 'Add a new MRN' will replace 'Add a new record' on the Record Status Dashboard and Add/Edit Record page. 

When the 'Add a new MRN' button is selected, a modal will open where users can enter an MRN and select the Verify MRN button. 
The MRN will be verified using the Identifier API call to STARR.  Once verified, the following information will be retrieved and can be stored in the record:
```
    * first name
    * last name
    * date of birth
    * gender
    * race
    * ethnicity
```
The approved privacy attestation must include any of these fields which are selected from the EM Config file.

# Configuration File
The user can specify which fields they want stored in their project in the config file.  
If the project attestation is not approved for a specified field, it will not be stored in the project

A record label prefix can be specified in the configuration file.  
This prefix will be used with a number appended to the end for the record ID. The number size can be defined 
in the configuation file so record names can be uniform (i.e. STUDY-00001, STUDY-00002, etc.)

# Dependencies
This EM depends on the vertx Token Manager EM, IRB Lookup EM and EM Logger. To use this EM, a valid IRB number must be added to
the REDCap Project Setup page and a Privacy Attestation must be filled out and approved by Privacy.  The approved Privacy 
attestation must include MRN number, first and last name and date of birth.  This information is displayed to the user to
be able to verify the MRN is correct. In addition to the required fields for display, any demographic data that is selected
to be saved in the project, must be privacy approved.

