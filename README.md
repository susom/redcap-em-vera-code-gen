# CodeGen

## Generate Unique Codes for Vera Project

* 8 character code length (total including CheckDigit)
* First Character Must be "V"
* Code portion can only consist of "234689ACDEFHJKMNPRTVWXY"
* Use <a href='https://wiki.openmrs.org/display/docs/Check+Digit+Algorithm'>Luhn algo variation</a>
* Need to store (if generating many, will need persistant store, in case script times out) created codes, to squash dupes

