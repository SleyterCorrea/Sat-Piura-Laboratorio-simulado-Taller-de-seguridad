/**
 * @package 	Module JQuery Daily Pop
 * @version 	1.7
 * @author 		Yannick Tanguy
 * @copyright 	Copyright (C) 2016 - yannick Tanguy
 * @license 	GNU/GPL http://www.gnu.org/copyleft/gpl.html
 **/

var temp = 0;

// Function to Show the animated effect
function dpanimfull(num,tp){
	if (num==1){
		if (tp==0){ jQuery('#dailyposition').show(); jQuery( "#dailyfullscreen" ).animate({ opacity: "toggle" }); }
		if (tp==1){ jQuery( "#dailyfullscreen" ).animate({ opacity: "toggle" }, function(){ jQuery( "#dailyposition" ).animate({ opacity: "toggle" }); }); }
		if (tp==2){
			largzone=jQuery("#dailypopupwidth").val();
			jQuery('#dailyposition').css({'width': '0px'});
			jQuery( "#dailyfullscreen" ).animate({ opacity: "toggle" }, function(){ jQuery('#dailyposition').show(); jQuery( "#dailyposition" ).animate({ width: largzone },1500); });
		}
	} else {
		jQuery( "#dailyposition" ).toggle();
		jQuery( "#dailyfullscreen" ).toggle();
	}
}

// Function to remove the Animated effect
function rmanimfull(num,tp){
	if (num==1){
		jQuery( "#dailyfullscreen" ).animate({ opacity: "toggle" }, function(){jQuery("#dailyfullscreen").remove();});
	} else {
		jQuery( "#dailyfullscreen" ).remove();
	}
}

window.onload = function(){
	letexte2=jQuery("#dailycomplete").html();
	largzone=jQuery("#dailypopupwidth").val();
	zetimer=jQuery("#jqtimer").val();
	actianim=jQuery("#actianim").val();
	jqanime=jQuery("#jqanime").val();
	jQuery("#dailycomplete").remove();
	jQuery('body').prepend(letexte2);
	fermcl=jQuery('#closedailyp').val();

	// width of the Popup
	jQuery('#dailyposition').css({'max-width': largzone+'px', 'display': 'none'});

	// start show the popup with or without animated effect
	dpanimfull(actianim,jqanime);

	timetmp=zetimer*1000;
	//prepare the end and the effect on remove PopUp

	if (actianim==1){
		endrem='jQuery( "#dailyfullscreen" ).animate({ opacity: "toggle" }, function(){jQuery("#dailyfullscreen").remove();});';
	} else {
		endrem='jQuery("#dailyfullscreen").remove();';
	}
	// timer
	for (i=zetimer ;i > -1;i--){
        setTimeout("jQuery('#closedailyp').val('"+fermcl+" "+i+"');",temp);
        temp+=1000;
		if (temp==timetmp){ setTimeout(endrem,temp); }
    }
	jQuery('#closedailyp').click(function () { rmanimfull(actianim,jqanime); });
}
