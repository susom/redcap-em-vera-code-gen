<?php
namespace Stanford\CodeGen;
/** @var \Stanford\ProjCaFacts\CodeGen $module */

if(!empty($_POST["action"])){
    $action = $_POST["action"];
    switch($action){
        case "genCodes":
            $n                  = $_POST["numcodes"];
            $validChars         = "234689ACDEFHJKMNPRTVWXY"; //23 characters are valid
            $codeLen            = 8; //Length of code to be created
            $required_prefix    = "V";
            $module->prepVars($codeLen, $validChars, $required_prefix);

            $result = $module->genCodes($n);
        break;

        default:
        break;
    }

    echo json_encode($result);
    exit;
}

require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
?>
<style>
    #copyveras{
        width:100%;
        height:200px;
        resize: vertical;
    }
    #generate {
        vertical-align:top;
    }
    #howmany{
        vertical-align:top: 
    }
    #howmany b,
    #howmany input{
        display:inline-block;
        margin-right:10px; 
    }
    #howmany input{
        height: calc(1.5em + .75rem + 2px);
        padding: .375rem .75rem;
        font-size: 1rem;
        font-weight: 400;
        line-height: 1.5;
        color: #495057;
        background-color: #fff;
        background-clip: padding-box;
        border: 1px solid #ced4da;
        border-radius: .25rem;
        transition: border-color .15s ease-in-out,box-shadow .15s ease-in-out;
    }
</style>

<div style='margin:20px 40px 0 0;'>
    <h4>Generate Unique Vera Codes:</h4>
    <textarea id="copyveras"><?= implode(", ",$codes) ?></textarea>
    <p>
        <label id="howmany"><b>How Many</b> <input type='number' id='numcodes' min='100' max='10000' step='100'/></label> <button id='generate' class="button btn btn-primary">Generate and Copy to Clipboard</button>
    </p>
</div>
<script>
$(document).ready(function(){
    $("#copyveras").click(function(){
        $(this).select();
        document.execCommand('copy');
    });

    $("#generate").click(function(){
        var numcodes = $("#numcodes").val();

        $.ajax({
            method: 'POST',
            data: {
                    "action"    : "genCodes",
                    "numcodes"  : numcodes
            },
            dataType: "json"
        }).done(function (result) {
            console.log(result);
            $("#copyveras").text(result.join(", "));
            $("#copyveras").trigger("click");
        }).fail(function () {
            console.log("something failed");
        });
    });

    // test validateCodeFormat function
    var code        = "4689WXY5";
    var checkDigit  = validateCodeFormat(code);
    console.log(code, "is valid?", checkDigit);
});
function validateCodeFormat(code) {
    var validChars  = "234689ACDEFHJKMNPRTVWXY";
    code            = code.toUpperCase().trim().split("").reverse(); //prep code for luhn algo UPPERCASe, TRIM , REVERSE

    // will match this with result of Luhn algo below, and remove from code array
    var verifyDigit = code.shift(); 
    var checkSum    = 0;

    // make sure code portion consists of valid chars
    // TODO, double check browser requirements may need to rewrite in older JS for browser compatability
    var checkvalid  = code.filter(char => validChars.indexOf(char) == -1);
    if(checkvalid.length){
        console.log("Invalid Character(s) in Code");
        return false;
    }

    // apply algo to code reversed "right to left"
    for (var i in code) {
        var char = code[i];
        var prep_ord_digit = char.charCodeAt(0) - 48;

        var weight;
        if (i % 2 == 0) {
        weight = (2 * prep_ord_digit) - parseInt(prep_ord_digit / 5) * 9;
        } else {
        weight = prep_ord_digit;
        }
        checkSum += weight;
    }

    checkSum        = Math.abs(checkSum) + 10;
    var checkDigit  = (10 - (checkSum % 10)) % 10;

    return checkDigit == verifyDigit;
}
</script>