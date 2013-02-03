# dateFinder

Given a string, dateFinder will try to extract a valid date from it. It returns an array containing a year, month and day corresponding with the first date it finds. The array will optionally contain a "range" key if it was unable to determine an exact date from the input string (ie: it could only extract a year, month or both).

### Example

“1969” - returns (1969, 1, 1, true)
“Dec ‘69” - returns (1969, 12, 1, true)
“7th December, 1969” - returns (1969, 12, 7)
“64th December, 1969” - returns false

### Usage

`$df = new dateFinder('December 7th, 1969');
var_dump($df->parsedDate);`

### Unit Tests

`$df = new dateFinder();
$df->unitTests();`
