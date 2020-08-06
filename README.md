# CodeGen

## Generate Unique Codes for Vera Project

* 8 character code length (total including CheckDigit)
* First Character Must be "V"
* Code portion can only consist of "234689ACDEFHJKMNPRTVWXY"
* Use <a href='https://wiki.openmrs.org/display/docs/Check+Digit+Algorithm'>Luhn algo variation</a>
* Need to store (if generating many, will need persistant store, in case script times out) created codes, to squash dupes


https://veracovidtest.com/kit/register?code=VR3TYF36
https://veracovidtest.com/kit/register?code=VPEEA6A1
https://veracovidtest.com/kit/register?code=VHKVA8X6
https://veracovidtest.com/kit/register?code=VFJ46K49
https://veracovidtest.com/kit/register?code=VDKJ4R94
https://veracovidtest.com/kit/register?code=V96EX4H3
https://veracovidtest.com/kit/register?code=V9CVY846
https://veracovidtest.com/kit/register?code=VKC6A3P6
https://veracovidtest.com/kit/register?code=VKH4NDD7
https://veracovidtest.com/kit/register?code=VK44EE87