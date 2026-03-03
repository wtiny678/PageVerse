/* ===============================
   MangaVerse SEARCH SCRIPT (FINAL CLEAN)
=============================== */

const BASE_UPLOAD = "http://localhost/ebook-platform/backend";

/* ===============================
   AUTO ENTER SEARCH
=============================== */

document.addEventListener("DOMContentLoaded", () => {

  const input = document.getElementById("query");

  if(input){
    input.addEventListener("keydown", e=>{
      if(e.key==="Enter") searchBooks();
    });
  }

});


/* ===============================
   MAIN SEARCH
=============================== */

async function searchBooks(){

  const q = document.getElementById("query").value.trim();
  const results = document.getElementById("results");

  if(!q){
    alert("Enter search text");
    return;
  }

  results.innerHTML="<p>Searching...</p>";

  try{

    const data = await apiRequest(`/search/books?q=${encodeURIComponent(q)}`);

    results.innerHTML="";

    if(data.status!=="success" || !data.data.length){
      results.innerHTML="<p>No books found</p>";
      return;
    }

    data.data.forEach(book=>{

      const cover = book.cover_image
        ? `${BASE_UPLOAD}/uploads/covers/${book.cover_image}`
        : "https://via.placeholder.com/200";

      const card=document.createElement("div");
      card.className="card";

      card.innerHTML=`
        <img src="${cover}">
        <h4>${book.title}</h4>
        <p>${book.author_name||"Unknown Author"}</p>
        <div style="margin-top:6px;color:#22c55e;font-weight:600;">
          ₹${book.price||0}
        </div>
        <button class="readBtn">Read</button>
      `;

      /* ===============================
         CLICK → ALWAYS OPEN BY BOOK ID
      =============================== */

      card.querySelector(".readBtn").onclick = () => {

        // author profile redirect (keep if needed)
        if(book.source==="user"){
          location.href="profile.html?user_id="+book.id;
          return;
        }

        // ⭐ ALWAYS OPEN READER USING BOOK ID
        location.href="reader.html?book_id="+book.id;

      };

      results.appendChild(card);

    });

  }
  catch(err){
    console.error(err);
    results.innerHTML="<p>Server error</p>";
  }

}