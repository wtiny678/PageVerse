/* ===============================
   MangaVerse FINAL STABLE READER (FIXED)
=============================== */

const token = localStorage.getItem("token") || "";
const params = new URLSearchParams(location.search);

const bookId = params.get("book_id");
const fileId = params.get("file_id");

let chapters=[];
let currentChapterIndex=0;


/* ===============================
   SAFE API
=============================== */

async function apiRequest(endpoint,method="GET",data=null){

const opt={method,headers:{}};

if(token) opt.headers["Authorization"]="Bearer "+token;

if(data){
opt.headers["Content-Type"]="application/json";
opt.body=JSON.stringify(data);
}

const res=await fetch(API_BASE+endpoint,opt);
return await res.json();
}


/* ===============================
   INIT
=============================== */

window.addEventListener("DOMContentLoaded",async()=>{

/* DIRECT FILE MODE */

if(fileId){
openPDF(`${API_BASE}/read/file?file_id=${fileId}&token=${token}`);
return;
}

/* BOOK MODE */

if(!bookId){
document.getElementById("chapterContent").innerHTML="Invalid book";
return;
}

try{

const fileCheck=await apiRequest(`/books/file?book_id=${bookId}`);

if(fileCheck.status==="success" && fileCheck.data?.id){

openPDF(`${API_BASE}/read/file?file_id=${fileCheck.data.id}&token=${token}`);
return;

}

}catch(e){}

/* CHAPTER MODE */

await loadWallet();
await loadBookInfo();
await loadChapters();
await loadBookmark();
await loadChapterContent();
await loadComments();

});


/* ===============================
   OPEN PDF (SMART VERSION)
=============================== */

function openPDF(url){

const container=document.getElementById("chapterContent");
if(!container) return;

container.innerHTML="<div style='padding:40px;text-align:center'>📖 Loading book...</div>";

const iframe=document.createElement("iframe");

iframe.src=url+"&t="+Date.now();
iframe.style.width="100%";
iframe.style.height="95vh";
iframe.style.border="none";

/* If backend returns JSON instead of PDF -> locked */
iframe.onload = () => {

try{

const doc = iframe.contentDocument || iframe.contentWindow.document;
const text = doc?.body?.innerText || "";

if(text.includes("Please purchase")){
showBuyOverlay(container);
}

}catch(e){}

};

iframe.onerror=()=>showBuyOverlay(container);

container.innerHTML="";

container.appendChild(iframe);

}

window.openPDF=openPDF;


/* ===============================
   LOCKED BOOK UI
=============================== */

function showBuyOverlay(container){

container.innerHTML=`
<div class="locked-box">

<div style="font-size:70px">🔒</div>

<h2>Book Locked</h2>

<button onclick="buyBook()" class="unlock-btn">
🪙 Buy Book
</button>

</div>
`;

}


/* ===============================
   BUY BOOK
=============================== */
async function buyBook(){

const params=new URLSearchParams(location.search);
const bookId=params.get("book_id");

if(!bookId){
alert("Invalid book");
return;
}

if(!confirm("Buy this book using wallet coins?")) return;

const res=await apiRequest("/access/buy-with-wallet","POST",{book_id:bookId});

if(res.status!=="success"){
alert(res.message);
return;
}

alert("✅ Book purchased successfully!");

location.reload();

}
window.buyBook=buyBook;


/* ===============================
   WALLET
=============================== */

async function loadWallet(){

const res=await apiRequest("/wallet/balance");

if(res.status==="success"){
document.getElementById("walletBalance").textContent=res.data.balance;
}

}


/* ===============================
   BOOK INFO
=============================== */

async function loadBookInfo(){

const res=await apiRequest(`/books/view?id=${bookId}`);
if(res.status!=="success") return;

const BASE=API_BASE.replace("/public","");

document.getElementById("bookCover").src=
res.data.cover_image
?`${BASE}/uploads/covers/${res.data.cover_image}`
:`https://via.placeholder.com/300x420?text=No+Cover`;

document.getElementById("bookTitle").textContent=res.data.title;

}


/* ===============================
   LOAD CHAPTERS
=============================== */

async function loadChapters(){

const res=await apiRequest(`/chapters/view?book_id=${bookId}`);
chapters=res.status==="success"?res.data:[];

const list=document.getElementById("chapterList");
if(!list) return;

list.innerHTML="";

chapters.forEach((ch,i)=>{

const d=document.createElement("div");
d.className="chapter";
d.innerText=(ch.chapter_no||"")+" "+ch.title;

d.onclick=()=>{
currentChapterIndex=i;
loadChapterContent();
saveBookmark();
};

list.appendChild(d);

});

}


/* ===============================
   LOAD CHAPTER CONTENT
=============================== */

async function loadChapterContent(){

const container=document.getElementById("chapterContent");
const ch=chapters[currentChapterIndex];

if(!ch){
container.innerHTML="No chapter";
return;
}

const res=await fetch(
`${API_BASE}/read/chapter?chapter_id=${ch.id}&token=${token}`
);

const data=await res.json();

if(data.status!=="success"){

container.innerHTML=`
<div class="locked-box">
<h2>${ch.title}</h2>
<p>Preview unavailable</p>
<button class="unlock-btn" onclick="buyCurrentChapter()">UNLOCK</button>
</div>
`;

return;
}

container.innerHTML=`
<h2>${data.data.title}</h2>
<div style="line-height:1.8;white-space:pre-wrap">
${data.data.content}
</div>
`;

}


/* ===============================
   BUY CHAPTER
=============================== */

async function buyCurrentChapter(){

if(!token){
alert("Login required");
return;
}

const ch=chapters[currentChapterIndex];
if(!ch) return;

const res=await apiRequest("/purchase/chapter","POST",{chapter_id:ch.id});

if(res.status==="success"){

startCoinRain(40);
await loadWallet();
loadChapterContent();

}else{

alert(res.message||"Purchase failed");

}

}

window.buyCurrentChapter=buyCurrentChapter;


/* ===============================
   COIN FX
=============================== */

function startCoinRain(n=40){

for(let i=0;i<n;i++){

setTimeout(()=>{

const c=document.createElement("div");
c.className="coin";
c.innerText="💰";
c.style.left=Math.random()*100+"vw";
c.style.animationDuration=(1+Math.random()*2)+"s";

document.body.appendChild(c);
setTimeout(()=>c.remove(),3000);

},i*40);

}

}