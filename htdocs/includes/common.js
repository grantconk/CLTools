/*from control.js from Entrisphere site */
function Is() {
 var agent = navigator.userAgent.toLowerCase();
 this.major = parseInt(navigator.appVersion);
 this.minor = parseFloat(navigator.appVersion);
 this.ns = ((agent.indexOf('mozilla')!=-1) && ((agent.indexOf('spoofer')==-1) && (agent.indexOf('compatible') == -1)));
 this.ns2 = (this.ns && (this.major == 2));
 this.ns3 = (this.ns && (this.major == 3));
 this.ns4 = (this.ns && (this.major == 4));
 this.ns5 = (this.ns && (this.major > 4));
 this.ns6 = (this.ns && (agent.indexOf('netscape6')!=-1) );
 this.ns7 = (this.ns && (agent.indexOf('netscape/7')!=-1) );
 this.ns7pr = (this.ns && (agent.indexOf('netscape/7.0b1')!=-1) );
 this.ns71 = (this.ns && (agent.indexOf('netscape/7.1')!=-1) );
 this.ns72 = (this.ns && (agent.indexOf('netscape/7.2')!=-1) );
 this.ie = (agent.indexOf("msie") != -1);
 this.ie3 = (this.ie && (this.major == 2));
 this.ie4 = (this.ie && (this.major >= 4));
 this.ie5 = (this.ie && (this.major == 4) && (agent.indexOf("msie 5.0") != -1));
 this.ie55 = (this.ie && (this.major == 4) && (agent.indexOf("msie 5.5") != -1));
 this.ie6 = (this.ie && (agent.indexOf("msie 6.0")!=-1));
 this.op3 = (agent.indexOf("opera") != -1);
 this.pc  = (agent.indexOf("win") != -1);
 this.mac = (agent.indexOf("mac")!=-1); // Mac detect
 this.client = ( (navigator.userAgent.indexOf('AOL')!=-1) || (navigator.userAgent.indexOf('CS 2000')!=-1) )? 1 : 0;
 this.moz = ( this.ns && (agent.indexOf("netscape/") == -1) );
 if (this.moz) this.ns = 0;
}
var is = new Is();

function findPosX(obj)
{
	var curleft = 0;
	if (obj.offsetParent)
	{
		while (obj.offsetParent)
		{
			curleft += obj.offsetLeft
			obj = obj.offsetParent;
		}
	}
	else if (obj.x)
		curleft += obj.x;
	return curleft;
}
function PosObj(obj, hgt, pObj) {
    var curTop = findPosY(obj);
    var abImg = getObject('fpAnimswapImgFP4');
    var curLeft = findPosX(abImg);
    tObj = getObject( pObj );
    tObj = tObj.style;
    if (is.ie)
        tObj.top = curTop + 229 + 'px';
    else if (is.op3)
        tObj.top = curTop + 248 + 'px';
    else
        tObj.top = curTop + 248 + 'px';
    tObj.width = ( curLeft - 100 ) + 'px';
}
function SPosObj(obj, hgt, pObj) {
    var curTop = findPosY(obj);
    var abImg = getObject('fpAnimswapImgFP4');
    var sPage = getObject('solutions');
    var curLeft = findPosX(abImg);
    tObj = getObject( pObj );
    tObj = tObj.style;
    sPage = sPage.style;
    var Top = 0;
    if (is.ie)
        Top = curTop + 229;
    else if (is.op3)
        Top = curTop + 248;
    else
        Top = curTop + 248;
    tObj.width = ( curLeft - 100 ) + 'px';
    sPage.top = Top + 3 + 'px';
    tObj.top = Top + 'px';
}
function findPosY(obj)
{
	var curtop = 0;
	if (obj.offsetParent)
	{
		while (obj.offsetParent)
		{
			curtop += obj.offsetTop
			obj = obj.offsetParent;
		}
	}
	else if (obj.y)
		curtop += obj.y;
    if (is.ie)
    	return curtop+22;
    else if (is.op3)
        return curtop+22;
    else
        return curtop+16;
}
function getObject(obj) 
{
	var strObj
	if ( document.all ) {
	strObj = document.all.item( obj );
		} 
	else if ( document.getElementById ) 
		{
	strObj = document.getElementById( obj );
		}		
	return strObj;
}
/* end from Entrisphere site */

/*
 * Resizes the main body width
 */
function adjustMainBody ()
{
	main_obj = document.getElementById('myw');
	menu_obj = document.getElementById('intranet_leftmenu');
	sub_w = (menu_obj.style.display == 'none' ? 40 : 190);
	main_obj.style.width = pageWidth() - sub_w;
}

/*
 * Returns page width so table and div values can be adjusted
 */
function pageWidth()
{
	return window.innerWidth != null ? window.innerWidth : document.body != null ? document.body.clientWidth:null;
}
/*
 * Returns page height so table and div values can be adjusted
 */
function pageHeight()
{
	return window.innerHeight != null ? window.innerHeight : document.body != null ? document.body.clientHeight:null;
}

/**
 * Sets a Cookie with the given name and value.
 *
 * name       Name of the cookie
 * value      Value of the cookie
 * [expires]  Expiration date of the cookie (default: end of current session)
 * [path]     Path where the cookie is valid (default: path of calling document)
 * [domain]   Domain where the cookie is valid
 *              (default: domain of calling document)
 * [secure]   Boolean value indicating if the cookie transmission requires a
 *              secure transmission
 */
