/*!
	autosize 4.0.1
	license: MIT
	http://www.jacklmoore.com/autosize

	Basty note:
	Fonts with subsets e.g. Alef with Hebrew don't display correctly on div with contenteditable=true.
	A textarea must be used instead, which needs auto-sizing (for font-size increase)
*/
!function(e,t){if("function"==typeof define&&define.amd)define(["module","exports"],t);else if("undefined"!=typeof exports)t(module,exports);else{var n={exports:{}};t(n,n.exports),e.MTautosize=n.exports}}(this,(function(e,t){"use strict";var n,o,a="function"==typeof Map?new Map:(n=[],o=[],{has:function(e){return-1<n.indexOf(e)},get:function(e){return o[n.indexOf(e)]},set:function(e,t){-1===n.indexOf(e)&&(n.push(e),o.push(t))},delete:function(e){var t=n.indexOf(e);-1<t&&(n.splice(t,1),o.splice(t,1))}}),i=function(e){return new Event(e,{bubbles:!0})};try{new Event("test")}catch(e){i=function(e){var t=document.createEvent("Event");return t.initEvent(e,!0,!1),t}}function r(e){var t=a.get(e);t&&t.destroy()}function s(e){var t=a.get(e);t&&t.update()}var l=null;"undefined"==typeof window||"function"!=typeof window.getComputedStyle?((l=function(e){return e}).destroy=function(e){return e},l.update=function(e){return e}):((l=function(e,t){return e&&Array.prototype.forEach.call(e.length?e:[e],(function(e){return function(e){if(e&&e.nodeName&&"TEXTAREA"===e.nodeName&&!a.has(e)){var t,n=null,o=null,r=null,s=function(){e.clientWidth!==o&&d()},l=function(t){window.removeEventListener("resize",s,!1),e.removeEventListener("input",d,!1),e.removeEventListener("keyup",d,!1),e.removeEventListener("autosize:destroy",l,!1),e.removeEventListener("autosize:update",d,!1),Object.keys(t).forEach((function(n){e.style[n]=t[n]})),a.delete(e)}.bind(e,{height:e.style.height,resize:e.style.resize,overflowY:e.style.overflowY,overflowX:e.style.overflowX,wordWrap:e.style.wordWrap});e.addEventListener("autosize:destroy",l,!1),"onpropertychange"in e&&"oninput"in e&&e.addEventListener("keyup",d,!1),window.addEventListener("resize",s,!1),e.addEventListener("input",d,!1),e.addEventListener("autosize:update",d,!1),e.style.overflowX="hidden",e.style.wordWrap="break-word",a.set(e,{destroy:l,update:d}),"vertical"===(t=window.getComputedStyle(e,null)).resize?e.style.resize="none":"both"===t.resize&&(e.style.resize="horizontal"),n="content-box"===t.boxSizing?-(parseFloat(t.paddingTop)+parseFloat(t.paddingBottom)):parseFloat(t.borderTopWidth)+parseFloat(t.borderBottomWidth),isNaN(n)&&(n=0),d()}function f(t){var n=e.style.width;e.style.width="0px",e.offsetWidth,e.style.width=n,e.style.overflowY=t}function c(){if(0!==e.scrollHeight){var t=function(e){for(var t=[];e&&e.parentNode&&e.parentNode instanceof Element;)e.parentNode.scrollTop&&t.push({node:e.parentNode,scrollTop:e.parentNode.scrollTop}),e=e.parentNode;return t}(e),a=document.documentElement&&document.documentElement.scrollTop;e.style.height="",e.style.height=e.scrollHeight+n+"px",o=e.clientWidth,t.forEach((function(e){e.node.scrollTop=e.scrollTop})),a&&(document.documentElement.scrollTop=a)}}function d(){c();var t=Math.round(parseFloat(e.style.height)),n=window.getComputedStyle(e,null),o="content-box"===n.boxSizing?Math.round(parseFloat(n.height)):e.offsetHeight;if(o!==t?"hidden"===n.overflowY&&(f("scroll"),c(),o="content-box"===n.boxSizing?Math.round(parseFloat(window.getComputedStyle(e,null).height)):e.offsetHeight):"hidden"!==n.overflowY&&(f("hidden"),c(),o="content-box"===n.boxSizing?Math.round(parseFloat(window.getComputedStyle(e,null).height)):e.offsetHeight),r!==o){r=o;var a=i("autosize:resized");try{e.dispatchEvent(a)}catch(t){}}}}(e)})),e}).destroy=function(e){return e&&Array.prototype.forEach.call(e.length?e:[e],r),e},l.update=function(e){return e&&Array.prototype.forEach.call(e.length?e:[e],s),e}),t.default=l,e.exports=t.default}));var MTWindow=!1;window.top.TvrMT?MTWindow=window.top:window.parent.TvrMT?MTWindow=window.parent:window.parent.parent&&window.parent.parent.TvrMT&&(MTWindow=window.parent.parent),jQuery(document).ready((function(e){var t=MTWindow.TvrMT,n=t.TvrUi,o=t.TvrCom,a=(t.tvrsprintf,MTWindow.TvrLang,t.mod.MTsave),i={query:"",category:{serif:1,"sans-serif":1,display:1,handwriting:1,monospace:1},sort:"popularity",subset:""},r={allFonts:{},filteredFonts:[],google:{url:"https://www.googleapis.com/webfonts/v1/webfonts?key=AIzaSyBY_w3-Yj6T54CQP5RNZrruBHEvwenEOmU",filters:e.extend(!0,{},i),adjustments:e.extend(!0,{},{preview_text:"sentence",custom_text:"",font_size:"",variant:"regular"}),pagination:{results_per_page:6,current_page:1,index:0,total_pages:0,total_fonts:0}},typekit:{},preview_text:{sentence:{text:"Grumpy wizards make toxic brew for the evil Queen and Jack.",font_size:32,line_height:1.1},paragraph:{text:"As the minuteness of the parts formed a great hindrance to my speed, I resolved, contrary to my first intention, to make the being of a gigantic stature; that is to say, about eight feet in height, and proportionably large. ",font_size:18,line_height:1.44},alphabet:{text:"ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghjklmnopqrstuvwxyz ‘?’“!”(%)[#]{@}/&\\<-+÷×=>®©$€£¥¢:;,.*",font_size:24,line_height:1.33},numerals:{text:"1234567890",font_size:58,line_height:1.1},custom:{text:"",font_size:24,line_height:1.33}},init:function(){this.get_font_settings(),this.bind_events(),this.update_filters(),this.fetch_fonts()},bind_events:function(){e("#tvr-fonts").on("change","select",(function(t){r.route_event(t,e(this))})).on("input propertychange","input, textarea",(function(t){r.route_event(t,e(this))})).on("blur","textarea",(function(t){r.route_event(t,e(this))})).on("click",(function(t){r.route_event(t,e(this))}))},route_event:function(t,n){switch(t.type){case"click":var a=e(t.target);switch(a.data("action")){case"pagination":r.change_pagination(a);break;case"toggle-category":o.toggle_radio_checkbox(a),r.filter_changed(a.prev(),"category");break;case"clear_filters":this.clear_filters(a);break;case"apply_to_all":this.apply_to_all(a);break;case"insert-font":this.insert_font(a);break;case"show-textarea":this.show_textarea(a)}break;case"blur":case"focusout":case"change":case"input":case"propertychange":var i=n,s=i.attr("name");switch(s){case"preview_text":case"variant":case"font_size":r.font_adjusted(i,s);break;case"current_page":r.change_pagination(i);break;case"query":case"sort":case"subset":r.filter_changed(i,s);break;case"font_textarea":"blur"===t.type||"focusout"===t.type?this.hide_textarea(i):r.font_adjusted(i,s)}}},get_font_settings:function(){n.pref&&n.pref.font_config.google&&(this.google=n.pref.font_config.google)},save_font_settings:function(){n.pref&&a.simple_ajax_request({url:n.ajaxUrl+"&save_font_config=google",type:"POST",sendData:a.serialized_data_with_single_key(JSON.stringify(this.google)),plus:"",minus:""})},update_filters:function(){var t=r.google.filters;e("#query-filter").find("input").val(t.query),e("#category-filter").find(".fake-checkbox").each((function(n){var a=e(this),i=a.prev().data("category");t.category[i]?o.check_fake(a):o.uncheck_fake(a)}));for(var n=["sort","subset"],a=0;a<n.length;a++){var i=n[a];this.set_select_element(e("#"+i+"-filter"),t[i])}},set_select_element:function(e,t){var n=e.find("select");return(n=o.isDomEl(n)?n:e).find("option").filter(":selected").prop("selected",!1).end().filter('[value="'+t+'"]').prop("selected",!0).attr("selected","selected"),n},fetch_fonts:function(){e.ajax({type:"GET",url:r.google.url,data:r.resolve_api_data(r.google.filters),dataType:"jsonp",async:!0,timeout:n.tOut,error:function(e,t){r.handle_ajax_error(e,t)},success:function(e){r.allFonts=e,r.filter_fonts(),r.load_first_page()}})},show_clear_filters:function(){e("#clear-filters").addClass("show")},hide_clear_filters:function(){e("#clear-filters").removeClass("show")},clear_filters:function(){var t="popularity"!==this.google.filters.sort;this.google.filters=e.extend(!0,{},i),t&&this.fetch_fonts(),this.update_filters(),this.filter_fonts(),this.load_first_page()},show_apply_to_all:function(e){e.find(".apply-to-all").addClass("show")},apply_to_all:function(t){var n=this.google.adjustments,o=t.closest("li"),a=o.find("select[name=variant]").val(),i=o.data("item-number");n.preview_text=o.find("select[name=preview_text]").val(),n.font_size=o.find("input[name=font_size]").val(),n.custom_text="custom"===n.preview_text?o.find("textarea.font").val():"",this.update_display_fonts();var s=(o=e("#gf-output").find("li[data-item-number="+i+"]")).find("select[name=variant]");this.set_select_element(s,a),r.font_adjusted(s,"variant")},getCaretPosition:function(e){var t,n,o=0;if(window.getSelection)(t=window.getSelection()).rangeCount&&(n=t.getRangeAt(0)).commonAncestorContainer.parentNode==e&&(o=n.endOffset);else if(document.selection&&document.selection.createRange&&(n=document.selection.createRange()).parentElement()==e){var a=document.createElement("span");e.insertBefore(a,e.firstChild);var i=n.duplicate();i.moveToElementText(a),i.setEndPoint("EndToEnd",n),o=i.text.length}return o},setSelectionRange:function(e,t,n){if(e.setSelectionRange)e.focus(),e.setSelectionRange(t,n);else if(e.createTextRange){var o=e.createTextRange();o.collapse(!0),o.moveEnd("character",n),o.moveStart("character",t),o.select()}},setCaretToPos:function(e,t){this.setSelectionRange(e,t,t)},show_textarea:function(e){var t=e.closest("li"),n=this.getCaretPosition(e[0]),o=t.find("textarea");t.addClass("show-textarea"),MTautosize.update(o),this.setCaretToPos(o[0],n)},hide_textarea:function(e){e.closest("li").removeClass("show-textarea")},font_adjusted:function(e,t){var n=this.google.adjustments,o=(this.google.filters,e.val()),a=e.closest("li"),i=a.find(".font");switch(t){case"preview_text":var r=this.preview_text[o],s=this.get_preview_text(r,n,o);i.text(s).val(s).css({"font-size":r.font_size+"px","line-height":r.line_height}),a.find(".font_size-adjustment").find("input").val(r.font_size);break;case"font_textarea":this.set_select_element(a.find(".preview_text-adjustment").find("select"),"custom"),i.filter("div").text(o);break;case"variant":var l=o.match(/^(\d+)?\s?([a-z]+)?$/),f=l&&l[1]?l[1]:"normal",c=l&&l[2]?l[2]:"normal";return i.css({"font-weight":f,"font-style":c}),!0;case"font_size":i.css("font-size",o+"px")}"font_textarea"!==t&&MTautosize.update(i.filter("textarea")),this.show_apply_to_all(a)},filter_fonts:function(){var e=this.google.filters,t=this.allFonts.items,n=[],a=e.category,i=[],r=e.query.trim().toLowerCase();for(var s in a)a.hasOwnProperty(s)&&a[s]&&i.push(s);for(var l=0;l<t.length;l++){var f=t[l],c=f.family.toLowerCase();e.query&&c.indexOf(r)<0||i.length<5&&!o.in_array(f.category,i)||e.subset&&!o.in_array(e.subset,f.subsets)||n.push(f)}t.length===n.length&&"popularity"===e.sort?this.hide_clear_filters():this.show_clear_filters(),this.filteredFonts=n},filter_changed:function(t,n){var o=this.google.filters,a=t.val(),i=t.closest(".font-filter");switch("category"!==n&&(o[n]=a),n){case"sort":return this.fetch_fonts(),!0;case"category":var r=o[n];i.find("input[type=checkbox]").each((function(t){var n=e(this),o=n.attr("name").match(/\[(.+)\]/)[1];r[o]=n.is(":checked")}))}this.filter_fonts(),this.load_first_page()},change_pagination:function(t){var n,o=r.google.pagination,a=t.data("value"),i=e("#current-font-page").val();switch(i=i?parseInt(i):1,a){case"first":n=1;break;case"prev":n=i-1;break;case"manual":n=i;break;case"next":n=i+1;break;case"last":n=o.total_pages}!n||n<1?n=1:n>o.total_pages&&o.total_pages>0&&(n=o.total_pages),o.current_page=n,o.index=(n-1)*o.results_per_page,r.update_pagination(),r.update_display_fonts()},update_pagination:function(){var t=e("#controls"),n=e("#pagination"),o=r.google.pagination;o.total_fonts=this.filteredFonts.length,o.total_pages=Math.ceil(o.total_fonts/o.results_per_page),t.find(".font-count").text(o.total_fonts),n.find(".page-total").text(o.total_pages);var a=e("#current-font-page");a.is(":focus")||a.val(o.current_page),n.find(".control-disabled").removeClass("control-disabled"),o.current_page<=1?n.find(".first-page, .prev-page").addClass("control-disabled"):o.current_page>=o.total_pages&&n.find(".gf-next-page, .gf-last-page").addClass("control-disabled")},load_first_page:function(){var t=e("#current-font-page");t.val(1),this.change_pagination(t)},get_preview_text:function(e,t,n){return"custom"===n?t.custom_text:e.text},update_display_fonts:function(){var t=e("#google-fonts"),n=[],a=r.google.pagination,i=r.google.adjustments,s=r.google.filters,l=a.index,f=a.index+a.results_per_page,c=this.preview_text[i.preview_text],d=this.get_preview_text(c,i,i.preview_text),u=i.font_size?i.font_size:c.font_size,p=this.filteredFonts.slice(l,f),g=e("<ul>"),h=e("#font-entry-template").find("li").clone();h.find(".font").text(d).val(d).css({"font-size":u+"px","line-height":c.line_height}).end().find(".font_size-adjustment").find("input").val(u),this.set_select_element(h.find(".preview_text-adjustment"),i.preview_text);for(var _=0;_<p.length;_++){for(var v=p[_],m=h.clone(!0,!0),y=m.find(".variant-adjustment").find("select"),w=v.variants,x="",b=0;b<w.length;b++){var T=w[b].replace(/italic/g," italic"),z=T,E="regular"===T?'selected="selected"':"";x+='<option value="'+(T=T.replace("regular","400"))+'"'+E+">"+z+"</option>"}y.html(x),w.length<=1&&y.prop("disabled",!0),m.attr("data-item-number",_).find(".font-name").text(v.family).end().find(".font").css("font-family",v.family).end().find(".use-font").data("use-font",v.family),g.append(m);var k=v.variants.length?v.family+":"+v.variants.join(","):v.family;n.push(k)}var j="//fonts.googleapis.com/css?family="+n.join("|").replace(/ /g,"+");j+=s.subset?"&subset="+s.subset:"",o.isDomEl(t)?t.attr("href",j):e("head").append('<link id="google-fonts" rel="stylesheet" type="text/css" href="'+j+'" />'),e("#gf-output").html(g[0].outerHTML),setTimeout((function(){MTautosize(document.querySelectorAll("textarea"))}),100),this.save_font_settings()},resolve_api_data:function(e){var t=[],n=["sort"];for(var a in e)if(e.hasOwnProperty(a)&&o.in_array(a,n)){var i=e[a];if(o.isDefined(i)){if(i===Object(i)){var r=i,s=[];for(var l in r)r.hasOwnProperty(l)&&r[l]&&s.push(l);if(!s.length)continue;i=s.join(",")}t.push(a+"="+i)}}return t.join("&")},insert_font:function(t){var o=[],a=t.closest("li"),i=a.find(".font-name").text(),r=a.find("select[name=variant]").val().trim(),s=e("#controls").find("select[name=subset]").val(),l=!1;r&&"400"!==r&&(o.push(r),l=!0),s&&(l||o.push(r),o.push(s)),o.length&&(i=i+" ("+o.join(", ")+")"),n.insert_google_font&&n.insert_google_font(i)},handle_ajax_error:function(e,t){0==e.status?alert("Connection Error. Request Failed. Please Try again."):404==e.status?alert("Requested URL not found."):500==e.status?alert("Internel Server Error."):"parsererror"==t?alert("Error.\nParsing JSON Request failed."):"timeout"==t?alert("Request Timed out."):alert("Unknow Error.\n"+e.responseText)}};r.init()}));