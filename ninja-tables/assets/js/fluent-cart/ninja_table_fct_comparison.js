(function(){"use strict";const g=window.jQuery;class k{static init(c,n){this.$table=c,this.settings=n,this.bindEvents(c,n)}static bindEvents(c,n){this.bindVariationChange(c),this.bindQuantity(c),this.bindAddToCart(c,n)}static bindEventsFor(c,n){this.bindEvents(c,n)}static getNewUrl(c,n,t){if(!c)return c;let o;try{o=new URL(c,window.location.href)}catch{return c}return o.searchParams.set("item_id",n),o.searchParams.set("quantity",t),o.toString()}static updateCartButtons(c,{variationId:n,quantity:t}={}){c.each(function(){const o=g(this);n!==void 0&&o.attr("data-variation_id",n),t!==void 0&&o.attr("data-quantity",t);const r=o.attr("href");r&&o.attr("href",k.getNewUrl(r,o.attr("data-variation_id"),o.attr("data-quantity")||1))})}static bindVariationChange(c){c.on("change",".nt_fct_variations_select",function(n){const t=g(this),o=t.find(":selected"),r=t.closest("tr"),l=t.closest(".nt_fct_variations_wrapper").data("product_id"),f=o.data("variation_id")||0,x=o.data("formatted_price")||"",v=o.data("compared_price")||"",j=o.data("image_src")||"",C=o.data("stock")||0,y=r.data("is_compare_row")||!1,$=r.data("row_id")||!1,u=s=>{if(r.hasClass("child"))return r.find(s).add(r.prev("tr").find(s));const p=r.find(s);return p.length?p:r.parent().find(s)};let a=u(".nt_fct_product_price_wrapper");y&&(a=u('[data-pid="'+l+'"] .nt_fct_product_price_wrapper')),a.find(".nt_fct_product_price").text(x);const e=a.find(".nt-fct-compared-price");v?e.text(v).show():e.hide();let i=u(".fct_product_image");$?i=u(`[data-row_id="${r.data("row_id")}"] .fct_product_image`):y&&(i=u(`[data-pid="${l}"] .fct_product_image`)),j&&i&&i.attr("src",j);let d=u(".nt_fct_product_stock");y&&(d=u('[data-pid="'+l+'"] .nt_fct_product_stock')),d.length&&d.attr("data-product_stock",C).text(C),k.updateCartButtons(u(`.nt_fct_add_to_cart_${l}, .nt_fct_buy_now_${l}`),{variationId:f})})}static bindQuantity(c){c.on("change",".nt_fct_quantity",function(){const n=g(this),t=n.data("product_id"),o=Math.max(1,parseInt(n.val(),10)||1);n.val(o),k.updateCartButtons(c.find(`.nt_fct_add_to_cart_${t}, .nt_fct_buy_now_${t}`),{quantity:o})})}static bindAddToCart(c,n){c.on("click",'[class*="nt_fct_add_to_cart_"]:not(.loading)',function(t){var x;t.preventDefault();const o=g(this),r=o.parent();if(o.data("processing"))return;o.data("processing",!0),r.addClass("loading"),o.prop("disabled",!0);const l=(x=t.target)==null?void 0:x.dataset;if(!l)return;const f={product_id:l.product_id,variation_id:parseInt(l.variation_id),quantity:l.quantity};k._ajaxAdd([f],o,r,n)})}static _ajaxAdd(c,n,t,o){const{ajax_url:r,ninja_table_public_nonce:l}=window.ninja_footables||{};g.post(r,{action:"ninja_table_wp_fct_add_to_cart",nonce:l,table_id:(o==null?void 0:o.table_id)||this.settings.table_id,products:c}).done(f=>{f!=null&&f.success&&f.cart_html?(g(".ninjatable_cart_wrapper.fluent-cart").length?g(".ninjatable_cart_wrapper.fluent-cart").replaceWith(f.cart_html):g(".ninja_table_wrapper").append(f.cart_html),t&&t.addClass("added").removeClass("loading"),setTimeout(()=>{t&&t.removeClass("loading"),n&&n.prop("disabled",!1).removeData("processing")},1e3)):(t&&t.removeClass("loading"),n&&n.prop("disabled",!1).removeData("processing"))}).fail(()=>{t&&t.removeClass("loading"),n&&n.prop("disabled",!1).removeData("processing")})}}const b=window.jQuery;class I{static init(c,n){this.$table=c,this.settings=n,this.appendStyles()}static createComparisonModal(c,n){if(c.length<2||c.length>5)return alert("Select 2–5 products");const t=`nt_cmp_${n.table_id}`;b(`#${t}`).remove(),b("body").append(`
            <div id="${t}" class="nt_cmp_overlay">
                <div class="nt_cmp_content">
                    <div class="nt_cmp_header">
                        <h3>Compare (${c.length})</h3>
                        <button class="nt_cmp_close">×</button>
                    </div>
                    <div class="nt_cmp_body">
                        <div id="cmp_${t}" class="loading">Loading comparison...</div>
                    </div>
                    <div class="nt_cmp_footer">
                        <button class="nt_btn nt_btn_sec" id="close_cmp">Close</button>
                    </div>
                </div>
            </div>`),b(`#${t}`).fadeIn(),setTimeout(()=>this.render(c,n,t),80),b(`#${t}`).on("click",".nt_cmp_close, #close_cmp",()=>this.close(t)),b(`#${t}`).on("click",o=>o.target===o.currentTarget&&this.close(t)),b(document).on("keydown.cmp",o=>o.key==="Escape"&&this.close(t))}static close(c){b(`#${c}`).fadeOut(()=>{b(`#${c}`).remove(),b(document).off("keydown.cmp")})}static render(c,n,t){let o=`
            <div class="cmp_wrap">
                <table class="nt_cmp_table">
                    <thead>
                        <tr>
                            <th class="prop">Feature</th>
                            ${c.map(r=>`<th>Product ${c.indexOf(r)+1}</th>`).join("")}
                        </tr>
                    </thead>
                    <tbody>`;(n.columns||[]).forEach(r=>{!r.visible||r.key.includes("editing")||c.some(l=>l[r.key])&&(o+=`<tr data-is_compare_row="true">
                                <td class="prop">${r.title}</td>`,c.forEach(l=>{const f=l.cart.product_id,x=l[r.key]||"",v=/buy|add_to_cart/.test(r.key)?"act":/quantity/.test(r.key)?"qty":/image/.test(r.key)?"img":/price/.test(r.key)?"price":"val";o+=`<td class="${v}" data-pid="${f}" data-key="${r.key}">${x}</td>`}),o+="</tr>")}),o+=`</tbody>
                </table>
            </div>`,b(`#cmp_${t}`).html(o),this.bindEvents(b(`#${t}`),n)}static bindEvents(c,n){const t=c.find(".nt_cmp_table");k.init(t,n)}static appendStyles(){b("#nt_cmp_style").length||b("head").append(`
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
        `)}}jQuery(document).ready(m=>{const n={},t={};let o=null;const r=a=>{var e,i;return(i=(e=a.attr("class"))==null?void 0:e.match(/nt_row_id_(\S+)/))==null?void 0:i[1]},l=a=>{const e=window.FooTable&&FooTable.get(a);if(e)return e.rows.all.map(i=>({$el:i.$el,value:i.value}));if(m.fn.dataTable&&m.fn.dataTable.isDataTable(a)){const i=a.DataTable();return i.rows().indexes().toArray().map(d=>({$el:m(i.row(d).node()),value:i.row(d).data()})).filter(d=>d.$el.length)}return[]},f=a=>a.find("tbody tr:visible").map((e,i)=>{const d=m(i),s=r(d);return s&&d.find(".ninja-compare-checkbox:checked").length?s:null}).get().filter(Boolean),x=(a,e)=>{var d,s;if(e.provider!=="wp_fct"||((s=(d=e.settings)==null?void 0:d.appearance_settings)==null?void 0:s.show_bulk_actions)!=="yes")return;I.init(a,e);const i=e.table_id;n[i]=[],t[i]=!1,o=e,v(a,i,e),j(a,i),n[i]=f(a),u(a,i)},v=(a,e,i)=>{const s=a.closest(".ninja_table_wrapper").find(".ninja-bulk-actions-tr");s.length&&(s.off("change.bulk").off("click.bulk"),s.on("change.bulk",".ninja-bulk-action-select",function(){const p=m(this).val();m(this).data("action",p),C(a,e,p)}),s.on("click.bulk",".ninja-bulk-apply-btn",function(){const p=s.find(".ninja-bulk-action-select").data("action");if(!p||!n[e].length)return;const _=y(a,e);if(p==="compare")I.createComparisonModal(_,o),$(a,e);else if(p==="add_to_cart"){const h=_.map(w=>w.cart);k._ajaxAdd(h,null,"",i),$(a,e)}}),s.find(".ninja-bulk-apply-btn").attr("data-table-id",e))},j=(a,e)=>{a.off("change.bulk").on("change.bulk",".ninja-compare-checkbox",function(){if(t[e])return;const i=m(this),d=i.closest("tr");if(!r(d))return i.prop("checked",!1);const p=a.find(".ninja-compare-checkbox:checked").length;if(i.prop("checked")&&p>20){i.prop("checked",!1);return}n[e]=f(a),u(a,e)}),a.off("click.toggle").on("click.toggle",".ninja-compare-checkbox-toggle",function(i){i.stopPropagation();const d=m(this);setTimeout(()=>{const s=d.prop("checked"),p=a.find("tbody tr:visible").filter((_,h)=>r(m(h)));if(t[e]=!0,p.find(".ninja-compare-checkbox").prop("checked",!1),s&&n[e].length<20){let _=0;p.each(function(){if(_>=20)return!1;const h=m(this).find(".ninja-compare-checkbox");h.is(":visible")&&(h.prop("checked",!0),_++)})}t[e]=!1,n[e]=f(a),u(a,e)},0)})},C=(a,e,i)=>{const d=l(a);d.length&&(t[e]=!0,d.forEach(s=>{const p=s.$el,_=p.find(".ninja-compare-checkbox");if(!_.length)return;const h=p.find(".nt_fct_variations_wrapper"),w=h.length>0,q=w&&h.find(".nt_fct_variations_select").val()||0,z=i==="add_to_cart"?!w||q&&parseInt(q,10)>0:!0;_.css("visibility",z?"visible":"hidden").prop("checked",z?_.prop("checked"):!1)}),t[e]=!1,n[e]=f(a),u(a,e))},y=(a,e)=>{const i=[];return l(a).forEach(d=>{var A;const s=r(d.$el);if(!n[e].includes(s))return;const p=E=>_.find(E).length?_.find(E):_.next().find(E),_=d.$el,h=p(".nt_fct_add_cart_wrapper .nt_button"),w=parseInt(h.attr("data-product_id"))||0,q=parseInt(h.attr("data-variation_id")||0,10),z=parseInt(h.attr("data-quantity")||1,10),T={product_id:w,variation_id:q,quantity:z},M=(A=_.attr("class").match(/nt_row_id_(\S+)/))==null?void 0:A[1];i.push({cart:T,row_id:M,...d.value})}),i},$=(a,e)=>{t[e]=!0,a.find(".ninja-compare-checkbox").prop("checked",!1),t[e]=!1,n[e]=[],u(a,e)},u=(a,e)=>{const i=a.closest(".ninja_table_wrapper"),d=a.find(".ninja-compare-checkbox-toggle"),s=a.find("tbody tr:visible .ninja-compare-checkbox"),p=s.filter(":checked").length,_=s.length;d.prop({indeterminate:p>0&&p<_&&p<20,checked:p>0&&(p>=20||p===_)});const h=n[e].length>0,w=i.find(".ninja-bulk-action-select").data("action");i.find(".ninja-bulk-action-select").prop("disabled",!h),i.find(".ninja-bulk-apply-btn").prop("disabled",!h||!w)};m(document).on("ninja_table_loaded",(a,e,i)=>{try{x(e,i)}catch(d){console.error("Bulk Compare Init Error:",d)}})})})();
