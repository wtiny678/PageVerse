/* ===============================
   BANK.JS — FINAL FIXED VERSION
================================= */

/* LOAD ON PAGE */
document.addEventListener("DOMContentLoaded", loadBankDetails);


/* ===============================
   SAVE / UPDATE BANK DETAILS
================================= */
async function saveBank(){

const holder_name=document.getElementById("holder_name").value.trim();
const bank_name=document.getElementById("bank_name").value.trim();
const account_number=document.getElementById("account_number").value.trim();
const ifsc=document.getElementById("ifsc").value.trim();

if(!holder_name||!bank_name||!account_number||!ifsc){
alert("Please fill all bank fields");
return;
}

try{

const res=await apiRequest("/bank","POST",{
holder_name,
bank_name,
account_number,
ifsc
});

if(res.status==="success"){
alert("Bank details saved successfully");
loadBankDetails();
}else{
alert(res.message||"Failed to save bank details");
}

}catch(err){
console.error(err);
alert("Server error while saving bank details");
}

}



/* ===============================
   LOAD BANK DETAILS
================================= */

async function loadBankDetails(){

try{

const res=await apiRequest("/bank","GET");

const div=document.getElementById("bankInfo");

/* ⭐ HANDLE EMPTY BANK SAFELY */

if(res.status!=="success" || !res.data){

div.innerHTML=`
<p><b>Status:</b> 
<span class="badge" style="background:#dc2626">
Not Added
</span></p>`;

return;
}

const b=res.data;

/* FILL FORM */

document.getElementById("holder_name").value=b.holder_name||"";
document.getElementById("bank_name").value=b.bank_name||"";
document.getElementById("account_number").value=b.account_number||"";
document.getElementById("ifsc").value=b.ifsc||"";

/* SHOW INFO */

div.innerHTML=`
<p><b>Status:</b> <span class="badge">Verified</span></p>
<p><b>Account Holder:</b> ${b.holder_name}</p>
<p><b>Bank:</b> ${b.bank_name}</p>
<p><b>Account No:</b> ${maskAccount(b.account_number)}</p>
<p><b>IFSC:</b> ${b.ifsc}</p>
`;

}catch(err){

console.error(err);

document.getElementById("bankInfo").innerHTML=
`<p style="color:red">Failed to load bank details</p>`;

}

}



/* ===============================
   WITHDRAW MONEY
================================= */

async function withdrawMoney(){

const val=document.getElementById("withdraw_amount").value;
const amount=parseFloat(val);

if(!amount || amount<=0){
alert("Enter valid amount");
return;
}

try{

/* ⭐ CORRECT ENDPOINT */

const res=await apiRequest("/earnings/withdraw","POST",{amount});

const msg=document.getElementById("withdrawMsg");

if(res.status==="success"){
msg.style.color="#22c55e";
msg.innerText="Withdrawal request submitted";
document.getElementById("withdraw_amount").value="";
}else{
msg.style.color="#ef4444";
msg.innerText=res.message||"Withdrawal failed";
}

}catch(err){

console.error(err);

document.getElementById("withdrawMsg").innerText=
"Server error while withdrawing";

}

}



/* ===============================
   MASK ACCOUNT NUMBER
================================= */

function maskAccount(acc){
if(!acc) return "";
if(acc.length<=4) return acc;
return "XXXXXX"+acc.slice(-4);
}