/* ================= GET BOOK ID ================= */

const params=new URLSearchParams(location.search);
const bookId=params.get("book_id");

document.addEventListener("DOMContentLoaded",loadBook);


/* ================= LOAD BOOK ================= */

async function loadBook(){

try{

if(!bookId){
alert("Invalid book");
location.href="dashboard.html";
return;
}

const res=await apiRequest(`/books/view?id=${bookId}`);

if(res.status!=="success"){
alert("Book not found");
location.href="dashboard.html";
return;
}

const b=res.data||{};

document.getElementById("bookTitle").innerText=b.title||"Book";
document.getElementById("bookPrice").innerText="₹"+
Number(b.price||0).toLocaleString("en-IN");

}catch(err){

console.error(err);
alert("Failed to load book");
location.href="dashboard.html";

}

}



/* ================= BUY (FAKE PAYMENT FINAL) ================= */

async function payNow(){

const status=document.getElementById("paymentStatus");

/* ⭐ TARGET CORRECT BUY BUTTON */
const btn=document.getElementById("buyBtn");

if(!confirm("Proceed with payment?")) return;

/* disable button */

btn.disabled=true;
btn.innerText="Processing...";

status.innerText="Processing payment...";
status.className="status";

try{

const res=await apiRequest("/payments/fake","POST",{book_id:bookId});

if(res.status!=="success"){
status.innerText=res.message||"Payment failed";
status.className="status error";
btn.disabled=false;
btn.innerText="Buy Now";
return;
}

/* SUCCESS */

status.innerText="✅ Payment successful!";
status.className="status success";

/* COIN RAIN */
if(typeof startCoinRain==="function"){
startCoinRain(60);
}

/* SINGLE REDIRECT ONLY */

setTimeout(()=>{
location.href="reader.html?book_id="+bookId;
},1500);

}catch(err){

console.error(err);

status.innerText="Server error";
status.className="status error";
btn.disabled=false;
btn.innerText="Buy Now";

}

}