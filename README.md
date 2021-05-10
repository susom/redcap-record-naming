# Automated Record Naming
This external module automates the naming of REDCap records. The current use of this EM is to 
support the creation of record names when DAGs are used. The generic form of a record name with DAGs
is
>    {Dag ID}-{increasing number}  (i.e. 4702-1, 4702-2, etc.)

This EM will support left padding the record number portion of the name, such as:
>    {DAG ID}-{left-padded increasing number}   (i.e. 4702-001, 4702-002, etc.)

Another option which is supported with this EM is to use the DAG name instead of the DAG ID.  For instance,
if DAG ID 4702 corresponds to the 'Stanford' DAG, the record name created would be:
>     Stanford-{increasing number)  (i.e. Stanford-1, Stanford-2, etc.)
>                               or
>     Stanford-{left-padded increasing number)   (i.e. Stanford-001, Stanford-002, etc.)

This EM overrides the +Add a new record button on the Dashboard page and on the Add/Edit Records page.  When this
EM is active, the Auto-numbering option on the Project Setup page will be disabled and there will be a note to say
'Record Naming EM is active'.


## Note about simultaneous record creation
If two users click 'add record' at the same time, they both will be opened to new records with the same proposed ID
 (e.g. `STAN-001`).  When the first user presses 'save', they will get this ID.  When the second user presses save,
  they will be assigned to the next numerical auto-numbering id possible (e.g. 14).  So, if you see a numerical ID
  created that does not match your desired sequence, this is likely what happened.


# Future Enhancements
Proposed future enhancements **(in no particular order)** are

* Add the ability to use some portion of a date string in the name (i.e. Stanford-2020-1, Stanford-2020-2, etc.)

* Add the ability to reset the increasing number when the date portion cycles. Some examples are:
        Stanford-2020-1, Stanford-2020-2, ...., Stanford-2021-1 ...
                                or
        Study-2020-01-1, Study-2020-01-2, ..., Study-2020-02-1, Study-2020-02-2, ...
        
* Add the ability to support public surveys when they are the first instrument in the project

* Add support for naming arms when used in the project.

## Change Log

* 2021-05-04 Updated the code to use the new reserveRecordId method