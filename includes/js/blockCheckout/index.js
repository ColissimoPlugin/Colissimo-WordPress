(()=>{"use strict";const e=window.React,t=window.wp.element,n=window.wp.plugins,l=window.wc.blocksCheckout,c=window.wp.data,i=({cart:n})=>{const[l,i]=(0,t.useState)(),{setValidationErrors:o,clearValidationError:r}=(0,c.useDispatch)("wc/store/validation"),{phone:d,country:s}=n.shippingAddress;window.lpcBlockChangeContent=e=>{i(e)},(0,t.useEffect)((()=>{"undefined"!=typeof lpcPickUpSelection&&(((e,t)=>{if(!e)return!1;if(!["FR","BE"].includes(t))return!0;if("FR"===t)return e.match(/^(\+33|0033|\+330|00330|0)(6|7)\d{8}$/);if("BE"===t){if(!e.match(/^^\+324\d{8}$/))return!1;const t=e.split("").reverse().map((e=>parseInt(e,10)));let n=!0,l=!0,c=!0;for(let e=0;e<t.length&&7!==e;e++)t[e+1]!==t[e]-1&&(n=!1),t[e+1]!==t[e]+1&&(l=!1),t[e+1]!==t[e]&&(c=!1);return!n&&!l&&!c}})(d,s)?l&&!l.includes('<div id="lpc_pick_up_info"></div>')?r("lpc_validation"):o({lpc_validation:{message:lpcPickUpSelection.messagePickupRequired,hidden:!1}}):o({lpc_validation:{message:lpcPickUpSelection.messagePhoneRequired,hidden:!1}}))}),[r,o,l,n.shippingAddress.phone,n.shippingAddress.country]);const{validationError:p}=(0,c.useSelect)((e=>({validationError:e("wc/store/validation").getValidationError("lpc_validation")})));let u="";if(null!=n?.shippingRates[0])return n?.shippingRates[0]?.shipping_rates.forEach((e=>{u=!0===e.selected?e.method_id:u})),"lpc_relay"!==u?(0,e.createElement)("div",null):("undefined"!=typeof lpcPickUpSelection&&jQuery.ajax({url:lpcPickUpSelection.baseAjaxUrl,type:"POST",dataType:"json",data:{action:"lpc_pickup_ajax_content"},success:function(e){"success"===e.type&&e.data.content!==l&&i(e.data.content)}}),jQuery("[data-lpc-template]").off("click").on("click",(function(e){e.preventDefault(),a({template:jQuery(this).attr("data-lpc-template")}),jQuery(this).is("[data-lpc-callback]")&&window[jQuery(this).attr("data-lpc-callback")](jQuery(this))})),jQuery("#lpc_pick_up_widget_show_map").off("click").on("click",(function(e){e.preventDefault(),$affectMethodDiv=jQuery(this).closest(".lpc_order_affect_available_methods"),a({template:"lpc_pick_up_widget_container"});const t={callBackFrame:"lpc_callback"};jQuery.extend(t,window.lpc_widget_info),jQuery("#lpc_widget_container").frameColissimoOpen(t),jQuery(".lpc-modal .modal-close").on("click",(function(){let e=jQuery("#lpc_widget_container");if(e.length>0)try{e.frameColissimoClose()}catch(e){console.error(e)}}))})),(0,e.createElement)("div",null,(0,e.createElement)("div",{dangerouslySetInnerHTML:{__html:l}}),!1===p?.hidden&&(0,e.createElement)("div",null,"⚠️ ",p?.message)))};function a(e){e.template||console.error("Could not instantiate modal without template");const t=`lpc-modal${e.template}`;let n=document.getElementById(t);if(n)n.style.display="block";else{const l=document.getElementById(`tmpl-${e.template}`);l||console.error("Error while getting the template of the modal"),document.body.insertAdjacentHTML("beforeend",`<div id='${t}'>${l.innerHTML}</div>`),n=document.getElementById(t)}const l=n.querySelectorAll(".modal-close");for(const e of l)e.addEventListener("click",(function(e){e.preventDefault(),n.style.display="none"}))}(0,n.registerPlugin)("lpc-wc-block",{render:()=>(0,e.createElement)(e.Fragment,null,(0,e.createElement)(l.ExperimentalOrderShippingPackages,null,(0,e.createElement)(i,null))),scope:"woocommerce-checkout"})})();
