<?php
namespace Stanford\CodeGen;

require_once "emLoggerTrait.php";

class CodeGen extends \ExternalModules\AbstractExternalModule {

    use emLoggerTrait;

    private $validChars;        // All valid characters
    private $validNums;
    private $validAlpha;

    private $codeLength;


	private $arrValidChars;
    private $lenValidChars;
    private $arrValidAlpha;
    private $lenValidAlpha;
    private $arrValidNums;
    private $lenValidNums;

    private $arrValidKeys;

    private $lastError;
    private $mask;
    private $checksumMethod;
    private $uniqueCodes;
    private $recursiveIterations = 0;

    // These only apply if not specified in EM settings
    const VALID_CHARS = "234689ACDEFHJKMNPRTVWXY";
    // const VALID_ALPHA =       "ACDEFHJKMNPRTVWXY";
    // const VALID_NUMS  = "234689";
    const DEFAULT_CODE_LEN = 6;
    const MAX_RECURSIVE_ITERATIONS = 10;    // Max number of unique collisions to happen before failing

    public function __construct() {
		parent::__construct();
		// Other code to run when object is instantiated
        if ($this->getProjectId() > 0) {
            $this->prepVars();
        }
	}

	public function getChecksumMethod() {
        return $this->checksumMethod;
    }

    public function getValidChars() {
        return $this->validChars;
    }


	/**
     * set all class vars
     */
    public function prepVars($codeLen = NULL, $mask = NULL, $valid_chars = NULL, $checksumMethod = NULL) {

        // Get the valid characters
        $this->validChars = $valid_chars;
        if (empty($valid_chars)) {
            $this->validChars = $this->getProjectSetting('allowable-chars');
            if (empty($this->validChars)) $this->validChars = self::VALID_CHARS;
        }
        $this->arrValidChars    = str_split($this->validChars);

        // Set the valid nums and alphas
        $this->arrValidNums = [];
        $this->arrValidAlpha = [];
        foreach ($this->arrValidChars as $k => $i) {
            if (is_numeric($i)) {
                $this->arrValidNums[] = $i;
            } else {
                $this->arrValidAlpha[] = $i;
            }
        }

        // Set the code length
        $this->codeLength = $codeLen;
        if (empty($this->codeLength)) $this->codeLength = $this->getProjectSetting('code-length');
        if (empty($this->codeLength)) $this->codeLength = self::DEFAULT_CODE_LEN;

        // mask using # for number, @ for alpha, . for any valid char, or the char it self
        $this->mask             = $mask;  // e.g. (V###@@@ for V123ABC)
        if (is_null($this->mask)) $this->mask = $this->getProjectSetting('mask');

        // Get a checksum method
        $this->checksumMethod = $checksumMethod;
        if (is_null($this->checksumMethod)) $this->checksumMethod = $this->getProjectSetting('checksum-method');


        // Arrays of characters and their indexes
        $this->lenValidChars    = count($this->arrValidChars);
        $this->lenValidAlpha    = count($this->arrValidAlpha);
        $this->lenValidNums     = count($this->arrValidNums);

        // A flipped array of valid chars leading to their index (with value min at 0)
        $this->arrValidKeys     = array_flip($this->arrValidChars);

        // store generated codes into a persistent store
        // (mem wont handle too large a value, and will need to carry over to subsequent runs) to squash dupes
		// $this->uniqueCodes       = $this->getAllUniqueCodes();
        // $this->emDebug($this);
	}


	/**
     * Randomly create a new code (needs to be verified against code database for uniqueness)
     * @return string
     */
    public function getCode() {
        $codebody   = $this->getRandomSeq($this->codeLength - 1);
        $checkdig   = $this->calcCheckDigit($codebody);
        $returncode = $codebody.$checkdig;

        if(in_array($returncode, $this->uniqueCodes)){
            // if exists, recurse, careful here... can't have too much recursion or take php down with "Maximum function nesting level of '256' reached" error
            if ($this->recursiveIterations > self::MAX_RECURSIVE_ITERATIONS) {
                return false;
            } else {
                $this->recursiveIterations++;
                $this->getCode();
            }
        }else{
            $this->recursiveIterations = 0;
			array_push($this->uniqueCodes, $returncode);
        }
        return $returncode;
    }


    public function insertCodes($codes) {
        // $q = $this->createQuery();
        // $q->add("insert into vera_direct_codes (code) values ");
        // foreach ($codes as $code) {
        //     $q->add('(?)')
        // }
        try {
            $arrVals = array_fill(0,count($codes),'(?)');
            $sql = 'insert into vera_direct_codes (code) values ' . implode(',', $arrVals) . " ON DUPLICATE KEY UPDATE code=code;";
            // $q = $this->framework->createQuery();
            // $q->add($sql, $codes);
            // $q->execute();
            // $this->emDebug(count($arrVals), $sql, $q);
            // return $q->affected_rows;
            $q = $this->query($sql, $codes);
            return true;

        } catch (\Exception $e) {
            $msg = $e->getMessage();
            $this->emDebug($msg);
            if (strpos($msg, "Duplicate")) {
                // Catch duplicate errors
                return false;
            }
            throw $e;
        }
    }