function setCookie(name, value, expires, path, domain, secure)
{
    document.cookie= name + "=" + escape(value) +
        ((expires) ? "; expires=" + expires.toGMTString() : "") +
        ((path) ? "; path=" + path : "") +
        ((domain) ? "; domain=" + domain : "") +
        ((secure) ? "; secure" : "");
}

/**
 * Gets the value of the specified cookie.
 *
 * name  Name of the desired cookie.
 *
 * Returns a string containing value of specified cookie,
 *   or null if cookie does not exist.
 */
function getCookie(name)
{
    var dc = document.cookie;
    var prefix = name + "=";
    var begin = dc.indexOf("; " + prefix);
    if (begin == -1)
    {
        begin = dc.indexOf(prefix);
        if (begin != 0) return null;
    }
    else
    {
        begin += 2;
    }
    var end = document.cookie.indexOf(";", begin);
    if (end == -1)
    {
        end = dc.length;
    }
    return unescape(dc.substring(begin + prefix.length, end));
}

/**
 * Deletes the specified cookie.
 *
 * name      name of the cookie
 * [path]    path of the cookie (must be same as path used to create cookie)
 * [domain]  domain of the cookie (must be same as domain used to create cookie)
 */
function deleteCookie(name, path, domain)
{
    if (getCookie(name))
    {
        document.cookie = name + "=" +
            ((path) ? "; path=" + path : "") +
            ((domain) ? "; domain=" + domain : "") +
            "; expires=Thu, 01-Jan-70 00:00:01 GMT";
    }
}

/*
 * Displays and hides a div tag block
 */
function toggle(toggleId, e)
{
	if (!e) {
		e = window.event;
	}
	if (!document.getElementById) {
		return false;
	}
	var body = document.getElementById(toggleId);
	if (!body) {
		return false;
	}
	var im = toggleId + "_toggle";
	if (body.style.display == 'none') {
		body.style.display = 'block';
	} else {
		body.style.display = 'none';
	//popUpProperties(body);
	}
	if (e) {
		e.cancelBubble = true;
		if (e.stopPropagation) {
			e.stopPropagation();
		}
	}
}

function toggleDiv (toggleId, e)
{
	if (!e) {
		e = window.event;
	}
	if (!document.getElementById) {
		return false;
	}
	var body = document.getElementById(toggleId);
	if (!body) {
		return false;
	}
	if (body) {	// DOM3 = IE5, NS6
		if (body.style.visibility == 'hidden') {
			body.style.visibility = 'visible';
			body.style.position = 'relative';
		} else {
			body.style.visibility = 'hidden';
			body.style.position = 'absolute';
		}
	} else {
		if (document.layers) {	// NS4
			if (document.toggleId.visibility == 'hidden') {
				document.toggleId.visibility = 'visible';
				document.toggleId.position = 'relative';
			} else {
				document.toggleId.visibility = 'hidden';
				document.toggleId.position = 'absolute';
			}
		} else {				// IE4
			if (document.all.toggleId.style.visibility == 'hidden') {
				document.all.toggleId.style.visibility = 'visible';
				document.all.toggleId.style.position = 'relative';
			} else {
				document.all.toggleId.style.visibility = 'hidden';
				document.all.toggleId.style.position = 'absolute';
			}
		}
	}
}
function showDivBox (id, mesg)
{
	if (!document.getElementById) {
		return false;
	}
	var body = document.getElementById(id);
	if (!body) {
		return false;
	}

	// change the content of div
	body.innerHTML = mesg;

	if (body) {	// DOM3 = IE5, NS6
		body.style.visibility = 'visible';
	} else {
		if (document.layers) {	// NS4
			document.toggleId.visibility = 'visible';
		} else {				// IE4
			document.all.toggleId.style.visibility = 'visible';
		}
	}
}
function forceShowDiv(toggleId, e)
{
	if (!e) {
		e = window.event;
	}
	if (!document.getElementById) {
		return false;
	}
	var body = document.getElementById(toggleId);
	if (!body) {
		return false;
	}
	if (body.style.display == 'none') {
		body.style.display = 'block';
	} else {
		// nothing
	}
	if (e) {
		e.cancelBubble = true;
		if (e.stopPropagation) {
			e.stopPropagation();
		}
	}
}
function forceHideDiv(toggleId, e)
{
	if (!e) {
		e = window.event;
	}
	if (!document.getElementById) {
		return false;
	}
	var body = document.getElementById(toggleId);
	if (!body) {
		return false;
	}
	if (body.style.display == 'none') {
		// nothing
	} else {
		body.style.display = 'none';
	}
	if (e) {
		e.cancelBubble = true;
		if (e.stopPropagation) {
			e.stopPropagation();
		}
	}
}


//
// For DEBUGGING
// displays an CSS object's properties in a new browser window
//
function popUpProperties(inobj) {
	op = window.open();
	op.document.open('text/plain');
	for (objprop in inobj) {
	op.document.write(objprop + ' => ' + inobj[objprop] + '\n');
	}
	op.document.close();
}