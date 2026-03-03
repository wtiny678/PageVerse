/* ===============================
   frontend/js/api.js
   Stable API helper (JWT safe)
================================= */

const API_BASE = "http://127.0.0.1/ebook-platform/backend/public";



/**
 * Generic API request
 */
async function apiRequest(endpoint, method = "GET", data = null) {
  const token = localStorage.getItem("token");
  const url = API_BASE + endpoint; // ✅ FIXED

  const options = {
    method: method.toUpperCase(),
    headers: {
      "Accept": "application/json"
    }
  };

  // JSON body
  if (!(data instanceof FormData) && data !== null && options.method !== "GET") {
    options.headers["Content-Type"] = "application/json";
    options.body = JSON.stringify(data);
  }

  // FormData body
  if (data instanceof FormData) {
    options.body = data;
  }

  // JWT header
  if (token) {
    options.headers["Authorization"] = "Bearer " + token;
  }

  try {
    const res = await fetch(url, options);

    let json;
    try {
      json = await res.json();
    } catch (e) {
      return { status: "error", message: "Invalid JSON from server", data: null };
    }

    // 🔴 TOKEN INVALID → FORCE LOGOUT
    if (res.status === 401) {
      localStorage.removeItem("token");
      alert("Session expired. Please login again.");
      window.location.href = "login.html";
      return;
    }

    return json;

  } catch (err) {
    console.error("NETWORK ERROR:", err);
    return { status: "error", message: "Cannot connect to server", data: null };
  }
}

/* ===============================
   Upload helper
================================= */
async function uploadFile(endpoint, formData) {
  return await apiRequest(endpoint, "POST", formData);
}

/* ===============================
   Token helpers
================================= */
function saveToken(token) {
  localStorage.setItem("token", token);
}
function getToken() {
  return localStorage.getItem("token");
}
function logout() {
  localStorage.removeItem("token");
  window.location.href = "login.html";
}

/* ===============================
   Auth helpers
================================= */
async function checkAuth() {
  const res = await apiRequest("/auth/profile");
  return res && res.status === "success";
}

async function getProfile() {
  return await apiRequest("/auth/profile");
}