	/**
     * Generate $n unique codes
     * @return array
     */
	public function genCodes($n){
		$codes = [];
		for($i=0; $i < $n; $i++) {
		    $code = $this->getCode();
		    if ($code !== false) $codes[] = $code;
		}

		//$this->storeAllUniqueCodes();
		return $codes;
	    // return $this->uniqueCodes;
	}


    /**
     * Get a summary of what is in the database right now
     * @return array
     */
	public function getDbSummary() {
	    $this->prepVars();

	    $summary = [];
	    $q = $this->query('select count(*) from vera_direct_codes', []);
	    $row = db_fetch_array($q);
	    $summary['totalDbEntries'] = $row[0];

	    $summary['codeLength'] = $this->codeLength;
	    $summary['validChars'] = $this->validChars;
        $summary['mask'] = $this->mask;
        $summary['checksumMethod'] = $this->checksumMethod;
        $summary['space'] = $this->getSpace($this->codeLength, $this->mask);

  	    $q = $this->query('select code from vera_direct_codes order by id desc limit 5', []);
  	    $examples = [];
  	    while ($row = db_fetch_array($q) ) {
  	        $examples[] = $row['code'];
        };
        $summary['examples'] = implode(", ", $examples);

        return $summary;
    }

    /**
     * Wipe the database
     */
    public function deleteDb() {
	    $q = $this->query('TRUNCATE vera_direct_codes', []);
	    $q = $this->query('alter TABLE vera_direct_codes AUTO_INCREMENT = 1', []);
        return;
	}


    /**
     * Move from db table to project
     * Currently it ADDs to the existing records
     */
	public function addCodesToProject() {

	    $id_field = \REDCap::getRecordIdField();

	    // For auto-id
	    $q = $this->query('select record from redcap_data where project_id = ?',[
            $this->getProjectId()
        ]);
        $max = 0;
        while ($row=db_fetch_row($q)) {
            $max = max($max, $row[0]);
        }
        $start = $max;
        $this->emDebug("starting add at $start");


        $q = $this->query('select * from vera_direct_codes',[]);

        $data = [];
        $i = 0;
        while ($row = db_fetch_assoc($q)) {
            $max++;
            $data[] = [
                $id_field => $max,
                'code' => $row['code']
            ];
            $i++;

            if ($i === 1000) {
                // Save
                $result = \REDCap::saveData('json', json_encode($data));
                //$this->emDebug($result);
                $i = 0;
                $data = [];
            }
        }

        if ($i > 0) {
            // Save
            $this->emDebug('final Save');
            $result = \REDCap::saveData('json', json_encode($data));
            $this->emDebug($result);
        }

        return $max-$start;
    }


    /**
     * Validate a code's format
     * @param $code
     * @return bool
     */
    public function validateCodeFormat($code) {
        switch ($this->checksumMethod) {
            case "luhn":
                $result = $this->validateCodeFormatLuhn($code);
                break;
            case "mod":
                $result = $this->validateCodeFormatMod($code);
                break;
            default:
                $this->emError("Invalid validateCodeFormat method: " . $this->checksumMethod);
                $result = false;
        }
        return $result;

    }

    /**
     * Validate a code's format using mod
     * @param $code
     * @return bool
     */
    public function validateCodeFormatMod($code)
    {
        $numDigits = strlen($code);

        // Verify length
        if ($numDigits !== $this->codeLength) {
            $this->lastError = "Invalid Code Length";
            return false;
        }

        // Verify all characters are valid
        $arrChars = str_split($code);
        $invalidChars = array_diff($arrChars, $this->arrValidChars);
        if (!empty($invalidChars)) {
            $this->lastError = "Code contains invalid characters: " . implode(",",$invalidChars);
            return false;
        }

        // Break off the checksum digit
        list($payload, $checkDigit) = str_split($code, $numDigits - 1);

        // Get checksum from pre-code
        $actualCheckDigit = $this->calcCheckDigit($payload);
        if ($actualCheckDigit !== $checkDigit) {
            $this->lastError = "Invalid CheckDigit for $code = $payload + $actualCheckDigit";
            return false;
        }

        return true;
    }


    /**
     * Validate code format using luhn
     * @param $code
     * @return bool
     */
    public function validateCodeFormatLuhn($code) {
        $numDigits      = strlen($code);

        // Verify length
        if ($numDigits !== $this->codeLength) {
            $this->lastError = "Invalid Code Length";
            return false;
        }

        // Break off the checksum digit
        list($payload, $checkDigit) = str_split($code, $numDigits - 1);

        // Verify all characters are valid EXCEPT THE CHECKDIGIT
        $arrChars     = str_split($payload);
        $invalidChars = array_diff($arrChars, $this->arrValidChars);
        if (!empty($invalidChars)) {
            $this->lastError = "Code contains invalid characters: " . implode(",",$invalidChars);
            return false;
        }

        // Check Digit should = mod of result of algo
        $actualCheckDigit = $this->calcCheckDigit($payload);
        if ( !is_numeric($checkDigit) || (intval($actualCheckDigit) !== intval($checkDigit))) {
            $this->lastError = "Invalid CheckDigit for $code = $payload + $actualCheckDigit";
            return false;
        }

        return true;
    }


