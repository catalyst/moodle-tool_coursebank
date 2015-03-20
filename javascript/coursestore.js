jQuery(document).ready(function() {
        jQuery('.conncheckbutton').click( conn_check );
        jQuery('.speedtestbutton').click( speed_test );
        jQuery('.conncheckurl-div').addClass("hide");
        jQuery('.speedtesturl-div').addClass("hide");
        jQuery('.conncheckbutton-div').removeClass("hide");
        jQuery('.speedtestbutton-div').removeClass("hide");
    }
);

function conn_check( event ) {
    event.preventDefault();
    var xhr = new XMLHttpRequest();
    var wwwroot = jQuery('.wwwroot').val();
    jQuery(".conncheck-fail").addClass("hide");
    jQuery(".conncheck-success").addClass("hide");
    jQuery(".check-div").removeClass("hide");
    xhr.onreadystatechange = function() {
        if (xhr.readyState == 4 && xhr.status == 200) {
            var response = xhr.responseText;
            if (response == "1") {
                jQuery(".check-div").addClass("hide");
                jQuery(".conncheck-success").removeClass("hide");
                if (!jQuery(".conncheck-fail").hasClass("hide")) {
                    jQuery(".conncheck-fail").addClass("hide");
                }
            } else {
                jQuery(".check-div").addClass("hide");
                jQuery(".conncheck-fail").removeClass("hide");
                if (!jQuery(".conncheck-success").hasClass("hide")) {
                    jQuery(".conncheck-success").addClass("hide");
                }
            }
        }
    };
    xhr.open('GET', wwwroot + '/admin/tool/coursestore/ajax.php?action=conncheck', true);
    xhr.send();
}
function speed_test( event ) {
    event.preventDefault();
    var xhr = new XMLHttpRequest();
    var wwwroot = jQuery('.wwwroot').val();
    jQuery(".speedtest-fail").addClass("hide");
    jQuery(".speedtest-success").addClass("hide");
    jQuery(".speedtest-div").removeClass("hide");
    xhr.onreadystatechange = function() {
        if (xhr.readyState == 4 && xhr.status == 200) {
            var response = +(xhr.responseText);
            if (response > 256) {
                jQuery(".speedtest-div").addClass("hide");
                jQuery(".speedtest-success").removeClass("hide");
                if (!jQuery(".speedtest-fail").hasClass("hide")) {
                    jQuery(".speedtest-fail").addClass("hide");
                }
                if (!jQuery(".speedtest-slow").hasClass("hide")) {
                    jQuery(".speedtest-slow").addClass("hide");
                }
            } else if (response == 0){
                jQuery(".speedtest-div").addClass("hide");
                jQuery(".speedtest-fail").removeClass("hide");
                if (!jQuery(".speedtest-success").hasClass("hide")) {
                    jQuery(".speedtest-success").addClass("hide");
                }
                if (!jQuery(".speedtest-slow").hasClass("hide")) {
                    jQuery(".speedtest-slow").addClass("hide");
                }
            }
            else {
                var speedtestcontent = jQuery('.speedtestslow').val();
                jQuery(".speedtest-alert-slow").text(speedtestcontent + ' ' + response + ' kbps');
                jQuery(".speedtest-div").addClass("hide");
                jQuery(".speedtest-slow").removeClass("hide");
                if (!jQuery(".speedtest-fail").hasClass("hide")) {
                    jQuery(".speedtest-fail").addClass("hide");
                }
                if (!jQuery(".speedtest-success").hasClass("hide")) {
                    jQuery(".speedtest-success").addClass("hide");
                }
            }
        }
    };
    xhr.open('GET', wwwroot + '/admin/tool/coursestore/ajax.php?action=speedtest', true);
    xhr.send();
}
