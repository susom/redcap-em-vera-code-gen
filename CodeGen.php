<?php
namespace Stanford\CodeGen;

require_once "emLoggerTrait.php";

class CodeGen extends \ExternalModules\AbstractExternalModule {

    use emLoggerTrait;

    private $validChars;        // All valid characters
	private $arrValidChars;
    private $lenValidChars;
    private $arrValidAlpha;
    private $lenValidAlpha;
    private $arrValidNums;
    private $lenValidNums;

    private $arrValidKeys;

    private $codeLength;
    private $lastError;
    private $prefix;
    private $mask;
    private $uniqueCodes;

    const VALID_CHARS = "234689ACDEFHJKMNPRTVWXY";
    const VALID_ALPHA =       "ACDEFHJKMNPRTVWXY";
    const VALID_NUMS  = "234689";
    const DEFAULT_CODE_LEN = 7;
    const MAX_RECURSIVE_ITERATIONS = 10;    // Max number of unique collisions to happen before failing

    private $recursiveIterations = 0;

    public function __construct() {
		parent::__construct();
		// Other code to run when object is instantiated
	}

	/**
     * set all class vars
     */
    public function prepVars($codeLen = self::DEFAULT_CODE_LEN, $mask = "", $valid_chars = self::VALID_CHARS, $valid_alpha = self::VALID_ALPHA, $valid_nums = self::VALID_NUMS) {
        // The length of the codes for this codeGen (this includes a checksum)
        $this->codeLength       = $codeLen;

        // Valid global characters
        $this->validChars       = $valid_chars;

        // Arrays of characters and their indexes
        $this->lenValidChars    = strlen($valid_chars);
        $this->arrValidChars    = str_split($valid_chars);

        $this->lenValidAlpha    = strlen($valid_alpha);
        $this->arrValidAlpha    = str_split($valid_alpha);

        $this->lenValidNums     = strlen($valid_nums);
        $this->arrValidNums     = str_split($valid_nums);

        // A flipped array of valid chars leading to their index (with value min at 0)
        $this->arrValidKeys     = array_flip($this->arrValidChars);

        // mask using # for number, @ for alpha, . for any valid char, or the char it self
        $this->mask             = $mask;  // e.g. (V###@@@ for V123ABC)

        // store generated codes into a persistent store
        // (mem wont handle too large a value, and will need to carry over to subsequent runs) to squash dupes
		$this->uniqueCodes       = $this->getAllUniqueCodes();

	}


	/**
     * Randomly create a new code (needs to be verified against code database for uniqueness)
     * @return string
     */
    public function getCode() {
        $mask       = $this->mask;
        $codebody   = $this->getRandomSeq($this->codeLength - 1, $mask);
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

	/**
     * Generate $n unique codes
     * @return array
     */
	public function genCodes($n){
		// $codes = [];
		for($i=0; $i < $n; $i++) {
		    $code = $this->getCode();
		    // if ($code !== false) $codes[] = $code;
		}

		//$this->storeAllUniqueCodes();
		//return $codes;
	    return $this->uniqueCodes;
	}


    /**
     * Get all  Unique Codes from REDCAP DB
     * @return array
     */
	public function getAllUniqueCodes(){
		// TODO CHANGE THIS TO GET FROM REDCAP TABLE "vera_direct_codes"
        if( empty($this->getProjectSetting("unique-codes")) ){
            return json_decode($this->getProjectSetting("unique-codes"),1);
        }else{
            return array();
        }
    }

    /**
     * Store Unique Codes in REDCAP DB
     * @return null
     */
	public function storeAllUniqueCodes(){
		// TODO CHANGE THIS TO STORE IN REDCAP TABLE "vera_direct_codes"
        $this->setProjectSetting("unique-codes", json_encode($this->uniqueCodes));
        return;
    }

    /**
     * Validate a code's format
     * @param $code
     * @return bool
     */
    public function validateCodeFormat($code) {
        $numDigits      = strlen($code);

        // Verify length
        if ($numDigits !== $this->codeLength) {
            $this->lastError = "Invalid Code Length";
            return false;
        }

        // Verify all characters are valid EXCEPT THE CHECKDIGIT
        $arrChars       = str_split($code);
        array_pop($arrChars);
        $invalidChars   = array_diff($arrChars, $this->arrValidChars);
        if (!empty($invalidChars)) {
            $this->lastError = "Code contains invalid characters: " . implode(",",$invalidChars);
            return false;
        }

        // Break off the checksum digit
        list($payload, $checkDigit) = str_split($code, $numDigits - 1);

        // Check Digit should = mod of result of algo
        $actualCheckDigit = $this->calcCheckDigit($payload);
        if (intval($actualCheckDigit) !== intval($checkDigit)) {
            $this->lastError = "Invalid CheckDigit for $code = $payload + $actualCheckDigit";
            return false;
        }

        return true;
    }

    /**
     * Calculate a checksum based on this method:
     * https://wiki.openmrs.org/display/docs/Check+Digit+Algorithm
     * @param $payload
     * @return string
     */
    private function calcCheckDigit($payload) {
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
     * Generate a random code of length specified
     * @param $len
     * @return string
     */
    private function getRandomSeq($len, $mask = "") {
        $r = [];
        for ($i = 0; $i < $len; $i++) {
            $type = substr($mask,$i,1);
            switch($type) {
                case ".":
                case false:
                    $r[] = $this->arrValidChars[rand(0, $this->lenValidChars - 1)];
                    break;
                case "@":
                    $r[] = self::VALID_ALPHA[rand(0, strlen(self::VALID_ALPHA) - 1)];
                    break;
                case "#":
                    $r[] = self::VALID_NUMS[rand(0, strlen(self::VALID_NUMS) - 1)];
                    break;
                default:
                    $r[] = $type;
            }
//            $r[] = $this->arrValidChars[rand(0, $this->lenValidChars - 1)];
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
        //        $size = pow($this->lenValidChars, $this->codeLength-1);
        //        return "A code of [$this->codeLength] characters including a check digit has space of " . number_format($size) . "<br>";
        $this->emDebug("A code of [$len] characters (" . ( intval($len)-1 ) . ") without the check digit, and a mask [" .
            $mask . "] has unique space of " . number_format($s));
    }




}
