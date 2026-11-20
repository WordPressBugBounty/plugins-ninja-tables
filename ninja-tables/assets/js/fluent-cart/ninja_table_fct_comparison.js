(function(){"use strict";const h=window.jQuery;class x{static init(a,t){this.$table=a,this.settings=t,this.bindEvents(a,t),this.bindCartSync(),this.bindRedrawMarks(a)}static bindRedrawMarks(a){!a||typeof a.on!="function"||a.off(".ntFctMarks").on("postdraw.ft.table.ntFctMarks draw.dt.ntFctMarks",()=>{this._inCart&&this.clearRemovedMarks(this._inCart,a)})}static bindCartSync(){this._cartSyncBound||(this._cartSyncBound=!0,window.addEventListener("fluentCartNotifyCartDrawerItemChanged",()=>this.scheduleCartSync()))}static scheduleCartSync(){clearTimeout(this._cartSyncTimer),this._cartSyncTimer=setTimeout(()=>this.syncCartBars(),300)}static syncCartBars(){const a=[...new Set(h(".ninjatable_cart_wrapper.fluent-cart[data-table_id]").toArray().map(s=>s.getAttribute("data-table_id")))],{ajax_url:t,ninja_table_public_nonce:e}=window.ninja_footables||{},n=this._hasMarks||h(".nt_fct_add_cart_wrapper.added").length>0;if(!a.length&&!n||!t)return;const o=this._cartSyncRequest=(this._cartSyncRequest||0)+1;return h.post(t,{action:"ninja_table_wp_fct_cart_fragments",nonce:e,table_ids:a}).done(s=>{o!==this._cartSyncRequest||!(s!=null&&s.success)||!s.fragments||(this.applyCartFragments(s.fragments),Array.isArray(s.in_cart)&&(this._inCart=s.in_cart.map(String),this.clearRemovedMarks(this._inCart)))})}static clearRemovedMarks(a,t=null){const e=a.map(String),n=".nt_fct_add_cart_wrapper.added";(t?t.find(n):h(n)).toArray().forEach(o=>{const s=o.querySelector('[class*="nt_fct_add_to_cart_"]');(!s||!e.includes(String(s.getAttribute("data-variation_id"))))&&h(o).removeClass("added")})}static placeCartBar(a,t,e){const n=h(`.ninjatable_cart_wrapper.fluent-cart[data-table_id="${parseInt(a,10)}"]`);if(n.length){n.replaceWith(t);return}const o=h(".ninjatable_cart_wrapper.fluent-cart:not([data-table_id])");if(o.length){o.replaceWith(t);return}h(".ninjatable_cart_wrapper.fluent-cart").length||this.tableWrapper(a,e).append(t)}static tableWrapper(a,t){const e=t&&t.closest?t.closest(".ninja_table_wrapper"):null;if(e&&e.length)return e;const n=parseInt(a,10);return h(`.ninja_table_wrapper[id="footable_parent_${n}"], .ninja_table_wrapper[id="ninja_dt_parent_${n}"]`)}static applyCartFragments(a){Object.keys(a).forEach(t=>{const e=parseInt(t,10);e&&a[t]&&h(`.ninjatable_cart_wrapper.fluent-cart[data-table_id="${e}"]`).replaceWith(a[t])})}static bindEvents(a,t){this.bindVariationChange(a),this.bindQuantity(a),this.bindAddToCart(a,t)}static bindEventsFor(a,t){this.bindEvents(a,t)}static getNewUrl(a,t,e){if(!a)return a;let n;try{n=new URL(a,window.location.href)}catch{return a}return n.searchParams.set("item_id",t),n.searchParams.set("quantity",e),n.toString()}static updateCartButtons(a,{variationId:t,quantity:e}={}){a.each(function(){const n=h(this);t!==void 0&&n.attr("data-variation_id",t),e!==void 0&&n.attr("data-quantity",e);const o=n.attr("href");o&&n.attr("href",x.getNewUrl(o,n.attr("data-variation_id"),n.attr("data-quantity")||1))})}static bindVariationChange(a){a.on("change",".nt_fct_variations_select",function(t){const e=h(this),n=e.find(":selected"),o=e.closest("tr"),s=e.closest(".nt_fct_variations_wrapper").data("product_id"),g=n.data("variation_id")||0,u=n.data("formatted_price")||"",y=n.data("compared_price")||"",C=n.data("image_src")||"",j=n.data("stock")||0,v=o.data("is_compare_row")||!1,S=o.data("row_id")||!1,b=p=>{if(o.hasClass("child"))return o.find(p).add(o.prev("tr").find(p));const l=o.find(p);return l.length?l:o.parent().find(p)};let r=b(".nt_fct_product_price_wrapper");v&&(r=b('[data-pid="'+s+'"] .nt_fct_product_price_wrapper')),r.find(".nt_fct_product_price").text(u);const i=r.find(".nt-fct-compared-price");y?i.text(y).show():i.hide();let c=b(".fct_product_image");S?c=b(`[data-row_id="${o.data("row_id")}"] .fct_product_image`):v&&(c=b(`[data-pid="${s}"] .fct_product_image`)),C&&c&&c.attr("src",C);let d=b(".nt_fct_product_stock");v&&(d=b('[data-pid="'+s+'"] .nt_fct_product_stock')),d.length&&d.attr("data-product_stock",j).text(j),x.updateCartButtons(b(`.nt_fct_add_to_cart_${s}, .nt_fct_buy_now_${s}`),{variationId:g})})}static bindQuantity(a){a.on("change",".nt_fct_quantity",function(){const t=h(this),e=t.data("product_id"),n=Math.max(1,parseInt(t.val(),10)||1);t.val(n),x.updateCartButtons(a.find(`.nt_fct_add_to_cart_${e}, .nt_fct_buy_now_${e}`),{quantity:n})})}static bindAddToCart(a,t){a.on("click",'[class*="nt_fct_add_to_cart_"]:not(.loading)',function(e){var u;e.preventDefault();const n=h(this),o=n.parent();if(n.data("processing"))return;n.data("processing",!0),o.addClass("loading"),n.prop("disabled",!0);const s=(u=e.target)==null?void 0:u.dataset;if(!s)return;const g={product_id:s.product_id,variation_id:parseInt(s.variation_id),quantity:s.quantity};x._ajaxAdd([g],n,o,t)})}static refreshFluentCart(){var e,n;const a=window.fluentCartCart;if(!a)return;const t=typeof a.refreshDrawer=="function"?a.refreshDrawer():(e=a.getCart)==null?void 0:e.call(a);(n=t==null?void 0:t.catch)==null||n.call(t,()=>{})}static _ajaxAdd(a,t,e,n){const{ajax_url:o,ninja_table_public_nonce:s}=window.ninja_footables||{},g=(n==null?void 0:n.table_id)||this.settings.table_id;h.post(o,{action:"ninja_table_wp_fct_add_to_cart",nonce:s,table_id:g,products:a}).done(u=>{u!=null&&u.success&&u.cart_html?(x.placeCartBar(g,u.cart_html,t),x.refreshFluentCart(),x.scheduleCartSync(),this._inCart&&a.forEach(y=>this._inCart.push(String(y.variation_id))),e&&(this._hasMarks=!0,e.addClass("added").removeClass("loading")),setTimeout(()=>{e&&e.removeClass("loading"),t&&t.prop("disabled",!1).removeData("processing")},1e3)):(e&&e.removeClass("loading"),t&&t.prop("disabled",!1).removeData("processing"))}).fail(()=>{e&&e.removeClass("loading"),t&&t.prop("disabled",!1).removeData("processing")})}}const m=window.jQuery;class I{static init(a,t){this.$table=a,this.settings=t,this.appendStyles()}static createComparisonModal(a,t){if(a.length<2||a.length>5)return alert("Select 2–5 products");const e=`nt_cmp_${t.table_id}`;m(`#${e}`).remove(),m("body").append(`
            <div id="${e}" class="nt_cmp_overlay">
                <div class="nt_cmp_content">
                    <div class="nt_cmp_header">
                        <h3>Compare (${a.length})</h3>
                        <button class="nt_cmp_close">×</button>
                    </div>
                    <div class="nt_cmp_body">
                        <div id="cmp_${e}" class="loading">Loading comparison...</div>
                    </div>
                    <div class="nt_cmp_footer">
                        <button class="nt_btn nt_btn_sec" id="close_cmp">Close</button>
                    </div>
                </div>
            </div>`),m(`#${e}`).fadeIn(),setTimeout(()=>this.render(a,t,e),80),m(`#${e}`).on("click",".nt_cmp_close, #close_cmp",()=>this.close(e)),m(`#${e}`).on("click",n=>n.target===n.currentTarget&&this.close(e)),m(document).on("keydown.cmp",n=>n.key==="Escape"&&this.close(e))}static close(a){m(`#${a}`).fadeOut(()=>{m(`#${a}`).remove(),m(document).off("keydown.cmp")})}static render(a,t,e){let n=`
            <div class="cmp_wrap">
                <table class="nt_cmp_table">
                    <thead>
                        <tr>
                            <th class="prop">Feature</th>
                            ${a.map(o=>`<th>Product ${a.indexOf(o)+1}</th>`).join("")}
                        </tr>
                    </thead>
                    <tbody>`;(t.columns||[]).forEach(o=>{!o.visible||o.key.includes("editing")||a.some(s=>s[o.key])&&(n+=`<tr data-is_compare_row="true">
                                <td class="prop">${o.title}</td>`,a.forEach(s=>{const g=s.cart.product_id,u=s[o.key]||"",y=/buy|add_to_cart/.test(o.key)?"act":/quantity/.test(o.key)?"qty":/image/.test(o.key)?"img":/price/.test(o.key)?"price":"val";n+=`<td class="${y}" data-pid="${g}" data-key="${o.key}">${u}</td>`}),n+="</tr>")}),n+=`</tbody>
                </table>
            </div>`,m(`#cmp_${e}`).html(n),this.bindEvents(m(`#${e}`),t)}static bindEvents(a,t){const e=a.find(".nt_cmp_table");x.init(e,t)}static appendStyles(){m("#nt_cmp_style").length||m("head").append(`
            <style id="nt_cmp_style">
                /* Overlay & Modal */
                .nt_cmp_overlay {
                    position: fixed;
                    top: 0; left: 0;
                    width: 100%; height: 100%;
                    background: rgba(0,0,0,0.6);
                    z-index: 10000;
                    display: none;
                    overflow: auto;
                    padding: 20px;
                    box-sizing: border-box;
                }

                .nt_cmp_content {
                    margin: 40px auto;
                    background: #fff;
                    border-radius: 12px;
                    max-width: 95%;
                    width: 1200px;
                    max-height: 90vh;
                    overflow: hidden;
                    box-shadow: 0 15px 40px rgba(0,0,0,0.3);
                    display: flex;
                    flex-direction: column;
                }

                /* Header - Compact */
                .nt_cmp_header {
                    padding: 14px 20px;
                    background: #f8f9fa;
                    border-bottom: 1px solid #eee;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    flex-shrink: 0;
                }

                .nt_cmp_header h3 {
                    margin: 0;
                    font-size: 18px;
                    font-weight: 600;
                    color: #333;
                }

                .nt_cmp_close {
                    background: none;
                    border: none;
                    font-size: 28px;
                    cursor: pointer;
                    color: #999;
                    padding: 0;
                    width: 36px;
                    height: 36px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }

                .nt_cmp_close:hover {
                    color: #333;
                }

                /* Body - Scrollable */
                .nt_cmp_body {
                    flex: 1;
                    overflow: auto;
                    padding: 0;
                }

                .cmp_wrap {
                    overflow: auto;
                    min-width: 800px;
                }

                /* Table Styling */
                .nt_cmp_table {
                    width: 100%;
                    table-layout: fixed; /* equal-width product columns regardless of content */
                    border-collapse: collapse;
                    font-size: 14px;
                    background: #fff;
                }

                .nt_cmp_table th,
                .nt_cmp_table td {
                    padding: 12px 16px;
                    border: 1px solid #e0e0e0;
                    text-align: left;
                    vertical-align: top;
                    overflow-wrap: anywhere;
                }

                .nt_cmp_table th {
                    background: #f5f5f5;
                    font-weight: 600;
                    color: #333;
                    position: sticky;
                    top: 0;
                    z-index: 10;
                    font-size: 13px;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }

                .prop {
                    width: 180px;
                    min-width: 180px;
                    background: #f8f9fa !important;
                    font-weight: 600;
                    position: sticky;
                    left: 0;
                    z-index: 11;
                    box-shadow: 2px 0 5px rgba(0,0,0,0.05);
                }

                /* Cell Types */
                .act a, .act button {
                    display: inline-block;
                    padding: 8px 14px;
                    background: #a46497;
                    color: #fff;
                    border: none;
                    border-radius: 6px;
                    font-size: 13px;
                    cursor: pointer;
                    text-decoration: none;
                    font-weight: 500;
                }

                .act a:hover, .act button:hover {
                    background: #8b3f7a;
                }

                .act a.loading, .act button.loading {
                    opacity: 0.7;
                    cursor: not-allowed;
                }

                .act a.added {
                    background: #28a745;
                }

                .img img {
                    max-width: 80px;
                    max-height: 80px;
                    object-fit: cover;
                    border-radius: 6px;
                    border: 1px solid #eee;
                }

                .qty input {
                    width: 70px;
                    padding: 6px 8px;
                    text-align: center;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                }

                .price {
                    font-weight: 600;
                    color: #e91e63;
                    font-size: 15px;
                }

                /* Footer */
                .nt_cmp_footer {
                    padding: 16px 20px;
                    border-top: 1px solid #eee;
                    text-align: right;
                    background: #f8f9fa;
                    flex-shrink: 0;
                }

                .nt_cmp_footer .nt_btn_sec {
                    padding: 10px 20px;
                    background: #fff;
                    color: #1f2937;
                    border: 1px solid #d1d5db;
                    border-radius: 6px;
                    cursor: pointer;
                    font-size: 14px;
                    font-weight: 500;
                }

                .nt_cmp_footer .nt_btn_sec:hover {
                    background: #f3f4f6;
                    border-color: #9ca3af;
                }

                /* Responsive */
                @media (max-width: 768px) {
                    .nt_cmp_content {
                        margin: 10px auto;
                        width: calc(100% - 20px);
                        max-height: 95vh;
                    }

                    .nt_cmp_header {
                        padding: 12px 16px;
                    }

                    .nt_cmp_header h3 {
                        font-size: 16px;
                    }

                    .nt_cmp_table th,
                    .nt_cmp_table td {
                        padding: 10px 12px;
                        font-size: 13px;
                    }

                    .prop {
                        width: 140px;
                        min-width: 140px;
                    }

                    .cmp_wrap {
                        min-width: 600px;
                    }
                }

                @media (max-width: 480px) {
                    .cmp_wrap {
                        min-width: 500px;
                    }

                    .act a, .act button {
                        padding: 6px 10px;
                        font-size: 12px;
                    }
                }
            </style>
        `)}}jQuery(document).ready(w=>{const t={},e={};let n=null;const o=r=>{var i,c;return(c=(i=r.attr("class"))==null?void 0:i.match(/nt_row_id_(\S+)/))==null?void 0:c[1]},s=r=>{const i=window.FooTable&&FooTable.get(r);if(i)return i.rows.all.map(c=>({$el:c.$el,value:c.value}));if(w.fn.dataTable&&w.fn.dataTable.isDataTable(r)){const c=r.DataTable();return c.rows().indexes().toArray().map(d=>({$el:w(c.row(d).node()),value:c.row(d).data()})).filter(d=>d.$el.length)}return[]},g=r=>r.find("tbody tr:visible").map((i,c)=>{const d=w(c),p=o(d);return p&&d.find(".ninja-compare-checkbox:checked").length?p:null}).get().filter(Boolean),u=(r,i)=>{var d,p;if(i.provider!=="wp_fct"||((p=(d=i.settings)==null?void 0:d.appearance_settings)==null?void 0:p.show_bulk_actions)!=="yes")return;I.init(r,i);const c=i.table_id;t[c]=[],e[c]=!1,n=i,y(r,c,i),C(r,c),t[c]=g(r),b(r,c)},y=(r,i,c)=>{const p=r.closest(".ninja_table_wrapper").find(".ninja-bulk-actions-tr");p.length&&(p.off("change.bulk").off("click.bulk"),p.on("change.bulk",".ninja-bulk-action-select",function(){const l=w(this).val();w(this).data("action",l),j(r,i,l)}),p.on("click.bulk",".ninja-bulk-apply-btn",function(){const l=p.find(".ninja-bulk-action-select").data("action");if(!l||!t[i].length)return;const _=v(r,i);if(l==="compare")I.createComparisonModal(_,n),S(r,i);else if(l==="add_to_cart"){const f=_.map(k=>k.cart);x._ajaxAdd(f,null,"",c),S(r,i)}}),p.find(".ninja-bulk-apply-btn").attr("data-table-id",i))},C=(r,i)=>{r.off("change.bulk").on("change.bulk",".ninja-compare-checkbox",function(){if(e[i])return;const c=w(this),d=c.closest("tr");if(!o(d))return c.prop("checked",!1);const l=r.find(".ninja-compare-checkbox:checked").length;if(c.prop("checked")&&l>20){c.prop("checked",!1);return}t[i]=g(r),b(r,i)}),r.off("click.toggle").on("click.toggle",".ninja-compare-checkbox-toggle",function(c){c.stopPropagation();const d=w(this);setTimeout(()=>{const p=d.prop("checked"),l=r.find("tbody tr:visible").filter((_,f)=>o(w(f)));if(e[i]=!0,l.find(".ninja-compare-checkbox").prop("checked",!1),p&&t[i].length<20){let _=0;l.each(function(){if(_>=20)return!1;const f=w(this).find(".ninja-compare-checkbox");f.is(":visible")&&(f.prop("checked",!0),_++)})}e[i]=!1,t[i]=g(r),b(r,i)},0)})},j=(r,i,c)=>{const d=s(r);d.length&&(e[i]=!0,d.forEach(p=>{const l=p.$el,_=l.find(".ninja-compare-checkbox");if(!_.length)return;const f=l.find(".nt_fct_variations_wrapper"),k=f.length>0,M=k&&f.find(".nt_fct_variations_select").val()||0,q=c==="add_to_cart"?!k||M&&parseInt(M,10)>0:!0;_.css("visibility",q?"visible":"hidden").prop("checked",q?_.prop("checked"):!1)}),e[i]=!1,t[i]=g(r),b(r,i))},v=(r,i)=>{const c=[];return s(r).forEach(d=>{var $;const p=o(d.$el);if(!t[i].includes(p))return;const l=A=>_.find(A).length?_.find(A):_.next().find(A),_=d.$el,f=l(".nt_fct_add_cart_wrapper .nt_button"),k=parseInt(f.attr("data-product_id"))||0,M=parseInt(f.attr("data-variation_id")||0,10),q=parseInt(f.attr("data-quantity")||1,10),E={product_id:k,variation_id:M,quantity:q},R=($=_.attr("class").match(/nt_row_id_(\S+)/))==null?void 0:$[1];c.push({cart:E,row_id:R,...d.value})}),c},S=(r,i)=>{e[i]=!0,r.find(".ninja-compare-checkbox").prop("checked",!1),e[i]=!1,t[i]=[],b(r,i)},b=(r,i)=>{const c=r.closest(".ninja_table_wrapper"),d=r.find(".ninja-compare-checkbox-toggle"),p=r.find("tbody tr:visible .ninja-compare-checkbox"),l=p.filter(":checked").length,_=p.length;d.prop({indeterminate:l>0&&l<_&&l<20,checked:l>0&&(l>=20||l===_)});const f=t[i].length>0,k=c.find(".ninja-bulk-action-select").data("action");c.find(".ninja-bulk-action-select").prop("disabled",!f),c.find(".ninja-bulk-apply-btn").prop("disabled",!f||!k)};w(document).on("ninja_table_loaded",(r,i,c)=>{try{u(i,c)}catch(d){console.error("Bulk Compare Init Error:",d)}})})})();
