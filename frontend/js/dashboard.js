(() => {

/* ===============================
   CONFIG
=============================== */

const API_BASE = "http://127.0.0.1/ebook-platform/backend/public";
const token = localStorage.getItem("token");

if(!token) window.location.href="login.html";
let uploadedFiles = [];
/* ===============================
   API HELPER
=============================== */

async function apiRequest(endpoint,method="GET",data=null){

  const headers={ "Authorization":"Bearer "+token };

  let body=null;

  if(data instanceof FormData){
    body=data;
  }
  else if(data){
    headers["Content-Type"]="application/json";
    body=JSON.stringify(data);
  }

  const res=await fetch(API_BASE+endpoint,{method,headers,body});
  const json=await res.json().catch(()=>null);

  if(!json) throw new Error("Invalid JSON");

  return json;
}

/* ===============================
   INIT
=============================== */

document.addEventListener("DOMContentLoaded",loadDashboard);


/* ===============================
   LOAD DASHBOARD
=============================== */

async function loadDashboard(){

  const res=await apiRequest("/dashboard/load");

  if(res.status!=="success"){
    localStorage.removeItem("token");
    return location.href="login.html";
  }

  const d=res.data;
  uploadedFiles = d.uploaded_files || [];


  document.getElementById("username").innerText=d.user?.name||"User";
  document.getElementById("role").innerText=d.mode||"reader";
  document.getElementById("wallet").innerText="₹"+(d.wallet_balance||0);

 // SHOW UPLOAD SECTION ONLY FOR AUTHOR
if(d.mode === "author"){
  document.getElementById("uploadBookSection").style.display="block";
}else{
  document.getElementById("uploadBookSection").style.display="none";
}


  loadMyBooks(d.books||[]);
  loadMyLibrary();
  loadBookmarks();
}
function updateWalletAnimated(add){

const wallet=document.getElementById("wallet");
if(!wallet) return;

let raw=(wallet.innerText||"0").replace(/[^\d]/g,"");
let current=parseInt(raw)||0;

let target=current+add;
let i=current;

const timer=setInterval(()=>{

i+=Math.ceil((target-current)/20);

wallet.innerText="₹"+Math.min(i,target);

if(i>=target) clearInterval(timer);

},30);

}
/* ===============================
   AUTHOR BOOKS PANEL
=============================== */

function loadMyBooks(books){

  const c=document.getElementById("myBooks");
  c.innerHTML="";

  if(!books.length){
    c.innerHTML="<p>No books yet</p>";
    return;
  }

  books.forEach(book=>{

    const cover=
      API_BASE.replace("/public","")+
      "/uploads/covers/"+(book.cover_image||"");

    const div=document.createElement("div");
    div.className="card";

    div.innerHTML=`

      <img src="${cover}">
      <p>${book.title}</p>
      <small>₹${Number(book.price||0).toFixed(2)}</small>

      <div class="book-actions">

        <button class="action-btn read-btn">📖 Read</button>

        <button class="action-btn">✍️ Add Chapter</button>

        <button class="action-btn">📑 Chapters</button>

        <button class="action-btn">💰 Earnings</button>

        <button class="action-btn delete-btn">🗑 Delete</button>

      </div>
    `;

    const b=div.querySelectorAll("button");

   b[0].onclick=()=>openReader(book);
    b[1].onclick=()=>openEditor(book.id);
    b[2].onclick=()=>viewChapters(book.id);
    b[3].onclick=()=>openEarnings(book.id);
    b[4].onclick=()=>showDeleteModal(book,div);

    c.appendChild(div);
  });
}
let deleteBookId=null;
let deleteCard=null;

function showDeleteModal(book,card){

deleteBookId=book.id;
deleteCard=card;

/* SHAKE + RED GLOW */
card.classList.add("delete-warning");
setTimeout(()=>card.classList.remove("delete-warning"),400);

/* SET COVER */
const cover=
API_BASE.replace("/public","")+
"/uploads/covers/"+(book.cover_image||"");

document.getElementById("deleteCover").src=cover;

/* SHOW MODAL */
document.getElementById("deleteModal").style.display="flex";

}

function closeDeleteModal(){
document.getElementById("deleteModal").style.display="none";
}

async function confirmDeleteBook(){

if(!deleteBookId) return;

/* DELETE FROM SERVER */
const res=await apiRequest("/books/delete","POST",{book_id:deleteBookId});

if(res.status!=="success"){
alert(res.message);
return;
}

/* FADE OUT CARD */
deleteCard.classList.add("fade-remove");

setTimeout(()=>{
deleteCard.remove();
},450);

closeDeleteModal();

}
/* ===============================
   LIBRARY
=============================== */

async function loadMyLibrary(){

  const res=await apiRequest("/dashboard/library");

  const books=res.data||[];

  const c=document.getElementById("myLibrary");
  c.innerHTML="";

  if(!books.length){
    c.innerHTML="<p>No purchases</p>";
    return;
  }

  books.forEach(book=>{

    const cover=
      API_BASE.replace("/public","")+
      "/uploads/covers/"+(book.cover_image||"");

    const div=document.createElement("div");
    div.className="card";

    div.innerHTML=`
      <img src="${cover}">
      <p>${book.title}</p>
      <button class="action-btn read-btn">📖 Read</button>
      <button class="action-btn">🔖 Bookmark</button>
    `;

    div.children[2].onclick=()=>openReader(book);
    div.children[3].onclick=()=>bookmarkBook(book.id);

    c.appendChild(div);
  });
}

/* ===============================
   BOOKMARKS
=============================== */

async function loadBookmarks(){

  const res=await apiRequest("/bookmarks/library");

  const books=res.data||[];
  const c=document.getElementById("myBookmarks");
  if(!c) return;

  c.innerHTML="";

  books.forEach(book=>{

    const cover=
      API_BASE.replace("/public","")+
      "/uploads/covers/"+(book.cover_image||"");

    const div=document.createElement("div");
    div.className="card";

    div.innerHTML=`
      <img src="${cover}">
      <p>${book.title}</p>
      <button class="action-btn read-btn">📖 Read</button>
      <button class="action-btn delete-btn">❌ Remove</button>
    `;

    div.children[2].onclick=()=>openReader(book.book_id);
    div.children[3].onclick=()=>removeBookmark(book.book_id);

    c.appendChild(div);
  });
}

/* ===============================
   NAVIGATION
=============================== */

function openReader(book){

  if(!book) return;

  const id = typeof book === "object" ? book.id : book;

  const file = uploadedFiles.find(f => f.book_id == id);

  if(file){
    location.href = "reader.html?file_id=" + file.id;
  }
  else{
    location.href = "reader.html?book_id=" + id;
  }
}

function openEditor(id){
  location.href="editor.html?book_id="+id;
}

function viewChapters(id){
  location.href="chapters.html?book_id="+id;
}

function openEarnings(id){
  location.href="earnings.html?book_id="+id;
}

window.openReader=openReader;


/* ===============================
   DELETE BOOK
=============================== */

async function deleteBook(id){

  if(!confirm("Delete book?")) return;

  const res=await apiRequest("/books/delete","POST",{book_id:id});

  if(res.status!=="success") return alert(res.message);

  alert("Deleted");
  loadDashboard();
}

/* ===============================
   UPLOAD BOOK
=============================== */

async function uploadBook(){

  const title=document.getElementById("newBookTitle").value.trim();
  const price=document.getElementById("newBookPrice").value||0;
  const type=document.getElementById("newBookType").value;

  const cover=document.getElementById("newBookCover").files[0];
  const file=document.getElementById("newBookFile").files[0];

  if(!title||!type||!cover||!file)
    return alert("Fill all fields");

  const fd=new FormData();
  fd.append("title",title);
  fd.append("price",price);
  fd.append("type",type);
  fd.append("cover_image",cover);
  fd.append("file",file);

  const res=await apiRequest("/books/create","POST",fd);

  if(res.status!=="success") return alert(res.message);

  alert("Uploaded!");
  loadDashboard();
}

window.uploadBook=uploadBook;

/* ===============================
   BOOKMARK
=============================== */

async function bookmarkBook(id){
  await apiRequest("/bookmarks/save","POST",{book_id:id});
  alert("Bookmarked");
}

/* ===============================
   REMOVE BOOKMARK
=============================== */

async function removeBookmark(id){
  await apiRequest("/bookmarks/remove","POST",{book_id:id});
  loadBookmarks();
}

/* ===============================
   SWITCH MODE
=============================== */
async function switchMode(){

  const current=document.getElementById("role").innerText.trim();

  const mode = current==="reader" ? "author" : "reader";

  const res = await apiRequest("/dashboard/switch-mode","POST",{mode});

  if(res.status!=="success"){
    alert(res.message);
    return;
  }

  // reload dashboard to refresh UI + permissions
  await loadDashboard();
const modal=document.getElementById("createBookModal");
if(modal) modal.style.display="none";

}

window.switchMode=switchMode;


/* ===============================
   LOGOUT
=============================== */

function logout(){
  localStorage.removeItem("token");
  location.href="login.html";
}

window.logout=logout;
/* ===============================
   CREATE BOOK MODAL
=============================== */

function openBookModal(){
 document.getElementById("createBookModal").style.display="flex";
}

function closeBookModal(){
 document.getElementById("createBookModal").style.display="none";
}

window.openBookModal=openBookModal;
window.closeBookModal=closeBookModal;


/* ===============================
   CREATE BOOK FROM MODAL
=============================== */

async function createBookFromModal(){

  const title=document.getElementById("mTitle").value.trim();
  const price=document.getElementById("mPrice").value||0;
  const type=document.getElementById("mType").value;

  // ⭐ ONLY REQUIRE TITLE + TYPE
  if(!title || !type){
    alert("Title and type required");
    return;
  }

  const data={
    title:title,
    price:price,
    type:type
  };

  const res=await apiRequest("/books/create","POST",data);

  if(res.status!=="success"){
    alert(res.message);
    return;
  }

  alert("Book created successfully!");

  closeBookModal();
  loadDashboard();
}
window.createBookFromModal=createBookFromModal;
/* ===============================
BOOK COVER UPLOAD (CLICK + DRAG)
=============================== */

/* ===============================
BOOK COVER UPLOAD (CLICK + DRAG)
=============================== */

document.addEventListener("DOMContentLoaded",()=>{

const drop=document.getElementById("coverDrop");
const input=document.getElementById("cCover");
const preview=document.getElementById("cPreview");

if(!drop || !input) return;

/* CLICK */
drop.onclick=()=>input.click();

/* FILE SELECT */
input.onchange=e=>{

const file=e.target.files[0];
if(!file) return;

const reader=new FileReader();

reader.onload=x=>{
preview.src=x.target.result;
preview.style.display="block";
};

reader.readAsDataURL(file);

};

/* DRAG */
drop.ondragover=e=>{
e.preventDefault();
drop.style.borderColor="#22c55e";
};

drop.ondragleave=()=>{
drop.style.borderColor="#38bdf8";
};

drop.ondrop=e=>{
e.preventDefault();

const file=e.dataTransfer.files[0];
if(!file) return;

input.files=e.dataTransfer.files;
input.onchange({target:{files:e.dataTransfer.files}});
};

});


function previewCover(event){

const file = event.target.files[0];
if(!file) return;

const reader = new FileReader();

reader.onload = e=>{
document.getElementById("coverPreview").src = e.target.result;
document.getElementById("coverPreview").style.display="block";
};

reader.readAsDataURL(file);

}

})();
