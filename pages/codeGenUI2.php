<?php
namespace Stanford\CodeGen;
/** @var CodeGen $module */


if(!empty($_POST["action"])){
    $action = $_POST["action"];
    switch($action){
        case "genCodes":
            $n                  = filter_var($_POST["numcodes"], FILTER_SANITIZE_NUMBER_INT);
            $result = $module->genCodes($n);
        break;

        case "genDbCodes":
            $n                  = filter_var($_POST["numcodes"], FILTER_SANITIZE_NUMBER_INT);
            $codes = $module->genCodes($n);
            if (count($codes) < $n) {
                $module->emDebug("Asked for $n - got back " . count($codes) . "uniques!");
            }

            $insert = $module->insertCodes($codes);
            // $module->emDebug("Insert Results", $insert);

            $result = $insert;
        break;

        case "getSummary":
            $result = $module->getDbSummary();
        break;

        case "deleteDb":
            $result = $module->deleteDb();
        break;

                case "addToProject":
            $result = $module->addCodesToProject();
        break;


        default:
        break;
    }

    echo json_encode($result);
    exit;
}

$loading    = $module->getUrl("pages/img/icon_loading.gif");
$loaded     = $module->getUrl("pages/img/icon_loaded.png");

$summary = $module->getDbSummary();

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


<div class="pt-3 pb-5">
    <h4>Summary:</h4>
    <pre id="summary"></pre>
    <div class="form-group">
        <button id="updateSummary" class="button btn btn-info">Update Summary</button>
        <p class="form-group">
            <label><b>Generate Codes until database code number is </b> <input type='number' id='db_size'/></label>
            <label><b>in batches of</b> <input type='number' id='batch_size' /></label>
            <button id='growDb' class="button btn btn-primary">Start</button>
            <button id='stopGrowDb' class="button btn btn-primary">Stop</button>
        </p>
    </div>
    <br><br>
    <div class="form-group">
        <button id="deleteDb" class="button btn btn-danger">Delete the Database</button>
        <button id="addToProject" class="button btn btn-success">Copy DB to Project</button>
    </div>

    <h4>Validate Code</h4>
    <div class="form-group">
        <input type="text" class="form-control" id="codeCheck" placeholder="e.g. VE4YPM7"> <button id="checkCode" class="button btn btn-info">Check Code Valid</button>
    </div>
</div>
<script type="text/javascript">

    VCG = {
        currentSize: 0,
        growing: false,
        duplicateCount: 0,
        checksumMethod: <?php echo json_encode($module->getChecksumMethod()); ?>,
        validChars: <?php echo json_encode($module->getValidChars()); ?>,

        validateCode: function(code) {
        }
    };


$(document).ready(function(){
    $("#copyveras").click(function(){
        $(this).select();
        document.execCommand('copy');
    });

    $('#deleteDb').click(function() {
        console.log('Deleting DB');
        $.ajax({
            method: 'POST',
            data: {
                "action"    : "deleteDb"
            },
            dataType: "json"
        }).done(function (result) {
            $("#updateSummary").trigger('click');
        }).fail(function () {
            console.log("something failed");
        });

    });

    $('#addToProject').click(function() {
        console.log('Adding DB To Project');
        $.ajax({
            method: 'POST',
            data: {
                "action"    : "addToProject"
            },
            dataType: "json"
        }).done(function (result) {
            console.log('added to project', result);
            $("#summarySummary").trigger('click');
            alert(result + ' records Added to Project');
        }).fail(function () {
            console.log("something failed");
        });

    });


    $('#updateSummary').click(function() {
        console.log('Updating summary');
        $.ajax({
            method: 'POST',
            data: {
                "action"    : "getSummary"
            },
            dataType: "json"
        }).done(function (result) {
            VCG.currentSize = result["totalDbEntries"];
            $("#summary").text(JSON.stringify(result, null, 2));
            console.log(VCG);
            if (VCG.growing) $('#growDb').trigger('click');
        }).fail(function () {
            console.log("something failed");
        });

    });

    $("#stopGrowDb").click(function() {
        VCG.growing = false;
    });

    $("#growDb").click(function(){
        var batchSize = $("#batch_size").val();
        var targetSize = $("#db_size").val();
        var toGo = targetSize - VCG.currentSize;
        var nextBatch = batchSize;



        if (toGo <= 0) {
            console.log('Reached Target!');
            VCG.growing = false;
            VCG.duplicateCount = 0;
            return;
        }

        if (VCG.duplicateCount > 5) {
            alert('Too many duplicates!  Try inserting in smaller chunks');
            VCG.duplicateCount = 0;
            VCG.growing = false;
            return;
        }


        // Shrink batch if approaching target
        if(toGo < batchSize) {
            nextBatch = toGo;
            console.log('Shrinking nextBatch to ' + nextBatch);
        }

        console.log(batchSize,targetSize,nextBatch);
        VCG.growing = true;

        $.ajax({
            method: 'POST',
            data: {
                "action"    : "genDbCodes",
                "numcodes"  : nextBatch
            },
            dataType: "json"
        }).done(function (result) {
            console.log('Just made some!', result);
            if(result === false) {
                console.log('Got a duplicate!');
                VCG.duplicateCount++;
            } else {
                VCG.duplicateCount = 0;
            }
            $('#updateSummary').trigger('click');
        }).fail(function () {
            console.log("something failed");
            VCG.growing = false;
        });
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

    $('#updateSummary').trigger('click');
});


function validateCodeFormat(code) {
    //console.log (code, VCG.checksumMethod);

    if (VCG.checksumMethod === 'luhn') {
        return validateCodeFormatLunh(code);
    }

    if (VCG.checksumMethod === 'mod') {
        return validateCodeFormatMod(code);
    }

    alert('invalid checksumMethod');
    console.log(code,VCG);
}


// Assumes that code only contains valid characters
function validateCodeFormatMod(code) {

    // Flip an array so key is value - assumes array is unique
    function array_flip( trans ) {
        var key, tmp_ar = {};
        for ( key in trans ) {
            if ( trans.hasOwnProperty( key ) ) {
                tmp_ar[trans[key]] = key;
            }
        }
        return tmp_ar;
    }

    // Prepare Valid Chars
    // validChars is a string such as "234689ACDEFHJKMNPRTVWXY"
    var validChars = VCG.validChars.split("");

    // Flip Chars
    var validKeys = array_flip(validChars);

    // Prepare Code
    var arrChars = code.trim().split("");

    // Remove checkDigit
    var lastDigit = arrChars.pop();

    // Calc CheckDigit using mod
    var idxSum = 0;
    for (i in arrChars) {
        char = arrChars[i];
        var k = parseInt(validKeys[char]);
        if (isNaN(k)) {
            console.log('invalid character in code: ' + char + ' -- ignoring it for checksum');
        } else {
            idxSum = idxSum + k;
        }
    }
    var mod = idxSum % validChars.length;

    // Convert mod index to actual character (e.g. 6 becomes A)
    var checkDigit = validChars[mod];

    return checkDigit == lastDigit;
}


function validateCodeFormatLunh(code) {
    var validChars  = VCG.validChars;
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
