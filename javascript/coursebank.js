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
            var response = JSON.parse(xhr.responseText);
            if (response[1]) {
            	window.location = response[2];
            	jQuery(".conncheck-fail").addClass("hide");
            	jQuery(".conncheck-success").addClass("hide");
            } else {
	            if (response[0] == "1") {
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
        }
    };
    xhr.open('GET', wwwroot + '/admin/tool/coursebank/ajax.php?action=conncheck', true);
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
            var response = jQuery.parseJSON( xhr.responseText );
            if (response[1]) {
            	window.location = response[2];
            } else {
	            if (+(response[0].speed) == 0){
	                // Connection has failed.
	                jQuery(".speedtest-div").addClass("hide");
	                jQuery(".speedtest-fail").removeClass("hide");
	
	                // hide the other divs.
	                if (!jQuery(".speedtest-success").hasClass("hide")) {
	                    jQuery(".speedtest-success").addClass("hide");
	                }
	                if (!jQuery(".speedtest-slow").hasClass("hide")) {
	                    jQuery(".speedtest-slow").addClass("hide");
	                }
	            } else if (+(response[0].speed) >= 256) {
	                // Good connection.
	                var speedtestcontent = jQuery('.speedtestsuccess').val();
	                var speedtestchunk   = jQuery('.speedtestchunk').val();
	                jQuery(".speedtest-alert-success").text(speedtestcontent + ' ' + response[0].speed + ' kbps. ' + speedtestchunk + ' ' + response[0].chunksize + 'kB.');
	                jQuery(".speedtest-div").addClass("hide");
	                jQuery(".speedtest-success").removeClass("hide");
	
	                // hide the other divs.
	                if (!jQuery(".speedtest-fail").hasClass("hide")) {
	                    jQuery(".speedtest-fail").addClass("hide");
	                }
	                if (!jQuery(".speedtest-slow").hasClass("hide")) {
	                    jQuery(".speedtest-slow").addClass("hide");
	                }
	            } else {
	                // This is a slow connection.
	                var speedtestcontent = jQuery('.speedtestslow').val();
	                var speedtestchunk   = jQuery('.speedtestchunk').val();
	                jQuery(".speedtest-alert-slow").text(speedtestcontent + ' ' + response[0].speed + ' kbps. ' + speedtestchunk + ' ' + response[0].chunksize + 'kB.');
	                jQuery(".speedtest-div").addClass("hide");
	                jQuery(".speedtest-slow").removeClass("hide");
	
	                // hide the other divs.
	                if (!jQuery(".speedtest-fail").hasClass("hide")) {
	                    jQuery(".speedtest-fail").addClass("hide");
	                }
	                if (!jQuery(".speedtest-success").hasClass("hide")) {
	                    jQuery(".speedtest-success").addClass("hide");
	                }
	            }
            }
        }
    };
    xhr.open('GET', wwwroot + '/admin/tool/coursebank/ajax.php?action=speedtest', true);
    xhr.send();
}
