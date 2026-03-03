/* ===============================
   GLOBAL STATE
================================= */
let canvas, ctx;
let drawing = false;
let tool = "pen";
let pages = [];
let undoStack = [];
const MAX_UNDO = 20;

/* ===============================
   INIT
================================= */
document.addEventListener("DOMContentLoaded", () => {
  canvas = document.getElementById("drawCanvas");

  if (canvas) {
    ctx = canvas.getContext("2d");

    canvas.addEventListener("mousedown", startDraw);
    canvas.addEventListener("mouseup", stopDraw);
    canvas.addEventListener("mouseout", stopDraw);
    canvas.addEventListener("mousemove", draw);

    canvas.addEventListener("touchstart", handleTouchStart, { passive: false });
    canvas.addEventListener("touchend", handleTouchEnd, { passive: false });
    canvas.addEventListener("touchmove", handleTouchMove, { passive: false });
  }

  document.addEventListener("keydown", handleKeyboard);
  initEditor();
});
/* ===============================
MODE SWITCHER (NOVEL / COMIC)
=============================== */

document.addEventListener("DOMContentLoaded", () => {

const mode = document.getElementById("mode");
const novel = document.getElementById("novelSection");
const comic = document.getElementById("comicSection");

function updateMode(){

if(mode.value === "comic"){
    novel.style.display = "none";
    comic.style.display = "block";
}
else{
    novel.style.display = "block";
    comic.style.display = "none";
}

}

mode.addEventListener("change", updateMode);

updateMode(); // run once on page load

});
/* ===============================
EXPORT PDF (NOVEL + COMIC)
=============================== */

function exportPDF(){

const { jsPDF } = window.jspdf;
const doc = new jsPDF();

const mode = document.getElementById("mode").value;


/* ========= NOVEL PDF ========= */

if(mode === "novel"){

const text =
document.getElementById("novelEditor").innerText || "Empty chapter";

const lines = doc.splitTextToSize(text,180);

doc.text(lines,15,20);

doc.save("chapter.pdf");

return;
}


/* ========= COMIC PDF ========= */

const canvas = document.getElementById("drawCanvas");

const img = canvas.toDataURL("image/png");

doc.addImage(img,"PNG",10,10,190,270);

doc.save("comic.pdf");

}

/* ===============================
   SAFE API REQUEST (JWT INCLUDED)
================================= */
async function apiRequest(path, method="GET", body=null){

  const token = localStorage.getItem("token");

  const res = await fetch(
    "http://127.0.0.1/ebook-platform/backend/public" + path,
    {
      method,
      headers:{
        "Content-Type":"application/json",
        "Authorization":"Bearer "+token
      },
      body: body ? JSON.stringify(body) : null
    }
  );

  return res.json();
}

/* ===============================
   KEYBOARD
================================= */
function handleKeyboard(e){
  if(e.ctrlKey && e.key==="s"){
    e.preventDefault();
    saveChapter();
  }
}

/* ===============================
   MODE SWITCH
================================= */
function switchMode(){
  const mode = document.getElementById("mode")?.value || "novel";
  document.getElementById("novelSection").style.display =
      mode==="novel"?"block":"none";
  document.getElementById("comicSection").style.display =
      mode==="comic"?"block":"none";
}

/* ===============================
   DRAWING
================================= */
function getPosition(e){
  const rect = canvas.getBoundingClientRect();
  if(e.touches){
    return {
      x:e.touches[0].clientX-rect.left,
      y:e.touches[0].clientY-rect.top
    };
  }
  return {x:e.offsetX,y:e.offsetY};
}

function startDraw(e){
  if(!ctx)return;
  drawing=true;
  const p=getPosition(e);
  ctx.beginPath();
  ctx.moveTo(p.x,p.y);
}

function stopDraw(){
  if(drawing) saveUndoState();
  drawing=false;
}

function draw(e){
  if(!drawing||!ctx)return;

  const p=getPosition(e);
  ctx.lineWidth=3;
  ctx.lineCap="round";
  ctx.strokeStyle="#000";

  ctx.lineTo(p.x,p.y);
  ctx.stroke();
  ctx.beginPath();
  ctx.moveTo(p.x,p.y);
}

function handleTouchStart(e){e.preventDefault();startDraw(e);}
function handleTouchEnd(e){e.preventDefault();stopDraw();}
function handleTouchMove(e){e.preventDefault();draw(e);}

/* ===============================
   UNDO
================================= */
function saveUndoState(){
  undoStack.push(canvas.toDataURL());
  if(undoStack.length>MAX_UNDO) undoStack.shift();
}

function undo(){
  if(!undoStack.length) return;
  const img=new Image();
  img.onload=()=>{
    ctx.clearRect(0,0,canvas.width,canvas.height);
    ctx.drawImage(img,0,0);
  };
  img.src=undoStack.pop();
}

function clearCanvas(){
  ctx.clearRect(0,0,canvas.width,canvas.height);
}

/* ===============================
   SAVE COMIC PAGE
================================= */
function savePage(){
  const data=canvas.toDataURL("image/png");
  pages.push(data);

  const img=document.createElement("img");
  img.src=data;
  img.style.width="120px";
  img.onclick=()=>{
    pages.splice(pages.indexOf(data),1);
    img.remove();
  };

  document.getElementById("comicPreview").appendChild(img);
  clearCanvas();
}

/* ===============================
   🔥 SAVE CHAPTER (FIXED)
================================= */
async function saveChapter(){

  const bookId = document.getElementById("bookId")?.value;
  const title  = document.getElementById("chapterTitle")?.value;
  const mode   = document.getElementById("mode")?.value || "novel";
  const chapterNo = document.getElementById("chapterNo")?.value || 1;
  const price  = document.getElementById("price")?.value || 0;

  if(!bookId || !title){
    alert("Book ID and Title required");
    return;
  }

  let content="";
  if(mode==="novel"){
    content=document.getElementById("novelEditor")?.innerHTML || "";
  }

  const payload={
    book_id:Number(bookId),
    title:title,
    chapter_no:Number(chapterNo),
    price:Number(price),
    type:mode,
    content:content,
    pages:mode==="comic"?pages:[]
  };

  try{

    /* ⭐⭐⭐ CORRECT ROUTE HERE */
    const res = await apiRequest("/chapters","POST",payload);


    if(res.status!=="success"){
      alert(res.message || "Save failed");
      return;
    }

    alert("✅ Chapter saved successfully");

  }catch(err){
    console.error(err);
    alert("Server error");
  }
}

/* ===============================
   LOAD CHAPTER
================================= */
async function loadChapter(id){

  const res = await apiRequest("/chapters/single?id="+id);

  if(res.status!=="success") return;

  document.getElementById("chapterTitle").value=res.data.title;
  document.getElementById("mode").value=res.data.type;

  switchMode();

  if(res.data.type==="novel"){
    document.getElementById("novelEditor").innerHTML=res.data.content;
  }else{
    pages=res.data.pages||[];
  }
}

/* ===============================
   INIT
================================= */
function initEditor(){

  switchMode();

  const params=new URLSearchParams(location.search);

  if(params.get("chapter_id")){
    loadChapter(params.get("chapter_id"));
  }

  if(params.get("book_id")){
    document.getElementById("bookId").value=params.get("book_id");
  }
}
