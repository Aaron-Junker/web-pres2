<script type="text/javascript" language="javascript" src="1k.js"></script>
<script type="text/javascript" language="javascript" src="slide.js"></script>
<script language="JavaScript1.2">
<!--
if(!document.all) {
	window.captureEvents(Event.KEYUP);
} else {
	document.onkeypress = keypressHandler;
}
function keypressHandler(e) {
	var e;
	if(document.all) { //it's IE
		e = window.event.keyCode;
	} else {
		e = e.which;
	}
	if (e == 39) { /* right arrow */
		if (effects.length > 0 && currentEffect < effects.length) {
			switch(effects[currentEffect].getAttribute('effect')) {
				case 'slide':
					try {
						slide(effects[currentEffect], effects[currentEffect].getAttribute('gotox')-50, effects[currentEffect].getAttribute('gotoy'), 0) ;
					} catch (e) { alert(e); }
					break;
				case 'hide':
					oldstyle = effects[currentEffect].getAttribute('oldstyle');
					effects[currentEffect].setAttribute('style',oldstyle+'visibility:visible;');	
					break;
			}
			currentEffect = currentEffect+1;
		} else if (<?php echo $nextSlideNum; ?>) {
			top.location='<?php echo "http://$_SERVER[HTTP_HOST]$baseDir$showScript/$currentPres/$nextSlideNum"; ?>';
		}
	}
	if (e == 37 && <?php echo $prevSlideNum+1; ?>) /* left arrow */
		top.location='<?php echo "http://$_SERVER[HTTP_HOST]$baseDir$showScript/$currentPres/$prevSlideNum"; ?>';
}
window.onkeyup = keypressHandler;

var effects = [];
var currentEffect = 0;

onload = function() {
	// find any div objects with an effect attribute 
	var divs = document.getElementsByTagName('div');
	for (var i=0; i < divs.length; i++) {
		if (divs[i].hasAttribute('effect')) {
			// ok, add this to our slider array
			//alert("slide "+divs[i].id+" at "+divs[i].offsetLeft+","+divs[i].offsetTop+"\n");
			if(divs[i].getAttribute('effect') == 'slide') {
				divs[i].setAttribute('gotox',divs[i].offsetLeft);
				divs[i].setAttribute('gotoy',0);
				divs[i].setAttribute('style','position:relative;left:-<?=$winW+10?>;top:0;');
			} else if(divs[i].getAttribute('effect') == 'hide') {
				style = divs[i].getAttribute('style');
				divs[i].setAttribute('style',style+'visibility:hidden;');
				divs[i].setAttribute('oldstyle',style);
			}
			effects[effects.length] = divs[i];
	    }
	}
}
-->
</script>
