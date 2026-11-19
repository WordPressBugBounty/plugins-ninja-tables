(function(){"use strict";const g=window.jQuery;class x{static init(n,t){this.$table=n,this.settings=t,this.bindEvents(n,t)}static bindEvents(n,t){this.bindVariationChange(n),this.bindQuantity(n),this.bindAddToCart(n,t)}static bindEventsFor(n,t){this.bindEvents(n,t)}static getNewUrl(n,t,e){if(!n)return n;let o;try{o=new URL(n,window.location.href)}catch{return n}return o.searchParams.set("item_id",t),o.searchParams.set("quantity",e),o.toString()}static updateCartButtons(n,{variationId:t,quantity:e}={}){n.each(function(){const o=g(this);t!==void 0&&o.attr("data-variation_id",t),e!==void 0&&o.attr("data-quantity",e);const r=o.attr("href");r&&o.attr("href",x.getNewUrl(r,o.attr("data-variation_id"),o.attr("data-quantity")||1))})}static bindVariationChange(n){n.on("change",".nt_fct_variations_select",function(t){const e=g(this),o=e.find(":selected"),r=e.closest("tr"),l=e.closest(".nt_fct_variations_wrapper").data("product_id"),_=o.data("variation_id")||0,w=o.data("formatted_price")||"",v=o.data("compared_price")||"",j=o.data("image_src")||"",C=o.data("stock")||0,y=r.data("is_compare_row")||!1,$=r.data("row_id")||!1,u=s=>{if(r.hasClass("child"))return r.find(s).add(r.prev("tr").find(s));const p=r.find(s);return p.length?p:r.parent().find(s)};let i=u(".nt_fct_product_price_wrapper");y&&(i=u('[data-pid="'+l+'"] .nt_fct_product_price_wrapper')),i.find(".nt_fct_product_price").text(w);const a=i.find(".nt-fct-compared-price");v?a.text(v).show():a.hide();let c=u(".fct_product_image");$?c=u(`[data-row_id="${r.data("row_id")}"] .fct_product_image`):y&&(c=u(`[data-pid="${l}"] .fct_product_image`)),j&&c&&c.attr("src",j);let d=u(".nt_fct_product_stock");y&&(d=u('[data-pid="'+l+'"] .nt_fct_product_stock')),d.length&&d.attr("data-product_stock",C).text(C),x.updateCartButtons(u(`.nt_fct_add_to_cart_${l}, .nt_fct_buy_now_${l}`),{variationId:_})})}static bindQuantity(n){n.on("change",".nt_fct_quantity",function(){const t=g(this),e=t.data("product_id"),o=Math.max(1,parseInt(t.val(),10)||1);t.val(o),x.updateCartButtons(n.find(`.nt_fct_add_to_cart_${e}, .nt_fct_buy_now_${e}`),{quantity:o})})}static bindAddToCart(n,t){n.on("click",'[class*="nt_fct_add_to_cart_"]:not(.loading)',function(e){var w;e.preventDefault();const o=g(this),r=o.parent();if(o.data("processing"))return;o.data("processing",!0),r.addClass("loading"),o.prop("disabled",!0);const l=(w=e.target)==null?void 0:w.dataset;if(!l)return;const _={product_id:l.product_id,variation_id:parseInt(l.variation_id),quantity:l.quantity};x._ajaxAdd([_],o,r,t)})}static refreshFluentCart(){var e,o;const n=window.fluentCartCart;if(!n)return;const t=typeof n.refreshDrawer=="function"?n.refreshDrawer():(e=n.getCart)==null?void 0:e.call(n);(o=t==null?void 0:t.catch)==null||o.call(t,()=>{})}static _ajaxAdd(n,t,e,o){const{ajax_url:r,ninja_table_public_nonce:l}=window.ninja_footables||{};g.post(r,{action:"ninja_table_wp_fct_add_to_cart",nonce:l,table_id:(o==null?void 0:o.table_id)||this.settings.table_id,products:n}).done(_=>{_!=null&&_.success&&_.cart_html?(g(".ninjatable_cart_wrapper.fluent-cart").length?g(".ninjatable_cart_wrapper.fluent-cart").replaceWith(_.cart_html):g(".ninja_table_wrapper").append(_.cart_html),x.refreshFluentCart(),e&&e.addClass("added").removeClass("loading"),setTimeout(()=>{e&&e.removeClass("loading"),t&&t.prop("disabled",!1).removeData("processing")},1e3)):(e&&e.removeClass("loading"),t&&t.prop("disabled",!1).removeData("processing"))}).fail(()=>{e&&e.removeClass("loading"),t&&t.prop("disabled",!1).removeData("processing")})}}const b=window.jQuery;class I{static init(n,t){this.$table=n,this.settings=t,this.appendStyles()}static createComparisonModal(n,t){if(n.length<2||n.length>5)return alert("Select 2–5 products");const e=`nt_cmp_${t.table_id}`;b(`#${e}`).remove(),b("body").append(`
            <div id="${e}" class="nt_cmp_overlay">
                <div class="nt_cmp_content">
                    <div class="nt_cmp_header">
                        <h3>Compare (${n.length})</h3>
                        <button class="nt_cmp_close">×</button>
                    </div>
                    <div class="nt_cmp_body">
                        <div id="cmp_${e}" class="loading">Loading comparison...</div>
                    </div>
                    <div class="nt_cmp_footer">
                        <button class="nt_btn nt_btn_sec" id="close_cmp">Close</button>
                    </div>
                </div>
            </div>`),b(`#${e}`).fadeIn(),setTimeout(()=>this.render(n,t,e),80),b(`#${e}`).on("click",".nt_cmp_close, #close_cmp",()=>this.close(e)),b(`#${e}`).on("click",o=>o.target===o.currentTarget&&this.close(e)),b(document).on("keydown.cmp",o=>o.key==="Escape"&&this.close(e))}static close(n){b(`#${n}`).fadeOut(()=>{b(`#${n}`).remove(),b(document).off("keydown.cmp")})}static render(n,t,e){let o=`
            <div class="cmp_wrap">
                <table class="nt_cmp_table">
                    <thead>
                        <tr>
                            <th class="prop">Feature</th>
                            ${n.map(r=>`<th>Product ${n.indexOf(r)+1}</th>`).join("")}
                        </tr>
                    </thead>
                    <tbody>`;(t.columns||[]).forEach(r=>{!r.visible||r.key.includes("editing")||n.some(l=>l[r.key])&&(o+=`<tr data-is_compare_row="true">
                                <td class="prop">${r.title}</td>`,n.forEach(l=>{const _=l.cart.product_id,w=l[r.key]||"",v=/buy|add_to_cart/.test(r.key)?"act":/quantity/.test(r.key)?"qty":/image/.test(r.key)?"img":/price/.test(r.key)?"price":"val";o+=`<td class="${v}" data-pid="${_}" data-key="${r.key}">${w}</td>`}),o+="</tr>")}),o+=`</tbody>
                </table>
            </div>`,b(`#cmp_${e}`).html(o),this.bindEvents(b(`#${e}`),t)}static bindEvents(n,t){const e=n.find(".nt_cmp_table");x.init(e,t)}static appendStyles(){b("#nt_cmp_style").length||b("head").append(`
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
        `)}}jQuery(document).ready(m=>{const t={},e={};let o=null;const r=i=>{var a,c;return(c=(a=i.attr("class"))==null?void 0:a.match(/nt_row_id_(\S+)/))==null?void 0:c[1]},l=i=>{const a=window.FooTable&&FooTable.get(i);if(a)return a.rows.all.map(c=>({$el:c.$el,value:c.value}));if(m.fn.dataTable&&m.fn.dataTable.isDataTable(i)){const c=i.DataTable();return c.rows().indexes().toArray().map(d=>({$el:m(c.row(d).node()),value:c.row(d).data()})).filter(d=>d.$el.length)}return[]},_=i=>i.find("tbody tr:visible").map((a,c)=>{const d=m(c),s=r(d);return s&&d.find(".ninja-compare-checkbox:checked").length?s:null}).get().filter(Boolean),w=(i,a)=>{var d,s;if(a.provider!=="wp_fct"||((s=(d=a.settings)==null?void 0:d.appearance_settings)==null?void 0:s.show_bulk_actions)!=="yes")return;I.init(i,a);const c=a.table_id;t[c]=[],e[c]=!1,o=a,v(i,c,a),j(i,c),t[c]=_(i),u(i,c)},v=(i,a,c)=>{const s=i.closest(".ninja_table_wrapper").find(".ninja-bulk-actions-tr");s.length&&(s.off("change.bulk").off("click.bulk"),s.on("change.bulk",".ninja-bulk-action-select",function(){const p=m(this).val();m(this).data("action",p),C(i,a,p)}),s.on("click.bulk",".ninja-bulk-apply-btn",function(){const p=s.find(".ninja-bulk-action-select").data("action");if(!p||!t[a].length)return;const f=y(i,a);if(p==="compare")I.createComparisonModal(f,o),$(i,a);else if(p==="add_to_cart"){const h=f.map(k=>k.cart);x._ajaxAdd(h,null,"",c),$(i,a)}}),s.find(".ninja-bulk-apply-btn").attr("data-table-id",a))},j=(i,a)=>{i.off("change.bulk").on("change.bulk",".ninja-compare-checkbox",function(){if(e[a])return;const c=m(this),d=c.closest("tr");if(!r(d))return c.prop("checked",!1);const p=i.find(".ninja-compare-checkbox:checked").length;if(c.prop("checked")&&p>20){c.prop("checked",!1);return}t[a]=_(i),u(i,a)}),i.off("click.toggle").on("click.toggle",".ninja-compare-checkbox-toggle",function(c){c.stopPropagation();const d=m(this);setTimeout(()=>{const s=d.prop("checked"),p=i.find("tbody tr:visible").filter((f,h)=>r(m(h)));if(e[a]=!0,p.find(".ninja-compare-checkbox").prop("checked",!1),s&&t[a].length<20){let f=0;p.each(function(){if(f>=20)return!1;const h=m(this).find(".ninja-compare-checkbox");h.is(":visible")&&(h.prop("checked",!0),f++)})}e[a]=!1,t[a]=_(i),u(i,a)},0)})},C=(i,a,c)=>{const d=l(i);d.length&&(e[a]=!0,d.forEach(s=>{const p=s.$el,f=p.find(".ninja-compare-checkbox");if(!f.length)return;const h=p.find(".nt_fct_variations_wrapper"),k=h.length>0,q=k&&h.find(".nt_fct_variations_select").val()||0,z=c==="add_to_cart"?!k||q&&parseInt(q,10)>0:!0;f.css("visibility",z?"visible":"hidden").prop("checked",z?f.prop("checked"):!1)}),e[a]=!1,t[a]=_(i),u(i,a))},y=(i,a)=>{const c=[];return l(i).forEach(d=>{var A;const s=r(d.$el);if(!t[a].includes(s))return;const p=E=>f.find(E).length?f.find(E):f.next().find(E),f=d.$el,h=p(".nt_fct_add_cart_wrapper .nt_button"),k=parseInt(h.attr("data-product_id"))||0,q=parseInt(h.attr("data-variation_id")||0,10),z=parseInt(h.attr("data-quantity")||1,10),D={product_id:k,variation_id:q,quantity:z},T=(A=f.attr("class").match(/nt_row_id_(\S+)/))==null?void 0:A[1];c.push({cart:D,row_id:T,...d.value})}),c},$=(i,a)=>{e[a]=!0,i.find(".ninja-compare-checkbox").prop("checked",!1),e[a]=!1,t[a]=[],u(i,a)},u=(i,a)=>{const c=i.closest(".ninja_table_wrapper"),d=i.find(".ninja-compare-checkbox-toggle"),s=i.find("tbody tr:visible .ninja-compare-checkbox"),p=s.filter(":checked").length,f=s.length;d.prop({indeterminate:p>0&&p<f&&p<20,checked:p>0&&(p>=20||p===f)});const h=t[a].length>0,k=c.find(".ninja-bulk-action-select").data("action");c.find(".ninja-bulk-action-select").prop("disabled",!h),c.find(".ninja-bulk-apply-btn").prop("disabled",!h||!k)};m(document).on("ninja_table_loaded",(i,a,c)=>{try{w(a,c)}catch(d){console.error("Bulk Compare Init Error:",d)}})})})();