    /**
     * @param        $payload
     * @param string $method  luhn or mod
     */
    private function calcCheckDigit($payload) {
        switch ($this->checksumMethod) {
            case "luhn":
                $result = $this->calcCheckDigitLuhn($payload);
                break;
            case "mod":
                $result = $this->calcCheckDigitMod($payload);
                break;
            default:
                $this->emError("Invalid calcCheckDigit method: " . $this->checksumMethod);
                $result = false;
        }
        return $result;
    }


    /**
     * Calculate a checksum based on this Luhn method:
     * https://wiki.openmrs.org/display/docs/Check+Digit+Algorithm
     * @param $payload
     * @return string
     */
    private function calcCheckDigitLuhn($payload) {
        // Convert each character to base x and sum.
        $arrChars       = str_split($payload);

        // Verify all characters are valid
        $invalidChars   = array_diff($arrChars, $this->arrValidChars);
        if (!empty($invalidChars)) {
            $this->lastError = "Code contains invalid characters: " . implode(",",$invalidChars);
            return false;
        }

        // then reverse and go left to right
        $arrChars       = array_reverse($arrChars);

        // Luhn algo variation
        $checkSum       = 0;
        foreach ($arrChars as $i => $char) {
            // get Ascii Value - 48
            $prep_ord_digit = ord($char) - 48;

            // from "right to left" even positioned character values get weighting
            if($i%2 == 0){
                //this will effectively double the value for int (then digits are summed if value > 9, unless derived from alpha value then use as is)
                $weight = (2 * $prep_ord_digit) - floor($prep_ord_digit / 5) * 9;
            }else{
                //use ascii value even if > 10 (for alpha values)
                $weight = $prep_ord_digit;
            }
            $checkSum   += $weight;

            //$this->emDebug( ( $i%2 ? "even" : "odd" ) . "$char => $weight");
        }
        $checkSum   = abs($checkSum) + 10; //handle sum < 10 if characters < 0 are allowed
        $checkDigit = floor((10 - ($checkSum%10)) % 10); //check digit is amount needed to reach next number divisible by ten

        //$this->emDebug("$checkSum % 10 = $checkDigit");

        return $checkDigit; //this will return var type "double" so make sure to intval it before comparison
    }


    /**
     * Calculate a checksum based on this modulus method:
     * @param $payload
     * @return string
     */
    private function calcCheckDigitMod($payload) {
        // Convert each character to base x and sum.
        $arrChars = str_split($payload);

        // Verify all characters are valid
        // $invalidChars = array_diff($arrChars, $this->arrValidChars);
        // if (!empty($invalidChars)) {
        //     $this->lastError = "Code contains invalid characters: " . implode(",",$invalidChars);
        //     return false;
        // }

        $idxSum = 0;
        foreach ($arrChars as $i => $char) {
            if(isset($this->arrValidKeys[$char])) {
                $idxSum   = $idxSum + $this->arrValidKeys[$char];
            } else {
                // character is not valid - we will ignore it for checksum purposes
            }
            //$this->emDebug($i . " -- $char => " . $this->arrValidKeys[$char] . " [" . $idxSum . "]");
        }
        $mod = $idxSum % $this->lenValidChars;
        $checkDigit = $this->arrValidChars[$mod];

        //$this->emDebug("$idxSum mod {$this->lenValidChars} = $mod which corresponds to $checkDigit");
        return $checkDigit;
    }


    /**
     * Generate a random code of length specified
     * @param $len
     * @return string
     */
    private function getRandomSeq($len) {
        $r = [];
        for ($i = 0; $i < $len; $i++) {
            $type = substr($this->mask,$i,1);
            switch($type) {
                case ".":
                case false:
                    $r[] = $this->arrValidChars[rand(0, $this->lenValidChars - 1)];
                    break;
                case "@":
                    $r[] = $this->arrValidAlpha[rand(0, $this->lenValidAlpha - 1)];
                    break;
                case "#":
                    $r[] = $this->arrValidNums[rand(0, $this->lenValidNums - 1)];
                    break;
                default:
                    $r[] = $type;
            }
           // $r[] = $this->arrValidChars[rand(0, $this->lenValidChars - 1)];
        }
        return implode("", $r);
    }


    /**
     * Return the number of codes available in this space
     * @return string
     */
    public function getSpace($len, $mask) {
        $s = 1;
        for ($i = 0; $i < $len - 1; $i++) {
            $type = substr($mask,$i,1);
            switch($type) {
                case ".":
                case false:
                    $s = $s * $this->lenValidChars;
                    break;
                case "@":
                    $s = $s * $this->lenValidAlpha;
                    break;
                case "#":
                    $s = $s * $this->lenValidNums;
                    break;
                default:
                    $s = $s * 1;
            }
        }

        // Take off for the checksum and see the total space
        // $this->emDebug("A code of [$len] characters (" . ( intval($len)-1 ) . ") without the check digit, and a mask [" .
        //     $mask . "] has unique space of " . number_format($s));
        return $s;
    }

}
