/* ===============================
   LOAD ON PAGE READY
================================= */

document.addEventListener("DOMContentLoaded", loadEarnings);


/* ===============================
   LOAD EARNINGS
================================= */

async function loadEarnings(){

try{

const res=await apiRequest("/earnings","GET");

if(res.status!=="success"){
alert(res.message||"Failed to load earnings");
return;
}

const d=res.data||{};


/* ===============================
   SAFE NUMBERS
================================= */

setText("totalBalance","₹"+formatMoney(d.total_balance||0));
setText("todayEarnings","₹"+formatMoney(d.today||0));
setText("monthEarnings","₹"+formatMoney(d.month||0));
setText("totalSales",d.total_sales||0);


/* ===============================
   SALES TABLE
================================= */

const table=document.getElementById("salesTable");
if(!table) return;

table.innerHTML="";

/* NO SALES */

if(!d.sales || d.sales.length===0){

table.innerHTML=`
<tr>
<td colspan="5" style="padding:20px;text-align:center;color:#94a3b8">
No sales yet
</td>
</tr>`;
return;
}

/* LOOP SALES */

d.sales.forEach(s=>{

const tr=document.createElement("tr");

tr.innerHTML=`
<td>${escapeHtml(s.book_title||"-")}</td>
<td>${escapeHtml(s.buyer||"-")}</td>
<td>₹${formatMoney(s.amount||0)}</td>
<td>${formatDate(s.date)}</td>
<td>
<span class="badge ${s.status==="success"?"success":"pending"}">
${s.status||"success"}
</span>
</td>
`;

table.appendChild(tr);

});

}catch(err){

console.error("Earnings error:",err);
alert("Server error while loading earnings");

}

}


/* ===============================
   WITHDRAW BUTTON
================================= */

function goWithdraw(){
location.href="bank.html";
}


/* ===============================
   SAFE TEXT SETTER
================================= */

function setText(id,val){
const el=document.getElementById(id);
if(el) el.innerText=val;
}


/* ===============================
   MONEY FORMAT
================================= */

function formatMoney(n){
return Number(n||0).toLocaleString("en-IN");
}


/* ===============================
   DATE FORMAT
================================= */

function formatDate(d){
if(!d) return "-";

const x=new Date(d);
if(isNaN(x.getTime())) return "-";

return x.toLocaleDateString()+" "+x.toLocaleTimeString();
}


/* ===============================
   ESCAPE HTML (SECURITY)
================================= */

function escapeHtml(text){
return String(text)
.replace(/&/g,"&amp;")
.replace(/</g,"&lt;")
.replace(/>/g,"&gt;");
}