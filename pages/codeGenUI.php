<?php
namespace Stanford\CodeGen;
/** @var CodeGen $module */

if(!empty($_POST["action"])){
    $action = $_POST["action"];
    switch($action){
        case "genCodes":
            $n                  = filter_var($_POST["numcodes"], FILTER_SANITIZE_NUMBER_INT);
            $validChars         = "234689ACDEFHJKMNPRTVWXY"; //23 characters are valid
            $codeLen            =  6; //Length of code to be created
            $mask               = "VVVVV."; //@@###";
            $module->prepVars($codeLen, $mask);
            $result = $module->genCodes($n);
            $module->getSpace($codeLen, $mask);
        break;

        default:
        break;
    }

    echo json_encode($result);
    exit;
}

$loading    = $module->getUrl("pages/img/icon_loading.gif");
$loaded     = $module->getUrl("pages/img/icon_loaded.png");
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

    #codeCheck{
        display: inline-block;
        width: auto;
        vertical-align: top;
        margin-right:10px
    }

    .good input {
        color:green;
        font-weight:bold;
    }
    .bad input {
        color:red;
        font-weight:bold;
    }
</style>

<div style='margin:20px 40px 0 0;'>
    <h4>Generate Unique Vera Codes:</h4>
    <textarea id="copyveras"><?= implode(", ",$codes) ?></textarea>
    <p class="form-group">
        <label id="howmany"><b>How Many</b> <input type='number' id='numcodes' min='100' max='10000' step='100'/></label> <button id='generate' class="button btn btn-primary">Generate and Copy to Clipboard</button>
    </p>

    <br><br>

    <h4>Validate Code</h4>
    <div class="form-group">
        <input type="text" class="form-control" id="codeCheck" placeholder="e.g. VE4YPM7"> <button id="checkCode" class="button btn btn-info">Check Code Valid</button>
    </div>
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
            $("#copyveras").text(result.join(", "));
            $("#copyveras").trigger("click");
        }).fail(function () {
            console.log("something failed");
        });
    });

    $("#checkCode").click(function(){
        $(this).parent().removeClass("good").removeClass("bad");
        var code = $("#codeCheck").val();

        if(validateCodeFormat(code)){
            $(this).parent().addClass("good");
        }else{
            $(this).parent().addClass("bad");
        }
    });
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
