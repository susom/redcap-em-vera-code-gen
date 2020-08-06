<?php
namespace Stanford\CodeGen;

require_once "emLoggerTrait.php";

class CodeGen extends \ExternalModules\AbstractExternalModule {

    use emLoggerTrait;

	private $arrValidChars;
    private $lenValidChars;
    private $codeLength;
    private $lastError;
    private $reqPrefix;
    private $uniqueCodes;

	const VERBOSE = false;

    public function __construct() {
		parent::__construct();
		// Other code to run when object is instantiated
	}
	
	/**
     * set all class vars
     * @return null
     */
    public function prepVars($codeLen = 7, $valid_chars = "234689ACDEFHJKMNPRTVWXY", $required_prefix = null) {
        $this->validChars       = $valid_chars;

        // The size of the valid chars array
        $this->lenValidChars    = strlen($valid_chars);

        // An array of valid characters to use, with index starting at 0
        $this->arrValidChars    = str_split($valid_chars);

        // A flipped array of valid chars leading to their index (with value min at 0)
        $this->arrValidKeys     = array_flip($this->arrValidChars);

        // The length of the codes for this codeGen (this includes a checksum)
        $this->codeLength       = $codeLen;

        //required prefix, will taked up one character space
        $this->reqPrefix        = $required_prefix;    

        // store generated codes into persistant store (mem wont handle too large a value, and will need to carry over to subsequent runs) to squash dupes
		$this->uniqueCodes       = $this->getAllUniqueCodes();
		// $this->emDebug("onload", $this->uniqueCodes);
	}


	/**
     * Randomly create a new code (needs to be verified against code database for uniqueness)
     * @return string
     */
    public function getCode() {
        $codebody   = $this->getRandomSeq($this->codeLength-2);
        $prefix     = $this->reqPrefix;
        $newcode    = $prefix.$codebody;
        $checkdig   = $this->calcCheckDigit($newcode);
        $returncode = $newcode.$checkdig;

        if(in_array($returncode, $this->uniqueCodes)){
            // if exists, recurse, careful here... can't have too much recursion or take php down with "Maximum function nesting level of '256' reached" error
            $this->getCode();
        }else{
			array_push($this->uniqueCodes, $returncode);
        }

        return $returncode;
    }

	/**
     * Generate $n unique codes
     * @return array
     */
	public function genCodes($n){
		$codes = [];
		for($i=0; $i < $n; $i++) {
			$codes[] = $this->getCode();
		}

		$this->storeAllUniqueCodes();
		return $codes;
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
     * Return the number of codes available in this space
     * @return string
     */
    public function getSpace() {
        // Take off for the checksum and see the total space
        $size = pow($this->lenValidChars, $this->codeLength-1);
        return "A code of [$this->codeLength] characters including a check digit has space of " . number_format($size) . "<br>";
    }


    /**
     * Calculate the modulus of the code using the base of the number of characters present
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

            if (self::VERBOSE){
                print_r( ($i%2 ? "even" : "odd" ) .  " : $char => $weight" .  "<br>");
            }
        }
        $checkSum   = abs($checkSum) + 10; //handle sum < 10 if characters < 0 are allowed
        $checkDigit = floor((10 - ($checkSum%10)) % 10); //check digit is amount needed to reach next number divisible by ten

        if (self::VERBOSE){
            print_r("$checkSum % 10 = $checkDigit <br>");
        }

        return $checkDigit; //this will return var type "double" so make sure to intval it before comparison
    }

    /**
     * Generate a random code of length specified
     * @param $len
     * @return string
     */
    private function getRandomSeq($len) {
        $r = [];
        for ($i = 0; $i < $len; $i++) {
            $r[] = $this->arrValidChars[rand(0, $this->lenValidChars - 1)];
        }
        return implode("", $r);
    }
}
